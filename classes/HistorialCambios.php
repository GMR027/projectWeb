<?php

namespace Classes;

/**
 * Historial de cambios del proyecto (sección «Administración» del breadcrumb).
 *
 * `Auditoria` solo registraba lo que hacían las herramientas de IA. Esta clase agrega los cambios que se hacen a
 * mano en cualquier herramienta: la llama `Model\ActiveRecord` al crear, actualizar o eliminar una fila de una
 * tabla observada (mismo contrato que `Webhooks::avisar()`: **nunca lanza y nunca interrumpe el guardado**).
 *
 * Cómo se lee una fila:
 *   origen = 'ia'     → la escribió `Auditoria::registrar()`. Nace `aceptado` (nada cambia datos sin que el admin
 *                        lo acepte) y vive en la pestaña «Cambios aceptados».
 *   origen = 'manual' → la escribió esta clase. Nace `pendiente`: es una cola de revisión.
 *
 * Acciones sobre una fila:
 *   aceptar  → estado `aceptado`; sale de la lista de pendientes.
 *   cancelar → revierte el cambio en los datos y agrega OTRO registro (`evento = 'revertido'`), marcando el
 *              original como `revertido`. La tabla solo recibe inserciones en su historial.
 *
 * Para que las acciones de IA no aparezcan duplicadas, los puntos que aplican cambios de IA deben correr dentro
 * de `HistorialCambios::silenciar(fn () => …)`.
 *
 * **Solo cambios que hace el usuario con las herramientas.** Lo que escribe el código por su cuenta (cron, bot de
 * Telegram, tareas automáticas, lecturas que recalculan fechas solas) NO se registra: sin una persona con sesión no
 * hay nada que anotar (ver `conUsuario()`) y los cálculos derivados van dentro de `silenciar()`. Las filas que
 * quedaron de antes sin usuario tampoco se enseñan (ver `donde()`).
 */
class HistorialCambios
{
    use AccesoDb;

    public const EVENTOS = ['creado' => 'Creado', 'modificado' => 'Modificado', 'eliminado' => 'Eliminado', 'revertido' => 'Revertido'];
    public const ESTADOS = ['pendiente' => 'Pendiente', 'aceptado' => 'Aceptado', 'revertido' => 'Revertido'];
    public const POR_PAGINA = 50;

    /**
     * Herramientas que registran cambios manuales (las de IA viven en `Auditoria::HERRAMIENTAS`).
     * `seguridad`, `punch` y `normativa` las comparten a propósito con la IA: es la misma herramienta.
     */
    public const HERRAMIENTAS = [
        'programa' => 'Programa', 'dependencias' => 'Dependencias de obra', 'lineatiempo' => 'Línea de tiempo activa',
        'lookahead' => 'Lookahead', 'planificador' => 'Planificador', 'vplan' => 'vPlan', 'lastplanner' => 'Last Planner',
        'recursos' => 'Recursos y cuadrillas', 'calendario' => 'Calendario', 'reportes' => 'Reporte diario',
        'bitacora' => 'Bitácora de obra', 'minutas' => 'Minutas', 'evidencias' => 'Evidencias',
        'curvas' => 'Curvas de productividad', 'seguridad' => 'Seguridad y permisos', 'punch' => 'Punch list',
        'rfi' => 'RFI', 'msr' => 'MSR · Materiales', 'cambios' => 'Órdenes de cambio', 'lineas-base' => 'Líneas base',
        'submittals' => 'Submittals', 'catalogo-materiales' => 'Catálogo de materiales', 'costos' => 'Costos y presupuesto',
        'archivos' => 'Archivo', 'datos-obra' => 'Datos de obra', 'editor' => 'Editor', 'redactor' => 'Redactor',
        'sheetflow' => 'SheetFlow', 'view360' => 'View360', 'traductor' => 'Traductor',
        'normativa' => 'Normativa y especificaciones', 'personal' => 'Personal del proyecto',
        'campos' => 'Campos personalizados', 'ajustes' => 'Ajustes del proyecto',
    ];

    /**
     * Tablas observadas: tabla => [herramienta, tipo de entidad, etiqueta, [columnas que forman el nombre], entidad vía]
     * `entidad vía` = [tabla, columna] para apuntar la entidad al registro padre (fila de detalle); null = la fila misma.
     */
    public const TABLAS = [
        // ---- Planeación ----
        'partidas' => ['programa', 'partida', 'Partida', ['codigo', 'nombre'], null],
        'actividades' => ['programa', 'actividad', 'Actividad', ['codigo', 'nombre'], null],
        'restricciones' => ['programa', 'restriccion', 'Restricción', ['codigo', 'descripcion'], null],
        'actividad_dependencias' => ['dependencias', 'dependencia', 'Dependencia', ['tipo'], ['actividades', 'sucesora_id']],
        'dependencias_cambios' => ['dependencias', 'dependencia', 'Cambio de dependencias', ['creado'], null],
        'lookahead_config' => ['lookahead', 'proyecto', 'Configuración del Lookahead', [], null],
        'calendario_jornada' => ['calendario', 'proyecto', 'Jornada semanal', [], null],
        'calendario_festivos' => ['calendario', 'proyecto', 'Día no hábil', ['fecha', 'motivo'], null],
        'recursos' => ['recursos', 'recurso', 'Recurso', ['nombre'], null],

        // ---- Campo ----
        'reportes_diarios' => ['reportes', 'reporte', 'Reporte diario', ['fecha', 'area'], null],
        'bitacora' => ['bitacora', 'registro', 'Registro de bitácora', ['fecha', 'descripcion'], null],
        'bitacora_departamentos' => ['bitacora', 'departamento', 'Departamento de bitácora', ['nombre'], null],
        'minutas' => ['minutas', 'minuta', 'Minuta', ['fecha', 'nombre'], null],
        'evidencias' => ['evidencias', 'evidencia', 'Evidencia', ['fecha', 'descripcion'], null],
        'punch_items' => ['punch', 'punch', 'Pendiente', ['titulo'], null],
        'rfis' => ['rfi', 'rfi', 'RFI', ['numero', 'asunto'], null],
        'permisos_trabajo' => ['seguridad', 'permiso', 'Permiso de trabajo', ['titulo'], null],
        'seguridad_incidentes' => ['seguridad', 'incidente', 'Incidente de seguridad', ['fecha', 'descripcion'], null],

        // ---- Materiales y cambios ----
        'materiales' => ['catalogo-materiales', 'material', 'Material', ['nombre'], null],
        'msrs' => ['msr', 'msr', 'Reporte MSR', ['nombre'], null],
        'msr_items' => ['msr', 'material', 'Renglón de MSR', [], ['msrs', 'msr_id']],
        'msr_partidas' => ['msr', 'msr', 'Partida del MSR', ['titulo'], ['msrs', 'msr_id']],
        'submittals' => ['submittals', 'submittal', 'Submittal', ['numero_submittal', 'departamento'], null],
        'ordenes_cambio' => ['cambios', 'orden', 'Orden de cambio', ['numero', 'titulo'], null],
        'cambio_partidas' => ['cambios', 'orden', 'Efecto de orden de cambio', ['descripcion'], ['ordenes_cambio', 'orden_id']],
        'lineas_base' => ['lineas-base', 'linea-base', 'Línea base', ['nombre'], null],
        'costos_movimientos' => ['costos', 'movimiento', 'Movimiento de costo', ['fecha', 'concepto'], null],
        'curvas' => ['curvas', 'curva', 'Curva de productividad', ['nombre'], null],

        // ---- Documentos y diseño ----
        'archivos' => ['archivos', 'archivo', 'Archivo', ['nombre'], null],
        'archivo_carpetas' => ['archivos', 'archivo', 'Carpeta del archivero', ['nombre'], null],
        'archivo_categorias' => ['archivos', 'archivo', 'Categoría del archivero', ['nombre'], null],
        'editor_graficos' => ['editor', 'grafico', 'Gráfico', ['nombre'], null],
        'editor_laminas' => ['editor', 'grafico', 'Lámina del gráfico', ['nombre'], ['editor_graficos', 'grafico_id']],
        'editor_categorias' => ['editor', 'grafico', 'Categoría del editor', ['nombre'], null],
        'simbolos' => ['editor', 'simbolo', 'Símbolo', ['nombre'], null],
        'estampas' => ['editor', 'estampa', 'Estampa', ['nombre'], null],
        'cajetines' => ['editor', 'cajetin', 'Cajetín', ['nombre'], null],
        'redactor_documentos' => ['redactor', 'documento', 'Documento del Redactor', ['nombre'], null],
        'sheetflow_hojas' => ['sheetflow', 'hoja', 'Hoja de SheetFlow', ['nombre'], null],
        'vplan_laminas' => ['vplan', 'lamina', 'Lámina de vPlan', ['titulo'], null],
        'vplan_tablas' => ['vplan', 'tabla', 'Tabla de vPlan', ['titulo'], ['vplan_laminas', 'lamina_id']],
        'vplan_notas' => ['vplan', 'nota', 'Nota de vPlan', ['texto'], ['vplan_laminas', 'lamina_id']],
        'view360_graficos' => ['view360', 'vista', 'Vista 360', ['nombre'], null],
        'normativa_documentos' => ['normativa', 'norma', 'Documento de normativa', ['titulo'], null],
        'normativa_aplicaciones' => ['normativa', 'vinculo', 'Vínculo de normativa', ['referencia'], null],
        'especialidades' => ['catalogo-materiales', 'especialidad', 'Especialidad', ['nombre'], null],

        // ---- Datos del proyecto ----
        'personal' => ['personal', 'personal', 'Miembro del personal', ['nombre'], null],
        'campos_personalizados' => ['campos', 'campo', 'Campo personalizado', ['nombre'], null],
        'enlaces_proyecto' => ['ajustes', 'enlace', 'Enlace de solo lectura', ['nombre'], null],
        'proyectos' => ['ajustes', 'proyecto', 'Proyecto', ['codigo', 'nombre'], null],
        // tablas sin columna `id`: se registran, pero no se pueden cancelar automáticamente (ver SIN_ID)
        'campos_valores' => ['campos', 'campo', 'Valor de campo', [], ['campos_personalizados', 'campo_id']],
        'costos_partidas' => ['costos', 'partida', 'Presupuesto de partida', [], ['partidas', 'partida_id']],
        'curva_reales' => ['curvas', 'curva', 'Punto real de la curva', ['periodo'], ['curvas', 'curva_id']],
        'submittal_materiales' => ['submittals', 'submittal', 'Material del submittal', [], ['submittals', 'submittal_id']],
    ];

    /**
     * Tablas que no tienen columna `id`: se registran (son cambios reales del usuario) pero **no se pueden
     * cancelar automáticamente**, porque no hay con qué volver a localizar la fila.
     */
    private const SIN_ID = ['campos_valores', 'actividad_avance', 'actividad_cantidades', 'actividad_recursos',
        'costos_partidas', 'curva_reales', 'submittal_materiales', 'lineas_base_actividades', 'lineas_base_materiales', 'disenos_lista'];

    /**
     * Tablas sin `proyecto_id` que se suben por su padre: tabla => [tabla padre, columna que apunta al padre].
     * Se recorre hasta encontrar un `proyecto_id` (máximo 5 saltos).
     * Solo se observan tablas con columna `id`: sin ella no hay reversión posible (las de detalle tipo
     * `actividad_avance`, `submittal_materiales` o `curva_reales` se anotan a mano desde su herramienta).
     */
    public const PADRES = [
        'actividades' => ['partidas', 'partida_id'],
        'restricciones' => ['actividades', 'actividad_id'],
        'msr_partidas' => ['msrs', 'msr_id'],
        'editor_laminas' => ['editor_graficos', 'grafico_id'],
        'vplan_tablas' => ['vplan_laminas', 'lamina_id'],
        'vplan_notas' => ['vplan_laminas', 'lamina_id'],
        'campos_valores' => ['campos_personalizados', 'campo_id'],
        'costos_partidas' => ['partidas', 'partida_id'],
        'curva_reales' => ['curvas', 'curva_id'],
        'submittal_materiales' => ['submittals', 'submittal_id'],
    ];

    /** Columnas que no se guardan en las imágenes antes/después (contraseñas, tokens, binarios y rutas de archivo) */
    private const NO_GUARDAR = [
        'archivo_guardado', 'archivo_original', 'password', 'token', 'hash_sha256',
        // marcas de actividad operativa del propio sistema: cambian solas y ensuciarían la cola de revisión
        'clima_ultimo', 'clima_aviso', 'resumen_ultimo', 'ultimo_acceso', 'accesos',
    ];

    private static $silencio = 0;   // contador anidado: silenciar() se puede anidar sin romper
    private static $disponible;     // ¿ya existen las columnas nuevas? (migración pendiente no debe romper nada)

    /**
     * ¿La migración `migracion_historial_cambios.sql` ya se aplicó? Si no, no se intenta escribir ni filtrar por
     * `origen`/`estado` (el resto de la plataforma sigue funcionando igual que antes).
     */
    public static function disponible(): bool
    {
        if (self::$disponible !== null) return self::$disponible;
        $n = self::filas("SELECT COUNT(*) n FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auditoria_cambios' AND column_name = 'origen'");
        return self::$disponible = (int)($n[0]['n'] ?? 0) > 0;
    }

    // ===================================================================== silencio

    /**
     * Ejecuta $fn sin registrar nada en el historial. Lo deben usar los puntos que aplican cambios de la IA
     * (ellos ya los anotan en `Auditoria`) y las importaciones/restauraciones masivas, para no llenar la cola de
     * revisión con filas que otra pantalla ya explica o con el detalle fila por fila de una carga.
     * Se puede anidar: el silencio dura lo que dure la llamada.
     */
    public static function silenciar(callable $fn)
    {
        self::$silencio++;
        try {
            return $fn();
        } finally {
            self::$silencio--;
        }
    }

    public static function silenciando(): bool
    {
        return self::$silencio > 0;
    }

    /**
     * ¿Hay una persona con sesión detrás de lo que se está escribiendo? Sin usuario no se registra nada: lo que hace
     * el código solo (cron, bot de Telegram, importaciones automáticas, tareas programadas) no es un cambio del
     * usuario y ensuciaría el historial. La sesión siempre está activa antes de que corra un controlador de la web,
     * así que la API REST y la app de escritorio (que traen la de siempre) sí registran.
     */
    public static function conUsuario(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return false;   // CLI y cron: no hay sesión de nadie
        return !empty($_SESSION['id']) && trim((string)($_SESSION['nombre'] ?? '')) !== '';
    }

    /** ¿Esta tabla se registra en el historial? (no si está silenciada, no hay usuario o falta la migración) */
    public static function observa(string $tabla): bool
    {
        return self::$silencio === 0 && self::conUsuario() && isset(self::TABLAS[$tabla]) && self::disponible();
    }

    // ===================================================================== captura (la llama ActiveRecord)

    /** Fila actual de una tabla observada (o null si no existe / no se observa) */
    public static function leer(string $tabla, int $id): ?array
    {
        if (!self::observa($tabla) || $id <= 0) return null;
        try {
            return self::filas('SELECT * FROM ' . $tabla . ' WHERE id = ' . $id . ' LIMIT 1')[0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Fila antes de que se actualice; null si la tabla no se observa o está silenciada */
    public static function antesDe(string $tabla, int $id): ?array
    {
        return self::leer($tabla, $id);
    }

    /**
     * Para las escrituras que **no pasan por `ActiveRecord`** (SQL directo): toma la imagen de la fila, corre
     * `$escribir` y anota el cambio si de verdad cambió algo. Devuelve lo que devuelva `$escribir`.
     *
     * `$id` es el registro que se toca; para un INSERT pásalo en 0 (se usa el id insertado) o devuelve tú el id
     * desde `$escribir`. Si no se pudo leer la fila no se anota nada: mejor perder un registro que inventarlo.
     */
    public static function escribir(string $tabla, string $evento, int $id, callable $escribir)
    {
        if (!self::observa($tabla)) return $escribir();
        $antes = $evento === 'creado' ? null : self::leer($tabla, $id);
        $r = $escribir();
        $nuevo = $id;
        if ($evento === 'creado') $nuevo = is_numeric($r) ? (int)$r : (int)self::db()->insert_id;
        $despues = $evento === 'eliminado' ? null : self::leer($tabla, $nuevo);
        if ($evento !== 'eliminado' && $despues === null) return $r;
        self::desdeFila($tabla, $evento, $nuevo, $antes, $despues);
        return $r;
    }

    /**
     * Para tablas **sin columna `id`** (o cuando quien escribe ya tiene las imágenes): se registran igualmente,
     * pero `$idRef` queda en 0 y el cambio no se puede cancelar desde la lista (ver `SIN_ID`).
     * `$antes`/`$despues` solo deben traer las columnas que se tocaron, más las que identifican la fila.
     */
    public static function desdeImagen(string $tabla, string $evento, ?array $antes, ?array $despues): void
    {
        try {
            if (!self::observa($tabla)) return;
            if (!in_array($evento, ['creado', 'modificado', 'eliminado'], true)) return;
            if ($antes === null && $despues === null) return;
            self::desdeFila($tabla, $evento, 0, $antes, $despues);
        } catch (\Throwable $e) {
            error_log('HistorialCambios::desdeImagen: ' . $e->getMessage());
        }
    }

    /** Registra el cambio. Nunca lanza: la auditoría no debe impedir el guardado. */
    public static function desdeFila(string $tabla, string $evento, int $id, ?array $antes, ?array $despues): void
    {
        try {
            if (!self::observa($tabla) || !in_array($evento, ['creado', 'modificado', 'eliminado'], true)) return;
            [$herramienta, $tipo, $etiqueta, $colsNombre] = self::TABLAS[$tabla];

            // Fila completa: de ahí salen el nombre, la entidad y el proyecto (la resolución no puede usar el recorte).
            // Manda la fila nueva si existe (así el nombre y la búsqueda son los de hoy); si se borró, la vieja.
            // Las tablas sin columna `id` llegan con $id = 0 y no se les inyecta uno inventado.
            $conId = fn(array $a) => $id > 0 ? ['id' => $id] + $a : $a;
            $completa = $conId($despues ?: $antes ?: []);
            if ($completa === []) return;

            if ($evento === 'modificado') {
                // Solo las columnas que de verdad cambiaron: la reversión toca lo mínimo y el JSON no se infla
                $viejo = self::soloCambios($antes ?? [], $despues ?? []);
                if ($viejo === []) return;                      // no cambió nada visible: no es un cambio
                $nuevo = [];
                foreach (array_keys($viejo) as $col) $nuevo[$col] = $despues[$col] ?? null;
                $antes = $conId($viejo);
                $despues = $conId($nuevo);
            } elseif ($evento === 'creado') {
                $antes = null;
                $despues = $completa;
            } else {   // eliminado: hace falta la fila completa para poder volver a insertarla
                $antes = $completa;
                $despues = null;
            }

            [$pid, $entidad] = self::entidad($tabla, $id, $tipo, $completa, $colsNombre);
            if (!$pid) return;

            $nombre = self::nombreDe($tabla, $completa, $colsNombre);
            if ($tabla === 'actividades') $nombre = CambiosLegibles::elemento('actividad', $id, $nombre, $pid);
            $resumen = self::resumen($evento, $etiqueta, $nombre, $antes, $despues, $pid);

            self::anotar($pid, 'manual', $herramienta, $evento, $tabla, $resumen, $antes, $despues, $entidad, 'pendiente');
        } catch (\Throwable $e) {
            error_log('HistorialCambios::desdeFila: ' . $e->getMessage());
        }
    }

    /** Solo las columnas de $a cuyo valor difiere en $b (excluye id) */
    private static function soloCambios(array $a, array $b): array
    {
        $r = [];
        foreach ($a as $k => $v) {
            if ($k === 'id' || in_array($k, self::NO_GUARDAR, true)) continue;
            if (!array_key_exists($k, $b)) continue;
            if ((string)$v === (string)($b[$k] ?? '')) continue;
            $r[$k] = $v;
        }
        return $r;
    }

    // ===================================================================== resolución

    /** [proyecto_id, [tipo, id, nombre]] de la fila; proyecto_id = 0 si no se pudo resolver */
    private static function entidad(string $tabla, int $id, string $tipoFila, array $fila, array $colsNombre): array
    {
        $pid = self::proyectoDe($tabla, $fila, $id);
        $def = self::TABLAS[$tabla] ?? null;
        $via = $def[4] ?? null;

        if ($via !== null) {
            [$tablaEnt, $col] = $via;
            $eid = (int)($fila[$col] ?? 0);
            $nombre = $eid ? self::nombreDe($tablaEnt, self::filaDe($tablaEnt, $eid) ?: [], self::TABLAS[$tablaEnt][3] ?? []) : '';
            return [$pid, [$tipoFila, $eid ?: null, $nombre]];
        }
        return [$pid, [$tipoFila, $id, self::nombreDe($tabla, $fila, $colsNombre)]];
    }

    private static function filaDe(string $tabla, int $id): ?array
    {
        if ($id <= 0) return null;
        $f = self::filas('SELECT * FROM ' . $tabla . ' WHERE id = ' . $id . ' LIMIT 1');
        return $f[0] ?? null;
    }

    /** proyecto_id de la fila, subiendo por PADRES si la tabla no lo trae (el proyecto es su propio proyecto) */
    public static function proyectoDe(string $tabla, array $fila, int $id = 0): int
    {
        if ($tabla === 'proyectos') return (int)($fila['id'] ?? $id);
        for ($i = 0; $i < 5; $i++) {
            if (isset($fila['proyecto_id'])) return (int)$fila['proyecto_id'];
            $padre = self::PADRES[$tabla] ?? null;
            if ($padre === null) return 0;
            [$tablaPadre, $col] = $padre;
            $fk = (int)($fila[$col] ?? $id);
            if ($fk <= 0) return 0;
            $fila = self::filaDe($tablaPadre, $fk) ?? [];
            $tabla = $tablaPadre;
        }
        return 0;
    }

    /** Nombre legible de una fila según las columnas definidas para su tabla */
    private static function nombreDe(string $tabla, array $fila, array $cols): string
    {
        $partes = [];
        foreach ($cols as $c) {
            $v = $fila[$c] ?? null;
            if ($v === null || $v === '') continue;
            $partes[] = trim((string)$v);
        }
        if (!$partes && isset($fila['id'])) $partes[] = '#' . (int)$fila['id'];
        return mb_substr(implode(' ', $partes), 0, 180);
    }

    /** Frase que se muestra en la lista */
    private static function resumen(string $evento, string $etiqueta, string $nombre, ?array $antes, ?array $despues, int $pid): string
    {
        $que = mb_strtolower($etiqueta) . ($nombre !== '' ? ' ' . $nombre : '');
        if ($evento === 'creado') return 'Se creó ' . $que;
        if ($evento === 'eliminado') return 'Se eliminó ' . $que;

        $cambios = [];
        $campos = array_diff(array_keys($despues ?? []), ['id']);
        foreach (array_slice($campos, 0, 3) as $c) {
            $cambios[] = CambiosLegibles::etiqueta($c) . ': ' . CambiosLegibles::valor($c, $antes[$c] ?? null, $pid, true)
                . ' → ' . CambiosLegibles::valor($c, $despues[$c] ?? null, $pid, true);
        }
        $extra = count($campos) - 3;
        $txt = implode(' · ', $cambios);
        if ($extra > 0) $txt .= ($txt !== '' ? ' · ' : '') . "+$extra campo(s) más";
        return mb_substr($que . ($txt !== '' ? ' — ' . $txt : ''), 0, 500);
    }

    /**
     * Ruta de entrada de cada herramienta: si no hay un enlace directo al elemento, «Ir al cambio» lleva aquí.
     * Cubre también las claves de `Auditoria::HERRAMIENTAS` (los cambios hechos por la IA).
     */
    public const RUTAS = [
        'programa' => '/programa', 'dependencias' => '/dependencias', 'lineatiempo' => '/lineatiempo',
        'lookahead' => '/lookahead', 'planificador' => '/planificador', 'vplan' => '/vplan',
        'lastplanner' => '/lastplanner', 'recursos' => '/recursos', 'calendario' => '/calendario',
        'reportes' => '/reportes', 'bitacora' => '/bitacora', 'minutas' => '/minutas', 'evidencias' => '/evidencias',
        'curvas' => '/curvas', 'seguridad' => '/seguridad', 'punch' => '/punch', 'rfi' => '/rfi',
        'msr' => '/msr', 'cambios' => '/cambios', 'lineas-base' => '/lineas-base', 'submittals' => '/submittals',
        'catalogo-materiales' => '/materiales', 'costos' => '/costos', 'archivos' => '/archivos',
        'datos-obra' => '/datos-obra', 'editor' => '/graficos', 'redactor' => '/redactor', 'sheetflow' => '/sheetflow',
        'view360' => '/view360', 'traductor' => '/traductor', 'normativa' => '/normativa',
        'personal' => '/directorio', 'campos' => '/campos', 'ajustes' => '/ajustes',
        // las de la IA
        'programador' => '/programador', 'inconsistencias' => '/inconsistencias', 'materiales' => '/analisis-materiales',
        'informes' => '/informes-ia', 'plantillas' => '/plantillas', 'ensamblador' => '/ensamblador-documentos',
        'ordenar' => '/programa', 'tabla-documento' => '/vplan',
    ];

    /** Pantalla del elemento según su tipo (recibe `?proyecto=&id=`); si no está, se cae a la herramienta */
    private const ENTIDADES = [
        'actividad' => '/programa/actividades/editar', 'restriccion' => '/programa/restricciones/editar',
        'dependencia' => '/dependencias', 'material' => '/materiales/editar', 'submittal' => '/submittals/editar',
        'orden' => '/cambios/ver', 'archivo' => '/archivos/ver', 'minuta' => '/minutas/ver',
        'reporte' => '/reportes/ver', 'punch' => '/punch/ver', 'permiso' => '/seguridad', 'incidente' => '/seguridad',
        'msr' => '/msr/ver', 'curva' => '/curvas/ver', 'grafico' => '/graficos/abrir',
        'documento' => '/redactor/abrir', 'hoja' => '/sheetflow/abrir', 'norma' => '/normativa/documento',
        'lamina' => '/vplan/ver', 'tabla' => '/vplan/tablas/editar', 'recurso' => '/recursos',
        'movimiento' => '/costos', 'campo' => '/campos', 'enlace' => '/ajustes', 'proyecto' => '/ajustes',
        'departamento' => '/bitacora/departamentos', 'especialidad' => '/directorio', 'personal' => '/directorio',
        'vista' => '/view360',
    ];

    /**
     * Enlace a la pantalla donde ocurrió el cambio (para el botón «Ver detalle»). Siempre devuelve algo navegable:
     * el elemento directo si existe, o la entrada de su herramienta.
     */
    public static function enlace(array $fila, int $pid): string
    {
        $tipo = (string)($fila['entidad_tipo'] ?? '');
        $eid = (int)($fila['entidad_id'] ?? 0);
        $base = self::RUTAS[(string)($fila['herramienta'] ?? '')] ?? '/proyectos';
        if ($tipo === 'partida' && $eid > 0) return '/programa?proyecto=' . $pid . '&partida=' . $eid;
        $ruta = self::ENTIDADES[$tipo] ?? null;
        return ($ruta === null || $eid <= 0) ? $base . '?proyecto=' . $pid : $ruta . '?proyecto=' . $pid . '&id=' . $eid;
    }

    /** Acepta de una vez todos los pendientes que coincidan con el filtro («Aceptar todo lo que se ve») */
    public static function aceptarTodos(int $pid, array $f = []): int
    {
        try {
            $db = self::db();
            $u = $db->real_escape_string(mb_substr(function_exists('usuario_actual') ? (usuario_actual()['nombre'] ?? '') : '', 0, 150));
            return (int)$db->query("UPDATE auditoria_cambios SET estado = 'aceptado', estado_en = NOW(), estado_por = '$u' WHERE " . self::donde($pid, $f) . " AND estado = 'pendiente'")->affected_rows;
        } catch (\Throwable $e) {
            error_log('HistorialCambios::aceptarTodos: ' . $e->getMessage());
            return 0;
        }
    }

    // ===================================================================== escritura

    private static function anotar(int $pid, string $origen, string $herramienta, string $evento, string $accion, string $resumen, ?array $antes, ?array $despues, array $entidad, string $estado): void
    {
        if (!self::conUsuario()) return;   // red de seguridad: sin usuario no hay cambio del usuario que anotar
        $db = self::db();
        [$tipo, $eid, $nombre] = $entidad;
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $js = fn($v) => $v === null ? 'NULL' : "'" . $esc(json_encode($v, JSON_UNESCAPED_UNICODE)) . "'";
        $usuario = function_exists('usuario_actual') ? (usuario_actual()['nombre'] ?? '') : '';
        $ahora = $estado === 'pendiente' ? 'NULL' : 'NOW()';

        $db->query("INSERT INTO auditoria_cambios (proyecto_id, origen, herramienta, evento, estado, estado_en, estado_por, accion, entidad_tipo, entidad_id, entidad_nombre, resumen, antes_json, despues_json, usuario) VALUES ("
            . $pid . ", '$origen', '" . $esc(mb_substr($herramienta, 0, 30)) . "', '$evento', '$estado', $ahora, '" . $esc(mb_substr($usuario, 0, 150)) . "', '"
            . $esc(mb_substr($accion, 0, 40)) . "', '" . $esc(mb_substr($tipo, 0, 20)) . "', " . ($eid === null ? 'NULL' : (int)$eid) . ", '"
            . $esc(mb_substr((string)$nombre, 0, 255)) . "', '" . $esc(mb_substr($resumen, 0, 500)) . "', {$js($antes)}, {$js($despues)}, '" . $esc(mb_substr($usuario, 0, 150)) . "')");
    }

    /**
     * Anota un cambio a mano para lo que no pasa por `ActiveRecord` (SQL directo, listas, configuraciones).
     * `$tabla` es opcional: si se indica, la fila se puede cancelar (revertir) desde la lista; si no, solo se verá.
     */
    public static function registrar(int $pid, string $herramienta, string $evento, string $resumen, ?array $antes = null, ?array $despues = null, ?array $entidad = null, string $tabla = ''): void
    {
        try {
            if (self::$silencio > 0 || !self::conUsuario()) return;
            if (!in_array($evento, ['creado', 'modificado', 'eliminado'], true)) $evento = 'modificado';
            self::anotar($pid, 'manual', $herramienta, $evento, $tabla, $resumen, $antes, $despues, $entidad ?? ['', null, ''], 'pendiente');
        } catch (\Throwable $e) {
            error_log('HistorialCambios::registrar: ' . $e->getMessage());
        }
    }

    // ===================================================================== consulta

    private static function donde(int $pid, array $f): string
    {
        $db = self::db();
        $w = ["proyecto_id = $pid"];
        // Solo cambios hechos por alguien: las filas que escribió el sistema sin usuario no se muestran
        // (ni en la lista, ni en los contadores, ni en el Excel/PDF)
        $w[] = "usuario <> ''";
        $origen = (string)($f['origen'] ?? '');
        if (in_array($origen, ['ia', 'manual'], true)) $w[] = "origen = '$origen'";
        $estado = (string)($f['estado'] ?? '');
        if (isset(self::ESTADOS[$estado])) $w[] = "estado = '$estado'";
        $herr = (string)($f['herramienta'] ?? '');
        if ($herr !== '' && isset(self::herramientas()[$herr])) $w[] = "herramienta = '" . $db->real_escape_string($herr) . "'";
        if (isset(self::EVENTOS[$f['evento'] ?? ''])) $w[] = "evento = '{$f['evento']}'";
        if (trim($f['usuario'] ?? '') !== '') $w[] = "usuario = '" . $db->real_escape_string(trim($f['usuario'])) . "'";
        foreach (['desde' => '>=', 'hasta' => '<='] as $k => $op) {
            $d = \DateTime::createFromFormat('Y-m-d', $f[$k] ?? '');
            if ($d && $d->format('Y-m-d') === $f[$k]) $w[] = "creado $op '" . $f[$k] . ($k === 'hasta' ? " 23:59:59" : " 00:00:00") . "'";
        }
        if (trim($f['q'] ?? '') !== '') {
            $q = $db->real_escape_string(str_replace(['%', '_'], ['\\%', '\\_'], trim($f['q'])));
            $w[] = "(resumen LIKE '%$q%' OR entidad_nombre LIKE '%$q%')";
        }
        return implode(' AND ', $w);
    }

    /** Catálogo de herramientas para los filtros: las manuales + las de IA */
    public static function herramientas(): array
    {
        static $cache = null;
        return $cache ??= self::HERRAMIENTAS + Auditoria::HERRAMIENTAS;
    }

    public static function contar(int $pid, array $f = []): int
    {
        return (int)(self::filas('SELECT COUNT(*) n FROM auditoria_cambios WHERE ' . self::donde($pid, $f))[0]['n'] ?? 0);
    }

    public static function listar(int $pid, array $f = [], int $pagina = 1, int $porPagina = self::POR_PAGINA): array
    {
        $off = max(0, ($pagina - 1) * $porPagina);
        $filas = self::filas('SELECT * FROM auditoria_cambios WHERE ' . self::donde($pid, $f) . " ORDER BY id DESC LIMIT $porPagina OFFSET $off");
        foreach ($filas as &$x) {
            $x['antes'] = $x['antes_json'] !== null ? json_decode($x['antes_json'], true) : null;
            $x['despues'] = $x['despues_json'] !== null ? json_decode($x['despues_json'], true) : null;
        }
        return $filas;
    }

    /** Fila suelta validando que pertenezca al proyecto */
    public static function fila(int $pid, int $id): ?array
    {
        $f = self::filas('SELECT * FROM auditoria_cambios WHERE id = ' . $id . ' AND proyecto_id = ' . $pid . ' LIMIT 1');
        if (!$f) return null;
        $f[0]['antes'] = $f[0]['antes_json'] !== null ? json_decode($f[0]['antes_json'], true) : null;
        $f[0]['despues'] = $f[0]['despues_json'] !== null ? json_decode($f[0]['despues_json'], true) : null;
        return $f[0];
    }

    public static function usuarios(int $pid): array
    {
        return array_column(self::filas("SELECT DISTINCT usuario FROM auditoria_cambios WHERE proyecto_id = $pid AND usuario <> '' ORDER BY usuario"), 'usuario');
    }

    // ===================================================================== acciones

    /** Acepta el cambio: sale de la cola de pendientes. Nunca borra el registro. */
    public static function aceptar(int $pid, int $id): bool
    {
        try {
            $db = self::db();
            $u = function_exists('usuario_actual') ? (usuario_actual()['nombre'] ?? '') : '';
            return (bool)$db->query("UPDATE auditoria_cambios SET estado = 'aceptado', estado_en = NOW(), estado_por = '"
                . $db->real_escape_string(mb_substr($u, 0, 150)) . "' WHERE id = $id AND proyecto_id = $pid AND estado = 'pendiente'");
        } catch (\Throwable $e) {
            error_log('HistorialCambios::aceptar: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancela el cambio: revierte los datos al estado anterior y deja un registro nuevo de la reversión.
     * Devuelve ['ok' => bool, 'error' => string|null].
     */
    public static function revertir(int $pid, int $id): array
    {
        $fila = self::fila($pid, $id);
        if (!$fila) return ['ok' => false, 'error' => 'Registro no encontrado'];
        if ($fila['estado'] === 'revertido') return ['ok' => false, 'error' => 'Ese cambio ya se revirtió'];
        if ($fila['origen'] !== 'manual') return ['ok' => false, 'error' => 'Solo se pueden cancelar cambios hechos a mano'];
        $tabla = (string)$fila['accion'];
        if ($tabla === '' || !preg_match('/^[a-z0-9_]+$/', $tabla) || in_array($tabla, self::SIN_ID, true)) {
            return ['ok' => false, 'error' => 'Ese cambio no se puede cancelar automáticamente (no corresponde a una sola fila localizable). Corrígelo directamente en la herramienta.'];
        }

        $idRef = (int)(($fila['antes']['id'] ?? $fila['despues']['id'] ?? 0));
        if ($idRef <= 0) return ['ok' => false, 'error' => 'Ese cambio no se puede cancelar automáticamente: no hay una fila que localizar. Corrígelo directamente en la herramienta.'];
        $antes = $fila['antes'] ?? [];

        $ahora = self::filas('SELECT * FROM ' . $tabla . ' WHERE id = ' . $idRef . ' LIMIT 1')[0] ?? null;
        $error = null;

        try {
            self::silenciar(function () use ($fila, $tabla, $idRef, $antes, $ahora, &$error) {
                $db = self::db();
                switch ($fila['evento']) {
                    case 'creado':   // deshacer una creación = quitar lo que se creó
                        if ($ahora !== null) $db->query('DELETE FROM ' . $tabla . ' WHERE id = ' . $idRef . ' LIMIT 1');
                        break;
                    case 'modificado':  // volver a escribir los valores anteriores de las columnas que cambiaron
                        if ($ahora === null) { $error = 'El registro ya no existe'; break; }
                        $set = [];
                        foreach ($antes as $col => $v) {
                            if (!preg_match('/^[a-z0-9_]+$/', $col) || $col === 'id') continue;
                            $set[] = $col . ' = ' . ($v === null ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'");
                        }
                        if ($set) $db->query('UPDATE ' . $tabla . ' SET ' . implode(', ', $set) . ' WHERE id = ' . $idRef . ' LIMIT 1');
                        break;
                    case 'eliminado':   // volver a insertar la fila tal como estaba
                        if ($ahora !== null) { $error = 'Ese registro ya vuelve a existir'; break; }
                        $cols = array_keys($antes);
                        $ok = [];
                        foreach ($cols as $col) if (preg_match('/^[a-z0-9_]+$/', $col)) $ok[] = $col;
                        if (!$ok) { $error = 'No se conservaron los datos originales'; break; }
                        $vals = [];
                        foreach ($ok as $col) $vals[] = $antes[$col] === null ? 'NULL' : "'" . $db->real_escape_string((string)$antes[$col]) . "'";
                        $db->query('INSERT INTO ' . $tabla . ' (' . implode(', ', $ok) . ') VALUES (' . implode(', ', $vals) . ')');
                        break;
                }
            });
        } catch (\Throwable $e) {
            error_log('HistorialCambios::revertir: ' . $e->getMessage());
            $error = 'No se pudo revertir: ' . $e->getMessage();
        }

        if ($error === null) {
            $db = self::db();
            $db->query("UPDATE auditoria_cambios SET estado = 'revertido', estado_en = NOW(), estado_por = '"
                . $db->real_escape_string(mb_substr(function_exists('usuario_actual') ? (usuario_actual()['nombre'] ?? '') : '', 0, 150))
                . "' WHERE id = $id AND proyecto_id = $pid");
            // El registro de la reversión es una fila nueva: el original queda como prueba de lo que pasó
            try {
                self::anotar($pid, 'manual', (string)$fila['herramienta'], 'revertido', $tabla,
                    'Revertido: ' . $fila['resumen'], $ahora, $antes ?: null,
                    [$fila['entidad_tipo'], $fila['entidad_id'] !== null ? (int)$fila['entidad_id'] : null, $fila['entidad_nombre']], 'aceptado');
            } catch (\Throwable $e) {
                error_log('HistorialCambios::revertir (anotar): ' . $e->getMessage());
            }
        }

        return ['ok' => $error === null, 'error' => $error, 'nota' => $fila['evento'] === 'eliminado' && $error === null
            ? 'Se recuperó el registro. Lo que dependía de él (fotos, partidas hijas, relaciones…) se había borrado con él y no se recupera.' : null];
    }
}
