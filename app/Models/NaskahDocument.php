<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Publication\NaskahRichModel;
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
        'comments',
        'revisions',
        'page_setup',
        'style_meta',
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
            'comments' => 'array',
            'revisions' => 'array',
            'page_setup' => 'array',
            'style_meta' => 'array',
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
     * Naskah sebagai teks polos (judul + isi blok).
     */
    public function toPlainText(): string
    {
        return app(NaskahRichModel::class)
            ->toPlainText(app(NaskahRichModel::class)->normalize((array) $this->blocks));
    }

    /**
     * Hitung jumlah kata dari seluruh blok (termasuk sel tabel).
     */
    public function computeWordCount(): int
    {
        $model = app(NaskahRichModel::class);

        return $model->countWords($model->normalize((array) $this->blocks));
    }

    /**
     * Komentar dokumen (default array kosong).
     *
     * @return array<int, array<string, mixed>>
     */
    public function commentsList(): array
    {
        return (array) ($this->comments ?? []);
    }

    /**
     * Revisi/track changes (default array kosong).
     *
     * @return array<int, array<string, mixed>>
     */
    public function revisionsList(): array
    {
        return (array) ($this->revisions ?? []);
    }
}
