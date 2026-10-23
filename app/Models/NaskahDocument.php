<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NaskahDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NaskahDocument: dokumen naskah yang disunting di Naskah Studio.
 *
 * Sumbernya bisa berkas lokal yang diunggah (source_type = local) atau
 * diambil dari tahap sebuah proyek Xishie (source_type = project).
 * Isi disimpan sebagai blok terstruktur agar bisa dirender seperti
 * word processor (judul, sub-judul, paragraf, kutipan, daftar).
 */
class NaskahDocument extends Model
{
    /** @use HasFactory<NaskahDocumentFactory> */
    use HasFactory;

    public const SOURCE_LOCAL = 'local';

    public const SOURCE_PROJECT = 'project';

    /** @var array<int, string> */
    public const BLOCK_TYPES = ['heading', 'paragraph', 'quote', 'bullet', 'numbered'];

    protected $table = 'naskah_documents';

    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'source_type',
        'source_stage',
        'source_filename',
        'blocks',
        'word_count',
        'last_saved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'word_count' => 'integer',
            'last_saved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<XishieProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(XishieProject::class, 'project_id');
    }

    /**
     * Naskah sebagai dokumen polos (judul + isi blok).
     */
    public function toPlainText(): string
    {
        $parts = [];

        foreach ($this->blocks as $block) {
            $text = trim((string) ($block['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'heading') {
                $level = (int) ($block['level'] ?? 1);
                $parts[] = str_repeat('#', max(1, min(3, $level))).' '.$text;
            } elseif ($type === 'bullet') {
                $parts[] = '- '.$text;
            } elseif ($type === 'numbered') {
                $parts[] = '1. '.$text;
            } elseif ($type === 'quote') {
                $parts[] = '> '.$text;
            } else {
                $parts[] = $text;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Hitung jumlah kata dari seluruh blok.
     */
    public function computeWordCount(): int
    {
        $total = 0;

        foreach ($this->blocks as $block) {
            $total += str_word_count(strip_tags((string) ($block['text'] ?? '')));
        }

        return $total;
    }
}
