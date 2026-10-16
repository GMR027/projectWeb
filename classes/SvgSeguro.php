<?php

namespace Classes;

/** Limpia un SVG subido por un usuario: solo formas y degradados, sin scripts, eventos ni referencias externas */
class SvgSeguro
{
    private const ELEMENTOS = ['svg', 'g', 'defs', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'title', 'desc'];

    /** Devuelve el SVG limpio (con width y height) o null si no es un SVG válido */
    public static function limpiar(string $contenido): ?string
    {
        if ($contenido === '' || strlen($contenido) > 1024 * 1024) return null;
        if (stripos($contenido, '<!ENTITY') !== false || stripos($contenido, '<!DOCTYPE') !== false && stripos($contenido, '[') !== false) return null;
        $previo = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $ok = $doc->loadXML($contenido, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        if (!$ok || !$doc->documentElement || strtolower($doc->documentElement->localName) !== 'svg') return null;

        self::limpiarNodo($doc->documentElement);
        $svg = $doc->documentElement;
        $svg->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        // El editor necesita un tamaño propio para dibujarlo: se toma del viewBox si no lo trae
        $vb = preg_split('/[\s,]+/', trim((string)$svg->getAttribute('viewBox')));
        $w = (float)$svg->getAttribute('width');
        $h = (float)$svg->getAttribute('height');
        if ($w <= 0 || $h <= 0) {
            $w = count($vb) === 4 && (float)$vb[2] > 0 ? (float)$vb[2] : 128;
            $h = count($vb) === 4 && (float)$vb[3] > 0 ? (float)$vb[3] : 128;
            $svg->setAttribute('width', (string)round($w));
            $svg->setAttribute('height', (string)round($h));
            if (!$svg->hasAttribute('viewBox')) $svg->setAttribute('viewBox', '0 0 ' . round($w) . ' ' . round($h));
        }
        return $doc->saveXML($svg);
    }

    private static function limpiarNodo(\DOMElement $el): void
    {
        foreach (iterator_to_array($el->childNodes) as $hijo) {
            if ($hijo instanceof \DOMElement) {
                if (!in_array(strtolower($hijo->localName), self::ELEMENTOS, true)) { $el->removeChild($hijo); continue; }
                self::limpiarNodo($hijo);
            } elseif (!($hijo instanceof \DOMText)) {
                $el->removeChild($hijo);   // comentarios, instrucciones, CDATA
            }
        }
        foreach (iterator_to_array($el->attributes) as $a) {
            $n = strtolower($a->nodeName);
            $v = trim($a->nodeValue);
            $malo = str_starts_with($n, 'on')
                || (in_array($n, ['href', 'xlink:href'], true))
                || (preg_match('/url\s*\(\s*[^#\s)]|javascript:|expression\s*\(|@import|data:/i', $v) === 1);
            if ($malo) $el->removeAttributeNode($a);
        }
    }
}
