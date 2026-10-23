<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Model\Proyecto;

/** Redactor de reportes diarios: el sistema reúne lo ocurrido en el día y la IA redacta el comentario; al aprobar se crea el reporte diario */
class ReporteDiario extends Base
{
    public static function id(): string { return 'reporte_diario'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Redactor de reporte diario', 'grupo' => 'campo', 'tipo' => 'reporte_diario', 'aplicable' => true,
            'descripcion' => 'Reúne las actividades en ejecución, el avance del día, la bitácora, las entregas de materiales y las restricciones, y redacta el comentario del reporte. Tú lo revisas y, al aprobarlo, se crea el reporte diario.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'fecha', 'etiqueta' => 'Fecha del reporte', 'tipo' => 'fecha', 'defecto' => date('Y-m-d')], ['nombre' => 'area', 'etiqueta' => 'Área', 'tipo' => 'texto', 'defecto' => 'General', 'max' => 150]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Reporte diario ' . date('d/m/Y', strtotime($params['fecha'])) . ' · ' . ($params['area'] ?: 'General');
    }

    /** Un día sin avances registrados, bitácora, entregas, hitos ni fotos no tiene nada que reportar */
    public static function sinCambios(array $datos): bool
    {
        return !array_filter($datos['actividades'], fn($x) => $x['registro']) && !$datos['bitacora'] && !$datos['entregas'] && !$datos['hitos'] && !$datos['fotos'];
    }

    /** Fechas del periodo con algo que reportar (avance, bitácora, entrega de material, hito o foto); el resto son días sin cambios */
    public static function diasConCambios(int $pid, string $desde, string $hasta): array
    {
        $d = self::db()->real_escape_string($desde);
        $h = self::db()->real_escape_string($hasta);
        $sql = [
            "SELECT DISTINCT h.fecha f FROM actividad_avance h JOIN actividades a ON a.id = h.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND h.fecha BETWEEN '$d' AND '$h'",
            "SELECT DISTINCT fecha f FROM bitacora WHERE proyecto_id = $pid AND fecha BETWEEN '$d' AND '$h'",
            "SELECT DISTINCT fecha_entrega_real f FROM materiales WHERE proyecto_id = $pid AND fecha_entrega_real BETWEEN '$d' AND '$h'",
            "SELECT DISTINCT fecha f FROM evidencias WHERE proyecto_id = $pid AND fecha BETWEEN '$d' AND '$h'",
        ];
        $con = [];
        foreach ($sql as $q) foreach (self::filas($q) as $x) $con[$x['f']] = true;
        foreach (Dependencias::actividades($pid) as $a) if ((int)$a['hito'] && $a['fecha_inicio'] >= $desde && $a['fecha_inicio'] <= $hasta) $con[$a['fecha_inicio']] = true;
        return $con;
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $f = $params['fecha'];
        $esc = self::db()->real_escape_string($f);
        $acts = Dependencias::actividades($pid);
        $hoy = [];
        foreach (self::filas("SELECT h.actividad_id, h.progreso FROM actividad_avance h JOIN actividades a ON a.id = h.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND h.fecha = '$esc'") as $x) $hoy[(int)$x['actividad_id']] = (int)$x['progreso'];
        $previo = [];
        foreach (self::filas("SELECT h.actividad_id, h.progreso FROM actividad_avance h JOIN actividades a ON a.id = h.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND h.fecha < '$esc' ORDER BY h.fecha") as $x) $previo[(int)$x['actividad_id']] = (int)$x['progreso'];
        $filas = [];
        foreach ($acts as $id => $a) {
            $enCurso = !(int)$a['hito'] && $a['fecha_inicio'] <= $f && $a['fecha_fin'] >= $f && (int)$a['progreso'] < 100;
            if (!$enCurso && !isset($hoy[$id])) continue;
            $ant = $previo[$id] ?? 0;
            $act = $hoy[$id] ?? (int)$a['progreso'];
            $filas[] = ['id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'partida' => $a['partida_codigo'], 'previo' => $ant, 'hoy' => $act, 'cambio' => isset($hoy[$id]) ? $act - $ant : 0, 'registro' => isset($hoy[$id])];
        }
        usort($filas, fn($x, $y) => [(int)$y['registro'], $x['codigo']] <=> [(int)$x['registro'], $y['codigo']]);
        $ids = array_column($filas, 'id');
        $bitacora = self::filas("SELECT departamento, importancia, descripcion FROM bitacora WHERE proyecto_id = $pid AND fecha = '$esc' ORDER BY FIELD(importancia,'critica','alta','media','baja')");
        $entregas = self::filas("SELECT nombre FROM materiales WHERE proyecto_id = $pid AND fecha_entrega_real = '$esc'");
        $restr = $ids ? self::filas("SELECT r.actividad_id, r.descripcion, t.nombre tipo FROM restricciones r JOIN tipos_restriccion t ON t.id = r.tipo_id WHERE r.estado = 'activa' AND r.actividad_id IN (" . implode(',', array_map('intval', $ids)) . ')') : [];
        $hitos = array_values(array_filter($acts, fn($a) => (int)$a['hito'] && $a['fecha_inicio'] === $f));
        $fotos = (int)(self::filas("SELECT COUNT(*) n FROM evidencias WHERE proyecto_id = $pid AND fecha = '$esc'")[0]['n'] ?? 0);
        $ultimo = self::filas("SELECT personal_json FROM reportes_diarios WHERE proyecto_id = $pid AND fecha < '$esc' ORDER BY fecha DESC, id DESC LIMIT 1")[0]['personal_json'] ?? '[]';
        $datos = ['fecha' => $f, 'area' => $params['area'], 'actividades' => $filas, 'bitacora' => $bitacora, 'entregas' => array_column($entregas, 'nombre'), 'restricciones' => $restr, 'hitos' => array_map(fn($a) => $a['codigo'] . ' ' . $a['nombre'], $hitos),
            'fotos' => $fotos, 'personal' => array_map('intval', json_decode($ultimo, true) ?: [])];

        $lin = [];
        foreach (array_slice($filas, 0, 25) as $x) $lin[] = "{$x['codigo']} {$x['nombre']} (avance {$x['previo']}% → {$x['hoy']}%" . ($x['registro'] ? '' : ', sin avance registrado hoy') . ')';
        $prompt = "Eres el residente de una obra de construcción y redactas el reporte diario. Redacta en español, en tono técnico y breve, SOLO con los datos de abajo; no inventes cifras, personas ni hechos.\n\n"
            . "Fecha: " . self::fmt($f) . " · Proyecto: {$p->nombre} · Área: {$params['area']}\n\n## ACTIVIDADES EN EJECUCIÓN\n" . ($lin ? implode("\n", $lin) : '(ninguna)')
            . "\n\n## BITÁCORA DEL DÍA\n" . ($bitacora ? implode("\n", array_map(fn($b) => "[{$b['importancia']}] {$b['departamento']}: " . self::corto($b['descripcion'], 220), array_slice($bitacora, 0, 8))) : '(sin entradas)')
            . "\n\n## MATERIALES ENTREGADOS HOY\n" . ($entregas ? implode("\n", array_map(fn($e) => self::corto($e['nombre'], 80), array_slice($entregas, 0, 10))) : '(ninguno)')
            . "\n\n## RESTRICCIONES ACTIVAS EN ESTAS ACTIVIDADES\n" . ($restr ? implode("\n", array_map(fn($r) => "{$r['tipo']}: " . self::corto($r['descripcion'], 150), array_slice($restr, 0, 8))) : '(ninguna)')
            . "\n\n## HITOS DEL DÍA\n" . ($hitos ? implode("\n", $datos['hitos']) : '(ninguno)')
            . "\n\nDevuelve SOLO un JSON: {\"comentarios\":\"1 a 3 párrafos con lo trabajado, los avances y los problemas del día\",\"pendientes\":[\"acciones o pendientes para el día siguiente\"]}";
        return ['datos' => $datos, 'lotes' => [['titulo' => 'Redactando el reporte del ' . self::fmt($f), 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $a = $datos['actividades'];
        $conAvance = array_filter($a, fn($x) => $x['registro']);
        $comentarios = Informes::limpiarTexto(self::dato($respuestas, 'comentarios') ?? '', 3000);
        $ia = $comentarios !== '';
        if (!$ia) {
            $partes = [];
            $partes[] = $a ? 'Se trabajó en ' . count($a) . ' actividad(es): ' . implode('; ', array_map(fn($x) => $x['codigo'] . ' ' . self::corto($x['nombre'], 60), array_slice($a, 0, 6))) . (count($a) > 6 ? ' y ' . (count($a) - 6) . ' más.' : '.') : 'No hay actividades programadas en ejecución para esta fecha.';
            if ($conAvance) $partes[] = 'Avances registrados: ' . implode('; ', array_map(fn($x) => "{$x['codigo']} de {$x['previo']}% a {$x['hoy']}%", array_slice($conAvance, 0, 6))) . '.';
            if ($datos['entregas']) $partes[] = 'Materiales entregados: ' . implode(', ', array_map(fn($n) => self::corto($n, 50), array_slice($datos['entregas'], 0, 5))) . '.';
            if ($datos['restricciones']) $partes[] = 'Restricciones activas: ' . count($datos['restricciones']) . '.';
            $comentarios = implode("\n\n", $partes);
        }
        $pendientes = self::textos(self::dato($respuestas, 'pendientes'));
        $secs = [
            Informes::kpis('Resumen del día', [['Actividades en ejecución', count($a)], ['Con avance registrado', count($conAvance)], ['Entradas de bitácora', count($datos['bitacora'])], ['Materiales entregados', count($datos['entregas'])], ['Fotos de evidencia', $datos['fotos']], ['Restricciones activas', count($datos['restricciones'])]]),
            Informes::texto('Comentarios del reporte', $comentarios),
            Informes::tabla('Actividades del día', ['Código', 'Actividad', 'Avance previo', 'Avance hoy', 'Cambio'], array_map(fn($x) => [$x['codigo'], self::corto($x['nombre'], 90), $x['previo'] . '%', $x['hoy'] . '%', $x['registro'] ? ($x['cambio'] >= 0 ? '+' : '') . $x['cambio'] . ' pts' : 'sin registro'], $a)),
        ];
        if ($pendientes) $secs[] = Informes::lista('Pendientes para el siguiente día', $pendientes);
        if ($datos['bitacora']) $secs[] = Informes::lista('Bitácora del día', array_map(fn($b) => "[{$b['importancia']}] {$b['departamento']}: " . self::corto($b['descripcion'], 200), $datos['bitacora']));
        if ($datos['entregas']) $secs[] = Informes::lista('Materiales entregados', $datos['entregas']);
        if ($datos['restricciones']) $secs[] = Informes::lista('Restricciones activas', array_map(fn($r) => "{$r['tipo']}: " . self::corto($r['descripcion'], 160), $datos['restricciones']));
        return ['secciones' => $secs, 'aplicar' => ['sin_cambios' => self::sinCambios($datos), 'tipo' => 'reporte_diario', 'fecha' => $datos['fecha'], 'area' => $datos['area'], 'comentarios' => $comentarios . ($pendientes ? "\n\nPendientes:\n- " . implode("\n- ", $pendientes) : ''),
            'actividades' => array_column($a, 'id'), 'personal' => $datos['personal']]];
    }
}
