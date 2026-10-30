<?php

namespace Controllers;

use MVC\Router;
use Model\Actividad;
use Model\Partida;
use Model\Proyecto;
use Model\Restriccion;
use Classes\AccesoDb;
use Classes\Calendario;
use Classes\Dependencias;
use Classes\DependenciasMateriales;

/**
 * Línea de tiempo activa: Gantt diario del mes elegido, agrupado por partida y centrado en
 * lo que se está ejecutando (no iniciadas, en proceso y con restricciones).
 * A diferencia del Gantt del Programa (solo lectura), aquí las barras se mueven o se
 * redimensionan con el mouse y las fechas se guardan al instante (POST /api/lineatiempo/fechas).
 */
class LineatiempoController
{
    use AccesoDb;

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();

        // ?mes= solo ancla la vista inicial: el diagrama abarca todos los meses con actividades
        // y se navega dentro de la misma página (botones ‹ › y Hoy), sin filtro de mes.
        $mesAncla = null;
        $f = \DateTime::createFromFormat('!Y-m', (string)($_GET['mes'] ?? ''));
        if ($f) $mesAncla = $f->format('Y-m');
        $d = self::datos($proyecto);
        $d['lt']['mesAncla'] = $mesAncla;   // ancla inicial de la vista (no afecta a la exportación)

        $router->render('lineatiempo/index', [
            'titulo' => 'Línea de tiempo activa - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'lt' => $d['lt'],
            'partidasSel' => $d['partidasSel'],
            'filtros' => $d['filtros'],
        ]);
    }

    /**
     * Exporta la vista actual (con los filtros elegidos) a PDF o a hoja de cálculo (.ods).
     * El PDF sale en UNA sola hoja del tamaño que haga falta (ver LineatiempoPdfExporter).
     */
    public static function exportar()
    {
        $proyecto = proyecto_de_peticion();
        if (!puede_descargar()) {
            header('Location: /lineatiempo?proyecto=' . (int)$proyecto->id . '&error=' . urlencode('No tienes permiso para descargar archivos.'));
            exit;
        }
        $d = self::datos($proyecto);
        $formato = ($_GET['formato'] ?? '') === 'ods' ? 'ods' : 'pdf';
        try {
            $exporter = $formato === 'ods'
                ? (new \Classes\Exportadores\LineatiempoSheetExporter($proyecto))->setFormato('ods')
                : new \Classes\Exportadores\LineatiempoPdfExporter($proyecto);
            $ruta = $exporter->setDatos($d['lt'], $d['filtros'], $d['partidasSel'])->generar('Linea-de-tiempo');
            $mime = $formato === 'ods' ? 'application/vnd.oasis.opendocument.spreadsheet' : 'application/pdf';
            $datos = (string)file_get_contents($ruta);
            $nombre = basename($ruta);
            // Sin historial: el archivo no se conserva en el servidor (public/ es accesible por URL)
            @unlink($ruta);
            enviar_descarga($datos, $mime, $nombre);
        } catch (\Throwable $e) {
            header('Location: /lineatiempo?proyecto=' . (int)$proyecto->id . '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /**
     * Datos de la vista: los mismos para la pantalla, el enlace directo (/lineatiempo/exportar) y
     * el Centro de Exportación (/exportar). $f puede venir de la URL (vista) o del POST:
     * - partida: id o vacío (todas)
     * - estados: lista de ['no_iniciada','en_proceso','completada'] (o «estado» único de la vista)
     * - restriccion / icono: booleanos
     * - mes: 'Y-m' para aislar ese mes completo (un archivo por mes en el Centro de Exportación)
     */
    public static function datos(\Model\Proyecto $proyecto, array $f = []): array
    {
        if (!$f) {   // desde la URL (la vista): el estado es único en su formulario
            $f = [
                'partida' => $_GET['partida'] ?? null,
                'estado' => $_GET['estado'] ?? '',
                'restriccion' => !empty($_GET['restriccion']),
                'icono' => !empty($_GET['icono']),
            ];
        }
        $partidaFiltro = filter_var($f['partida'] ?? null, FILTER_VALIDATE_INT);

        // Estados: lista explícita (Centro de Exportación, en modo casillas) o el valor único de la vista
        $estadosSel = array_values(array_intersect((array)($f['estados'] ?? []), ['no_iniciada', 'en_proceso', 'completada']));
        $estado = in_array($f['estado'] ?? '', ['pendientes', 'todas', 'completadas'], true) ? $f['estado'] : 'pendientes';
        if (!$estadosSel) {
            $estadosSel = $estado === 'completadas' ? ['completada']
                : ($estado === 'todas' ? ['no_iniciada', 'en_proceso', 'completada'] : ['no_iniciada', 'en_proceso']);
        } else {
            $estado = $estadosSel === ['completada'] ? 'completadas' : (count($estadosSel) === 3 ? 'todas' : 'pendientes');
        }
        $soloRestriccion = !empty($f['restriccion']);
        $iconoActivo = !empty($f['icono']);   // casilla «Icono de actividad»: disco circular sobre cada barra
        $hoy = date('Y-m-d');

        // Mes aislado (Centro de Exportación): el diagrama cubre ese mes completo
        $mes = $mesInicio = $mesFin = null;
        $md = \DateTime::createFromFormat('!Y-m', (string)($f['mes'] ?? ''));
        if ($md) {
            $mes = $md->format('Y-m');
            $mesInicio = $md->format('Y-m-01');
            $mesFin = $md->format('Y-m-t');
        }

        $todas = Dependencias::actividades((int)$proyecto->id);   // [id => fila con partida, color, etc.]
        $restricciones = self::restriccionesActivas((int)$proyecto->id);   // [actividad_id => ['tipos' => [], 'texto' => '']]
        $iconos = self::iconosActividades((int)$proyecto->id);   // [actividad_id => '/build/img/…']

        // Selector de partidas: todas las que tienen actividades (aunque el filtro actual no las muestre)
        $partidasSel = [];
        foreach ($todas as $a) {
            $partidasSel[(int)$a['partida_id']] = $a['partida_codigo'] . ' · ' . $a['partida'];
        }
        asort($partidasSel);

        $visibles = [];
        $conRestriccion = 0;
        foreach ($todas as $a) {
            $a['id'] = (int)$a['id'];
            $a['partida_id'] = (int)$a['partida_id'];
            $a['hito'] = (int)$a['hito'];
            $a['progreso'] = (int)$a['progreso'];
            $a['restriccion'] = $restricciones[$a['id']] ?? null;
            if ($partidaFiltro && $a['partida_id'] !== $partidaFiltro) continue;
            if (!in_array($a['status'], $estadosSel, true)) continue;
            if ($soloRestriccion && !$a['restriccion']) continue;
            if (!$a['fecha_inicio'] || !$a['fecha_fin']) continue;
            if ($mes !== null && ($a['fecha_fin'] < $mesInicio || $a['fecha_inicio'] > $mesFin)) continue;   // solo lo de ese mes
            $a['icono'] = $iconos[(int)$a['id']] ?? null;   // imagen subida a la actividad (si la tiene)
            $visibles[] = $a;
            if ($a['restriccion']) $conRestriccion++;
        }

        // Partidas que quedaron con actividades visibles (con cuántas restricciones hay en ellas)
        $partidas = [];
        foreach ($visibles as $a) {
            $id = $a['partida_id'];
            if (!isset($partidas[$id])) {
                $partidas[$id] = ['id' => $id, 'codigo' => $a['partida_codigo'], 'nombre' => $a['partida'],
                    'color' => $a['color'], 'total' => 0, 'restricciones' => 0];
            }
            $partidas[$id]['total']++;
            if ($a['restriccion']) $partidas[$id]['restricciones']++;
        }

        // Rango del diagrama: el mes elegido, o del primer al último mes con actividades visibles
        // (meses completos). Tope de 3 años para no generar miles de columnas en proyectos muy largos.
        if ($mes !== null) {
            $inicioR = $mesInicio;
            $finR = $mesFin;
        } else {
            $inicioR = $finR = null;
            foreach ($visibles as $a) {
                if ($inicioR === null || $a['fecha_inicio'] < $inicioR) $inicioR = $a['fecha_inicio'];
                if ($finR === null || $a['fecha_fin'] > $finR) $finR = $a['fecha_fin'];
            }
            if ($inicioR === null) { $inicioR = date('Y-m-01'); $finR = date('Y-m-t'); }
            $inicioR = date('Y-m-01', strtotime($inicioR));
            $finR = date('Y-m-t', strtotime($finR));
            $maxDias = 1096;
            if ((int)((strtotime($finR) - strtotime($inicioR)) / 86400) + 1 > $maxDias) {
                $ancla = ($hoy >= $inicioR && $hoy <= $finR) ? $hoy : $inicioR;
                $ini = max(strtotime($inicioR), strtotime($ancla) - (int)($maxDias / 2) * 86400);
                $inicioR = date('Y-m-01', $ini);
                $finR = date('Y-m-t', $ini + ($maxDias - 1) * 86400);
            }
        }

        $calendario = new Calendario((int)$proyecto->id);
        $dias = [];
        for ($d = new \DateTime($inicioR); $d->format('Y-m-d') <= $finR; $d->modify('+1 day')) {
            $fecha = $d->format('Y-m-d');
            $dias[] = [
                'fecha' => $fecha, 'nro' => (int)$d->format('j'), 'mes' => (int)$d->format('n'), 'anio' => (int)$d->format('Y'),
                'dow' => (int)$d->format('N'), 'habil' => $calendario->esHabil($fecha),
            ];
        }

        return [
            'lt' => [
                'proyecto' => (int)$proyecto->id, 'inicio' => $inicioR, 'fin' => $finR,
                'hoy' => $hoy, 'dias' => $dias, 'partidas' => array_values($partidas),
                'actividades' => $visibles, 'total' => count($visibles), 'conRestriccion' => $conRestriccion,
                'iconos' => $iconoActivo,
            ],
            'partidasSel' => $partidasSel,
            'filtros' => ['partida' => $partidaFiltro ?: 0, 'estado' => $estado, 'restriccion' => $soloRestriccion,
                'icono' => $iconoActivo, 'estados' => $estadosSel, 'mes' => $mes],
        ];
    }

    /** Meses con actividades del proyecto: [Y-m => 'Marzo 2026'] (para elegir el mes en /exportar) */
    public static function mesesConActividades(\Model\Proyecto $proyecto): array
    {
        $MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $marcados = [];
        foreach (Dependencias::actividades((int)$proyecto->id) as $a) {
            if (!$a['fecha_inicio'] || !$a['fecha_fin']) continue;
            $ini = date('Y-m-01', strtotime($a['fecha_inicio']));
            $fin = date('Y-m-t', strtotime($a['fecha_fin']));
            for ($d = new \DateTime($ini); $d->format('Y-m') <= $fin; $d->modify('+1 month')) {
                $marcados[$d->format('Y-m')] = true;
            }
        }
        ksort($marcados);
        $res = [];
        foreach (array_keys($marcados) as $m) {
            $dt = \DateTime::createFromFormat('!Y-m', $m);
            $res[$m] = $MESES[(int)$dt->format('n')] . ' ' . $dt->format('Y');
        }
        return $res;
    }

    /**
     * Guarda las fechas de una actividad tras mover o redimensionar su barra.
     * Misma secuencia que al editar fechas en el Programa: calendario, partida,
     * materiales instalados y dependencias (modo automático).
     */
    public static function fechas()
    {
        $p = proyecto_api('gestionar');
        $actividad = Actividad::find(filter_var($_POST['actividad_id'] ?? null, FILTER_VALIDATE_INT));
        $partida = $actividad ? Partida::find($actividad->partida_id) : null;
        if (!$actividad || !$partida || (int)$partida->proyecto_id !== (int)$p->id) {
            json_respuesta(['success' => false, 'error' => 'Actividad no encontrada'], 404);
        }
        $ini = self::fechaValida($_POST['fecha_inicio'] ?? '');
        $fin = self::fechaValida($_POST['fecha_fin'] ?? '');
        if (!$ini || !$fin) json_respuesta(['success' => false, 'error' => 'Fechas inválidas'], 422);
        if ($fin < $ini) json_respuesta(['success' => false, 'error' => 'La fecha de fin no puede ser anterior a la de inicio'], 422);

        $calendario = new Calendario((int)$p->id);
        $ini = self::habilMasCercano($calendario, $ini);
        $fin = self::habilMasCercano($calendario, $fin);
        if ($fin < $ini) $fin = $ini;

        try {
            $actividad->fecha_inicio = $ini;
            if ($actividad->esHito()) {
                $actividad->normalizarHito();   // un hito no tiene duración: se mueve completo
            } else {
                $actividad->fecha_fin = $fin;
                $actividad->duracion_dias = max(1, (int)ceil($calendario->calcularDuracion($ini, $fin)));
            }
            if (!$actividad->guardar()) throw new \Exception('Error al guardar');

            $partida->recalcularFechas();
            \Model\ActividadMaterial::sincronizarInstalacion((int)$partida->proyecto_id);
            $movidas = Dependencias::propagar($p);   // modo automático
            // Los materiales nunca deben tumbar el guardado de fechas (igual que en MaterialController)
            try { DependenciasMateriales::propagar($p); } catch (\Throwable $e) { error_log('Dependencias de materiales: ' . $e->getMessage()); }

            json_respuesta([
                'success' => true, 'dependientes_movidas' => $movidas,
                'fecha_inicio' => $actividad->fecha_inicio, 'fecha_fin' => $actividad->fecha_fin,
                'duracion_dias' => (int)$actividad->duracion_dias,
                'duracion_real' => $calendario->calcularDuracion($actividad->fecha_inicio, $actividad->fecha_fin),
            ]);
        } catch (\Throwable $e) {   // \Throwable: un \Error también debe devolver JSON, no un 500 genérico
            json_respuesta(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Restricciones activas del proyecto agrupadas por actividad: [actividad_id => ['tipos' => [], 'texto' => '']] */
    /**
     * Marca una restricción como resuelta desde la burbuja de la ⚠ de la línea de tiempo.
     * Mismas reglas que el formulario del Programa: solo quien gestiona el proyecto (el token
     * CSRF llega en la cabecera X-CSRF-Token que agrega el JS global en cada fetch).
     */
    public static function resolver()
    {
        $p = proyecto_api('gestionar');
        $restriccion = Restriccion::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        $actividad = $restriccion ? Actividad::find($restriccion->actividad_id) : null;
        $partida = $actividad ? Partida::find($actividad->partida_id) : null;
        if (!$restriccion || !$partida || (int)$partida->proyecto_id !== (int)$p->id) {
            json_respuesta(['success' => false, 'error' => 'Restricción no encontrada'], 404);
        }
        if ($restriccion->estado !== 'activa') {
            json_respuesta(['success' => false, 'error' => 'La restricción ya estaba resuelta'], 409);
        }
        $restriccion->estado = 'resuelta';
        $restriccion->guardar();
        json_respuesta(['success' => true]);
    }

    /** Iconos subidos a las actividades del proyecto: [actividad_id => ruta pública]. Vacío si la migración no está aplicada */
    private static function iconosActividades(int $proyectoId): array
    {
        $out = [];
        $pid = (int)$proyectoId;
        try {
            foreach (self::filas("SELECT a.id, a.icono FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.icono IS NOT NULL AND a.icono <> ''") as $f) {
                $out[(int)$f['id']] = '/build/img/actividades-iconos/' . basename((string)$f['icono']);
            }
        } catch (\Throwable $e) {   // migración pendiente: la línea de tiempo funciona sin iconos
        }
        return $out;
    }

    private static function restriccionesActivas(int $proyectoId): array
    {
        $out = [];
        $pid = (int)$proyectoId;
        foreach (self::filas(
            "SELECT r.id, r.actividad_id, r.codigo, r.descripcion, tr.nombre AS tipo
             FROM restricciones r
             JOIN actividades a ON a.id = r.actividad_id
             JOIN partidas p ON p.id = a.partida_id
             LEFT JOIN tipos_restriccion tr ON tr.id = r.tipo_id
             WHERE p.proyecto_id = $pid AND r.estado = 'activa'
             ORDER BY r.actividad_id, r.id"
        ) as $f) {
            $id = (int)$f['actividad_id'];
            $tipo = (string)($f['tipo'] ?? '');
            if ($tipo !== '' && !in_array($tipo, $out[$id]['tipos'] ?? [], true)) $out[$id]['tipos'][] = $tipo;
            $out[$id]['texto'][] = trim(implode(' · ', array_filter([$tipo, $f['codigo'] ?? '', self::corto($f['descripcion'] ?? '', 60)])));
            // Detalle completo (con id) para la burbuja flotante de la ⚠, con su botón «Resolver»
            $out[$id]['items'][] = ['id' => (int)$f['id'], 'codigo' => (string)($f['codigo'] ?? ''), 'tipo' => $tipo, 'descripcion' => (string)($f['descripcion'] ?? '')];
            $out[$id]['total'] = ($out[$id]['total'] ?? 0) + 1;
        }
        return $out;
    }

    /** La fecha si viene en formato Y-m-d exacto; null en otro caso */
    private static function fechaValida($v): ?string
    {
        $v = trim((string)$v);
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }

    /** Acerca la fecha al día hábil más cercano (por delante si hay empate) */
    private static function habilMasCercano(Calendario $c, string $fecha): string
    {
        if ($c->esHabil($fecha)) return $fecha;
        $adelante = new \DateTime($fecha);
        $atras = new \DateTime($fecha);
        do {
            $adelante->modify('+1 day');
        } while (!$c->esHabil($adelante->format('Y-m-d')));
        do {
            $atras->modify('-1 day');
        } while (!$c->esHabil($atras->format('Y-m-d')));
        $ts = strtotime($fecha);
        return ($adelante->getTimestamp() - $ts) <= ($ts - $atras->getTimestamp())
            ? $adelante->format('Y-m-d') : $atras->format('Y-m-d');
    }
}
