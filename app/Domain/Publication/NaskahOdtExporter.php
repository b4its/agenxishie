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
            .'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" office:version="1.3">'."\n"
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
            .' </office:styles>'."\n"
            .'</office:document-styles>';
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

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');
            $text = (string) ($block['text'] ?? '');
            $escText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if ($type === 'heading') {
                $level = max(1, min(3, (int) ($block['level'] ?? 1)));
                $body .= '<text:h text:outline-level="'.$level.'" text:style-name="Heading_'.$level.'">'.$escText.'</text:h>';
            } elseif ($type === 'quote') {
                $body .= '<text:p text:style-name="Quotations">'.$escText.'</text:p>';
            } elseif ($type === 'callout') {
                $cType = strtoupper((string) ($block['calloutType'] ?? 'CATATAN'));
                $body .= '<text:p text:style-name="Quotations">['.$cType.'] '.$escText.'</text:p>';
            } elseif ($type === 'bullet') {
                $body .= '<text:list><text:list-item><text:p>'.$escText.'</text:p></text:list-item></text:list>';
            } elseif ($type === 'numbered') {
                $body .= '<text:list><text:list-item><text:p>'.$escText.'</text:p></text:list-item></text:list>';
            } elseif ($type === 'pagebreak') {
                $body .= '<text:p><text:page-break/></text:p>';
            } elseif ($type === 'footnote-ref' || $type === 'endnote-ref') {
                $badge = ($type === 'endnote-ref') ? '[Catatan Akhir]' : '[Catatan Kaki]';
                $body .= '<text:p text:style-name="Footnote">'.$badge.' '.$escText.'</text:p>';
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
                    foreach ($rows as $row) {
                        $body .= '<table:table-row>';
                        foreach ((array) $row as $cell) {
                            $cellText = is_array($cell) ? (string) ($cell['text'] ?? '') : (string) $cell;
                            $body .= '<table:table-cell office:value-type="string"><text:p>'
                                .htmlspecialchars($cellText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                                .'</text:p></table:table-cell>';
                        }
                        $body .= '</table:table-row>';
                    }
                    $body .= '</table:table>';
                }
            } else {
                $body .= '<text:p>'.$escText.'</text:p>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
            .'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" '
            .'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" '
            .'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" office:version="1.3">'."\n"
            .' <office:body>'."\n"
            .'  <office:text>'."\n"
            .'   '.$body."\n"
            .'  </office:text>'."\n"
            .' </office:body>'."\n"
            .'</office:document-content>';
    }
}
