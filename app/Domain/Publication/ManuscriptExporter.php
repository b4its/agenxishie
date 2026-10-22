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
            self::FORMAT_MARKDOWN => ['label' => 'Markdown', 'extension' => 'md', 'mime' => 'text/markdown'],
            self::FORMAT_LATEX => ['label' => 'LaTeX', 'extension' => 'tex', 'mime' => 'application/x-tex'],
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
            self::FORMAT_BIBTEX => $this->toBibtex($project),
            self::FORMAT_TEXT => $this->toText($manuscript),
            self::FORMAT_DOCX => $this->toDocx($manuscript),
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

        // Bila ada naskah hasil adaptasi venue, pakai isinya sebagai sumber
        // (sudah diformat untuk jurnal target).
        if (is_array($adapted) && isset($adapted['manuscript']) && is_string($adapted['manuscript']) && trim($adapted['manuscript']) !== '') {
            $sections = $this->sectionsFromCompiled((string) $adapted['manuscript']);
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

    private function toBibtex(XishieProject $project): string
    {
        $entries = [];

        /** @var ProjectReference $reference */
        foreach ($project->references()->orderBy('citation_key')->get() as $reference) {
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
