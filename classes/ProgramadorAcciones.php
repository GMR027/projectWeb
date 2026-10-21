<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;

/**
 * Lo que se hace con una propuesta del Programador: impacto por partida, aceptarla por partida (y deshacerlo), compararla con otra,
 * seguimiento de obstáculos y mejoras, efecto en la Curva S y exportación. Solo aceptar/deshacer tocan `actividades` y sus predecesoras.
 */
class ProgramadorAcciones
{
    public const ESTADOS = ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'atendido' => 'Atendido', 'descartado' => 'Descartado'];

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function dias(string $a, string $b): int
    {
        return (int)round((strtotime($b) - strtotime($a)) / 86400);
    }

    private static function json(?string $t): array
    {
        return $t ? (json_decode($t, true) ?: []) : [];
    }

    private static function columna(int $propuestaId, string $col): array
    {
        $r = self::db()->query("SELECT $col FROM programador_propuestas WHERE id = $propuestaId");
        $f = $r ? $r->fetch_row() : null;
        return self::json($f[0] ?? null);
    }

    private static function escribir(int $proyectoId, int $propuestaId, string $col, array $datos): void
    {
        $db = self::db();
        $db->query("UPDATE programador_propuestas SET $col = '" . $db->real_escape_string(json_encode($datos, JSON_UNESCAPED_UNICODE)) . "' WHERE id = $propuestaId AND proyecto_id = $proyectoId");
    }

    // ---------- impacto por partida ----------

    /** Una fila por partida: cuántas actividades cambian, fines, ruta crítica, obstáculos, mejoras y si ya se aceptó */
    public static function impacto(Proyecto $p, array $propuesta): array
    {
        $pid = (int)$p->id;
        $vivas = Dependencias::actividades($pid);
        $aceptadas = self::columna((int)$propuesta['id'], 'aceptadas_json');
        $res = [];
        $porAct = [];
        foreach ($propuesta['filas'] as $f) {
            if (!isset($vivas[$f['id']])) continue;
            $v = $vivas[$f['id']];
            $k = (int)$v['partida_id'];
            $porAct[$f['id']] = $k;
            $res[$k] ??= ['id' => $k, 'nombre' => $v['partida_codigo'] . ' · ' . $v['partida'], 'actividades' => 0, 'cambian' => 0, 'criticas' => 0, 'obstaculos' => 0, 'mejoras' => 0,
                'fin_actual' => $f['actual']['fin'], 'fin_propuesto' => $f['propuesto']['fin'], 'aplicables' => 0, 'aceptada' => $aceptadas[$k] ?? null];
            $x = &$res[$k];
            $x['actividades']++;
            if ($f['cambia']) $x['cambian']++;
            if ($f['critica']) $x['criticas']++;
            if ($f['actual']['fin'] > $x['fin_actual']) $x['fin_actual'] = $f['actual']['fin'];
            if ($f['propuesto']['fin'] > $x['fin_propuesto']) $x['fin_propuesto'] = $f['propuesto']['fin'];
            unset($x);
        }
        foreach (($propuesta['resumen']['obstaculos'] ?? []) as $o) foreach (array_unique(array_map(fn($i) => $porAct[$i] ?? null, $o['actividades'])) as $k) if ($k !== null) $res[$k]['obstaculos']++;
        foreach (($propuesta['resumen']['mejoras'] ?? []) as $m) foreach (array_unique(array_map(fn($i) => $porAct[$i] ?? null, $m['actividades'])) as $k) if ($k !== null) $res[$k]['mejoras']++;
        $rels = self::relacionesVivas($pid);
        foreach ($res as $k => &$x) {
            $x['delta'] = self::dias($x['fin_actual'], $x['fin_propuesto']);
            if (!$x['aceptada']) $x['aplicables'] = count(self::cambiosPartida($propuesta['filas'], $vivas, $rels, $k)['actividades']);
        }
        unset($x);
        return array_values($res);
    }

    private static function relacionesVivas(int $pid): array
    {
        $out = [];
        foreach (Dependencias::relaciones($pid) as $r) $out[$r['suc']][] = ['pred' => $r['pred'], 'tipo' => $r['tipo'], 'retraso' => $r['retraso']];
        return $out;
    }

    private static function clavesRel(array $l): array
    {
        $k = array_map(fn($r) => $r['pred'] . $r['tipo'] . $r['retraso'], $l);
        sort($k);
        return $k;
    }

    /**
     * Qué se escribiría en el programa al aceptar una partida: solo actividades sin avance ni fecha fija y cuyo programa actual aún
     * coincide con el que vio la IA. [actividades => [id => [...]], omitidas => [textos]]
     */
    private static function cambiosPartida(array $filas, array $vivas, array $relsVivas, int $partidaId): array
    {
        $acts = [];
        $omitidas = [];
        foreach ($filas as $f) {
            $v = $vivas[$f['id']] ?? null;
            if (!$v || (int)$v['partida_id'] !== $partidaId || $f['bloqueada']) continue;
            if ((int)$v['progreso'] > 0 || (int)$v['fecha_fija']) continue;
            $cambiaFechas = $v['fecha_inicio'] !== $f['propuesto']['inicio'] || $v['fecha_fin'] !== $f['propuesto']['fin'] || (int)$v['duracion_dias'] !== (int)$f['propuesto']['dur'];
            $preds = array_map(fn($d) => ['pred' => $d['id'], 'tipo' => $d['tipo'], 'retraso' => $d['retraso']], $f['predecesoras']);
            $cambiaRels = self::clavesRel($preds) !== self::clavesRel($relsVivas[$f['id']] ?? []);
            if (!$cambiaFechas && !$cambiaRels) continue;
            if ($cambiaFechas && ($v['fecha_inicio'] !== $f['actual']['inicio'] || $v['fecha_fin'] !== $f['actual']['fin'])) {
                $omitidas[] = $f['codigo'] . ': el programa cambió después de la propuesta';
                continue;
            }
            $acts[$f['id']] = ['id' => $f['id'], 'codigo' => $f['codigo'], 'inicio' => $f['propuesto']['inicio'], 'fin' => $f['propuesto']['fin'], 'dur' => (int)$f['propuesto']['dur'],
                'fechas' => $cambiaFechas, 'relaciones' => $cambiaRels ? $preds : null];
        }
        return ['actividades' => $acts, 'omitidas' => $omitidas];
    }

    // ---------- aceptar / deshacer ----------

    /** @return array{0: bool, 1: string} */
    public static function aceptarPartida(Proyecto $p, int $propuestaId, int $partidaId): array
    {
        $pid = (int)$p->id;
        $prop = Programador::obtener($pid, $propuestaId);
        if (!$prop) return [false, 'Propuesta no encontrada'];
        $aceptadas = self::columna($propuestaId, 'aceptadas_json');
        if (isset($aceptadas[$partidaId])) return [false, 'Esa partida ya se aceptó'];
        $partida = Partida::find($partidaId);
        if (!$partida || (int)$partida->proyecto_id !== $pid) return [false, 'Partida no válida'];

        $vivas = Dependencias::actividades($pid);
        $rels = self::relacionesVivas($pid);
        $plan = self::cambiosPartida($prop['filas'], $vivas, $rels, $partidaId);
        if (!$plan['actividades']) return [false, 'No hay cambios que aplicar en esta partida' . ($plan['omitidas'] ? ' (' . implode('; ', array_slice($plan['omitidas'], 0, 3)) . ')' : '')];

        $db = self::db();
        $respaldo = ['actividades' => [], 'relaciones' => []];
        $db->begin_transaction();
        try {
            foreach ($plan['actividades'] as $id => $c) {
                $v = $vivas[$id];
                $respaldo['actividades'][] = ['id' => $id, 'inicio' => $v['fecha_inicio'], 'fin' => $v['fecha_fin'], 'dur' => (int)$v['duracion_dias']];
                if ($c['fechas']) {
                    $a = Actividad::find($id);
                    $a->fecha_inicio = $c['inicio'];
                    $a->fecha_fin = $c['fin'];
                    $a->duracion_dias = $c['dur'];
                    $a->guardar();
                }
            }
            // Predecesoras: primero se quitan las de todas las afectadas y luego se agregan (así el orden no provoca falsos ciclos)
            $conRel = array_filter($plan['actividades'], fn($c) => $c['relaciones'] !== null);
            foreach ($conRel as $id => $c) {
                $respaldo['relaciones'][$id] = $rels[$id] ?? [];
                $db->query("DELETE FROM actividad_dependencias WHERE proyecto_id = $pid AND sucesora_id = " . (int)$id);
            }
            $avisos = $plan['omitidas'];
            foreach ($conRel as $id => $c) {
                foreach ($c['relaciones'] as $d) {
                    [$ok, $msg] = Dependencias::guardar($pid, $d['pred'], $id, $d['tipo'], $d['retraso']);
                    if (!$ok) $avisos[] = $c['codigo'] . ': predecesora omitida (' . $msg . ')';
                }
            }
            $partida->recalcularFechas();
            try {
                \Model\ActividadMaterial::sincronizarInstalacion($pid);
            } catch (\Throwable $e) {
            }
            $db->query("INSERT INTO programador_respaldos (proyecto_id, propuesta_id, partida_id, datos_json) VALUES ($pid, $propuestaId, $partidaId, '" . $db->real_escape_string(json_encode($respaldo)) . "')");
            $aceptadas[$partidaId] = ['fecha' => date('Y-m-d H:i:s'), 'actividades' => count($plan['actividades']), 'respaldo' => (int)$db->insert_id, 'usuario' => usuario_actual()['nombre'] ?? '', 'avisos' => array_slice($avisos, 0, 10)];
            self::escribir($pid, $propuestaId, 'aceptadas_json', $aceptadas);
            // Una propuesta con partidas aceptadas no debe rotar
            $db->query("UPDATE programador_propuestas SET guardada = 1, nombre = IF(nombre = '', '" . $db->real_escape_string('Propuesta ' . date('d/m/Y', strtotime($prop['creado']))) . "', nombre) WHERE id = $propuestaId");
            $db->commit();
            Auditoria::registrar($pid, 'programador', 'aplicado', 'aceptar_partida', count($plan['actividades']) . ' actividad(es) de «' . $partida->nombre . '» actualizadas desde la propuesta #' . $propuestaId, $respaldo,
                ['actividades' => array_map(fn($c) => ['id' => $c['id'], 'inicio' => $c['inicio'], 'fin' => $c['fin'], 'dur' => $c['dur']], array_values($plan['actividades']))], $propuestaId, (string)$prop['modelo'], ['partida', $partidaId, $partida->codigo . ' ' . $partida->nombre]);
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo aplicar: ' . $e->getMessage()];
        }
        return [true, count($plan['actividades']) . ' actividad(es) de «' . $partida->nombre . '» actualizadas en el programa' . ($avisos ? ' · ' . count($avisos) . ' aviso(s)' : '')];
    }

    /** Regresa la partida al estado en que estaba antes de aceptarla */
    public static function deshacerPartida(Proyecto $p, int $propuestaId, int $partidaId): array
    {
        $pid = (int)$p->id;
        $aceptadas = self::columna($propuestaId, 'aceptadas_json');
        $rid = (int)($aceptadas[$partidaId]['respaldo'] ?? 0);
        $r = self::db()->query("SELECT datos_json FROM programador_respaldos WHERE id = $rid AND proyecto_id = $pid AND deshecho = 0");
        $f = $r ? $r->fetch_row() : null;
        if (!$f) return [false, 'No hay respaldo para deshacer'];
        $dat = self::json($f[0]);
        $db = self::db();
        $db->begin_transaction();
        try {
            foreach ($dat['actividades'] ?? [] as $x) {
                $a = Actividad::find($x['id']);
                if (!$a) continue;
                $a->fecha_inicio = $x['inicio'];
                $a->fecha_fin = $x['fin'];
                $a->duracion_dias = $x['dur'];
                $a->guardar();
            }
            foreach ($dat['relaciones'] ?? [] as $suc => $lista) {
                $db->query("DELETE FROM actividad_dependencias WHERE proyecto_id = $pid AND sucesora_id = " . (int)$suc);
                foreach ($lista as $d) {
                    $db->query("INSERT IGNORE INTO actividad_dependencias (proyecto_id, predecesora_id, sucesora_id, tipo, retraso) VALUES ($pid, " . (int)$d['pred'] . ', ' . (int)$suc . ", '" . $db->real_escape_string($d['tipo']) . "', " . (int)$d['retraso'] . ')');
                }
            }
            if ($partida = Partida::find($partidaId)) $partida->recalcularFechas();
            try {
                \Model\ActividadMaterial::sincronizarInstalacion($pid);
            } catch (\Throwable $e) {
            }
            $db->query("UPDATE programador_respaldos SET deshecho = 1 WHERE id = $rid");
            unset($aceptadas[$partidaId]);
            self::escribir($pid, $propuestaId, 'aceptadas_json', $aceptadas);
            $db->commit();
            Auditoria::registrar($pid, 'programador', 'revertido', 'aceptar_partida', 'Partida regresada a su programa anterior (propuesta #' . $propuestaId . ')', $dat, null, $propuestaId, '', ['partida', $partidaId, ($partida->codigo ?? '') . ' ' . ($partida->nombre ?? '')]);
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo deshacer: ' . $e->getMessage()];
        }
        return [true, 'La partida volvió a su programa anterior'];
    }

    // ---------- comparar dos propuestas ----------

    public static function comparar(array $a, array $b): array
    {
        $fb = array_column($b['filas'], null, 'id');
        $filas = [];
        $partidas = [];
        foreach ($a['filas'] as $x) {
            $y = $fb[$x['id']] ?? null;
            if (!$y) continue;
            $dif = $x['propuesto']['inicio'] !== $y['propuesto']['inicio'] || $x['propuesto']['fin'] !== $y['propuesto']['fin'];
            $d = self::dias($x['propuesto']['fin'], $y['propuesto']['fin']);
            $q = &$partidas[$x['partida']];
            $q ??= ['partida' => $x['partida'], 'fin_a' => $x['propuesto']['fin'], 'fin_b' => $y['propuesto']['fin'], 'difieren' => 0];
            if ($x['propuesto']['fin'] > $q['fin_a']) $q['fin_a'] = $x['propuesto']['fin'];
            if ($y['propuesto']['fin'] > $q['fin_b']) $q['fin_b'] = $y['propuesto']['fin'];
            if ($dif) $q['difieren']++;
            unset($q);
            if ($dif) $filas[] = ['codigo' => $x['codigo'], 'nombre' => $x['nombre'], 'partida' => $x['partida'], 'a' => $x['propuesto'], 'b' => $y['propuesto'], 'delta' => $d];
        }
        usort($filas, fn($m, $n) => abs($n['delta']) <=> abs($m['delta']));
        foreach ($partidas as &$q) $q['delta'] = self::dias($q['fin_a'], $q['fin_b']);
        unset($q);
        $ra = $a['resumen'];
        $rb = $b['resumen'];
        return [
            'resumen' => [
                ['Fin propuesto', $ra['fin_propuesto'], $rb['fin_propuesto']], ['Días hábiles vs. actual', $ra['dif_dias_habiles'], $rb['dif_dias_habiles']],
                ['Actividades que cambian', $ra['movidas'], $rb['movidas']], ['Relaciones nuevas', $ra['relaciones_nuevas'], $rb['relaciones_nuevas']],
                ['Obstáculos', count($ra['obstaculos'] ?? []), count($rb['obstaculos'] ?? [])], ['Mejoras sugeridas', count($ra['mejoras'] ?? []), count($rb['mejoras'] ?? [])],
            ],
            'partidas' => array_values(array_filter($partidas, fn($q) => $q['difieren'] > 0)), 'filas' => array_slice($filas, 0, 80), 'total_difieren' => count($filas),
        ];
    }

    // ---------- seguimiento de obstáculos y mejoras ----------

    public static function seguimiento(int $propuestaId): array
    {
        return self::columna($propuestaId, 'seguimiento_json');
    }

    public static function marcarSeguimiento(int $proyectoId, int $propuestaId, string $clave, string $estado, string $responsable): bool
    {
        if (!preg_match('/^[om]\d{1,3}$/', $clave) || !isset(self::ESTADOS[$estado]) || !Programador::obtener($proyectoId, $propuestaId)) return false;
        $s = self::seguimiento($propuestaId);
        $s[$clave] = ['estado' => $estado, 'responsable' => mb_substr(trim($responsable), 0, 80), 'fecha' => date('Y-m-d')];
        self::escribir($proyectoId, $propuestaId, 'seguimiento_json', $s);
        return true;
    }

    // ---------- Curva S ----------

    /** Curva S del programa actual, de la propuesta y la real, en las mismas semanas */
    public static function curva(Proyecto $p, array $filas): array
    {
        $alt = [];
        foreach ($filas as $f) $alt[$f['id']] = [$f['propuesto']['inicio'], $f['propuesto']['fin']];
        $c = CurvaS::calcular($p, $alt);
        return $c['hayDatos'] ? $c['periodos'] : [];
    }

    // ---------- exportación ----------

    private static function tablas(Proyecto $p, array $prop): array
    {
        $r = $prop['resumen'];
        $codigos = array_column($prop['filas'], 'codigo', 'id');
        $lista = fn(array $ids) => implode(', ', array_map(fn($i) => $codigos[$i] ?? $i, $ids));
        $seg = self::seguimiento((int)$prop['id']);
        $est = fn($k) => self::ESTADOS[$seg[$k]['estado'] ?? 'pendiente'];
        $resp = fn($k) => $seg[$k]['responsable'] ?? '';
        $t = [];
        $t['Resumen'] = [['Concepto', 'Valor'], ['Proyecto', $p->nombre], ['Propuesta', ($prop['nombre'] ?? '') ?: $prop['modelo']], ['Generada', $prop['creado']], ['Fin actual', $r['fin_actual']], ['Fin propuesto', $r['fin_propuesto']],
            ['Días hábiles de diferencia', $r['dif_dias_habiles']], ['Actividades que cambian', $r['movidas']], ['Relaciones nuevas', $r['relaciones_nuevas']], ['Obstáculos', count($r['obstaculos'])], ['Mejoras sugeridas', count($r['mejoras'] ?? [])]];
        $t['Por partida'] = [['Partida', 'Actividades', 'Cambian', 'Fin actual', 'Fin propuesto', 'Δ días', 'Ruta crítica', 'Obstáculos', 'Mejoras', 'Aceptada']];
        foreach (self::impacto($p, $prop) as $x) $t['Por partida'][] = [$x['nombre'], $x['actividades'], $x['cambian'], $x['fin_actual'], $x['fin_propuesto'], $x['delta'], $x['criticas'], $x['obstaculos'], $x['mejoras'], $x['aceptada'] ? substr($x['aceptada']['fecha'], 0, 10) : ''];
        $t['Actividades'] = [['Código', 'Actividad', 'Partida', 'Tipo', 'Inicio actual', 'Fin actual', 'Inicio propuesto', 'Fin propuesto', 'Δ fin (d)', 'Holgura (d)', 'Crítica', 'Justificación']];
        foreach ($prop['filas'] as $f) $t['Actividades'][] = [$f['codigo'], $f['nombre'], $f['partida'], $f['tipo'] ?? '', $f['actual']['inicio'], $f['actual']['fin'], $f['propuesto']['inicio'], $f['propuesto']['fin'], $f['delta_dias'], $f['holgura'], $f['critica'] ? 'Sí' : '', $f['justificacion']];
        $t['Obstáculos'] = [['Severidad', 'Fuente', 'Actividades', 'Descripción', 'Recomendación', 'Detectado por', 'Estado', 'Responsable']];
        foreach ($r['obstaculos'] as $i => $o) $t['Obstáculos'][] = [$o['severidad'], $o['fuente'], $lista($o['actividades']), $o['descripcion'], $o['recomendacion'], $o['origen'] === 'ia' ? 'IA' : 'Sistema', $est("o$i"), $resp("o$i")];
        $t['Mejoras'] = [['Severidad', 'Categoría', 'Actividades', 'Hallazgo', 'Mejora', 'Estado', 'Responsable']];
        foreach ($r['mejoras'] ?? [] as $i => $m) $t['Mejoras'][] = [$m['severidad'], $m['categoria'], $lista($m['actividades']), $m['hallazgo'], $m['mejora'], $est("m$i"), $resp("m$i")];
        return $t;
    }

    public static function xlsx(Proyecto $p, array $prop): string
    {
        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $libro->removeSheetByIndex(0);
        foreach (self::tablas($p, $prop) as $nombre => $filas) {
            $h = $libro->createSheet();
            $h->setTitle($nombre);
            $h->fromArray($filas, null, 'A1');
            $h->getStyle('A1:' . $h->getHighestColumn() . '1')->getFont()->setBold(true);
            foreach (range('A', $h->getHighestColumn()) as $col) $h->getColumnDimension($col)->setAutoSize(true);
            $h->freezePane('A2');
        }
        $libro->setActiveSheetIndex(0);
        $tmp = tempnam(sys_get_temp_dir(), 'prg');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($tmp);
        $bin = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    public static function pdf(Proyecto $p, array $prop): string
    {
        $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $tablas = self::tablas($p, $prop);
        $cambian = [$tablas['Actividades'][0]];
        foreach (array_slice($tablas['Actividades'], 1) as $f) if ($f[8] != 0 || $f[4] !== $f[6]) $cambian[] = $f;
        $tablas['Actividades'] = array_slice($cambian, 0, 200);
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:8px;color:#111}h1{font-size:15px}h2{font-size:11px;margin:14px 0 4px}table{width:100%;border-collapse:collapse;margin-bottom:6px}th,td{border:1px solid #bbb;padding:2px 4px;text-align:left;vertical-align:top}th{background:#e8ecf1}</style></head><body>'
            . '<h1>Programador de actividades · ' . $h($p->nombre) . '</h1>';
        foreach ($tablas as $nombre => $filas) {
            if (count($filas) < 2 && $nombre !== 'Resumen') continue;
            $html .= '<h2>' . $h($nombre === 'Actividades' ? 'Actividades que cambian' : $nombre) . '</h2><table>';
            foreach ($filas as $i => $f) $html .= '<tr>' . implode('', array_map(fn($c) => ($i === 0 ? '<th>' : '<td>') . $h($c) . ($i === 0 ? '</th>' : '</td>'), $f)) . '</tr>';
            $html .= '</table>';
        }
        return Pdf::generar($html . '</body></html>', 'letter', 'landscape');
    }
}
