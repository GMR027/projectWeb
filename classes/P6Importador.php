<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;

/**
 * Importa el programa desde Primavera P6: XML de P6 (APIBusinessObjects) o XER.
 * Trae WBS → partidas, actividades, relaciones y avance. Es un lector determinista:
 * no usa IA ni la nube. Previsualiza antes de escribir y permite actualizar por código.
 */
class P6Importador
{
    use AccesoDb;

    public const BASE = __DIR__ . '/../storage/import';
    public const MAX_BYTES = 30 * 1024 * 1024;
    private const COLORES = ['#FF5A1F', '#00B4D8', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444', '#6366F1', '#14B8A6', '#EC4899', '#84CC16'];
    private const TIPOS_XML = ['finish to start' => 'FS', 'start to start' => 'SS', 'finish to finish' => 'FF', 'start to finish' => 'SF'];
    private const TIPOS_XER = ['PR_FS' => 'FS', 'PR_SS' => 'SS', 'PR_FF' => 'FF', 'PR_SF' => 'SF'];

    /** @return array{token:string,resumen:array,avisos:array,partidas:array,dependencias:array,opciones:array} */
    public static function analizar(string $ruta, int $proyectoId, array $opciones = []): array
    {
        $opciones = [
            'recalcular' => !isset($opciones['recalcular']) || !empty($opciones['recalcular']),
            'duplicados' => in_array($opciones['duplicados'] ?? '', ['actualizar', 'crear', 'saltar'], true) ? $opciones['duplicados'] : 'actualizar',
        ];
        $datos = self::leer($ruta);
        $avisos = $datos['avisos'];

        $calendario = new Calendario($proyectoId);
        $horasDia = max(1, (float)($datos['horas_dia'] ?: 8));
        $existentes = [];
        foreach (self::filas("SELECT a.id, a.codigo FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $proyectoId") as $f) {
            $existentes[mb_strtolower(trim((string)$f['codigo']))] = (int)$f['id'];
        }

        $partidas = [];
        $porWbs = [];
        $vistos = [];
        $nuevas = $actualizadas = $omitidas = $hitos = 0;
        foreach ($datos['actividades'] as $a) {
            $codigo = trim((string)$a['codigo']);
            if ($codigo === '') $codigo = 'P6-' . $a['id'];
            $clave = mb_strtolower($codigo);
            if (isset($vistos[$clave])) $avisos[] = 'Código repetido en el archivo: ' . $codigo . ' (se conserva el primero).';
            if (isset($vistos[$clave])) { $omitidas++; continue; }
            $vistos[$clave] = true;
            $hito = !empty($a['hito']);
            if ($hito) $hitos++;
            $duracion = $hito ? 0 : max(1, (int)round(((float)$a['horas']) / $horasDia));
            [$inicio, $fin, $duracion] = self::fechas($a, $duracion, $hito, $opciones['recalcular'], $calendario);
            if ($inicio === null) { $omitidas++; $avisos[] = 'Actividad sin fecha válida: ' . mb_substr((string)$a['nombre'], 0, 60); continue; }
            if (isset($existentes[$clave])) {
                if ($opciones['duplicados'] === 'saltar') { $omitidas++; continue; }
                if ($opciones['duplicados'] === 'actualizar') $actualizadas++;
            } else {
                $nuevas++;
            }
            $wbs = (string)$a['wbs'];
            if (!isset($porWbs[$wbs])) {
                $wbsNombre = (string)($a['wbs_nombre'] ?? '');
                $wbsCodigo = trim((string)($a['wbs_codigo'] ?? ''));
                [$pCod, $pNom] = self::codigoPartida($wbsCodigo !== '' ? $wbsCodigo : $wbs, $wbsNombre, count($partidas) + 1);
                $porWbs[$wbs] = count($partidas);
                $partidas[] = ['codigo' => $pCod, 'nombre' => $pNom, 'actividades' => []];
            }
            $partidas[$porWbs[$wbs]]['actividades'][] = [
                'id' => (string)$a['id'], 'codigo' => mb_substr($codigo, 0, 50), 'nombre' => mb_substr((string)$a['nombre'], 0, 200),
                'inicio' => $inicio, 'fin' => $fin, 'duracion' => $duracion, 'hito' => $hito,
                'avance' => max(0, min(100, (int)round((float)$a['avance']))),
                'prioridad' => self::prioridad($a['prioridad'] ?? ''),
                'notas' => mb_substr((string)($a['notas'] ?? ''), 0, 2000),
            ];
        }
        if (!$partidas) {
            if (!$datos['actividades']) throw new \Exception('El archivo no contiene actividades con fechas que se puedan importar.');
            $avisos[] = 'Con las opciones elegidas no queda ninguna actividad por importar.';
        }

        $dependencias = [];
        $idsValidos = [];
        foreach ($datos['actividades'] as $a) $idsValidos[(string)$a['id']] = true;
        $vistasDep = [];
        foreach ($datos['dependencias'] as $d) {
            if (!isset($idsValidos[(string)$d['pred']], $idsValidos[(string)$d['suc']]) || (string)$d['pred'] === (string)$d['suc']) continue;
            $clave = $d['pred'] . '>' . $d['suc'] . '>' . $d['tipo'];
            if (isset($vistasDep[$clave])) continue;
            $vistasDep[$clave] = true;
            $dependencias[] = $d;
        }
        if ($datos['relaciones_omitidas'] ?? 0) $avisos[] = (int)$datos['relaciones_omitidas'] . ' relación(es) con actividad inexistente se omitieron.';
        if ($opciones['duplicados'] === 'actualizar') $avisos[] = 'Las actividades con el mismo código se actualizarán (no se duplican).';
        if (!$opciones['recalcular']) $avisos[] = 'Se conservan las fechas del archivo tal cual (sin recalcular con el calendario del proyecto).';

        $token = bin2hex(random_bytes(12));
        if (!is_dir(self::BASE . "/$token")) mkdir(self::BASE . "/$token", 0775, true);
        $guardar = ['proyecto' => $proyectoId, 'opciones' => $opciones, 'partidas' => $partidas, 'dependencias' => $dependencias];
        file_put_contents(self::BASE . "/$token/p6.json", json_encode($guardar, JSON_UNESCAPED_UNICODE));

        return [
            'token' => $token, 'partidas' => $partidas, 'dependencias' => $dependencias, 'avisos' => array_values(array_unique($avisos)), 'opciones' => $opciones,
            'resumen' => [
                'partidas' => count($partidas),
                'actividades' => array_sum(array_map(fn($p) => count($p['actividades']), $partidas)),
                'dependencias' => count($dependencias),
                'hitos' => $hitos, 'nuevas' => $nuevas, 'actualizadas' => $actualizadas, 'omitidas' => $omitidas,
                'formato' => $datos['formato'],
            ],
        ];
    }

    /** Lectura pura del archivo (sin base de datos): la usan la vista previa y las pruebas */
    public static function leer(string $ruta): array
    {
        $bytes = (string)file_get_contents($ruta);
        $avisos = [];
        $datos = str_starts_with(ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $bytes)), '<')
            ? self::leerXml($bytes, $avisos)
            : self::leerXer($bytes, $avisos);
        $datos['avisos'] = $avisos;
        return $datos;
    }

    public static function cargar(string $token, int $proyectoId): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) return null;
        $json = @file_get_contents(self::BASE . "/$token/p6.json");
        $d = $json ? json_decode($json, true) : null;
        return $d && (int)$d['proyecto'] === $proyectoId ? $d : null;
    }

    /** Crea/actualiza partidas, actividades y dependencias. Devuelve el conteo real. */
    public static function aplicar(string $token, Proyecto $proyecto): array
    {
        $d = self::cargar($token, (int)$proyecto->id);
        if (!$d) throw new \Exception('La importación expiró o no es válida. Sube el archivo de nuevo.');
        $pid = (int)$proyecto->id;
        $opciones = $d['opciones'] ?? ['recalcular' => true, 'duplicados' => 'actualizar'];
        $db = self::db();
        $db->begin_transaction();
        $nPartidas = $nActs = $nActsAct = $nDeps = 0;
        $mapa = [];
        try {
            $partidas = [];
            foreach (self::filas("SELECT id, codigo FROM partidas WHERE proyecto_id = $pid") as $p) {
                $partidas[mb_strtolower(trim((string)$p['codigo']))] = (int)$p['id'];
            }
            $orden = count($partidas);
            $existentes = [];
            foreach (self::filas("SELECT a.id, a.codigo FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid") as $f) {
                $existentes[mb_strtolower(trim((string)$f['codigo']))] = (int)$f['id'];
            }
            Actividad::omitirCompuertaCalidad(true);
            foreach ($d['partidas'] as $i => $pa) {
                $claveP = mb_strtolower(trim((string)$pa['codigo']));
                if ($claveP !== '' && isset($partidas[$claveP])) {
                    $partidaId = $partidas[$claveP];
                } else {
                    $partida = new Partida([
                        'proyecto_id' => $pid, 'codigo' => self::codigoLibre((string)$pa['codigo'], array_keys($partidas)),
                        'nombre' => mb_substr((string)$pa['nombre'], 0, 150),
                        'color_bg' => self::COLORES[($orden + $i) % count(self::COLORES)], 'orden' => ++$orden,
                    ]);
                    $r = $partida->guardar();
                    if (empty($r['resultado'])) continue;
                    $partidaId = (int)$r['id'];
                    $partidas[mb_strtolower(trim((string)$partida->codigo))] = $partidaId;
                    $nPartidas++;
                }
                foreach ($pa['actividades'] as $j => $a) {
                    $clave = mb_strtolower(trim((string)$a['codigo']));
                    $existente = $existentes[$clave] ?? null;
                    if ($existente && $opciones['duplicados'] === 'saltar') { $mapa[(string)$a['id']] = $existente; continue; }
                    if ($existente) {
                        $act = Actividad::find($existente);
                        if (!$act) { $existente = null; }
                        else {
                            $act->nombre = $a['nombre'];
                            $act->fecha_inicio = $a['inicio'];
                            $act->duracion_dias = $a['hito'] ? 0 : max(1, (int)$a['duracion']);
                            $act->fecha_fin = $a['fin'];
                            $act->progreso = (int)$a['avance'];
                            $act->hito = $a['hito'] ? 1 : 0;
                            $act->prioridad = $a['prioridad'];
                            if ($a['notas'] !== '') $act->descripcion = $a['notas'];
                            $act->sincronizarStatus();
                            $act->guardar();
                            $mapa[(string)$a['id']] = $existente;
                            $nActsAct++;
                            continue;
                        }
                    }
                    $act = new Actividad([
                        'partida_id' => $partidaId, 'codigo' => self::codigoLibre((string)$a['codigo'], array_keys($existentes)),
                        'nombre' => $a['nombre'], 'descripcion' => $a['notas'], 'fecha_inicio' => $a['inicio'],
                        'duracion_dias' => $a['hito'] ? 0 : max(1, (int)$a['duracion']), 'fecha_fin' => $a['fin'],
                        'progreso' => (int)$a['avance'], 'prioridad' => $a['prioridad'], 'hito' => $a['hito'] ? 1 : 0, 'orden' => $j + 1,
                    ]);
                    $act->sincronizarStatus();
                    $r = $act->guardar();
                    if (empty($r['resultado'])) continue;
                    $mapa[(string)$a['id']] = (int)$r['id'];
                    $existentes[mb_strtolower(trim((string)$act->codigo))] = (int)$r['id'];
                    $nActs++;
                }
                Partida::find($partidaId)?->recalcularFechas();
            }
            foreach ($d['dependencias'] as $dep) {
                $pred = $mapa[(string)$dep['pred']] ?? null;
                $suc = $mapa[(string)$dep['suc']] ?? null;
                if (!$pred || !$suc || $pred === $suc) continue;
                $tipo = in_array($dep['tipo'], ['FS', 'SS', 'FF', 'SF'], true) ? $dep['tipo'] : 'FS';
                $retraso = (int)($dep['retraso'] ?? 0);
                $db->query("INSERT IGNORE INTO actividad_dependencias (proyecto_id, predecesora_id, sucesora_id, tipo, retraso) VALUES ($pid, $pred, $suc, '$tipo', $retraso)");
                if ($db->affected_rows > 0) $nDeps++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        } finally {
            Actividad::omitirCompuertaCalidad(false);
        }
        self::limpiar($token);
        return ['partidas' => $nPartidas, 'actividades' => $nActs, 'actualizadas' => $nActsAct, 'dependencias' => $nDeps];
    }

    public static function limpiar(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) return;
        @unlink(self::BASE . "/$token/p6.json");
        @rmdir(self::BASE . "/$token");
    }

    // ---------------------------------------------------------------- lectura XML

    private static function leerXml(string $bytes, array &$avisos): array
    {
        $inicio = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $bytes));
        if (stripos($inicio, '<!DOCTYPE') !== false || stripos($inicio, '<!ENTITY') !== false) {
            throw new \Exception('El XML contiene declaraciones no permitidas.');
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($inicio, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || !$dom->documentElement) throw new \Exception('No se pudo leer el XML de P6.');
        $raiz = strtolower($dom->documentElement->localName);
        if (!in_array($raiz, ['apibusinessobjects', 'project'], true)) {
            throw new \Exception('No parece un XML de Primavera P6 (falta <APIBusinessObjects>).');
        }
        $xp = new \DOMXPath($dom);
        $hijo = function (\DOMNode $n, string $campo): string {
            foreach ($n->childNodes as $c) {
                if ($c instanceof \DOMElement && strcasecmp($c->localName, $campo) === 0) return trim($c->textContent);
            }
            return '';
        };
        $horasDia = 8.0;
        foreach ($xp->query("//*[local-name()='HoursPerDay']") as $h) {
            $v = (float)trim($h->textContent);
            if ($v > 0 && $v <= 24) { $horasDia = $v; break; }
        }
        $wbs = [];
        foreach ($xp->query("//*[local-name()='WBS'][*[local-name()='ObjectId']]") as $n) {
            $id = $hijo($n, 'ObjectId');
            if ($id === '') continue;
            $wbs[$id] = ['nombre' => $hijo($n, 'Name') ?: $hijo($n, 'Code'), 'codigo' => $hijo($n, 'Code')];
        }
        $actividades = [];
        $omitidas = 0;
        foreach ($xp->query("//*[local-name()='Activity'][*[local-name()='ObjectId']]") as $n) {
            $tipo = $hijo($n, 'Type');
            if (stripos($tipo, 'WBS Summary') !== false) { $omitidas++; continue; }
            $id = $hijo($n, 'ObjectId');
            if ($id === '') continue;
            $wbsId = $hijo($n, 'WBSObjectId');
            $actividades[] = [
                'id' => $id, 'codigo' => $hijo($n, 'Id'), 'nombre' => $hijo($n, 'Name') ?: $hijo($n, 'Id'),
                'wbs' => $wbsId, 'wbs_nombre' => $wbs[$wbsId]['nombre'] ?? '', 'wbs_codigo' => $wbs[$wbsId]['codigo'] ?? '',
                'inicio' => $hijo($n, 'StartDate') ?: $hijo($n, 'ActualStartDate'),
                'fin' => $hijo($n, 'FinishDate') ?: $hijo($n, 'ActualFinishDate'),
                'horas' => (float)($hijo($n, 'PlannedDuration') ?: $hijo($n, 'AtCompletionDuration')),
                'avance' => (float)($hijo($n, 'PercentComplete') ?: 0),
                'prioridad' => $hijo($n, 'Priority') ?: $hijo($n, 'PriorityType'),
                'notas' => $hijo($n, 'Notes'),
                'hito' => stripos($tipo, 'Milestone') !== false || $hijo($n, 'Milestone') === '1',
            ];
        }
        $dependencias = [];
        $relOmitidas = 0;
        foreach ($xp->query("//*[local-name()='Relationship'][*[local-name()='ObjectId']]") as $n) {
            $pred = $hijo($n, 'PredecessorActivityObjectId');
            $suc = $hijo($n, 'SuccessorActivityObjectId');
            $tipo = self::TIPOS_XML[strtolower($hijo($n, 'Type'))] ?? null;
            if ($pred === '' || $suc === '' || !$tipo) { $relOmitidas++; continue; }
            $lag = (float)($hijo($n, 'Lag') ?: 0);
            $dependencias[] = ['pred' => $pred, 'suc' => $suc, 'tipo' => $tipo, 'retraso' => (int)round($lag / $horasDia)];
        }
        if ($omitidas) $avisos[] = "$omitidas actividad(es) de resumen WBS se omitieron.";
        if (!$actividades) throw new \Exception('El XML de P6 no contiene actividades.');
        return ['formato' => 'XML de P6', 'horas_dia' => $horasDia, 'actividades' => $actividades, 'dependencias' => $dependencias, 'relaciones_omitidas' => $relOmitidas];
    }

    // ---------------------------------------------------------------- lectura XER

    private static function leerXer(string $bytes, array &$avisos): array
    {
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $bytes);
        if (!preg_match('/ERMHDR|%T\s+TASK/s', $texto)) throw new \Exception('El archivo no parece un XER de Primavera P6.');
        $tablas = [];
        $actual = null;
        $campos = [];
        foreach (preg_split('/\r\n|\n|\r/', $texto) as $linea) {
            if ($linea === '' || $linea[0] !== '%') continue;
            $partes = explode("\t", $linea);
            $marca = $partes[0];
            if ($marca === '%T') { $actual = strtoupper(trim($partes[1] ?? '')); $campos = []; $tablas[$actual] = []; continue; }
            if ($marca === '%F') { $campos = array_map('strtolower', array_slice($partes, 1)); continue; }
            if ($marca === '%R' && $actual !== null) {
                $valores = array_slice($partes, 1);
                if ($campos && count($campos) === count($valores)) $tablas[$actual][] = array_combine($campos, $valores);
                elseif (!$campos) $tablas[$actual][] = $valores;
                continue;
            }
        }
        $horasDia = 8.0;
        foreach ($tablas['PROJECT'] ?? [] as $p) {
            $v = (float)($p['dayhr_cnt'] ?? 0);
            if ($v > 0 && $v <= 24) { $horasDia = $v; break; }
        }
        if ($horasDia === 8.0) {
            foreach ($tablas['CALENDAR'] ?? [] as $c) {
                $v = (float)($c['day_hr_cnt'] ?? 0);
                if ($v > 0 && $v <= 24) { $horasDia = $v; break; }
            }
        }
        $wbs = [];
        foreach ($tablas['WBS'] ?? [] as $w) {
            $id = (string)($w['wbs_id'] ?? '');
            if ($id === '') continue;
            $wbs[$id] = ['nombre' => trim((string)($w['wbs_name'] ?? '')) ?: trim((string)($w['wbs_short_name'] ?? '')), 'codigo' => trim((string)($w['wbs_short_name'] ?? ''))];
        }
        $actividades = [];
        $omitidas = 0;
        foreach ($tablas['TASK'] ?? [] as $t) {
            $tipoT = strtoupper((string)($t['task_type'] ?? ''));
            if ($tipoT === 'TT_WBS') { $omitidas++; continue; }
            $id = (string)($t['task_id'] ?? '');
            if ($id === '') continue;
            $wbsId = (string)($t['wbs_id'] ?? '');
            $mile = in_array($tipoT, ['TT_MILE', 'TT_FINMILE'], true);
            $actividades[] = [
                'id' => $id, 'codigo' => (string)($t['task_code'] ?? ''), 'nombre' => (string)($t['task_name'] ?? '') ?: (string)($t['task_code'] ?? ''),
                'wbs' => $wbsId, 'wbs_nombre' => $wbs[$wbsId]['nombre'] ?? '', 'wbs_codigo' => $wbs[$wbsId]['codigo'] ?? '',
                'inicio' => (string)($t['target_start_date'] ?? '') ?: (string)($t['act_start_date'] ?? ''),
                'fin' => (string)($t['target_end_date'] ?? '') ?: (string)($t['act_end_date'] ?? ''),
                'horas' => (float)($t['target_drtn_hr_cnt'] ?? 0),
                'avance' => (float)($t['phys_complete_pct'] ?? 0),
                'prioridad' => (string)($t['priority_type'] ?? ''),
                'notas' => (string)($t['task_notes'] ?? ''),
                'hito' => $mile,
            ];
        }
        $dependencias = [];
        $relOmitidas = 0;
        foreach ($tablas['TASKPRED'] ?? [] as $d) {
            $pred = (string)($d['pred_task_id'] ?? '');
            $suc = (string)($d['succ_task_id'] ?? '');
            $tipo = self::TIPOS_XER[strtoupper((string)($d['pred_type'] ?? ''))] ?? null;
            if ($pred === '' || $suc === '' || !$tipo) { $relOmitidas++; continue; }
            $lag = (float)($d['lag_hr_cnt'] ?? 0);
            $dependencias[] = ['pred' => $pred, 'suc' => $suc, 'tipo' => $tipo, 'retraso' => (int)round($lag / $horasDia)];
        }
        if ($omitidas) $avisos[] = "$omitidas actividad(es) de resumen WBS se omitieron.";
        if (!$actividades) throw new \Exception('El XER no contiene actividades.');
        return ['formato' => 'XER de P6', 'horas_dia' => $horasDia, 'actividades' => $actividades, 'dependencias' => $dependencias, 'relaciones_omitidas' => $relOmitidas];
    }

    // ---------------------------------------------------------------- utilidades

    private static function fechas(array $a, int $duracion, bool $hito, bool $recalcular, Calendario $cal): array
    {
        $inicio = self::fecha($a['inicio'] ?? '');
        $fin = self::fecha($a['fin'] ?? '');
        if ($inicio === null) return [null, null, $duracion];
        if ($recalcular) {
            for ($i = 0; $i < 400 && !$cal->esHabil($inicio); $i++) $inicio = date('Y-m-d', strtotime($inicio . ' +1 day'));
            $fin = $hito ? $inicio : $cal->calcularFechaFin($inicio, max(1, $duracion));
        } elseif ($fin === null || $fin < $inicio) {
            $fin = $hito ? $inicio : $cal->calcularFechaFin($inicio, max(1, $duracion));
        }
        return [$inicio, $fin, $duracion];
    }

    private static function fecha(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') return null;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            $d = \DateTime::createFromFormat('Y-m-d', $m[1]);
            return $d && $d->format('Y-m-d') === $m[1] ? $m[1] : null;
        }
        foreach (['d/m/Y H:i', 'd/m/Y', 'm/d/Y'] as $f) {
            $d = \DateTime::createFromFormat($f, $s);
            if ($d) return $d->format('Y-m-d');
        }
        return null;
    }

    private static function codigoPartida(string $wbs, string $nombre, int $n): array
    {
        $nombre = trim($nombre) ?: 'WBS ' . $wbs;
        $codigo = trim((string)($wbs ?? ''));
        if (preg_match('/^(\d+(?:\.\d+)*)\s*[-–]\s*(.+)$/u', $nombre, $m)) return [mb_substr($m[1], 0, 50), trim($m[2])];
        return [$codigo !== '' ? mb_substr($codigo, 0, 50) : $n . '.0', mb_substr($nombre, 0, 150)];
    }

    private static function codigoLibre(string $codigo, array $usadosKeys): string
    {
        $usados = array_map(fn($c) => mb_strtolower(trim((string)$c)), $usadosKeys);
        $base = trim($codigo) !== '' ? trim($codigo) : '1';
        $c = $base;
        for ($i = 2; in_array(mb_strtolower($c), $usados, true); $i++) $c = "$base-$i";
        return mb_substr($c, 0, 50);
    }

    public static function prioridad($p): string
    {
        if (!is_numeric($p)) {
            $t = mb_strtolower((string)$p);
            if (str_contains($t, 'top') || str_contains($t, 'crit')) return 'critica';
            if (str_contains($t, 'high') || str_contains($t, 'alta')) return 'alta';
            if (str_contains($t, 'low') || str_contains($t, 'baja')) return 'baja';
            return 'media';
        }
        $p = (int)$p;
        if ($p >= 900) return 'critica';
        if ($p >= 700) return 'alta';
        if ($p >= 400) return 'media';
        return 'baja';
    }
}
