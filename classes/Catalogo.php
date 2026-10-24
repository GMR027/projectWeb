<?php

namespace Classes;

/**
 * Directorio del proyecto (Administración): una sola fuente para los departamentos y las categorías que usan varias herramientas.
 * Los valores se guardan como texto en cada herramienta; el catálogo (más lo que ya está en uso) alimenta sus listas, y renombrar
 * un valor aquí lo cambia en todas.
 */
class Catalogo
{
    use AccesoDb;

    /** Tablas que guardan un departamento como texto: tabla => herramienta */
    public const USO_DEPARTAMENTO = [
        'bitacora' => 'Bitácora', 'minutas' => 'Minutas', 'redactor_documentos' => 'Redactor', 'sheetflow_hojas' => 'SheetFlow',
        'submittals' => 'Submittals', 'personal' => 'Personal', 'evidencias' => 'Evidencias',
    ];

    /**
     * Categorías por ámbito: tabla del catálogo, cómo se cuentan los usos y a qué herramienta pertenecen.
     * `archivo` ya tenía su tabla (se enlaza por id); `punch` y `evidencias` usan catalogo_categorias y se guardan como texto.
     */
    public const AMBITOS = [
        'archivo' => ['titulo' => 'Archivo', 'tabla' => 'archivo_categorias'],
        'punch' => ['titulo' => 'Punch list', 'usos' => ['punch_items' => 'categoria']],
        'evidencias' => ['titulo' => 'Evidencias', 'usos' => ['evidencias' => 'categoria']],
    ];

    public static function limpiar(string $n): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $n)), 0, 100);
    }

    // ============================== DEPARTAMENTOS ==============================

    /** Catálogo + los que ya están en uso en alguna herramienta, ordenados */
    public static function departamentos(int $pid): array
    {
        $l = [];
        foreach (self::filas("SELECT nombre FROM bitacora_departamentos WHERE proyecto_id = $pid") as $f) $l[$f['nombre']] = true;
        foreach (array_keys(self::USO_DEPARTAMENTO) as $t) {
            foreach (self::filas("SELECT DISTINCT departamento AS n FROM $t WHERE proyecto_id = $pid AND departamento <> ''") as $f) $l[$f['n']] = true;
        }
        $n = array_keys($l);
        natcasesort($n);
        return array_values($n);
    }

    /** [nombre => ['en_catalogo' => bool, 'usos' => [herramienta => n], 'total' => n]] */
    public static function departamentosConUso(int $pid): array
    {
        $r = [];
        foreach (self::filas("SELECT nombre FROM bitacora_departamentos WHERE proyecto_id = $pid") as $f) $r[$f['nombre']] = ['en_catalogo' => true, 'usos' => [], 'total' => 0];
        foreach (self::USO_DEPARTAMENTO as $t => $herr) {
            foreach (self::filas("SELECT departamento AS n, COUNT(*) AS c FROM $t WHERE proyecto_id = $pid AND departamento <> '' GROUP BY departamento") as $f) {
                $r[$f['n']] ??= ['en_catalogo' => false, 'usos' => [], 'total' => 0];
                $r[$f['n']]['usos'][$herr] = (int)$f['c'];
                $r[$f['n']]['total'] += (int)$f['c'];
            }
        }
        uksort($r, 'strnatcasecmp');
        return $r;
    }

    /** Agrega el departamento al catálogo si no está (sin importar mayúsculas). Devuelve el nombre canónico */
    public static function agregarDepartamento(int $pid, string $nombre): string
    {
        $nombre = self::limpiar($nombre);
        if ($nombre === '') return '';
        $e = self::esc($nombre);
        $f = self::filas("SELECT nombre FROM bitacora_departamentos WHERE proyecto_id = $pid AND nombre = '$e' LIMIT 1");
        if ($f) return $f[0]['nombre'];
        HistorialCambios::escribir('bitacora_departamentos', 'creado', 0,
            fn() => self::db()->query("INSERT IGNORE INTO bitacora_departamentos (proyecto_id, nombre) VALUES ($pid, '$e')"));
        return $nombre;
    }

    /** id de una fila del catálogo que se localiza por nombre (0 si la tabla no se lleva en el historial) */
    private static function idPorNombre(string $tabla, int $pid, string $nombreEsc): int
    {
        if (!HistorialCambios::observa($tabla) || !preg_match('/^[a-z0-9_]+$/', $tabla)) return 0;
        $f = self::filas("SELECT id FROM $tabla WHERE proyecto_id = $pid AND nombre = '$nombreEsc' LIMIT 1");
        return (int)($f[0]['id'] ?? 0);
    }

    /** Cambia el nombre en el catálogo y en todas las herramientas; si el nuevo ya existe, se unen. Devuelve los registros cambiados */
    public static function renombrarDepartamento(int $pid, string $de, string $a): int
    {
        $a = self::limpiar($a);
        if ($a === '' || $a === $de) return 0;
        $d = self::esc($de);
        $n = self::esc($a);
        $cambios = 0;
        foreach (array_keys(self::USO_DEPARTAMENTO) as $t) {
            self::db()->query("UPDATE $t SET departamento = '$n' WHERE proyecto_id = $pid AND departamento = '$d'");
            $cambios += max(0, self::db()->affected_rows);
        }
        $existe = self::filas("SELECT id FROM bitacora_departamentos WHERE proyecto_id = $pid AND nombre = '$n' LIMIT 1");
        if ($existe) {
            HistorialCambios::escribir('bitacora_departamentos', 'eliminado', self::idPorNombre('bitacora_departamentos', $pid, $d),
                fn() => self::db()->query("DELETE FROM bitacora_departamentos WHERE proyecto_id = $pid AND nombre = '$d'"));
        } else {
            HistorialCambios::escribir('bitacora_departamentos', 'modificado', self::idPorNombre('bitacora_departamentos', $pid, $d),
                fn() => self::db()->query("UPDATE bitacora_departamentos SET nombre = '$n' WHERE proyecto_id = $pid AND nombre = '$d'"));
        }
        return $cambios;
    }

    /** Quita del catálogo; las herramientas que lo usan lo conservan como texto, por eso solo se permite sin uso */
    public static function eliminarDepartamento(int $pid, string $nombre): bool
    {
        $u = self::departamentosConUso($pid)[$nombre] ?? null;
        if ($u && $u['total'] > 0) return false;
        $d = self::esc($nombre);
        HistorialCambios::escribir('bitacora_departamentos', 'eliminado', self::idPorNombre('bitacora_departamentos', $pid, $d),
            fn() => self::db()->query("DELETE FROM bitacora_departamentos WHERE proyecto_id = $pid AND nombre = '$d'"));
        return true;
    }

    // ============================== CATEGORÍAS ==============================

    /** Nombres para las listas de una herramienta: catálogo + los que ya están en uso (+ sugerencias fijas, p. ej. las del Punch) */
    public static function categorias(int $pid, string $ambito, array $sugeridas = []): array
    {
        $a = self::AMBITOS[$ambito] ?? null;
        if (!$a) return [];
        $l = array_fill_keys($sugeridas, true);
        if (isset($a['tabla'])) {
            foreach (self::filas("SELECT nombre FROM {$a['tabla']} WHERE proyecto_id = $pid") as $f) $l[$f['nombre']] = true;
        } else {
            foreach (self::filas("SELECT nombre FROM catalogo_categorias WHERE proyecto_id = $pid AND ambito = '" . self::esc($ambito) . "'") as $f) $l[$f['nombre']] = true;
            foreach ($a['usos'] as $t => $c) {
                foreach (self::filas("SELECT DISTINCT $c AS n FROM $t WHERE proyecto_id = $pid AND $c <> ''") as $f) $l[$f['n']] = true;
            }
        }
        $n = array_keys($l);
        natcasesort($n);
        return array_values($n);
    }

    /** [nombre => usos] de un ámbito, con lo que está en el catálogo y lo que solo se usó como texto */
    public static function categoriasConUso(int $pid, string $ambito, array $sugeridas = []): array
    {
        $a = self::AMBITOS[$ambito] ?? null;
        if (!$a) return [];
        $r = [];
        if (isset($a['tabla'])) {
            foreach (self::filas("SELECT c.nombre, (SELECT COUNT(*) FROM archivos x WHERE x.categoria_id = c.id) AS n FROM {$a['tabla']} c WHERE c.proyecto_id = $pid") as $f) $r[$f['nombre']] = ['en_catalogo' => true, 'usos' => (int)$f['n']];
        } else {
            foreach (self::filas("SELECT nombre FROM catalogo_categorias WHERE proyecto_id = $pid AND ambito = '" . self::esc($ambito) . "'") as $f) $r[$f['nombre']] = ['en_catalogo' => true, 'usos' => 0];
            foreach ($sugeridas as $s) $r[$s] ??= ['en_catalogo' => false, 'usos' => 0, 'sugerida' => true];
            foreach ($a['usos'] as $t => $c) {
                foreach (self::filas("SELECT $c AS n, COUNT(*) AS k FROM $t WHERE proyecto_id = $pid AND $c <> '' GROUP BY $c") as $f) {
                    $r[$f['n']] ??= ['en_catalogo' => false, 'usos' => 0];
                    $r[$f['n']]['usos'] += (int)$f['k'];
                }
            }
        }
        uksort($r, 'strnatcasecmp');
        return $r;
    }

    public static function agregarCategoria(int $pid, string $ambito, string $nombre): string
    {
        $a = self::AMBITOS[$ambito] ?? null;
        $nombre = self::limpiar($nombre);
        if (!$a || $nombre === '') return '';
        $e = self::esc($nombre);
        if (isset($a['tabla'])) {
            $f = self::filas("SELECT nombre FROM {$a['tabla']} WHERE proyecto_id = $pid AND LOWER(nombre) = LOWER('$e') LIMIT 1");
            if ($f) return $f[0]['nombre'];
            HistorialCambios::escribir($a['tabla'], 'creado', 0, fn() => self::db()->query("INSERT INTO {$a['tabla']} (proyecto_id, nombre) VALUES ($pid, '$e')"));
        } else {
            HistorialCambios::escribir('catalogo_categorias', 'creado', 0, fn() => self::db()->query("INSERT IGNORE INTO catalogo_categorias (proyecto_id, ambito, nombre) VALUES ($pid, '" . self::esc($ambito) . "', '$e')"));
        }
        return $nombre;
    }

    public static function renombrarCategoria(int $pid, string $ambito, string $de, string $a): int
    {
        $def = self::AMBITOS[$ambito] ?? null;
        $a = self::limpiar($a);
        if (!$def || $a === '' || $a === $de) return 0;
        $d = self::esc($de);
        $n = self::esc($a);
        $cambios = 0;
        if (isset($def['tabla'])) {
            HistorialCambios::escribir($def['tabla'], 'modificado', self::idPorNombre($def['tabla'], $pid, $d),
                fn() => self::db()->query("UPDATE {$def['tabla']} SET nombre = '$n' WHERE proyecto_id = $pid AND nombre = '$d'"));
            return max(0, self::db()->affected_rows);
        }
        foreach ($def['usos'] as $t => $c) {
            self::db()->query("UPDATE $t SET $c = '$n' WHERE proyecto_id = $pid AND $c = '$d'");
            $cambios += max(0, self::db()->affected_rows);
        }
        $amb = self::esc($ambito);
        if (self::filas("SELECT id FROM catalogo_categorias WHERE proyecto_id = $pid AND ambito = '$amb' AND nombre = '$n' LIMIT 1")) {
            HistorialCambios::escribir('catalogo_categorias', 'eliminado', self::idPorNombre('catalogo_categorias', $pid, $d),
                fn() => self::db()->query("DELETE FROM catalogo_categorias WHERE proyecto_id = $pid AND ambito = '$amb' AND nombre = '$d'"));
        } else {
            HistorialCambios::escribir('catalogo_categorias', 'modificado', self::idPorNombre('catalogo_categorias', $pid, $d),
                fn() => self::db()->query("UPDATE catalogo_categorias SET nombre = '$n' WHERE proyecto_id = $pid AND ambito = '$amb' AND nombre = '$d'"));
        }
        return $cambios;
    }

    /** Elimina la categoría; si está en uso solo se borra con `$liberar` (los registros que la usan quedan sin categoría; en `archivo` no aplica) */
    public static function eliminarCategoria(int $pid, string $ambito, string $nombre, bool $liberar = false): bool
    {
        $def = self::AMBITOS[$ambito] ?? null;
        if (!$def) return false;
        $e = self::esc($nombre);
        if ((self::categoriasConUso($pid, $ambito)[$nombre]['usos'] ?? 0) > 0) {
            if (!$liberar || isset($def['tabla'])) return false;
            foreach ($def['usos'] as $t => $c) self::db()->query("UPDATE $t SET $c = '' WHERE proyecto_id = $pid AND $c = '$e'");
        }
        if (isset($def['tabla'])) {
            HistorialCambios::escribir($def['tabla'], 'eliminado', self::idPorNombre($def['tabla'], $pid, $e),
                fn() => self::db()->query("DELETE FROM {$def['tabla']} WHERE proyecto_id = $pid AND nombre = '$e'"));
        } else {
            HistorialCambios::escribir('catalogo_categorias', 'eliminado', 0,
                fn() => self::db()->query("DELETE FROM catalogo_categorias WHERE proyecto_id = $pid AND ambito = '" . self::esc($ambito) . "' AND nombre = '$e'"));
        }
        return true;
    }
}
