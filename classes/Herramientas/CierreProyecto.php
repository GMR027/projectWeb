<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Classes\Punch;
use Model\Proyecto;

/**
 * Cierre de proyecto: lista de verificación de entrega calculada con los datos reales (actividades, materiales y fichas, submittals, restricciones, punch list, seguridad,
 * normativa, archivos), un índice del expediente de materiales y las lecciones aprendidas (la IA las redacta con las causas de retraso, el punch list y los incidentes).
 */
class CierreProyecto extends Base
{
    public static function id(): string { return 'cierre'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Cierre de proyecto y expediente de entrega', 'grupo' => 'cierre', 'tipo' => 'cierre', 'aplicable' => false,
            'descripcion' => 'Revisa qué falta para entregar el proyecto (actividades, materiales con ficha y certificado, submittals, restricciones, punch list, seguridad, documentos), arma el índice del expediente y redacta las lecciones aprendidas. Se exporta a PDF para la entrega.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'], 'parametros' => []];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Cierre del proyecto · ' . date('d/m/Y');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $c = fn(string $sql) => (int)(self::filas($sql)[0]['n'] ?? 0);
        $acts = Dependencias::actividades($pid);
        $pend = array_values(array_filter($acts, fn($a) => (int)$a['progreso'] < 100));
        usort($pend, fn($x, $y) => strcmp($x['fecha_fin'], $y['fecha_fin']));
        $mats = self::filas("SELECT m.id, m.nombre, m.especialidad, m.compra, m.fecha_entrega_real, m.ficha_tecnica, m.certificado_calidad,
                (SELECT s.estatus FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE sm.material_id = m.id ORDER BY (s.estatus IN ('aprobado','aprobado_an')) DESC, s.fecha DESC LIMIT 1) sub FROM materiales m WHERE m.proyecto_id = $pid ORDER BY m.especialidad, m.nombre");
        $archivos = $c("SELECT COUNT(*) n FROM archivos WHERE proyecto_id = $pid");
        $hayArchivo = fn(string $re) => $c("SELECT COUNT(*) n FROM archivos WHERE proyecto_id = $pid AND nombre REGEXP '$re'");
        $punch = Punch::analisis($pid);
        $lec = Dependencias::actividades($pid) ? CausasRetraso::preparar($p, ['dias' => 1])['datos'] : ['por_causa' => [], 'total' => 0];
        $datos = [
            'actividades' => ['total' => count($acts), 'pendientes' => count($pend), 'lista' => array_map(fn($a) => $a['codigo'] . ' ' . self::corto($a['nombre'], 60) . ' (' . $a['progreso'] . '%)', array_slice($pend, 0, 15))],
            'materiales' => ['total' => count($mats), 'sin_entrega' => count(array_filter($mats, fn($m) => !$m['fecha_entrega_real'])), 'sin_ficha' => count(array_filter($mats, fn($m) => empty($m['ficha_tecnica']))), 'sin_cert' => count(array_filter($mats, fn($m) => trim((string)$m['certificado_calidad']) === '')),
                'filas' => array_map(fn($m) => [self::corto($m['nombre'], 70), $m['especialidad'] ?: '—', $m['fecha_entrega_real'] ? 'Entregado' : ($m['compra'] === 'comprado' ? 'Comprado' : 'Pendiente'), !empty($m['ficha_tecnica']) ? 'Sí' : 'No', trim((string)$m['certificado_calidad']) !== '' ? 'Sí' : 'No', ['aprobado' => 'Aprobado', 'aprobado_an' => 'Aprobado c/anot.', 'enviado' => 'En revisión', 'rechazado' => 'Rechazado'][$m['sub']] ?? 'Sin submittal'], $mats)],
            'submittals' => $c("SELECT COUNT(*) n FROM submittals WHERE proyecto_id = $pid AND estatus IN ('enviado','rechazado')"),
            'restricciones' => $c("SELECT COUNT(*) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND r.estado = 'activa'"),
            'punch' => $punch, 'permisos_abiertos' => $c("SELECT COUNT(*) n FROM permisos_trabajo WHERE proyecto_id = $pid AND estado IN ('solicitado','aprobado')"), 'incidentes' => $c("SELECT COUNT(*) n FROM seguridad_incidentes WHERE proyecto_id = $pid AND estado = 'abierto'"),
            'normativa' => $c("SELECT COUNT(*) n FROM normativa_aplicaciones WHERE proyecto_id = $pid AND estado = 'propuesta'"), 'propuestas_ia' => $c("SELECT COUNT(*) n FROM inconsistencias WHERE proyecto_id = $pid AND estado = 'pendiente'") + $c("SELECT COUNT(*) n FROM analisis_materiales_hallazgos WHERE proyecto_id = $pid AND estado = 'pendiente'"),
            'archivos' => ['total' => $archivos, 'asbuilt' => $hayArchivo('as[ -_]?built|planos finales|planos as'), 'garantias' => $hayArchivo('garant'), 'manuales' => $hayArchivo('manual|memoria|protocolo')],
            'evidencias' => $c("SELECT COUNT(*) n FROM evidencias WHERE proyecto_id = $pid"), 'causas' => $lec['por_causa'] ?? [], 'atrasadas' => $lec['total'] ?? 0,
            'bitacora_graves' => self::filas("SELECT fecha, descripcion FROM bitacora WHERE proyecto_id = $pid AND importancia IN ('alta','critica') ORDER BY fecha DESC LIMIT 8"),
        ];
        $causas = array_filter($datos['causas']);
        arsort($causas);
        $prompt = "Eres el gerente de un proyecto de construcción que está por cerrarse y redactas las LECCIONES APRENDIDAS para la empresa. Usa SOLO los datos; no inventes hechos. Cada lección debe poder aplicarse en un proyecto futuro.\n\nProyecto: {$p->nombre} ({$p->fecha_inicio} a {$p->fecha_fin})\n"
            . "Actividades atrasadas: {$datos['atrasadas']}. Causas de retraso (actividades afectadas): " . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($causas), $causas)) . "\n"
            . "Punch list: {$punch['total']} pendientes registrados, {$punch['vencidos']} vencidos; categorías con más pendientes: " . implode(', ', array_map(fn($r) => "{$r['clave']} ({$r['n']})", array_slice($punch['categorias'], 0, 4))) . "\n"
            . "Incidentes de seguridad abiertos: {$datos['incidentes']}. Submittals sin aprobar: {$datos['submittals']}. Materiales sin ficha técnica: {$datos['materiales']['sin_ficha']} de {$datos['materiales']['total']}.\n"
            . "\n## BITÁCORA IMPORTANTE\n" . ($datos['bitacora_graves'] ? implode("\n", array_map(fn($b) => self::fmt($b['fecha']) . ': ' . self::corto($b['descripcion'], 180), $datos['bitacora_graves'])) : '(sin entradas)')
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"1 párrafo del balance del proyecto\",\"lecciones\":[{\"tema\":\"\",\"que_paso\":\"\",\"aprendizaje\":\"qué hacer distinto la próxima vez\"}]}  (3 a 6 lecciones)";
        return ['datos' => $datos, 'lotes' => [['titulo' => 'Redactando las lecciones aprendidas', 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $a = $datos['actividades']; $m = $datos['materiales']; $ar = $datos['archivos']; $pu = $datos['punch'];
        $chk = [
            ['Todas las actividades terminadas', $a['pendientes'] === 0, $a['pendientes'] ? "{$a['pendientes']} actividad(es) sin terminar" : 'Completo'],
            ['Materiales entregados', $m['sin_entrega'] === 0, $m['sin_entrega'] ? "{$m['sin_entrega']} sin entrega real" : 'Completo'],
            ['Fichas técnicas de los materiales', $m['sin_ficha'] === 0, $m['sin_ficha'] ? "{$m['sin_ficha']} sin ficha técnica (PDF)" : 'Completo'],
            ['Certificados de calidad', $m['sin_cert'] === 0, $m['sin_cert'] ? "{$m['sin_cert']} sin certificado registrado" : 'Completo'],
            ['Submittals aprobados', $datos['submittals'] === 0, $datos['submittals'] ? "{$datos['submittals']} en revisión o rechazados" : 'Completo'],
            ['Sin restricciones activas', $datos['restricciones'] === 0, $datos['restricciones'] ? "{$datos['restricciones']} activa(s)" : 'Completo'],
            ['Punch list cerrado', $pu['pendientes'] === 0, $pu['pendientes'] ? "{$pu['pendientes']} pendiente(s), {$pu['vencidos']} vencido(s)" : 'Completo'],
            ['Permisos de trabajo cerrados', $datos['permisos_abiertos'] === 0, $datos['permisos_abiertos'] ? "{$datos['permisos_abiertos']} sin cerrar" : 'Completo'],
            ['Incidentes de seguridad cerrados', $datos['incidentes'] === 0, $datos['incidentes'] ? "{$datos['incidentes']} abierto(s)" : 'Completo'],
            ['Vínculos de normativa decididos', $datos['normativa'] === 0, $datos['normativa'] ? "{$datos['normativa']} por revisar" : 'Completo'],
            ['Análisis inteligente resuelto', $datos['propuestas_ia'] === 0, $datos['propuestas_ia'] ? "{$datos['propuestas_ia']} pendiente(s)" : 'Completo'],
            ['Planos finales (as-built) en Archivo', $ar['asbuilt'] > 0, $ar['asbuilt'] ? "{$ar['asbuilt']} archivo(s)" : 'No se encontró ningún archivo con «as-built» o «planos finales» en el nombre'],
            ['Garantías en Archivo', $ar['garantias'] > 0, $ar['garantias'] ? "{$ar['garantias']} archivo(s)" : 'No se encontró ningún archivo con «garantía» en el nombre'],
            ['Manuales, memorias o protocolos en Archivo', $ar['manuales'] > 0, $ar['manuales'] ? "{$ar['manuales']} archivo(s)" : 'No se encontró ningún archivo de manuales, memorias o protocolos'],
        ];
        $ok = count(array_filter($chk, fn($x) => $x[1]));
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 2000);
        $lecciones = [];
        foreach ((array)(self::dato($respuestas, 'lecciones') ?? []) as $l) if (is_array($l) && trim((string)($l['aprendizaje'] ?? '')) !== '') $lecciones[] = [Informes::limpiarTexto($l['tema'] ?? '', 80), Informes::limpiarTexto($l['que_paso'] ?? '', 260), Informes::limpiarTexto($l['aprendizaje'], 260)];
        $ia = (bool)$lecciones;
        if (!$lecciones) {
            $causas = array_filter($datos['causas']); arsort($causas);
            if ($causas) { $k = array_key_first($causas); $lecciones[] = ['Retrasos', "La causa que más actividades atrasadas afectó fue «{$k}» ({$causas[$k]}).", 'Atender esa causa desde la planeación: revisarla en el lookahead desde las primeras semanas.']; }
            if ($pu['categorias']) $lecciones[] = ['Calidad', "La categoría con más pendientes en el punch list fue «{$pu['categorias'][0]['clave']}».", 'Agregar una inspección intermedia de esa categoría antes de la entrega final.'];
            if ($m['sin_ficha']) $lecciones[] = ['Documentación', "{$m['sin_ficha']} materiales llegaron al cierre sin ficha técnica.", 'Exigir la ficha técnica al aprobar el submittal, no al cierre.'];
        }
        if ($resumen === '') $resumen = "El proyecto está al " . round($ok / count($chk) * 100) . " % de la lista de entrega ($ok de " . count($chk) . " puntos cumplidos). " . ($ok === count($chk) ? 'Está listo para entregar.' : 'Resuelve los puntos pendientes de la tabla antes de la entrega.');
        $secs = [
            Informes::kpis('Listo para entregar', [['Lista de entrega', round($ok / count($chk) * 100) . ' %', "$ok de " . count($chk) . ' puntos'], ['Actividades terminadas', ($a['total'] - $a['pendientes']) . ' / ' . $a['total']], ['Materiales con ficha', ($m['total'] - $m['sin_ficha']) . ' / ' . $m['total']], ['Pendientes de calidad', $pu['pendientes']], ['Fotos de evidencia', $datos['evidencias']], ['Archivos en el expediente', $ar['total']]]),
            Informes::texto('Balance' . ($ia ? ' (redactado con IA, revísalo)' : ' (automático)'), $resumen),
            Informes::tabla('Lista de verificación de entrega', ['Punto', 'Estado', 'Detalle'], array_map(fn($x) => [$x[0], $x[1] ? 'Cumple' : 'PENDIENTE', $x[2]], $chk)),
        ];
        if ($a['lista']) $secs[] = Informes::lista('Actividades sin terminar (las 15 con fecha más antigua)', $a['lista']);
        $secs[] = Informes::tabla('Expediente de materiales', ['Material', 'Especialidad', 'Entrega', 'Ficha técnica', 'Certificado', 'Submittal'], $m['filas']);
        $secs[] = Informes::tabla('Lecciones aprendidas' . ($ia ? ' (redactadas con IA)' : ' (automáticas, a partir de los datos)'), ['Tema', 'Qué pasó', 'Qué hacer distinto'], $lecciones);
        return ['secciones' => $secs];
    }
}
