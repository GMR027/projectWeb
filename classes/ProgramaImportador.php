<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;
use Model\Subactividad;

/**
 * Importa el programa de obra desde un XML MSPDI (MS Project) o un .pod en XML.
 *
 * Niveles: 1 resumen → partida; 2 → actividad; 3 → subactividad de esa actividad.
 * Los subgrupos (nivel 3 resumen o nivel 4+) se aplanan con un aviso. Las predecesoras
 * se reconstruyen al final, cuando ya existen todos los nodos.
 */
class ProgramaImportador
{
    public const BASE = __DIR__ . '/../storage/import';
    private const COLORES = ['#FF5A1F', '#00B4D8', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444', '#6366F1', '#14B8A6', '#EC4899', '#84CC16'];

    /** Lee el archivo y guarda lo analizado; devuelve token, resumen, vista previa y avisos */
    public static function analizar(string $ruta, int $proyectoId): array
    {
        $tareas = self::leerTareas(self::cargarXml($ruta), $avisos);
        $partidas = self::agrupar($tareas, $avisos);
        if (!$partidas) throw new \Exception('El archivo no contiene tareas con fechas que se puedan importar.');

        $token = bin2hex(random_bytes(12));
        if (!is_dir(self::BASE . "/$token")) mkdir(self::BASE . "/$token", 0775, true);
        file_put_contents(self::BASE . "/$token/data.json", json_encode(['proyecto' => $proyectoId, 'partidas' => $partidas], JSON_UNESCAPED_UNICODE));

        $acts = array_sum(array_map(fn($p) => count($p['actividades']), $partidas));
        $subs = array_sum(array_map(fn($p) => array_sum(array_map(fn($a) => count($a['subactividades'] ?? []), $p['actividades'])), $partidas));
        $hitos = array_sum(array_map(fn($p) => count(array_filter($p['actividades'], fn($a) => $a['hito'])), $partidas));
        return ['token' => $token, 'partidas' => $partidas, 'avisos' => $avisos,
            'resumen' => ['partidas' => count($partidas), 'actividades' => $acts, 'subactividades' => $subs, 'hitos' => $hitos]];
    }

    public static function cargar(string $token, int $proyectoId): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) return null;
        $json = @file_get_contents(self::BASE . "/$token/data.json");
        $d = $json ? json_decode($json, true) : null;
        return $d && (int)$d['proyecto'] === $proyectoId ? $d : null;
    }

    /** Crea partidas, actividades, subactividades y sus relaciones (se suman a lo que ya tenga) */
    public static function aplicar(string $token, Proyecto $proyecto): array
    {
        $d = self::cargar($token, (int)$proyecto->id);
        if (!$d) throw new \Exception('La importación expiró o no es válida. Sube el archivo de nuevo.');
        $pid = (int)$proyecto->id;
        $calendario = new Calendario($pid);

        $codigos = array_map(fn($p) => $p->codigo, $proyecto->getPartidas());
        $orden = count($codigos);
        $nPartidas = $nActs = $nSubs = 0;
        $uidMap = [];   // uid del XML => a<id> / s<id>
        $preds = [];    // [nodo => [[uid,tipo,retraso], …]]

        foreach ($d['partidas'] as $i => $pa) {
            $codigo = self::codigoLibre($pa['codigo'], $codigos);
            $codigos[] = $codigo;
            $partida = new Partida(['proyecto_id' => $pid, 'codigo' => $codigo, 'nombre' => $pa['nombre'], 'color_bg' => self::COLORES[($orden + $i) % count(self::COLORES)], 'orden' => ++$orden]);
            $r = $partida->guardar();
            if (!$r['resultado']) continue;
            $partidaId = (int)$r['id'];
            $nPartidas++;

            $usados = [];
            foreach ($pa['actividades'] as $j => $a) {
                $cod = self::codigoLibre($a['codigo'], $usados);
                $usados[] = $cod;
                $subs = $a['subactividades'] ?? [];
                $inicio = (string)$a['inicio'];
                $fin = (string)$a['fin'];
                if (($inicio === '' || $fin === '') && $subs) {
                    $inicios = array_filter(array_map(fn($s) => (string)$s['inicio'], $subs));
                    $fines = array_filter(array_map(fn($s) => (string)$s['fin'], $subs));
                    if ($inicios) $inicio = min($inicios);
                    if ($fines) $fin = max($fines);
                }
                if ($inicio === '') continue;
                $hito = empty($subs) && (int)$a['hito'] ? 1 : 0;
                $fin = $hito ? $inicio : max($inicio, $fin !== '' ? $fin : $inicio);
                $dur = $hito ? 0 : max(1, (int)ceil($calendario->calcularDuracion($inicio, $fin)));
                $act = new Actividad([
                    'partida_id' => $partidaId, 'codigo' => $cod, 'nombre' => $a['nombre'], 'descripcion' => $a['notas'],
                    'fecha_inicio' => $inicio, 'duracion_dias' => $dur, 'fecha_fin' => $fin, 'progreso' => empty($subs) ? $a['avance'] : 0,
                    'prioridad' => $a['prioridad'], 'hito' => $hito, 'orden' => $j + 1,
                ]);
                $act->sincronizarStatus();
                $res = $act->guardar();
                if (!$res || empty($res['resultado'])) continue;
                $actId = (int)($res['id'] ?? 0);
                if ($actId <= 0) continue;
                $nActs++;
                if ($a['uid'] !== '') $uidMap[$a['uid']] = 'a' . $actId;
                if (!empty($a['preds'])) $preds['a' . $actId] = $a['preds'];

                foreach ($subs as $k => $s) {
                    $sIni = (string)$s['inicio'];
                    $sFin = (string)$s['fin'];
                    if ($sIni === '') continue;
                    $sHito = (int)$s['hito'] ? 1 : 0;
                    $sFin = $sHito ? $sIni : max($sIni, $sFin !== '' ? $sFin : $sIni);
                    $sub = new Subactividad([
                        'actividad_id' => $actId, 'nombre' => $s['nombre'], 'fecha_inicio' => $sIni, 'fecha_fin' => $sFin,
                        'progreso' => $s['avance'], 'prioridad' => $s['prioridad'], 'hito' => $sHito, 'orden' => $k + 1,
                    ]);
                    $subRes = $sub->guardar();
                    if (!$subRes || empty($subRes['resultado'])) continue;
                    $subId = (int)($subRes['id'] ?? 0);
                    if ($subId <= 0) continue;
                    $nSubs++;
                    if ($s['uid'] !== '') $uidMap[$s['uid']] = 's' . $subId;
                    if (!empty($s['preds'])) $preds['s' . $subId] = $s['preds'];
                }
                if ($subs) {
                    $act = Actividad::find($actId);
                    if ($act) \Classes\Subactividades::recalcular($act, ['propagar' => false, 'hoja' => false]);
                }
            }
            Partida::find($partidaId)?->recalcularFechas();
        }

        // Relaciones: ahora que todos los nodos existen
        foreach ($preds as $nodo => $lista) {
            foreach ($lista as $p) {
                $origen = $uidMap[$p['uid']] ?? null;
                if (!$origen || $origen === $nodo) continue;
                try {
                    \Classes\Dependencias::guardar($pid, $origen, $nodo, $p['tipo'], $p['retraso']);
                } catch (\Throwable $e) {
                    error_log('Importar programa · dependencia: ' . $e->getMessage());
                }
            }
        }

        self::limpiar($token);
        return ['partidas' => $nPartidas, 'actividades' => $nActs, 'subactividades' => $nSubs];
    }

    public static function limpiar(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) return;
        @unlink(self::BASE . "/$token/data.json");
        @rmdir(self::BASE . "/$token");
    }

    // ------------------------------------------------------------------ lectura

    /** XML (o .pod comprimido que lo contenga) → DOMDocument, sin cargar entidades externas */
    private static function cargarXml(string $ruta): \DOMDocument
    {
        $bytes = (string)file_get_contents($ruta);
        if (str_starts_with($bytes, 'PK')) {   // zip: se busca el primer XML dentro
            $zip = new \ZipArchive();
            if ($zip->open($ruta) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (preg_match('/\.xml$/i', $zip->getNameIndex($i))) { $bytes = (string)$zip->getFromIndex($i); break; }
                }
                $zip->close();
            }
        }
        $inicio = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $bytes));
        if (!str_starts_with($inicio, '<')) {
            throw new \Exception('Este archivo no es XML. Los .pod/.mpp nativos son binarios: ábrelo en ProjectLibre o MS Project y expórtalo como XML (formato MSPDI).');
        }
        if (stripos($inicio, '<!DOCTYPE') !== false || stripos($inicio, '<!ENTITY') !== false) {
            throw new \Exception('El XML contiene declaraciones no permitidas.');
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($inicio, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || !$dom->documentElement || $dom->documentElement->localName !== 'Project') {
            throw new \Exception('No es un XML de proyecto válido (MSPDI). Falta el nodo <Project>.');
        }
        return $dom;
    }

    private static function leerTareas(\DOMDocument $dom, ?array &$avisos): array
    {
        $avisos = [];
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('p', $dom->documentElement->namespaceURI ?: 'urn:none');
        $q = $dom->documentElement->namespaceURI ? 'p:' : '';
        $texto = fn(\DOMNode $n, string $c) => trim((string)$xp->evaluate("string({$q}$c)", $n));

        $out = [];
        $sinFecha = $inactivas = 0;
        $tipos = ['1' => 'FS', '2' => 'SS', '3' => 'FF', '4' => 'SF'];
        foreach ($xp->query("/{$q}Project/{$q}Tasks/{$q}Task") as $n) {
            $uid = $texto($n, 'UID');
            $nivel = (int)$texto($n, 'OutlineLevel');
            $nombre = $texto($n, 'Name');
            if ($uid === '0' || $nivel === 0 || $nombre === '') continue;
            if ($texto($n, 'Null') === '1') continue;
            if ($texto($n, 'Active') === '0') { $inactivas++; continue; }
            $resumen = $texto($n, 'Summary') === '1';
            $ini = substr($texto($n, 'Start'), 0, 10);
            $fin = substr($texto($n, 'Finish'), 0, 10);
            $preds = [];
            foreach ($xp->query("{$q}PredecessorLink", $n) as $pl) {
                $pu = $texto($pl, 'PredecessorUID');
                if ($pu === '') continue;
                $lag = (int)$texto($pl, 'LinkLag');
                $preds[] = ['uid' => $pu, 'tipo' => $tipos[$texto($pl, 'Type')] ?? 'FS', 'retraso' => (int)round($lag / 4800)];
            }
            $out[] = [
                'uid' => $uid, 'nivel' => $nivel, 'resumen' => $resumen, 'nombre' => $nombre,
                'wbs' => $texto($n, 'WBS') ?: $texto($n, 'OutlineNumber'),
                'inicio' => self::fechaValida($ini) ? $ini : '', 'fin' => self::fechaValida($fin) ? $fin : '',
                'hito' => $texto($n, 'Milestone') === '1', 'avance' => max(0, min(100, (int)$texto($n, 'PercentComplete'))),
                'prioridad' => self::prioridad((int)($texto($n, 'Priority') ?: 500)), 'notas' => mb_substr($texto($n, 'Notes'), 0, 2000),
                'preds' => $preds,
            ];
        }
        if ($inactivas) $avisos[] = "$inactivas tarea(s) inactiva(s) se omitieron.";
        if ($xp->query("/{$q}Project/{$q}Resources/{$q}Resource")->length) $avisos[] = 'Los recursos y asignaciones del archivo no se importan.';
        $avisos[] = 'Los calendarios del archivo no se importan: las fechas se conservan tal cual y la duración se recalcula con el calendario de este proyecto.';
        return $out;
    }

    /** Nivel 1 = partida; nivel 2 = actividad; nivel 3 = subactividad. Los subgrupos se aplanan. */
    private static function agrupar(array $tareas, array &$avisos): array
    {
        $partidas = [];
        $actual = null;
        $actividad = null;
        $sueltas = [];
        $aplanados = 0;
        foreach ($tareas as $t) {
            if ($t['nivel'] === 1 && $t['resumen']) {
                [$cod, $nom] = self::separarCodigo($t['nombre'], $t['wbs'] ? $t['wbs'] . '.0' : '');
                $partidas[] = ['codigo' => $cod, 'nombre' => $nom, 'actividades' => []];
                $actual = array_key_last($partidas);
                $actividad = null;
                continue;
            }
            if ($t['nivel'] === 2) {
                if ($actual === null) { $sueltas[] = $t; continue; }
                $partidas[$actual]['actividades'][] = self::actividad($t, count($partidas[$actual]['actividades']) + 1, $partidas[$actual]['codigo']);
                $actividad = array_key_last($partidas[$actual]['actividades']);
                continue;
            }
            if ($t['nivel'] === 3 && !$t['resumen']) {
                if ($actual === null || $actividad === null) { $aplanados++; continue; }
                $partidas[$actual]['actividades'][$actividad]['subactividades'][] = self::subactividad($t, count($partidas[$actual]['actividades'][$actividad]['subactividades']) + 1, $partidas[$actual]['actividades'][$actividad]['codigo']);
                continue;
            }
            // Nivel 3 resumen: se omite y sus hijos (nivel 4+) se aplanan como subactividades.
            if ($t['resumen']) { $aplanados++; continue; }
            if ($actual !== null && $actividad !== null) {
                $partidas[$actual]['actividades'][$actividad]['subactividades'][] = self::subactividad($t, count($partidas[$actual]['actividades'][$actividad]['subactividades']) + 1, $partidas[$actual]['actividades'][$actividad]['codigo']);
                if ($t['nivel'] > 3) $aplanados++;
            } else {
                $sueltas[] = $t;
            }
        }
        if ($sueltas) {
            $partidas[] = ['codigo' => '', 'nombre' => 'Actividades importadas', 'actividades' => array_map(fn($t, $i) => self::actividad($t, $i + 1, ''), $sueltas, array_keys($sueltas))];
        }
        if ($aplanados) $avisos[] = "$aplanados subgrupo(s) de nivel 3 resumen o nivel 4+ se aplanaron dentro de su actividad.";
        foreach ($partidas as $i => &$p) if (!$p['codigo']) $p['codigo'] = ($i + 1) . '.0';
        unset($p);
        return array_values(array_filter($partidas, fn($p) => $p['actividades']));
    }

    private static function actividad(array $t, int $n, string $codPartida): array
    {
        $base = $codPartida ? preg_replace('/\.0$/', '', $codPartida) : '';
        [$cod, $nom] = self::separarCodigo($t['nombre'], $base ? "$base.$n" : (string)$n);
        return ['codigo' => $cod, 'nombre' => mb_substr($nom, 0, 200), 'inicio' => $t['inicio'], 'fin' => $t['fin'], 'hito' => $t['hito'],
            'avance' => $t['avance'], 'prioridad' => $t['prioridad'], 'notas' => $t['notas'], 'uid' => $t['uid'], 'preds' => $t['preds'], 'subactividades' => []];
    }

    private static function subactividad(array $t, int $n, string $codActividad): array
    {
        [$cod, $nom] = self::separarCodigo($t['nombre'], $codActividad . '.' . $n);
        return ['codigo' => $cod, 'nombre' => mb_substr($nom, 0, 200), 'inicio' => $t['inicio'], 'fin' => $t['fin'], 'hito' => $t['hito'],
            'avance' => $t['avance'], 'prioridad' => $t['prioridad'], 'notas' => $t['notas'], 'uid' => $t['uid'], 'preds' => $t['preds']];
    }

    /** "1.2 - Excavación" → ['1.2', 'Excavación'] (así exporta este sistema); si no hay código usa el de respaldo */
    private static function separarCodigo(string $nombre, string $respaldo): array
    {
        if (preg_match('/^(\d+(?:\.\d+)*)\s+-\s+(.+)$/su', $nombre, $m)) return [mb_substr($m[1], 0, 50), $m[2]];
        return [$respaldo, $nombre];
    }

    private static function codigoLibre(string $codigo, array $usados): string
    {
        $base = $codigo !== '' ? $codigo : '1';
        $c = $base;
        for ($i = 2; in_array($c, $usados, true); $i++) $c = "$base-$i";
        return mb_substr($c, 0, 50);
    }

    private static function fechaValida(string $f): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $f);
        return $d && $d->format('Y-m-d') === $f;
    }

    private static function prioridad(int $p): string
    {
        return $p >= 900 ? 'critica' : ($p >= 700 ? 'alta' : ($p >= 400 ? 'media' : 'baja'));
    }
}
