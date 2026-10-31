<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use ZipArchive;

/**
 * NaskahOdtExporter: menulis blok naskah menjadi berkas OpenDocument Text (.odt)
 * standar LibreOffice Writer dan OASIS OpenDocument 1.3.
 */
final class NaskahOdtExporter
{
    public function __construct(
        private readonly NaskahRichModel $model,
    ) {}

    /**
     * Bangun biner .odt dari blok naskah.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $options
     */
    public function build(array $blocks, string $title = '', array $options = []): string
    {
        $blocks = $this->model->normalize($blocks);

        $temp = tempnam(sys_get_temp_dir(), 'xishie-odt-');
        if ($temp === false) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) {
            @unlink($temp);

            return '';
        }

        // 1. mimetype (harus file pertama dalam arsip zip dan tidak terkompresi)
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

        // 2. META-INF/manifest.xml
        $zip->addFromString('META-INF/manifest.xml', $this->manifestXml());

        // 3. meta.xml
        $zip->addFromString('meta.xml', $this->metaXml($title));

        // 4. styles.xml
        $zip->addFromString('styles.xml', $this->stylesXml());

        // 5. content.xml
        $zip->addFromString('content.xml', $this->contentXml($blocks, $title));

        $zip->close();

        $binary = (string) file_get_contents($temp);
        @unlink($temp);

        return $binary;
    }

    private function manifestXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">'."\n"
            .' <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>'."\n"
            .' <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>'."\n"
            .' <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>'."\n"
            .' <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>'."\n"
            .'</manifest:manifest>';
    }

    private function metaXml(string $title): string
    {
        $esc = htmlspecialchars($title !== '' ? $title : 'Naskah', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $date = now()->toIso8601String();

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" office:version="1.3">'."\n"
            .' <office:meta>'."\n"
            .'  <dc:title>'.$esc.'</dc:title>'."\n"
            .'  <dc:creator>Xishie Naskah Studio</dc:creator>'."\n"
            .'  <dc:date>'.$date.'</dc:date>'."\n"
            .' </office:meta>'."\n"
            .'</office:document-meta>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
            .'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" '
            .'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" '
            .'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.3">'."\n"
            .' <office:styles>'."\n"
            .'  <style:style style:name="Standard" style:family="paragraph" style:class="text">'."\n"
            .'   <style:text-properties fo:font-size="12pt" fo:font-family="Georgia"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="Heading_1" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:margin-top="0.6cm" fo:margin-bottom="0.3cm"/>'."\n"
            .'   <style:text-properties fo:font-size="18pt" fo:font-weight="bold" fo:color="#1e40af"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="Heading_2" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:margin-top="0.45cm" fo:margin-bottom="0.2cm"/>'."\n"
            .'   <style:text-properties fo:font-size="15pt" fo:font-weight="bold" fo:color="#1e40af"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="Heading_3" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:margin-top="0.35cm" fo:margin-bottom="0.15cm"/>'."\n"
            .'   <style:text-properties fo:font-size="13pt" fo:font-weight="bold" fo:color="#1e40af"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="Quotations" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:margin-left="1cm" fo:margin-top="0.3cm" fo:margin-bottom="0.3cm"/>'."\n"
            .'   <style:text-properties fo:font-style="italic" fo:color="#334155"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="Callout_Box" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:margin-left="0.8cm" fo:padding-left="0.4cm" fo:border-left="0.08cm solid #2563eb" fo:margin-top="0.3cm" fo:margin-bottom="0.3cm"/>'."\n"
            .'   <style:text-properties fo:color="#1e293b"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="P_Justify" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:text-align="justify"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="P_Center" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:text-align="center"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="P_Right" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:text-align="end"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="P_Hanging" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:margin-left="1.27cm" fo:text-indent="-1.27cm" fo:text-align="justify"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="Footnote" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:margin-top="0.2cm" fo:margin-bottom="0.2cm"/>'."\n"
            .'   <style:text-properties fo:font-size="10pt" fo:font-style="italic" fo:color="#475569"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="Formula" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:text-align="center" fo:margin-top="0.4cm" fo:margin-bottom="0.4cm"/>'."\n"
            .'   <style:text-properties fo:font-family="Cambria Math" fo:font-size="12pt"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="TableCaption" style:family="paragraph" style:parent-style-name="Standard">'."\n"
            .'   <style:paragraph-properties fo:margin-top="0.3cm" fo:margin-bottom="0.15cm"/>'."\n"
            .'   <style:text-properties fo:font-weight="bold" fo:font-size="10pt" fo:color="#334155"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="T_Bold" style:family="text">'."\n"
            .'   <style:text-properties fo:font-weight="bold"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="T_Italic" style:family="text">'."\n"
            .'   <style:text-properties fo:font-style="italic"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="T_Bold_Italic" style:family="text">'."\n"
            .'   <style:text-properties fo:font-weight="bold" fo:font-style="italic"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="T_Underline" style:family="text">'."\n"
            .'   <style:text-properties style:text-underline-style="solid" style:text-underline-width="auto" style:text-underline-color="font-color"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="T_Strike" style:family="text">'."\n"
            .'   <style:text-properties style:text-line-through-style="solid"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="T_Sub" style:family="text">'."\n"
            .'   <style:text-properties style:text-position="sub 58%"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="T_Sup" style:family="text">'."\n"
            .'   <style:text-properties style:text-position="super 58%"/>'."\n"
            .'  </style:style>'."\n"
            .'  <style:style style:name="T_Math" style:family="text">'."\n"
            .'   <style:text-properties fo:font-family="Cambria Math" fo:font-style="italic"/>'."\n"
            .'  </style:style>'."\n"
            .'  <text:list-style style:name="BulletList">'."\n"
            .'   <text:list-level-style-bullet text:level="1" text:style-name="Standard" text:bullet-char="•">'."\n"
            .'    <style:list-level-properties text:space-before="0.6cm" text:min-label-width="0.5cm"/>'."\n"
            .'   </text:list-level-style-bullet>'."\n"
            .'   <text:list-level-style-bullet text:level="2" text:style-name="Standard" text:bullet-char="◦">'."\n"
            .'    <style:list-level-properties text:space-before="1.2cm" text:min-label-width="0.5cm"/>'."\n"
            .'   </text:list-level-style-bullet>'."\n"
            .'   <text:list-level-style-bullet text:level="3" text:style-name="Standard" text:bullet-char="▪">'."\n"
            .'    <style:list-level-properties text:space-before="1.8cm" text:min-label-width="0.5cm"/>'."\n"
            .'   </text:list-level-style-bullet>'."\n"
            .'  </text:list-style>'."\n"
            .'  <text:list-style style:name="NumberedList">'."\n"
            .'   <text:list-level-style-number text:level="1" style:num-format="1" style:num-suffix=".">'."\n"
            .'    <style:list-level-properties text:space-before="0.6cm" text:min-label-width="0.5cm"/>'."\n"
            .'   </text:list-level-style-number>'."\n"
            .'   <text:list-level-style-number text:level="2" style:num-format="a" style:num-suffix=".">'."\n"
            .'    <style:list-level-properties text:space-before="1.2cm" text:min-label-width="0.5cm"/>'."\n"
            .'   </text:list-level-style-number>'."\n"
            .'   <text:list-level-style-number text:level="3" style:num-format="i" style:num-suffix=".">'."\n"
            .'    <style:list-level-properties text:space-before="1.8cm" text:min-label-width="0.5cm"/>'."\n"
            .'   </text:list-level-style-number>'."\n"
            .'  </text:list-style>'."\n"
            .' </office:styles>'."\n"
            .'</office:document-styles>';
    }

    /**
     * Format runs blok naskah menjadi elemen teks ODT XML.
     *
     * @param  array<string, mixed>  $block
     */
    private function runsToOdt(array $block): string
    {
        $runs = $this->model->runsWithText($block);
        if (empty($runs)) {
            $text = (string) ($block['text'] ?? '');

            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $out = '';
        foreach ($runs as $run) {
            $text = (string) ($run['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $bold = ! empty($run['bold']);
            $italic = ! empty($run['italic']);
            $underline = ! empty($run['underline']);
            $strike = ! empty($run['strike']);
            $sub = ! empty($run['sub']);
            $sup = ! empty($run['sup']);

            $styleName = null;
            if ($bold && $italic) {
                $styleName = 'T_Bold_Italic';
            } elseif ($bold) {
                $styleName = 'T_Bold';
            } elseif ($italic) {
                $styleName = 'T_Italic';
            } elseif ($underline) {
                $styleName = 'T_Underline';
            } elseif ($strike) {
                $styleName = 'T_Strike';
            } elseif ($sub) {
                $styleName = 'T_Sub';
            } elseif ($sup) {
                $styleName = 'T_Sup';
            }

            $link = ! empty($run['link']) ? (string) $run['link'] : null;

            if (str_contains($text, '$') && preg_match('/\$(.+?)\$/', $text)) {
                $parts = preg_split('/(\$.+?\$)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        if ($part === '') {
                            continue;
                        }
                        if (str_starts_with($part, '$') && str_ends_with($part, '$') && strlen($part) > 2) {
                            $math = substr($part, 1, -1);
                            $escMath = htmlspecialchars($math, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $item = '<text:span text:style-name="T_Math">'.$escMath.'</text:span>';
                        } else {
                            $escPart = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $item = $styleName !== null
                                ? '<text:span text:style-name="'.$styleName.'">'.$escPart.'</text:span>'
                                : $escPart;
                        }

                        if ($link !== null) {
                            $escHref = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $out .= '<text:a xlink:type="simple" xlink:href="'.$escHref.'">'.$item.'</text:a>';
                        } else {
                            $out .= $item;
                        }
                    }
                    continue;
                }
            }

            $escText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $span = $styleName !== null
                ? '<text:span text:style-name="'.$styleName.'">'.$escText.'</text:span>'
                : $escText;

            if ($link !== null) {
                $escHref = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $out .= '<text:a xlink:type="simple" xlink:href="'.$escHref.'">'.$span.'</text:a>';
            } else {
                $out .= $span;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function contentXml(array $blocks, string $title): string
    {
        $body = '';

        if ($title !== '') {
            $escTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $body .= '<text:h text:outline-level="1" text:style-name="Heading_1">'.$escTitle.'</text:h>';
        }

        $i = 0;
        $total = count($blocks);

        while ($i < $total) {
            $block = $blocks[$i];
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'bullet' || $type === 'numbered') {
                $listStyle = $type === 'bullet' ? 'BulletList' : 'NumberedList';
                $body .= '<text:list text:style-name="'.$listStyle.'">';
                while ($i < $total && (string) ($blocks[$i]['type'] ?? '') === $type) {
                    $itemBlock = $blocks[$i];
                    $lvl = (int) ($itemBlock['listLevel'] ?? 0);
                    $itemRuns = $this->runsToOdt($itemBlock);
                    if ($lvl > 0) {
                        $body .= '<text:list-item><text:list><text:list-item><text:p>'.$itemRuns.'</text:p></text:list-item></text:list></text:list-item>';
                    } else {
                        $body .= '<text:list-item><text:p>'.$itemRuns.'</text:p></text:list-item>';
                    }
                    $i++;
                }
                $body .= '</text:list>';
                continue;
            }

            if ($type === 'title') {
                $body .= '<text:h text:outline-level="1" text:style-name="Heading_1">'.$this->runsToOdt($block).'</text:h>';
            } elseif ($type === 'subtitle') {
                $body .= '<text:h text:outline-level="2" text:style-name="Heading_2">'.$this->runsToOdt($block).'</text:h>';
            } elseif ($type === 'heading') {
                $level = max(1, min(3, (int) ($block['level'] ?? 1)));
                $body .= '<text:h text:outline-level="'.$level.'" text:style-name="Heading_'.$level.'">'.$this->runsToOdt($block).'</text:h>';
            } elseif ($type === 'image') {
                $caption = (string) ($block['image']['caption'] ?? $block['image']['alt'] ?? $block['caption'] ?? 'Gambar Naskah');
                $escCap = htmlspecialchars($caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $body .= '<text:p text:style-name="P_Center">[Gambar: '.$escCap.']</text:p>';
            } elseif ($type === 'quote') {
                $body .= '<text:p text:style-name="Quotations">'.$this->runsToOdt($block).'</text:p>';
            } elseif ($type === 'callout') {
                $cType = (string) ($block['calloutType'] ?? 'info');
                $label = match ($cType) {
                    'warning' => '[Peringatan]',
                    'caution' => '[Perhatian]',
                    'important' => '[Penting]',
                    'tip' => '[Tips]',
                    'sketch' => '[Sketsa & Diagram]',
                    'theorem' => '[Teorema]',
                    default => '[Catatan]',
                };
                $body .= '<text:p text:style-name="Callout_Box">'.$label.' '.$this->runsToOdt($block).'</text:p>';
            } elseif ($type === 'pagebreak') {
                $body .= '<text:p><text:page-break/></text:p>';
            } elseif ($type === 'footnote-ref' || $type === 'endnote-ref') {
                $badge = ($type === 'endnote-ref') ? '[Catatan Akhir]' : '[Catatan Kaki]';
                $body .= '<text:p text:style-name="Footnote">'.$badge.' '.$this->runsToOdt($block).'</text:p>';
            } elseif ($type === 'equation') {
                $latex = (string) ($block['equation']['latex'] ?? $block['latex'] ?? $block['text'] ?? '');
                $caption = (string) ($block['equation']['caption'] ?? $block['caption'] ?? '');
                $escLatex = htmlspecialchars($latex, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $escCap = htmlspecialchars($caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $body .= '<text:p text:style-name="Formula">'.$escLatex.($escCap !== '' ? ' ('.$escCap.')' : '').'</text:p>';
            } elseif ($type === 'table' || $type === 'toc') {
                $caption = (string) ($block['table']['caption'] ?? $block['caption'] ?? '');
                if ($caption !== '') {
                    $body .= '<text:p text:style-name="TableCaption">'.htmlspecialchars($caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</text:p>';
                }
                $rows = (array) ($block['table']['rows'] ?? []);
                $cols = count((array) ($rows[0] ?? []));
                if ($cols > 0) {
                    $body .= '<table:table table:name="Table'.uniqid().'">';
                    $body .= '<table:table-column table:number-columns-repeated="'.$cols.'"/>';
                    foreach ($rows as $rIdx => $row) {
                        $body .= '<table:table-row>';
                        foreach ((array) $row as $cell) {
                            $cellText = is_array($cell) ? (string) ($cell['text'] ?? '') : (string) $cell;
                            $cellRuns = is_array($cell) ? ($cell['runs'] ?? []) : [];
                            $formatted = ! empty($cellRuns)
                                ? $this->runsToOdt(['text' => $cellText, 'runs' => $cellRuns])
                                : htmlspecialchars($cellText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $body .= '<table:table-cell office:value-type="string"><text:p>'
                                .$formatted
                                .'</text:p></table:table-cell>';
                        }
                        $body .= '</table:table-row>';
                    }
                    $body .= '</table:table>';
                }
            } else {
                $align = (string) ($block['align'] ?? 'left');
                $hanging = ! empty($block['hangingIndent']);
                $pStyle = match (true) {
                    $hanging => 'P_Hanging',
                    $align === 'justify' => 'P_Justify',
                    $align === 'center' => 'P_Center',
                    $align === 'right' => 'P_Right',
                    default => 'Standard',
                };
                $body .= '<text:p text:style-name="'.$pStyle.'">'.$this->runsToOdt($block).'</text:p>';
            }

            $i++;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
            .'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" '
            .'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" '
            .'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" '
            .'xmlns:xlink="http://www.w3.org/1999/xlink" office:version="1.3">'."\n"
            .' <office:body>'."\n"
            .'  <office:text>'."\n"
            .'   '.$body."\n"
            .'  </office:text>'."\n"
            .' </office:body>'."\n"
            .'</office:document-content>';
    }
}
