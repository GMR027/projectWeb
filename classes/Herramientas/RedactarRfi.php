<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Classes\Normativa;
use Classes\Rfi;
use Classes\Voz;
use Model\Proyecto;

/**
 * Redactar un RFI (Fase 5.4): a partir de lo que ve el residente («el muro del eje 3 choca con la bajada de aire») redacta una solicitud de
 * información formal con asunto, antecedentes y pregunta clara. El servidor aporta lo verificable: la actividad afectada y sus fechas, sus sucesoras,
 * los materiales vinculados, las cláusulas de normativa relacionadas y la fecha límite sugerida para no frenar la obra; la IA solo redacta.
 * Al aprobar el informe se crea el RFI (estado «abierto») en el módulo de RFI. Sin IA se arma con una plantilla.
 */
class RedactarRfi extends Base
{
    public static function id(): string { return 'redactar_rfi'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Redactar un RFI', 'grupo' => 'campo', 'tipo' => 'rfi', 'aplicable' => true, 'voz' => true,
            'descripcion' => 'Describe el problema con tus palabras (o dictándolo) y elige la actividad afectada: la IA redacta la solicitud de información con asunto, antecedentes y pregunta clara, y el sistema agrega las fechas, las sucesoras y la normativa relacionada. Al aprobar el informe se crea el RFI. Sin IA se arma con plantilla.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'medio'],
            'parametros' => [
                ['nombre' => 'problema', 'etiqueta' => 'Qué pasa o qué necesitas que te definan', 'tipo' => 'texto', 'defecto' => '', 'max' => 3000, 'voz' => true],
                ['nombre' => 'actividad_id', 'etiqueta' => 'Actividad afectada (id, 0 = ninguna)', 'tipo' => 'numero', 'defecto' => 0, 'min' => 0, 'max' => 99999999],
                ['nombre' => 'prioridad', 'etiqueta' => 'Prioridad', 'tipo' => 'opcion', 'defecto' => 'media', 'opciones' => Rfi::PRIORIDADES],
                ['nombre' => 'responsable', 'etiqueta' => 'A quién va dirigido (opcional)', 'tipo' => 'texto', 'defecto' => '', 'max' => 150],
            ]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'RFI · ' . self::corto((string)($params['problema'] ?? ''), 60);
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $problema = trim((string)$params['problema']);
        $acts = Dependencias::actividades($pid);
        $a = $acts[(int)$params['actividad_id']] ?? null;
        $ctx = ['actividad' => null, 'sucesoras' => [], 'materiales' => [], 'normativa' => [], 'abiertos' => [], 'fecha_limite' => null];
        if ($a) {
            $ctx['actividad'] = ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'partida' => $a['partida'], 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'progreso' => (int)$a['progreso']];
            foreach (Dependencias::relaciones($pid) as $r) if ($r['pred'] === (int)$a['id'] && isset($acts[$r['suc']])) $ctx['sucesoras'][] = $acts[$r['suc']]['codigo'] . ' ' . self::corto($acts[$r['suc']]['nombre'], 50);
            $ctx['materiales'] = array_column(self::filas("SELECT m.nombre FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id WHERE am.actividad_id = " . (int)$a['id'] . " AND m.proyecto_id = $pid LIMIT 6"), 'nombre');
            $ctx['abiertos'] = array_column(self::filas("SELECT numero, asunto FROM rfis WHERE proyecto_id = $pid AND actividad_id = " . (int)$a['id'] . " AND estado = 'abierto' LIMIT 5"), 'asunto', 'numero');
            // Para no frenar la actividad: una semana antes de que empiece (mínimo, dentro de 2 días)
            $lim = date('Y-m-d', strtotime($a['fecha_inicio'] . ' -7 days'));
            $ctx['fecha_limite'] = max($lim, date('Y-m-d', strtotime("$hoy +2 days")));
            if ($ctx['fecha_limite'] > $a['fecha_fin']) $ctx['fecha_limite'] = date('Y-m-d', strtotime("$hoy +2 days"));
        }
        foreach (Normativa::buscar($pid, Voz::palabras($problema . ' ' . ($a['nombre'] ?? '')), 3) as $f) $ctx['normativa'][] = ['doc' => trim(($f['codigo'] ? $f['codigo'] . ' · ' : '') . $f['doc']), 'pagina' => (int)$f['pagina'], 'clausula' => $f['clausula'], 'texto' => self::corto($f['texto'], 260)];
        $ctx['problema'] = $problema;

        $prompt = "Eres residente de obra y redactas una solicitud de información (RFI) formal, breve y clara para el proyectista o el cliente. Usa SOLO los datos dados; no inventes planos, cláusulas, cifras ni fechas que no estén. Si faltan datos, pide lo que falta en la pregunta.\n\n"
            . "Proyecto: {$p->nombre}\n## PROBLEMA\n\"\"\"\n$problema\n\"\"\"\n"
            . ($a ? "\n## ACTIVIDAD AFECTADA\n{$a['codigo']} {$a['nombre']} (partida {$a['partida']}), programada del " . self::fmt($a['fecha_inicio']) . ' al ' . self::fmt($a['fecha_fin']) . ", avance {$a['progreso']} %\n"
                . ($ctx['sucesoras'] ? 'Le siguen: ' . implode('; ', array_slice($ctx['sucesoras'], 0, 4)) . "\n" : '') . ($ctx['materiales'] ? 'Materiales: ' . implode(', ', $ctx['materiales']) . "\n" : '') : '')
            . ($ctx['normativa'] ? "\n## NORMATIVA RELACIONADA (del proyecto)\n" . implode("\n", array_map(fn($n) => "- {$n['doc']}, pág. {$n['pagina']}" . ($n['clausula'] ? ", cláusula {$n['clausula']}" : '') . ': ' . $n['texto'], $ctx['normativa'])) . "\n" : '')
            . "\nDevuelve SOLO un JSON: {\"asunto\":\"máx. 100 caracteres\",\"antecedentes\":\"2 o 3 frases con el contexto\",\"pregunta\":\"2 a 4 frases, pide una definición concreta y una fecha de respuesta\"}";
        return ['datos' => $ctx, 'lotes' => $problema === '' ? [] : [['titulo' => 'Redactando el RFI', 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $a = $datos['actividad'];
        $asunto = Informes::limpiarTexto(self::dato($respuestas, 'asunto') ?? '', 100);
        $antec = Informes::limpiarTexto(self::dato($respuestas, 'antecedentes') ?? '', 900);
        $preg = Informes::limpiarTexto(self::dato($respuestas, 'pregunta') ?? '', 1500);
        $ia = $asunto !== '' && $preg !== '';
        if (!$ia) {   // plantilla sin IA
            $asunto = self::corto(preg_split('/[.\n]/u', $datos['problema'])[0] ?: $datos['problema'], 100);
            $antec = $a ? "Durante la ejecución de la actividad {$a['codigo']} «{$a['nombre']}» (programada del " . self::fmt($a['inicio']) . ' al ' . self::fmt($a['fin']) . ') se detectó lo siguiente: ' . $datos['problema'] : 'Se detectó lo siguiente en obra: ' . $datos['problema'];
            $preg = 'Se solicita su definición o aclaración para poder continuar' . ($datos['fecha_limite'] ? ' (se requiere respuesta a más tardar el ' . self::fmt($datos['fecha_limite']) . ')' : '') . '.';
        }
        $cuerpo = trim(($antec !== '' ? "Antecedentes: $antec\n\n" : '') . "Pregunta: $preg");
        $items = [
            'asunto' => $asunto, 'pregunta' => $cuerpo, 'prioridad' => (string)$params['prioridad'], 'responsable' => (string)$params['responsable'],
            'fecha_limite' => $datos['fecha_limite'], 'actividad_id' => $a ? (int)$a['id'] : 0,
        ];
        $secs = [
            Informes::kpis('RFI', [['Prioridad', \Classes\Rfi::PRIORIDADES[$params['prioridad']] ?? $params['prioridad']], ['Respuesta requerida para', $datos['fecha_limite'] ? self::fmt($datos['fecha_limite']) : 'sin fecha'], ['Redactado con', $ia ? 'IA' : 'plantilla']]),
            Informes::texto('Asunto', $asunto),
            Informes::texto('Texto del RFI' . ($ia ? ' (redactado con IA, revísalo)' : ' (plantilla)'), $cuerpo),
        ];
        if ($a) $secs[] = Informes::lista('Contexto que agrega el sistema', array_values(array_filter([
            "Actividad: {$a['codigo']} {$a['nombre']} · {$a['partida']} · " . self::fmt($a['inicio']) . ' a ' . self::fmt($a['fin']) . " · avance {$a['progreso']} %",
            $datos['sucesoras'] ? 'Si no se responde a tiempo se retrasan: ' . implode('; ', array_slice($datos['sucesoras'], 0, 5)) : '',
            $datos['materiales'] ? 'Materiales vinculados: ' . implode(', ', $datos['materiales']) : '',
            $datos['abiertos'] ? 'Ya hay RFI abiertos sobre esta actividad: ' . implode('; ', array_map(fn($n, $s) => "$n ($s)", array_keys($datos['abiertos']), $datos['abiertos'])) . '. Revisa que no se repita.' : '',
        ])));
        if ($datos['normativa']) $secs[] = Informes::tabla('Normativa relacionada (por si conviene citarla)', ['Documento', 'Página', 'Cláusula', 'Texto'], array_map(fn($n) => [$n['doc'], $n['pagina'], $n['clausula'] ?: '—', $n['texto']], $datos['normativa']));
        return ['secciones' => $secs, 'aplicar' => ['tipo' => 'rfi', 'rfi' => $items]];
    }
}
