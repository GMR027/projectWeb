<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Classes\Inconsistencias;
use Classes\Voz;
use Model\Proyecto;

/**
 * Comandos de voz sobre actividades: «actividad 3.2 al 80 por ciento», «renombra la 4.1 a…», «descripción de la 5.3: …».
 * Nada se cambia al dictar: cada comando entendido se vuelve una propuesta en Análisis inteligente que el admin acepta o rechaza.
 */
class ComandosVoz extends Base
{
    public static function id(): string { return 'comandos_voz'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Comandos de voz', 'grupo' => 'campo', 'tipo' => 'comandos_voz', 'aplicable' => false, 'voz' => true,
            'descripcion' => 'Dicta qué actividades actualizar: el avance, un nuevo nombre o una descripción. Cada comando entendido se convierte en una propuesta que tú aceptas o rechazas en Análisis inteligente; nada cambia al dictar. Los comandos de avance sencillos funcionan incluso sin IA.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'texto', 'etiqueta' => 'Lo dictado', 'tipo' => 'texto', 'defecto' => '', 'max' => 4000, 'voz' => true]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Comandos de voz ' . date('d/m/Y H:i');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $acts = Dependencias::actividades((int)$p->id);
        $tt = Voz::palabras(Voz::normalizar($params['texto']));
        $puntajes = [];
        foreach ($acts as $id => $a) {
            $cod = Voz::normalizar((string)$a['codigo']);
            $puntajes[$id] = (str_contains(Voz::normalizar($params['texto']), $cod) && $cod !== '' ? 5 : 0) + count(array_intersect($tt, Voz::palabras($a['nombre'])));
        }
        arsort($puntajes);
        $cand = array_slice(array_keys($puntajes), 0, 60);
        $lin = array_map(fn($id) => $acts[$id]['codigo'] . ' | ' . self::corto($acts[$id]['nombre'], 80), $cand);
        $prompt = "Conviertes lo dictado por un residente de obra en comandos sobre actividades del programa. Los números pueden venir en letras («tres punto dos» es 3.2). No inventes comandos que no se dijeron.\n\n"
            . "Acciones posibles: \"avance\" (valor = porcentaje 0 a 100), \"nombre\" (valor = nuevo nombre de la actividad), \"descripcion\" (valor = nueva descripción).\n\n"
            . "## ACTIVIDADES (código | nombre)\n" . implode("\n", $lin) . "\n\n## LO DICTADO\n\"\"\"\n" . $params['texto'] . "\n\"\"\"\n\n"
            . "Devuelve SOLO un JSON: {\"comandos\":[{\"referencia\":\"código o nombre de la actividad como se dijo\",\"accion\":\"avance|nombre|descripcion\",\"valor\":\"...\"}]}";
        return ['datos' => ['texto' => $params['texto']], 'lotes' => trim($params['texto']) === '' ? [] : [['titulo' => 'Interpretando lo dictado', 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $pid = (int)$p->id;
        $acts = Dependencias::actividades($pid);
        $cmds = [];
        foreach ((array)(self::dato($respuestas, 'comandos') ?? []) as $c) if (is_array($c)) $cmds[] = ['referencia' => (string)($c['referencia'] ?? ''), 'accion' => (string)($c['accion'] ?? ''), 'valor' => $c['valor'] ?? ''];
        $deIA = (bool)$cmds;
        if (!$cmds) $cmds = Voz::comandosSimples($datos['texto']);
        $filas = [];
        $hall = [];
        foreach ($cmds as $c) {
            [$id, $motivo] = Voz::resolverActividad($acts, $c['referencia']);
            $accion = $c['accion'];
            $a = $id ? $acts[$id] : null;
            $nom = $a ? $a['codigo'] . ' ' . self::corto($a['nombre'], 50) : '—';
            if (!$a) { $filas[] = [$c['referencia'], $accion, (string)$c['valor'], '—', 'No aplicado: ' . $motivo]; continue; }
            $h = null;
            if ($accion === 'avance') {
                $v = preg_replace('/[^0-9]/', '', (string)$c['valor']);
                if ($v === '' || (int)$v > 100) { $filas[] = [$c['referencia'], $accion, (string)$c['valor'], $nom, 'No aplicado: porcentaje no válido']; continue; }
                $h = ['titulo' => "Voz: avance de {$a['codigo']} al $v%", 'descripcion' => "Dictado: «{$c['referencia']}» → avance $v% (actual {$a['progreso']}%).", 'texto' => "Poner el avance de «{$a['codigo']}» en $v%.", 'accion' => 'ajustar_progreso', 'params' => ['actividad_id' => $id, 'progreso' => (int)$v]];
            } elseif ($accion === 'nombre' && trim((string)$c['valor']) !== '') {
                $h = ['titulo' => "Voz: renombrar {$a['codigo']}", 'descripcion' => "Dictado: nuevo nombre «" . self::corto((string)$c['valor'], 120) . "» para «" . self::corto($a['nombre'], 80) . "».", 'texto' => 'Renombrar a «' . self::corto((string)$c['valor'], 120) . '».', 'accion' => 'renombrar_actividad', 'params' => ['actividad_id' => $id, 'nombre' => (string)$c['valor']]];
            } elseif ($accion === 'descripcion' && trim((string)$c['valor']) !== '') {
                $h = ['titulo' => "Voz: descripción de {$a['codigo']}", 'descripcion' => "Dictado: nueva descripción para «" . self::corto($a['nombre'], 80) . "».", 'texto' => 'Actualizar la descripción: «' . self::corto((string)$c['valor'], 160) . '».', 'accion' => 'editar_descripcion', 'params' => ['actividad_id' => $id, 'descripcion' => (string)$c['valor']]];
            }
            if (!$h) { $filas[] = [$c['referencia'], $accion, (string)$c['valor'], $nom, 'No aplicado: comando no reconocido']; continue; }
            $hall[] = ['huella' => sha1('voz:' . uniqid('', true)), 'categoria' => 'voz', 'severidad' => 'media', 'origen' => $deIA ? 'ia' : 'sistema', 'titulo' => $h['titulo'], 'descripcion' => $h['descripcion'], 'actividades' => [$id], 'solucion' => ['texto' => $h['texto'], 'accion' => $h['accion'], 'params' => $h['params']]];
            $filas[] = [$c['referencia'], $accion, (string)$c['valor'], $nom, 'Propuesta creada'];
        }
        $n = count($hall);
        $ref = '';
        if ($hall) {
            $r = Inconsistencias::guardar($p, $hall, '', ['del_sistema' => $deIA ? 0 : $n, 'de_la_ia' => $deIA ? $n : 0]);
            Inconsistencias::marcarCorrida($pid, $r['corrida'], true, 'Comandos de voz ' . date('d/m H:i'));
            $ref = "/propuestas-ia?proyecto=$pid";
        }
        return ['secciones' => [
            Informes::texto('Resultado', $n ? "Se crearon $n propuesta(s). Nada se ha cambiado todavía: acéptalas o recházalas en Análisis inteligente ($ref)." : 'No se entendió ningún comando aplicable. Prueba con frases como «actividad 3.2 al 80 por ciento».'),
            Informes::tabla('Comandos interpretados', ['Dicho', 'Acción', 'Valor', 'Actividad', 'Resultado'], $filas),
            Informes::texto('Texto dictado', $datos['texto']),
        ]];
    }
}
