<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\GuidelineEngine\GuidelineResolverService;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\ProjectReference;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * ManuscriptExporter: hasilkan naskah siap kirim dalam berbagai format
 * (Markdown, LaTeX, BibTeX, teks polos, dan DOCX) — PRD KPI #15
 * "Manuscript Export Conversion".
 *
 * DOCX dihasilkan tanpa dependensi eksternal memakai OOXML minimal yang valid
 * melalui ZipArchive, sehingga pengguna langsung bisa mengunduh berkas .docx
 * yang terbuka di Word/LibreOffice/Google Docs.
 */
final class ManuscriptExporter
{
    public const FORMAT_MARKDOWN = 'markdown';

    public const FORMAT_LATEX = 'latex';

    public const FORMAT_BIBTEX = 'bibtex';

    public const FORMAT_TEXT = 'text';

    public const FORMAT_DOCX = 'docx';

    public const FORMAT_ODT = 'odt';

    public const FORMAT_HTML = 'html';

    public const FORMAT_JSON = 'json';

    public function __construct(
        private readonly CitationFormatter $citationFormatter,
    ) {}

    /**
     * Daftar format yang didukung beserta label, ekstensi, dan MIME type.
     *
     * @return array<string, array{label: string, extension: string, mime: string}>
     */
    public function formats(): array
    {
        return [
            self::FORMAT_DOCX => ['label' => 'Word (DOCX)', 'extension' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            self::FORMAT_ODT => ['label' => 'OpenDocument (ODT)', 'extension' => 'odt', 'mime' => 'application/vnd.oasis.opendocument.text'],
            self::FORMAT_MARKDOWN => ['label' => 'Markdown', 'extension' => 'md', 'mime' => 'text/markdown'],
            self::FORMAT_LATEX => ['label' => 'LaTeX', 'extension' => 'tex', 'mime' => 'application/x-tex'],
            self::FORMAT_HTML => ['label' => 'HTML (Web/Cetak)', 'extension' => 'html', 'mime' => 'text/html'],
            self::FORMAT_JSON => ['label' => 'JSON (Struktur Data)', 'extension' => 'json', 'mime' => 'application/json'],
            self::FORMAT_BIBTEX => ['label' => 'BibTeX', 'extension' => 'bib', 'mime' => 'application/x-bibtex'],
            self::FORMAT_TEXT => ['label' => 'Teks Polos', 'extension' => 'txt', 'mime' => 'text/plain'],
        ];
    }

    public function isSupported(string $format): bool
    {
        return array_key_exists($format, $this->formats());
    }

    /**
     * Nama berkas unduhan (aman untuk filesystem).
     */
    public function filename(XishieProject $project, string $format): string
    {
        $base = Str::slug((string) $project->title) ?: 'naskah';
        $extension = $this->formats()[$format]['extension'] ?? 'txt';

        return $base.'.'.$extension;
    }

    /**
     * Render naskah ke format yang diminta.
     *
     * @param  array<string, mixed>|null  $adapted  Hasil ManuscriptAdapter (opsional)
     */
    public function render(XishieProject $project, string $format, ?ProjectStage $stage = null, ?array $adapted = null): string
    {
        if (! $this->isSupported($format)) {
            throw new RuntimeException("Format ekspor tidak didukung: {$format}.");
        }

        $manuscript = $this->buildManuscript($project, $stage, $adapted);

        return match ($format) {
            self::FORMAT_MARKDOWN => $this->toMarkdown($manuscript),
            self::FORMAT_LATEX => $this->toLatex($manuscript),
            self::FORMAT_HTML => $this->toHtml($manuscript),
            self::FORMAT_JSON => $this->toJson($manuscript),
            self::FORMAT_BIBTEX => $this->toBibtex($project),
            self::FORMAT_TEXT => $this->toText($manuscript),
            self::FORMAT_DOCX => $this->toDocx($manuscript),
            self::FORMAT_ODT => $this->toOdt($manuscript),
        };
    }

    /**
     * Susun struktur naskah yang dipakai semua format teks.
     *
     * @param  array<string, mixed>|null  $adapted
     * @return array{
     *     title: string,
     *     abstract: string,
     *     keywords: array<int, string>,
     *     sections: array<int, array{heading: string, content: string}>,
     *     bibliography: array<int, string>,
     *     citation_style: string
     * }
     */
    public function buildManuscript(XishieProject $project, ?ProjectStage $stage = null, ?array $adapted = null): array
    {
        $record = $this->resolveStage($project, $stage);
        $guideline = app(GuidelineResolverService::class)->resolve($project);
        $style = CitationStyle::tryFrom($guideline->citationStyle) ?? CitationStyle::APA7;

        $sections = $this->sectionsFrom($record);
        $abstract = $this->abstractFrom($sections);

        // Bila ada naskah hasil adaptasi venue atau ekstraksi naskah jurnal,
        // pakai isinya sebagai sumber.
        if (is_array($adapted)) {
            $compiledText = $adapted['manuscript'] ?? $adapted['compiled'] ?? null;
            if (is_string($compiledText) && trim($compiledText) !== '') {
                $sections = $this->sectionsFromCompiled($compiledText);
                $abstract = $this->abstractFrom($sections);
            }
        }

        return [
            'title' => (string) $project->title,
            'abstract' => $abstract,
            'keywords' => $this->keywords($project, $abstract),
            'sections' => $sections,
            'bibliography' => $this->citationFormatter->bibliography(
                $project->references()->orderBy('citation_key')->get(),
                $style,
            ),
            'citation_style' => $style->value,
        ];
    }

    private function resolveStage(XishieProject $project, ?ProjectStage $stage): ?ProjectStageRecord
    {
        $stage ??= $project->current_stage;

        return $project->stages()->where('stage_name', $stage->value)->first()
            ?? $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();
    }

    /**
     * @return array<int, array{heading: string, content: string}>
     */
    private function sectionsFrom(?ProjectStageRecord $record): array
    {
        $sections = [];

        foreach ((array) ($record?->sections_content ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $sections[] = [
                'heading' => (string) ($section['heading'] ?? ''),
                'content' => trim((string) ($section['content'] ?? '')),
            ];
        }

        return $sections;
    }

    /**
     * Pecah naskah gabungan (Markdown) menjadi sections.
     *
     * @return array<int, array{heading: string, content: string}>
     */
    private function sectionsFromCompiled(string $compiled): array
    {
        $parts = preg_split('/^##\s+/mu', trim($compiled)) ?: [];
        $sections = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $lines = explode("\n", $part, 2);
            $sections[] = [
                'heading' => trim($lines[0]),
                'content' => trim($lines[1] ?? ''),
            ];
        }

        return $sections;
    }

    /**
     * @param  array<int, array{heading: string, content: string}>  $sections
     */
    private function abstractFrom(array $sections): string
    {
        foreach ($sections as $section) {
            if (stripos($section['heading'], 'abstrak') !== false || stripos($section['heading'], 'abstract') !== false) {
                return $section['content'];
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function keywords(XishieProject $project, string $abstract): array
    {
        $base = trim($project->field_of_study->label().' '.$project->document_type->label());
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($abstract)) ?: [];
        $stop = ['dan', 'yang', 'untuk', 'dengan', 'pada', 'the', 'and', 'for', 'this', 'that', 'dari'];

        $freq = [];

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 4 || in_array($token, $stop, true)) {
                continue;
            }

            $freq[$token] = ($freq[$token] ?? 0) + 1;
        }

        arsort($freq);

        return array_slice(array_keys($freq), 0, 5) ?: array_filter([$base]);
    }

    /**
     * @param  array{title: string, abstract: string, keywords: array<int, string>, sections: array<int, array{heading: string, content: string}>, bibliography: array<int, string>, citation_style: string}  $m
     */
    private function toMarkdown(array $m): string
    {
        $out = '# '.$m['title']."\n\n";

        if ($m['abstract'] !== '') {
            $out .= "## Abstrak\n\n".$m['abstract']."\n\n";
        }

        if ($m['keywords'] !== []) {
            $out .= '**Kata kunci:** '.implode(', ', $m['keywords'])."\n\n";
        }

        foreach ($m['sections'] as $section) {
            $out .= '## '.$section['heading']."\n\n".$section['content']."\n\n";
        }

        if ($m['bibliography'] !== []) {
            $out .= "## Daftar Pustaka\n\n";
            foreach ($m['bibliography'] as $entry) {
                $out .= '- '.$entry."\n";
            }
        }

        return rtrim($out)."\n";
    }

    /**
     * @param  array{title: string, abstract: string, keywords: array<int, string>, sections: array<int, array{heading: string, content: string}>, bibliography: array<int, string>, citation_style: string}  $m
     */
    private function toHtml(array $m): string
    {
        $escapedTitle = htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8');
        $html = "<!DOCTYPE html>\n<html lang=\"id\">\n<head>\n";
        $html .= "    <meta charset=\"utf-8\">\n";
        $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
        $html .= "    <title>{$escapedTitle}</title>\n";
        $html .= "    <style>\n";
        $html .= "        body { font-family: 'Times New Roman', Georgia, serif; line-height: 1.6; max-width: 800px; margin: 2rem auto; padding: 0 1.5rem; color: #111; }\n";
        $html .= "        h1 { font-size: 1.8rem; text-align: center; margin-bottom: 1.5rem; }\n";
        $html .= "        .abstract-box { background: #f8f9fa; border-left: 3px solid #1E40AF; padding: 1rem 1.25rem; margin: 1.5rem 0; font-style: italic; }\n";
        $html .= "        .abstract-title { font-weight: bold; font-style: normal; margin-bottom: 0.5rem; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.05em; }\n";
        $html .= "        .keywords { margin-top: 0.75rem; font-weight: normal; font-style: normal; font-size: 0.9rem; }\n";
        $html .= "        h2 { font-size: 1.25rem; margin-top: 1.75rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.3rem; text-transform: uppercase; }\n";
        $html .= "        p { text-align: justify; margin-bottom: 1rem; text-indent: 1.5em; }\n";
        $html .= "        .bib-entry { margin-bottom: 0.5rem; padding-left: 2rem; text-indent: -2rem; font-size: 0.95rem; }\n";
        $html .= "        @media print { body { max-width: 100%; margin: 0; padding: 1in; font-size: 12pt; } h1 { font-size: 16pt; } h2 { font-size: 13pt; } .abstract-box { background: none; border-left: none; padding: 0; font-size: 10pt; } }\n";
        $html .= "    </style>\n</head>\n<body>\n";
        $html .= "    <h1>{$escapedTitle}</h1>\n";

        if ($m['abstract'] !== '') {
            $html .= "    <div class=\"abstract-box\">\n";
            $html .= "        <div class=\"abstract-title\">Abstrak</div>\n";
            $html .= '        <div>'.nl2br(htmlspecialchars($m['abstract'], ENT_QUOTES, 'UTF-8'))."</div>\n";
            if ($m['keywords'] !== []) {
                $html .= '        <div class="keywords"><strong>Kata Kunci:</strong> '.htmlspecialchars(implode(', ', $m['keywords']), ENT_QUOTES, 'UTF-8')."</div>\n";
            }
            $html .= "    </div>\n";
        }

        foreach ($m['sections'] as $sec) {
            $heading = htmlspecialchars($sec['heading'], ENT_QUOTES, 'UTF-8');
            $content = nl2br(htmlspecialchars($sec['content'], ENT_QUOTES, 'UTF-8'));
            $html .= "    <h2>{$heading}</h2>\n";
            $html .= "    <p>{$content}</p>\n";
        }

        if ($m['bibliography'] !== []) {
            $html .= "    <h2>Daftar Pustaka</h2>\n";
            foreach ($m['bibliography'] as $entry) {
                $html .= '    <div class="bib-entry">'.htmlspecialchars($entry, ENT_QUOTES, 'UTF-8')."</div>\n";
            }
        }

        $html .= "</body>\n</html>";

        return $html;
    }

    /**
     * @param  array{title: string, abstract: string, keywords: array<int, string>, sections: array<int, array{heading: string, content: string}>, bibliography: array<int, string>, citation_style: string}  $m
     */
    private function toJson(array $m): string
    {
        $payload = [
            'title' => $m['title'],
            'abstract' => $m['abstract'],
            'keywords' => $m['keywords'],
            'citation_style' => $m['citation_style'],
            'total_words' => str_word_count(strip_tags($m['abstract'].' '.implode(' ', array_column($m['sections'], 'content')))),
            'sections' => array_map(static fn (array $s): array => [
                'heading' => $s['heading'],
                'word_count' => str_word_count(strip_tags($s['content'])),
                'content' => $s['content'],
            ], $m['sections']),
            'bibliography' => $m['bibliography'],
            'exported_at' => now()->toIso8601String(),
        ];

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array{title: string, abstract: string, keywords: array<int, string>, sections: array<int, array{heading: string, content: string}>, bibliography: array<int, string>, citation_style: string}  $m
     */
    private function toText(array $m): string
    {
        $out = $m['title']."\n".str_repeat('=', mb_strlen($m['title']))."\n\n";

        if ($m['abstract'] !== '') {
            $out .= "ABSTRAK\n".$m['abstract']."\n\n";
        }

        if ($m['keywords'] !== []) {
            $out .= 'Kata kunci: '.implode(', ', $m['keywords'])."\n\n";
        }

        foreach ($m['sections'] as $section) {
            $out .= $section['heading']."\n".str_repeat('-', mb_strlen($section['heading']))."\n".$section['content']."\n\n";
        }

        if ($m['bibliography'] !== []) {
            $out .= "DAFTAR PUSTAKA\n";
            foreach ($m['bibliography'] as $entry) {
                $out .= '- '.$entry."\n";
            }
        }

        return rtrim($out)."\n";
    }

    /**
     * @param  array{title: string, abstract: string, keywords: array<int, string>, sections: array<int, array{heading: string, content: string}>, bibliography: array<int, string>, citation_style: string}  $m
     */
    private function toLatex(array $m): string
    {
        $escape = static fn (string $s): string => str_replace(
            ['\\', '&', '%', '$', '#', '_', '{', '}', '~', '^'],
            ['\\textbackslash{}', '\\&', '\\%', '\\$', '\\#', '\\_', '\\{', '\\}', '\\textasciitilde{}', '\\textasciicircum{}'],
            $s,
        );

        $out = "\\documentclass[12pt]{article}\n";
        $out .= "\\usepackage[utf8]{inputenc}\n\\usepackage{geometry}\n\\geometry{a4paper, margin=2.5cm}\n";
        $out .= '\\title{'.$escape($m['title'])."}\n\\author{Agen Xishie}\n\\date{\\today}\n";
        $out .= "\\begin{document}\n\\maketitle\n\n";

        if ($m['abstract'] !== '') {
            $out .= "\\begin{abstract}\n".$escape($m['abstract'])."\n\\end{abstract}\n\n";
        }

        if ($m['keywords'] !== []) {
            $out .= '\\noindent\\textbf{Kata kunci:} '.$escape(implode(', ', $m['keywords']))."\n\n";
        }

        foreach ($m['sections'] as $section) {
            $out .= '\\section*{'.$escape($section['heading'])."}\n".$escape($section['content'])."\n\n";
        }

        if ($m['bibliography'] !== []) {
            $out .= "\\section*{Daftar Pustaka}\n\\begin{itemize}\n";
            foreach ($m['bibliography'] as $entry) {
                $out .= '  \\item '.$escape($entry)."\n";
            }
            $out .= "\\end{itemize}\n";
        }

        $out .= "\n\\end{document}\n";

        return $out;
    }

    /**
     * @param  XishieProject|array<string, mixed>  $projectOrManuscript
     */
    private function toBibtex(XishieProject|array $projectOrManuscript): string
    {
        if ($projectOrManuscript instanceof XishieProject) {
            $entries = [];

            /** @var ProjectReference $reference */
            foreach ($projectOrManuscript->references()->orderBy('citation_key')->get() as $reference) {
                $authors = implode(' and ', array_map(static fn ($a): string => (string) $a, (array) ($reference->authors ?? [])));
                $type = $reference->journal_name !== null ? 'article' : 'misc';
                $key = (string) $reference->citation_key;

                $fields = [
                    'title' => (string) $reference->title,
                    'author' => $authors !== '' ? $authors : 'Anonim',
                    'year' => (string) ($reference->year ?? 'n.d.'),
                ];

                if ($reference->journal_name !== null) {
                    $fields['journal'] = (string) $reference->journal_name;
                }

                if ($reference->doi !== null) {
                    $fields['doi'] = (string) $reference->doi;
                }

                $lines = ["@{$type}{{$key},"];
                foreach ($fields as $name => $value) {
                    $lines[] = "  {$name} = {".str_replace(['{', '}'], '', $value).'}';
                }

                $entries[] = implode(",\n", $lines)."\n}";
            }

            return implode("\n\n", $entries).($entries === [] ? '' : "\n");
        }

        $bibliography = (array) ($projectOrManuscript['bibliography'] ?? []);
        $entries = [];
        foreach ($bibliography as $idx => $entry) {
            $key = 'ref'.($idx + 1);
            $lines = ["@misc{{$key},", '  title = {'.str_replace(['{', '}'], '', (string) $entry).'}', '}'];
            $entries[] = implode("\n", $lines);
        }

        return implode("\n\n", $entries).($entries === [] ? '' : "\n");
    }

    /**
     * @param  array{title: string, abstract: string, keywords: array<int, string>, sections: array<int, array{heading: string, content: string}>, bibliography: array<int, string>, citation_style: string}  $m
     */
    private function toOdt(array $m): string
    {
        $blocks = [];

        if ($m['abstract'] !== '') {
            $blocks[] = ['type' => 'heading', 'level' => 1, 'text' => 'Abstrak'];
            foreach (preg_split('/\n{2,}/', $m['abstract']) ?: [] as $para) {
                if (trim($para) !== '') {
                    $blocks[] = ['type' => 'paragraph', 'text' => trim($para)];
                }
            }
        }

        if ($m['keywords'] !== []) {
            $blocks[] = ['type' => 'paragraph', 'text' => 'Kata kunci: '.implode(', ', $m['keywords'])];
        }

        foreach ($m['sections'] as $section) {
            if ($section['heading'] !== '') {
                $blocks[] = ['type' => 'heading', 'level' => 1, 'text' => $section['heading']];
            }

            foreach (preg_split('/\n{2,}/', $section['content']) ?: [] as $para) {
                if (trim($para) !== '') {
                    $blocks[] = ['type' => 'paragraph', 'text' => trim($para)];
                }
            }
        }

        if ($m['bibliography'] !== []) {
            $blocks[] = ['type' => 'heading', 'level' => 1, 'text' => 'Daftar Pustaka'];
            foreach ($m['bibliography'] as $entry) {
                $blocks[] = ['type' => 'paragraph', 'text' => $entry];
            }
        }

        return app(NaskahOdtExporter::class)->build($blocks, $m['title']);
    }

    /**
     * Hasilkan berkas DOCX (OOXML) minimal yang valid tanpa dependensi.
     *
     * @param  array{title: string, abstract: string, keywords: array<int, string>, sections: array<int, array{heading: string, content: string}>, bibliography: array<int, string>, citation_style: string}  $m
     */
    private function toDocx(array $m): string
    {
        $paragraphs = [];

        $paragraphs[] = $this->docxParagraph($m['title'], 'Title');

        if ($m['abstract'] !== '') {
            $paragraphs[] = $this->docxParagraph('Abstrak', 'Heading1');
            foreach (preg_split('/\n{2,}/', $m['abstract']) ?: [] as $para) {
                if (trim($para) !== '') {
                    $paragraphs[] = $this->docxParagraph(trim($para));
                }
            }
        }

        if ($m['keywords'] !== []) {
            $paragraphs[] = $this->docxParagraph('Kata kunci: '.implode(', ', $m['keywords']), 'Normal', true);
        }

        foreach ($m['sections'] as $section) {
            if ($section['heading'] !== '') {
                $paragraphs[] = $this->docxParagraph($section['heading'], 'Heading1');
            }

            foreach (preg_split('/\n{2,}/', $section['content']) ?: [] as $para) {
                if (trim($para) !== '') {
                    $paragraphs[] = $this->docxParagraph(trim($para));
                }
            }
        }

        if ($m['bibliography'] !== []) {
            $paragraphs[] = $this->docxParagraph('Daftar Pustaka', 'Heading1');
            foreach ($m['bibliography'] as $entry) {
                $paragraphs[] = $this->docxParagraph($entry, 'Normal');
            }
        }

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.implode('', $paragraphs)
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1418" w:right="1418" w:bottom="1418" w:left="1418" w:header="708" w:footer="708" w:gutter="0"/>'
            .'</w:sectPr></w:body></w:document>';

        $temp = tempnam(sys_get_temp_dir(), 'xishie-docx-');
        if ($temp === false) {
            throw new RuntimeException('Tidak dapat membuat berkas sementara untuk DOCX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) {
            @unlink($temp);
            throw new RuntimeException('Tidak dapat membuka arsip DOCX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->docxContentTypes());
        $zip->addFromString('_rels/.rels', $this->docxRootRels());
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $this->docxDocumentRels());
        $zip->addFromString('word/styles.xml', $this->docxStyles());
        $zip->close();

        $binary = (string) file_get_contents($temp);
        @unlink($temp);

        return $binary;
    }

    private function docxParagraph(string $text, string $style = 'Normal', bool $bold = false): string
    {
        $runProps = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
        $pPr = $style !== 'Normal' ? '<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>' : '';

        return '<w:p>'.$pPr.'<w:r>'.$runProps.'<w:t xml:space="preserve">'.$this->xmlEscape($text).'</w:t></w:r></w:p>';
    }

    private function xmlEscape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function docxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private function docxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function docxDocumentRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function docxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:rPr><w:b/><w:sz w:val="40"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:rPr><w:b/><w:sz w:val="30"/></w:rPr></w:style>'
            .'</w:styles>';
    }
}
