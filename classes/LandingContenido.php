<?php

namespace Classes;

/**
 * Contenido público de la landing y de /servicios.
 *
 * Un solo lugar para el catálogo de herramientas, los frentes y los datos de
 * contacto. Así la landing no repite listas que se desincronizan del menú.
 */
class LandingContenido
{
    /** Herramientas por frente. Solo herramientas que el cliente puede usar. */
    public const FRENTES = [
        'planear' => [
            'titulo' => 'Planear',
            'icono' => 'fa-chart-gantt',
            'desc' => 'Fechas, dependencias y semanas por venir.',
            'herramientas' => [
                ['Programa y Gantt', 'fa-list-check', 'Partidas, actividades e hitos con fechas sobre el calendario real. Cambia una fecha y todo se recalcula.'],
                ['Calendario operativo', 'fa-calendar-days', 'Jornada semanal, festivos, paros y clima: los días hábiles se respetan en cada actividad.'],
                ['Dependencias y ruta crítica', 'fa-diagram-project', 'Relaciones FS, SS, FF y SF, holguras, conflictos y propagación automática.'],
                ['Línea de tiempo activa', 'fa-chart-gantt', 'Gantt diario con arrastre de fechas, zoom, paneo y resolución de restricciones.'],
                ['Línea de balance', 'fa-chart-column', 'Frentes y oficios por piso con takt, cruces y generación de actividades.'],
                ['Lookahead 4S', 'fa-binoculars', 'La semana actual y las cuatro siguientes con PTA y limitaciones.'],
                ['Planificador por material', 'fa-diagram-next', 'Cruza actividades pendientes con materiales comprados y dice qué puedes adelantar.'],
                ['Last Planner', 'fa-note-sticky', 'Tarjetas por día y partida, listas para imprimir, calculadas desde tu programa.'],
                ['Vplan', 'fa-sheet-plastic', 'Láminas libres con plano, tabla del programa, recovery, restricciones y punch list.'],
                ['Líneas base', 'fa-timeline', 'Guarda una foto del programa y compara qué se movió, qué entró a la ruta crítica y por qué.'],
                ['Tipo de obra y plantillas', 'fa-layer-group', 'Programas base por tipología para arrancar sin capturar todo de nuevo.'],
            ],
        ],
        'ejecutar' => [
            'titulo' => 'Ejecutar',
            'icono' => 'fa-helmet-safety',
            'desc' => 'Lo que pasa en campo, fechado y con evidencia.',
            'herramientas' => [
                ['Reporte diario', 'fa-clipboard-list', 'Personal, actividades, materiales, fotos y costo del día en una pantalla móvil.'],
                ['Bitácora de obra', 'fa-book', 'Notas por departamento con niveles de importancia y clima automático.'],
                ['Minutas', 'fa-handshake', 'Temas, responsables, compromisos y asistentes con PDF listo para enviar.'],
                ['Evidencias', 'fa-camera-retro', 'Fotos del avance por actividad en una línea de tiempo.'],
                ['Curvas de productividad', 'fa-chart-line', 'Planeado contra real por semana y frente de trabajo.'],
                ['Seguridad y permisos', 'fa-helmet-safety', 'ATS, permisos, incidentes e indicadores de seguridad en campo.'],
                ['Punch list', 'fa-screwdriver-wrench', 'Pendientes con foto antes y después, inspección por ubicación y PDF por contratista.'],
                ['Calidad e inspecciones', 'fa-clipboard-check', 'ITP, ensayos, no conformidades y expediente con compuerta de calidad.'],
                ['RFI', 'fa-circle-question', 'Preguntas y respuestas ligadas a actividades y planos, con control de revisión.'],
                ['Mapa de avance', 'fa-map-location-dot', 'Avance marcado sobre el plano o el Vplan por zona y frente.'],
            ],
        ],
        'abastecer' => [
            'titulo' => 'Abastecer',
            'icono' => 'fa-truck-ramp-box',
            'desc' => 'Qué falta aprobar, comprar o entregar.',
            'herramientas' => [
                ['MSR · materiales', 'fa-truck-ramp-box', 'Submittals, compra y entrega con las columnas que tú elijas.'],
                ['Submittals', 'fa-boxes-packing', 'Aprobaciones y revisiones ligadas a cada material y a su actividad.'],
                ['Catálogo de materiales', 'fa-cubes', 'Fotos, fichas técnicas, proveedores y normas aplicables.'],
                ['Almacén', 'fa-warehouse', 'Órdenes de compra, recepciones y salidas con existencias.'],
                ['Dependencias de materiales', 'fa-link', 'Qué material frena qué actividad y cómo reprogramar sin perder el hilo.'],
            ],
        ],
        'documentar' => [
            'titulo' => 'Documentar',
            'icono' => 'fa-file-lines',
            'desc' => 'Actas, tablas y planos en su versión vigente.',
            'herramientas' => [
                ['Archivo del proyecto', 'fa-folder-open', 'Planos, especificaciones y contratos por categoría, fecha y revisión.'],
                ['Visor de documentos', 'fa-eye', 'Abre planos y PDF sin salir de la plataforma.'],
                ['Editor de gráficos', 'fa-pen-ruler', 'Croquis, diagramas y láminas con varias hojas y exportación a PNG, PDF u ODP.'],
                ['Símbolos', 'fa-shapes', 'Biblioteca de cámaras, racks, extintores, luminarias y tableros para marcar planos.'],
                ['Redactor', 'fa-file-pen', 'Actas, memorias y procedimientos con portada, versiones y DOCX, ODT o PDF.'],
                ['SheetFlow', 'fa-table-cells', 'Tablas con fórmulas e importación/exportación a XLSX, CSV y PDF.'],
                ['View360', 'fa-street-view', 'Un plano con zonas que abren fotos 360° de la obra y notas para el cliente.'],
                ['Unir documentos (PDF)', 'fa-object-group', 'Une actas y archivos del Archivo en un PDF que se actualiza solo.'],
                ['Comparador', 'fa-code-compare', 'Dos revisiones de un plano con un deslizador para ver qué cambió.'],
                ['Anotador', 'fa-pen-nib', 'Globos, textos, formas, medidas, firmas y PDF aplanado.'],
                ['Traductor', 'fa-language', 'Texto y documentos de obra español ↔ inglés sin salir de la plataforma.'],
                ['Envíos con acuse', 'fa-paper-plane', 'Transmittals con acuse y control de entregas al cliente o subcontratista.'],
            ],
        ],
        'controlar' => [
            'titulo' => 'Controlar',
            'icono' => 'fa-scale-balanced',
            'desc' => 'Desviaciones, cambios y quién puede ver qué.',
            'herramientas' => [
                ['Órdenes de cambio', 'fa-file-signature', 'Impacto en costo y días, instalaciones temporales y su retiro pendiente.'],
                ['Costos y presupuesto', 'fa-sack-dollar', 'Presupuesto, valor ganado, CPI/SPI y costo final del proyecto.'],
                ['Catálogo de conceptos', 'fa-rectangle-list', 'Conceptos de obra y reparto de cantidades entre actividades.'],
                ['Estimaciones', 'fa-file-invoice-dollar', 'Estimaciones con IVA, retención, anticipo y PDF.'],
                ['Riesgos', 'fa-triangle-exclamation', 'Matriz 3×3, mitigación y materialización como restricción.'],
                ['Contratos y fianzas', 'fa-file-contract', 'Contratos, fianzas y seguros con vencimientos a la vista.'],
                ['Subcontratos', 'fa-user-tie', 'Alcance, pagos y cumplimiento por subcontratista.'],
                ['Cierre de obra', 'fa-flag-checkered', 'Checklist de cierre, acta y liberación de punch.'],
                ['Recursos y cuadrillas', 'fa-people-group', 'Cuadrillas y carga semanal por especialidad.'],
                ['Equipo y maquinaria', 'fa-truck', 'Horas, mantenimiento y disponibilidad del equipo.'],
                ['Datos de obra', 'fa-building', 'Ficha contractual, ubicación y datos del proyecto.'],
                ['Historial de cambios', 'fa-clock-rotate-left', 'Quién cambió qué y cuándo, con trazabilidad.'],
                ['Salud del proyecto', 'fa-heart-pulse', 'Semáforo y actividades en riesgo por material, permiso o punch.'],
                ['Campos personalizados', 'fa-tags', 'Campos propios con filtros y agrupación en las listas.'],
                ['Plantillas de proyecto', 'fa-copy', 'Un proyecto completo listo para repetir en la siguiente obra.'],
                ['Compartir en solo lectura', 'fa-share-nodes', 'Enlace para el cliente y calendario ICS sin dar acceso de edición.'],
            ],
        ],
    ];

    /** Total de herramientas del catálogo público. */
    public static function total(): int
    {
        $n = 0;
        foreach (self::FRENTES as $frente) {
            $n += count($frente['herramientas']);
        }
        return $n;
    }

    /** Frentes con su conteo, listos para la vista. */
    public static function frentes(): array
    {
        $salida = [];
        foreach (self::FRENTES as $clave => $frente) {
            $frente['clave'] = $clave;
            $frente['total'] = count($frente['herramientas']);
            $salida[$clave] = $frente;
        }
        return $salida;
    }

    /** Lista plana para el explorador de funciones. */
    public static function todos(): array
    {
        $salida = [];
        foreach (self::FRENTES as $clave => $frente) {
            foreach ($frente['herramientas'] as $i => [$nombre, $icono, $detalle]) {
                $salida[] = [
                    'frente' => $clave,
                    'frente_titulo' => $frente['titulo'],
                    'frente_icono' => $frente['icono'],
                    'nombre' => $nombre,
                    'icono' => $icono,
                    'detalle' => $detalle,
                    'indice' => $i,
                ];
            }
        }
        return $salida;
    }

    /** Correo de contacto visible (si está configurado). */
    public static function contacto(): string
    {
        return trim((string)(getenv('CONTACT_EMAIL') ?: ($_ENV['CONTACT_EMAIL'] ?? '')));
    }

    /** Número de WhatsApp en solo dígitos, con lada internacional. */
    public static function whatsappNumero(): string
    {
        $n = trim((string)(getenv('CONTACT_WHATSAPP') ?: ($_ENV['CONTACT_WHATSAPP'] ?? '')));
        return preg_replace('/\D+/', '', $n) ?? '';
    }

    /** Enlace de WhatsApp con mensaje precargado. */
    public static function whatsappEnlace(string $mensaje = 'Hola, quiero información sobre ProjectWeb para mi obra.'): string
    {
        $n = self::whatsappNumero();
        if ($n === '') return '';
        return 'https://wa.me/' . $n . '?text=' . rawurlencode($mensaje);
    }

    /** Número legible: +52 612 291 3090. */
    public static function whatsappFormateado(): string
    {
        $n = self::whatsappNumero();
        if ($n === '') return '';
        if (strlen($n) === 12 && str_starts_with($n, '52')) {
            return '+52 ' . substr($n, 2, 3) . ' ' . substr($n, 5, 3) . ' ' . substr($n, 8, 4);
        }
        if (strlen($n) === 10) {
            return '+52 ' . substr($n, 0, 3) . ' ' . substr($n, 3, 3) . ' ' . substr($n, 6, 4);
        }
        return '+' . $n;
    }

    /** Precio de un servicio, con override opcional por .env. */
    public static function precioServicio(string $clave): int
    {
        $mapa = [
            'piloto' => ['LANDING_PILOTO_PRECIO', 3900],
            'diagnostico' => ['LANDING_DIAGNOSTICO_PRECIO', 4900],
            'quincenal' => ['LANDING_SEGUIMIENTO_PRECIO', 3900],
            'semanal' => ['LANDING_SEGUIMIENTO_COMPLETO_PRECIO', 6900],
            'oficina' => ['LANDING_OFICINA_PRECIO', 14900],
        ];
        [$var, $defecto] = $mapa[$clave] ?? [null, 0];
        $valor = $var ? (getenv($var) ?: ($_ENV[$var] ?? '')) : '';
        $valor = (int)preg_replace('/[^0-9]/', '', (string)$valor);
        return $valor > 0 ? $valor : $defecto;
    }

    /** Formato de moneda MXN sin decimales. */
    public static function mxn(int $n): string
    {
        return '$' . number_format($n, 0, '.', ',');
    }
}
