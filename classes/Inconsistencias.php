<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;

/**
 * Inconsistencias de proyecto: reglas del sistema (sin IA) + revisión semántica de la IA. Cada hallazgo trae una solución propuesta
 * de una lista cerrada de acciones; solo se aplica cuando el admin la acepta, y casi todas se pueden revertir.
 */
class Inconsistencias
{
    use AccesoDb;

    public const CATEGORIAS = ['fechas' => 'Fechas', 'avance' => 'Avance', 'calendario' => 'Calendario', 'dependencias' => 'Dependencias', 'materiales' => 'Materiales',
        'restricciones' => 'Restricciones', 'submittals' => 'Submittals', 'reportes' => 'Reportes y bitácora', 'duplicados' => 'Duplicados', 'estructura' => 'Estructura', 'nomenclatura' => 'Nomenclatura', 'duraciones' => 'Duraciones',
        'clima' => 'Clima', 'voz' => 'Comandos de voz', 'avance_plano' => 'Avance en planos', 'otro' => 'Otro'];
    public const SEVERIDADES = ['baja' => 1, 'media' => 2, 'alta' => 3, 'critica' => 4];
    /** Acciones que una solución puede ejecutar: nombre => parámetros que exige */
    public const ACCIONES = [
        'nota' => [], 'ajustar_fechas' => ['actividad_id', 'inicio', 'fin'], 'sincronizar_status' => ['actividad_id'], 'ajustar_progreso' => ['actividad_id', 'progreso'],
        'recalcular_partida' => ['partida_id'], 'marcar_comprado' => ['material_id'], 'resolver_restriccion' => ['restriccion_id'], 'quitar_dependencia' => ['pred', 'suc'],
        'ajustar_duracion' => ['actividad_id', 'dur'], 'renombrar_actividad' => ['actividad_id', 'nombre'], 'editar_descripcion' => ['actividad_id', 'descripcion'], 'agregar_dia_no_habil' => ['fecha', 'motivo'], 'agregar_dependencia' => ['pred', 'suc', 'tipo', 'retraso'],
    ];
    public const PAQUETE_MAX = 22;

    private static function fecha($v): ?string
    {
        if (!is_string($v)) return null;
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }

    private static function hallazgo(string $regla, string $cat, string $sev, string $titulo, string $desc, array $acts, string $texto, string $accion = 'nota', array $params = []): array
    {
        return ['huella' => sha1('s:' . $regla), 'categoria' => $cat, 'severidad' => $sev, 'origen' => 'sistema', 'titulo' => self::corto($titulo, 200), 'descripcion' => $desc,
            'actividades' => array_values($acts), 'solucion' => ['texto' => $texto, 'accion' => $accion, 'params' => $params]];
    }

    // ---------- fase 1: reglas del sistema ----------

    public static function detectarSistema(Proyecto $p, ?string $hoy = null): array
    {
        $pid = (int)$p->id;
        $hoy ??= date('Y-m-d');
        $cal = new Calendario($pid);
        $motor = new RutaCritica($cal);
        $out = [];
        $acts = [];
        foreach (self::filas("SELECT a.*, pa.codigo AS pcodigo, pa.nombre AS pnombre, pa.fecha_inicio AS p_ini, pa.fecha_fin AS p_fin FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid ORDER BY pa.orden, a.orden") as $a) $acts[(int)$a['id']] = $a;
        $nom = fn($a) => $a['codigo'] . ' ' . self::corto($a['nombre'], 60);

        foreach ($acts as $id => $a) {
            $prog = (int)$a['progreso'];
            $hito = (int)$a['hito'];
            $fija = (int)$a['fecha_fija'];
            $dur = (int)$a['duracion_dias'];
            // fin antes que inicio
            if ($a['fecha_fin'] < $a['fecha_inicio']) {
                $fin = $cal->calcularFechaFin($a['fecha_inicio'], max(1, $dur));
                $out[] = self::hallazgo("fin_antes_inicio:$id:{$a['fecha_inicio']}:{$a['fecha_fin']}", 'fechas', 'alta', 'Fin anterior al inicio: ' . $nom($a), "«{$a['nombre']}» empieza el {$a['fecha_inicio']} pero termina el {$a['fecha_fin']}.",
                    [$id], "Mantener el inicio y recalcular el fin con su duración ($dur d): $fin.", 'ajustar_fechas', ['actividad_id' => $id, 'inicio' => $a['fecha_inicio'], 'fin' => $fin]);
                continue;   // el resto de reglas de fechas no tiene sentido con fechas invertidas
            }
            // estatus que no corresponde al avance
            $esperado = $prog <= 0 ? 'no_iniciada' : ($prog >= 100 ? 'completada' : 'en_proceso');
            if ($a['status'] !== $esperado) {
                $out[] = self::hallazgo("status:$id:{$a['status']}:$prog", 'avance', 'media', 'Estatus no corresponde al avance: ' . $nom($a), "Avance {$prog}% pero el estatus es «{$a['status']}» (debería ser «{$esperado}»).",
                    [$id], "Sincronizar el estatus a «{$esperado}» según el avance.", 'sincronizar_status', ['actividad_id' => $id]);
            }
            // hito con duración
            if ($hito && $a['fecha_inicio'] !== $a['fecha_fin']) {
                $out[] = self::hallazgo("hito:$id:{$a['fecha_inicio']}:{$a['fecha_fin']}", 'fechas', 'baja', 'Hito con duración: ' . $nom($a), "Un hito debe ocurrir en un solo día; este va del {$a['fecha_inicio']} al {$a['fecha_fin']}.",
                    [$id], "Poner el fin igual al inicio ({$a['fecha_inicio']}).", 'ajustar_fechas', ['actividad_id' => $id, 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_inicio']]);
            }
            // fuera del rango del proyecto
            if (($p->fecha_inicio && $a['fecha_inicio'] < $p->fecha_inicio) || ($p->fecha_fin && $a['fecha_fin'] > $p->fecha_fin)) {
                $out[] = self::hallazgo("fuera_proyecto:$id:{$a['fecha_inicio']}:{$a['fecha_fin']}", 'fechas', 'media', 'Actividad fuera de las fechas del proyecto: ' . $nom($a),
                    "La actividad va del {$a['fecha_inicio']} al {$a['fecha_fin']} y el proyecto del {$p->fecha_inicio} al {$p->fecha_fin}.", [$id], 'Revisar si se amplían las fechas del proyecto o se reprograma la actividad (no se cambia nada automáticamente).');
            }
            // duración que no cuadra con las fechas y el calendario
            if (!$hito && $dur > 0 && !$fija) {
                $calc = $cal->calcularFechaFin($a['fecha_inicio'], $dur);
                $dif = abs($motor->difHab($calc, $a['fecha_fin']));
                if ($dif >= 3) {
                    $real = max(1, (int)round($cal->calcularDuracion($a['fecha_inicio'], $a['fecha_fin'])));
                    $out[] = self::hallazgo("duracion:$id:$dur:{$a['fecha_inicio']}:{$a['fecha_fin']}", 'fechas', 'baja', 'Duración distinta a las fechas: ' . $nom($a),
                        "Dice durar $dur d hábiles pero con el calendario del proyecto sus fechas ({$a['fecha_inicio']} → {$a['fecha_fin']}) equivalen a ~$real d ($dif d hábiles de diferencia).",
                        [$id], 'Conservar el inicio y recalcular el fin con la duración indicada: ' . $calc . '.', 'ajustar_fechas', ['actividad_id' => $id, 'inicio' => $a['fecha_inicio'], 'fin' => $calc]);
                }
            }
            // inicio en día no hábil
            if (!$hito && !$fija && $prog === 0 && !$cal->esHabil($a['fecha_inicio']) && $a['fecha_inicio'] >= $hoy) {
                $ini = $motor->sig($a['fecha_inicio']);
                $fin = $cal->calcularFechaFin($ini, max(1, $dur));
                $out[] = self::hallazgo("no_habil:$id:{$a['fecha_inicio']}", 'calendario', 'baja', 'Inicia en día no hábil: ' . $nom($a), "Empieza el {$a['fecha_inicio']}, que es festivo o día sin jornada en el calendario del proyecto.",
                    [$id], "Mover el inicio al siguiente día hábil ($ini) y el fin a $fin.", 'ajustar_fechas', ['actividad_id' => $id, 'inicio' => $ini, 'fin' => $fin]);
            }
            // avance con inicio futuro / 100% con fin futuro
            if ($prog > 0 && $a['fecha_inicio'] > $hoy) {
                $out[] = self::hallazgo("avance_futuro:$id:$prog:{$a['fecha_inicio']}", 'avance', 'alta', 'Tiene avance pero inicia en el futuro: ' . $nom($a), "Reporta {$prog}% de avance y su inicio programado es el {$a['fecha_inicio']} (hoy es $hoy).",
                    [$id], 'Revisar si el avance está mal capturado o si la actividad se adelantó y su fecha de inicio debe corregirse (no se cambia nada automáticamente).');
            } elseif ($prog >= 100 && $a['fecha_fin'] > $hoy) {
                $out[] = self::hallazgo("completa_futuro:$id:{$a['fecha_fin']}", 'avance', 'media', 'Completada con fin en el futuro: ' . $nom($a), "Está al 100% pero su fin programado es el {$a['fecha_fin']} (hoy es $hoy).",
                    [$id], 'Confirmar que terminó antes de lo programado; si es así, ajustar su fecha de fin (no se cambia nada automáticamente).');
            }
            // vencida sin avance
            if (!$hito && !$fija && $prog === 0 && $a['fecha_fin'] < $hoy) {
                $ini = $motor->sig($hoy);
                $fin = $cal->calcularFechaFin($ini, max(1, $dur));
                $out[] = self::hallazgo("vencida:$id:{$a['fecha_fin']}", 'avance', 'media', 'Vencida sin avance: ' . $nom($a), "Debió terminar el {$a['fecha_fin']} y no tiene avance.",
                    [$id], "Reprogramar desde hoy conservando su duración ($dur d): $ini → $fin.", 'ajustar_fechas', ['actividad_id' => $id, 'inicio' => $ini, 'fin' => $fin]);
            }
        }

        // partidas con fechas que no son las de sus actividades
        $porPartida = [];
        foreach ($acts as $a) $porPartida[(int)$a['partida_id']][] = $a;
        foreach ($porPartida as $pidPartida => $lista) {
            $ini = min(array_column($lista, 'fecha_inicio'));
            $fin = max(array_column($lista, 'fecha_fin'));
            $a0 = $lista[0];
            if ($a0['p_ini'] !== $ini || $a0['p_fin'] !== $fin) {
                $out[] = self::hallazgo("partida_fechas:$pidPartida:{$a0['p_ini']}:{$a0['p_fin']}", 'estructura', 'baja', "Fechas de la partida {$a0['pcodigo']} desactualizadas",
                    "La partida «{$a0['pnombre']}» dice {$a0['p_ini']} → {$a0['p_fin']} pero sus actividades van de $ini a $fin.", array_map(fn($x) => (int)$x['id'], array_slice($lista, 0, 5)),
                    "Recalcular las fechas de la partida ($ini → $fin).", 'recalcular_partida', ['partida_id' => $pidPartida]);
            }
        }

        // dependencias que no se respetan
        $rels = Dependencias::relaciones($pid);
        if ($rels) {
            $m = [];
            foreach ($acts as $id => $a) $m[$id] = ['id' => $id, 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'dur' => (int)$a['duracion_dias'], 'hito' => (int)$a['hito'], 'fija' => (int)$a['fecha_fija']];
            $c = $motor->calcular($m, $rels);
            if (!$c['ciclo']) {
                foreach ($rels as $r) {
                    if (empty($c['deps'][$r['id']]['conflicto'])) continue;
                    $s = $acts[$r['suc']];
                    $pr = $acts[$r['pred']];
                    $req = $c['deps'][$r['id']]['requerido'];
                    $puede = (int)$s['progreso'] === 0 && !(int)$s['fecha_fija'] && !(int)$s['hito'];
                    $fin = $puede ? $cal->calcularFechaFin($req, max(1, (int)$s['duracion_dias'])) : null;
                    $out[] = self::hallazgo("dep_conflicto:{$r['pred']}:{$r['suc']}:{$s['fecha_inicio']}:{$pr['fecha_fin']}", 'dependencias', 'alta', "Dependencia incumplida: {$pr['codigo']} → {$s['codigo']}",
                        "«{$s['nombre']}» inicia el {$s['fecha_inicio']} pero por su relación {$r['tipo']} con «{$pr['nombre']}» no debería iniciar antes del $req.", [$r['pred'], $r['suc']],
                        $puede ? "Mover «{$s['codigo']}» a iniciar el $req (fin $fin)." : 'La sucesora tiene avance, es hito o tiene fecha fija: revisar a mano si se quita la relación o se reprograma (no se cambia nada automáticamente).',
                        $puede ? 'ajustar_fechas' : 'nota', $puede ? ['actividad_id' => $r['suc'], 'inicio' => $req, 'fin' => $fin] : []);
                }
            }
        }

        // materiales
        foreach (self::filas("SELECT m.id, m.nombre, m.compra, m.fecha_compra_prog AS cp, m.fecha_compra_real AS cr, m.fecha_entrega_prog AS ep, m.fecha_entrega_real AS er FROM materiales m WHERE m.proyecto_id = $pid") as $m) {
            $mid = (int)$m['id'];
            $nm = self::corto($m['nombre'], 60);
            if ($m['er'] && $m['compra'] === 'pendiente') {
                $out[] = self::hallazgo("mat_entregado_sin_compra:$mid", 'materiales', 'media', "Material entregado pero sin compra: $nm", "«{$m['nombre']}» tiene entrega real el {$m['er']} y aparece como compra «pendiente».", [], 'Marcar el material como comprado.', 'marcar_comprado', ['material_id' => $mid]);
            }
            if ($m['cp'] && $m['ep'] && $m['ep'] < $m['cp']) {
                $out[] = self::hallazgo("mat_entrega_antes_compra:$mid:{$m['cp']}:{$m['ep']}", 'materiales', 'media', "Entrega programada antes de la compra: $nm", "«{$m['nombre']}» se compra el {$m['cp']} pero su entrega está programada el {$m['ep']}.", [], 'Revisar cuál de las dos fechas es la correcta (no se cambia nada automáticamente).');
            }
            if ($m['cr'] && $m['er'] && $m['er'] < $m['cr']) {
                $out[] = self::hallazgo("mat_real_invertida:$mid:{$m['cr']}:{$m['er']}", 'materiales', 'media', "Entrega real antes de la compra real: $nm", "«{$m['nombre']}» se compró el {$m['cr']} pero se entregó el {$m['er']}.", [], 'Corregir la fecha mal capturada (no se cambia nada automáticamente).');
            }
        }
        foreach (self::filas("SELECT a.id, a.codigo, a.nombre, a.progreso, m.id AS mid, m.nombre AS mnombre FROM actividad_materiales am JOIN actividades a ON a.id = am.actividad_id JOIN materiales m ON m.id = am.material_id JOIN partidas pa ON pa.id = a.partida_id
                              WHERE pa.proyecto_id = $pid AND a.progreso > 0 AND a.sin_material = 0 AND m.fecha_entrega_real IS NULL") as $f) {
            $out[] = self::hallazgo("avance_sin_material:{$f['id']}:{$f['mid']}", 'materiales', 'media', "Avance sin material entregado: {$f['codigo']} ", "«{$f['nombre']}» lleva {$f['progreso']}% pero «{$f['mnombre']}» no tiene entrega real registrada.", [(int)$f['id']],
                'Registrar la entrega del material o marcar la actividad «sin material» si no lo requiere (no se cambia nada automáticamente).');
        }

        // restricciones activas en actividades terminadas
        foreach (self::filas("SELECT r.id, r.descripcion, a.id AS aid, a.codigo, a.nombre FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND r.estado = 'activa' AND a.progreso >= 100") as $f) {
            $out[] = self::hallazgo("rest_activa_completa:{$f['id']}", 'restricciones', 'media', "Restricción activa en actividad terminada: {$f['codigo']}", "«{$f['nombre']}» está al 100% y aún tiene la restricción activa: " . self::corto($f['descripcion'], 140), [(int)$f['aid']],
                'Marcar la restricción como resuelta.', 'resolver_restriccion', ['restriccion_id' => (int)$f['id']]);
        }

        // submittals
        foreach (self::filas("SELECT id, numero_submittal AS num, estatus, fecha, fecha_respuesta AS resp FROM submittals WHERE proyecto_id = $pid") as $f) {
            $n = $f['num'] ?: ('#' . $f['id']);
            if ($f['estatus'] === 'enviado' && $f['resp']) {
                $out[] = self::hallazgo("sub_enviado_con_respuesta:{$f['id']}", 'submittals', 'baja', "Submittal $n «enviado» con fecha de respuesta", "Tiene respuesta el {$f['resp']} pero sigue como «enviado».", [], 'Actualizar el estatus a aprobado o rechazado según la respuesta (no se cambia nada automáticamente).');
            } elseif (in_array($f['estatus'], ['aprobado', 'aprobado_an', 'rechazado'], true) && !$f['resp']) {
                $out[] = self::hallazgo("sub_sin_respuesta:{$f['id']}:{$f['estatus']}", 'submittals', 'baja', "Submittal $n {$f['estatus']} sin fecha de respuesta", 'Tiene un estatus de respuesta pero no se registró cuándo llegó.', [], 'Capturar la fecha de respuesta (no se cambia nada automáticamente).');
            } elseif ($f['resp'] && $f['resp'] < $f['fecha']) {
                $out[] = self::hallazgo("sub_resp_invertida:{$f['id']}:{$f['fecha']}:{$f['resp']}", 'submittals', 'media', "Submittal $n respondido antes de enviarse", "Se envió el {$f['fecha']} y la respuesta es del {$f['resp']}.", [], 'Corregir la fecha mal capturada (no se cambia nada automáticamente).');
            }
        }

        // reportes diarios: trabajo reportado en actividades sin avance
        foreach (self::filas("SELECT id, fecha, area, actividades_json FROM reportes_diarios WHERE proyecto_id = $pid AND fecha <= '$hoy' AND actividades_json IS NOT NULL") as $f) {
            foreach (array_filter((array)json_decode((string)$f['actividades_json'], true), 'is_int') as $aid) {
                $a = $acts[$aid] ?? null;
                if ($a && (int)$a['progreso'] === 0) {
                    $out[] = self::hallazgo("reporte_sin_avance:{$f['id']}:$aid", 'reportes', 'media', 'Reportada en obra pero sin avance: ' . $nom($a), "El reporte diario del {$f['fecha']} ({$f['area']}) menciona «{$a['nombre']}» y la actividad sigue en 0%.", [$aid],
                        'Registrar el avance real de la actividad (no se cambia nada automáticamente).');
                } elseif ($a && $f['fecha'] < $a['fecha_inicio'] && !(int)$a['hito']) {
                    $out[] = self::hallazgo("reporte_antes_inicio:{$f['id']}:$aid:{$a['fecha_inicio']}", 'reportes', 'baja', 'Reportada antes de su inicio programado: ' . $nom($a), "El reporte del {$f['fecha']} la menciona y su inicio programado es el {$a['fecha_inicio']}.", [$aid],
                        'Revisar si se adelantó (corregir la fecha de inicio) o si el reporte está mal asignado (no se cambia nada automáticamente).');
                }
            }
        }

        // duplicados evidentes
        $vistos = [];
        $codigos = [];
        foreach ($acts as $id => $a) {
            $k = mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', $a['nombre']))) . '|' . $a['partida_id'];
            $vistos[$k][] = $id;
            $codigos[mb_strtolower($a['codigo']) . '|' . $a['partida_id']][] = $id;
        }
        foreach ($vistos as $k => $ids) if (count($ids) > 1) {
            sort($ids);
            $out[] = self::hallazgo('dup_nombre:' . implode(',', $ids), 'duplicados', 'media', 'Actividades con el mismo nombre en una partida: ' . self::corto($acts[$ids[0]]['nombre'], 60), 'Hay ' . count($ids) . ' actividades con el mismo nombre en «' . $acts[$ids[0]]['pnombre'] . '» (' . implode(', ', array_map(fn($i) => $acts[$i]['codigo'], $ids)) . ').', $ids,
                'Revisar si es un duplicado y eliminar o renombrar la que sobre (no se cambia nada automáticamente).');
        }
        foreach ($codigos as $k => $ids) if (count($ids) > 1) {
            sort($ids);
            $out[] = self::hallazgo('dup_codigo:' . implode(',', $ids), 'duplicados', 'baja', 'Código repetido en una partida: ' . $acts[$ids[0]]['codigo'], 'Las actividades ' . implode(' y ', array_map(fn($i) => '«' . self::corto($acts[$i]['nombre'], 40) . '»', $ids)) . ' comparten el código ' . $acts[$ids[0]]['codigo'] . '.', $ids,
                'Darles códigos distintos (no se cambia nada automáticamente).');
        }
        foreach (self::nomenclatura($acts) as $h) $out[] = $h;
        foreach (Plantillas::reglaSecuencia($p, $acts, $rels) as $h) $out[] = $h;
        foreach (self::duracionesAtipicas($p, $acts, $cal) as $h) $out[] = $h;
        usort($out, fn($x, $y) => self::SEVERIDADES[$y['severidad']] <=> self::SEVERIDADES[$x['severidad']]);
        return $out;
    }

    /** Palabras significativas de un nombre (sin acentos, mayúsculas ni palabras de relleno) */
    private static function palabras(string $t): array
    {
        $t = mb_strtolower(strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n']));
        $vacias = ['de', 'del', 'la', 'el', 'los', 'las', 'en', 'y', 'e', 'a', 'con', 'para', 'por', 'un', 'una', 'al', 'sobre', 'the', 'of', 'and', 'in', 'to'];
        return array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', $t), fn($w) => strlen($w) > 1 && !in_array($w, $vacias, true) && !ctype_digit($w))));
    }

    /** Orden de la nomenclatura: solo limpiezas seguras (espacios, mayúscula inicial, punto final); lo semántico lo propone la IA */
    private static function nomenclatura(array $acts): array
    {
        $out = [];
        foreach ($acts as $id => $a) {
            $n = (string)$a['nombre'];
            $limpio = trim((string)preg_replace('/\s+/u', ' ', $n));
            $limpio = rtrim($limpio, " .;,");
            if ($limpio !== '' && mb_strtolower(mb_substr($limpio, 0, 1)) === mb_substr($limpio, 0, 1) && preg_match('/^\p{Ll}/u', $limpio)) $limpio = mb_strtoupper(mb_substr($limpio, 0, 1)) . mb_substr($limpio, 1);
            if ($limpio === '' || $limpio === $n) continue;
            $out[] = self::hallazgo("nomenclatura:$id:" . md5($n), 'nomenclatura', 'baja', 'Nombre por ordenar: ' . $a['codigo'] . ' ' . self::corto($n, 60), "El nombre «" . self::corto($n, 100) . "» tiene espacios sobrantes, no empieza con mayúscula o termina en signo de puntuación.",
                [$id], "Renombrar a «" . self::corto($limpio, 100) . "».", 'renombrar_actividad', ['actividad_id' => $id, 'nombre' => $limpio]);
        }
        return array_slice($out, 0, 40);
    }

    /**
     * Actividades de nombre parecido (este y otros proyectos) para sugerir una duración al crear o editar una actividad.
     * @return array{n: int, min: int, p50: int, max: int, ejemplos: array}|null  null si hay menos de 3 comparables
     */
    public static function similares(Proyecto $p, string $nombre, int $excluir = 0): ?array
    {
        $t = self::palabras($nombre);
        if (count($t) < 2) return null;
        $d = [];
        $ej = [];
        foreach (self::filas("SELECT a.id, a.codigo, a.nombre, a.duracion_dias, pa.proyecto_id FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.hito = 0 AND a.duracion_dias > 0 AND a.id <> $excluir") as $f) {
            $comunes = count(array_intersect($t, self::palabras($f['nombre'])));
            $union = count(array_unique(array_merge($t, self::palabras($f['nombre']))));
            if ($comunes >= 2 && $union > 0 && $comunes / $union >= 0.5) {
                $d[] = (int)$f['duracion_dias'];
                if ((int)$f['proyecto_id'] === (int)$p->id && count($ej) < 4) $ej[] = $f['codigo'] . ' ' . self::corto($f['nombre'], 50) . ' (' . $f['duracion_dias'] . ' d)';
            }
        }
        if (count($d) < 3) return null;
        sort($d);
        return ['n' => count($d), 'min' => $d[0], 'p50' => $d[intdiv(count($d), 2)], 'max' => end($d), 'ejemplos' => $ej];
    }

    /**
     * Estimador de duraciones: compara cada actividad sin empezar con otras de nombre parecido (en este y en los demás proyectos).
     * Con al menos 3 comparables y una diferencia clara propone la mediana. Solo cuentas, nunca se muestran nombres de otros proyectos.
     */
    private static function duracionesAtipicas(Proyecto $p, array $acts, Calendario $cal): array
    {
        $pid = (int)$p->id;
        $todas = [];
        foreach ($acts as $id => $a) if (!(int)$a['hito'] && (int)$a['duracion_dias'] > 0) $todas[] = ['id' => $id, 'p' => $pid, 't' => self::palabras($a['nombre']), 'd' => (int)$a['duracion_dias']];
        foreach (self::filas("SELECT a.id, pa.proyecto_id, a.nombre, a.duracion_dias FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id <> $pid AND a.hito = 0 AND a.duracion_dias > 0") as $f) $todas[] = ['id' => (int)$f['id'], 'p' => (int)$f['proyecto_id'], 't' => self::palabras($f['nombre']), 'd' => (int)$f['duracion_dias']];
        if (count($todas) < 8) return [];
        $out = [];
        foreach ($acts as $id => $a) {
            if ((int)$a['hito'] || (int)$a['progreso'] > 0 || (int)$a['fecha_fija'] || (int)$a['duracion_dias'] < 1) continue;
            $t = self::palabras($a['nombre']);
            if (count($t) < 2) continue;
            $d = [];
            foreach ($todas as $o) {
                if ($o['id'] === $id) continue;
                $comunes = count(array_intersect($t, $o['t']));
                $union = count(array_unique(array_merge($t, $o['t'])));
                if ($comunes >= 2 && $union > 0 && $comunes / $union >= 0.5) $d[] = $o['d'];
            }
            if (count($d) < 3) continue;
            sort($d);
            $med = $d[intdiv(count($d), 2)];
            $dur = (int)$a['duracion_dias'];
            if ($med < 1 || abs($dur - $med) < 3 || !($dur >= 2 * $med || $dur * 2 <= $med)) continue;
            $out[] = ['dev' => abs($dur - $med), 'h' => self::hallazgo("duracion_atipica:$id:$dur:$med", 'duraciones', 'baja', 'Duración atípica: ' . $a['codigo'] . ' ' . self::corto($a['nombre'], 60),
                "Dura $dur d hábiles y " . count($d) . " actividades parecidas duran entre {$d[0]} y " . end($d) . " d (mediana $med d).", [$id],
                "Ajustar la duración a $med d (la fecha de fin se recalcula con el calendario).", 'ajustar_duracion', ['actividad_id' => $id, 'dur' => $med])];
        }
        usort($out, fn($x, $y) => $y['dev'] <=> $x['dev']);
        return array_map(fn($x) => $x['h'], array_slice($out, 0, 15));
    }

    // ---------- vigilancia continua ----------

    /** Foto numérica del proyecto para detectar cambios bruscos entre comprobaciones */
    private static function metricas(int $pid): array
    {
        $f = self::filas("SELECT COALESCE(SUM(a.progreso * GREATEST(a.duracion_dias, 1)) / NULLIF(SUM(GREATEST(a.duracion_dias, 1)), 0), 0) av, MAX(a.fecha_fin) fin, COUNT(*) n FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND a.hito = 0")[0] ?? [];
        return ['avance' => round((float)($f['av'] ?? 0), 2), 'fin' => $f['fin'] ?? null, 'actividades' => (int)($f['n'] ?? 0),
            'restricciones' => (int)(self::filas("SELECT COUNT(*) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND r.estado = 'activa'")[0]['n'] ?? 0)];
    }

    /**
     * Comprueba el proyecto con las reglas del sistema (sin IA) y lo compara con la comprobación anterior: devuelve los hallazgos nuevos
     * (que no estén ya aceptados o rechazados) y los cambios bruscos. No escribe hallazgos: solo avisa; el usuario decide si hace «Revisar ahora».
     * @return array{revisado: string, pendientes: int, nuevos: array, cambios: array, primera: bool}
     */
    public static function vigilar(Proyecto $p): array
    {
        $pid = (int)$p->id;
        $db = self::db();
        $h = self::detectarSistema($p);
        $resueltas = [];
        foreach (self::filas("SELECT huella FROM inconsistencias WHERE proyecto_id = $pid AND estado <> 'pendiente'") as $f) $resueltas[$f['huella']] = true;
        $h = array_values(array_filter($h, fn($x) => !isset($resueltas[$x['huella']])));
        $previo = self::filas("SELECT huellas_json, metricas_json FROM vigilancia_estado WHERE proyecto_id = $pid")[0] ?? null;
        $vistas = $previo ? array_flip((array)json_decode($previo['huellas_json'], true)) : [];
        $nuevos = array_values(array_filter($h, fn($x) => !isset($vistas[$x['huella']])));
        $m = self::metricas($pid);
        $cambios = [];
        if ($previo) {
            $a = json_decode($previo['metricas_json'], true) ?: [];
            if (isset($a['avance']) && $m['avance'] < $a['avance'] - 1) $cambios[] = ['severidad' => 'alta', 'titulo' => 'El avance global bajó de ' . $a['avance'] . '% a ' . $m['avance'] . '%', 'descripcion' => 'Alguna actividad retrocedió en avance o se agregó trabajo nuevo. Revisa el historial de avance.'];
            if (!empty($a['fin']) && $m['fin'] && abs(strtotime($m['fin']) - strtotime($a['fin'])) >= 5 * 86400) $cambios[] = ['severidad' => 'media', 'titulo' => 'El fin del programa cambió de ' . $a['fin'] . ' a ' . $m['fin'], 'descripcion' => 'La última actividad del programa se movió cinco días o más desde la comprobación anterior.'];
            if (isset($a['actividades']) && $m['actividades'] < $a['actividades']) $cambios[] = ['severidad' => 'media', 'titulo' => ($a['actividades'] - $m['actividades']) . ' actividad(es) menos que en la comprobación anterior', 'descripcion' => 'Se eliminaron actividades del programa.'];
            if (isset($a['restricciones']) && $m['restricciones'] >= $a['restricciones'] + 3) $cambios[] = ['severidad' => 'media', 'titulo' => 'Aumentaron las restricciones activas de ' . $a['restricciones'] . ' a ' . $m['restricciones'], 'descripcion' => 'Se abrieron restricciones nuevas desde la comprobación anterior.'];
        }
        $resumenNuevos = array_map(fn($x) => ['severidad' => $x['severidad'], 'titulo' => $x['titulo'], 'categoria' => $x['categoria']], array_slice($nuevos, 0, 60));
        $esc = fn($v) => $db->real_escape_string(json_encode($v, JSON_UNESCAPED_UNICODE));
        $db->query("REPLACE INTO vigilancia_estado (proyecto_id, huellas_json, metricas_json, nuevos_json, revisado) VALUES ($pid, '" . $esc(array_column($h, 'huella')) . "', '" . $esc($m) . "', '" . $esc(['hallazgos' => $resumenNuevos, 'cambios' => $cambios, 'total_nuevos' => count($nuevos)]) . "', NOW())");
        return ['revisado' => date('Y-m-d H:i:s'), 'pendientes' => count($h), 'nuevos' => $resumenNuevos, 'total_nuevos' => count($nuevos), 'cambios' => $cambios, 'primera' => !$previo];
    }

    /** Resultado de la última comprobación (o null si nunca se hizo) */
    public static function estadoVigilancia(int $pid): ?array
    {
        $f = self::filas("SELECT revisado, nuevos_json FROM vigilancia_estado WHERE proyecto_id = $pid")[0] ?? null;
        if (!$f) return null;
        return ['revisado' => $f['revisado']] + ((array)json_decode((string)$f['nuevos_json'], true) + ['hallazgos' => [], 'cambios' => [], 'total_nuevos' => 0]);
    }

    // ---------- fase 2: revisión con IA ----------

    private static function instrucciones(): string
    {
        return <<<'TXT'
Eres un auditor de datos de proyectos de construcción. Revisa CADA ACTIVIDAD de la lista, una por una (sus fechas, duración, avance, predecesoras, materiales y restricciones), contrastándola con la bitácora y los reportes diarios, y detecta INCONSISTENCIAS en la información que no son simples cálculos (esos ya los detectó el sistema). También revisa la NOMENCLATURA: ortografía, mayúsculas y estilo uniforme (verbo en infinitivo + objeto + ubicación) sin cambiar el significado, proponiendo renombrar_actividad solo cuando el cambio sea claro: contradicciones entre lo que dicen la bitácora o los reportes y el avance o las fechas, actividades repetidas o casi iguales con otro nombre, materiales o restricciones asignados a una actividad que no corresponde, nombres que no corresponden a su partida, duraciones absurdas frente a actividades parecidas, restricciones que el texto indica ya resueltas, etc.

Reglas estrictas:
- Reporta SOLO inconsistencias reales sustentadas en los datos de abajo; si no hay, devuelve la lista vacía. No inventes ni repitas lo que ya detectó el sistema.
- Cada hallazgo debe ser sobre una o más ACTIVIDADES concretas (la partida es solo el agrupador): usa SOLO ids de las actividades de la lista en "actividades"; sin al menos un id válido el hallazgo se descarta. Máximo 10 hallazgos, los más importantes primero.
- "titulo" breve (máx. 100 caracteres); "descripcion" cita el dato que lo sustenta (máx. 300 caracteres).
- "categoria": fechas, avance, calendario, dependencias, materiales, restricciones, submittals, reportes, duplicados, estructura u otro. "severidad": baja, media, alta o critica.
- "solucion": {"texto": qué propones hacer (máx. 200 caracteres), "accion": una de las siguientes, "params": sus parámetros}. Si no hay una acción segura, usa "nota" (el usuario lo resolverá a mano).
  Acciones: nota {} · ajustar_fechas {actividad_id, inicio, fin} (YYYY-MM-DD) · ajustar_progreso {actividad_id, progreso 0-100} · sincronizar_status {actividad_id} · resolver_restriccion {restriccion_id} · marcar_comprado {material_id} · quitar_dependencia {pred, suc} · renombrar_actividad {actividad_id, nombre} · editar_descripcion {actividad_id, descripcion}.
  Usa una acción distinta de "nota" solo cuando los datos la respalden claramente.

Formato (JSON estricto):
{"hallazgos":[{"categoria":"avance","severidad":"media","titulo":"","descripcion":"","actividades":[0],"solucion":{"texto":"","accion":"nota","params":{}}}]}
TXT;
    }

    /** Prompts por paquete de actividades (por partida) más una revisión cruzada de nombres para encontrar repetidas entre partidas */
    public static function lotes(Proyecto $p, array $sistema): array
    {
        $ctx = Programador::contexto($p);
        $porPartida = [];
        foreach ($ctx['actividades'] as $a) $porPartida[$a['partida']][] = $a;
        $bit = array_map(fn($b) => "{$b['fecha']} {$b['importancia']} {$b['depto']}: " . self::corto($b['texto'], 200), array_slice($ctx['bitacora'], 0, 12));
        $yaPorAct = [];
        foreach ($sistema as $h) foreach ($h['actividades'] as $i) $yaPorAct[$i][] = $h['titulo'];

        $trozos = [];
        $pend = [];
        $nombres = [];
        foreach ($porPartida as $nombre => $lista) {
            foreach (array_chunk($lista, self::PAQUETE_MAX) as $k => $t) {
                $etq = self::corto($nombre, 40) . (count($lista) > self::PAQUETE_MAX ? ' (' . ($k + 1) . ')' : '');
                if (count($t) >= 10) { $trozos[] = [$etq, $t]; continue; }
                if (count($pend) + count($t) > self::PAQUETE_MAX) { $trozos[] = [implode(' + ', $nombres), $pend]; $pend = []; $nombres = []; }
                array_push($pend, ...$t); $nombres[] = $etq;
            }
        }
        if ($pend) $trozos[] = [implode(' + ', $nombres), $pend];

        $lotes = [];
        $totalActs = count($ctx['actividades']);
        $hechas = 0;
        foreach ($trozos as $i => [$titulo, $acts]) {
            $ids = array_column($acts, 'id');
            $desde = $hechas + 1;
            $hechas += count($acts);
            $lineas = [];
            foreach ($acts as $a) {
                $pr = implode(',', array_map(fn($d) => $d['id'] . ($d['tipo'] !== 'FS' ? $d['tipo'] : ''), $a['predecesoras']));
                $m = implode(';', array_map(fn($x) => self::corto($x['nombre'], 35) . '(' . ($x['entrega_real'] ? 'entregado' : ($x['entrega_prog'] ?: 'sin fecha')) . ')', $a['materiales']));
                $r = implode(';', array_map(fn($x) => self::corto($x['tipo'] . ': ' . $x['descripcion'], 55), $a['restricciones']));
                $lineas[] = $a['id'] . '|' . $a['codigo'] . '|' . self::corto($a['nombre'], 65) . '|' . substr($a['inicio'], 2) . '→' . substr($a['fin'], 2) . '|' . $a['dur'] . 'd|' . $a['progreso'] . '%'
                    . ($pr !== '' ? '|pred:' . $pr : '') . ($m !== '' ? '|mat:' . $m : '') . ($r !== '' ? '|rest:' . $r : '');
            }
            $rep = [];
            foreach ($ctx['reportes'] as $r) if (array_intersect($r['actividades'], $ids)) $rep[] = "{$r['fecha']} {$r['area']} [" . implode(',', array_intersect($r['actividades'], $ids)) . ']: ' . self::corto($r['texto'], 200);
            $ya = [];
            foreach ($ids as $id) foreach ($yaPorAct[$id] ?? [] as $t) $ya[$t] = 1;
            $prompt = self::instrucciones() . Plantillas::contexto($p) . "\n\n## ACTIVIDADES A REVISAR (partida: $titulo)\nHoy: {$ctx['hoy']} · proyecto {$ctx['proyecto']['inicio']} → {$ctx['proyecto']['fin']}"
                . "\n\n## ACTIVIDADES (id|código|nombre|inicio→fin|duración|avance|pred|mat|rest)\n" . implode("\n", $lineas)
                . "\n\n## YA DETECTADO POR EL SISTEMA (no lo repitas)\n" . ($ya ? implode("\n", array_slice(array_keys($ya), 0, 15)) : '(nada)')
                . "\n\n## BITÁCORA RECIENTE\n" . ($bit ? implode("\n", $bit) : '(vacía)')
                . "\n\n## REPORTES DIARIOS QUE MENCIONAN ESTAS ACTIVIDADES\n" . ($rep ? implode("\n", array_slice($rep, 0, 6)) : '(ninguno)')
                . "\n\nResponde SOLO con el JSON.";
            $codigos = array_values(array_unique(array_map(fn($a) => strtok($a['partida'], ' '), $acts)));
            $lotes[] = ['titulo' => "Actividades {$desde} a {$hechas} de {$totalActs} (partida" . (count($codigos) > 1 ? 's' : '') . ' ' . implode(', ', array_slice($codigos, 0, 4)) . (count($codigos) > 4 ? '…' : '') . ')', 'prompt' => $prompt, 'ids' => $ids];
        }
        // revisión cruzada de nombres: solo nombres, en una sola pasada, para encontrar repetidas entre partidas distintas
        if (count($ctx['actividades']) > 30) {
            $todas = array_map(fn($a) => $a['id'] . '|' . self::corto($a['partida'], 18) . '|' . self::corto($a['nombre'], 55), array_values($ctx['actividades']));
            foreach (array_chunk($todas, 160) as $j => $trozo) {
                $lotes[] = ['titulo' => 'Comparación de nombres entre las ' . $totalActs . ' actividades' . (count($todas) > 160 ? ' (' . ($j + 1) . ' de ' . ceil(count($todas) / 160) . ')' : ''), 'ids' => [], 'prompt' => self::instrucciones()
                    . "\n\nEn esta pasada SOLO busca actividades REPETIDAS o casi iguales (aunque estén en partidas distintas) y nombres que no correspondan a su partida; usa categoria «duplicados» o «estructura» y accion «nota».\n\n## ACTIVIDADES (id|partida|nombre)\n" . implode("\n", $trozo) . "\n\nResponde SOLO con el JSON."];
            }
        }
        return $lotes;
    }

    /** Valida lo que dijo la IA: ids del proyecto, categorías y acciones permitidas con sus parámetros; si algo no cuadra, la solución queda como «nota» */
    public static function normalizarIA(Proyecto $p, array $respuestas): array
    {
        $pid = (int)$p->id;
        $acts = Dependencias::actividades($pid);
        $out = [];
        foreach ($respuestas as $r) {
            foreach ((array)($r['hallazgos'] ?? []) as $h) {
                if (!is_array($h)) continue;
                $titulo = self::corto($h['titulo'] ?? '', 200);
                $desc = self::corto($h['descripcion'] ?? '', 500);
                if ($titulo === '' || $desc === '') continue;
                $ids = array_values(array_unique(array_filter(array_map('intval', (array)($h['actividades'] ?? [])), fn($i) => isset($acts[$i]))));
                if (!$ids) continue;   // la revisión es por actividad: un hallazgo sin actividad del proyecto no se puede ubicar
                $cat = isset(self::CATEGORIAS[$h['categoria'] ?? '']) ? $h['categoria'] : 'otro';
                $sev = isset(self::SEVERIDADES[$h['severidad'] ?? '']) ? $h['severidad'] : 'media';
                $s = is_array($h['solucion'] ?? null) ? $h['solucion'] : [];
                $accion = isset(self::ACCIONES[$s['accion'] ?? '']) && !in_array($s['accion'], ['ajustar_duracion', 'agregar_dia_no_habil', 'agregar_dependencia'], true) ? $s['accion'] : 'nota';   // duración y días no hábiles los proponen solo las reglas del sistema
                $params = self::paramsValidos($pid, $accion, is_array($s['params'] ?? null) ? $s['params'] : []);
                $texto = self::corto($s['texto'] ?? '', 300) ?: 'Revisar y corregir a mano.';
                if ($params === null) { $accion = 'nota'; $params = []; $texto .= ' (el sistema no pudo validar la acción propuesta: se resuelve a mano)'; }
                $out[] = ['huella' => sha1('i:' . $cat . '|' . implode(',', $ids) . '|' . $accion . '|' . json_encode($params) . '|' . mb_strtolower(preg_replace('/\W+/u', '', $titulo))), 'categoria' => $cat, 'severidad' => $sev,
                    'origen' => 'ia', 'titulo' => $titulo, 'descripcion' => $desc, 'actividades' => $ids, 'solucion' => ['texto' => $texto, 'accion' => $accion, 'params' => $params]];
            }
        }
        return $out;
    }

    /** Parámetros de una acción ya saneados, o null si no son válidos / no pertenecen al proyecto */
    private static function paramsValidos(int $pid, string $accion, array $p): ?array
    {
        $db = self::db();
        $en = fn(string $sql) => (bool)($db->query($sql)?->num_rows);
        switch ($accion) {
            case 'nota': return [];
            case 'ajustar_fechas':
                $a = (int)($p['actividad_id'] ?? 0);
                $i = self::fecha($p['inicio'] ?? null);
                $f = self::fecha($p['fin'] ?? null);
                if (!$i || !$f || $f < $i || !$en("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $a AND pa.proyecto_id = $pid")) return null;
                return ['actividad_id' => $a, 'inicio' => $i, 'fin' => $f];
            case 'sincronizar_status':
                $a = (int)($p['actividad_id'] ?? 0);
                return $en("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $a AND pa.proyecto_id = $pid") ? ['actividad_id' => $a] : null;
            case 'ajustar_progreso':
                $a = (int)($p['actividad_id'] ?? 0);
                $v = $p['progreso'] ?? null;
                if (!is_numeric($v) || $v < 0 || $v > 100 || !$en("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $a AND pa.proyecto_id = $pid")) return null;
                return ['actividad_id' => $a, 'progreso' => (int)$v];
            case 'recalcular_partida':
                $x = (int)($p['partida_id'] ?? 0);
                return $en("SELECT 1 FROM partidas WHERE id = $x AND proyecto_id = $pid") ? ['partida_id' => $x] : null;
            case 'marcar_comprado':
                $x = (int)($p['material_id'] ?? 0);
                return $en("SELECT 1 FROM materiales WHERE id = $x AND proyecto_id = $pid") ? ['material_id' => $x] : null;
            case 'resolver_restriccion':
                $x = (int)($p['restriccion_id'] ?? 0);
                return $en("SELECT 1 FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE r.id = $x AND pa.proyecto_id = $pid") ? ['restriccion_id' => $x] : null;
            case 'ajustar_duracion':
                $a = (int)($p['actividad_id'] ?? 0);
                $d = (int)($p['dur'] ?? 0);
                if ($d < 1 || $d > 3650 || !$en("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $a AND pa.proyecto_id = $pid")) return null;
                return ['actividad_id' => $a, 'dur' => $d];
            case 'renombrar_actividad':
                $a = (int)($p['actividad_id'] ?? 0);
                $n = self::corto($p['nombre'] ?? '', 200);
                if ($n === '' || !$en("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $a AND pa.proyecto_id = $pid")) return null;
                return ['actividad_id' => $a, 'nombre' => $n];
            case 'editar_descripcion':
                $a = (int)($p['actividad_id'] ?? 0);
                $t = trim((string)($p['descripcion'] ?? ''));
                if (mb_strlen($t) > 2000 || !$en("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $a AND pa.proyecto_id = $pid")) return null;
                return ['actividad_id' => $a, 'descripcion' => $t];
            case 'agregar_dia_no_habil':
                $f = self::fecha($p['fecha'] ?? null);
                if (!$f) return null;
                return ['fecha' => $f, 'motivo' => self::corto($p['motivo'] ?? 'Día no hábil', 190)];
            case 'agregar_dependencia':
                $a = (int)($p['pred'] ?? 0);
                $b = (int)($p['suc'] ?? 0);
                $tipo = $p['tipo'] ?? 'FS';
                if ($a === $b || !isset(Dependencias::TIPOS[$tipo]) || !$en("SELECT 1 FROM actividades x JOIN partidas pa ON pa.id = x.partida_id WHERE x.id = $a AND pa.proyecto_id = $pid") || !$en("SELECT 1 FROM actividades x JOIN partidas pa ON pa.id = x.partida_id WHERE x.id = $b AND pa.proyecto_id = $pid")) return null;
                return ['pred' => $a, 'suc' => $b, 'tipo' => $tipo, 'retraso' => max(-365, min(365, (int)($p['retraso'] ?? 0)))];
            case 'quitar_dependencia':
                $a = (int)($p['pred'] ?? 0);
                $b = (int)($p['suc'] ?? 0);
                return $en("SELECT 1 FROM actividad_dependencias WHERE proyecto_id = $pid AND predecesora_id = $a AND sucesora_id = $b") ? ['pred' => $a, 'suc' => $b] : null;
        }
        return null;
    }

    // ---------- persistencia ----------

    public const CORRIDAS_SIN_GUARDAR = 5;

    /**
     * Guarda un análisis nuevo (corrida) con sus hallazgos. Los análisis anteriores NO se tocan; lo ya aceptado o rechazado en cualquiera
     * de ellos no se vuelve a proponer. Los análisis sin guardar rotan (se conservan los 5 últimos).
     * @return array{corrida: int, nuevos: int, ya_resueltos: int}
     */
    public static function guardar(Proyecto $p, array $hallazgos, string $modelo = '', array $conteo = []): array
    {
        $db = self::db();
        $pid = (int)$p->id;
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $sist = count(array_filter($hallazgos, fn($h) => $h['origen'] === 'sistema'));
        $db->query("INSERT INTO inconsistencias_corridas (proyecto_id, modelo, del_sistema, de_la_ia) VALUES ($pid, '" . $esc(self::corto($modelo, 150)) . "', " . (int)($conteo['del_sistema'] ?? $sist) . ', ' . (int)($conteo['de_la_ia'] ?? count($hallazgos) - $sist) . ')');
        $corrida = (int)$db->insert_id;
        \Classes\Revisiones::registrarEjecucion($pid, 'inconsistencias');
        $resueltas = [];
        foreach (self::filas("SELECT huella FROM inconsistencias WHERE proyecto_id = $pid AND estado <> 'pendiente'") as $f) $resueltas[$f['huella']] = true;
        $nuevos = $ya = 0;
        $vistos = [];
        foreach ($hallazgos as $h) {
            if (isset($vistos[$h['huella']])) continue;
            $vistos[$h['huella']] = true;
            if (isset($resueltas[$h['huella']])) { $ya++; continue; }
            $h['solucion']['esperado'] = self::esperado($h['solucion']['accion'], $h['solucion']['params']);
            $db->query("INSERT INTO inconsistencias (proyecto_id, corrida_id, huella, categoria, severidad, origen, titulo, descripcion, actividades_json, solucion_json, modelo) VALUES ($pid, $corrida, '{$esc($h['huella'])}', '{$esc($h['categoria'])}', '{$esc($h['severidad'])}', '{$esc($h['origen'])}', '{$esc($h['titulo'])}', '{$esc($h['descripcion'])}', '"
                . $esc(json_encode($h['actividades'])) . "', '" . $esc(json_encode($h['solucion'], JSON_UNESCAPED_UNICODE)) . "', '" . $esc(self::corto($h['origen'] === 'ia' ? $modelo : '', 150)) . "')");
            $nuevos++;
        }
        $db->query("DELETE FROM inconsistencias_corridas WHERE proyecto_id = $pid AND guardada = 0 AND id NOT IN (SELECT id FROM (SELECT id FROM inconsistencias_corridas WHERE proyecto_id = $pid AND guardada = 0 ORDER BY id DESC LIMIT " . self::CORRIDAS_SIN_GUARDAR . ') t)');
        return ['corrida' => $corrida, 'nuevos' => $nuevos, 'ya_resueltos' => $ya];
    }

    /**
     * Guarda el registro de cada lote de la IA ({titulo, ids, estado, error, respuesta}): actividades cubiertas, hallazgos validados y
     * respuesta cruda, para consultarlo después aunque el lote no haya generado hallazgos o haya fallado.
     */
    public static function guardarLotes(Proyecto $p, int $corrida, array $lotes): int
    {
        $db = self::db();
        $pid = (int)$p->id;
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $acts = Dependencias::actividades($pid);
        $n = 0;
        foreach (array_values($lotes) as $orden => $l) {
            if (!is_array($l)) continue;
            $ok = ($l['estado'] ?? 'ok') !== 'error';
            $cubiertas = [];
            foreach ((array)($l['ids'] ?? []) as $i) if (isset($acts[(int)$i])) $cubiertas[] = ['id' => (int)$i, 'codigo' => $acts[(int)$i]['codigo'], 'nombre' => $acts[(int)$i]['nombre']];
            $hallazgos = [];
            if ($ok && is_array($l['respuesta'] ?? null)) {
                foreach (self::normalizarIA($p, [$l['respuesta']]) as $h) $hallazgos[] = ['titulo' => $h['titulo'], 'severidad' => $h['severidad'], 'categoria' => $h['categoria'], 'accion' => $h['solucion']['accion']];
            }
            $resp = $ok && isset($l['respuesta']) ? mb_substr(json_encode($l['respuesta'], JSON_UNESCAPED_UNICODE), 0, 60000) : '';
            $db->query("INSERT INTO inconsistencias_lotes (proyecto_id, corrida_id, orden, titulo, estado, error, actividades_json, hallazgos_json, respuesta_json) VALUES ($pid, $corrida, " . ($orden + 1) . ", '" . $esc(self::corto($l['titulo'] ?? 'Lote ' . ($orden + 1), 150)) . "', '" . ($ok ? 'ok' : 'error') . "', '" . $esc(self::corto($l['error'] ?? '', 500)) . "', '"
                . $esc(json_encode($cubiertas, JSON_UNESCAPED_UNICODE)) . "', '" . $esc(json_encode($hallazgos, JSON_UNESCAPED_UNICODE)) . "', '" . $esc($resp) . "')");
            $n++;
        }
        return $n;
    }

    /** Registro por lote de un análisis (sin la respuesta cruda) */
    public static function lotesDeCorrida(int $pid, int $corrida): array
    {
        $out = [];
        foreach (self::filas("SELECT id, orden, titulo, estado, error, actividades_json, hallazgos_json FROM inconsistencias_lotes WHERE proyecto_id = $pid AND corrida_id = $corrida ORDER BY orden") as $f) {
            $f['actividades'] = json_decode((string)$f['actividades_json'], true) ?: [];
            $f['hallazgos'] = json_decode((string)$f['hallazgos_json'], true) ?: [];
            unset($f['actividades_json'], $f['hallazgos_json']);
            $out[] = $f;
        }
        return $out;
    }

    /** Estado de los datos que la solución va a tocar al momento de detectar: si cambian antes de aceptar, la solución ya no se aplica */
    private static function esperado(string $accion, array $params): ?array
    {
        if ($accion === 'ajustar_fechas' || $accion === 'ajustar_progreso') {
            $f = self::filas('SELECT fecha_inicio, fecha_fin, progreso FROM actividades WHERE id = ' . (int)$params['actividad_id'])[0] ?? null;
            if (!$f) return null;
            return $accion === 'ajustar_fechas' ? ['inicio' => $f['fecha_inicio'], 'fin' => $f['fecha_fin']] : ['progreso' => (int)$f['progreso']];
        }
        if (in_array($accion, ['ajustar_duracion', 'renombrar_actividad', 'editar_descripcion'], true)) {
            $f = self::filas('SELECT duracion_dias, fecha_fin, nombre, descripcion FROM actividades WHERE id = ' . (int)$params['actividad_id'])[0] ?? null;
            if (!$f) return null;
            return $accion === 'ajustar_duracion' ? ['dur' => (int)$f['duracion_dias'], 'fin' => $f['fecha_fin']] : ($accion === 'renombrar_actividad' ? ['nombre' => $f['nombre']] : ['descripcion' => (string)$f['descripcion']]);
        }
        return null;
    }

    public static function corridas(int $pid): array
    {
        return self::filas("SELECT c.id, c.nombre, c.guardada, c.modelo, c.del_sistema, c.de_la_ia, c.creado,
                   SUM(i.estado = 'pendiente') AS pendientes, SUM(i.estado = 'aceptada') AS aceptadas, SUM(i.estado = 'rechazada') AS rechazadas, COUNT(i.id) AS total
            FROM inconsistencias_corridas c LEFT JOIN inconsistencias i ON i.corrida_id = c.id WHERE c.proyecto_id = $pid GROUP BY c.id ORDER BY c.id DESC");
    }

    /** Guarda con nombre un análisis (queda siempre disponible) o lo quita de guardados */
    public static function marcarCorrida(int $pid, int $id, bool $guardar, string $nombre = ''): void
    {
        $db = self::db();
        $n = $db->real_escape_string(self::corto($nombre, 150));
        $db->query('UPDATE inconsistencias_corridas SET guardada = ' . ($guardar ? 1 : 0) . ", nombre = '" . ($guardar ? $n : '') . "' WHERE id = $id AND proyecto_id = $pid");
    }

    public static function eliminarCorrida(int $pid, int $id): void
    {
        self::db()->query("DELETE FROM inconsistencias_corridas WHERE id = $id AND proyecto_id = $pid");
    }

    /** Lista para la pantalla, con actividades implicadas ya resueltas a código y nombre */
    public static function listar(int $pid, array $f = []): array
    {
        $w = ["proyecto_id = $pid"];
        if (!empty($f['corrida'])) $w[] = 'corrida_id = ' . (int)$f['corrida'];
        if (in_array($f['estado'] ?? '', ['pendiente', 'aceptada', 'rechazada'], true)) $w[] = "estado = '{$f['estado']}'";
        if (isset(self::SEVERIDADES[$f['severidad'] ?? ''])) $w[] = "severidad = '{$f['severidad']}'";
        if (isset(self::CATEGORIAS[$f['categoria'] ?? ''])) $w[] = "categoria = '{$f['categoria']}'";
        if (in_array($f['origen'] ?? '', ['sistema', 'ia'], true)) $w[] = "origen = '{$f['origen']}'";
        $filas = self::filas('SELECT * FROM inconsistencias WHERE ' . implode(' AND ', $w) . " ORDER BY FIELD(estado,'pendiente','aceptada','rechazada'), FIELD(severidad,'critica','alta','media','baja'), id");
        $acts = Dependencias::actividades($pid);
        foreach ($filas as &$x) {
            $x['id'] = (int)$x['id'];
            $x['actividades'] = array_values(array_filter(array_map(fn($i) => isset($acts[$i]) ? ['id' => $i, 'codigo' => $acts[$i]['codigo'], 'nombre' => $acts[$i]['nombre']] : null, (array)json_decode((string)$x['actividades_json'], true))));
            $x['solucion'] = json_decode($x['solucion_json'], true) ?: ['texto' => '', 'accion' => 'nota', 'params' => []];
            $x['revertible'] = $x['estado'] === 'aceptada' && $x['antes_json'] !== null;
        }
        return $filas;
    }

    public static function conteos(int $pid, int $corrida = 0): array
    {
        $c = ['pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0, 'criticas' => 0];
        foreach (self::filas("SELECT estado, severidad, COUNT(*) n FROM inconsistencias WHERE proyecto_id = $pid" . ($corrida ? " AND corrida_id = $corrida" : '') . ' GROUP BY estado, severidad') as $f) {
            $c[$f['estado']] += (int)$f['n'];
            if ($f['estado'] === 'pendiente' && in_array($f['severidad'], ['alta', 'critica'], true)) $c['criticas'] += (int)$f['n'];
        }
        return $c;
    }

    private static function obtener(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM inconsistencias WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    private static function cerrar(int $pid, int $id, string $estado, ?array $antes): void
    {
        $db = self::db();
        $a = $antes === null ? 'NULL' : "'" . $db->real_escape_string(json_encode($antes)) . "'";
        $quien = $db->real_escape_string(usuario_actual()['nombre'] ?? '');
        $db->query("UPDATE inconsistencias SET estado = '$estado', antes_json = $a, resuelto_por = '$quien', resuelto_en = NOW() WHERE id = $id AND proyecto_id = $pid");
    }

    public static function rechazar(int $pid, int $id): array
    {
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] !== 'pendiente') return [false, 'La inconsistencia ya no está pendiente'];
        self::cerrar($pid, $id, 'rechazada', null);
        $sol = json_decode($h['solucion_json'], true) ?: [];
        Auditoria::registrar($pid, 'inconsistencias', 'rechazado', (string)($sol['accion'] ?? 'nota'), 'Rechazado: ' . $h['titulo'], null, (array)($sol['params'] ?? []), $id);
        return [true, 'Rechazada: no se volverá a mostrar'];
    }

    /** Vuelve a dejar pendiente algo rechazado, o revierte lo aplicado si la acción lo permite */
    public static function reabrir(Proyecto $p, int $id): array
    {
        $pid = (int)$p->id;
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] === 'pendiente') return [false, 'No hay nada que reabrir'];
        if ($h['estado'] === 'aceptada' && $h['antes_json'] !== null) {
            [$ok, $msg] = self::restaurar($p, json_decode($h['antes_json'], true) ?: []);
            if (!$ok) return [false, $msg];
        }
        self::db()->query("UPDATE inconsistencias SET estado = 'pendiente', antes_json = NULL, resuelto_por = '', resuelto_en = NULL WHERE id = $id AND proyecto_id = $pid");
        $sol = json_decode($h['solucion_json'], true) ?: [];
        $revirtio = $h['estado'] === 'aceptada' && $h['antes_json'] !== null;
        Auditoria::registrar($pid, 'inconsistencias', $revirtio ? 'revertido' : 'reabierto', (string)($sol['accion'] ?? 'nota'), ($revirtio ? 'Cambio revertido: ' : 'Vuelve a pendiente: ') . $h['titulo'], $revirtio ? json_decode($h['antes_json'], true) : null, null, $id, '', Auditoria::entidad($pid, (string)($sol['accion'] ?? 'nota'), (array)($sol['params'] ?? [])));
        return [true, $h['estado'] === 'aceptada' && $h['antes_json'] !== null ? 'Cambio revertido; vuelve a estar pendiente' : 'Vuelve a estar pendiente'];
    }

    // ---------- aplicar / revertir ----------

    public static function aceptar(Proyecto $p, int $id): array
    {
        $pid = (int)$p->id;
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] !== 'pendiente') return [false, 'La inconsistencia ya no está pendiente'];
        $s = json_decode($h['solucion_json'], true) ?: [];
        $accion = $s['accion'] ?? 'nota';
        $params = self::paramsValidos($pid, $accion, (array)($s['params'] ?? []));
        if ($params === null || !isset(self::ACCIONES[$accion])) return [false, 'La solución ya no es aplicable (los datos cambiaron). Rechaza o resuelve a mano.'];
        if (!empty($s['esperado']) && self::esperado($accion, $params) !== $s['esperado']) return [false, 'La actividad cambió desde que se hizo este análisis, así que la solución ya no aplica. Vuelve a analizar el proyecto o resuélvelo a mano.'];
        $db = self::db();
        $db->begin_transaction();
        try {
            [$ok, $msg, $antes] = self::ejecutar($p, $accion, $params);
            if (!$ok) { $db->rollback(); return [false, $msg]; }
            self::cerrar($pid, $id, 'aceptada', $antes);
            Auditoria::registrar($pid, 'inconsistencias', 'aplicado', $accion, $msg, $antes, $params, $id, $h['origen'] === 'ia' ? (string)$h['modelo'] : '');
            $db->query('UPDATE inconsistencias_corridas SET guardada = 1, nombre = IF(nombre = \'\', CONCAT(\'Análisis \', DATE_FORMAT(creado, \'%d/%m/%Y\')), nombre) WHERE id = ' . (int)$h['corrida_id']);   // un análisis con cambios aplicados no debe rotar
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo aplicar: ' . $e->getMessage()];
        }
        return [true, $msg];
    }

    private static function refrescar(int $pid, array $partidas): void
    {
        foreach (array_unique($partidas) as $x) if ($pa = Partida::find((int)$x)) $pa->recalcularFechas();
        try {
            \Model\ActividadMaterial::sincronizarInstalacion($pid);
        } catch (\Throwable $e) {
        }
    }

    /** @return array{0: bool, 1: string, 2: ?array} resultado, mensaje y valores previos (null si la acción no cambia datos) */
    private static function ejecutar(Proyecto $p, string $accion, array $x): array
    {
        $pid = (int)$p->id;
        $db = self::db();
        switch ($accion) {
            case 'nota':
                return [true, 'Marcada como atendida (no se cambió ningún dato)', null];
            case 'ajustar_fechas':
                $a = Actividad::find($x['actividad_id']);
                if ((int)$a->fecha_fija) return [false, 'La actividad tiene fecha fija: quita el candado primero', null];
                $antes = ['accion' => $accion, 'actividad_id' => (int)$a->id, 'inicio' => $a->fecha_inicio, 'fin' => $a->fecha_fin, 'dur' => (int)$a->duracion_dias];
                $a->fecha_inicio = $x['inicio'];
                $a->fecha_fin = $x['fin'];
                if (!(int)$a->hito) $a->duracion_dias = max(1, (int)round((new Calendario($pid))->calcularDuracion($x['inicio'], $x['fin'])));
                $a->guardar();
                self::refrescar($pid, [$a->partida_id]);
                return [true, "«{$a->codigo}» ahora va del {$x['inicio']} al {$x['fin']}", $antes];
            case 'sincronizar_status':
            case 'ajustar_progreso':
                $a = Actividad::find($x['actividad_id']);
                $antes = ['accion' => $accion, 'actividad_id' => (int)$a->id, 'progreso' => (int)$a->progreso, 'status' => $a->status];
                if ($accion === 'ajustar_progreso') $a->progreso = $x['progreso'];
                $a->sincronizarStatus();
                $a->guardar();
                return [true, "«{$a->codigo}»: avance {$a->progreso}%, estatus {$a->status}", $antes];
            case 'recalcular_partida':
                $pa = Partida::find($x['partida_id']);
                $antes = ['accion' => $accion, 'partida_id' => (int)$pa->id, 'inicio' => $pa->fecha_inicio, 'fin' => $pa->fecha_fin];
                $pa->recalcularFechas();
                return [true, "Partida «{$pa->codigo}» recalculada", $antes];
            case 'marcar_comprado':
                $f = self::filas("SELECT compra FROM materiales WHERE id = " . (int)$x['material_id'] . " AND proyecto_id = $pid")[0] ?? null;
                if (!$f) return [false, 'Material no encontrado', null];
                $db->query("UPDATE materiales SET compra = 'comprado' WHERE id = " . (int)$x['material_id']);
                return [true, 'Material marcado como comprado', ['accion' => $accion, 'material_id' => (int)$x['material_id'], 'compra' => $f['compra']]];
            case 'resolver_restriccion':
                $f = self::filas('SELECT estado FROM restricciones WHERE id = ' . (int)$x['restriccion_id'])[0] ?? null;
                if (!$f) return [false, 'Restricción no encontrada', null];
                $db->query("UPDATE restricciones SET estado = 'resuelta' WHERE id = " . (int)$x['restriccion_id']);
                return [true, 'Restricción marcada como resuelta', ['accion' => $accion, 'restriccion_id' => (int)$x['restriccion_id'], 'estado' => $f['estado']]];
            case 'ajustar_duracion':
                $a = Actividad::find($x['actividad_id']);
                if ((int)$a->fecha_fija) return [false, 'La actividad tiene fecha fija: quita el candado primero', null];
                $antes = ['accion' => $accion, 'actividad_id' => (int)$a->id, 'inicio' => $a->fecha_inicio, 'fin' => $a->fecha_fin, 'dur' => (int)$a->duracion_dias];
                $a->duracion_dias = $x['dur'];
                $a->fecha_fin = (int)$a->hito ? $a->fecha_inicio : (new Calendario($pid))->calcularFechaFin($a->fecha_inicio, $x['dur']);
                $a->guardar();
                self::refrescar($pid, [$a->partida_id]);
                return [true, "«{$a->codigo}» ahora dura {$x['dur']} d hábiles y termina el {$a->fecha_fin}", $antes];
            case 'renombrar_actividad':
                $a = Actividad::find($x['actividad_id']);
                $antes = ['accion' => $accion, 'actividad_id' => (int)$a->id, 'nombre' => $a->nombre];
                $a->nombre = $x['nombre'];
                $a->guardar();
                return [true, "Actividad «{$a->codigo}» renombrada a «{$x['nombre']}»", $antes];
            case 'editar_descripcion':
                $a = Actividad::find($x['actividad_id']);
                $antes = ['accion' => $accion, 'actividad_id' => (int)$a->id, 'descripcion' => $a->descripcion];
                $a->descripcion = $x['descripcion'];
                $a->guardar();
                return [true, "Descripción de «{$a->codigo}» actualizada", $antes];
            case 'agregar_dia_no_habil':
                $ya = self::filas("SELECT id FROM calendario_festivos WHERE proyecto_id = $pid AND fecha = '" . $db->real_escape_string($x['fecha']) . "'")[0] ?? null;
                if ($ya) return [false, 'Ese día ya está marcado como no hábil', null];
                $db->query("INSERT INTO calendario_festivos (proyecto_id, fecha, motivo, tipo) VALUES ($pid, '" . $db->real_escape_string($x['fecha']) . "', '" . $db->real_escape_string($x['motivo']) . "', 'clima')");
                return [true, "Día {$x['fecha']} marcado como no hábil ({$x['motivo']})", ['accion' => $accion, 'festivo_id' => (int)$db->insert_id]];
            case 'agregar_dependencia':
                $prev = self::filas("SELECT tipo, retraso FROM actividad_dependencias WHERE proyecto_id = $pid AND predecesora_id = " . (int)$x['pred'] . ' AND sucesora_id = ' . (int)$x['suc'])[0] ?? null;
                [$ok, $msg] = Dependencias::guardar($pid, (int)$x['pred'], (int)$x['suc'], $x['tipo'], (int)$x['retraso']);
                if (!$ok) return [false, $msg, null];
                return [true, 'Relación agregada (' . $x['tipo'] . ')', ['accion' => $accion, 'pred' => (int)$x['pred'], 'suc' => (int)$x['suc'], 'previa' => $prev ? ['tipo' => $prev['tipo'], 'retraso' => (int)$prev['retraso']] : null]];
            case 'quitar_dependencia':
                $f = self::filas("SELECT tipo, retraso FROM actividad_dependencias WHERE proyecto_id = $pid AND predecesora_id = " . (int)$x['pred'] . ' AND sucesora_id = ' . (int)$x['suc'])[0] ?? null;
                if (!$f) return [false, 'La relación ya no existe', null];
                $db->query("DELETE FROM actividad_dependencias WHERE proyecto_id = $pid AND predecesora_id = " . (int)$x['pred'] . ' AND sucesora_id = ' . (int)$x['suc']);
                return [true, 'Relación eliminada', ['accion' => $accion, 'pred' => (int)$x['pred'], 'suc' => (int)$x['suc'], 'tipo' => $f['tipo'], 'retraso' => (int)$f['retraso']]];
        }
        return [false, 'Acción no permitida', null];
    }

    private static function restaurar(Proyecto $p, array $a): array
    {
        $pid = (int)$p->id;
        $db = self::db();
        switch ($a['accion'] ?? '') {
            case 'ajustar_fechas':
                $x = Actividad::find($a['actividad_id']);
                if (!$x) return [false, 'La actividad ya no existe'];
                $x->fecha_inicio = $a['inicio'];
                $x->fecha_fin = $a['fin'];
                $x->duracion_dias = $a['dur'];
                $x->guardar();
                self::refrescar($pid, [$x->partida_id]);
                return [true, ''];
            case 'sincronizar_status':
            case 'ajustar_progreso':
                $x = Actividad::find($a['actividad_id']);
                if (!$x) return [false, 'La actividad ya no existe'];
                $x->progreso = $a['progreso'];
                $x->status = $a['status'];
                $x->guardar();
                return [true, ''];
            case 'recalcular_partida':
                $db->query('UPDATE partidas SET fecha_inicio = ' . ($a['inicio'] ? "'" . $db->real_escape_string($a['inicio']) . "'" : 'NULL') . ', fecha_fin = ' . ($a['fin'] ? "'" . $db->real_escape_string($a['fin']) . "'" : 'NULL') . ' WHERE id = ' . (int)$a['partida_id'] . " AND proyecto_id = $pid");
                return [true, ''];
            case 'ajustar_duracion':
                $x = Actividad::find($a['actividad_id']);
                if (!$x) return [false, 'La actividad ya no existe'];
                $x->fecha_inicio = $a['inicio'];
                $x->fecha_fin = $a['fin'];
                $x->duracion_dias = $a['dur'];
                $x->guardar();
                self::refrescar($pid, [$x->partida_id]);
                return [true, ''];
            case 'renombrar_actividad':
                $x = Actividad::find($a['actividad_id']);
                if (!$x) return [false, 'La actividad ya no existe'];
                $x->nombre = $a['nombre'];
                $x->guardar();
                return [true, ''];
            case 'editar_descripcion':
                $x = Actividad::find($a['actividad_id']);
                if (!$x) return [false, 'La actividad ya no existe'];
                $x->descripcion = $a['descripcion'];
                $x->guardar();
                return [true, ''];
            case 'agregar_dia_no_habil':
                $db->query('DELETE FROM calendario_festivos WHERE id = ' . (int)$a['festivo_id'] . " AND proyecto_id = $pid");
                return [true, ''];
            case 'marcar_comprado':
                $db->query("UPDATE materiales SET compra = '" . $db->real_escape_string($a['compra']) . "' WHERE id = " . (int)$a['material_id'] . " AND proyecto_id = $pid");
                return [true, ''];
            case 'resolver_restriccion':
                $db->query("UPDATE restricciones SET estado = '" . $db->real_escape_string($a['estado']) . "' WHERE id = " . (int)$a['restriccion_id']);
                return [true, ''];
            case 'agregar_dependencia':
                if ($a['previa']) $db->query("UPDATE actividad_dependencias SET tipo = '" . $db->real_escape_string($a['previa']['tipo']) . "', retraso = " . (int)$a['previa']['retraso'] . " WHERE proyecto_id = $pid AND predecesora_id = " . (int)$a['pred'] . ' AND sucesora_id = ' . (int)$a['suc']);
                else $db->query("DELETE FROM actividad_dependencias WHERE proyecto_id = $pid AND predecesora_id = " . (int)$a['pred'] . ' AND sucesora_id = ' . (int)$a['suc']);
                return [true, ''];
            case 'quitar_dependencia':
                $db->query("INSERT IGNORE INTO actividad_dependencias (proyecto_id, predecesora_id, sucesora_id, tipo, retraso) VALUES ($pid, " . (int)$a['pred'] . ', ' . (int)$a['suc'] . ", '" . $db->real_escape_string($a['tipo']) . "', " . (int)$a['retraso'] . ')');
                return [true, ''];
        }
        return [false, 'No se puede revertir esta acción'];
    }
}
