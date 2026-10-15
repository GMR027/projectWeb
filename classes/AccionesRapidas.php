<?php

namespace Classes;

/** Datos de la sección «Acciones rápidas» del sidebar (un proyecto) */
class AccionesRapidas
{
    private const LIMITE = 5;

    /**
     * @param int|null $limite elementos por sección (null = todos)
     * @return array<int, array{clave:string, titulo:string, icono:string, total:int, items:array, ver:string, abierta:bool}>
     */
    public static function secciones(int $pid, bool $gestiona, ?int $limite = self::LIMITE): array
    {
        global $db;
        $q = function (string $sql) use ($db): array {
            $r = $db->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        };
        $n = fn(string $sql): int => (int)(($db->query($sql)->fetch_row()[0]) ?? 0);
        $L = $limite ?? 1000;   // null = página completa: sin límite ni textos recortados
        $cut = fn(string $t, int $w) => $limite === null ? $t : mb_strimwidth($t, 0, $w, '…');
        $p = "?proyecto=$pid";
        $fecha = fn(?string $f) => $f ? formatear_fecha($f, 'd/m') : '';
        $secs = [];

        // --- Programa: hoy y semana ---
        $lunes = date('Y-m-d', strtotime('monday this week'));
        $domingo = date('Y-m-d', strtotime('sunday this week'));
        $base = "FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND a.status <> 'completada'";
        $hoyW = "a.fecha_inicio <= CURDATE() AND a.fecha_fin >= CURDATE()";
        $semW = "a.fecha_inicio <= '$domingo' AND a.fecha_fin >= '$lunes' AND NOT ($hoyW)";
        $mapAct = fn(array $f) => [
            'titulo' => $f['codigo'] . ' · ' . $f['nombre'],
            'sub' => $fecha($f['fecha_inicio']) . ' → ' . $fecha($f['fecha_fin']) . ' · ' . (int)$f['progreso'] . '%',
            'chip' => in_array($f['prioridad'], ['alta', 'critica'], true) ? $f['prioridad'] : '',
            'url' => "/programa/actividades/editar$p&id=" . (int)$f['id'],
        ];
        $cols = "a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.progreso, a.prioridad";
        $secs[] = ['clave' => 'hoy', 'titulo' => 'Actividades de hoy', 'icono' => 'fa-sun', 'abierta' => true,
            'total' => $n("SELECT COUNT(*) $base AND $hoyW"),
            'items' => array_map($mapAct, $q("SELECT $cols $base AND $hoyW ORDER BY a.fecha_fin ASC, a.id ASC LIMIT $L")),
            'ver' => "/programa$p"];
        $secs[] = ['clave' => 'semana', 'titulo' => 'Actividades de la semana', 'icono' => 'fa-calendar-week', 'abierta' => false,
            'total' => $n("SELECT COUNT(*) $base AND $semW"),
            'items' => array_map($mapAct, $q("SELECT $cols $base AND $semW ORDER BY a.fecha_inicio ASC, a.id ASC LIMIT $L")),
            'ver' => "/lookahead$p"];

        // --- Restricciones activas ---
        $baseR = "FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id
                  JOIN tipos_restriccion t ON t.id = r.tipo_id WHERE pa.proyecto_id = $pid AND r.estado = 'activa'";
        $secs[] = ['clave' => 'restr', 'titulo' => 'Restricciones activas', 'icono' => 'fa-triangle-exclamation', 'abierta' => false,
            'total' => $n("SELECT COUNT(*) $baseR"),
            'items' => array_map(fn($f) => [
                'titulo' => $f['tipo'] . ' · ' . $f['actividad'], 'wbs' => $f['wbs'],
                'sub' => ($f['descripcion'] ? $cut(trim($f['descripcion']), 40) . ' · ' : '') . ($f['fecha'] ? 'liberar ' . $fecha($f['fecha']) : 'sin fecha'),
                'chip' => $f['fecha'] && $f['fecha'] < date('Y-m-d') ? 'vencida' : '',
                'url' => "/programa/actividades/editar$p&id=" . (int)$f['actividad_id'], 'rid' => (int)$f['rid'],
            ], $q("SELECT r.id AS rid, a.codigo AS wbs, a.id AS actividad_id, t.nombre AS tipo, a.nombre AS actividad, r.descripcion, r.fecha $baseR ORDER BY (r.fecha IS NULL), r.fecha ASC, r.id ASC LIMIT $L")),
            'ver' => "/programa$p"];

        // --- Bitácora alta / crítica ---
        $baseB = "FROM bitacora WHERE proyecto_id = $pid AND importancia IN ('alta','critica')";
        $secs[] = ['clave' => 'bitacora', 'titulo' => 'Bitácora · alta y crítica', 'icono' => 'fa-book', 'abierta' => false,
            'total' => $n("SELECT COUNT(*) $baseB"),
            'items' => array_map(fn($f) => [
                'titulo' => $cut(trim(preg_replace('/\s+/', ' ', $f['descripcion'])), 60),
                'sub' => $fecha($f['fecha']) . ($f['departamento'] ? ' · ' . $f['departamento'] : ''),
                'chip' => $f['importancia'],
                'url' => "/bitacora$p",
            ], $q("SELECT fecha, departamento, descripcion, importancia $baseB ORDER BY fecha DESC, id DESC LIMIT $L")),
            'ver' => "/bitacora$p"];

        // --- Últimas minutas ---
        $secs[] = ['clave' => 'minutas', 'titulo' => 'Últimas minutas', 'icono' => 'fa-handshake', 'abierta' => false,
            'total' => $n("SELECT COUNT(*) FROM minutas WHERE proyecto_id = $pid"),
            'items' => array_map(fn($f) => [
                'titulo' => $f['nombre'], 'sub' => $fecha($f['fecha']),
                'chip' => in_array($f['importancia'], ['alta', 'critica'], true) ? $f['importancia'] : '',
                'url' => "/minutas/ver$p&id=" . (int)$f['id'],
            ], $q("SELECT id, fecha, nombre, importancia FROM minutas WHERE proyecto_id = $pid ORDER BY fecha DESC, id DESC LIMIT $L")),
            'ver' => "/minutas$p"];

        // --- Materiales con submittal sin aprobar: en revisión (P) o a resubmitar (RA). A y AN cuentan como aprobados. ---
        $etqSt = [1 => 'P', 2 => 'RA'];
        $txtSt = ['P' => 'En revisión', 'RA' => 'Resubmitar'];
        $baseS = "FROM msr_items i JOIN materiales m ON m.id = i.material_id LEFT JOIN submittals s ON s.id = i.submittal_id
                  WHERE i.proyecto_id = $pid AND i.msr_id IS NOT NULL AND i.status IN ('P','RA')";
        $secs[] = ['clave' => 'submittals', 'titulo' => 'Submittals por aprobar', 'icono' => 'fa-boxes-packing', 'abierta' => false,
            'total' => $n("SELECT COUNT(DISTINCT i.material_id) $baseS"),
            'items' => array_map(function ($f) use ($etqSt, $txtSt, $p) {
                $st = $etqSt[(int)$f['peor']] ?? 'P';
                return [
                    'titulo' => $f['nombre'],
                    'sub' => ($f['sub'] ? $f['sub'] . ' · ' : '') . $txtSt[$st],
                    'chip' => $st === 'RA' ? 'RA' : '',
                    'url' => "/msr/ver$p&id=" . (int)$f['msr_id'],
                    'sid' => (int)$f['sid'], 'estatus' => $f['estatus'],
                ];
            }, $q("SELECT m.nombre, MAX(FIELD(i.status, 'P', 'RA')) AS peor, MIN(s.numero_submittal) AS sub, MIN(i.msr_id) AS msr_id, MIN(i.submittal_id) AS sid, MIN(s.estatus) AS estatus
                   $baseS GROUP BY m.id, m.nombre ORDER BY peor DESC, MIN(s.fecha) ASC, m.nombre ASC LIMIT $L")),
            'ver' => "/msr$p"];

        if ($gestiona) {
            // --- Materiales con compra pendiente y/o sin entregar ---
            $baseM = "FROM materiales WHERE proyecto_id = $pid AND (compra = 'pendiente' OR fecha_entrega_real IS NULL)";
            $secs[] = ['clave' => 'materiales', 'titulo' => 'Materiales pendientes', 'icono' => 'fa-cubes', 'abierta' => false,
                'total' => $n("SELECT COUNT(*) $baseM"),
                'items' => array_map(function ($f) use ($fecha, $p) {
                    $e = [];
                    if ($f['compra'] === 'pendiente') $e[] = 'compra pendiente' . ($f['fecha_compra_prog'] ? ' (' . $fecha($f['fecha_compra_prog']) . ')' : '');
                    if (!$f['fecha_entrega_real']) $e[] = 'sin entregar' . ($f['fecha_entrega_prog'] ? ' (' . $fecha($f['fecha_entrega_prog']) . ')' : '');
                    return ['titulo' => $f['nombre'], 'sub' => implode(' · ', $e), 'chip' => '', 'url' => "/materiales/editar$p&id=" . (int)$f['id'],
                        'mid' => (int)$f['id'], 'compra' => $f['compra'], 'entregado' => (bool)$f['fecha_entrega_real']];
                }, $q("SELECT id, nombre, compra, fecha_compra_prog, fecha_entrega_prog, fecha_entrega_real $baseM
                       ORDER BY (compra = 'pendiente') DESC, (fecha_compra_prog IS NULL), fecha_compra_prog ASC, nombre ASC LIMIT $L")),
                'ver' => "/materiales$p&compra=pendiente"];
        }
        return $secs;
    }
}
