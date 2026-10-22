<?php

declare(strict_types=1);

namespace App\Omnigensi\Memory;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Pencarian semantik hibrida di atas MySQL standar (tanpa pgvector):
 *
 *  1. Penyaringan kandidat cepat via FULLTEXT (MATCH ... AGAINST) bila tersedia.
 *  2. Perhitungan presisi Cosine Similarity di level PHP atas vektor JSON.
 *
 * Bila FULLTEXT tidak tersedia (mis. SQLite pada test), fallback ke LIKE
 * sehingga layanan tetap dapat diuji tanpa MySQL.
 */
final class HybridVectorMatcherService
{
    private const DIMENSION = 1536;

    /**
     * Cari kandidat paling relevan dari sebuah model yang memiliki kolom
     * teks (FULLTEXT) dan kolom embedding JSON.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<int, float>  $queryVector
     * @param  array<int, string>  $fullTextColumns
     * @param  array<string, mixed>  $filters  pasangan kolom => nilai untuk WHERE
     * @return array<int, array{model: TModel, score: float}>
     */
    public function search(
        string $modelClass,
        string $queryText,
        array $queryVector,
        array $fullTextColumns = ['title', 'abstract'],
        string $embeddingColumn = 'embedding',
        int $limit = 10,
        array $filters = [],
        int $candidateLimit = 200,
    ): array {
        $query = $modelClass::query();

        foreach ($filters as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        $candidates = $this->fetchCandidates($query, $queryText, $fullTextColumns, $candidateLimit);

        $scored = [];

        foreach ($candidates as $candidate) {
            $vector = $this->normalizeVector((array) ($candidate->{$embeddingColumn} ?? []));

            if ($vector === []) {
                continue;
            }

            $scored[] = [
                'model' => $candidate,
                'score' => $this->cosineSimilarity($queryVector, $vector),
            ];
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Cosine similarity antara dua vektor.
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $length = min(count($a), count($b));

        if ($length === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];

            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Bangun embedding deterministik (hash-based) sepanjang DIMENSION.
     *
     * Catatan: implementasi ini dipakai sebagai *stand-in* offline yang stabil
     * agar pipeline dan pengujian dapat berjalan tanpa panggilan API eksternal.
     * Menggunakan unigram + bigram berbobot agar kemiripan semantik lebih
     * diskriminatif untuk istilah multi-kata (mis. "machine learning").
     * Di produksi, vektor dari model embedding dapat langsung disimpan ke
     * kolom JSON yang sama tanpa mengubah pemanggil.
     *
     * @return array<int, float>
     */
    public function embed(string $text, int $dimension = self::DIMENSION): array
    {
        $vector = array_fill(0, $dimension, 0.0);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= 3,
        ));

        $this->accumulate($vector, $tokens, $dimension, 1.0);

        // Bigram memberi bobot lebih pada frasa bermakna.
        $bigrams = [];

        for ($i = 0, $count = count($tokens) - 1; $i < $count; $i++) {
            $bigrams[] = $tokens[$i].'_'.$tokens[$i + 1];
        }

        $this->accumulate($vector, $bigrams, $dimension, 0.6);

        return $this->normalizeVector($vector);
    }

    /**
     * @param  array<int, float>  $vector
     * @param  array<int, string>  $tokens
     */
    private function accumulate(array &$vector, array $tokens, int $dimension, float $weight): void
    {
        // Frekuensi token dalam dokumen (TF lokal) untuk meredam pengulangan.
        $frequency = array_count_values($tokens);

        foreach ($frequency as $token => $count) {
            $token = (string) $token;

            // Bobot sub-linear TF: 1 + log(tf).
            $tf = 1.0 + log((float) $count);

            $hash = crc32($token);
            $sign = (($hash >> 1) & 1) === 1 ? 1.0 : -1.0;

            // Sebarkan ke dua dimensi agar tetangga leksikal berbagi massa vektor
            // (mengurangi tumbukan hash acak yang membuat semua skor ~1.0).
            $primary = $hash % $dimension;
            $secondary = (int) (($hash >> 7) % $dimension);

            $vector[$primary] += $sign * $weight * $tf;
            $vector[$secondary] += $sign * $weight * $tf * 0.5;
        }
    }

    /**
     * @param  array<int, float|int>  $vector
     * @return array<int, float>
     */
    private function normalizeVector(array $vector): array
    {
        return array_values(array_map(static fn ($value): float => (float) $value, $vector));
    }

    /**
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    private function fetchCandidates(Builder $query, string $queryText, array $columns, int $candidateLimit): Collection
    {
        $connection = $query->getModel()->getConnection();
        $driver = $connection->getDriverName();
        $naturalQuery = $this->toNaturalLanguageQuery($queryText);

        if ($driver === 'mysql' && $naturalQuery !== '') {
            $matchColumns = implode(', ', array_map(
                static fn (string $column): string => "`{$column}`",
                $columns,
            ));

            // Klon query agar filter FULLTEXT tidak mencemari fallback.
            $fullTextQuery = clone $query;

            $result = $fullTextQuery
                ->whereRaw("MATCH ({$matchColumns}) AGAINST (? IN NATURAL LANGUAGE MODE)", [$naturalQuery])
                ->limit($candidateLimit)
                ->get();

            if ($result->isNotEmpty()) {
                return $result;
            }

            // InnoDB FULLTEXT tidak mengindeks baris yang belum di-commit
            // (mis. di dalam transaksi test), sehingga fallback tetap diperlukan.
        }

        // Fallback: pencocokan per token (MySQL non-FULLTEXT / SQLite / transaksi).
        $tokens = array_slice($this->toTokens($queryText), 0, 5);
        $fallback = clone $query;

        if ($tokens !== []) {
            $fallback->where(function (Builder $nested) use ($tokens, $columns): void {
                foreach ($tokens as $token) {
                    $nested->orWhere(function (Builder $inner) use ($token, $columns): void {
                        foreach ($columns as $column) {
                            $inner->orWhere($column, 'like', '%'.$token.'%');
                        }
                    });
                }
            });

            $matched = $fallback->limit($candidateLimit)->get();

            if ($matched->isNotEmpty()) {
                return $matched;
            }
        }

        // Tidak ada token yang cocok (mis. kueri berbahasa berbeda dari korpus):
        // tetap kembalikan kandidat dari filter yang diberikan agar pemanggil
        // dapat melakukan peringkat cosine atas seluruh korpus proyek.
        return (clone $query)->limit($candidateLimit)->get();
    }

    private function toNaturalLanguageQuery(string $text): string
    {
        $tokens = array_slice($this->toTokens($text), 0, 12);

        return implode(' ', $tokens);
    }

    /**
     * @return array<int, string>
     */
    private function toTokens(string $text): array
    {
        $stopwords = ['the', 'and', 'for', 'with', 'this', 'that', 'dari', 'dan', 'yang', 'untuk', 'pada', 'dengan'];

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, $stopwords, true),
        ));
    }
}
