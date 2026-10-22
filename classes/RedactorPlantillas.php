<?php

namespace Classes;

use Model\RedactorDocumento;

/** Plantillas del Redactor: las incluidas con la aplicación y las que el proyecto guarda. Marcas: {{proyecto}} {{nombre}} {{fecha}} {{departamento}} {{usuario}} */
class RedactorPlantillas
{
    private const B = 'border:1px solid #888888';

    private static function tabla(array $filas, array $encabezado = [], string $ancho = '100%'): string
    {
        $h = '<table style="width:' . $ancho . ';border-collapse:collapse"><tbody>';
        if ($encabezado) {
            $h .= '<tr>' . implode('', array_map(fn($t) => '<th style="' . self::B . '">' . $t . '</th>', $encabezado)) . '</tr>';
        }
        foreach ($filas as $f) $h .= '<tr>' . implode('', array_map(fn($t) => '<td style="' . self::B . '">' . ($t === '' ? '<br>' : $t) . '</td>', $f)) . '</tr>';
        return $h . '</tbody></table>';
    }

    /** Fila de bloques de firma a mano: una columna por persona, sin bordes */
    private static function firmas(array $personas): string
    {
        $h = '<table style="width:100%;border-collapse:collapse"><tbody><tr>';
        foreach ($personas as [$nombre, $cargo]) {
            $h .= '<td style="border:0;text-align:center"><p><br></p><p><br></p><p style="text-align:center">______________________________</p><p style="text-align:center"><strong>' . $nombre . '</strong><br>' . $cargo . '</p></td>';
        }
        return $h . '</tr></tbody></table>';
    }

    private static function encabezado(string $tipo): string
    {
        return '<h1 style="text-align:center">' . $tipo . '</h1><p style="text-align:center"><strong>{{proyecto}}</strong><br>{{departamento}} · {{fecha}}</p>';
    }

    /** Plantillas incluidas: clave => [nombre, descripción, icono, html] */
    public static function incluidas(): array
    {
        return [
            'oficio' => ['Oficio / carta', 'Carta formal con destinatario, asunto y firma', 'fa-envelope-open-text',
                '<p style="text-align:right">{{fecha}}</p><p><strong>Asunto:</strong> </p><p><br></p><p><strong>Nombre del destinatario</strong><br>Cargo<br>Empresa</p><p><br></p><p>Por medio de la presente me dirijo a usted para </p><p style="text-align:justify">Sin otro particular por el momento, quedo a sus órdenes para cualquier aclaración.</p><p><br></p><p>Atentamente</p>' . self::firmas([['{{usuario}}', '{{departamento}}']])],
            'minuta' => ['Minuta de reunión', 'Asistentes, orden del día, acuerdos y responsables', 'fa-handshake',
                self::encabezado('Minuta de reunión') . '<h2>Datos de la reunión</h2>' . self::tabla([['<strong>Fecha</strong>', '{{fecha}}', '<strong>Hora</strong>', ''], ['<strong>Lugar</strong>', '', '<strong>Convoca</strong>', '{{usuario}}']])
                . '<h2>Asistentes</h2>' . self::tabla([['', '', ''], ['', '', ''], ['', '', '']], ['Nombre', 'Empresa / área', 'Cargo'])
                . '<h2>Orden del día</h2><ol><li></li><li></li></ol><h2>Acuerdos</h2>' . self::tabla([['1', '', '', ''], ['2', '', '', '']], ['#', 'Acuerdo', 'Responsable', 'Fecha compromiso'])
                . '<h2>Próxima reunión</h2><p></p>' . self::firmas([['Elaboró', '{{usuario}}'], ['Revisó', '']])],
            'avance' => ['Informe de avance', 'Resumen, avance físico, actividades, riesgos y siguientes pasos', 'fa-chart-line',
                self::encabezado('Informe de avance') . '<h2>1. Resumen ejecutivo</h2><p>Descripción general del periodo.</p><h2>2. Avance físico</h2>'
                . self::tabla([['', '', '', ''], ['', '', '', '']], ['Concepto', '% programado', '% real', 'Observaciones'])
                . '<h2>3. Actividades realizadas</h2><ul><li></li><li></li></ul><h2>4. Actividades próximas</h2><ul><li></li></ul><h2>5. Riesgos y restricciones</h2>'
                . self::tabla([['', '', '']], ['Riesgo / restricción', 'Impacto', 'Acción']) . '<h2>6. Conclusiones</h2><p></p>'],
            'memoria' => ['Memoria técnica', 'Documento largo con índice automático y secciones numeradas', 'fa-book',
                '<p style="text-align:center;font-size:28pt"><strong>Memoria técnica</strong></p><p style="text-align:center"><strong>{{nombre}}</strong><br>{{proyecto}}<br>{{departamento}} · {{fecha}}</p><div class="salto-pagina" style="page-break-after:always"></div>'
                . '<div class="rd-indice"><p><strong>Índice</strong></p><p><em>Inserta o actualiza el índice con el botón «Índice».</em></p></div><div class="salto-pagina" style="page-break-after:always"></div>'
                . '<h1>1. Objetivo</h1><p></p><h1>2. Alcance</h1><p></p><h1>3. Descripción</h1><h2>3.1 Antecedentes</h2><p></p><h2>3.2 Criterios de diseño</h2><p></p><h1>4. Cálculos</h1><p></p><h1>5. Conclusiones</h1><p></p>'],
            'rfi' => ['Solicitud de información (RFI)', 'Pregunta técnica con referencia, urgencia y respuesta', 'fa-circle-question',
                self::encabezado('Solicitud de información (RFI)') . self::tabla([['<strong>No. de RFI</strong>', '', '<strong>Fecha</strong>', '{{fecha}}'], ['<strong>De</strong>', '{{usuario}}', '<strong>Para</strong>', ''], ['<strong>Disciplina</strong>', '', '<strong>Urgencia</strong>', 'Normal / Urgente'], ['<strong>Referencia</strong>', 'Plano, especificación o submittal', '<strong>Fecha límite de respuesta</strong>', '']])
                . '<h2>Pregunta</h2><p></p><h2>Propuesta del contratista</h2><p></p><h2>Respuesta</h2><p></p>' . self::firmas([['Solicita', '{{usuario}}'], ['Responde', '']])],
            'cambio' => ['Orden de cambio', 'Cambio de alcance con impacto en costo y tiempo', 'fa-arrows-rotate',
                self::encabezado('Orden de cambio') . self::tabla([['<strong>No. de orden</strong>', '', '<strong>Fecha</strong>', '{{fecha}}']]) . '<h2>Descripción del cambio</h2><p></p><h2>Motivo</h2><p></p><h2>Impacto</h2>'
                . self::tabla([['Costo', '', ''], ['Tiempo (días)', '', ''], ['Actividades afectadas', '', '']], ['Concepto', 'Importe / valor', 'Observaciones']) . self::firmas([['Contratista', ''], ['Supervisión', ''], ['Cliente', '']])],
            'acta' => ['Acta de entrega-recepción', 'Entrega de obra o equipo con observaciones y firmas', 'fa-clipboard-check',
                self::encabezado('Acta de entrega-recepción') . '<p>En la fecha {{fecha}}, se hace entrega y recepción de los trabajos correspondientes a <strong>{{proyecto}}</strong>, conforme a lo contratado.</p><h2>Alcance entregado</h2><ul><li></li><li></li></ul><h2>Observaciones</h2>'
                . self::tabla([['', '', '']], ['Observación', 'Responsable', 'Fecha de atención']) . '<h2>Documentación entregada</h2><ul><li>Planos as-built</li><li>Manuales y garantías</li></ul>' . self::firmas([['Entrega', ''], ['Recibe', ''], ['Testigo', '']])],
            'incidente' => ['Reporte de incidente', 'Accidente o evento de seguridad: qué, cuándo, causas y acciones', 'fa-triangle-exclamation',
                self::encabezado('Reporte de incidente') . self::tabla([['<strong>Fecha y hora</strong>', '', '<strong>Lugar</strong>', ''], ['<strong>Tipo</strong>', 'Accidente / casi accidente / daño', '<strong>Gravedad</strong>', ''], ['<strong>Persona(s) involucrada(s)</strong>', '', '<strong>Reporta</strong>', '{{usuario}}']])
                . '<h2>Descripción de lo ocurrido</h2><p></p><h2>Causas probables</h2><p></p><h2>Acciones inmediatas</h2><p></p><h2>Acciones correctivas y preventivas</h2>' . self::tabla([['', '', '']], ['Acción', 'Responsable', 'Fecha']) . self::firmas([['Reporta', '{{usuario}}'], ['Seguridad', '']])],
            'checklist' => ['Lista de verificación', 'Puntos a revisar con casillas de cumplimiento', 'fa-list-check',
                self::encabezado('Lista de verificación') . self::tabla([['', '☐ Sí  ☐ No  ☐ N/A', ''], ['', '☐ Sí  ☐ No  ☐ N/A', ''], ['', '☐ Sí  ☐ No  ☐ N/A', ''], ['', '☐ Sí  ☐ No  ☐ N/A', '']], ['Punto a verificar', 'Cumple', 'Observaciones']) . self::firmas([['Revisó', '{{usuario}}']])],
            'bitacora' => ['Bitácora de obra', 'Registro diario de personal, actividades, materiales, equipo e incidencias', 'fa-clipboard-list',
                self::encabezado('Bitácora de obra')
                . '<h2>Datos del día</h2>' . self::tabla([['<strong>Fecha</strong>', '{{fecha}}', '<strong>Turno</strong>', ''], ['<strong>Clima</strong>', '', '<strong>Reporta</strong>', '{{usuario}}'], ['<strong>Ubicación / frente</strong>', '', '<strong>Supervisor</strong>', '']])
                . '<h2>Personal en obra</h2>' . self::tabla([['', '', '', ''], ['', '', '', ''], ['', '', '', '']], ['Nombre', 'Puesto / cuadrilla', 'Entrada', 'Salida'])
                . '<h2>Actividades realizadas</h2>' . self::tabla([['', '', '', ''], ['', '', '', ''], ['', '', '', '']], ['Actividad', 'Ubicación', 'Avance', 'Observaciones'])
                . '<h2>Materiales recibidos</h2>' . self::tabla([['', '', '', ''], ['', '', '', '']], ['Material', 'Cantidad', 'Unidad', 'Proveedor / remisión'])
                . '<h2>Equipo y maquinaria</h2>' . self::tabla([['', '', ''], ['', '', '']], ['Equipo', 'Horas', 'Operador'])
                . '<h2>Incidencias, restricciones y notas</h2><p></p>'
                . self::firmas([['Residente de obra', ''], ['Supervisor', ''], ['Contratista', '']])],
            'reporte_semanal' => ['Reporte semanal de obra', 'Avance programado vs real, actividades, restricciones y plan de la próxima semana', 'fa-calendar-days',
                self::encabezado('Reporte semanal de obra')
                . '<h2>Datos del periodo</h2>' . self::tabla([['<strong>Semana</strong>', '', '<strong>Del</strong>', ''], ['<strong>Al</strong>', '', '<strong>Residente</strong>', '{{usuario}}']])
                . '<h2>1. Resumen ejecutivo</h2><p>Principales avances, desviaciones y decisiones de la semana.</p>'
                . '<h2>2. Avance por partida</h2>' . self::tabla([['', '', '', ''], ['', '', '', ''], ['', '', '', '']], ['Partida', '% programado', '% real', 'Desviación'])
                . '<h2>3. Actividades de la semana</h2>' . self::tabla([['', '', '', ''], ['', '', '', ''], ['', '', '', '']], ['Actividad', 'Inicio', 'Fin', 'Avance / observaciones'])
                . '<h2>4. Restricciones y riesgos</h2>' . self::tabla([['', '', '', ''], ['', '', '', '']], ['Restricción', 'Impacto', 'Responsable', 'Fecha compromiso'])
                . '<h2>5. Plan de la próxima semana</h2><ul><li></li><li></li><li></li></ul>'
                . '<h2>6. Apoyos requeridos</h2><p></p>'
                . self::firmas([['Residente de obra', '{{usuario}}'], ['Supervisión', ''], ['Cliente', '']])],
            'estimacion' => ['Estimación de obra', 'Estimación por conceptos con importes, amortización y retenciones', 'fa-file-invoice-dollar',
                self::encabezado('Estimación de obra')
                . '<h2>Datos de la estimación</h2>' . self::tabla([['<strong>Estimación No.</strong>', '', '<strong>Periodo</strong>', ''], ['<strong>Contrato / orden</strong>', '', '<strong>Fecha</strong>', '{{fecha}}'], ['<strong>Contratista</strong>', '', '<strong>Revisó</strong>', '{{usuario}}']])
                . '<h2>Conceptos ejecutados</h2>' . self::tabla([['', '', '', '', '', ''], ['', '', '', '', '', ''], ['', '', '', '', '', ''], ['', '', '', '', '', '']], ['No.', 'Concepto', 'Unidad', 'Cantidad', 'P.U.', 'Importe'])
                . '<h2>Resumen</h2>' . self::tabla([['Subtotal', '', ''], ['Amortización de anticipo (%)', '', ''], ['Retención (%)', '', ''], ['IVA', '', ''], ['<strong>Total a pagar</strong>', '', '']], ['Concepto', 'Porcentaje', 'Importe'])
                . '<h2>Notas</h2><p>La estimación ampara los trabajos ejecutados y verificados en campo durante el periodo.</p>'
                . self::firmas([['Elaboró', '{{usuario}}'], ['Revisó', ''], ['Autorizó', '']])],
            'submittals' => ['Control de submittals', 'Lista de documentos técnicos con estado, revisión y fechas de respuesta', 'fa-folder-open',
                self::encabezado('Control de submittals')
                . '<p>Registro de documentos enviados para revisión y aprobación antes de la ejecución.</p>'
                . self::tabla([['', '', '', '', '', '', ''], ['', '', '', '', '', '', ''], ['', '', '', '', '', '', '']], ['No.', 'Documento', 'Disciplina', 'Especificación', 'Estado', 'Enviado', 'Respuesta'])
                . '<p><strong>Estados:</strong> Pendiente · Enviado · Aprobado · Aprobado con comentarios · Rechazado · Resubmitir.</p>'
                . '<h2>Comentarios y pendientes</h2><p></p>'
                . self::firmas([['Elaboró', '{{usuario}}'], ['Revisó', '']])],
            'no_conformidad' => ['Reporte de no conformidad', 'Desviación de calidad con causa raíz, acción correctiva y cierre', 'fa-triangle-exclamation',
                self::encabezado('Reporte de no conformidad')
                . self::tabla([['<strong>No. de NC</strong>', '', '<strong>Fecha</strong>', '{{fecha}}'], ['<strong>Ubicación</strong>', '', '<strong>Detectado por</strong>', '{{usuario}}'], ['<strong>Actividad / partida</strong>', '', '<strong>Responsable</strong>', ''], ['<strong>Severidad</strong>', 'Crítica / Mayor / Menor', '<strong>Estado</strong>', 'Abierta / En proceso / Cerrada']])
                . '<h2>Descripción de la no conformidad</h2><p></p><h2>Requisito incumplido</h2><p>Especificación, plano o norma aplicable.</p>'
                . '<h2>Análisis de causa raíz</h2><p></p><h2>Acción inmediata</h2><p></p>'
                . '<h2>Acción correctiva</h2>' . self::tabla([['', '', '', ''], ['', '', '', '']], ['Acción', 'Responsable', 'Fecha compromiso', 'Seguimiento'])
                . '<h2>Verificación y cierre</h2><p></p>'
                . self::firmas([['Reporta', '{{usuario}}'], ['Calidad', ''], ['Responsable del cierre', '']])],
            'procedimiento' => ['Procedimiento constructivo', 'Secuencia de trabajo, recursos, control de calidad y seguridad', 'fa-screwdriver-wrench',
                self::encabezado('Procedimiento constructivo')
                . '<h2>1. Objetivo</h2><p>Definir la metodología, los recursos y los controles para ejecutar la actividad de forma segura y conforme a la especificación.</p>'
                . '<h2>2. Alcance</h2><p>Aplica a las actividades de <strong>{{proyecto}}</strong> relacionadas con el presente procedimiento.</p>'
                . '<h2>3. Referencias</h2><ul><li>Planos y especificaciones del proyecto</li><li>Normas aplicables</li><li>Procedimientos de calidad y seguridad</li></ul>'
                . '<h2>4. Responsables</h2>' . self::tabla([['', ''], ['', ''], ['', '']], ['Puesto', 'Responsabilidad'])
                . '<h2>5. Recursos</h2>' . self::tabla([['', '', ''], ['', '', ''], ['', '', '']], ['Material / equipo', 'Cantidad', 'Observaciones'])
                . '<h2>6. Secuencia de trabajo</h2><ol><li>Preparación del área y verificación de condiciones.</li><li>Replanteo y trazo.</li><li>Ejecución de la actividad.</li><li>Inspección y liberación.</li><li>Limpieza y cierre.</li></ol>'
                . '<h2>7. Control de calidad</h2>' . self::tabla([['', '', ''], ['', '', '']], ['Punto de inspección', 'Criterio de aceptación', 'Registro'])
                . '<h2>8. Seguridad y medio ambiente</h2><p>Identificar peligros, aplicar el análisis de trabajo seguro, usar el equipo de protección personal y controlar los residuos.</p>'
                . '<h2>9. Registros</h2><ul><li>Checklist de liberación</li><li>Reporte fotográfico</li><li>Certificados de materiales</li></ul>'
                . self::firmas([['Elaboró', '{{usuario}}'], ['Revisó', ''], ['Autorizó', '']])],
        ];
    }

    /** Plantillas guardadas por el proyecto: [['id', 'nombre', 'descripcion']] */
    public static function deProyecto(int $proyectoId): array
    {
        $out = [];
        try {
            $r = $GLOBALS['db']->query("SELECT id, nombre, descripcion FROM redactor_plantillas WHERE proyecto_id = $proyectoId ORDER BY nombre");
            while ($r && ($f = $r->fetch_assoc())) $out[] = ['id' => (int)$f['id'], 'nombre' => $f['nombre'], 'descripcion' => $f['descripcion']];
        } catch (\Throwable $e) {
        }
        return $out;
    }

    /** Datos de una plantilla ('inc:clave' o 'pro:id') con las marcas ya sustituidas, o null */
    public static function aplicar(string $clave, int $proyectoId, string $nombreProyecto, RedactorDocumento $d, string $usuario): ?array
    {
        $html = null;
        $ajustes = [];
        if (str_starts_with($clave, 'inc:')) {
            $p = self::incluidas()[substr($clave, 4)] ?? null;
            if ($p) $html = $p[3];
        } elseif (str_starts_with($clave, 'pro:')) {
            $id = (int)substr($clave, 4);
            $r = $GLOBALS['db']->query("SELECT * FROM redactor_plantillas WHERE id = $id AND proyecto_id = $proyectoId");
            if ($r && ($f = $r->fetch_assoc())) {
                $html = (string)$f['contenido'];
                $ajustes = ['papel' => $f['papel'], 'orientacion' => $f['orientacion'], 'margen_sup' => $f['margen_sup'], 'margen_inf' => $f['margen_inf'], 'margen_izq' => $f['margen_izq'], 'margen_der' => $f['margen_der']];
            }
        }
        if ($html === null) return null;
        $e = fn($t) => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $html = strtr($html, ['{{proyecto}}' => $e($nombreProyecto), '{{nombre}}' => $e($d->nombre), '{{fecha}}' => date('d/m/Y', strtotime($d->fecha)), '{{departamento}}' => $e($d->departamento !== '' ? $d->departamento : 'Departamento'), '{{usuario}}' => $e($usuario)]);
        return ['contenido' => Redactor::limpiar($html)] + $ajustes;
    }

    public static function guardar(int $proyectoId, string $nombre, string $descripcion, RedactorDocumento $d): bool
    {
        $db = $GLOBALS['db'];
        $html = Redactor::limpiar(Redactor::resolverCambios((string)$d->contenido, true));
        $q = fn($t) => $db->real_escape_string($t);
        return (bool)$db->query('INSERT INTO redactor_plantillas (proyecto_id, nombre, descripcion, papel, orientacion, margen_sup, margen_inf, margen_izq, margen_der, contenido) VALUES (' . $proyectoId . ", '" . $q(mb_substr($nombre, 0, 100)) . "', '" . $q(mb_substr($descripcion, 0, 200)) . "', '" . $q($d->papel) . "', '" . $q($d->orientacion) . "', " . (float)$d->margen_sup . ', ' . (float)$d->margen_inf . ', ' . (float)$d->margen_izq . ', ' . (float)$d->margen_der . ", '" . $q($html) . "')");
    }

    public static function eliminar(int $proyectoId, int $id): void
    {
        $GLOBALS['db']->query("DELETE FROM redactor_plantillas WHERE id = $id AND proyecto_id = $proyectoId");
    }
}
