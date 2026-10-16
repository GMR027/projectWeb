<?php

namespace Classes\Exportadores;

/** Presentación OpenDocument (.odp): una diapositiva por imagen, ajustada a toda la diapositiva */
class OdpExporter
{
    private const NS = 'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" '
        . 'xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" '
        . 'xmlns:presentation="urn:oasis:names:tc:opendocument:xmlns:presentation:1.0" xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" xmlns:dc="http://purl.org/dc/elements/1.1/" '
        . 'xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"';

    /**
     * @param array<int, array{png: string, nombre?: string}> $laminas imágenes PNG (binario), todas del mismo tamaño
     * @return string contenido del archivo .odp
     */
    public static function generar(array $laminas, int $anchoPx, int $altoPx, string $titulo): string
    {
        if (!$laminas) throw new \InvalidArgumentException('Sin láminas');
        $w = 28.0;                                   // cm, ancho de la diapositiva (el alto sigue la proporción de la imagen)
        $h = round($w * $altoPx / max(1, $anchoPx), 3);
        $x = fn(string $t) => htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $paginas = '';
        $manifiesto = '';
        foreach ($laminas as $i => $l) {
            $n = $i + 1;
            $nombre = $x(($l['nombre'] ?? '') !== '' ? $l['nombre'] : "Lámina $n");
            $paginas .= "<draw:page draw:name=\"$nombre\" draw:style-name=\"dp1\" draw:master-page-name=\"Default\">"
                . "<draw:frame draw:style-name=\"gr1\" draw:name=\"imagen$n\" text:anchor-type=\"page\" text:anchor-page-number=\"$n\" svg:x=\"0cm\" svg:y=\"0cm\" svg:width=\"{$w}cm\" svg:height=\"{$h}cm\">"
                . "<draw:image xlink:href=\"Pictures/imagen$n.png\" xlink:type=\"simple\" xlink:show=\"embed\" xlink:actuate=\"onLoad\"/></draw:frame></draw:page>";
            $manifiesto .= "<manifest:file-entry manifest:full-path=\"Pictures/imagen$n.png\" manifest:media-type=\"image/png\"/>";
        }

        $contenido = '<?xml version="1.0" encoding="UTF-8"?>' . "<office:document-content " . self::NS . ' office:version="1.2">'
            . '<office:automatic-styles>'
            . '<style:style style:name="dp1" style:family="drawing-page"><style:drawing-page-properties presentation:background-visible="true" presentation:background-objects-visible="true" draw:fill="none"/></style:style>'
            . '<style:style style:name="gr1" style:family="graphic"><style:graphic-properties draw:stroke="none" draw:fill="none" draw:textarea-horizontal-align="center" draw:textarea-vertical-align="middle" style:run-through="foreground" style:wrap="none"/></style:style>'
            . '</office:automatic-styles>'
            . "<office:body><office:presentation>$paginas</office:presentation></office:body></office:document-content>";

        $estilos = '<?xml version="1.0" encoding="UTF-8"?>' . "<office:document-styles " . self::NS . ' office:version="1.2">'
            . '<office:automatic-styles><style:page-layout style:name="PM1"><style:page-layout-properties fo:margin-top="0cm" fo:margin-bottom="0cm" fo:margin-left="0cm" fo:margin-right="0cm" '
            . "fo:page-width=\"{$w}cm\" fo:page-height=\"{$h}cm\" style:print-orientation=\"" . ($w >= $h ? 'landscape' : 'portrait') . '"/></style:page-layout></office:automatic-styles>'
            . '<office:master-styles><style:master-page style:name="Default" style:page-layout-name="PM1"/></office:master-styles></office:document-styles>';

        $meta = '<?xml version="1.0" encoding="UTF-8"?>' . "<office:document-meta " . self::NS . ' office:version="1.2"><office:meta>'
            . '<dc:title>' . $x($titulo) . '</dc:title><meta:generator>ProjectWeb</meta:generator><meta:creation-date>' . gmdate('Y-m-d\TH:i:s') . '</meta:creation-date></office:meta></office:document-meta>';

        $manifest = '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2">'
            . '<manifest:file-entry manifest:full-path="/" manifest:version="1.2" manifest:media-type="application/vnd.oasis.opendocument.presentation"/>'
            . '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>' . $manifiesto . '</manifest:manifest>';

        $tmp = tempnam(sys_get_temp_dir(), 'odp');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('No se pudo crear el archivo');
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.presentation');   // debe ir primero y sin comprimir
        $zip->setCompressionName('mimetype', \ZipArchive::CM_STORE);
        $zip->addFromString('content.xml', $contenido);
        $zip->addFromString('styles.xml', $estilos);
        $zip->addFromString('meta.xml', $meta);
        $zip->addFromString('META-INF/manifest.xml', $manifest);
        foreach ($laminas as $i => $l) {
            $zip->addFromString('Pictures/imagen' . ($i + 1) . '.png', $l['png']);
            $zip->setCompressionName('Pictures/imagen' . ($i + 1) . '.png', \ZipArchive::CM_STORE);
        }
        $zip->close();
        $datos = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $datos;
    }
}
