<?php

namespace Classes;

use Model\Proyecto;

/** Grabaciones de reuniones guardadas en el proyecto (audio fuera de public, con cuota y acceso solo del admin) */
class Grabaciones
{
    public const MAX_BYTES = 200 * 1024 * 1024;
    private const TIPOS = ['audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a'];

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    public static function carpeta(int $pid): string
    {
        return dirname(__DIR__) . "/storage/grabaciones/$pid";
    }

    /** Bytes de audio del proyecto (para la cuota) */
    public static function bytes(int $pid): int
    {
        try {
            return (int)(self::db()->query("SELECT COALESCE(SUM(tamano), 0) FROM grabaciones WHERE proyecto_id = $pid")->fetch_row()[0] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** @return array{0: ?int, 1: string} id y mensaje de error */
    public static function guardar(Proyecto $p, string $tmp, string $nombre, int $duracion, ?string $transcripcion): array
    {
        $pid = (int)$p->id;
        if (!is_file($tmp)) return [null, 'No llegó el audio'];
        $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $ext = self::TIPOS[$mime] ?? null;
        if (!$ext) return [null, 'Formato de audio no permitido (' . $mime . ')'];
        $tam = (int)filesize($tmp);
        if ($tam < 1 || $tam > self::MAX_BYTES) return [null, 'El audio está vacío o supera ' . Almacenamiento::formato(self::MAX_BYTES)];
        if (!Almacenamiento::cabe($pid, $tam)) return [null, Almacenamiento::mensajeLleno($pid)];
        $dir = self::carpeta($pid);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return [null, 'No se pudo crear la carpeta de grabaciones'];
        $archivo = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!(is_uploaded_file($tmp) ? move_uploaded_file($tmp, "$dir/$archivo") : copy($tmp, "$dir/$archivo"))) return [null, 'No se pudo guardar el audio'];
        $db = self::db();
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $db->query("INSERT INTO grabaciones (proyecto_id, nombre, archivo, mime, tamano, duracion_seg, transcripcion, creado_por) VALUES ($pid, '{$esc(mb_substr($nombre, 0, 200))}', '$archivo', '{$esc($mime)}', $tam, " . max(0, $duracion) . ', '
            . ($transcripcion === null || trim($transcripcion) === '' ? 'NULL' : "'" . $esc(mb_substr($transcripcion, 0, 200000)) . "'") . ", '" . $esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "')");
        return [(int)$db->insert_id, ''];
    }

    public static function listar(int $pid): array
    {
        try {
            $r = self::db()->query("SELECT id, nombre, mime, tamano, duracion_seg, creado, creado_por, (transcripcion IS NOT NULL AND transcripcion <> '') AS con_texto FROM grabaciones WHERE proyecto_id = $pid ORDER BY id DESC");
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function obtener(int $pid, int $id): ?array
    {
        try {
            $r = self::db()->query("SELECT * FROM grabaciones WHERE id = $id AND proyecto_id = $pid");
            return $r ? ($r->fetch_assoc() ?: null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function ruta(array $g): string
    {
        return self::carpeta((int)$g['proyecto_id']) . '/' . basename($g['archivo']);
    }

    public static function eliminar(int $pid, int $id): void
    {
        $g = self::obtener($pid, $id);
        if (!$g) return;
        @unlink(self::ruta($g));
        self::db()->query("DELETE FROM grabaciones WHERE id = $id AND proyecto_id = $pid");
    }

    public static function texto(int $pid, int $id): string
    {
        return (string)(self::obtener($pid, $id)['transcripcion'] ?? '');
    }

    public static function duracion(int $seg): string
    {
        return sprintf('%d:%02d', intdiv($seg, 60), $seg % 60);
    }
}
