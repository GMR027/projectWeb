<?php

namespace Classes;

/**
 * Respaldos completos del sistema: un .zip con la base de datos (database.sql, incluye usuarios y contraseñas)
 * y las carpetas del volumen de archivos. Se guardan en storage/respaldos.
 */
class Respaldo
{
    private const RAIZ = __DIR__ . '/..';
    private const DIR = __DIR__ . '/../storage/respaldos';
    private const FILAS_POR_INSERT = 200;

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    public static function dir(): string
    {
        if (!is_dir(self::DIR)) @mkdir(self::DIR, 0775, true);
        return self::DIR;
    }

    /** Nombre de archivo seguro (sin rutas) o null si no es un respaldo válido */
    public static function ruta(string $archivo): ?string
    {
        $archivo = basename($archivo);
        if (!preg_match('/^[A-Za-z0-9_\-]+\.zip$/', $archivo)) return null;
        $ruta = self::dir() . '/' . $archivo;
        return is_file($ruta) ? $ruta : null;
    }

    /** [['archivo', 'nombre', 'fecha' (timestamp), 'bytes'], ...] del más reciente al más antiguo */
    public static function listar(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.zip') ?: [] as $f) {
            $out[] = ['archivo' => basename($f), 'nombre' => basename($f, '.zip'), 'fecha' => filemtime($f), 'bytes' => filesize($f)];
        }
        usort($out, fn($a, $b) => $b['fecha'] <=> $a['fecha']);
        return $out;
    }

    public static function eliminar(string $archivo): bool
    {
        $ruta = self::ruta($archivo);
        return $ruta !== null && @unlink($ruta);
    }

    /** Crea un respaldo y devuelve el nombre del archivo */
    public static function crear(string $nombre = ''): string
    {
        @set_time_limit(0);
        $nombre = trim(preg_replace('/[^A-Za-z0-9_\-]+/', '_', $nombre), '_');
        $base = ($nombre !== '' ? $nombre : 'respaldo') . '_' . date('Y-m-d_His');
        $ruta = self::dir() . '/' . $base . '.zip';
        $sql = self::dir() . '/.' . $base . '.sql';

        self::volcarBd($sql);

        $zip = new \ZipArchive();
        if ($zip->open($ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($sql);
            throw new \RuntimeException('No se pudo crear el archivo de respaldo.');
        }
        $zip->addFile($sql, 'database.sql');
        $zip->addFromString('manifest.json', json_encode([
            'app' => 'projectweb', 'version' => 1, 'fecha' => date('c'),
            'base_datos' => $_ENV['DB_NAME'] ?? 'project', 'carpetas' => array_values(Volumen::CARPETAS),
        ], JSON_PRETTY_PRINT));
        foreach (Volumen::CARPETAS as $rel) {
            $abs = realpath(self::RAIZ . '/' . $rel);
            if (!$abs) continue;
            $zip->addEmptyDir('archivos/' . $rel);
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) $zip->addFile($f->getPathname(), 'archivos/' . $rel . '/' . substr($f->getPathname(), strlen($abs) + 1));
            }
        }
        $ok = $zip->close();
        @unlink($sql);   // addFile lee al cerrar; se borra después
        if (!$ok) {
            @unlink($ruta);
            throw new \RuntimeException('No se pudo escribir el respaldo (¿falta espacio en disco?).');
        }
        return basename($ruta);
    }

    /** Vuelca todas las tablas a un .sql (una sentencia por línea salvo CREATE TABLE) */
    private static function volcarBd(string $destino): void
    {
        $db = self::db();
        $h = fopen($destino, 'w');
        fwrite($h, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
        $tablas = [];
        $r = $db->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        while ($f = $r->fetch_row()) $tablas[] = $f[0];
        foreach ($tablas as $t) {
            $q = '`' . str_replace('`', '``', $t) . '`';
            $create = $db->query("SHOW CREATE TABLE $q")->fetch_row()[1];
            fwrite($h, "DROP TABLE IF EXISTS $q;\n$create;\n");
            $res = $db->query("SELECT * FROM $q", MYSQLI_USE_RESULT);
            $tipos = [];
            foreach ($res->fetch_fields() as $c) $tipos[] = in_array($c->type, [1, 2, 3, 4, 5, 8, 9, 16, 246], true);   // numéricos
            $lote = [];
            while ($fila = $res->fetch_row()) {
                $vals = [];
                foreach ($fila as $i => $v) {
                    $vals[] = $v === null ? 'NULL' : ($tipos[$i] ? $v : "'" . $db->real_escape_string($v) . "'");
                }
                $lote[] = '(' . implode(',', $vals) . ')';
                if (count($lote) >= self::FILAS_POR_INSERT) {
                    fwrite($h, "INSERT INTO $q VALUES " . implode(',', $lote) . ";\n");
                    $lote = [];
                }
            }
            $res->free();
            if ($lote) fwrite($h, "INSERT INTO $q VALUES " . implode(',', $lote) . ";\n");
        }
        fwrite($h, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($h);
    }

    /**
     * Restaura un respaldo (ruta a un .zip): reemplaza la base de datos y las carpetas de archivos.
     * Antes guarda un respaldo automático del estado actual. Lanza RuntimeException si el zip no es válido.
     */
    public static function restaurar(string $zipRuta): void
    {
        @set_time_limit(0);
        $zip = new \ZipArchive();
        if ($zip->open($zipRuta) !== true) throw new \RuntimeException('El archivo no es un .zip válido.');
        $manifest = json_decode((string)$zip->getFromName('manifest.json'), true);
        if (!is_array($manifest) || ($manifest['app'] ?? '') !== 'projectweb' || $zip->locateName('database.sql') === false) {
            $zip->close();
            throw new \RuntimeException('El archivo no es un respaldo de este sistema.');
        }
        $permitidas = array_values(Volumen::CARPETAS);
        $entradas = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (str_contains($n, '..') || str_starts_with($n, '/') || str_contains($n, '\\')) {
                $zip->close();
                throw new \RuntimeException('El respaldo contiene rutas no permitidas.');
            }
            if (str_starts_with($n, 'archivos/')) {
                $ok = false;
                foreach ($permitidas as $p) if (str_starts_with($n, "archivos/$p/") || $n === "archivos/$p/") $ok = true;
                if ($ok && !str_ends_with($n, '/')) $entradas[] = $n;
            }
        }

        // Red de seguridad: respaldo del estado actual antes de sobrescribir
        self::crear('antes_de_restaurar');

        // Base de datos
        $sql = self::dir() . '/.restaurar_' . bin2hex(random_bytes(4)) . '.sql';
        $in = $zip->getStream('database.sql');
        $out = fopen($sql, 'w');
        stream_copy_to_stream($in, $out);
        fclose($in); fclose($out);
        try {
            self::ejecutarSql($sql);
        } finally {
            @unlink($sql);
        }

        // Archivos: se vacía cada carpeta y se extrae lo del respaldo
        foreach ($permitidas as $rel) self::vaciar(self::RAIZ . '/' . $rel);
        foreach ($entradas as $n) {
            $dest = self::RAIZ . '/' . substr($n, strlen('archivos/'));
            if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
            $s = $zip->getStream($n);
            $d = fopen($dest, 'w');
            stream_copy_to_stream($s, $d);
            fclose($s); fclose($d);
        }
        $zip->close();
    }

    private static function ejecutarSql(string $archivo): void
    {
        $db = self::db();
        $h = fopen($archivo, 'r');
        $sentencia = '';
        while (($linea = fgets($h)) !== false) {
            $sentencia .= $linea;
            if (str_ends_with(rtrim($linea, "\r\n"), ';')) {
                if (!$db->query($sentencia)) {
                    $e = $db->error;
                    fclose($h);
                    throw new \RuntimeException('Error al restaurar la base de datos: ' . $e);
                }
                $sentencia = '';
            }
        }
        fclose($h);
    }

    private static function vaciar(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
}
