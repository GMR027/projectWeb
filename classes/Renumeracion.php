<?php

namespace Classes;

use Model\Partida;

/**
 * Orden y numeración de partidas y actividades (sin IA). Las partidas se numeran «N.0» y sus actividades «N.M»,
 * como en el programa importado. Mover una actividad a otra partida está permitido: cambia su partida_id.
 */
class Renumeracion
{
    use AccesoDb;

    /** Estructura actual: [{id, codigo, nombre, actividades:[{id, codigo, nombre}]}] en orden */
    public static function estructura(int $pid): array
    {
        $db = self::db();
        $partidas = [];
        $r = $db->query("SELECT id, codigo, nombre FROM partidas WHERE proyecto_id = $pid ORDER BY orden ASC, id ASC");
        foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $p) $partidas[(int)$p['id']] = ['id' => (int)$p['id'], 'codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'actividades' => []];
        $r = $db->query("SELECT a.id, a.partida_id, a.codigo, a.nombre FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid ORDER BY a.orden ASC, a.id ASC");
        foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $a) $partidas[(int)$a['partida_id']]['actividades'][] = ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre']];
        return array_values($partidas);
    }

    /**
     * Códigos que resultarían de un orden: [partida_id => codigo, 'a'.actividad_id => codigo]
     * @param array $orden [{id, actividades:[ids]}]
     */
    public static function codigos(array $orden): array
    {
        $out = [];
        foreach (array_values($orden) as $i => $p) {
            $out[(int)$p['id']] = ($i + 1) . '.0';
            foreach (array_values($p['actividades'] ?? []) as $j => $aid) $out['a' . (int)$aid] = ($i + 1) . '.' . ($j + 1);
        }
        return $out;
    }

    /** Comprueba que el orden contiene exactamente las partidas y actividades del proyecto, cada una una vez. Devuelve el error o '' */
    public static function validar(int $pid, array $orden): string
    {
        $actual = self::estructura($pid);
        $pa = $aa = [];
        foreach ($actual as $p) { $pa[] = $p['id']; foreach ($p['actividades'] as $a) $aa[] = $a['id']; }
        $pn = $an = [];
        foreach ($orden as $p) {
            if (!is_array($p) || !isset($p['id'])) return 'Orden no válido';
            $pn[] = (int)$p['id'];
            foreach ((array)($p['actividades'] ?? []) as $aid) $an[] = (int)$aid;
        }
        sort($pa); sort($pn); sort($aa); sort($an);
        if ($pa !== $pn || $aa !== $an) return 'El programa cambió mientras ordenabas (otra persona agregó o quitó partidas o actividades). Recarga la página.';
        return '';
    }

    /**
     * Guarda el orden (y la partida de cada actividad) y, si se pide, renumera.
     * Los códigos son únicos por proyecto/partida, así que primero se pasan a un valor temporal.
     * @return array{partidas:int, actividades:int}
     */
    public static function aplicar(int $pid, array $orden, bool $renumerar, ?array $parciales = null, string $origen = ''): array
    {
        $db = self::db();
        $antes = self::instantanea($pid);
        // $parciales = [partida_id => codigo, 'a'.actividad_id => codigo]: solo esos códigos cambian (el resto conserva el suyo)
        $codigos = $parciales ?? ($renumerar ? self::codigos($orden) : []);
        $renumerar = $renumerar || ($parciales !== null && $parciales);
        $db->begin_transaction();
        try {
            if ($renumerar) {
                $ids = fn(string $pre) => implode(',', array_map('intval', array_map(fn($k) => ltrim((string)$k, 'a'), array_filter(array_keys($codigos), fn($k) => $pre === 'a' ? str_starts_with((string)$k, 'a') : !str_starts_with((string)$k, 'a')))));
                $pi = $parciales === null ? '' : $ids('p'); $ai = $parciales === null ? '' : $ids('a');
                if ($parciales === null || $pi !== '') $db->query("UPDATE partidas SET codigo = CONCAT('~', id) WHERE proyecto_id = $pid" . ($parciales === null ? '' : " AND id IN ($pi)"));
                if ($parciales === null || $ai !== '') $db->query("UPDATE actividades a JOIN partidas p ON p.id = a.partida_id SET a.codigo = CONCAT('~', a.id) WHERE p.proyecto_id = $pid" . ($parciales === null ? '' : " AND a.id IN ($ai)"));
            }
            $na = 0;
            foreach (array_values($orden) as $i => $p) {
                $id = (int)$p['id'];
                $set = 'orden = ' . ($i + 1) . ($renumerar && isset($codigos[$id]) ? ", codigo = '" . $db->escape_string($codigos[$id]) . "'" : '');
                $db->query("UPDATE partidas SET $set WHERE id = $id AND proyecto_id = $pid");
                foreach (array_values($p['actividades'] ?? []) as $j => $aid) {
                    $aid = (int)$aid;
                    $set = "partida_id = $id, orden = " . ($j + 1) . ($renumerar && isset($codigos['a' . $aid]) ? ", codigo = '" . $db->escape_string($codigos['a' . $aid]) . "'" : '');
                    $db->query("UPDATE actividades SET $set WHERE id = $aid");
                    $na++;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        foreach (Partida::whereAll('proyecto_id', $pid) as $p) $p->recalcularFechas();
        $cambios = self::contarCambios($antes, self::instantanea($pid));
        Auditoria::registrar($pid, 'ordenar', 'aplicado', 'ordenar_renumerar', ($origen !== '' ? "Ordenar y renumerar ($origen)" : 'Ordenar y renumerar') . ": {$cambios['codigos']} código(s) y {$cambios['movidas']} actividad(es) con otra posición o partida", $antes, ['renumerar' => $renumerar] + $cambios, null, '', ['proyecto', $pid, 'Orden del programa']);
        return ['partidas' => count($orden), 'actividades' => $na, 'cambios' => $cambios, 'deshacer_id' => (int)$db->insert_id];
    }

    /** Foto del orden, la partida y el código de cada partida/actividad (para deshacer): [{id, codigo, orden, actividades:[{id, codigo, orden}]}] */
    public static function instantanea(int $pid): array
    {
        $db = self::db();
        $partidas = [];
        $r = $db->query("SELECT id, codigo, orden FROM partidas WHERE proyecto_id = $pid ORDER BY orden ASC, id ASC");
        foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $p) $partidas[(int)$p['id']] = ['id' => (int)$p['id'], 'codigo' => $p['codigo'], 'orden' => (int)$p['orden'], 'actividades' => []];
        $r = $db->query("SELECT a.id, a.partida_id, a.codigo, a.orden FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid ORDER BY a.orden ASC, a.id ASC");
        foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $a) $partidas[(int)$a['partida_id']]['actividades'][] = ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'orden' => (int)$a['orden']];
        return array_values($partidas);
    }

    /** @return array{codigos:int, movidas:int} códigos distintos y actividades que cambiaron de partida o de posición */
    private static function contarCambios(array $antes, array $despues): array
    {
        $a = $d = [];
        foreach ($antes as $p) { $a['p' . $p['id']] = [$p['codigo'], $p['orden'], 0]; foreach ($p['actividades'] as $x) $a['a' . $x['id']] = [$x['codigo'], $x['orden'], $p['id']]; }
        foreach ($despues as $p) { $d['p' . $p['id']] = [$p['codigo'], $p['orden'], 0]; foreach ($p['actividades'] as $x) $d['a' . $x['id']] = [$x['codigo'], $x['orden'], $p['id']]; }
        $codigos = $movidas = 0;
        foreach ($d as $k => $v) {
            if (!isset($a[$k])) continue;
            if ($a[$k][0] !== $v[0]) $codigos++;
            if ($k[0] === 'a' && ($a[$k][1] !== $v[1] || $a[$k][2] !== $v[2])) $movidas++;
        }
        return ['codigos' => $codigos, 'movidas' => $movidas];
    }

    /** Última aplicación que aún se puede deshacer (no deshecha): {id, creado, usuario, resumen} o null */
    public static function ultimaDeshacible(int $pid): ?array
    {
        try {
            $r = self::db()->query("SELECT a.id, a.creado, a.usuario, a.resumen FROM auditoria_cambios a WHERE a.proyecto_id = $pid AND a.herramienta = 'ordenar' AND a.evento = 'aplicado'
                AND NOT EXISTS (SELECT 1 FROM auditoria_cambios r WHERE r.proyecto_id = $pid AND r.herramienta = 'ordenar' AND r.evento = 'revertido' AND r.referencia_id = a.id)
                ORDER BY a.id DESC LIMIT 1");
            return $r ? ($r->fetch_assoc() ?: null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Devuelve el orden y los códigos que había antes de la aplicación indicada. Solo si el programa tiene las mismas partidas y actividades. Devuelve el error o '' */
    public static function deshacer(int $pid, int $auditoriaId): string
    {
        $db = self::db();
        $u = self::ultimaDeshacible($pid);
        if (!$u || (int)$u['id'] !== $auditoriaId) return 'Solo se puede deshacer el último orden guardado, y ya no está disponible.';
        $r = $db->query("SELECT antes_json FROM auditoria_cambios WHERE id = $auditoriaId AND proyecto_id = $pid");
        $antes = json_decode((string)($r ? ($r->fetch_assoc()['antes_json'] ?? '') : ''), true);
        if (!is_array($antes)) return 'No hay copia del orden anterior.';
        $actual = self::instantanea($pid);
        $ids = fn(array $e) => array_merge(array_map(fn($p) => 'p' . $p['id'], $e), ...array_values(array_map(fn($p) => array_map(fn($x) => 'a' . $x['id'], $p['actividades']), $e)));
        $x = $ids($antes); $y = $ids($actual); sort($x); sort($y);
        if ($x !== $y) return 'Después de ordenar se agregaron o quitaron partidas o actividades; no se puede deshacer en automático.';
        $db->begin_transaction();
        try {
            $db->query("UPDATE partidas SET codigo = CONCAT('~', id) WHERE proyecto_id = $pid");
            $db->query("UPDATE actividades a JOIN partidas p ON p.id = a.partida_id SET a.codigo = CONCAT('~', a.id) WHERE p.proyecto_id = $pid");
            foreach ($antes as $p) {
                $id = (int)$p['id'];
                $db->query("UPDATE partidas SET orden = " . (int)$p['orden'] . ", codigo = '" . $db->escape_string($p['codigo']) . "' WHERE id = $id AND proyecto_id = $pid");
                foreach ($p['actividades'] as $a) $db->query("UPDATE actividades SET partida_id = $id, orden = " . (int)$a['orden'] . ", codigo = '" . $db->escape_string($a['codigo']) . "' WHERE id = " . (int)$a['id']);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return 'No se pudo deshacer: ' . $e->getMessage();
        }
        foreach (Partida::whereAll('proyecto_id', $pid) as $p) $p->recalcularFechas();
        Auditoria::registrar($pid, 'ordenar', 'revertido', 'ordenar_renumerar', 'Se deshizo el orden y la numeración: ' . $u['resumen'], null, null, $auditoriaId, '', ['proyecto', $pid, 'Orden del programa']);
        return '';
    }

    /**
     * Datos para decidir antes de mover algo: fechas, avance, vínculos y lugares donde otros módulos escriben su código como texto.
     * @return array<string, mixed> {titulo, lineas:[[etiqueta, valor]], menciones:[[modulo, cantidad]]}
     */
    public static function detalle(int $pid, string $tipo, int $id): ?array
    {
        $db = self::db();
        $uno = fn(string $sql) => ($r = $db->query($sql)) ? ($r->fetch_assoc() ?: null) : null;
        $n = fn(string $sql) => (int)(($uno($sql) ?? ['n' => 0])['n']);
        if ($tipo === 'partida') {
            $p = $uno("SELECT * FROM partidas WHERE id = $id AND proyecto_id = $pid");
            if (!$p) return null;
            $lineas = [['Actividades', (string)$n("SELECT COUNT(*) n FROM actividades WHERE partida_id = $id")], ['Fechas', ($p['fecha_inicio'] ?: '—') . ' → ' . ($p['fecha_fin'] ?: '—')],
                ['Láminas Vplan ligadas', (string)$n("SELECT COUNT(*) n FROM vplan_laminas WHERE partida_id = $id")]];
            if (trim((string)$p['descripcion']) !== '') $lineas[] = ['Descripción', mb_substr(trim($p['descripcion']), 0, 240)];
            return ['titulo' => $p['codigo'] . ' ' . $p['nombre'], 'lineas' => $lineas, 'menciones' => self::menciones($pid, $p['codigo'])];
        }
        $a = $uno("SELECT a.*, p.codigo pcodigo, p.nombre pnombre FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $id AND p.proyecto_id = $pid");
        if (!$a) return null;
        $lineas = [['Partida', $a['pcodigo'] . ' ' . $a['pnombre']], ['Fechas', $a['fecha_inicio'] . ' → ' . $a['fecha_fin'] . ' (' . $a['duracion_dias'] . ' d)'],
            ['Avance', $a['progreso'] . '% · ' . str_replace('_', ' ', $a['status'])],
            ['Dependencias', $n("SELECT COUNT(*) n FROM actividad_dependencias WHERE predecesora_id = $id") . ' sucesora(s) · ' . $n("SELECT COUNT(*) n FROM actividad_dependencias WHERE sucesora_id = $id") . ' predecesora(s)'],
            ['Materiales', (string)$n("SELECT COUNT(*) n FROM actividad_materiales WHERE actividad_id = $id")], ['Evidencias', (string)$n("SELECT COUNT(*) n FROM evidencias WHERE actividad_id = $id")]];
        if (trim((string)$a['descripcion']) !== '') $lineas[] = ['Descripción', mb_substr(trim($a['descripcion']), 0, 240)];
        return ['titulo' => $a['codigo'] . ' ' . $a['nombre'], 'lineas' => $lineas, 'menciones' => self::menciones($pid, $a['codigo'])];
    }

    /** Textos libres que contienen el código tal cual (bitácora, notas de láminas y de 360, minutas). Esos textos NO se reescriben al renumerar */
    private static function menciones(int $pid, string $codigo): array
    {
        $db = self::db();
        if ($codigo === '') return [];
        $re = "(^|[^0-9.])" . preg_quote($codigo, '/') . "([^0-9]|\\.?$)";
        $re = $db->escape_string($re);
        $cuenta = function (string $sql) use ($db) { try { $r = $db->query($sql); return $r ? (int)$r->fetch_assoc()['n'] : 0; } catch (\Throwable $e) { return 0; } };
        $out = [
            ['Bitácora', $cuenta("SELECT COUNT(*) n FROM bitacora WHERE proyecto_id = $pid AND descripcion REGEXP '$re'")],
            ['Notas de láminas', $cuenta("SELECT COUNT(*) n FROM vplan_notas v JOIN vplan_laminas l ON l.id = v.lamina_id WHERE l.proyecto_id = $pid AND v.descripcion REGEXP '$re'")],
            ['Minutas', $cuenta("SELECT COUNT(*) n FROM minutas WHERE proyecto_id = $pid AND CONCAT(COALESCE(temas_json,''), COALESCE(compromisos_json,'')) REGEXP '$re'")],
        ];
        return array_values(array_filter($out, fn($m) => $m[1] > 0));
    }

    // ---------- Propuesta por fechas (herramienta de IA del escritorio) ----------

    /** Partidas y actividades con fechas, en el orden actual */
    private static function conFechas(int $pid): array
    {
        $db = self::db();
        $partidas = [];
        $r = $db->query("SELECT id, codigo, nombre, orden, fecha_inicio, fecha_fin FROM partidas WHERE proyecto_id = $pid ORDER BY orden ASC, id ASC");
        foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $p) $partidas[(int)$p['id']] = ['id' => (int)$p['id'], 'codigo' => (string)$p['codigo'], 'nombre' => $p['nombre'], 'inicio' => $p['fecha_inicio'] ?: null, 'fin' => $p['fecha_fin'] ?: null, 'actividades' => []];
        $r = $db->query("SELECT a.id, a.partida_id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid ORDER BY a.orden ASC, a.id ASC");
        foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $a) $partidas[(int)$a['partida_id']]['actividades'][] = ['id' => (int)$a['id'], 'codigo' => (string)$a['codigo'], 'nombre' => $a['nombre'], 'inicio' => $a['fecha_inicio'] ?: null, 'fin' => $a['fecha_fin'] ?: null];
        return array_values($partidas);
    }

    /** Orden estable por inicio y fin; sin fecha al final. Devuelve los elementos reordenados */
    private static function porFecha(array $xs): array
    {
        $con = array_map(fn($x, $i) => [$x['inicio'] ?? '9999-99-99', $x['fin'] ?? '9999-99-99', $i, $x], $xs, array_keys($xs));
        usort($con, fn($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
        return array_map(fn($c) => $c[3], $con);
    }

    private static function numeroDe(string $codigo, int $porDefecto): int
    {
        return preg_match('/^(\d+)/', $codigo, $m) ? (int)$m[1] : $porDefecto;
    }

    /**
     * Propuesta determinista (sin IA): partidas por fecha de inicio y actividades de cada partida por fecha.
     * @return array{huella:string, items:array, avisos:array}
     */
    public static function proponerPorFechas(int $pid): array
    {
        $act = self::conFechas($pid);
        foreach ($act as &$p) {   // la fecha de la partida sale de sus actividades
            $ini = array_filter(array_column($p['actividades'], 'inicio')); $fin = array_filter(array_column($p['actividades'], 'fin'));
            if ($ini) $p['inicio'] = min($ini);
            if ($fin) $p['fin'] = max($fin);
        }
        unset($p);
        $huella = md5(json_encode($act));
        $items = []; $avisos = [];
        $nuevoP = self::porFecha($act);
        $filas = [];
        foreach ($nuevoP as $i => $p) {
            $de = array_search($p['id'], array_column($act, 'id'));
            $filas[] = ['id' => $p['id'], 'nombre' => $p['nombre'], 'codigo' => $p['codigo'], 'nuevo' => ($i + 1) . '.0', 'de' => $de + 1, 'a' => $i + 1, 'inicio' => $p['inicio'], 'fin' => $p['fin']];
        }
        $ordenP = array_column($filas, 'id') !== array_column($act, 'id');
        $codigoP = (bool)array_filter($filas, fn($f) => $f['codigo'] !== $f['nuevo']);
        if ($ordenP || $codigoP) $items[] = ['id' => 'p', 'tipo' => 'partidas', 'titulo' => 'Partidas', 'orden' => $ordenP, 'codigo' => $codigoP, 'filas' => $filas];
        foreach ($act as $pi => $p) {
            if (!$p['actividades']) continue;
            $nuevo = self::porFecha($p['actividades']);
            $num = self::numeroDe($p['codigo'], $pi + 1);
            $f = [];
            foreach ($nuevo as $j => $a) {
                $de = array_search($a['id'], array_column($p['actividades'], 'id'));
                $f[] = ['id' => $a['id'], 'nombre' => $a['nombre'], 'codigo' => $a['codigo'], 'nuevo' => $num . '.' . ($j + 1), 'de' => $de + 1, 'a' => $j + 1, 'inicio' => $a['inicio'], 'fin' => $a['fin']];
            }
            $o = array_column($f, 'id') !== array_column($p['actividades'], 'id');
            $c = (bool)array_filter($f, fn($x) => $x['codigo'] !== $x['nuevo']);
            if ($o || $c) $items[] = ['id' => 'a' . $p['id'], 'tipo' => 'actividades', 'titulo' => $p['codigo'] . ' ' . $p['nombre'], 'orden' => $o, 'codigo' => $c, 'filas' => $f];
        }
        $sinFecha = 0;
        foreach ($act as $p) foreach ($p['actividades'] as $a) if (!$a['inicio']) $sinFecha++;
        if ($sinFecha) $avisos[] = "$sinFecha actividad(es) sin fecha de inicio: quedan al final de su partida.";
        try {
            $r = self::db()->query("SELECT p.codigo pc, s.codigo sc FROM actividad_dependencias d JOIN actividades p ON p.id = d.predecesora_id JOIN actividades s ON s.id = d.sucesora_id JOIN partidas pp ON pp.id = p.partida_id
                WHERE pp.proyecto_id = $pid AND p.fecha_inicio > s.fecha_inicio LIMIT 5");
            foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $d) $avisos[] = "La predecesora {$d['pc']} empieza después que su sucesora {$d['sc']}: revisa las fechas antes de confiar en el orden.";
        } catch (\Throwable $e) { /* sin tabla de dependencias */ }
        return ['huella' => $huella, 'items' => $items, 'avisos' => $avisos];
    }

    /**
     * Aplica lo elegido de la propuesta por fechas.
     * @param array $sel [{id:'p'|'a<partida>', ordenar:bool, renumerar:bool}]
     * @return array|string error (texto) o el resultado de aplicar()
     */
    public static function aplicarPorFechas(int $pid, string $huella, array $sel)
    {
        $prop = self::proponerPorFechas($pid);
        if (!hash_equals($prop['huella'], $huella)) return 'El programa cambió desde que se generó la propuesta. Genera una nueva.';
        $elegidos = [];
        foreach ($sel as $s) if (is_array($s) && isset($s['id'])) $elegidos[(string)$s['id']] = ['o' => !empty($s['ordenar']), 'r' => !empty($s['renumerar'])];
        $validos = array_column($prop['items'], null, 'id');
        foreach ($elegidos as $id => $e) if (!isset($validos[$id]) || (!$e['o'] && !$e['r'])) unset($elegidos[$id]);
        if (!$elegidos) return 'No hay nada elegido para aplicar.';
        $act = self::conFechas($pid);
        foreach ($act as &$p) { $ini = array_filter(array_column($p['actividades'], 'inicio')); $fin = array_filter(array_column($p['actividades'], 'fin')); if ($ini) $p['inicio'] = min($ini); if ($fin) $p['fin'] = max($fin); }
        unset($p);
        $ordenarP = !empty($elegidos['p']['o']); $renumP = !empty($elegidos['p']['r']);
        $partidas = $ordenarP ? self::porFecha($act) : $act;
        $orden = []; $codigos = []; $finalP = []; $finalA = [];
        foreach ($partidas as $i => $p) {
            $acts = isset($elegidos['a' . $p['id']]['o']) && $elegidos['a' . $p['id']]['o'] ? self::porFecha($p['actividades']) : $p['actividades'];
            $orden[] = ['id' => $p['id'], 'actividades' => array_column($acts, 'id')];
            if ($renumP) $codigos[$p['id']] = ($i + 1) . '.0';
            $finalP[$p['id']] = $codigos[$p['id']] ?? $p['codigo'];
            $num = $renumP ? $i + 1 : self::numeroDe($p['codigo'], $i + 1);
            $renumA = $renumP || !empty($elegidos['a' . $p['id']]['r']);
            foreach ($acts as $j => $a) {
                if ($renumA) $codigos['a' . $a['id']] = $num . '.' . ($j + 1);
                $finalA[$p['id']][] = $codigos['a' . $a['id']] ?? $a['codigo'];
            }
        }
        if (count($finalP) !== count(array_unique($finalP))) return 'Dos partidas quedarían con el mismo código. Marca «Renumerar» en las partidas.';
        foreach ($finalA as $cods) if (count($cods) !== count(array_unique($cods))) return 'Dos actividades de una partida quedarían con el mismo código. Marca «Renumerar» en esa partida.';
        $err = self::validar($pid, $orden);
        if ($err !== '') return $err;
        return self::aplicar($pid, $orden, false, $codigos, 'por fechas, IA');
    }

    /** Siguiente código de partida libre («N.0», con N mayor que cualquier número usado) */
    public static function siguienteCodigoPartida(int $pid): string
    {
        $max = 0;
        foreach (Partida::whereAll('proyecto_id', $pid) as $p) if (preg_match('/^(\d+)/', (string)$p->codigo, $m)) $max = max($max, (int)$m[1]);
        return ($max + 1) . '.0';
    }
}
