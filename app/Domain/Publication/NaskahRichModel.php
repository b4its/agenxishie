<?php

declare(strict_types=1);

namespace App\Domain\Publication;

/**
 * NaskahRichModel: skema blok kaya untuk Naskah Studio.
 *
 * Blok mendukung format inline (runs), perataan, indentasi, spasi, daftar,
 * tabel, gambar, dan pemisah halaman. Kelas ini menormalkan blok (termasuk
 * migrasi dokumen lama yang hanya punya {type, level, text}), menerapkan
 * format inline pada rentang teks, serta merender ke HTML/Markdown/teks/DOCX.
 *
 * Skema blok:
 *   type      heading|paragraph|quote|bullet|numbered|table|image|pagebreak|footnote-ref
 *   level     1..3 (heading)
 *   text      teks polos (selalu tersinkron dengan runs)
 *   runs      array<{text,bold,italic,underline,strike,sub,sup,color,highlight,smallCaps,allCaps,code,link}>
 *   align     left|center|right|justify
 *   indent    0..8
 *   listType  none|bullet|numbered
 *   listLevel 0..8
 *   spacingBefore / spacingAfter / lineSpacing (float)
 *   table     {rows: array<array<array{text,runs}>>, header: bool}
 *   image     {src, alt, width}
 *   pageBreak bool
 */
final class NaskahRichModel
{
    /** @var array<int, string> */
    public const TEXT_TYPES = ['title', 'subtitle', 'heading', 'paragraph', 'quote', 'bullet', 'numbered', 'footnote-ref', 'callout', 'toc'];

    /** @var array<int, string> */
    public const ALL_TYPES = ['title', 'subtitle', 'heading', 'paragraph', 'quote', 'bullet', 'numbered', 'table', 'image', 'pagebreak', 'footnote-ref', 'callout', 'toc', 'equation'];

    /**
     * Normalisasi satu set blok (termasuk migrasi dokumen lama).
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $blocks): array
    {
        $normalized = [];

        foreach (array_values($blocks) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $normalized[] = $this->normalizeBlock($block);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    public function normalizeBlock(array $block): array
    {
        $type = (string) ($block['type'] ?? 'paragraph');

        if (! in_array($type, self::ALL_TYPES, true)) {
            $type = 'paragraph';
        }

        if ($type === 'table') {
            return $this->normalizeTableBlock($block);
        }

        if ($type === 'equation') {
            $latex = (string) ($block['equation']['latex'] ?? $block['latex'] ?? $block['text'] ?? 'E = mc^2');
            $caption = (string) ($block['equation']['caption'] ?? $block['caption'] ?? '');
            $display = (string) ($block['equation']['display'] ?? $block['display'] ?? 'block');

            return [
                'type' => 'equation',
                'text' => $latex,
                'runs' => [],
                'equation' => [
                    'latex' => $latex,
                    'caption' => $caption,
                    'display' => $display,
                ],
                'align' => $this->align($block['align'] ?? 'center'),
            ];
        }

        if ($type === 'image') {
            return [
                'type' => 'image',
                'text' => '',
                'runs' => [],
                'image' => [
                    'src' => (string) ($block['image']['src'] ?? $block['src'] ?? ''),
                    'alt' => (string) ($block['image']['alt'] ?? $block['alt'] ?? ''),
                    'caption' => (string) ($block['image']['caption'] ?? $block['caption'] ?? $block['image']['alt'] ?? ''),
                    'width' => (int) ($block['image']['width'] ?? $block['width'] ?? 480),
                ],
                'align' => $this->align($block['align'] ?? 'center'),
            ];
        }

        if ($type === 'pagebreak') {
            return ['type' => 'pagebreak', 'pageBreak' => true, 'text' => '', 'runs' => []];
        }

        $text = (string) ($block['text'] ?? '');
        $runs = $this->normalizeRuns($block['runs'] ?? [], $text);

        return [
            'type' => $type,
            'level' => $type === 'heading' ? max(1, min(3, (int) ($block['level'] ?? 1))) : 0,
            'text' => $text,
            'runs' => $runs,
            'align' => $this->align($block['align'] ?? 'left'),
            'indent' => max(0, min(8, (int) ($block['indent'] ?? 0))),
            'listType' => in_array($block['listType'] ?? '', ['bullet', 'numbered'], true) ? $block['listType'] : ($type === 'bullet' ? 'bullet' : ($type === 'numbered' ? 'numbered' : 'none')),
            'listLevel' => max(0, min(8, (int) ($block['listLevel'] ?? 0))),
            'spacingBefore' => max(0.0, min(72.0, (float) ($block['spacingBefore'] ?? 0))),
            'spacingAfter' => max(0.0, min(72.0, (float) ($block['spacingAfter'] ?? 0))),
            'lineSpacing' => max(0.8, min(3.0, (float) ($block['lineSpacing'] ?? 1.0))),
            'pageBreak' => (bool) ($block['pageBreak'] ?? false),
            'dropCap' => (bool) ($block['dropCap'] ?? false),
            'calloutType' => in_array($block['calloutType'] ?? '', ['info', 'warning', 'tip', 'note', 'success', 'theorem'], true) ? $block['calloutType'] : 'info',
            'firstLineIndent' => (bool) ($block['firstLineIndent'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizeTableBlock(array $block): array
    {
        $table = is_array($block['table'] ?? null) ? $block['table'] : [];
        $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];

        $normRows = [];
        $maxCols = 1;

        foreach (array_values($rows) as $row) {
            $cells = is_array($row) ? array_values($row) : [];
            $normCells = [];

            foreach ($cells as $cell) {
                if (is_array($cell)) {
                    $cellText = (string) ($cell['text'] ?? '');
                    $normCells[] = ['text' => $cellText, 'runs' => $this->normalizeRuns($cell['runs'] ?? [], $cellText)];
                } else {
                    $normCells[] = ['text' => (string) $cell, 'runs' => $this->normalizeRuns([], (string) $cell)];
                }
            }

            $maxCols = max($maxCols, count($normCells));
            $normRows[] = $normCells;
        }

        if ($normRows === []) {
            $normRows = [[['text' => '', 'runs' => []]]];
        }

        // Ratakan lebar kolom agar grid konsisten.
        foreach ($normRows as $i => $row) {
            while (count($normRows[$i]) < $maxCols) {
                $normRows[$i][] = ['text' => '', 'runs' => []];
            }
        }

        return [
            'type' => 'table',
            'text' => '',
            'runs' => [],
            'table' => [
                'rows' => $normRows,
                'header' => (bool) ($table['header'] ?? true),
                'style' => (string) ($table['style'] ?? 'grid'),
            ],
            'align' => $this->align($block['align'] ?? 'left'),
        ];
    }

    /**
     * Normalisasi runs agar teks gabungannya sama dengan teks blok.
     *
     * @param  array<int, mixed>  $runs
     * @return array<int, array<string, mixed>>
     */
    public function normalizeRuns(array $runs, string $text): array
    {
        $clean = [];

        foreach ($runs as $run) {
            if (! is_array($run)) {
                continue;
            }

            $runText = (string) ($run['text'] ?? '');

            if ($runText === '') {
                continue;
            }

            $clean[] = [
                'text' => $runText,
                'bold' => (bool) ($run['bold'] ?? false),
                'italic' => (bool) ($run['italic'] ?? false),
                'underline' => (bool) ($run['underline'] ?? false),
                'strike' => (bool) ($run['strike'] ?? false),
                'sub' => (bool) ($run['sub'] ?? false),
                'sup' => (bool) ($run['sup'] ?? false),
                'smallCaps' => (bool) ($run['smallCaps'] ?? false),
                'allCaps' => (bool) ($run['allCaps'] ?? false),
                'code' => (bool) ($run['code'] ?? false),
                'color' => $this->color($run['color'] ?? null),
                'highlight' => $this->color($run['highlight'] ?? null),
                'link' => $this->safeUrl($run['link'] ?? null),
            ];
        }

        $joined = implode('', array_column($clean, 'text'));

        // Teks berubah tanpa runs (mis. suntingan lama): bungkus satu run polos.
        if ($joined !== $text) {
            return $text === '' ? [] : [['text' => $text] + $this->plainRunAttributes()];
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function plainRunAttributes(): array
    {
        return [
            'bold' => false, 'italic' => false, 'underline' => false, 'strike' => false,
            'sub' => false, 'sup' => false, 'smallCaps' => false, 'allCaps' => false,
            'code' => false, 'color' => null, 'highlight' => null, 'link' => null,
        ];
    }

    /**
     * Terapkan atribut format inline pada rentang [start, end) sebuah blok teks.
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $attribute  mis. ['bold' => true] atau ['bold' => null] untuk toggle
     * @return array<string, mixed>
     */
    public function applyInlineFormat(array $block, int $start, int $end, array $attribute): array
    {
        $text = (string) ($block['text'] ?? '');
        $length = mb_strlen($text);

        $start = max(0, min($length, $start));
        $end = max(0, min($length, $end));

        if ($start >= $end) {
            return $block;
        }

        $runs = $this->runsWithText($block);

        $segments = [];
        $offset = 0;
        foreach ($runs as $run) {
            $runText = (string) $run['text'];
            $runLen = mb_strlen($runText);
            $runStart = $offset;
            $runEnd = $offset + $runLen;
            $offset = $runEnd;

            if ($runEnd <= $start || $runStart >= $end) {
                $segments[] = $run;

                continue;
            }

            // Potong run pada batas seleksi dan terapkan atribut ke bagian tengah.
            $beforeLen = max(0, $start - $runStart);
            $afterLen = max(0, $runEnd - $end);

            if ($beforeLen > 0) {
                $segments[] = $this->withText($run, mb_substr($runText, 0, $beforeLen));
            }

            $middleLen = $runLen - $beforeLen - $afterLen;
            if ($middleLen > 0) {
                $middle = $this->withText($run, mb_substr($runText, $beforeLen, $middleLen));
                $middle = $this->mergeAttributes($middle, $attribute);
                $segments[] = $middle;
            }

            if ($afterLen > 0) {
                $segments[] = $this->withText($run, mb_substr($runText, $runLen - $afterLen));
            }
        }

        $block['runs'] = $this->mergeAdjacentRuns($segments);
        $block['text'] = $text;

        return $block;
    }

    /**
     * Ambil runs sebuah blok; bila kosong, buat satu run polos dari teks.
     *
     * @param  array<string, mixed>  $block
     * @return array<int, array<string, mixed>>
     */
    public function runsWithText(array $block): array
    {
        $runs = $this->normalizeRuns((array) ($block['runs'] ?? []), (string) ($block['text'] ?? ''));

        if ($runs === [] && (string) ($block['text'] ?? '') !== '') {
            return [['text' => (string) $block['text']] + $this->plainRunAttributes()];
        }

        return $runs;
    }

    /**
     * Set format inline untuk seluruh blok (tanpa seleksi).
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $attribute
     * @return array<string, mixed>
     */
    public function applyInlineFormatAll(array $block, array $attribute): array
    {
        $text = (string) ($block['text'] ?? '');

        if ($text === '') {
            return $block;
        }

        return $this->applyInlineFormat($block, 0, mb_strlen($text), $attribute);
    }

    /**
     * Terapkan auto-correct tipografi pada teks (LibreOffice AutoCorrect).
     *
     * @return array{text: string, count: int}
     */
    public function autoCorrect(string $text): array
    {
        $replacements = [
            '/--/' => '—',
            '/->/' => '→',
            '/<-/' => '←',
            '/<=>/' => '⇔',
            '/=>/' => '⇒',
            '/\([cC]\)/' => '©',
            '/\([rR]\)/' => '®',
            '/\([tT][mM]\)/' => '™',
            '/\b1\/2\b/' => '½',
            '/\b1\/4\b/' => '¼',
            '/\b3\/4\b/' => '¾',
            '/\+\/-/' => '±',
            '/\.{3}/' => '…',
            '/!=/' => '≠',
            '/<=/' => '≤',
            '/>=/' => '≥',
            '/\bdegC\b/' => '°C',
            '/\bapprox\b/' => '≈',
        ];

        $count = 0;
        foreach ($replacements as $pattern => $replacement) {
            $text = (string) preg_replace($pattern, $replacement, $text, -1, $matches);
            $count += $matches;
        }

        return ['text' => $text, 'count' => $count];
    }

    /**
     * Hitung formula pada kolom tabel (SUM, AVERAGE, MIN, MAX - LibreOffice Table Calculation).
     *
     * @param  array<string, mixed>  $tableBlock
     * @return array<string, mixed>
     */
    public function calculateTableColumn(array $tableBlock, int $colIndex, string $formula = 'sum'): array
    {
        $rows = (array) ($tableBlock['table']['rows'] ?? []);
        if ($rows === []) {
            return $tableBlock;
        }

        $values = [];
        $hasHeader = (bool) ($tableBlock['table']['header'] ?? true);
        $startRow = $hasHeader ? 1 : 0;

        for ($r = $startRow; $r < count($rows); $r++) {
            $cellText = trim((string) ($rows[$r][$colIndex]['text'] ?? ''));
            $cleanNum = str_replace([',', ' '], ['', ''], $cellText);
            if (is_numeric($cleanNum)) {
                $values[] = (float) $cleanNum;
            }
        }

        if ($values === []) {
            return $tableBlock;
        }

        $result = match (strtolower($formula)) {
            'avg', 'average' => count($values) > 0 ? (array_sum($values) / count($values)) : 0,
            'min' => min($values),
            'max' => max($values),
            default => array_sum($values),
        };

        $colsCount = count((array) ($rows[0] ?? []));
        $newRow = [];
        for ($c = 0; $c < $colsCount; $c++) {
            if ($c === 0 && $colIndex > 0) {
                $newRow[] = ['text' => strtoupper($formula), 'runs' => []];
            } elseif ($c === $colIndex) {
                $formatted = fmod($result, 1.0) === 0.0 ? (string) (int) $result : number_format($result, 2);
                $newRow[] = ['text' => $formatted, 'runs' => []];
            } else {
                $newRow[] = ['text' => '', 'runs' => []];
            }
        }

        $rows[] = $newRow;
        $tableBlock['table']['rows'] = $rows;

        return $tableBlock;
    }

    /**
     * @param  array<string, mixed>  $run
     * @param  array<string, mixed>  $attribute
     * @return array<string, mixed>
     */
    private function mergeAttributes(array $run, array $attribute): array
    {
        foreach ($attribute as $key => $value) {
            if (! array_key_exists($key, $this->plainRunAttributes())) {
                continue;
            }

            // Nilai null = toggle berdasarkan nilai saat ini.
            if ($value === null) {
                $run[$key] = ! ($run[$key] ?? false);
            } else {
                $run[$key] = $value;
            }
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<string, mixed>
     */
    private function withText(array $run, string $text): array
    {
        $run['text'] = $text;

        return $run;
    }

    /**
     * Gabungkan run bersebelahan dengan atribut identik.
     *
     * @param  array<int, array<string, mixed>>  $runs
     * @return array<int, array<string, mixed>>
     */
    private function mergeAdjacentRuns(array $runs): array
    {
        $merged = [];

        foreach ($runs as $run) {
            if ((string) $run['text'] === '') {
                continue;
            }

            if ($merged !== []) {
                $last = $merged[array_key_last($merged)];
                $a = $last;
                $b = $run;
                unset($a['text'], $b['text']);

                if ($a === $b) {
                    $merged[array_key_last($merged)]['text'] .= (string) $run['text'];

                    continue;
                }
            }

            $merged[] = $run;
        }

        return $merged;
    }

    // =====================================================================
    // Rendering
    // =====================================================================

    /**
     * Render blok ke HTML (aman, sudah di-escape).
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $options  ['watermark'=>string,'pageColor'=>string,'pageBorder'=>bool,'lineNumbers'=>bool,'columns'=>int,'footnotes'=>array]
     */
    public function toHtml(array $blocks, string $title = '', array $options = []): string
    {
        $escape = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $body = '';
        $lineNo = 0;

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'pagebreak') {
                $body .= '<div class="page-break"></div>';

                continue;
            }

            if ($type === 'image') {
                $src = $this->safeUrl($block['image']['src'] ?? null) ?? '';
                $alt = $escape((string) ($block['image']['alt'] ?? ''));
                $caption = $escape((string) ($block['image']['caption'] ?? ''));
                $width = (int) ($block['image']['width'] ?? 0);
                $style = $width > 0 ? ' style="width:'.$width.'px"' : ' style="max-width:100%"';
                $captionHtml = $caption !== '' ? '<figcaption style="font-size:0.9em; font-style:italic; color:#64748b; margin-top:4px;">'.$caption.'</figcaption>' : '';
                $body .= '<figure class="img-figure align-'.($block['align'] ?? 'center').'" style="margin:16px 0; text-align:'.($block['align'] ?? 'center').';"><img src="'.$escape($src).'" alt="'.$alt.'"'.$style.'>'.$captionHtml.'</figure>';

                continue;
            }

            if ($type === 'table') {
                $body .= $this->tableToHtml($block);

                continue;
            }

            if ($type === 'equation') {
                $latex = (string) ($block['equation']['latex'] ?? $block['latex'] ?? $block['text'] ?? '');
                $caption = (string) ($block['equation']['caption'] ?? $block['caption'] ?? '');
                $capTrim = trim($caption, '()');
                $captionHtml = $capTrim !== '' ? '<span class="eq-caption" style="float:right; font-weight:600; color:#616161;">('.$escape($capTrim).')</span>' : '';
                $body .= '<div class="equation-block" style="margin:16px 0; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; text-align:center; font-family:\'Cambria Math\', serif; font-size:1.15em;">'.$captionHtml.'<span class="latex-math">'.$escape($latex).'</span></div>';

                continue;
            }

            if ($type === 'callout') {
                $cType = (string) ($block['calloutType'] ?? 'info');
                $body .= '<div class="callout-box" style="border-left:4px solid #0f6cbd; background:#f0f9ff; padding:10px 14px; margin:14px 0; border-radius:0 4px 4px 0;"><strong>['.strtoupper($cType).']</strong> '.$escape((string) ($block['text'] ?? '')).'</div>';

                continue;
            }

            $lineNo++;
            $attrs = $this->blockAttributes($block, $lineNo, $options);
            $inner = $this->runsToHtml($block, $escape);

            $tag = match ($type) {
                'heading' => 'h'.(int) ($block['level'] ?? 1),
                'quote' => 'blockquote',
                default => 'p',
            };

            $listPrefix = '';
            if (in_array($type, ['bullet', 'numbered'], true)) {
                $marker = $type === 'bullet' ? '•' : $lineNo.'.';
                $listPrefix = '<span class="list-marker">'.$marker.'</span> ';
            }

            $body .= '<'.$tag.$attrs.'>'.$listPrefix.$inner.'</'.$tag.'>';
        }

        $watermark = (string) ($options['watermark'] ?? '');
        $watermarkHtml = $watermark !== ''
            ? '<div class="watermark">'.$escape($watermark).'</div>'
            : '';

        $pageStyle = '';
        if (! empty($options['pageColor'])) {
            $pageStyle .= 'background:'.($this->color($options['pageColor']) ?? '#ffffff').';';
        }
        if (! empty($options['pageBorder'])) {
            $pageStyle .= 'border:1px solid #94a3b8;';
        }

        $titleHtml = $title !== '' ? '<h1 class="doc-title">'.$escape($title).'</h1>' : '';

        return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>'
            .$escape($title !== '' ? $title : 'Naskah').'</title><style>'
            .$this->printCss()
            .'</style></head><body><div class="page" style="'.$pageStyle.'">'.$watermarkHtml.$titleHtml.$body.'</div></body></html>';
    }

    /**
     * CSS cetak/halaman untuk HTML ekspor.
     */
    public function printCss(): string
    {
        return 'body{font-family:Georgia,serif;line-height:1.7;margin:0;background:#e2e8f0;color:#0f172a}'
            .'.page{max-width:860px;min-height:1000px;margin:2rem auto;padding:3rem 3.2rem;background:#fff;box-shadow:0 2px 10px rgba(15,23,42,.2);position:relative}'
            .'h1,h2,h3{font-family:system-ui,sans-serif;color:#1e40af}.doc-title{font-size:1.9rem;text-align:center;margin-bottom:1.5rem}'
            .'.doc-title{border-bottom:2px solid #e2e8f0;padding-bottom:1rem}'
            .'blockquote{border-left:3px solid #1e40af;margin:1rem 0;padding-left:1rem;color:#334155;font-style:italic}'
            .'table{border-collapse:collapse;width:100%;margin:1rem 0;font-size:.95rem}td,th{border:1px solid #cbd5e1;padding:.45rem .6rem;text-align:left}th{background:#eff6ff}'
            .'.list-marker{font-weight:700;color:#1e40af}'
            .'.align-center{text-align:center}.align-right{text-align:right}.align-justify{text-align:justify}'
            .'.page-break{page-break-after:always;break-after:page;border-top:1px dashed #94a3b8;margin:2rem 0}'
            .'.watermark{position:absolute;top:40%;left:0;right:0;text-align:center;font-size:5rem;color:rgba(30,64,175,.10);transform:rotate(-30deg);pointer-events:none;font-weight:800}'
            .'.line-numbers .line-no{float:left;margin-left:-2.6rem;color:#94a3b8;font-size:.75rem}'
            .'.ln{position:relative}.ln-num{position:absolute;left:-2.4rem;color:#94a3b8;font-size:.75rem}'
            .'a{color:#1e40af}sub,sup{font-size:.72em}mark{background:#fde68a}code{background:#f1f5f9;padding:.05rem .25rem;border-radius:3px}'
            .'@media print{body{background:#fff}.page{box-shadow:none;margin:0;max-width:none;min-height:auto}}';
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $options
     */
    private function blockAttributes(array $block, int $lineNo, array $options): string
    {
        $classes = ['b-'.($block['type'] ?? 'paragraph'), 'align-'.($block['align'] ?? 'left')];
        $styles = [];

        if (($block['type'] ?? '') === 'heading') {
            $classes[] = 'h'.(int) ($block['level'] ?? 1);
        }

        $indent = (int) ($block['indent'] ?? 0);
        if ($indent > 0) {
            $styles[] = 'margin-left:'.($indent * 1.5).'rem';
        }
        if (! empty($block['firstLineIndent'])) {
            $styles[] = 'text-indent:1.27cm';
        }

        if (($block['spacingBefore'] ?? 0) > 0) {
            $styles[] = 'margin-top:'.$block['spacingBefore'].'pt';
        }
        if (($block['spacingAfter'] ?? 0) > 0) {
            $styles[] = 'margin-bottom:'.$block['spacingAfter'].'pt';
        }
        if (($block['lineSpacing'] ?? 1.0) > 1.0) {
            $styles[] = 'line-height:'.$block['lineSpacing'];
        }

        if (! empty($options['lineNumbers'])) {
            $classes[] = 'ln';
            $num = '<span class="ln-num">'.$lineNo.'</span>';
        } else {
            $num = '';
        }

        $attr = ' class="'.implode(' ', $classes).'"';
        if ($styles !== []) {
            $attr .= ' style="'.implode(';', $styles).'"';
        }

        return $attr.' data-prefix="'.$num.'"';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    public function runsToHtml(array $block, ?callable $escape = null): string
    {
        $escape ??= static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $runs = $this->runsWithText($block);

        if ($runs === []) {
            return '';
        }

        $html = '';
        foreach ($runs as $run) {
            $text = $escape((string) $run['text']);

            if (! empty($run['code'])) {
                $text = '<code>'.$text.'</code>';
            }
            if (! empty($run['bold'])) {
                $text = '<strong>'.$text.'</strong>';
            }
            if (! empty($run['italic'])) {
                $text = '<em>'.$text.'</em>';
            }
            if (! empty($run['underline'])) {
                $text = '<u>'.$text.'</u>';
            }
            if (! empty($run['strike'])) {
                $text = '<s>'.$text.'</s>';
            }
            if (! empty($run['sub'])) {
                $text = '<sub>'.$text.'</sub>';
            }
            if (! empty($run['sup'])) {
                $text = '<sup>'.$text.'</sup>';
            }
            if (! empty($run['smallCaps'])) {
                $text = '<span style="font-variant:small-caps">'.$text.'</span>';
            }
            if (! empty($run['allCaps'])) {
                $text = '<span style="text-transform:uppercase">'.$text.'</span>';
            }
            if (! empty($run['highlight'])) {
                $text = '<mark style="background:'.$this->color($run['highlight']).'">'.$text.'</mark>';
            }
            if (! empty($run['color'])) {
                $text = '<span style="color:'.$this->color($run['color']).'">'.$text.'</span>';
            }
            if (! empty($run['link'])) {
                $href = $this->safeUrl($run['link']);
                if ($href !== null) {
                    $text = '<a href="'.$escape($href).'" target="_blank" rel="noopener">'.$text.'</a>';
                }
            }

            $html .= $text;
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function tableToHtml(array $block): string
    {
        $escape = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = (array) ($block['table']['rows'] ?? []);
        $header = (bool) ($block['table']['header'] ?? true);

        $html = '<table>';
        foreach ($rows as $rIndex => $row) {
            $html .= '<tr>';
            foreach ((array) $row as $cell) {
                $tag = ($header && $rIndex === 0) ? 'th' : 'td';
                $inner = isset($cell['runs']) ? $this->runsToHtml(['text' => (string) ($cell['text'] ?? ''), 'runs' => $cell['runs']], $escape) : $escape((string) ($cell['text'] ?? ''));
                $html .= '<'.$tag.'>'.$inner.'</'.$tag.'>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Render ke Markdown.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function toMarkdown(array $blocks, string $title = ''): string
    {
        $out = [];

        if ($title !== '') {
            $out[] = '# '.$title;
        }

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'pagebreak') {
                $out[] = '---';

                continue;
            }

            if ($type === 'image') {
                $alt = (string) (($block['image']['caption'] ?? '') ?: ($block['image']['alt'] ?? ''));
                $out[] = '!['.$alt.']('.($block['image']['src'] ?? '').')';

                continue;
            }

            if ($type === 'table') {
                $out[] = $this->tableToMarkdown($block);

                continue;
            }

            if ($type === 'equation') {
                $latex = (string) ($block['equation']['latex'] ?? $block['latex'] ?? $block['text'] ?? '');
                $caption = (string) ($block['equation']['caption'] ?? $block['caption'] ?? '');
                $out[] = "$$\n".$latex."\n$$".($caption !== '' ? "\n*(".$caption.')*' : '');

                continue;
            }

            $text = $this->runsToMarkdown($this->runsWithText($block));
            $out[] = match ($type) {
                'heading' => str_repeat('#', (int) ($block['level'] ?? 1)).' '.$text,
                'quote' => '> '.$text,
                'callout' => '> **['.strtoupper((string) ($block['calloutType'] ?? 'catatan')).']** '.$text,
                'bullet' => '- '.$text,
                'numbered' => '1. '.$text,
                default => $text,
            };
        }

        return implode("\n\n", $out);
    }

    /**
     * @param  array<int, array<string, mixed>>  $runs
     */
    public function runsToMarkdown(array $runs): string
    {
        $text = '';
        foreach ($runs as $run) {
            $t = (string) $run['text'];

            if (! empty($run['code'])) {
                $t = '`'.$t.'`';
            }
            if (! empty($run['bold'])) {
                $t = '**'.$t.'**';
            }
            if (! empty($run['italic'])) {
                $t = '*'.$t.'*';
            }
            if (! empty($run['strike'])) {
                $t = '~~'.$t.'~~';
            }
            if (! empty($run['sub'])) {
                $t = '~'.$t.'~';
            }
            if (! empty($run['sup'])) {
                $t = '^'.$t.'^';
            }
            if (! empty($run['link'])) {
                $t = '['.$t.']('.$run['link'].')';
            }

            $text .= $t;
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function tableToMarkdown(array $block): string
    {
        $rows = (array) ($block['table']['rows'] ?? []);
        if ($rows === []) {
            return '';
        }

        $lines = [];
        foreach ($rows as $rIndex => $row) {
            $cells = array_map(static fn ($c): string => trim((string) ($c['text'] ?? '')), (array) $row);
            $lines[] = '| '.implode(' | ', $cells).' |';

            if ($rIndex === 0 && ($block['table']['header'] ?? true)) {
                $lines[] = '| '.implode(' | ', array_fill(0, count($cells), '---')).' |';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Teks polos.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function toPlainText(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'pagebreak' || $type === 'image') {
                continue;
            }

            if ($type === 'table') {
                foreach ((array) ($block['table']['rows'] ?? []) as $row) {
                    $parts[] = implode("\t", array_map(static fn ($c): string => (string) ($c['text'] ?? ''), (array) $row));
                }

                continue;
            }

            $parts[] = (string) ($block['text'] ?? '');
        }

        return implode("\n\n", array_filter($parts, static fn (string $p): bool => trim($p) !== ''));
    }

    /**
     * Hitung kata dari blok teks dan sel tabel.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function countWords(array $blocks): int
    {
        $total = 0;

        foreach ($blocks as $block) {
            $total += str_word_count(strip_tags((string) ($block['text'] ?? '')));

            if (($block['type'] ?? '') === 'table') {
                foreach ((array) ($block['table']['rows'] ?? []) as $row) {
                    foreach ((array) $row as $cell) {
                        $total += str_word_count(strip_tags((string) ($cell['text'] ?? '')));
                    }
                }
            }
        }

        return $total;
    }

    /**
     * Daftar judul untuk panel navigasi & daftar isi.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array{index: int, level: int, text: string}>
     */
    public function outline(array $blocks): array
    {
        $items = [];

        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $items[] = ['index' => (int) $index, 'level' => (int) ($block['level'] ?? 1), 'text' => $text];
        }

        return $items;
    }

    /**
     * Buat blok baru dengan default lengkap.
     *
     * @return array<string, mixed>
     */
    public function newBlock(string $type = 'paragraph', string $text = '', int $level = 1): array
    {
        return $this->normalizeBlock(['type' => $type, 'text' => $text, 'level' => $level, 'runs' => []]);
    }

    /**
     * Buat blok rumus LaTeX baru.
     *
     * @return array<string, mixed>
     */
    public function newEquationBlock(string $latex, string $caption = '', string $display = 'block'): array
    {
        return $this->normalizeBlock([
            'type' => 'equation',
            'equation' => [
                'latex' => $latex,
                'caption' => $caption,
                'display' => $display,
            ],
            'align' => 'center',
        ]);
    }

    /**
     * Buat blok callout ilmiah baru.
     *
     * @return array<string, mixed>
     */
    public function newCalloutBlock(string $type = 'info', string $text = ''): array
    {
        return $this->normalizeBlock([
            'type' => 'callout',
            'calloutType' => in_array($type, ['info', 'warning', 'tip', 'note', 'success', 'theorem'], true) ? $type : 'info',
            'text' => $text,
            'runs' => [],
        ]);
    }

    /**
     * Buat blok tabel baru.
     *
     * @param  array<int, array<int, array<string, mixed>|string>>  $rows
     * @return array<string, mixed>
     */
    public function newTableBlock(array $rows = [], bool $hasHeader = true): array
    {
        return $this->normalizeBlock([
            'type' => 'table',
            'table' => [
                'header' => $hasHeader,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * Buat blok gambar baru.
     *
     * @return array<string, mixed>
     */
    public function newImageBlock(string $src, string $alt = '', int $width = 480, string $align = 'center', string $caption = ''): array
    {
        return $this->normalizeBlock([
            'type' => 'image',
            'image' => [
                'src' => $src,
                'alt' => $alt,
                'caption' => $caption ?: $alt,
                'width' => $width,
            ],
            'align' => $align,
        ]);
    }

    /**
     * Hitung statistik mendalam dan skor keterbacaan (LibreOffice / Word style).
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    public function computeStatistics(array $blocks): array
    {
        $text = $this->toPlainText($blocks);
        $words = $this->countWords($blocks);
        $charCount = mb_strlen($text);
        $charCountNoSpaces = mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');

        $sentences = max(1, count(preg_split('/[.!?]+(?:\s+|$)/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [1]));
        $paragraphs = max(1, count(array_filter($blocks, static fn (array $b): bool => in_array($b['type'] ?? '', ['paragraph', 'quote', 'heading', 'bullet', 'numbered'], true))));

        preg_match_all('/[aiueoAIUEO]/u', $text, $matches);
        $syllables = max(1, count($matches[0] ?? []));

        $wordsPerSentence = $words / $sentences;
        $syllablesPerWord = $words > 0 ? ($syllables / $words) : 1.0;

        $rawReadingEase = 206.835 - (1.015 * $wordsPerSentence) - (84.6 * $syllablesPerWord);
        $readingEase = round(max(0.0, min(100.0, $rawReadingEase)), 1);

        $rawGrade = (0.39 * $wordsPerSentence) + (11.8 * $syllablesPerWord) - 15.59;
        $gradeLevel = round(max(1.0, min(20.0, $rawGrade)), 1);

        $readingTimeMinutes = max(1, (int) ceil($words / 200));
        $speakingTimeMinutes = max(1, (int) ceil($words / 130));

        return [
            'words' => $words,
            'characters' => $charCount,
            'charactersNoSpaces' => $charCountNoSpaces,
            'sentences' => $sentences,
            'paragraphs' => $paragraphs,
            'syllables' => $syllables,
            'readingEase' => $readingEase,
            'gradeLevel' => $gradeLevel,
            'readingTimeMinutes' => $readingTimeMinutes,
            'speakingTimeMinutes' => $speakingTimeMinutes,
        ];
    }

    /**
     * Ekspor blok naskah menjadi dokumen LaTeX standar.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function toLatex(array $blocks, string $title = ''): string
    {
        $esc = static function (string $str): string {
            $map = [
                '\\' => '\\textbackslash{}',
                '%' => '\\%',
                '$' => '\\$',
                '&' => '\\&',
                '#' => '\\#',
                '_' => '\\_',
                '{' => '\\{',
                '}' => '\\}',
                '~' => '\\textasciitilde{}',
                '^' => '\\textasciicircum{}',
            ];

            return strtr($str, $map);
        };

        $out = "\\documentclass[12pt,a4paper]{article}\n"
            ."\\usepackage[utf8]{inputenc}\n"
            ."\\usepackage[margin=2.5cm]{geometry}\n"
            ."\\usepackage{hyperref}\n"
            ."\\usepackage{booktabs}\n\n";

        if ($title !== '') {
            $out .= '\\title{'.$esc($title)."}\n\\author{Xishie Naskah Studio}\n\\date{\\today}\n";
        }

        $out .= "\\begin{document}\n";
        if ($title !== '') {
            $out .= "\\maketitle\n\n";
        }

        foreach ($this->normalize($blocks) as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');
            $text = (string) ($block['text'] ?? '');

            if ($type === 'heading') {
                $lvl = max(1, min(3, (int) ($block['level'] ?? 1)));
                $cmd = match ($lvl) {
                    1 => '\\section',
                    2 => '\\subsection',
                    default => '\\subsubsection',
                };
                $out .= $cmd.'{'.$esc($text)."}\n\n";
            } elseif ($type === 'quote') {
                $out .= "\\begin{quote}\n".$esc($text)."\n\\end{quote}\n\n";
            } elseif ($type === 'callout') {
                $out .= "\\begin{quote}\n\\textbf{[".strtoupper((string) ($block['calloutType'] ?? 'Catatan')).']} '.$esc($text)."\n\\end{quote}\n\n";
            } elseif ($type === 'bullet') {
                $out .= "\\begin{itemize}\n\\item ".$esc($text)."\n\\end{itemize}\n\n";
            } elseif ($type === 'numbered') {
                $out .= "\\begin{enumerate}\n\\item ".$esc($text)."\n\\end{enumerate}\n\n";
            } elseif ($type === 'pagebreak') {
                $out .= "\\newpage\n\n";
            } elseif ($type === 'equation') {
                $latex = (string) ($block['equation']['latex'] ?? $block['latex'] ?? $block['text'] ?? '');
                $caption = (string) ($block['equation']['caption'] ?? $block['caption'] ?? '');
                $out .= "\\begin{equation}\n".$latex."\n"
                    .($caption !== '' ? '\\label{eq:'.preg_replace('/[^a-zA-Z0-9]/', '', $caption)."}\n" : '')
                    ."\\end{equation}\n\n";
            } elseif ($type === 'image') {
                $src = (string) ($block['image']['src'] ?? '');
                $caption = (string) ($block['image']['caption'] ?? $block['image']['alt'] ?? 'Gambar Naskah');
                $out .= "\\begin{figure}[h]\n\\centering\n% \\includegraphics[width=0.8\\textwidth]{".$esc($src)."}\n\\caption{".$esc($caption)."}\n\\end{figure}\n\n";
            } elseif ($type === 'table') {
                $rows = (array) ($block['table']['rows'] ?? []);
                $cols = count((array) ($rows[0] ?? []));
                if ($cols > 0) {
                    $colSpec = str_repeat('l', $cols);
                    $out .= "\\begin{table}[h]\n\\centering\n\\begin{tabular}{".$colSpec."}\n\\toprule\n";
                    foreach ($rows as $r => $row) {
                        $cells = array_map(static fn ($c): string => $esc(is_array($c) ? ($c['text'] ?? '') : (string) $c), (array) $row);
                        $out .= implode(' & ', $cells)." \\\\\n";
                        if ($r === 0) {
                            $out .= "\\midrule\n";
                        }
                    }
                    $out .= "\\bottomrule\n\\end{tabular}\n\\end{table}\n\n";
                }
            } else {
                if (trim($text) !== '') {
                    $out .= $esc($text)."\n\n";
                }
            }
        }

        $out .= "\\end{document}\n";

        return $out;
    }

    /**
     * Ekspor blok naskah menjadi Rich Text Format (.rtf).
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function toRtf(array $blocks, string $title = ''): string
    {
        $rtfEsc = static function (string $text): string {
            $out = '';
            $len = mb_strlen($text);
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($text, $i, 1);
                $ord = mb_ord($char);
                if ($ord < 128) {
                    if ($char === '\\' || $char === '{' || $char === '}') {
                        $out .= '\\'.$char;
                    } elseif ($char === "\n") {
                        $out .= '\\par ';
                    } else {
                        $out .= $char;
                    }
                } else {
                    $out .= '\\u'.$ord.'?';
                }
            }

            return $out;
        };

        $out = "{\\rtf1\\ansi\\deff0\n{\\fonttbl{\\f0\\froman\\fcharset0 Georgia;}{\\f1\\fswiss\\fcharset0 Arial;}}\n";
        $out .= "{\\colortbl ;\\red30\\green64\\blue175;\\red51\\green65\\blue85;}\n";

        if ($title !== '') {
            $out .= '\\pard\\qc\\b\\fs36\\cf1 '.$rtfEsc($title)."\\b0\\par\\par\n";
        }

        foreach ($this->normalize($blocks) as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');
            $text = (string) ($block['text'] ?? '');

            if ($type === 'heading') {
                $lvl = max(1, min(3, (int) ($block['level'] ?? 1)));
                $size = match ($lvl) {
                    1 => 32,
                    2 => 28,
                    default => 24,
                };
                $out .= '\\pard\\ql\\b\\fs'.$size.'\\cf1 '.$rtfEsc($text)."\\b0\\fs24\\cf0\\par\n";
            } elseif ($type === 'quote') {
                $out .= '\\pard\\li720\\i\\cf2 '.$rtfEsc($text)."\\i0\\cf0\\par\n";
            } elseif ($type === 'bullet') {
                $out .= '\\pard\\li360 {\\pntext\\bullet\\tab}'.$rtfEsc($text)."\\par\n";
            } elseif ($type === 'numbered') {
                $out .= '\\pard\\li360 '.$rtfEsc($text)."\\par\n";
            } elseif ($type === 'pagebreak') {
                $out .= "\\page\n";
            } else {
                if (trim($text) !== '') {
                    $out .= '\\pard\\ql\\fs24 '.$rtfEsc($text)."\\par\n";
                }
            }
        }

        $out .= "}\n";

        return $out;
    }

    /**
     * Ubah isi sub-bab proyek menjadi blok kaya.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    public function fromSections(array $sections): array
    {
        $parser = new NaskahBlockParser;
        $blocks = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = trim((string) ($section['heading'] ?? ''));
            $content = trim((string) ($section['content'] ?? ''));

            if ($heading !== '') {
                $blocks[] = $this->newBlock('heading', $heading, 1);
            }

            foreach ($parser->parse($content) as $parsed) {
                $type = $parsed['type'] === 'heading' ? 'paragraph' : $parsed['type'];
                $blocks[] = $this->newBlock($type, (string) $parsed['text'], (int) $parsed['level']);
            }
        }

        return $blocks;
    }

    private function align(mixed $align): string
    {
        return in_array($align, ['left', 'center', 'right', 'justify'], true) ? $align : 'left';
    }

    private function color(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^[a-z]{3,20}$/i', $value) === 1) {
            return $value;
        }

        return null;
    }

    private function safeUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(https?:|mailto:|#|\/|data:image\/)/i', $value) === 1) {
            return $value;
        }

        return null;
    }
}
