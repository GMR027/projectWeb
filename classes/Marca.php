<?php

namespace Classes;

use Model\Proyecto;
use Classes\Exportadores\MsrPdfExporter;

/**
 * Marca del cliente (Fase 4.4): logo, nombre y dos colores del proyecto que se aplican a los PDF de reportes.
 * Los exportadores pasan `'marca' => $proyecto` a Pdf::generar y Pdf::preparar inserta una banda con el logo y los colores
 * (títulos y encabezados de tabla). Los documentos que el usuario redacta (Redactor, SheetFlow, láminas del vPlan) no la llevan.
 * Las columnas viven en proyectos (marca_*); si la migración aún no está aplicada simplemente no hay marca.
 */
class Marca
{
    use AccesoDb;

    public const COLOR_DEFECTO = '#ff5a1f';
    public const COLOR2_DEFECTO = '#1f2a44';

    public static function colorValido(?string $c): ?string
    {
        return $c !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : null;
    }

    /** Marca vigente del proyecto o null si está apagada o sin migración */
    public static function de(Proyecto $p): ?array
    {
        if (!(int)($p->marca_activa ?? 0)) {
            $f = self::filas('SELECT marca_activa, marca_nombre, marca_logo, marca_color, marca_color2 FROM proyectos WHERE id = ' . (int)$p->id)[0] ?? null;
            if (!$f || !(int)$f['marca_activa']) return null;
        } else {
            $f = ['marca_nombre' => $p->marca_nombre ?? '', 'marca_logo' => $p->marca_logo ?? '', 'marca_color' => $p->marca_color ?? '', 'marca_color2' => $p->marca_color2 ?? ''];
        }
        return [
            'nombre' => (string)$f['marca_nombre'], 'logo' => (string)$f['marca_logo'],
            'color' => self::colorValido($f['marca_color']) ?? self::COLOR_DEFECTO, 'color2' => self::colorValido($f['marca_color2']) ?? self::COLOR2_DEFECTO,
        ];
    }

    /** Valores guardados aunque la marca esté apagada (para el formulario) */
    public static function configuracion(Proyecto $p): array
    {
        $f = self::filas('SELECT marca_activa, marca_nombre, marca_logo, marca_color, marca_color2 FROM proyectos WHERE id = ' . (int)$p->id)[0] ?? null;
        return [
            'disponible' => $f !== null, 'activa' => (bool)($f['marca_activa'] ?? 0), 'nombre' => (string)($f['marca_nombre'] ?? ''), 'logo' => (string)($f['marca_logo'] ?? ''),
            'color' => self::colorValido($f['marca_color'] ?? null) ?? self::COLOR_DEFECTO, 'color2' => self::colorValido($f['marca_color2'] ?? null) ?? self::COLOR2_DEFECTO,
        ];
    }

    /** Guarda la marca. $logo: nombre de archivo nuevo, '' para quitarlo, null para dejar el que hay */
    public static function guardar(int $pid, bool $activa, string $nombre, string $color, string $color2, ?string $logo): bool
    {
        $nombre = mb_substr(trim(strip_tags($nombre)), 0, 150);
        $color = self::colorValido($color) ?? self::COLOR_DEFECTO;
        $color2 = self::colorValido($color2) ?? self::COLOR2_DEFECTO;
        $a = $activa ? 1 : 0;
        try {
            if ($logo === null) {
                $st = self::db()->prepare('UPDATE proyectos SET marca_activa = ?, marca_nombre = ?, marca_color = ?, marca_color2 = ? WHERE id = ?');
                $st->bind_param('isssi', $a, $nombre, $color, $color2, $pid);
            } else {
                $l = $logo === '' ? null : $logo;
                $st = self::db()->prepare('UPDATE proyectos SET marca_activa = ?, marca_nombre = ?, marca_color = ?, marca_color2 = ?, marca_logo = ? WHERE id = ?');
                $st->bind_param('issssi', $a, $nombre, $color, $color2, $l, $pid);
            }
            return $st->execute();
        } catch (\Throwable $e) {
            error_log('Marca: ' . $e->getMessage());
            return false;
        }
    }

    /** Blanco o casi negro según el fondo, para que el texto se lea */
    public static function contraste(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111111' : '#ffffff';
    }

    /** Banda de marca (logo + nombre + colores) como HTML con estilos en línea, apta para Dompdf y para la vista previa */
    public static function banda(array $m, string $proyectoNombre = ''): string
    {
        $logo = $m['logo'] !== '' ? MsrPdfExporter::imagenBase64($m['logo'], 'build/img/logos') : null;
        $h = '<div style="margin:0 0 10pt 0;border-bottom:3pt solid ' . $m['color'] . ';padding-bottom:5pt;"><table style="width:100%;border-collapse:collapse"><tr>'
            . '<td style="width:28%;border:0;padding:0;vertical-align:middle">' . ($logo ? '<img src="' . $logo . '" style="max-height:34pt;max-width:130pt">' : '') . '</td>'
            . '<td style="border:0;padding:0;text-align:center;vertical-align:middle;font-size:13pt;font-weight:bold;color:' . $m['color'] . '">' . htmlspecialchars($m['nombre']) . '</td>'
            . '<td style="width:28%;border:0;padding:0;text-align:right;vertical-align:middle;font-size:8pt;color:#555">' . htmlspecialchars($proyectoNombre) . '</td>'
            . '</tr></table></div>'
            . '<div style="height:2pt;background:' . $m['color2'] . ';margin:-8pt 0 10pt 0"></div>';
        return $h;
    }

    /** Estilo que lleva los colores de la marca a títulos y encabezados de tabla */
    public static function css(array $m): string
    {
        return '<style>h1,h2,h3{color:' . $m['color'] . ' !important}table th{background:' . $m['color'] . ' !important;color:' . self::contraste($m['color']) . ' !important}</style>';
    }

    /** Devuelve el HTML con la marca puesta; sin marca activa lo devuelve igual */
    public static function aplicar(string $html, Proyecto $p): string
    {
        $m = self::de($p);
        if (!$m) return $html;
        $estilo = self::css($m);
        $html = stripos($html, '</head>') !== false ? preg_replace('~</head>~i', $estilo . '</head>', $html, 1) : $estilo . $html;
        $banda = self::banda($m, (string)$p->nombre);
        return preg_match('~<body[^>]*>~i', $html) ? preg_replace('~(<body[^>]*>)~i', '$1' . $banda, $html, 1) : $html;
    }
}
