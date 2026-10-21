<?php

namespace Classes;

/**
 * Respaldo de UN proyecto (a diferencia de Respaldo, que guarda todo el sistema). Cada .zip lleva las filas del proyecto
 * (datos/<tabla>.jsonl, las tablas se descubren por llaves foráneas desde `proyectos`) y los archivos que esas filas usan
 * (archivos/<ruta>). Se guardan en storage/respaldos/proyectos/<id>. Al restaurar se sustituyen las filas del proyecto y solo
 * se recuperan los archivos que faltan: nunca se borra nada del disco.
 */
class RespaldoProyecto
{
    use AccesoDb;

    private const RAIZ = __DIR__ . '/..';
    private const LOTE = 100;
    public const HORA_AUTO = '23:30';
    /** Cuántos se conservan de cada tipo */
    private const CONSERVAR = ['auto' => 1, 'semanal' => 1, 'mensual' => 1, 'manual' => 5, 'previo' => 2];
    /** Tablas con llave al proyecto que no se respaldan: registros generados, permisos o bitácoras que no deben retroceder */
    private const EXCLUIDAS = ['exportaciones', 'proyecto_accesos', 'auditoria_cambios', 'programador_respaldos'];
    private const COMPRIMIDOS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'mp3', 'm4a', 'ogg', 'webm', 'docx', 'xlsx', 'pptx'];
    private const EXT_ARCHIVO = 'jpe?g|png|gif|webp|svg|pdf|docx?|xlsx?|pptx?|odt|csv|mp3|wav|m4a|webm|ogg|zip|dwg|dxf|txt';
    private const MAX_ARCHIVO = 100 * 1048576;

    public static function dir(int $pid): string
    {
        $d = Respaldo::dir() . '/proyectos/' . $pid;
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    // ---------- esquema ----------

    /** ['tabla' => ['col', 'padre', 'pcol']] en orden padres→hijos; 'proyectos' va primero */
    private static function esquema(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $bd = self::db()->query('SELECT DATABASE()')->fetch_row()[0];
        $fks = self::filas("SELECT table_name t, column_name c, referenced_table_name rt, referenced_column_name rc FROM information_schema.key_column_usage WHERE table_schema = '" . self::esc($bd) . "' AND referenced_table_name IS NOT NULL");
        $orden = ['proyectos' => null];
        do {
            $nuevo = false;
            foreach ($fks as $f) {
                $t = $f['t'];
                if (isset($orden[$t]) || in_array($t, self::EXCLUIDAS, true) || !array_key_exists($f['rt'], $orden)) continue;
                $orden[$t] = ['col' => $f['c'], 'padre' => $f['rt'], 'pcol' => $f['rc']];
                $nuevo = true;
            }
        } while ($nuevo);
        return $cache = $orden;
    }

    /** Condición WHERE que limita una tabla a las filas del proyecto */
    private static function alcance(array $esq, string $t, int $pid): string
    {
        if ($t === 'proyectos') return "`id` = $pid";
        $e = $esq[$t];
        if ($e['padre'] === 'proyectos') return "`{$e['col']}` = $pid";
        return "`{$e['col']}` IN (SELECT `{$e['pcol']}` FROM `{$e['padre']}` WHERE " . self::alcance($esq, $e['padre'], $pid) . ')';
    }

    // ---------- configuración y listado ----------

    private static function archivoConfig(int $pid): string
    {
        return self::dir($pid) . '/config.json';
    }

    /** ¿Tiene respaldo automático nocturno? (activo por omisión) */
    public static function automatico(int $pid): bool
    {
        $c = json_decode((string)@file_get_contents(self::archivoConfig($pid)), true);
        return (bool)(is_array($c) ? ($c['auto'] ?? true) : true);
    }

    public static function guardarAutomatico(int $pid, bool $auto): void
    {
        file_put_contents(self::archivoConfig($pid), json_encode(['auto' => $auto]));
    }

    /** Nombre de archivo seguro y existente o null */
    public static function ruta(int $pid, string $archivo): ?string
    {
        $archivo = basename($archivo);
        if (!preg_match('/^(auto|semanal|mensual|manual|previo)_[0-9_\-]+\.zip$/', $archivo)) return null;
        $r = self::dir($pid) . '/' . $archivo;
        return is_file($r) ? $r : null;
    }

    /** [['archivo', 'tipo', 'fecha', 'bytes'], ...] del más reciente al más antiguo */
    public static function listar(int $pid): array
    {
        $out = [];
        foreach (glob(self::dir($pid) . '/*.zip') ?: [] as $f) {
            $n = basename($f);
            if (!preg_match('/^(auto|semanal|mensual|manual|previo)_/', $n, $m)) continue;
            $out[] = ['archivo' => $n, 'tipo' => $m[1], 'fecha' => filemtime($f), 'bytes' => filesize($f)];
        }
        usort($out, fn($a, $b) => $b['fecha'] <=> $a['fecha']);
        return $out;
    }

    /** El respaldo más reciente que se puede restaurar «desde el anterior» (los de seguridad previos a restaurar no cuentan) */
    public static function anterior(int $pid): ?array
    {
        foreach (self::listar($pid) as $r) if (in_array($r['tipo'], ['auto', 'manual'], true)) return $r;
        return null;
    }

    public static function eliminar(int $pid, string $archivo): bool
    {
        $r = self::ruta($pid, $archivo);
        return $r !== null && @unlink($r);
    }

    // ---------- crear ----------

    /** Crea un respaldo del proyecto y devuelve el nombre del archivo */
    public static function crear(int $pid, string $tipo = 'manual'): string
    {
        @set_time_limit(0);
        if (!isset(self::CONSERVAR[$tipo])) $tipo = 'manual';
        $esq = self::esquema();
        $dir = self::dir($pid);
        $base = $tipo . '_' . date('Y-m-d_His');
        $ruta = "$dir/$base.zip";
        $zip = new \ZipArchive();
        if ($zip->open($ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('No se pudo crear el archivo de respaldo.');

        $temporales = [];
        $tokens = [];
        $conteo = [];
        $patron = '/[A-Za-z0-9_\-]+\.(?:' . self::EXT_ARCHIVO . ')\b/i';
        try {
            foreach (array_keys($esq) as $t) {
                $tmp = "$dir/.$base.$t.jsonl";
                $temporales[] = $tmp;
                $h = fopen($tmp, 'w');
                $n = 0;
                $res = self::db()->query("SELECT * FROM `$t` WHERE " . self::alcance($esq, $t, $pid), MYSQLI_USE_RESULT);
                while ($fila = $res->fetch_assoc()) {
                    fwrite($h, json_encode($fila, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
                    $n++;
                    foreach ($fila as $v) {
                        if (is_string($v) && strlen($v) > 3 && str_contains($v, '.') && preg_match_all($patron, $v, $m)) {
                            foreach ($m[0] as $nombre) $tokens[strtolower($nombre)] = true;
                        }
                    }
                }
                $res->free();
                fclose($h);
                $conteo[$t] = $n;
                if ($n > 0) $zip->addFile($tmp, "datos/$t.jsonl");
            }

            $archivos = self::archivosDe($pid, $tokens);
            foreach ($archivos as $rel) {
                $abs = self::RAIZ . '/' . $rel;
                $zip->addFile($abs, "archivos/$rel");
                if (in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), self::COMPRIMIDOS, true)) $zip->setCompressionName("archivos/$rel", \ZipArchive::CM_STORE);
            }
            $zip->addFromString('manifest.json', json_encode([
                'app' => 'projectweb', 'tipo' => 'proyecto', 'version' => 1, 'proyecto_id' => $pid, 'fecha' => date('c'),
                'base_datos' => $_ENV['DB_NAME'] ?? 'project', 'filas' => $conteo, 'archivos' => count($archivos),
            ], JSON_PRETTY_PRINT));
            $ok = $zip->close();   // aquí se leen los temporales
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($ruta);
            throw $e;
        } finally {
            foreach ($temporales as $t) @unlink($t);
        }
        if (!$ok) {
            @unlink($ruta);
            throw new \RuntimeException('No se pudo escribir el respaldo (¿falta espacio en disco?).');
        }
        self::podar($pid, $tipo);
        return basename($ruta);
    }

    /**
     * Respaldo nocturno: el diario reemplaza al de ayer; además queda una copia semanal (si la última tiene 7 días o más)
     * y una mensual (si aún no hay una de este mes), para que el usuario tenga de dónde elegir
     */
    public static function crearAutomatico(int $pid): string
    {
        $f = self::crear($pid, 'auto');
        $origen = self::dir($pid) . "/$f";
        $ult = fn(string $tipo) => current(array_filter(self::listar($pid), fn($r) => $r['tipo'] === $tipo)) ?: null;
        $semanal = $ult('semanal');
        $mensual = $ult('mensual');
        $copias = [];
        if (!$semanal || time() - $semanal['fecha'] >= 7 * 86400 - 3600) $copias[] = 'semanal';
        if (!$mensual || date('Y-m', $mensual['fecha']) !== date('Y-m')) $copias[] = 'mensual';
        foreach ($copias as $tipo) {
            $dest = self::dir($pid) . '/' . $tipo . substr($f, 4);
            if (@copy($origen, $dest)) { @touch($dest, filemtime($origen)); self::podar($pid, $tipo); }
        }
        return $f;
    }

    /** Archivos (rutas relativas a la raíz) del proyecto: carpetas propias + los que mencionan sus filas */
    private static function archivosDe(int $pid, array $tokens): array
    {
        $rel = [];
        $agregar = function (string $abs) use (&$rel) {
            if (is_file($abs) && filesize($abs) <= self::MAX_ARCHIVO) $rel[substr(realpath($abs), strlen(realpath(self::RAIZ)) + 1)] = true;
        };
        foreach (["storage/archivos/$pid", "storage/fichas/$pid"] as $d) {
            if (!is_dir(self::RAIZ . "/$d")) continue;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::RAIZ . "/$d", \FilesystemIterator::SKIP_DOTS)) as $f) $agregar($f->getPathname());
        }
        if ($tokens) {
            foreach (['public/uploads', 'public/build/img/logos', 'public/build/img/caratulas'] as $d) {
                if (!is_dir(self::RAIZ . "/$d")) continue;
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::RAIZ . "/$d", \FilesystemIterator::SKIP_DOTS)) as $f) {
                    if ($f->isFile() && isset($tokens[strtolower($f->getFilename())])) $agregar($f->getPathname());
                }
            }
        }
        return array_keys($rel);
    }

    /** Deja solo los últimos N de cada tipo */
    private static function podar(int $pid, string $tipo): void
    {
        $mios = array_values(array_filter(self::listar($pid), fn($r) => $r['tipo'] === $tipo));
        foreach (array_slice($mios, self::CONSERVAR[$tipo]) as $viejo) self::eliminar($pid, $viejo['archivo']);
    }

    // ---------- restaurar ----------

    /** Sustituye los datos del proyecto por los del respaldo. Antes guarda un respaldo «previo» para poder deshacer */
    public static function restaurar(int $pid, string $archivo): void
    {
        @set_time_limit(0);
        $ruta = self::ruta($pid, $archivo);
        if (!$ruta) throw new \RuntimeException('El respaldo no existe.');
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) throw new \RuntimeException('El respaldo está dañado.');
        $m = json_decode((string)$zip->getFromName('manifest.json'), true);
        if (!is_array($m) || ($m['tipo'] ?? '') !== 'proyecto' || (int)($m['proyecto_id'] ?? 0) !== $pid) {
            $zip->close();
            throw new \RuntimeException('Este respaldo no pertenece a este proyecto.');
        }

        self::crear($pid, 'previo');

        $esq = self::esquema();
        $db = self::db();
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $db->begin_transaction();
        try {
            foreach (array_reverse(array_keys($esq)) as $t) {   // hijos primero: su alcance depende de que el padre aún exista
                if ($t === 'proyectos') continue;
                if (!$db->query("DELETE FROM `$t` WHERE " . self::alcance($esq, $t, $pid))) throw new \RuntimeException("No se pudo limpiar $t: " . $db->error);
            }
            foreach (array_keys($esq) as $t) {
                $s = $zip->getStream("datos/$t.jsonl");
                if (!$s) continue;
                $cols = array_column(self::filas("SHOW COLUMNS FROM `$t`"), 'Field');
                if ($t === 'proyectos') {
                    $fila = json_decode((string)fgets($s), true) ?: [];
                    $set = [];
                    foreach ($fila as $c => $v) if (in_array($c, $cols, true) && !in_array($c, ['id', 'usuario_id'], true)) $set[] = "`$c` = " . ($v === null ? 'NULL' : "'" . self::esc($v) . "'");
                    if ($set && !$db->query('UPDATE `proyectos` SET ' . implode(', ', $set) . " WHERE id = $pid")) throw new \RuntimeException('No se pudo restaurar el proyecto: ' . $db->error);
                } else {
                    $lote = [];
                    $campos = null;
                    $volcar = function () use (&$lote, &$campos, $db, $t) {
                        if (!$lote) return;
                        $sql = "INSERT INTO `$t` (`" . implode('`,`', $campos) . '`) VALUES ' . implode(',', $lote);
                        $lote = [];
                        if (!$db->query($sql)) throw new \RuntimeException("No se pudo restaurar $t: " . $db->error);
                    };
                    while (($linea = fgets($s)) !== false) {
                        $fila = json_decode($linea, true);
                        if (!is_array($fila)) continue;
                        $campos ??= array_values(array_filter(array_keys($fila), fn($c) => in_array($c, $cols, true)));
                        $lote[] = '(' . implode(',', array_map(fn($c) => ($fila[$c] ?? null) === null ? 'NULL' : "'" . self::esc($fila[$c]) . "'", $campos)) . ')';
                        if (count($lote) >= self::LOTE) $volcar();
                    }
                    $volcar();
                }
                fclose($s);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $zip->close();
            throw new \RuntimeException($e->getMessage());
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        self::recuperarArchivos($zip, $pid);
        $zip->close();
    }

    /** Devuelve al disco los archivos del respaldo que ya no están (nunca sobrescribe ni borra) */
    private static function recuperarArchivos(\ZipArchive $zip, int $pid): void
    {
        $permitidos = ['public/uploads/', 'public/build/img/logos/', 'public/build/img/caratulas/', "storage/archivos/$pid/", "storage/fichas/$pid/"];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (!str_starts_with($n, 'archivos/') || str_ends_with($n, '/')) continue;
            $rel = substr($n, 9);
            if (str_contains($rel, '..') || str_contains($rel, '\\')) continue;
            $ok = false;
            foreach ($permitidos as $p) if (str_starts_with($rel, $p)) $ok = true;
            $dest = self::RAIZ . '/' . $rel;
            if (!$ok || file_exists($dest)) continue;
            if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
            $s = $zip->getStream($n);
            $d = fopen($dest, 'w');
            stream_copy_to_stream($s, $d);
            fclose($s); fclose($d);
        }
    }

    // ---------- automático nocturno ----------

    private static function marca(): string
    {
        return Respaldo::dir() . '/proyectos/.auto';
    }

    /** Última vez que tocaba el respaldo nocturno (hoy 23:30, o ayer si aún no llega) */
    private static function ultimaHora(): int
    {
        $hoy = (int)strtotime(date('Y-m-d') . ' ' . self::HORA_AUTO . ':00');
        return time() >= $hoy ? $hoy : $hoy - 86400;
    }

    /** ¿Pasó la hora y aún no se hicieron los respaldos de esa noche? Una sola lectura de fecha: barata en cada petición */
    public static function pendiente(): bool
    {
        return (int)@filemtime(self::marca()) < self::ultimaHora();
    }

    /** Respaldo automático de todos los proyectos que lo tengan activo. null si no tocaba. Con candado para no duplicar */
    public static function ejecutarSiToca(bool $forzar = false): ?int
    {
        Respaldo::dir();
        @mkdir(dirname(self::marca()), 0775, true);
        $lock = fopen(dirname(self::marca()) . '/.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return null;
        try {
            if (!$forzar && !self::pendiente()) return null;
            $hechos = 0;
            foreach (self::filas('SELECT id FROM proyectos') as $p) {
                if (!self::automatico((int)$p['id'])) continue;
                try {
                    self::crearAutomatico((int)$p['id']);
                    $hechos++;
                } catch (\Throwable $e) {
                    error_log("Respaldo del proyecto {$p['id']}: " . $e->getMessage());
                }
            }
            touch(self::marca());
            return $hechos;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
