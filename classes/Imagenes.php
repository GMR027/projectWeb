<?php

namespace Classes;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * Procesamiento de imágenes subidas (materiales, símbolos, vPlan y Editor).
 *
 * El tipo se detecta por la firma del archivo (getimagesize) y no solo por mime_content_type:
 * así un JPG/PNG no se rechaza cuando fileinfo devuelve un valor raro. Si Intervention Image
 * no está instalado o falla, se usa GD directamente (lo que antes dejaba los JPG/PNG sin guardar
 * mientras el SVG sí funcionaba).
 */
class Imagenes
{
    public const CARPETA = __DIR__ . '/../public/uploads/materiales';
    /** Lado máximo de la miniatura. 640 px para que también sirva como símbolo en planos; las imágenes viejas siguen a 240 px. */
    public const ANCHO = 640;

    public const CARPETA_VPLAN = __DIR__ . '/../public/uploads/vplan';

    /** MIME normalizado de una imagen por su firma; null si no es JPG, PNG, GIF o WebP. */
    public static function tipoImagen(string $ruta): ?string
    {
        if (!is_file($ruta)) return null;
        $info = @getimagesize($ruta);
        if ($info && isset($info[2])) {
            $mapa = [
                IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png',
                IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp',
            ];
            if (isset($mapa[$info[2]])) return $mapa[$info[2]];
        }
        $mime = @mime_content_type($ruta) ?: '';
        $mime = ['image/jpg' => 'image/jpeg', 'image/pjpeg' => 'image/jpeg', 'image/x-png' => 'image/png'][$mime] ?? $mime;
        return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) ? $mime : null;
    }

    /**
     * Reescala y re-codifica una imagen (jpg o png). Devuelve ['bin' => datos, 'ext' => 'jpg'|'png'] o null.
     * Intervention primero; si no está disponible o falla, GD. Los errores quedan en el log.
     */
    private static function codificar(string $origen, ?int $maxLado = null, int $calidad = 85): ?array
    {
        $mime = self::tipoImagen($origen);
        if (!$mime) {
            error_log('Imagenes · tipo no admitido: ' . basename($origen));
            return null;
        }
        // WebP también va a PNG para conservar la transparencia (muchos símbolos la usan).
        $formato = in_array($mime, ['image/png', 'image/gif', 'image/webp'], true) ? 'png' : 'jpg';

        // 1) Intervention Image (comportamiento histórico)
        try {
            if (class_exists(ImageManager::class) && class_exists(Driver::class)) {
                $img = (new ImageManager(new Driver()))->read($origen);
                if ($maxLado && $maxLado > 0) $img->scaleDown(width: $maxLado, height: $maxLado);
                $codificada = $formato === 'png' ? $img->toPng() : $img->toJpeg($calidad);
                $bin = (string)$codificada;
                if ($bin !== '') return ['bin' => $bin, 'ext' => $formato];
            }
        } catch (\Throwable $e) {
            error_log('Imagenes · Intervention: ' . $e->getMessage());
        }

        // 2) Respaldo nativo con GD: no depende de composer ni de Intervention
        if (!function_exists('imagecreatefromstring')) {
            error_log('Imagenes · GD no está disponible');
            return null;
        }
        $crudo = @file_get_contents($origen);
        if ($crudo === false || $crudo === '') return null;
        $im = @imagecreatefromstring($crudo);
        if (!$im) {
            error_log('Imagenes · no se pudo decodificar con GD: ' . basename($origen));
            return null;
        }
        $w = imagesx($im); $h = imagesy($im);
        if ($maxLado && $maxLado > 0 && max($w, $h) > $maxLado) {
            $k = $maxLado / max($w, $h);
            $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
            $nueva = imagecreatetruecolor($nw, $nh);
            if ($formato === 'png') {   // conservar la transparencia
                imagealphablending($nueva, false);
                imagesavealpha($nueva, true);
                $fondo = imagecolorallocatealpha($nueva, 0, 0, 0, 127);
                imagefill($nueva, 0, 0, $fondo);
            }
            imagecopyresampled($nueva, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($im);
            $im = $nueva;
        }
        ob_start();
        $ok = $formato === 'png' ? imagepng($im) : imagejpeg($im, null, $calidad);
        $bin = (string)ob_get_clean();
        imagedestroy($im);
        return ($ok && $bin !== '') ? ['bin' => $bin, 'ext' => $formato] : null;
    }

    /** Escribe el binario con un nombre nuevo; deja el error en el log si no puede. */
    private static function guardarBinario(string $dir, string $bin, string $ext): ?string
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('Imagenes · no se pudo crear la carpeta ' . $dir);
            return null;
        }
        if (!is_writable($dir)) {
            error_log('Imagenes · la carpeta no es escribible: ' . $dir);
            return null;
        }
        $nombre = bin2hex(random_bytes(16)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $nombre, $bin) === false) {
            error_log('Imagenes · no se pudo escribir ' . $dir . '/' . $nombre);
            return null;
        }
        return $nombre;
    }

    /** Genera miniatura desde un archivo y devuelve el nombre guardado */
    public static function miniatura(string $origen): ?string
    {
        $r = self::codificar($origen, self::ANCHO, 80);
        return $r ? self::guardarBinario(self::CARPETA, $r['bin'], $r['ext']) : null;
    }

    /** Guarda una imagen de plano/simbología reducida a $maxAncho px de ancho (si es mayor) */
    public static function guardarGrande(string $origen, int $maxAncho = 3840): ?string
    {
        $r = self::codificar($origen, $maxAncho, 88);
        return $r ? self::guardarBinario(self::CARPETA_VPLAN, $r['bin'], $r['ext']) : null;
    }

    /** Copia un archivo de vPlan con otro nombre (para que cada lámina sea dueña del suyo); null si no existe */
    public static function copiarVplan(?string $nombre): ?string
    {
        $ruta = $nombre ? self::CARPETA_VPLAN . '/' . basename($nombre) : null;
        if (!$ruta || !is_file($ruta)) return null;
        $nuevo = bin2hex(random_bytes(16)) . '.' . pathinfo($ruta, PATHINFO_EXTENSION);
        return @copy($ruta, self::CARPETA_VPLAN . '/' . $nuevo) ? $nuevo : null;
    }

    public static function eliminarVplan(?string $nombre): void
    {
        if ($nombre) @unlink(self::CARPETA_VPLAN . '/' . basename($nombre));
    }

    /** data URI de una imagen de vPlan reducida (para PDF); null si no existe */
    public static function vplanBase64(?string $nombre, int $maxAncho = 2000): ?string
    {
        $ruta = $nombre ? self::CARPETA_VPLAN . '/' . basename($nombre) : null;
        if (!$ruta || !is_file($ruta)) return null;
        $r = self::codificar($ruta, $maxAncho, 85);
        if (!$r) return null;
        return 'data:image/' . ($r['ext'] === 'png' ? 'png' : 'jpeg') . ';base64,' . base64_encode($r['bin']);
    }

    public static function eliminar(?string $nombre): void
    {
        if ($nombre) @unlink(self::CARPETA . '/' . basename($nombre));
    }

    public static function ruta(?string $nombre): ?string
    {
        $r = $nombre ? self::CARPETA . '/' . basename($nombre) : null;
        return $r && is_file($r) ? $r : null;
    }

    /** Guarda una foto reducida en public/uploads/{carpeta}; devuelve el nombre o null si no es una imagen válida */
    public static function guardarEn(string $carpeta, string $origen, int $maxLado = 1600, int $calidad = 85): ?string
    {
        $dir = __DIR__ . '/../public/uploads/' . preg_replace('/[^a-z_]/', '', $carpeta);
        $r = self::codificar($origen, $maxLado, $calidad);
        return $r ? self::guardarBinario($dir, $r['bin'], $r['ext']) : null;
    }

    public static function eliminarEn(string $carpeta, ?string $nombre): void
    {
        if ($nombre) @unlink(__DIR__ . '/../public/uploads/' . preg_replace('/[^a-z_]/', '', $carpeta) . '/' . basename($nombre));
    }
}
