<?php

namespace Classes\Herramientas;

use Classes\Informes;
use Classes\Seguridad;
use Model\Proyecto;

/**
 * Seguridad de la semana: para las actividades próximas la IA redacta riesgos, medidas y EPP y una charla de 5 minutos; el sistema (por palabras del nombre de la actividad) dice
 * qué permisos de trabajo exigen y cuáles faltan, y puede dejar borradores de permiso para que los revises y solicites. No sustituye al responsable de seguridad.
 */
class SeguridadCharla extends Base
{
    public static function id(): string { return 'seguridad_charla'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Seguridad: charla y permisos', 'grupo' => 'seguridad', 'tipo' => 'seguridad_charla', 'aplicable' => false,
            'descripcion' => 'Para las actividades de los próximos días redacta los riesgos, medidas y EPP, una charla de seguridad de 5 minutos, y revisa qué permisos de trabajo faltan. Opcionalmente deja borradores de permiso (no se solicitan hasta que los revises). No sustituye al responsable de seguridad.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'medio'],
            'parametros' => [['nombre' => 'dias', 'etiqueta' => 'Actividades de los próximos (días)', 'tipo' => 'numero', 'defecto' => 7, 'min' => 1, 'max' => 14],
                ['nombre' => 'borradores', 'etiqueta' => '¿Dejar borradores de los permisos que faltan?', 'tipo' => 'opcion', 'opciones' => ['si' => 'Sí, como borradores por revisar', 'no' => 'No'], 'defecto' => 'si']]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Seguridad de la semana · ' . date('d/m/Y');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $T = Seguridad::tipos($pid);
        $req = Seguridad::requeridos($pid, (int)$params['dias']);
        $hoy = date('Y-m-d');
        $fin = date('Y-m-d', strtotime('+' . (int)$params['dias'] . ' days'));
        $acts = array_values(array_filter(\Classes\Dependencias::actividades($pid), fn($a) => !(int)$a['hito'] && (int)$a['progreso'] < 100 && $a['fecha_fin'] >= $hoy && $a['fecha_inicio'] <= $fin));
        usort($acts, fn($x, $y) => [-count(Seguridad::tiposRequeridos($x['nombre'], '', $pid)), $x['fecha_inicio']] <=> [-count(Seguridad::tiposRequeridos($y['nombre'], '', $pid)), $y['fecha_inicio']]);
        $acts = array_slice($acts, 0, 10);
        $lin = array_map(fn($a) => $a['codigo'] . ' | ' . self::corto($a['nombre'], 90) . ' | ' . self::fmt($a['fecha_inicio']) . ($tp = Seguridad::tiposRequeridos($a['nombre'], '', $pid) ? ' | permisos: ' . implode(', ', array_map(fn($t) => $T[$t]['nombre'], Seguridad::tiposRequeridos($a['nombre'], '', $pid))) : ''), $acts);
        $prompt = "Eres el responsable de seguridad e higiene de una obra de construcción. Para cada actividad de la lista identifica los riesgos principales, las medidas de control y el equipo de protección personal (EPP), y redacta una charla de seguridad de 5 minutos para la cuadrilla. Basa todo en el tipo de trabajo que describe el nombre; no inventes datos del sitio.\n\n"
            . "Proyecto: {$p->nombre}\n\n## ACTIVIDADES (código | nombre | inicio | permisos que suele exigir)\n" . ($lin ? implode("\n", $lin) : '(ninguna)')
            . "\n\nDevuelve SOLO un JSON: {\"actividades\":[{\"codigo\":\"\",\"riesgos\":[\"máx. 4\"],\"medidas\":[\"máx. 5\"],\"epp\":[\"máx. 5\"]}],\"charla\":\"texto de la charla de 5 minutos, claro y directo, en español\"}";
        return ['datos' => ['actividades' => array_map(fn($a) => ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'tipos' => Seguridad::tiposRequeridos($a['nombre'], '', $pid)], $acts), 'requeridos' => array_map(fn($r) => ['id' => (int)$r['actividad']['id'], 'codigo' => $r['actividad']['codigo'], 'nombre' => $r['actividad']['nombre'], 'inicio' => $r['actividad']['fecha_inicio'], 'fin' => $r['actividad']['fecha_fin'], 'tipo' => $r['tipo'], 'estado' => $r['estado']], $req)],
            'lotes' => $acts ? [['titulo' => 'Redactando riesgos, medidas y la charla de seguridad', 'prompt' => $prompt]] : []];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $pid = (int)$p->id;
        $T = Seguridad::tipos($pid);
        $ia = [];
        foreach ((array)(self::dato($respuestas, 'actividades') ?? []) as $x) if (is_array($x) && isset($x['codigo'])) $ia[(string)$x['codigo']] = $x;
        $charla = Informes::limpiarTexto(self::dato($respuestas, 'charla') ?? '', 3500);
        $filas = [];
        foreach ($datos['actividades'] as $a) {
            $x = $ia[$a['codigo']] ?? null;
            $base = array_values(array_unique(array_merge(...array_map(fn($t) => $T[$t]['medidas'], $a['tipos'] ?: ['general']))));
            $filas[] = [$a['codigo'] . ' ' . self::corto($a['nombre'], 60), self::fmt($a['inicio']), $x ? implode('; ', self::textos($x['riesgos'] ?? [], 4, 120)) : ($a['tipos'] ? 'Riesgos de ' . implode(', ', array_map(fn($t) => mb_strtolower($T[$t]['nombre']), $a['tipos'])) : '—'),
                $x ? implode('; ', self::textos($x['medidas'] ?? [], 5, 120)) : implode('; ', array_slice($base, 0, 4)), $x ? implode(', ', self::textos($x['epp'] ?? [], 5, 60)) : 'Casco, lentes, calzado de seguridad, guantes'];
        }
        $creados = 0;
        $falta = array_filter($datos['requeridos'], fn($r) => $r['estado'] === 'falta');
        if ($params['borradores'] === 'si') {
            foreach ($falta as $r) {
                $ya = $GLOBALS['db']->query("SELECT 1 FROM permisos_trabajo WHERE proyecto_id = $pid AND actividad_id = " . (int)$r['id'] . " AND tipo = '{$r['tipo']}' AND estado IN ('borrador','solicitado','aprobado')")->num_rows;
                if ($ya) continue;
                $x = $ia[$r['codigo']] ?? [];
                [$ok] = Seguridad::crearPermiso($pid, ['tipo' => $r['tipo'], 'titulo' => $T[$r['tipo']]['nombre'] . ': ' . self::corto($r['nombre'], 120), 'actividad_id' => $r['id'], 'vigente_desde' => max($r['inicio'], date('Y-m-d')), 'vigente_hasta' => max($r['fin'], $r['inicio'], date('Y-m-d')),
                    'descripcion' => 'Borrador generado a partir de la actividad ' . $r['codigo'] . ' (' . self::corto($r['nombre'], 100) . ').', 'riesgos' => implode("\n", self::textos($x['riesgos'] ?? [], 4, 160)), 'medidas' => Seguridad::medidas($r['tipo'], self::textos($x['medidas'] ?? [], 4, 160), $pid)], 'ia', 'borrador');
                if ($ok) $creados++;
            }
        }
        $secs = [
            Informes::kpis('Seguridad de los próximos ' . $params['dias'] . ' días', [['Actividades revisadas', count($datos['actividades'])], ['Permisos requeridos', count($datos['requeridos'])], ['Sin permiso', count($falta)], ['Borradores creados', $creados, $creados ? 'en Seguridad y permisos' : '']]),
            Informes::texto('Charla de seguridad de 5 minutos', $charla !== '' ? $charla : 'Reúne a la cuadrilla, repasa los riesgos y medidas de la tabla de abajo (especialmente los trabajos que exigen permiso), verifica el EPP de cada persona y confirma quién es el responsable de cada frente.'),
            Informes::tabla('Riesgos, medidas y EPP por actividad', ['Actividad', 'Inicio', 'Riesgos', 'Medidas de control', 'EPP'], $filas),
            Informes::tabla('Permisos de trabajo requeridos', ['Actividad', 'Inicio', 'Permiso', 'Situación'], array_map(fn($r) => [$r['codigo'] . ' ' . self::corto($r['nombre'], 60), self::fmt($r['inicio']), $T[$r['tipo']]['nombre'], ['ok' => 'Aprobado', 'tramite' => 'En trámite', 'falta' => 'FALTA'][$r['estado']]], $datos['requeridos'])),
        ];
        if ($creados) $secs[] = Informes::texto('Qué sigue', "Se dejaron $creados borrador(es) de permiso. Revísalos en Seguridad y permisos, completa responsable y contratista y solicítalos; el administrador los aprueba cuando las medidas estén verificadas.");
        return ['secciones' => $secs];
    }
}
