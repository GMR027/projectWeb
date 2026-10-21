<?php

namespace Classes;

use Model\Proyecto;

/** Reglas comunes de los editores de marcas (vPlan «Editar marcas» y Editor de gráficos): validar lo que llega del navegador y subir imágenes */
class MarcasLienzo
{
    public const MAX_JSON = 3_000_000;
    public const MAX_MARCAS = 2000;

    /**
     * Marcas ({shapes: [...]}) ya decodificadas o null si algo no es válido.
     * $srcValido: expresión regular que debe cumplir el src de cada imagen (solo archivos que subió el propio editor).
     */
    public static function leer(string $json, string $srcValido, string|false|null $png, int $maxPng): ?array
    {
        $datos = json_decode($json, true);
        foreach (($datos['shapes'] ?? []) as $sh) {
            if (($sh['type'] ?? '') === 'image' && !preg_match($srcValido, (string)($sh['src'] ?? ''))) $datos = null;
        }
        if (!is_array($datos) || strlen($json) > self::MAX_JSON || !isset($datos['shapes']) || count($datos['shapes']) > self::MAX_MARCAS || !$png || strlen($png) > $maxPng) return null;
        return $datos;
    }

    /** Binario de un data URL de imagen. $tipos = alternativas permitidas (p. ej. 'png|jpeg'); false si no cumple */
    public static function binarioDeDataUrl(string $dataUrl, string $tipos = 'png'): string|false
    {
        return preg_match('#^data:image/(' . $tipos . ');base64,#', $dataUrl) ? base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true) : false;
    }

    /** Guarda el binario como imagen del vPlan (lado mayor $lado) y devuelve su nombre o null */
    public static function guardarBinario(string $bin, int $lado): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mar');
        file_put_contents($tmp, $bin);
        $nombre = Imagenes::guardarGrande($tmp, $lado);
        @unlink($tmp);
        return $nombre ?: null;
    }

    /** Imagen subida desde el editor (campo «imagen»): responde JSON con el src y termina */
    public static function subirImagen(Proyecto $proyecto): void
    {
        $f = $_FILES['imagen'] ?? null;
        $ok = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']) && $f['size'] <= 20 * 1024 * 1024
            && in_array(mime_content_type($f['tmp_name']), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
        if ($ok && !Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) json_respuesta(['success' => false, 'error' => Almacenamiento::mensajeLleno((int)$proyecto->id)], 422);
        $nombre = $ok ? Imagenes::guardarGrande($f['tmp_name'], 1600) : null;
        if (!$nombre) json_respuesta(['success' => false, 'error' => 'Imagen no válida (jpg, png, webp o gif de hasta 20 MB)'], 422);
        json_respuesta(['success' => true, 'src' => '/uploads/vplan/' . $nombre]);
    }
}
