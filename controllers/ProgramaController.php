<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Partida;
use Model\Actividad;
use Model\Restriccion;
use Model\TipoRestriccion;
use Classes\Calendario;
use Classes\GanttRenderer;

class ProgramaController
{
    /** Vista principal: tabla de actividades + Gantt semanal */
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        if (!$proyectoId) {
            header('Location: /proyectos');
            exit;
        }
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }

        exigir_ver_proyecto($proyecto);

        $gantt = new GanttRenderer($proyecto);
        $semanas = $gantt->getSemanas();
        $filas = $gantt->getFilas();
        $calendario = new Calendario($proyectoId);

        $router->render('programa/index', [
            'titulo' => 'Programa de Obra - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'semanas' => $semanas,
            'filas' => $filas,
            'calendario' => $calendario
        ]);
    }

    /** Endpoint AJAX para actualizar fechas de actividad */
    public static function actualizarFechaAjax()
    {
        header('Content-Type: application/json');

        if (!is_auth()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $actividadId = filter_var($_POST['actividad_id'] ?? null, FILTER_VALIDATE_INT);
        $tipo = $_POST['tipo'] ?? ''; // 'inicio' o 'fin'
        $nuevaFecha = $_POST['fecha'] ?? '';

        if (!$actividadId || !$nuevaFecha || !in_array($tipo, ['inicio', 'fin'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            exit;
        }

        $actividad = Actividad::find($actividadId);
        if (!$actividad) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Actividad no encontrada']);
            exit;
        }

        // Validar formato de fecha
        $fecha = \DateTime::createFromFormat('Y-m-d', $nuevaFecha);
        if (!$fecha) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Formato de fecha inválido']);
            exit;
        }

        $partida = Partida::find($actividad->partida_id);
        if (!puede_gestionar_proyecto(Proyecto::find($partida->proyecto_id))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $calendario = new Calendario($partida->proyecto_id);

        try {
            if ($actividad->esHito()) {
                // Un hito no tiene duración: cualquier fecha lo mueve completo
                $actividad->fecha_inicio = $nuevaFecha;
                $actividad->normalizarHito();
            } elseif ($tipo === 'inicio') {
                // Cambiar fecha inicio → recalcular fecha fin con la duración actual
                $actividad->fecha_inicio = $nuevaFecha;
                $actividad->fecha_fin = $calendario->calcularFechaFin($nuevaFecha, (int)$actividad->duracion_dias);
            } else {
                // Cambiar fecha fin → recalcular duración
                $actividad->fecha_fin = $nuevaFecha;
                $nuevaDuracion = $calendario->calcularDuracion($actividad->fecha_inicio, $nuevaFecha);
                $actividad->duracion_dias = max(1, (int)ceil($nuevaDuracion));
            }

            $resultado = $actividad->guardar();

            if ($resultado) {
                // Recalcular fechas de la partida
                $partida->recalcularFechas();

                echo json_encode([
                    'success' => true,
                    'fecha_inicio' => $actividad->fecha_inicio,
                    'fecha_fin' => $actividad->fecha_fin,
                    'duracion_dias' => $actividad->duracion_dias,
                    'duracion_real' => $calendario->calcularDuracion($actividad->fecha_inicio, $actividad->fecha_fin)
                ]);
            } else {
                throw new \Exception('Error al guardar');
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Endpoint AJAX para actualizar el % de avance de una actividad */
    public static function actualizarProgresoAjax()
    {
        header('Content-Type: application/json');
        $actividad = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth()
            ? Actividad::find(filter_var($_POST['actividad_id'] ?? null, FILTER_VALIDATE_INT)) : null;
        $partida = $actividad ? Partida::find($actividad->partida_id) : null;
        $proyecto = $partida ? Proyecto::find($partida->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $valor = $_POST['progreso'] ?? '';
        if (!is_numeric($valor)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valor inválido']);
            exit;
        }
        $actividad->progreso = max(0, min(100, (int)round((float)$valor)));
        $actividad->sincronizarStatus();
        if (!$actividad->guardar()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al guardar']);
            exit;
        }
        echo json_encode(['success' => true, 'progreso' => (int)$actividad->progreso]);
        exit;
    }

    // ==================== IMPORTAR XML / POD ====================
    public static function importar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $vista = ['titulo' => 'Importar programa - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'previa' => null];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $f = $_FILES['archivo'] ?? null;
            $ext = $f ? strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) : '';
            if (!$f || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name']) || !in_array($ext, ['xml', 'pod'], true) || $f['size'] > 30 * 1024 * 1024) {
                Proyecto::setAlerta('error', 'Sube un archivo .xml o .pod de máximo 30 MB');
            } else {
                try {
                    @set_time_limit(120);
                    $vista['previa'] = \Classes\ProgramaImportador::analizar($f['tmp_name'], (int)$proyecto->id);
                } catch (\Throwable $e) {
                    Proyecto::setAlerta('error', 'No se pudo leer el archivo: ' . $e->getMessage());
                }
            }
        }
        $vista['alertas'] = Proyecto::getAlertas();
        $router->render('programa/importar', $vista);
    }

    public static function confirmarImportacion()
    {
        $proyecto = proyecto_de_peticion(true);
        try {
            $r = \Classes\ProgramaImportador::aplicar($_POST['token'] ?? '', $proyecto);
            header('Location: /programa?proyecto=' . $proyecto->id . '&importado=' . $r['actividades'] . '&partidas=' . $r['partidas']);
        } catch (\Throwable $e) {
            header('Location: /programa/importar?proyecto=' . $proyecto->id . '&error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function cancelarImportacion()
    {
        $proyecto = proyecto_de_peticion(true);
        \Classes\ProgramaImportador::limpiar($_POST['token'] ?? '');
        header('Location: /programa/importar?proyecto=' . $proyecto->id);
        exit;
    }

    // ==================== PARTIDAS ====================
    public static function crearPartida(Router $router)
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_gestionar_proyecto($proyecto);

        $alertas = [];
        $partida = new Partida(['proyecto_id' => $proyectoId]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $partida->sincronizar($_POST);
            $partida->id = null;
            $partida->proyecto_id = $proyectoId;
            $alertas = $partida->validar();

            if (empty($alertas)) {
                // Validar código único en el proyecto
                $existe = Partida::whereAll('proyecto_id', $proyectoId);
                foreach ($existe as $p) {
                    if ($p->codigo === $partida->codigo) {
                        Partida::setAlerta('error', 'El código ya existe en este proyecto');
                        break;
                    }
                }
                $alertas = Partida::getAlertas();
            }

            if (empty($alertas)) {
                $resultado = $partida->guardar();
                if ($resultado['resultado']) {
                    header('Location: /programa?proyecto=' . $proyectoId . '&creado=partida');
                    exit;
                }
            }
        }

        $router->render('programa/crear-partida', [
            'titulo' => 'Nueva Partida',
            'proyecto' => $proyecto,
            'partida' => $partida,
            'alertas' => $alertas
        ]);
    }

    public static function editarPartida(Router $router)
    {
        redirigir_si_no_auth();
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $partida = Partida::find($id);
        if (!$partida) {
            header('Location: /proyectos');
            exit;
        }
        $proyecto = Proyecto::find($partida->proyecto_id);
        exigir_gestionar_proyecto($proyecto);
        $alertas = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $partida->sincronizar($_POST);
            $partida->id = $id;
            $partida->proyecto_id = $proyecto->id;
            $alertas = $partida->validar();
            if (empty($alertas)) {
                if ($partida->guardar()) {
                    header('Location: /programa?proyecto=' . $proyecto->id . '&editado=partida');
                    exit;
                }
            }
        }

        $router->render('programa/editar-partida', [
            'titulo' => 'Editar Partida',
            'proyecto' => $proyecto,
            'partida' => $partida,
            'alertas' => $alertas
        ]);
    }

    public static function eliminarPartida()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $partida = Partida::find($id);
            if ($partida) {
                $proyectoId = $partida->proyecto_id;
                exigir_gestionar_proyecto(Proyecto::find($proyectoId));
                $partida->eliminar();
                header('Location: /programa?proyecto=' . $proyectoId . '&eliminado=partida');
                exit;
            }
            header('Location: /proyectos');
            exit;
        }
    }

    // ==================== ACTIVIDADES ====================
    public static function crearActividad(Router $router)
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_gestionar_proyecto($proyecto);
        $partidas = $proyecto->getPartidas();
        $partidaIdSeleccionada = $_GET['partida'] ?? null;

        $alertas = [];
        $actividad = new Actividad();
        $calendario = new Calendario($proyectoId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $actividad->sincronizar($_POST);
            $actividad->hito = !empty($_POST['hito']) ? 1 : 0;   // casilla: sin marcar no viaja en el POST
            $actividad->id = null;
            $alertas = $actividad->validar();
            // La partida elegida debe pertenecer a este proyecto
            $partidaElegida = Partida::find($actividad->partida_id);
            if (!$partidaElegida || (int)$partidaElegida->proyecto_id !== (int)$proyecto->id) {
                header('Location: /proyectos');
                exit;
            }

            if (empty($alertas)) {
                // Calcular fecha_fin con calendario
                $actividad->fecha_fin = $actividad->esHito() ? $actividad->fecha_inicio : $calendario->calcularFechaFin(
                    $actividad->fecha_inicio,
                    (int)$actividad->duracion_dias
                );
                $actividad->normalizarHito();
                $actividad->sincronizarStatus();

                $resultado = $actividad->guardar();
                if ($resultado['resultado']) {
                    // Recalcular fechas de partida
                    $partida = Partida::find($actividad->partida_id);
                    $partida->recalcularFechas();
                    header('Location: /programa?proyecto=' . $proyectoId . '&creado=actividad');
                    exit;
                }
            }
        }

        $router->render('programa/crear-actividad', [
            'titulo' => 'Nueva Actividad',
            'proyecto' => $proyecto,
            'partidas' => $partidas,
            'partidaIdSeleccionada' => $partidaIdSeleccionada,
            'actividad' => $actividad,
            'alertas' => $alertas
        ]);
    }

    public static function editarActividad(Router $router)
    {
        redirigir_si_no_auth();
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $actividad = Actividad::find($id);
        if (!$actividad) {
            header('Location: /proyectos');
            exit;
        }
        $partida = Partida::find($actividad->partida_id);
        $proyecto = Proyecto::find($partida->proyecto_id);
        exigir_gestionar_proyecto($proyecto);
        $partidas = $proyecto->getPartidas();
        $calendario = new Calendario($proyecto->id);
        $alertas = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $actividad->sincronizar($_POST);
            $actividad->hito = !empty($_POST['hito']) ? 1 : 0;
            $actividad->id = $id;
            // Solo se puede mover a una partida del mismo proyecto
            $destino = Partida::find($actividad->partida_id);
            if (!$destino || (int)$destino->proyecto_id !== (int)$proyecto->id) {
                $actividad->partida_id = $partida->id;
            }
            $alertas = $actividad->validar();

            if (empty($alertas)) {
                $actividad->fecha_fin = $actividad->esHito() ? $actividad->fecha_inicio : $calendario->calcularFechaFin(
                    $actividad->fecha_inicio,
                    (int)$actividad->duracion_dias
                );
                $actividad->normalizarHito();
                $actividad->sincronizarStatus();
                if ($actividad->guardar()) {
                    $partida->recalcularFechas();
                    header('Location: /programa?proyecto=' . $proyecto->id . '&editado=actividad');
                    exit;
                }
            }
        }

        $router->render('programa/editar-actividad', [
            'titulo' => 'Editar Actividad',
            'proyecto' => $proyecto,
            'partidas' => $partidas,
            'partida' => $partida,
            'actividad' => $actividad,
            'alertas' => $alertas
        ]);
    }

    public static function eliminarActividad()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $actividad = Actividad::find($id);
            if ($actividad) {
                $partida = Partida::find($actividad->partida_id);
                $proyectoId = $partida->proyecto_id;
                exigir_gestionar_proyecto(Proyecto::find($proyectoId));
                $actividad->eliminar();
                $partida->recalcularFechas();
                header('Location: /programa?proyecto=' . $proyectoId . '&eliminado=actividad');
                exit;
            }
            header('Location: /proyectos');
            exit;
        }
    }

    // ==================== RESTRICCIONES ====================
    public static function crearRestriccion(Router $router)
    {
        redirigir_si_no_auth();
        $actividadId = filter_var($_GET['actividad'] ?? null, FILTER_VALIDATE_INT);
        $actividad = Actividad::find($actividadId);
        if (!$actividad) {
            header('Location: /proyectos');
            exit;
        }
        $partida = Partida::find($actividad->partida_id);
        $proyecto = Proyecto::find($partida->proyecto_id);
        exigir_gestionar_proyecto($proyecto);
        $tipos = TipoRestriccion::all('id ASC');

        $alertas = [];
        $restriccion = new Restriccion(['actividad_id' => $actividadId, 'fecha' => date('Y-m-d')]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $restriccion->sincronizar($_POST);
            $restriccion->id = null;
            $restriccion->actividad_id = $actividadId;
            if (!$restriccion->tipo_id) {
                Restriccion::setAlerta('error', 'Debes seleccionar un tipo de restricción');
            }
            $alertas = Restriccion::getAlertas();
            if (empty($alertas)) {
                $resultado = $restriccion->guardar();
                if ($resultado['resultado']) {
                    header('Location: /programa?proyecto=' . $proyecto->id . '&creado=restriccion');
                    exit;
                }
            }
        }

        $router->render('programa/crear-restriccion', [
            'titulo' => 'Nueva Restricción',
            'proyecto' => $proyecto,
            'actividad' => $actividad,
            'tipos' => $tipos,
            'restriccion' => $restriccion,
            'alertas' => $alertas
        ]);
    }

    public static function eliminarRestriccion()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $restriccion = Restriccion::find($id);
            if ($restriccion) {
                $actividad = Actividad::find($restriccion->actividad_id);
                $partida = Partida::find($actividad->partida_id);
                $proyectoId = $partida->proyecto_id;
                exigir_gestionar_proyecto(Proyecto::find($proyectoId));
                $restriccion->eliminar();
                header('Location: ' . (self::volverA('/acciones', $proyectoId) ?? '/programa?proyecto=' . $proyectoId . '&eliminado=restriccion'));
                exit;
            }
            header('Location: /proyectos');
            exit;
        }
    }

    public static function resolverRestriccion()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $restriccion = Restriccion::find($id);
            if ($restriccion) {
                $actividad = Actividad::find($restriccion->actividad_id);
                $partida = Partida::find($actividad->partida_id);
                exigir_gestionar_proyecto(Proyecto::find($partida->proyecto_id));
                $restriccion->estado = 'resuelta';
                $restriccion->guardar();
                header('Location: ' . (self::volverA('/acciones', $partida->proyecto_id) ?? '/programa?proyecto=' . $partida->proyecto_id));
                exit;
            }
            header('Location: /proyectos');
            exit;
        }
    }

    /** Destino de regreso permitido (solo la página de acciones rápidas del mismo proyecto) */
    private static function volverA(string $ruta, $proyectoId): ?string
    {
        return ($_POST['volver'] ?? '') === $ruta ? $ruta . '?proyecto=' . (int)$proyectoId . '#acc-restr' : null;
    }
}
