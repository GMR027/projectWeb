<?php

namespace Classes;

use Model\Proyecto;

/**
 * Consultas predefinidas de solo lectura sobre un proyecto, para el chat «Pregunta a tu proyecto». La IA elige cuáles ejecutar y con qué
 * parámetros (nunca escribe consultas libres) y redacta la respuesta con los datos devueltos. Cada resultado es compacto: [titulo, columnas, filas, nota].
 */
class Consultas
{
    private const LIMITE = 25;

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function filas(string $sql): array
    {
        try {
            $r = self::db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function esc(string $s): string
    {
        return self::db()->real_escape_string(str_replace(['%', '_'], ['\\%', '\\_'], mb_substr(trim($s), 0, 80)));
    }

    private static function lim($v): int
    {
        return max(1, min(self::LIMITE, (int)($v ?: self::LIMITE)));
    }

    private static function corto(?string $t, int $n): string
    {
        $t = trim((string)preg_replace('/\s+/u', ' ', (string)$t));
        return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
    }

    private static function res(string $titulo, array $cols, array $filas, string $nota = ''): array
    {
        return ['titulo' => $titulo, 'columnas' => $cols, 'filas' => array_map(fn($f) => array_map(fn($c) => $c === null ? '' : (string)$c, array_values($f)), $filas), 'nota' => $nota];
    }

    public static function catalogo(): array
    {
        return [
            'resumen' => ['descripcion' => 'Resumen general del proyecto: fechas, avance, actividades completadas/atrasadas, restricciones, submittals y materiales pendientes.', 'parametros' => []],
            'actividades' => ['descripcion' => 'Lista de actividades. Filtros: estado (todas|atrasadas|en_curso|proximas|completadas|criticas|sin_iniciar), partida (texto), texto (palabra del nombre), dias (para proximas, por defecto 14), limite.', 'parametros' => ['estado', 'partida', 'texto', 'dias', 'limite']],
            'actividad' => ['descripcion' => 'Detalle de UNA actividad (por código como 3.2 o por palabras del nombre): fechas, avance, predecesoras, materiales, restricciones.', 'parametros' => ['codigo', 'texto']],
            'partidas' => ['descripcion' => 'Partidas con sus fechas, número de actividades y avance.', 'parametros' => []],
            'ruta_critica' => ['descripcion' => 'Actividades de la ruta crítica (holgura cero).', 'parametros' => ['limite']],
            'materiales' => ['descripcion' => 'Materiales. Filtros: filtro (todos|sin_comprar|sin_entrega|entrega_tarde|sin_submittal|comprados), texto, limite.', 'parametros' => ['filtro', 'texto', 'limite']],
            'submittals' => ['descripcion' => 'Submittals. Filtro: estatus (todos|pendientes|rechazados|aprobados), limite.', 'parametros' => ['estatus', 'limite']],
            'restricciones' => ['descripcion' => 'Restricciones activas de actividades, con su tipo y fecha. Filtro opcional: tipo (texto).', 'parametros' => ['tipo', 'limite']],
            'bitacora' => ['descripcion' => 'Entradas de bitácora recientes. Filtros: dias (por defecto 30), importancia (baja|media|alta|critica), texto.', 'parametros' => ['dias', 'importancia', 'texto', 'limite']],
            'reportes' => ['descripcion' => 'Reportes diarios recientes con sus comentarios.', 'parametros' => ['limite']],
            'obstaculos' => ['descripcion' => 'Obstáculos detectados: materiales que llegan tarde, submittals sin aprobar y restricciones activas, con severidad.', 'parametros' => ['limite']],
            'curva_s' => ['descripcion' => 'Avance programado contra avance real de las últimas semanas (Curva S).', 'parametros' => ['semanas']],
            'propuestas' => ['descripcion' => 'Análisis inteligente: pendientes de aceptar o rechazar (inconsistencias y análisis de materiales), por severidad.', 'parametros' => []],
            'historial' => ['descripcion' => 'Últimos cambios aplicados o revertidos por las herramientas de IA.', 'parametros' => ['limite']],
        ];
    }

    /** Ejecuta una consulta del catálogo. Lanza \InvalidArgumentException si no existe */
    public static function ejecutar(Proyecto $p, string $nombre, array $a = []): array
    {
        if (!isset(self::catalogo()[$nombre])) throw new \InvalidArgumentException('Consulta no válida');
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $L = self::lim($a['limite'] ?? 0);
        $acts = fn() => Dependencias::actividades($pid);
        switch ($nombre) {
            case 'resumen':
                $todas = $acts();
                $n = count($todas);
                $comp = count(array_filter($todas, fn($x) => (int)$x['progreso'] >= 100));
                $atr = count(array_filter($todas, fn($x) => !(int)$x['hito'] && (int)$x['progreso'] < 100 && $x['fecha_fin'] < $hoy));
                $curva = CurvaS::calcular($p);
                $per = null;
                foreach ($curva['periodos'] ?? [] as $x) if ($x['inicio'] <= $hoy) $per = $x;
                $dep = Dependencias::datos($p);
                $c = fn(string $sql) => (int)(self::filas($sql)[0]['n'] ?? 0);
                return self::res('Resumen de ' . $p->nombre, ['Dato', 'Valor'], [
                    ['Fechas del proyecto', $p->fecha_inicio . ' a ' . $p->fecha_fin], ['Hoy', $hoy], ['Actividades', "$n ($comp completadas, $atr atrasadas)"], ['En ruta crítica', $dep['resumen']['criticas'] ?? 0],
                    ['Avance programado / real (semana actual)', $per ? $per['plan'] . '% / ' . ($per['real'] ?? 's/d') . '%' : 's/d'], ['Restricciones activas', $c("SELECT COUNT(*) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND r.estado = 'activa'")],
                    ['Submittals en revisión', $c("SELECT COUNT(*) n FROM submittals WHERE proyecto_id = $pid AND estatus = 'enviado'")], ['Submittals rechazados', $c("SELECT COUNT(*) n FROM submittals WHERE proyecto_id = $pid AND estatus = 'rechazado'")],
                    ['Materiales sin entrega real', $c("SELECT COUNT(*) n FROM materiales WHERE proyecto_id = $pid AND fecha_entrega_real IS NULL")], ['Análisis inteligente: pendientes', $c("SELECT COUNT(*) n FROM inconsistencias WHERE proyecto_id = $pid AND estado = 'pendiente'") + $c("SELECT COUNT(*) n FROM analisis_materiales_hallazgos WHERE proyecto_id = $pid AND estado = 'pendiente'")],
                ]);
            case 'actividades':
                $estado = in_array($a['estado'] ?? '', ['todas', 'atrasadas', 'en_curso', 'proximas', 'completadas', 'criticas', 'sin_iniciar'], true) ? $a['estado'] : 'todas';
                $dias = max(1, min(90, (int)($a['dias'] ?? 14)));
                $lista = $acts();
                $crit = [];
                if ($estado === 'criticas') foreach (Dependencias::datos($p)['actividades'] as $x) if ($x['critica']) $crit[$x['id']] = true;
                $parte = Voz::sinAcentos((string)($a['partida'] ?? ''));
                $txt = Voz::palabras((string)($a['texto'] ?? ''));
                $f = array_filter($lista, function ($x) use ($estado, $hoy, $dias, $crit, $parte, $txt) {
                    if ($parte !== '' && !str_contains(Voz::sinAcentos($x['partida_codigo'] . ' ' . $x['partida']), $parte)) return false;
                    if ($txt && count(array_intersect($txt, Voz::palabras($x['nombre']))) < 1) return false;
                    return match ($estado) {
                        'atrasadas' => !(int)$x['hito'] && (int)$x['progreso'] < 100 && $x['fecha_fin'] < $hoy,
                        'en_curso' => (int)$x['progreso'] < 100 && $x['fecha_inicio'] <= $hoy && $x['fecha_fin'] >= $hoy,
                        'proximas' => (int)$x['progreso'] < 100 && $x['fecha_inicio'] > $hoy && $x['fecha_inicio'] <= date('Y-m-d', strtotime("+$dias days")),
                        'completadas' => (int)$x['progreso'] >= 100,
                        'sin_iniciar' => (int)$x['progreso'] === 0,
                        'criticas' => isset($crit[$x['id']]),
                        default => true,
                    };
                });
                usort($f, fn($x, $y) => [$estado === 'atrasadas' ? $x['fecha_fin'] : $x['fecha_inicio'], $x['codigo']] <=> [$estado === 'atrasadas' ? $y['fecha_fin'] : $y['fecha_inicio'], $y['codigo']]);
                return self::res("Actividades ($estado)", ['Código', 'Actividad', 'Partida', 'Inicio', 'Fin', 'Avance'], array_map(fn($x) => [$x['codigo'], self::corto($x['nombre'], 80), $x['partida_codigo'], $x['fecha_inicio'], $x['fecha_fin'], $x['progreso'] . '%'], array_slice($f, 0, $L)), count($f) > $L ? 'Se muestran ' . $L . ' de ' . count($f) : 'Total: ' . count($f));
            case 'actividad':
                $lista = $acts();
                [$id, $motivo] = Voz::resolverActividad($lista, (string)($a['codigo'] ?? '') ?: (string)($a['texto'] ?? ''));
                if (!$id) return self::res('Actividad', ['Resultado'], [[$motivo ?: 'no encontrada']]);
                $x = $lista[$id];
                $preds = self::filas("SELECT a.codigo, a.nombre, d.tipo, d.retraso FROM actividad_dependencias d JOIN actividades a ON a.id = d.predecesora_id WHERE d.sucesora_id = $id");
                $mats = self::filas("SELECT m.nombre, m.compra, m.fecha_entrega_prog, m.fecha_entrega_real FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id WHERE am.actividad_id = $id");
                $rest = self::filas("SELECT t.nombre tipo, r.descripcion, r.estado FROM restricciones r JOIN tipos_restriccion t ON t.id = r.tipo_id WHERE r.actividad_id = $id");
                $filas = [['Actividad', $x['codigo'] . ' ' . $x['nombre']], ['Partida', $x['partida_codigo'] . ' ' . $x['partida']], ['Fechas', $x['fecha_inicio'] . ' a ' . $x['fecha_fin'] . ' (' . $x['duracion_dias'] . ' d)'], ['Avance', $x['progreso'] . '% · ' . $x['status']], ['Fecha fija', (int)$x['fecha_fija'] ? 'sí' : 'no']];
                foreach ($preds as $d) $filas[] = ['Predecesora', "{$d['codigo']} {$d['nombre']} ({$d['tipo']}" . ($d['retraso'] ? ", {$d['retraso']} d" : '') . ')'];
                foreach ($mats as $m) $filas[] = ['Material', self::corto($m['nombre'], 60) . ' · compra ' . $m['compra'] . ' · entrega ' . ($m['fecha_entrega_real'] ? 'real ' . $m['fecha_entrega_real'] : ($m['fecha_entrega_prog'] ?: 'sin fecha'))];
                foreach ($rest as $r) $filas[] = ['Restricción ' . $r['estado'], $r['tipo'] . ': ' . self::corto($r['descripcion'], 120)];
                return self::res('Detalle de la actividad ' . $x['codigo'], ['Dato', 'Valor'], $filas);
            case 'partidas':
                $por = [];
                foreach ($acts() as $x) { $por[$x['partida_id']]['n'] = ($por[$x['partida_id']]['n'] ?? 0) + 1; $por[$x['partida_id']]['p'] = ($por[$x['partida_id']]['p'] ?? 0) + (int)$x['progreso']; $por[$x['partida_id']]['d'] = $x['partida_codigo'] . ' ' . $x['partida']; $por[$x['partida_id']]['i'] = min($por[$x['partida_id']]['i'] ?? '9999', $x['fecha_inicio']); $por[$x['partida_id']]['f'] = max($por[$x['partida_id']]['f'] ?? '0', $x['fecha_fin']); }
                return self::res('Partidas', ['Partida', 'Actividades', 'Avance medio', 'Inicio', 'Fin'], array_map(fn($x) => [self::corto($x['d'], 70), $x['n'], round($x['p'] / $x['n']) . '%', $x['i'], $x['f']], array_slice(array_values($por), 0, 40)));
            case 'ruta_critica':
                $c = array_filter(Dependencias::datos($p)['actividades'], fn($x) => $x['critica']);
                usort($c, fn($x, $y) => strcmp((string)$x['es'], (string)$y['es']));
                return self::res('Ruta crítica', ['Código', 'Actividad', 'Inicio', 'Fin', 'Avance'], array_map(fn($x) => [$x['codigo'], self::corto($x['nombre'], 80), $x['es'] ?? $x['inicio'], $x['ef'] ?? $x['fin'], $x['progreso'] . '%'], array_slice($c, 0, $L)), count($c) ? '' : 'No hay ruta crítica calculada (revisa que las dependencias estén en modo informativo o automático).');
            case 'materiales':
                $filtro = in_array($a['filtro'] ?? '', ['todos', 'sin_comprar', 'sin_entrega', 'entrega_tarde', 'sin_submittal', 'comprados'], true) ? $a['filtro'] : 'todos';
                $w = ["m.proyecto_id = $pid"];
                if ($filtro === 'sin_comprar') $w[] = "m.compra = 'pendiente'";
                if ($filtro === 'comprados') $w[] = "m.compra = 'comprado'";
                if ($filtro === 'sin_entrega') $w[] = 'm.fecha_entrega_real IS NULL';
                if ($filtro === 'entrega_tarde') $w[] = "m.fecha_entrega_real IS NULL AND m.fecha_entrega_prog IS NOT NULL AND EXISTS (SELECT 1 FROM actividad_materiales am JOIN actividades a ON a.id = am.actividad_id WHERE am.material_id = m.id AND a.fecha_inicio < m.fecha_entrega_prog)";
                if ($filtro === 'sin_submittal') $w[] = 'NOT EXISTS (SELECT 1 FROM submittal_materiales sm WHERE sm.material_id = m.id)';
                if (($a['texto'] ?? '') !== '') $w[] = "m.nombre LIKE '%" . self::esc((string)$a['texto']) . "%'";
                $r = self::filas('SELECT m.nombre, m.compra, m.fecha_compra_prog, m.fecha_entrega_prog, m.fecha_entrega_real FROM materiales m WHERE ' . implode(' AND ', $w) . ' ORDER BY m.fecha_entrega_prog IS NULL, m.fecha_entrega_prog LIMIT ' . $L);
                $t = (int)(self::filas('SELECT COUNT(*) n FROM materiales m WHERE ' . implode(' AND ', $w))[0]['n'] ?? 0);
                return self::res("Materiales ($filtro)", ['Material', 'Compra', 'Entrega programada', 'Entrega real'], array_map(fn($x) => [self::corto($x['nombre'], 80), $x['compra'], $x['fecha_entrega_prog'], $x['fecha_entrega_real']], $r), "Total: $t");
            case 'submittals':
                $e = ['pendientes' => "estatus = 'enviado'", 'rechazados' => "estatus = 'rechazado'", 'aprobados' => "estatus IN ('aprobado','aprobado_an')"][$a['estatus'] ?? ''] ?? '1=1';
                $r = self::filas("SELECT COALESCE(NULLIF(numero_submittal,''), CONCAT('#', id)) n, estatus, fecha, fecha_respuesta, departamento, DATEDIFF('$hoy', fecha) dias FROM submittals WHERE proyecto_id = $pid AND $e ORDER BY fecha LIMIT $L");
                return self::res('Submittals', ['Número', 'Estatus', 'Enviado', 'Respuesta', 'Departamento', 'Días desde el envío'], array_map(fn($x) => [$x['n'], $x['estatus'], $x['fecha'], $x['fecha_respuesta'], $x['departamento'], $x['dias']], $r));
            case 'restricciones':
                $w = "pa.proyecto_id = $pid AND r.estado = 'activa'" . (($a['tipo'] ?? '') !== '' ? " AND t.nombre LIKE '%" . self::esc((string)$a['tipo']) . "%'" : '');
                $r = self::filas("SELECT a.codigo, t.nombre tipo, r.descripcion, r.fecha FROM restricciones r JOIN tipos_restriccion t ON t.id = r.tipo_id JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE $w ORDER BY r.fecha IS NULL, r.fecha LIMIT $L");
                return self::res('Restricciones activas', ['Actividad', 'Tipo', 'Descripción', 'Fecha límite'], array_map(fn($x) => [$x['codigo'], $x['tipo'], self::corto($x['descripcion'], 120), $x['fecha']], $r));
            case 'bitacora':
                $dias = max(1, min(365, (int)($a['dias'] ?? 30)));
                $w = "proyecto_id = $pid AND fecha >= DATE_SUB('$hoy', INTERVAL $dias DAY)" . (in_array($a['importancia'] ?? '', ['baja', 'media', 'alta', 'critica'], true) ? " AND importancia = '{$a['importancia']}'" : '') . (($a['texto'] ?? '') !== '' ? " AND descripcion LIKE '%" . self::esc((string)$a['texto']) . "%'" : '');
                $r = self::filas("SELECT fecha, departamento, importancia, descripcion FROM bitacora WHERE $w ORDER BY fecha DESC, id DESC LIMIT $L");
                return self::res("Bitácora (últimos $dias días)", ['Fecha', 'Departamento', 'Importancia', 'Descripción'], array_map(fn($x) => [$x['fecha'], $x['departamento'], $x['importancia'], self::corto($x['descripcion'], 160)], $r));
            case 'reportes':
                $r = self::filas("SELECT fecha, area, comentarios FROM reportes_diarios WHERE proyecto_id = $pid ORDER BY fecha DESC, id DESC LIMIT " . min($L, 10));
                return self::res('Reportes diarios recientes', ['Fecha', 'Área', 'Comentarios'], array_map(fn($x) => [$x['fecha'], $x['area'], self::corto($x['comentarios'], 220)], $r));
            case 'obstaculos':
                $o = Programador::contexto($p, $hoy)['obstaculos'];
                $sv = ['critica' => 4, 'alta' => 3, 'media' => 2, 'baja' => 1];
                usort($o, fn($x, $y) => ($sv[$y['severidad']] ?? 0) <=> ($sv[$x['severidad']] ?? 0));
                return self::res('Obstáculos detectados', ['Severidad', 'Fuente', 'Descripción', 'Recomendación'], array_map(fn($x) => [$x['severidad'], $x['fuente'], self::corto($x['descripcion'], 140), self::corto($x['recomendacion'], 100)], array_slice($o, 0, $L)), 'Total: ' . count($o));
            case 'curva_s':
                $n = max(2, min(12, (int)($a['semanas'] ?? 6)));
                $per = array_values(array_filter(CurvaS::calcular($p)['periodos'] ?? [], fn($x) => $x['inicio'] <= $hoy));
                return self::res('Curva S (últimas semanas)', ['Semana', 'Programado', 'Real'], array_map(fn($x) => [$x['inicio'] . ' a ' . $x['fin'], $x['plan'] . '%', $x['real'] !== null ? $x['real'] . '%' : 's/d'], array_slice($per, -$n)));
            case 'propuestas':
                $r = self::filas("SELECT 'Inconsistencias' h, severidad, COUNT(*) n FROM inconsistencias WHERE proyecto_id = $pid AND estado = 'pendiente' GROUP BY severidad UNION ALL SELECT 'Análisis de materiales', severidad, COUNT(*) FROM analisis_materiales_hallazgos WHERE proyecto_id = $pid AND estado = 'pendiente' GROUP BY severidad");
                return self::res('Análisis inteligente: pendientes', ['Herramienta', 'Severidad', 'Cantidad'], array_map(fn($x) => [$x['h'], $x['severidad'], $x['n']], $r), 'Se aceptan o rechazan en Análisis inteligente.');
            case 'historial':
                $r = self::filas("SELECT creado, usuario, herramienta, evento, resumen FROM auditoria_cambios WHERE proyecto_id = $pid ORDER BY id DESC LIMIT $L");
                return self::res('Historial de cambios de IA', ['Fecha', 'Usuario', 'Herramienta', 'Evento', 'Resumen'], array_map(fn($x) => [$x['creado'], $x['usuario'], $x['herramienta'], $x['evento'], self::corto($x['resumen'], 120)], $r));
        }
        return self::res('Sin datos', ['Resultado'], []);
    }

    /** Respaldo sin IA: de la pregunta, qué consultas ejecutar (palabras clave) */
    public static function rutear(string $pregunta): array
    {
        $q = Voz::normalizar($pregunta);
        $out = [];
        $add = function (string $n, array $p = []) use (&$out) { foreach ($out as $o) if ($o[0] === $n && $o[1] == $p) return; $out[] = [$n, $p]; };
        if (preg_match('/\b\d+(?:\.\d+)+\b/', $q, $m)) $add('actividad', ['codigo' => $m[0]]);
        if (preg_match('/atrasad|retras|vencid|fuera de fecha/', $q)) $add('actividades', ['estado' => 'atrasadas']);
        if (preg_match('/critic|ruta critica/', $q)) $add('ruta_critica');
        if (preg_match('/proxim|siguiente|esta semana|lookahead|que sigue|por empezar|iniciar/', $q)) $add('actividades', ['estado' => 'proximas']);
        if (preg_match('/en curso|ejecucion|trabajando|en proceso/', $q)) $add('actividades', ['estado' => 'en_curso']);
        if (preg_match('/material|entrega|compra|suministro/', $q)) $add('materiales', preg_match('/tarde|retras|despues/', $q) ? ['filtro' => 'entrega_tarde'] : (preg_match('/sin comprar|por comprar|pendiente/', $q) ? ['filtro' => 'sin_comprar'] : ['filtro' => 'sin_entrega']));
        if (preg_match('/submittal|aprobacion|aprobar/', $q)) $add('submittals', preg_match('/rechaz/', $q) ? ['estatus' => 'rechazados'] : ['estatus' => 'pendientes']);
        if (preg_match('/restric/', $q)) $add('restricciones');
        if (preg_match('/bitacora/', $q)) $add('bitacora');
        if (preg_match('/reporte/', $q)) $add('reportes');
        if (preg_match('/obstacul|riesgo|problema|bloque/', $q)) $add('obstaculos');
        if (preg_match('/curva|programado contra real|plan.*real/', $q)) $add('curva_s');
        if (preg_match('/partida/', $q)) $add('partidas');
        if (preg_match('/propuesta|inconsisten|pendientes de ia/', $q)) $add('propuestas');
        if (preg_match('/historial|cambios|auditor|quien cambio/', $q)) $add('historial');
        if (preg_match('/avance|resumen|estado|como va|cuanto llevamos|general/', $q) || !$out) array_unshift($out, ['resumen', []]);
        return array_slice($out, 0, 4);
    }

    /** Resultado en texto compacto para el prompt del modelo o para mostrar sin IA */
    public static function texto(array $r, int $max = 2200): string
    {
        $t = '### ' . $r['titulo'] . "\n" . implode(' | ', $r['columnas']) . "\n";
        foreach ($r['filas'] as $f) $t .= implode(' | ', $f) . "\n";
        if ($r['nota'] !== '') $t .= '(' . $r['nota'] . ")\n";
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
    }
}
