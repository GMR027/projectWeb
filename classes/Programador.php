<?php

namespace Classes;

use Model\Proyecto;

/**
 * Programador de actividades (IA): arma el contexto que analiza el modelo y convierte su respuesta en un programa PROPUESTO.
 *
 * La IA solo decide la estructura (clasificación, predecesoras, paralelismos, a qué actividades adelantar) y señala obstáculos.
 * Las fechas las calcula siempre el motor de calendario y dependencias (RutaCritica). Nada de esto toca `actividades`.
 */
class Programador
{
    use AccesoDb;

    public const PAQUETE_MIN = 10;        // las partidas pequeñas se juntan hasta tener al menos esto
    public const PAQUETE_MAX = 22;        // las partidas grandes se parten en paquetes de a lo más esto
    public const PAQUETE_CARACTERES = 11000;
    private const ADMIN = '/\b(tr[aá]mite|permiso|licencia|autorizaci[oó]n|aprobaci[oó]n|submittal|compra|adquisici[oó]n|contrataci[oó]n|cotizaci[oó]n|licitaci[oó]n|ingenier[ií]a|planos?|dise[nñ]o|estudio|dictamen|certificaci[oó]n|fianza|seguro|entrega de documentaci[oó]n|memoria|catalogo|cat[aá]logo|conciliaci[oó]n|estimaci[oó]n|programa de|gesti[oó]n|solicitud|revisi[oó]n de)\b/iu';
    private const SEVERIDADES = ['baja' => 1, 'media' => 2, 'alta' => 3, 'critica' => 4];
    private const FUENTES = ['material', 'submittal', 'bitacora', 'reporte', 'restriccion', 'otro'];
    private const ACCIONES = ['mantener', 'adelantar'];
    public const CATEGORIAS_MEJORA = ['inconsistencia', 'relacion_faltante', 'duracion', 'riesgo', 'dato_faltante', 'oportunidad'];

    // ---------- contexto ----------

    /** Todo lo que el modelo necesita saber del proyecto (solo actividades; las partidas son separadores) */
    public static function contexto(Proyecto $p, ?string $hoy = null): array
    {
        $pid = (int)$p->id;
        $hoy ??= date('Y-m-d');
        $acts = Dependencias::actividades($pid);
        $extra = [];
        foreach (self::filas("SELECT a.id, a.descripcion, a.prioridad, a.sin_material FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid") as $f) $extra[(int)$f['id']] = $f;

        $mats = [];
        foreach (self::filas(
            "SELECT am.actividad_id, m.id, m.nombre, m.compra, m.fecha_compra_prog, m.fecha_entrega_prog, m.fecha_entrega_real,
                    (SELECT s.estatus FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE sm.material_id = m.id
                     ORDER BY (s.estatus IN ('aprobado','aprobado_an')) DESC, s.fecha DESC LIMIT 1) AS submittal
             FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id WHERE m.proyecto_id = $pid"
        ) as $f) $mats[(int)$f['actividad_id']][] = $f;

        $restr = [];
        foreach (self::filas("SELECT r.actividad_id, r.descripcion, r.fecha, t.nombre AS tipo FROM restricciones r JOIN tipos_restriccion t ON t.id = r.tipo_id JOIN actividades a ON a.id = r.actividad_id JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND r.estado = 'activa'") as $f) $restr[(int)$f['actividad_id']][] = $f;

        $preds = [];
        foreach (Dependencias::relaciones($pid) as $r) $preds[$r['suc']][] = ['id' => $r['pred'], 'tipo' => $r['tipo'], 'retraso' => $r['retraso']];

        $lista = [];
        foreach ($acts as $id => $a) {
            $m = [];
            foreach ($mats[$id] ?? [] as $x) {
                $m[] = ['nombre' => self::corto($x['nombre'], 80), 'compra' => $x['compra'], 'compra_prog' => $x['fecha_compra_prog'], 'entrega_prog' => $x['fecha_entrega_prog'], 'entrega_real' => $x['fecha_entrega_real'], 'submittal' => $x['submittal']];
            }
            $lista[$id] = [
                'id' => $id, 'codigo' => $a['codigo'], 'partida' => $a['partida_codigo'] . ' ' . $a['partida'], 'nombre' => $a['nombre'],
                'descripcion' => self::corto($extra[$id]['descripcion'] ?? '', 200), 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'dur' => (int)$a['duracion_dias'],
                'hito' => (int)$a['hito'], 'progreso' => (int)$a['progreso'], 'prioridad' => $extra[$id]['prioridad'] ?? 'media', 'fija' => (int)$a['fecha_fija'],
                'sin_material' => (int)($extra[$id]['sin_material'] ?? 0), 'predecesoras' => $preds[$id] ?? [], 'materiales' => $m,
                'restricciones' => array_map(fn($r) => ['tipo' => $r['tipo'], 'descripcion' => self::corto($r['descripcion'], 160), 'fecha' => $r['fecha']], $restr[$id] ?? []),
            ];
        }

        $bitacora = array_map(fn($f) => ['fecha' => $f['fecha'], 'depto' => $f['departamento'], 'importancia' => $f['importancia'], 'texto' => self::corto($f['descripcion'], 320)],
            self::filas("SELECT fecha, departamento, importancia, descripcion FROM bitacora WHERE proyecto_id = $pid ORDER BY FIELD(importancia,'critica','alta','media','baja'), fecha DESC LIMIT 40"));
        $reportes = [];
        foreach (self::filas("SELECT fecha, area, comentarios, actividades_json FROM reportes_diarios WHERE proyecto_id = $pid AND comentarios <> '' ORDER BY fecha DESC LIMIT 15") as $f) {
            $reportes[] = ['fecha' => $f['fecha'], 'area' => $f['area'], 'texto' => self::corto($f['comentarios'], 320), 'actividades' => array_values(array_filter((array)json_decode((string)$f['actividades_json'], true), 'is_int'))];
        }

        $cal = new Calendario($pid);
        $festivos = array_column(self::filas("SELECT fecha FROM calendario_festivos WHERE proyecto_id = $pid AND fecha >= '$hoy' ORDER BY fecha LIMIT 40"), 'fecha');
        $jornada = [];
        for ($d = 1; $d <= 7; $d++) $jornada[$d] = $cal->getFactor(date('Y-m-d', strtotime("next monday +" . ($d - 1) . " day")));

        return [
            'proyecto' => ['id' => $pid, 'nombre' => $p->nombre, 'inicio' => $p->fecha_inicio, 'fin' => $p->fecha_fin], 'hoy' => $hoy,
            'jornada_lun_a_dom' => array_values($jornada), 'festivos_proximos' => $festivos,
            'actividades' => $lista, 'bitacora' => $bitacora, 'reportes' => $reportes,
            'obstaculos' => self::obstaculosObjetivos($lista, $hoy),
        ];
    }

    /** Obstáculos que se detectan sin IA, con los datos del propio sistema */
    public static function obstaculosObjetivos(array $acts, string $hoy): array
    {
        $out = [];
        foreach ($acts as $a) {
            if ($a['progreso'] >= 100) continue;
            foreach ($a['materiales'] as $m) {
                $entrega = $m['entrega_real'] ?: $m['entrega_prog'];
                if (!$m['entrega_real'] && $m['entrega_prog'] && $m['entrega_prog'] > $a['inicio']) {
                    $out[] = self::obst([$a['id']], 'material', $m['entrega_prog'] > $a['fin'] ? 'alta' : 'media', "«{$m['nombre']}» llega el {$m['entrega_prog']} y la actividad inicia el {$a['inicio']}", 'Adelantar la compra o mover el inicio hasta la entrega', 'sistema', $m['entrega_prog']);
                } elseif (!$entrega && $m['compra'] !== 'comprado') {
                    $out[] = self::obst([$a['id']], 'material', 'media', "«{$m['nombre']}» sin comprar y sin fecha de entrega programada", 'Programar la compra y fijar fecha de entrega', 'sistema');
                }
                if (in_array($m['submittal'], ['enviado', 'rechazado'], true) && !$m['entrega_real']) {
                    $out[] = self::obst([$a['id']], 'submittal', $m['submittal'] === 'rechazado' ? 'alta' : 'media', "Submittal de «{$m['nombre']}» " . ($m['submittal'] === 'rechazado' ? 'rechazado (hay que reenviarlo)' : 'aún en revisión'), 'Dar seguimiento a la aprobación antes de comprar', 'sistema');
                }
            }
            foreach ($a['restricciones'] as $r) {
                $out[] = self::obst([$a['id']], 'restriccion', ($r['fecha'] && $r['fecha'] < $hoy) ? 'alta' : 'media', "Restricción activa ({$r['tipo']}): {$r['descripcion']}", 'Liberar la restricción antes del inicio', 'sistema');
            }
        }
        return $out;
    }

    private static function obst(array $ids, string $fuente, string $sev, string $desc, string $rec, string $origen, ?string $fecha = null): array
    {
        return ['actividades' => $ids, 'fuente' => $fuente, 'severidad' => $sev, 'descripcion' => $desc, 'recomendacion' => $rec, 'origen' => $origen, 'fecha' => $fecha];
    }

    // ---------- fase 1 (sin IA): qué analizar y cómo empacarlo ----------

    /** Clasificación por palabras clave del nombre/descripción; la IA solo la corrige cuando se equivoca */
    public static function clasificar(array $a): string
    {
        return preg_match(self::ADMIN, $a['nombre'] . ' ' . $a['descripcion']) ? 'administrativa' : 'operativa';
    }

    /** Solo lo que aún puede cambiar: lo iniciado, terminado o los hitos no se analizan ni se mueven */
    public static function analizables(array $ctx): array
    {
        return array_filter($ctx['actividades'], fn($a) => $a['progreso'] === 0 && !$a['hito'] && !$a['fija']);
    }

    /** Línea compacta de una actividad: id|código|nombre|inicio→fin|dur|clase|pred:..|mat:..|rest:.. */
    private static function linea(array $a): string
    {
        $pr = implode(',', array_map(fn($p) => $p['id'] . ($p['tipo'] !== 'FS' ? $p['tipo'] : '') . ($p['retraso'] ? ($p['retraso'] > 0 ? '+' : '') . $p['retraso'] : ''), $a['predecesoras']));
        $m = implode(';', array_map(fn($x) => self::corto($x['nombre'], 40) . '(' . ($x['entrega_real'] ? 'entregado' : ($x['entrega_prog'] ?: 'sin fecha')) . ($x['submittal'] ? ',sub:' . $x['submittal'] : ',sin sub') . ')', $a['materiales']));
        $r = implode(';', array_map(fn($x) => self::corto($x['tipo'] . ': ' . $x['descripcion'], 60), $a['restricciones']));
        return $a['id'] . '|' . $a['codigo'] . '|' . self::corto($a['nombre'], 70) . '|' . substr($a['inicio'], 5) . '→' . substr($a['fin'], 5) . '|' . $a['dur'] . 'd|' . ($a['clase'] === 'administrativa' ? 'adm' : 'op')
            . ($pr !== '' ? '|pred:' . $pr : '') . ($m !== '' ? '|mat:' . $m : '') . ($r !== '' ? '|rest:' . $r : '');
    }

    /** Paquetes pequeños de actividades analizables, siguiendo las partidas: [['titulo','ids','actividades'=>[...]]] */
    public static function paquetes(array $ctx): array
    {
        $porPartida = [];
        foreach (self::analizables($ctx) as $a) {
            $a['clase'] = self::clasificar($a);
            $porPartida[$a['partida']][] = $a;
        }
        $paquetes = [];
        $pend = [];
        $pendNombres = [];
        $cerrar = function () use (&$paquetes, &$pend, &$pendNombres) {
            if ($pend) $paquetes[] = ['titulo' => implode(' + ', $pendNombres), 'actividades' => $pend];
            $pend = []; $pendNombres = [];
        };
        foreach ($porPartida as $nombre => $lista) {
            foreach (array_chunk($lista, self::PAQUETE_MAX) as $k => $trozo) {
                $etq = self::corto($nombre, 40) . (count($lista) > self::PAQUETE_MAX ? ' (' . ($k + 1) . ')' : '');
                if (count($trozo) >= self::PAQUETE_MIN) { $cerrar(); $paquetes[] = ['titulo' => $etq, 'actividades' => $trozo]; continue; }
                if (count($pend) + count($trozo) > self::PAQUETE_MAX) $cerrar();
                array_push($pend, ...$trozo); $pendNombres[] = $etq;
                if (count($pend) >= self::PAQUETE_MIN) $cerrar();
            }
        }
        $cerrar();
        return $paquetes;
    }

    /** Prompts por paquete (fase 2) más un resumen de paquetes para la fase de integración */
    public static function lotes(array $ctx): array
    {
        $bit = array_map(fn($b) => "{$b['fecha']} {$b['importancia']} {$b['depto']}: " . self::corto($b['texto'], 220), array_slice($ctx['bitacora'], 0, 12));
        $paquetes = self::paquetes($ctx);
        $lotes = [];
        foreach ($paquetes as $i => $pq) {
            $ids = array_column($pq['actividades'], 'id');
            $rep = [];
            foreach ($ctx['reportes'] as $r) if (array_intersect($r['actividades'], $ids)) $rep[] = "{$r['fecha']} {$r['area']}: " . self::corto($r['texto'], 220);
            $obst = [];
            foreach ($ctx['obstaculos'] as $o) if (array_intersect($o['actividades'], $ids)) $obst[] = implode(',', $o['actividades']) . ': ' . self::corto($o['descripcion'], 120);
            $prompt = self::instrucciones() . Plantillas::contexto(\Model\Proyecto::find((int)$ctx['proyecto']['id'])) . "\n\n## PAQUETE: {$pq['titulo']}\nHoy: {$ctx['hoy']} · fin del proyecto: {$ctx['proyecto']['fin']} · jornada lun-dom: " . implode(',', $ctx['jornada_lun_a_dom']) . ' · festivos próximos: ' . implode(',', array_slice($ctx['festivos_proximos'], 0, 10))
                . "\n\n## ACTIVIDADES (id|código|nombre|inicio→fin|duración|clase|pred|mat|rest)\n" . implode("\n", array_map([self::class, 'linea'], $pq['actividades']))
                . "\n\n## OBSTÁCULOS YA DETECTADOS POR EL SISTEMA (no los repitas)\n" . ($obst ? implode("\n", $obst) : '(ninguno)')
                . "\n\n## BITÁCORA RECIENTE (más importantes primero)\n" . ($bit ? implode("\n", $bit) : '(vacía)')
                . "\n\n## REPORTES DIARIOS QUE MENCIONAN ESTAS ACTIVIDADES\n" . ($rep ? implode("\n", array_slice($rep, 0, 6)) : '(ninguno)')
                . "\n\nResponde SOLO con el JSON, sin texto antes ni después.";
            $lotes[] = ['titulo' => 'Paquete ' . ($i + 1) . ' de ' . count($paquetes) . ' · ' . $pq['titulo'] . ' · ' . count($ids) . ' act.', 'prompt' => $prompt, 'ids' => $ids];
        }
        return $lotes;
    }

    /**
     * Fase 3: conecta los paquetes entre sí. Recibe solo un resumen por paquete (no las actividades completas) y lo que ya
     * propuso cada uno; devuelve el prompt o null si hay un solo paquete.
     */
    public static function integracion(array $ctx, array $respuestas): ?array
    {
        $paquetes = self::paquetes($ctx);
        if (count($paquetes) < 2) return null;
        $mov = [];
        foreach ($respuestas as $r) foreach ((array)($r['actividades'] ?? []) as $e) if (is_array($e) && isset($e['id'])) $mov[(int)$e['id']] = $e;
        $res = [];
        foreach ($paquetes as $i => $pq) {
            $a = $pq['actividades'];
            $ini = min(array_column($a, 'inicio'));
            $fin = max(array_column($a, 'fin'));
            // actividades «clave»: las que abren y cierran el paquete, más las que tienen cambios o obstáculos
            $orden = $a;
            usort($orden, fn($x, $y) => [$x['inicio'], $x['id']] <=> [$y['inicio'], $y['id']]);
            $claves = array_merge(array_slice($orden, 0, 3), array_slice($orden, -3));
            foreach ($a as $x) if (isset($mov[$x['id']])) $claves[] = $x;
            $vistas = [];
            $lineas = [];
            foreach ($claves as $x) { if (isset($vistas[$x['id']])) continue; $vistas[$x['id']] = 1; $lineas[] = $x['id'] . '|' . $x['codigo'] . '|' . self::corto($x['nombre'], 55) . '|' . substr($x['inicio'], 5) . '→' . substr($x['fin'], 5); }
            $res[] = "### P" . ($i + 1) . ' ' . $pq['titulo'] . " ($ini → $fin, " . count($a) . " act.)\n" . implode("\n", array_slice($lineas, 0, 10));
        }
        $prompt = "Eres un programador de obra experto. Ya se analizó cada paquete de actividades por separado. Ahora CONÉCTALOS: propón relaciones de precedencia ENTRE actividades de paquetes distintos (por ejemplo, la instalación de un sistema no puede empezar antes de terminar la obra civil de su zona) y señala riesgos que afecten a varios paquetes.\n\n"
            . "Reglas: usa SOLO ids de la lista; no inventes fechas; solo relaciones entre paquetes distintos que sean necesarias técnicamente (máximo 25, las más importantes); tipos FS, SS, FF, SF; \"retraso\" en días hábiles (negativo = traslape); cada una con \"motivo\" breve (máx. 120 caracteres).\n\n"
            . "## PAQUETES Y SUS ACTIVIDADES CLAVE (id|código|nombre|inicio→fin)\n" . implode("\n\n", $res)
            . "\n\nFormato (JSON estricto): {\"relaciones\":[{\"pred\":0,\"suc\":0,\"tipo\":\"FS\",\"retraso\":0,\"motivo\":\"\"}],\"obstaculos\":[{\"actividades\":[0],\"fuente\":\"otro\",\"severidad\":\"media\",\"descripcion\":\"\",\"recomendacion\":\"\"}]}\nResponde SOLO con el JSON.";
        return ['titulo' => 'Integración de ' . count($paquetes) . ' paquetes', 'prompt' => $prompt];
    }

    /**
     * Fase 4 (revisión): la IA actúa como analista de lo que ya se obtuvo de los paquetes. Calcula el programa propuesto con lo
     * respondido hasta ahora y manda un resumen compacto para que detecte inconsistencias, huecos y qué se puede mejorar.
     * No genera cambios al programa: solo produce «mejoras» que se muestran junto a la propuesta.
     */
    public static function revision(Proyecto $p, array $respuestas, ?string $hoy = null): ?array
    {
        $calc = self::construir($p, $respuestas, $hoy);
        $r = $calc['resumen'];
        $filas = array_filter($calc['filas'], fn($f) => !$f['bloqueada'] && !$f['hito']);
        if (!$filas) return null;

        $linea = function (array $f, bool $conPred = true): string {
            $pr = implode(',', array_map(fn($d) => $d['id'] . ($d['tipo'] !== 'FS' ? $d['tipo'] : '') . ($d['retraso'] ? ($d['retraso'] > 0 ? '+' : '') . $d['retraso'] : ''), $f['predecesoras']));
            return $f['id'] . '|' . $f['codigo'] . '|' . self::corto($f['nombre'], 55) . '|' . substr($f['actual']['fin'], 5) . '→' . substr($f['propuesto']['fin'], 5) . '|Δ' . $f['delta_dias'] . 'd|dur ' . $f['actual']['dur'] . '→' . $f['propuesto']['dur']
                . ($f['tipo'] ? '|' . ($f['tipo'] === 'administrativa' ? 'adm' : 'op') : '') . ($conPred && $pr !== '' ? '|pred:' . $pr : '') . ($f['justificacion'] !== '' ? '|why:' . self::corto($f['justificacion'], 90) : '');
        };

        $cambian = array_filter($filas, fn($f) => $f['cambia']);
        usort($cambian, fn($x, $y) => abs($y['delta_dias']) <=> abs($x['delta_dias']));
        $criticas = array_filter($filas, fn($f) => $f['critica']);
        $conSucesor = [];
        foreach ($calc['filas'] as $f) foreach ($f['predecesoras'] as $d) $conSucesor[$d['id']] = true;
        $aisladas = array_filter($filas, fn($f) => !$f['predecesoras'] && !isset($conSucesor[$f['id']]));
        $obst = array_slice($r['obstaculos'], 0, 15);

        $partes = [];
        $partes[] = "## RESUMEN\nFin actual {$r['fin_actual']} → propuesto {$r['fin_propuesto']} ({$r['dif_dias_habiles']} días hábiles) · fin del proyecto " . ($r['fin_proyecto'] ?: 'sin definir')
            . " · {$r['analizadas']} de {$r['actividades']} actividades con análisis de la IA · {$r['relaciones_nuevas']} relaciones nuevas · {$r['movidas']} actividades cambian · hoy {$r['hoy']}";
        if ($r['avisos']) $partes[] = "## AVISOS DEL SISTEMA\n" . implode("\n", $r['avisos']);
        $partes[] = "## ACTIVIDADES QUE MÁS CAMBIAN (id|código|nombre|fin actual→propuesto|Δ|duración|clase|pred|why)\n" . ($cambian ? implode("\n", array_map($linea, array_slice($cambian, 0, 20))) : '(ninguna)');
        $partes[] = "## RUTA CRÍTICA PROPUESTA\n" . ($criticas ? implode("\n", array_map(fn($f) => $linea($f, true), array_slice($criticas, 0, 20))) : '(sin ruta crítica calculada)');
        $partes[] = "## SIN PREDECESORAS NI SUCESORAS (" . count($aisladas) . ")\n" . ($aisladas ? implode("\n", array_map(fn($f) => $f['id'] . '|' . $f['codigo'] . '|' . self::corto($f['nombre'], 55) . '|' . substr($f['propuesto']['inicio'], 5) . '→' . substr($f['propuesto']['fin'], 5), array_slice($aisladas, 0, 15))) : '(ninguna)');
        $partes[] = "## OBSTÁCULOS YA DETECTADOS (sistema + IA)\n" . ($obst ? implode("\n", array_map(fn($o) => "{$o['severidad']}|{$o['fuente']}|" . implode(',', $o['actividades']) . '|' . self::corto($o['descripcion'], 110), $obst)) : '(ninguno)');

        $prompt = "Eres un analista senior de programas de obra. Abajo está la información que se obtuvo al analizar por paquetes un programa de obra (cambios propuestos, ruta crítica, obstáculos). NO generes un programa nuevo: REVISA críticamente esa información y dime qué se puede mejorar.\n\n"
            . "Busca, con base SOLO en los datos de abajo:\n"
            . "- inconsistencias (cambios contradictorios, una actividad adelantada que depende de algo que termina después, ruta crítica poco creíble, fin propuesto incoherente con los avisos);\n"
            . "- relaciones faltantes (actividades aisladas o con predecesoras que parecen incompletas);\n"
            . "- duraciones dudosas;\n"
            . "- riesgos que los obstáculos detectados no cubren o que están duplicados;\n"
            . "- datos que faltan en el proyecto para poder programar mejor (fechas de entrega, submittals, restricciones, avance);\n"
            . "- oportunidades de acortar la ruta crítica o paralelizar.\n\n"
            . "Reglas: máximo 15 hallazgos, los más importantes primero; usa SOLO ids de la lista (puede ir vacío si el hallazgo es general); no inventes datos ni fechas; cada \"hallazgo\" (máx. 200 caracteres) debe citar el dato que lo sustenta y cada \"mejora\" (máx. 200 caracteres) debe ser una acción concreta. "
            . "\"categoria\": " . implode(', ', self::CATEGORIAS_MEJORA) . ". \"severidad\": baja, media, alta o critica.\n\n"
            . implode("\n\n", $partes)
            . "\n\nFormato (JSON estricto; lista vacía si no hay nada que mejorar):\n{\"mejoras\":[{\"actividades\":[0],\"categoria\":\"inconsistencia\",\"severidad\":\"media\",\"hallazgo\":\"\",\"mejora\":\"\"}]}\nResponde SOLO con el JSON.";
        return ['titulo' => 'Revisión de la información obtenida', 'prompt' => $prompt];
    }

    private static function instrucciones(): string
    {
        return <<<'TXT'
Eres un programador de obra (planner) experto en construcción. Analiza este PAQUETE de actividades aún no iniciadas y propón CÓMO ejecutarlas. Tu propuesta NO reemplaza el programa vigente: es un escenario alterno que el usuario compara.

Cada actividad ya trae su clase probable (adm = administrativa: trámites, compras, submittals, aprobaciones, permisos, documentos; op = operativa: trabajo físico en obra), sus predecesoras actuales (pred), materiales con su fecha de entrega y submittal (mat) y restricciones (rest).

Tu trabajo:
1. Corrige la clase SOLO si está mal.
2. Propón cambios de estructura: predecesoras reales (no solo de orden), paralelismos y qué adelantar. Las administrativas suelen poder adelantarse en paralelo a la obra.
3. Detecta obstáculos reales con la bitácora, los reportes, los materiales y los submittals.

Reglas estrictas:
- DEVUELVE SOLO las actividades donde propones algo (cambiar clase, predecesoras, adelantar, duración o no_antes_de). Las demás se omiten: significa «mantener». Menos texto es mejor.
- NO calcules fechas: el sistema las calcula. Solo "no_antes_de" (YYYY-MM-DD) si una fuente concreta lo exige, citada en la justificación.
- Usa SOLO ids de este paquete para "id" y para predecesoras. Tipos FS, SS, FF, SF; "retraso" en días hábiles (negativo = traslape).
- "accion": "adelantar" si puede empezar en cuanto se cumplan sus predecesoras; omítela para mantener.
- "predecesoras" reemplaza a las actuales (usa [] si puede iniciar sin ellas); omítelo para conservarlas.
- "duracion_sugerida" solo con razón clara, entre la mitad y el doble de la actual.
- "justificacion" breve (máx. 150 caracteres) citando la fuente.
- Obstáculos: solo riesgos específicos, con actividad afectada y recomendación accionable. "fuente": material, submittal, bitacora, reporte, restriccion u otro. "severidad": baja, media, alta o critica.

Formato (JSON estricto; listas vacías si no hay nada):
{"actividades":[{"id":0,"tipo":"administrativa|operativa","accion":"adelantar","predecesoras":[{"id":0,"tipo":"FS","retraso":0}],"duracion_sugerida":null,"no_antes_de":null,"justificacion":""}],
 "obstaculos":[{"actividades":[0],"fuente":"bitacora","severidad":"alta","descripcion":"","recomendacion":""}]}
TXT;
    }

    // ---------- respuesta del modelo ----------

    /** Extrae el JSON de la respuesta de un modelo (con o sin ```json ... ```) o null */
    public static function extraerJson(string $texto): ?array
    {
        $texto = trim($texto);
        $j = json_decode($texto, true);
        if (is_array($j)) return $j;
        $i = strpos($texto, '{');
        $f = strrpos($texto, '}');
        if ($i === false || $f === false || $f <= $i) return null;
        $j = json_decode(substr($texto, $i, $f - $i + 1), true);
        return is_array($j) ? $j : null;
    }

    private static function fechaValida($v): ?string
    {
        if (!is_string($v)) return null;
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }

    private static function alcanza(int $desde, int $hasta, array $rels): bool
    {
        $adj = [];
        foreach ($rels as $r) $adj[$r['pred']][] = $r['suc'];
        $visto = [];
        $pila = [$desde];
        while ($pila) {
            $x = array_pop($pila);
            if ($x === $hasta) return true;
            if (isset($visto[$x])) continue;
            $visto[$x] = true;
            foreach ($adj[$x] ?? [] as $y) $pila[] = $y;
        }
        return false;
    }

    /**
     * Valida lo que respondió la IA y calcula el programa propuesto con el motor.
     * @param array $respuestas lista de arrays decodificados (uno por lote)
     * @return array{resumen: array, filas: array}
     */
    public static function construir(Proyecto $p, array $respuestas, ?string $hoy = null): array
    {
        $pid = (int)$p->id;
        $hoy ??= date('Y-m-d');
        $ctx = self::contexto($p, $hoy);
        $acts = $ctx['actividades'];
        $cal = new Calendario($pid);
        $motor = new RutaCritica($cal);
        $avisos = [];

        // 1) Lo que dijo la IA, solo de actividades del proyecto
        $ia = [];
        $obstIA = [];
        foreach ($respuestas as $r) {
            foreach ((array)($r['actividades'] ?? []) as $e) {
                $id = is_array($e) ? (int)($e['id'] ?? 0) : 0;
                if (isset($acts[$id]) && !isset($ia[$id])) $ia[$id] = $e;
            }
            foreach ((array)($r['obstaculos'] ?? []) as $o) {
                if (!is_array($o)) continue;
                $ids = array_values(array_unique(array_filter(array_map('intval', (array)($o['actividades'] ?? [])), fn($i) => isset($acts[$i]))));
                $desc = self::corto($o['descripcion'] ?? '', 400);
                if (!$ids || $desc === '') continue;
                $fuente = in_array($o['fuente'] ?? '', self::FUENTES, true) ? $o['fuente'] : 'otro';
                $sev = isset(self::SEVERIDADES[$o['severidad'] ?? '']) ? $o['severidad'] : 'media';
                $obstIA[] = self::obst($ids, $fuente, $sev, $desc, self::corto($o['recomendacion'] ?? '', 300), 'ia');
            }
        }

        // Mejoras de la fase de revisión (la IA revisó lo obtenido de los paquetes)
        $mejoras = [];
        foreach ($respuestas as $r) {
            foreach ((array)($r['mejoras'] ?? []) as $m) {
                if (!is_array($m)) continue;
                $hallazgo = self::corto($m['hallazgo'] ?? '', 300);
                if ($hallazgo === '') continue;
                $ids = array_values(array_unique(array_filter(array_map('intval', (array)($m['actividades'] ?? [])), fn($i) => isset($acts[$i]))));
                $mejoras[] = [
                    'actividades' => $ids, 'categoria' => in_array($m['categoria'] ?? '', self::CATEGORIAS_MEJORA, true) ? $m['categoria'] : 'riesgo',
                    'severidad' => isset(self::SEVERIDADES[$m['severidad'] ?? '']) ? $m['severidad'] : 'media', 'hallazgo' => $hallazgo, 'mejora' => self::corto($m['mejora'] ?? '', 300),
                ];
            }
        }
        usort($mejoras, fn($x, $y) => self::SEVERIDADES[$y['severidad']] <=> self::SEVERIDADES[$x['severidad']]);

        // 2) Red de relaciones propuesta: la de la IA reemplaza a la actual solo en las actividades donde la dio
        $reemplazo = [];
        $motivoRel = [];
        foreach ($ia as $id => $e) if (array_key_exists('predecesoras', $e) && is_array($e['predecesoras'])) $reemplazo[$id] = true;
        $rels = [];
        $n = 0;
        foreach (Dependencias::relaciones($pid) as $r) {
            if (!isset($reemplazo[$r['suc']])) $rels[] = ['id' => ++$n] + $r;
        }
        $nuevas = 0;
        $quitadas = 0;
        foreach ($reemplazo as $suc => $_) {
            $quitadas += count($acts[$suc]['predecesoras']);
            foreach ($ia[$suc]['predecesoras'] as $d) {
                $pred = is_array($d) ? (int)($d['id'] ?? 0) : 0;
                $tipo = is_array($d) && isset(Dependencias::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'FS';
                if (!isset($acts[$pred]) || $pred === $suc) { $avisos[] = "Predecesora no válida ignorada en {$acts[$suc]['codigo']}"; continue; }
                if (self::alcanza($suc, $pred, $rels)) { $avisos[] = "Se omitió {$acts[$pred]['codigo']} → {$acts[$suc]['codigo']} porque crearía un ciclo"; continue; }
                $rels[] = ['id' => ++$n, 'pred' => $pred, 'suc' => $suc, 'tipo' => $tipo, 'retraso' => max(-30, min(60, (int)($d['retraso'] ?? 0)))];
                $nuevas++;
            }
        }
        // Relaciones entre paquetes (fase de integración): se suman a las existentes
        foreach ($respuestas as $r) {
            foreach ((array)($r['relaciones'] ?? []) as $d) {
                $pred = is_array($d) ? (int)($d['pred'] ?? 0) : 0;
                $suc = is_array($d) ? (int)($d['suc'] ?? 0) : 0;
                if (!isset($acts[$pred], $acts[$suc]) || $pred === $suc) continue;
                if (array_filter($rels, fn($x) => $x['pred'] === $pred && $x['suc'] === $suc)) continue;
                if (self::alcanza($suc, $pred, $rels)) { $avisos[] = "Se omitió {$acts[$pred]['codigo']} → {$acts[$suc]['codigo']} (entre paquetes) porque crearía un ciclo"; continue; }
                $tipo = isset(Dependencias::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'FS';
                $rels[] = ['id' => ++$n, 'pred' => $pred, 'suc' => $suc, 'tipo' => $tipo, 'retraso' => max(-30, min(60, (int)($d['retraso'] ?? 0)))];
                $nuevas++;
                $motivoRel[$suc][] = self::corto($d['motivo'] ?? '', 120);
            }
        }

        // 3) Modelo para el motor: lo avanzado/terminado no se mueve; el resto parte de su "piso" y las relaciones lo empujan
        $modelo = [];
        $info = [];
        foreach ($acts as $id => $a) {
            $e = $ia[$id] ?? [];
            $bloqueada = $a['progreso'] > 0 || $a['fija'];
            $dur = $a['dur'];
            if (!$bloqueada && !$a['hito'] && isset($e['duracion_sugerida'])) {
                $s = (int)$e['duracion_sugerida'];
                if ($s >= max(1, (int)ceil($a['dur'] / 2)) && $s <= max(1, $a['dur'] * 2)) $dur = $s; else $avisos[] = "Duración sugerida fuera de rango ignorada en {$a['codigo']}";
            }
            $accion = in_array($e['accion'] ?? '', self::ACCIONES, true) ? $e['accion'] : 'mantener';
            $piso = $a['inicio'];
            $motivo = [];
            if (!$bloqueada) {
                if ($a['inicio'] < $hoy) { $piso = $motor->sig($hoy); $motivo[] = 'inicio vencido: se reubica desde hoy'; }
                if ($accion === 'adelantar') $piso = $motor->sig($hoy);
                if (($nb = self::fechaValida($e['no_antes_de'] ?? null)) && $nb > $piso) { $piso = $motor->sig($nb); $motivo[] = "no antes del $nb"; }
                foreach ($a['materiales'] as $m) {
                    if (!$m['entrega_real'] && $m['entrega_prog'] && $m['entrega_prog'] > $piso && !$a['sin_material']) { $piso = $motor->sig($m['entrega_prog']); $motivo[] = "espera material «{$m['nombre']}» ({$m['entrega_prog']})"; }
                }
            }
            $fin = $bloqueada ? $a['fin'] : ($a['hito'] ? $piso : $cal->calcularFechaFin($piso, $dur));
            $modelo[$id] = ['id' => $id, 'inicio' => $piso, 'fin' => $fin, 'dur' => $dur, 'hito' => $a['hito'], 'fija' => $bloqueada ? 1 : 0];
            $info[$id] = ['dur' => $dur, 'accion' => $accion, 'motivo' => $motivo, 'bloqueada' => $bloqueada];
        }
        $c = $motor->calcular($modelo, $rels);
        if ($c['ciclo']) { $avisos[] = 'Las relaciones propuestas formaban un ciclo; se usaron solo las fechas base'; $c['acts'] = array_map(fn($m) => ['es' => $m['inicio'], 'ef' => $m['fin'], 'holgura' => null, 'critica' => false], $modelo); }

        // 4) Filas comparativas
        $filas = [];
        $mov = $adm = $ope = 0;
        foreach ($acts as $id => $a) {
            $k = $c['acts'][$id];
            $e = $ia[$id] ?? [];
            $tipo = in_array($e['tipo'] ?? '', ['administrativa', 'operativa'], true) ? $e['tipo'] : (($a['progreso'] === 0 && !$a['hito'] && !$a['fija']) ? self::clasificar($a) : null);
            $tipo === 'administrativa' ? $adm++ : ($tipo === 'operativa' ? $ope++ : null);
            $cambia = $k['es'] !== $a['inicio'] || $k['ef'] !== $a['fin'];
            if ($cambia) $mov++;
            $predsProp = [];
            foreach ($rels as $r) if ($r['suc'] === $id) $predsProp[] = ['id' => $r['pred'], 'codigo' => $acts[$r['pred']]['codigo'], 'tipo' => $r['tipo'], 'retraso' => $r['retraso']];
            $just = self::corto($e['justificacion'] ?? '', 240);
            foreach ($motivoRel[$id] ?? [] as $mr) if ($mr !== '') $just = trim($just . ' [enlace entre paquetes: ' . $mr . ']');
            if ($info[$id]['motivo']) $just = trim($just . ' [' . implode('; ', $info[$id]['motivo']) . ']');
            $filas[] = [
                'id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'partida' => $a['partida'], 'tipo' => $tipo, 'accion' => $info[$id]['accion'], 'hito' => $a['hito'], 'progreso' => $a['progreso'],
                'actual' => ['inicio' => $a['inicio'], 'fin' => $a['fin'], 'dur' => $a['dur']],
                'propuesto' => ['inicio' => $k['es'], 'fin' => $k['ef'], 'dur' => $info[$id]['dur']],
                'delta_dias' => (int)round((strtotime($k['ef']) - strtotime($a['fin'])) / 86400), 'cambia' => $cambia,
                'holgura' => $k['holgura'], 'critica' => (bool)$k['critica'], 'predecesoras' => $predsProp, 'justificacion' => $just, 'bloqueada' => $info[$id]['bloqueada'],
            ];
        }

        $obst = array_merge($ctx['obstaculos'], $obstIA);
        usort($obst, fn($x, $y) => self::SEVERIDADES[$y['severidad']] <=> self::SEVERIDADES[$x['severidad']]);
        $finActual = $acts ? max(array_column($acts, 'fin')) : null;
        $finProp = $filas ? max(array_map(fn($f) => $f['propuesto']['fin'], $filas)) : null;
        $cont = array_count_values(array_column($obst, 'severidad'));
        if ($ia === []) $avisos[] = 'La IA no devolvió análisis de actividades: el programa propuesto solo refleja lo detectado por el sistema';
        if ($finProp && $p->fecha_fin && $finProp > $p->fecha_fin) $avisos[] = "La propuesta termina el $finProp, después de la fecha fin del proyecto ({$p->fecha_fin})";

        return [
            'resumen' => [
                'actividades' => count($acts), 'analizadas' => count($ia), 'administrativas' => $adm, 'operativas' => $ope, 'movidas' => $mov,
                'relaciones_nuevas' => $nuevas, 'relaciones_reemplazadas' => $quitadas, 'fin_actual' => $finActual, 'fin_propuesto' => $finProp,
                'dif_dias_habiles' => ($finActual && $finProp) ? $motor->difHab($finActual, $finProp) : 0, 'fin_proyecto' => $p->fecha_fin,
                'obstaculos' => $obst, 'mejoras' => array_slice($mejoras, 0, 20), 'obstaculos_por_severidad' => $cont, 'avisos' => array_values(array_unique($avisos)), 'hoy' => $hoy,
            ],
            'filas' => $filas,
        ];
    }

    // ---------- persistencia ----------

    public static function guardar(Proyecto $p, array $calculo, string $modelo): int
    {
        $db = self::db();
        $pid = (int)$p->id;
        $r = $calculo['resumen'];
        $fa = $r['fin_actual'] ? "'" . $db->real_escape_string($r['fin_actual']) . "'" : 'NULL';
        $fp = $r['fin_propuesto'] ? "'" . $db->real_escape_string($r['fin_propuesto']) . "'" : 'NULL';
        $db->query("INSERT INTO programador_propuestas (proyecto_id, modelo, fin_actual, fin_propuesto, resumen_json, filas_json) VALUES ($pid, '" . $db->real_escape_string(self::corto($modelo, 150)) . "', $fa, $fp, '"
            . $db->real_escape_string(json_encode($r, JSON_UNESCAPED_UNICODE)) . "', '" . $db->real_escape_string(json_encode($calculo['filas'], JSON_UNESCAPED_UNICODE)) . "')");
        $id = (int)$db->insert_id;
        \Classes\Revisiones::registrarEjecucion($pid, 'programador');
        // Solo se conservan las 10 últimas propuestas sin guardar por proyecto; las guardadas se quedan
        $db->query("DELETE FROM programador_propuestas WHERE proyecto_id = $pid AND guardada = 0 AND id NOT IN (SELECT id FROM (SELECT id FROM programador_propuestas WHERE proyecto_id = $pid AND guardada = 0 ORDER BY id DESC LIMIT 10) t)");
        return $id;
    }

    public static function lista(int $proyectoId): array
    {
        return self::filas("SELECT id, modelo, fin_actual, fin_propuesto, creado, guardada, nombre FROM programador_propuestas WHERE proyecto_id = $proyectoId ORDER BY id DESC");
    }

    /** Propuestas guardadas por el admin, con los totales para listarlas en el Programa */
    public static function guardadas(int $proyectoId): array
    {
        $out = [];
        foreach (self::filas("SELECT id, nombre, modelo, fin_propuesto, creado, resumen_json FROM programador_propuestas WHERE proyecto_id = $proyectoId AND guardada = 1 ORDER BY id DESC") as $f) {
            $r = json_decode($f['resumen_json'], true) ?: [];
            $out[] = ['id' => (int)$f['id'], 'nombre' => $f['nombre'], 'modelo' => $f['modelo'], 'creado' => $f['creado'], 'fin_propuesto' => $f['fin_propuesto'],
                'dif' => (int)($r['dif_dias_habiles'] ?? 0), 'movidas' => (int)($r['movidas'] ?? 0), 'obstaculos' => count($r['obstaculos'] ?? []), 'mejoras' => count($r['mejoras'] ?? [])];
        }
        return $out;
    }

    /** Guarda (con nombre) o quita de guardadas una propuesta */
    public static function marcar(int $proyectoId, int $id, bool $guardar, string $nombre = ''): void
    {
        $db = self::db();
        $g = $guardar ? 1 : 0;
        $n = $db->real_escape_string(self::corto($nombre, 150));
        $db->query("UPDATE programador_propuestas SET guardada = $g, nombre = '" . ($guardar ? $n : '') . "' WHERE id = $id AND proyecto_id = $proyectoId");
    }

    public static function obtener(int $proyectoId, int $id): ?array
    {
        $f = self::filas("SELECT * FROM programador_propuestas WHERE id = $id AND proyecto_id = $proyectoId")[0] ?? null;
        if (!$f) return null;
        return ['id' => (int)$f['id'], 'modelo' => $f['modelo'], 'guardada' => (int)$f['guardada'], 'nombre' => $f['nombre'], 'creado' => $f['creado'], 'resumen' => json_decode($f['resumen_json'], true) ?: [], 'filas' => json_decode($f['filas_json'], true) ?: []];
    }

    public static function eliminar(int $proyectoId, int $id): void
    {
        self::db()->query("DELETE FROM programador_propuestas WHERE id = $id AND proyecto_id = $proyectoId");
    }
}
