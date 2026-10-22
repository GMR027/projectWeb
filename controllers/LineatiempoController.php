<?php

namespace Controllers;

use MVC\Router;
use Model\Actividad;
use Model\Partida;
use Model\Proyecto;
use Classes\AccesoDb;
use Classes\Calendario;
use Classes\Dependencias;

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

        // Filtros (quedan en la URL: se puede recargar, compartir o marcar como favorita).
        // Ya no hay filtro de mes: el diagrama abarca todos los meses con actividades y se
        // navega dentro de la misma página (botones ‹ › y Hoy). ?mes= solo ancla la vista inicial.
        $mesAncla = null;
        $f = \DateTime::createFromFormat('!Y-m', (string)($_GET['mes'] ?? ''));
        if ($f) $mesAncla = $f->format('Y-m');
        $partidaFiltro = filter_var($_GET['partida'] ?? null, FILTER_VALIDATE_INT);
        $estado = in_array($_GET['estado'] ?? '', ['pendientes', 'todas', 'completadas'], true) ? $_GET['estado'] : 'pendientes';
        $soloRestriccion = !empty($_GET['restriccion']);
        $iconoActivo = !empty($_GET['icono']);   // casilla «Icono de actividad»: disco circular sobre cada barra
        $hoy = date('Y-m-d');

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
            if ($estado === 'pendientes' && $a['status'] === 'completada') continue;
            if ($estado === 'completadas' && $a['status'] !== 'completada') continue;
            if ($soloRestriccion && !$a['restriccion']) continue;
            if (!$a['fecha_inicio'] || !$a['fecha_fin']) continue;
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

        // Rango del diagrama: del primer al último mes con actividades visibles (meses completos).
        // Tope de 3 años para no generar miles de columnas en proyectos muy largos.
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

        $calendario = new Calendario((int)$proyecto->id);
        $dias = [];
        for ($d = new \DateTime($inicioR); $d->format('Y-m-d') <= $finR; $d->modify('+1 day')) {
            $fecha = $d->format('Y-m-d');
            $dias[] = [
                'fecha' => $fecha, 'nro' => (int)$d->format('j'), 'mes' => (int)$d->format('n'), 'anio' => (int)$d->format('Y'),
                'dow' => (int)$d->format('N'), 'habil' => $calendario->esHabil($fecha),
            ];
        }

        $router->render('lineatiempo/index', [
            'titulo' => 'Línea de tiempo activa - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'lt' => [
                'proyecto' => (int)$proyecto->id, 'inicio' => $inicioR, 'fin' => $finR, 'mesAncla' => $mesAncla,
                'hoy' => $hoy, 'dias' => $dias, 'partidas' => array_values($partidas),
                'actividades' => $visibles, 'total' => count($visibles), 'conRestriccion' => $conRestriccion,
                'iconos' => $iconoActivo,
            ],
            'partidasSel' => $partidasSel,
            'filtros' => ['partida' => $partidaFiltro ?: 0, 'estado' => $estado, 'restriccion' => $soloRestriccion, 'icono' => $iconoActivo],
        ]);
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

            json_respuesta([
                'success' => true, 'dependientes_movidas' => $movidas,
                'fecha_inicio' => $actividad->fecha_inicio, 'fecha_fin' => $actividad->fecha_fin,
                'duracion_dias' => (int)$actividad->duracion_dias,
                'duracion_real' => $calendario->calcularDuracion($actividad->fecha_inicio, $actividad->fecha_fin),
            ]);
        } catch (\Exception $e) {
            json_respuesta(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Restricciones activas del proyecto agrupadas por actividad: [actividad_id => ['tipos' => [], 'texto' => '']] */
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
            "SELECT r.actividad_id, r.codigo, r.descripcion, tr.nombre AS tipo
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
