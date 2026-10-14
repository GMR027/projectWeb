<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;

/**
 * Importa el programa de obra desde un XML en formato MSPDI (MS Project) o un .pod en XML (el que genera este sistema).
 * Es un lector determinista (sin IA): solo se traen las columnas que coinciden —nombre, fechas, % de avance, hito, prioridad,
 * notas y la estructura por niveles—. Predecesoras, recursos, costos y calendarios del archivo no se importan.
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
        $hitos = array_sum(array_map(fn($p) => count(array_filter($p['actividades'], fn($a) => $a['hito'])), $partidas));
        return ['token' => $token, 'partidas' => $partidas, 'avisos' => $avisos, 'resumen' => ['partidas' => count($partidas), 'actividades' => $acts, 'hitos' => $hitos]];
    }

    public static function cargar(string $token, int $proyectoId): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) return null;
        $json = @file_get_contents(self::BASE . "/$token/data.json");
        $d = $json ? json_decode($json, true) : null;
        return $d && (int)$d['proyecto'] === $proyectoId ? $d : null;
    }

    /** Crea partidas y actividades en el proyecto (se suman a lo que ya tenga) */
    public static function aplicar(string $token, Proyecto $proyecto): array
    {
        $d = self::cargar($token, (int)$proyecto->id);
        if (!$d) throw new \Exception('La importación expiró o no es válida. Sube el archivo de nuevo.');
        $pid = (int)$proyecto->id;
        $calendario = new Calendario($pid);

        $codigos = array_map(fn($p) => $p->codigo, $proyecto->getPartidas());
        $orden = count($codigos);
        $nPartidas = $nActs = 0;

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
                $hito = (int)$a['hito'];
                $fin = $hito ? $a['inicio'] : max($a['inicio'], $a['fin']);
                $dur = $hito ? 0 : max(1, (int)ceil($calendario->calcularDuracion($a['inicio'], $fin)));
                $act = new Actividad([
                    'partida_id' => $partidaId, 'codigo' => $cod, 'nombre' => $a['nombre'], 'descripcion' => $a['notas'],
                    'fecha_inicio' => $a['inicio'], 'duracion_dias' => $dur, 'fecha_fin' => $fin, 'progreso' => $a['avance'],
                    'prioridad' => $a['prioridad'], 'hito' => $hito, 'orden' => $j + 1,
                ]);
                $act->sincronizarStatus();
                if ($act->guardar()['resultado']) $nActs++;
            }
            Partida::find($partidaId)?->recalcularFechas();
        }
        self::limpiar($token);
        return ['partidas' => $nPartidas, 'actividades' => $nActs];
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
        foreach ($xp->query("/{$q}Project/{$q}Tasks/{$q}Task") as $n) {
            $uid = $texto($n, 'UID');
            $nivel = (int)$texto($n, 'OutlineLevel');
            $nombre = $texto($n, 'Name');
            if ($uid === '0' || $nivel === 0 || $nombre === '') continue;           // tarea resumen del proyecto o vacía
            if ($texto($n, 'Null') === '1') continue;
            if ($texto($n, 'Active') === '0') { $inactivas++; continue; }
            $resumen = $texto($n, 'Summary') === '1';
            $ini = substr($texto($n, 'Start'), 0, 10);
            $fin = substr($texto($n, 'Finish'), 0, 10);
            if (!$resumen && !self::fechaValida($ini)) { $sinFecha++; continue; }
            $out[] = [
                'nivel' => $nivel, 'resumen' => $resumen, 'nombre' => $nombre, 'wbs' => $texto($n, 'WBS') ?: $texto($n, 'OutlineNumber'),
                'inicio' => $ini, 'fin' => self::fechaValida($fin) ? $fin : $ini,
                'hito' => $texto($n, 'Milestone') === '1', 'avance' => max(0, min(100, (int)$texto($n, 'PercentComplete'))),
                'prioridad' => self::prioridad((int)($texto($n, 'Priority') ?: 500)), 'notas' => mb_substr($texto($n, 'Notes'), 0, 2000),
            ];
        }
        if ($sinFecha) $avisos[] = "$sinFecha tarea(s) sin fecha de inicio válida se omitieron.";
        if ($inactivas) $avisos[] = "$inactivas tarea(s) inactiva(s) se omitieron.";
        if ($xp->query("/{$q}Project/{$q}Tasks/{$q}Task/{$q}PredecessorLink")->length) $avisos[] = 'Las dependencias (predecesoras) del archivo no se importan.';
        if ($xp->query("/{$q}Project/{$q}Resources/{$q}Resource")->length) $avisos[] = 'Los recursos y asignaciones del archivo no se importan.';
        $avisos[] = 'Los calendarios del archivo no se importan: las fechas se conservan tal cual y la duración se recalcula con el calendario de este proyecto.';
        return $out;
    }

    /** Nivel 1 = partida; niveles 2+ no resumen = actividades; las tareas sueltas de nivel 1 van a «Actividades importadas» */
    private static function agrupar(array $tareas, array &$avisos): array
    {
        $partidas = [];
        $actual = null;
        $sueltas = [];
        $aplanados = 0;
        foreach ($tareas as $t) {
            if ($t['nivel'] === 1 && $t['resumen']) {
                [$cod, $nom] = self::separarCodigo($t['nombre'], $t['wbs'] ? $t['wbs'] . '.0' : '');
                $partidas[] = ['codigo' => $cod, 'nombre' => $nom, 'actividades' => []];
                $actual = array_key_last($partidas);
                continue;
            }
            if ($t['resumen']) { $aplanados++; continue; }    // subgrupos: se aplanan dentro de su partida
            if ($t['nivel'] === 1 || $actual === null) { $sueltas[] = $t; continue; }
            $partidas[$actual]['actividades'][] = self::actividad($t, count($partidas[$actual]['actividades']) + 1, $partidas[$actual]['codigo']);
        }
        if ($sueltas) {
            $partidas[] = ['codigo' => '', 'nombre' => 'Actividades importadas', 'actividades' => array_map(fn($t, $i) => self::actividad($t, $i + 1, ''), $sueltas, array_keys($sueltas))];
        }
        if ($aplanados) $avisos[] = "$aplanados subgrupo(s) de nivel 2 o más se aplanaron: sus actividades quedan directamente en la partida.";
        foreach ($partidas as $i => &$p) if (!$p['codigo']) $p['codigo'] = ($i + 1) . '.0';
        unset($p);
        return array_values(array_filter($partidas, fn($p) => $p['actividades']));
    }

    private static function actividad(array $t, int $n, string $codPartida): array
    {
        $base = $codPartida ? preg_replace('/\.0$/', '', $codPartida) : '';
        [$cod, $nom] = self::separarCodigo($t['nombre'], $base ? "$base.$n" : (string)$n);
        return ['codigo' => $cod, 'nombre' => mb_substr($nom, 0, 200), 'inicio' => $t['inicio'], 'fin' => $t['fin'], 'hito' => $t['hito'],
            'avance' => $t['avance'], 'prioridad' => $t['prioridad'], 'notas' => $t['notas']];
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
