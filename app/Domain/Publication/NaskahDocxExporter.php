<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use ZipArchive;

/**
 * NaskahDocxExporter: menulis blok naskah menjadi berkas .docx (OOXML) nyata.
 *
 * Membuat arsip zip minimal yang valid: [Content_Types].xml, _rels/.rels,
 * word/document.xml, word/styles.xml, dan word/_rels/document.xml.rels.
 * Mendukung judul/heading, paragraf dengan format inline (tebal, miring,
 * garis bawah, coret, sub/sup, warna, highlight), perataan, indentasi,
 * daftar, tabel, gambar (embedded), dan pemisah halaman.
 */
final class NaskahDocxExporter
{
    public function __construct(
        private readonly NaskahRichModel $model,
    ) {}

    /**
     * Bangun biner .docx dari blok.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $options  ['watermark'=>string,'title'=>string]
     */
    public function build(array $blocks, string $title = '', array $options = []): string
    {
        $blocks = $this->model->normalize($blocks);

        $temp = tempnam(sys_get_temp_dir(), 'xishie-docx-');
        if ($temp === false) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) {
            @unlink($temp);

            return '';
        }

        $media = [];
        $documentXml = $this->documentXml($blocks, $title, $media, $options);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($media)));
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        $zip->addFromString('word/numbering.xml', $this->numberingXml());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRels(count($media)));

        foreach ($media as $i => $binary) {
            $zip->addFromString('word/media/image'.($i + 1).'.png', $binary);
        }

        $zip->close();

        $binary = (string) file_get_contents($temp);
        @unlink($temp);

        return $binary;
    }

    /**
     * Alias to build().
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $options
     */
    public function toDocx(array $blocks, string $title = '', array $options = []): string
    {
        return $this->build($blocks, $title, $options);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, string>  $media
     * @param  array<string, mixed>  $options
     */
    private function documentXml(array $blocks, string $title, array &$media, array $options): string
    {
        $body = '';

        if ($title !== '') {
            $body .= $this->paragraphXml('<w:pPr><w:pStyle w:val="Title"/><w:jc w:val="center"/></w:pPr>', [
                [['text' => $title, 'bold' => true], null],
            ], 32);
        }

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'pagebreak') {
                $body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

                continue;
            }

            if ($type === 'table' || $type === 'toc') {
                $body .= $this->tableXml($block);

                continue;
            }

            if ($type === 'equation') {
                $latex = (string) ($block['equation']['latex'] ?? $block['latex'] ?? $block['text'] ?? '');
                $caption = (string) ($block['equation']['caption'] ?? $block['caption'] ?? '');
                $body .= $this->equationBlockXml($latex, $caption);

                continue;
            }

            if ($type === 'footnote-ref' || $type === 'endnote-ref') {
                $isEndnote = $type === 'endnote-ref';
                $label = $isEndnote ? '[Catatan Akhir] ' : '[Catatan Kaki] ';
                $text = (string) ($block['text'] ?? '');
                $body .= '<w:p><w:pPr><w:spacing w:before="120" w:after="120"/></w:pPr>'
                    .'<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="18"/><w:i/><w:color w:val="475569"/></w:rPr>'
                    .'<w:t xml:space="preserve">'.$label.$this->esc($text).'</w:t></w:r></w:p>';

                continue;
            }

            if ($type === 'callout') {
                $cType = (string) ($block['calloutType'] ?? 'info');
                $prefix = match ($cType) {
                    'warning' => '[Peringatan] ',
                    'tip' => '[Tips] ',
                    'theorem' => '[Teorema] ',
                    'important' => '[Penting] ',
                    'caution' => '[Perhatian] ',
                    'sketch' => '[Sketsa & Diagram] ',
                    default => '[Catatan] ',
                };
                $color = match ($cType) {
                    'warning' => 'D83B01',
                    'caution' => 'A80000',
                    'important' => '8E44AD',
                    'tip' => '107C41',
                    'sketch' => '004E8C',
                    default => '0F6CBD',
                };
                $text = (string) ($block['text'] ?? '');
                $body .= '<w:p><w:pPr><w:pBdr><w:left w:val="single" w:sz="24" w:space="12" w:color="'.$color.'"/></w:pBdr>'
                    .'<w:ind w:left="360"/><w:spacing w:before="180" w:after="180"/></w:pPr>'
                    .'<w:r><w:rPr><w:b/><w:color w:val="'.$color.'"/></w:rPr><w:t xml:space="preserve">'.$this->esc($prefix).'</w:t></w:r>'
                    .'<w:r><w:t xml:space="preserve">'.$this->esc($text).'</w:t></w:r></w:p>';

                continue;
            }

            if ($type === 'image') {
                $src = (string) ($block['image']['src'] ?? '');
                $caption = (string) ($block['image']['caption'] ?? $block['image']['alt'] ?? '');
                $binary = $this->decodeDataUrl($src);

                if ($binary !== null) {
                    $media[] = $binary;
                    $relId = 'rId'.(100 + count($media));
                    $body .= $this->imageParagraphXml($relId, (int) ($block['image']['width'] ?? 320));
                    if ($caption !== '') {
                        $body .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="60" w:after="180"/></w:pPr>'
                            .'<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:i/><w:sz w:val="18"/><w:color w:val="666666"/></w:rPr>'
                            .'<w:t xml:space="preserve">'.$this->esc($caption).'</w:t></w:r></w:p>';
                    }
                }

                continue;
            }

            $body .= $this->textBlockXml($block);
        }

        $isLandscape = strtolower((string) ($options['orientation'] ?? 'portrait')) === 'landscape';
        $pgSz = $isLandscape
            ? '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
            : '<w:pgSz w:w="11906" w:h="16838"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            .'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
            .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            .'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<w:body>'.$body
            .'<w:sectPr>'.$pgSz.'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function textBlockXml(array $block): string
    {
        $type = (string) ($block['type'] ?? 'paragraph');
        $align = (string) ($block['align'] ?? 'left');
        $jc = match ($align) {
            'center' => '<w:jc w:val="center"/>',
            'right' => '<w:jc w:val="right"/>',
            'justify' => '<w:jc w:val="both"/>',
            default => '<w:jc w:val="left"/>',
        };

        $indent = (int) ($block['indent'] ?? 0);
        $firstLine = (bool) ($block['firstLineIndent'] ?? false);
        $hanging = (bool) ($block['hangingIndent'] ?? false);
        $rightIndent = (int) ($block['rightIndent'] ?? 0);
        $ind = '';
        if ($indent > 0 || $firstLine || $hanging || $rightIndent > 0) {
            $leftAttr = ($indent > 0 || $hanging) ? ' w:left="'.(($indent > 0 ? $indent : 1) * 720).'"' : '';
            $firstLineAttr = $firstLine ? ' w:firstLine="720"' : '';
            $hangingAttr = $hanging ? ' w:hanging="720"' : '';
            $rightAttr = $rightIndent > 0 ? ' w:right="'.($rightIndent * 720).'"' : '';
            $ind = '<w:ind'.$leftAttr.$firstLineAttr.$hangingAttr.$rightAttr.'/>';
        }

        $shd = '';
        if (! empty($block['backgroundColor']) && is_string($block['backgroundColor']) && $block['backgroundColor'] !== 'none') {
            $bgHex = $this->hex($block['backgroundColor']);
            if ($bgHex !== null) {
                $shd = '<w:shd w:val="clear" w:fill="'.$bgHex.'"/>';
            }
        }

        $bdr = '';
        if (! empty($block['borderColor']) && is_string($block['borderColor'])) {
            $bdrHex = $this->hex($block['borderColor']);
            if ($bdrHex !== null) {
                $bdr = '<w:pBdr><w:left w:val="single" w:sz="18" w:space="12" w:color="'.$bdrHex.'"/></w:pBdr>';
            }
        }

        $spacing = '';
        $before = (int) ($block['spacingBefore'] ?? 0);
        $after = (int) ($block['spacingAfter'] ?? 0);
        $line = (float) ($block['lineSpacing'] ?? 1.0);
        if ($before > 0 || $after > 0 || $line > 1.0) {
            $spacing = '<w:spacing w:before="'.($before * 20).'" w:after="'.($after * 20).'" w:line="'.(int) ($line * 240).'" w:lineRule="auto"/>';
        }

        $style = match ($type) {
            'heading' => '<w:pStyle w:val="Heading'.(int) ($block['level'] ?? 1).'"/>',
            'quote' => '<w:pStyle w:val="Quote"/>',
            default => '',
        };

        $numPr = '';
        if (in_array($type, ['bullet', 'numbered'], true)) {
            $numId = $type === 'bullet' ? 1 : 2;
            $numPr = '<w:numPr><w:ilvl w:val="'.(int) ($block['listLevel'] ?? 0).'"/><w:numId w:val="'.$numId.'"/></w:numPr>';
        }

        $pPr = '<w:pPr>'.$style.$numPr.$spacing.$ind.$jc.$bdr.$shd.'</w:pPr>';

        $content = '';
        $size = $type === 'heading' ? (32 - ((int) ($block['level'] ?? 1) * 4)) : 24;

        foreach ($this->model->runsWithText($block) as $run) {
            $content .= $this->runXml((string) $run['text'], $run, $size);
        }

        if ($type === 'quote') {
            $pPr = str_replace('</w:pPr>', '<w:ind w:left="720"/></w:pPr>', $pPr);
        }

        return '<w:p>'.$pPr.$content.'</w:p>';
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function runXml(string $text, array $run, int $size = 24): string
    {
        $rPr = '<w:rPr>';
        if (! empty($run['bold'])) {
            $rPr .= '<w:b/>';
        }
        if (! empty($run['italic'])) {
            $rPr .= '<w:i/>';
        }
        if (! empty($run['underline'])) {
            $rPr .= '<w:u w:val="single"/>';
        }
        if (! empty($run['strike'])) {
            $rPr .= '<w:strike/>';
        }
        if (! empty($run['sub'])) {
            $rPr .= '<w:vertAlign w:val="subscript"/>';
        }
        if (! empty($run['sup'])) {
            $rPr .= '<w:vertAlign w:val="superscript"/>';
        }
        if (! empty($run['smallCaps'])) {
            $rPr .= '<w:smallCaps/>';
        }
        if (! empty($run['allCaps'])) {
            $rPr .= '<w:caps/>';
        }
        if (! empty($run['color']) && is_string($run['color'])) {
            $hex = $this->hex($run['color']);
            if ($hex !== null) {
                $rPr .= '<w:color w:val="'.$hex.'"/>';
            }
        } elseif (! empty($run['link'])) {
            $rPr .= '<w:color w:val="2563EB"/>';
        }
        if (! empty($run['link']) && empty($run['underline'])) {
            $rPr .= '<w:u w:val="single"/>';
        }
        if (! empty($run['highlight']) && is_string($run['highlight'])) {
            $hex = $this->hex($run['highlight']);
            if ($hex !== null) {
                $rPr .= '<w:highlight w:val="yellow"/>';
            }
        }
        $rPr .= '<w:sz w:val="'.$size.'"/></w:rPr>';

        if (str_contains($text, '$') && preg_match('/\$(.+?)\$/', $text)) {
            $parts = preg_split('/(\$.+?\$)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            $out = '';
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if (str_starts_with($part, '$') && str_ends_with($part, '$') && strlen($part) > 2) {
                    $mathContent = substr($part, 1, -1);
                    $mathPr = '<w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math"/><w:i/><w:sz w:val="'.$size.'"/></w:rPr>';
                    $out .= '<w:r>'.$mathPr.'<w:t xml:space="preserve">'.$this->esc($mathContent).'</w:t></w:r>';
                } else {
                    $out .= '<w:r>'.$rPr.'<w:t xml:space="preserve">'.$this->esc($part).'</w:t></w:r>';
                }
            }

            return $out;
        }

        return '<w:r>'.$rPr.'<w:t xml:space="preserve">'.$this->esc($text).'</w:t></w:r>';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function tableXml(array $block): string
    {
        $rows = (array) ($block['table']['rows'] ?? []);
        $header = (bool) ($block['table']['header'] ?? true);
        $cols = $rows !== [] ? count((array) $rows[0]) : 0;

        if ($cols === 0) {
            return '';
        }

        $caption = (string) ($block['table']['caption'] ?? $block['caption'] ?? '');
        $captionXml = '';
        if ($caption !== '') {
            $captionXml = '<w:p><w:pPr><w:spacing w:before="120" w:after="60"/></w:pPr>'
                .'<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:i/><w:color w:val="334155"/><w:sz w:val="20"/></w:rPr>'
                .'<w:t xml:space="preserve">'.$this->esc($caption).'</w:t></w:r></w:p>';
        }

        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:space="0" w:color="94A3B8"/>'
            .'<w:left w:val="single" w:sz="4" w:space="0" w:color="94A3B8"/>'
            .'<w:bottom w:val="single" w:sz="4" w:space="0" w:color="94A3B8"/>'
            .'<w:right w:val="single" w:sz="4" w:space="0" w:color="94A3B8"/>'
            .'<w:insideH w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            .'<w:insideV w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            .'</w:tblBorders>'
            .'<w:tblCellMar>'
            .'<w:top w:w="120" w:type="dxa"/><w:left w:w="160" w:type="dxa"/><w:bottom w:w="120" w:type="dxa"/><w:right w:w="160" w:type="dxa"/>'
            .'</w:tblCellMar>'
            .'</w:tblPr><w:tblGrid>';

        for ($c = 0; $c < $cols; $c++) {
            $xml .= '<w:gridCol w:w="'.(int) (9000 / $cols).'"/>';
        }
        $xml .= '</w:tblGrid>';

        foreach ($rows as $rIndex => $row) {
            $xml .= '<w:tr>';
            foreach ((array) $row as $cell) {
                $cellText = (string) ($cell['text'] ?? '');
                $isHeader = $header && $rIndex === 0;
                $cellBgHex = ! empty($cell['bg']) ? $this->hex((string) $cell['bg']) : (! empty($cell['highlight']) ? $this->hex((string) $cell['highlight']) : null);
                $shading = $cellBgHex !== null
                    ? '<w:shd w:val="clear" w:fill="'.$cellBgHex.'"/>'
                    : ($isHeader ? '<w:shd w:val="clear" w:fill="EFF6FF"/>' : '');

                $cellAlign = (string) ($cell['align'] ?? ($isHeader ? 'center' : 'left'));
                $cellJc = match ($cellAlign) {
                    'center' => '<w:jc w:val="center"/>',
                    'right' => '<w:jc w:val="right"/>',
                    'justify' => '<w:jc w:val="both"/>',
                    default => '<w:jc w:val="left"/>',
                };

                $cellRuns = '';
                foreach ($this->model->runsWithText(['text' => $cellText, 'runs' => $cell['runs'] ?? []]) as $run) {
                    $run['bold'] = $isHeader ? true : ($run['bold'] ?? false);
                    $cellRuns .= $this->runXml((string) $run['text'], $run, 22);
                }
                $xml .= '<w:tc><w:tcPr>'.$shading.'</w:tcPr><w:p><w:pPr>'.$cellJc.'</w:pPr>'.$cellRuns.'</w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }

        return $captionXml.$xml.'</w:tbl><w:p/>';
    }

    private function imageParagraphXml(string $relId, int $width): string
    {
        $width = max(80, min(560, $width > 0 ? $width : 320));
        $emu = $width * 9525;

        return '<w:p><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$emu.'" cy="'.(int) ($emu * 0.66).'"/>'
            .'<wp:docPr id="1" name="Gambar"/>'
            .'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="image.png"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="'.$relId.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$emu.'" cy="'.(int) ($emu * 0.66).'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            .'</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    /**
     * @param  array<int, string>  $textRuns
     */
    private function paragraphXml(string $pPr, array $runs, int $size): string
    {
        $content = '';
        foreach ($runs as $pair) {
            if (! is_array($pair) || ! isset($pair[0]['text'])) {
                continue;
            }
            $content .= $this->runXml((string) $pair[0]['text'], $pair[0], $size);
        }

        return '<w:p>'.$pPr.$content.'</w:p>';
    }

    private function contentTypes(int $mediaCount): string
    {
        $defaults = '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .$defaults
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function documentRels(int $mediaCount): string
    {
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>';

        for ($i = 1; $i <= $mediaCount; $i++) {
            $rels .= '<Relationship Id="rId'.(100 + $i).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image'.$i.'.png"/>';
        }

        return $rels.'</Relationships>';
    }

    private function numberingXml(): string
    {
        $bulletLevels = '';
        $numberedLevels = '';

        for ($lvl = 0; $lvl <= 8; $lvl++) {
            $indLeft = ($lvl + 1) * 720;
            $bulletChar = match ($lvl % 3) {
                0 => '&#8226;',
                1 => 'o',
                2 => '&#9642;',
            };
            $bulletFont = match ($lvl % 3) {
                0 => 'Symbol',
                1 => 'Courier New',
                2 => 'Wingdings',
            };
            $bulletLevels .= '<w:lvl w:ilvl="'.$lvl.'">'
                .'<w:start w:val="1"/>'
                .'<w:numFmt w:val="bullet"/>'
                .'<w:lvlText w:val="'.$bulletChar.'"/>'
                .'<w:rPr><w:rFonts w:ascii="'.$bulletFont.'" w:hAnsi="'.$bulletFont.'" w:hint="default"/></w:rPr>'
                .'<w:pPr><w:ind w:left="'.$indLeft.'" w:hanging="360"/></w:pPr>'
                .'</w:lvl>';

            $numFmt = match ($lvl % 3) {
                0 => 'decimal',
                1 => 'lowerLetter',
                2 => 'lowerRoman',
            };
            $numberedLevels .= '<w:lvl w:ilvl="'.$lvl.'">'
                .'<w:start w:val="1"/>'
                .'<w:numFmt w:val="'.$numFmt.'"/>'
                .'<w:lvlText w:val="%'.($lvl + 1).'."/>'
                .'<w:pPr><w:ind w:left="'.$indLeft.'" w:hanging="360"/></w:pPr>'
                .'</w:lvl>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:abstractNum w:abstractNumId="1">'
            .'<w:multiLevelType w:val="hybridMultilevel"/>'
            .$bulletLevels
            .'</w:abstractNum>'
            .'<w:abstractNum w:abstractNumId="2">'
            .'<w:multiLevelType w:val="hybridMultilevel"/>'
            .$numberedLevels
            .'</w:abstractNum>'
            .'<w:num w:numId="1"><w:abstractNumId w:val="1"/></w:num>'
            .'<w:num w:numId="2"><w:abstractNumId w:val="2"/></w:num>'
            .'</w:numbering>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Georgia" w:hAnsi="Georgia"/><w:sz w:val="24"/></w:rPr></w:rPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:styleId="Normal" w:default="1"><w:name w:val="Normal"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:rPr><w:b/><w:sz w:val="32"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:rPr><w:b/><w:sz w:val="28"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:rPr><w:b/><w:sz w:val="26"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:rPr><w:b/><w:sz w:val="24"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:rPr><w:i/><w:color w:val="334155"/></w:rPr></w:style>'
            .'<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/></w:style>'
            .'</w:styles>';
    }

    private function equationBlockXml(string $latex, string $caption = ''): string
    {
        $capTrim = trim($caption, '()');
        $capText = $capTrim !== '' ? '   ('.$this->esc($capTrim).')' : '';

        return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="240" w:after="240"/></w:pPr>'
            .'<w:r><w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math"/><w:i/><w:sz w:val="24"/></w:rPr>'
            .'<w:t xml:space="preserve">'.$this->esc($latex).$capText.'</w:t></w:r></w:p>';
    }

    private function decodeDataUrl(string $src): ?string
    {
        if (preg_match('/^data:image\/[a-z0-9+]+;base64,(.+)$/is', $src, $m) === 1) {
            $binary = base64_decode($m[1], true);

            return $binary === false ? null : $binary;
        }

        if (str_starts_with($src, '/storage/')) {
            $path = public_path(ltrim($src, '/'));
            if (is_file($path) && is_readable($path)) {
                return file_get_contents($path) ?: null;
            }
        }

        return null;
    }

    private function hex(string $color): ?string
    {
        $color = ltrim(trim($color), '#');

        if (preg_match('/^[0-9a-f]{6}$/i', $color) === 1) {
            return strtoupper($color);
        }

        if (preg_match('/^[0-9a-f]{3}$/i', $color) === 1) {
            return strtoupper($color[0].$color[0].$color[1].$color[1].$color[2].$color[2]);
        }

        return null;
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
