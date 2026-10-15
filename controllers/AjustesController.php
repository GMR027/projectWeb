<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Partida;
use Model\Actividad;
use Model\CalendarioJornada;
use Model\CalendarioFestivo;
use Model\Exportacion;
use Classes\Calendario;

class AjustesController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }

        exigir_gestionar_proyecto($proyecto);   // jornada y festivos solo los ve quien puede editarlos
        $jornada = $proyecto->getJornada();
        $festivos = $proyecto->getFestivos();

        // Calendario del mes activo
        $mesActual = $_GET['mes'] ?? date('Y-m');
        $calendario = new Calendario($proyectoId);
        $actividades = self::obtenerActividadesDelMes($proyecto, $mesActual);

        $router->render('ajustes/index', [
            'almacenamiento' => ['desglose' => \Classes\Almacenamiento::desglose((int)$proyecto->id)],
            'titulo' => 'Ajustes - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'jornada' => $jornada,
            'festivos' => $festivos,
            'mesActual' => $mesActual,
            'actividades' => $actividades,
            'calendario' => $calendario
        ]);
    }

    /** Obtiene actividades que tienen días programados en el mes especificado */
    private static function obtenerActividadesDelMes(Proyecto $proyecto, string $mesYmd): array
    {
        $inicioMes = new \DateTime($mesYmd . '-01');
        $finMes = clone $inicioMes;
        $finMes->modify('last day of this month');

        $resultado = [];
        foreach ($proyecto->getPartidas() as $partida) {
            foreach ($partida->getActividades() as $act) {
                $inicioAct = new \DateTime($act->fecha_inicio);
                $finAct = new \DateTime($act->fecha_fin);

                // Si la actividad intersecta con el mes
                if ($inicioAct <= $finMes && $finAct >= $inicioMes) {
                    $resultado[] = [
                        'actividad' => $act,
                        'partida' => $partida,
                        'dias_en_mes' => self::calcularDiasEnMes($act, $inicioMes, $finMes)
                    ];
                }
            }
        }
        return $resultado;
    }

    /** Calcula qué días del mes la actividad está programada */
    private static function calcularDiasEnMes(Actividad $actividad, \DateTime $inicioMes, \DateTime $finMes): array
    {
        $dias = [];
        $inicio = max(new \DateTime($actividad->fecha_inicio), $inicioMes);
        $fin = min(new \DateTime($actividad->fecha_fin), $finMes);

        $curr = clone $inicio;
        while ($curr <= $fin) {
            $dias[] = $curr->format('j'); // día del mes sin ceros
            $curr->modify('+1 day');
        }
        return $dias;
    }

    /** Guardar configuración de jornada semanal */
    public static function guardarJornada()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$proyectoId) {
            header('Location: /proyectos');
            exit;
        }

        exigir_gestionar_proyecto(Proyecto::find($proyectoId));

        for ($dia = 1; $dia <= 7; $dia++) {
            $factor = isset($_POST["factor_$dia"]) ? floatval($_POST["factor_$dia"]) : 1.0;
            $factor = max(0, min(1, $factor));

            $existe = null;
            $todas = CalendarioJornada::whereAll('proyecto_id', $proyectoId);
            foreach ($todas as $j) {
                if ((int)$j->dia_semana === $dia) {
                    $existe = $j;
                    break;
                }
            }
            if ($existe) {
                $existe->factor = $factor;
                $existe->guardar();
            } else {
                $nueva = new CalendarioJornada([
                    'proyecto_id' => $proyectoId,
                    'dia_semana' => $dia,
                    'factor' => $factor
                ]);
                $nueva->guardar();
            }
        }

        header('Location: /ajustes?proyecto=' . $proyectoId . '&jornada=ok');
        exit;
    }

    /** Agregar día festivo / paro */
    public static function agregarFestivo()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $fecha = $_POST['fecha'] ?? '';
        $motivo = trim($_POST['motivo'] ?? '');
        $tipo = $_POST['tipo'] ?? 'festivo';

        exigir_gestionar_proyecto(Proyecto::find($proyectoId));
        if ($proyectoId && $fecha && $motivo) {
            $existe = CalendarioFestivo::whereAll('proyecto_id', $proyectoId);
            $duplicado = false;
            foreach ($existe as $f) {
                if ($f->fecha === $fecha) {
                    $duplicado = true;
                    break;
                }
            }
            if (!$duplicado) {
                $festivo = new CalendarioFestivo([
                    'proyecto_id' => $proyectoId,
                    'fecha' => $fecha,
                    'motivo' => $motivo,
                    'tipo' => $tipo
                ]);
                $festivo->guardar();
            }
        }
        header('Location: /ajustes?proyecto=' . $proyectoId . '&festivo=ok');
        exit;
    }

    public static function eliminarFestivo()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $festivo = CalendarioFestivo::find($id);
            // El festivo debe pertenecer al proyecto sobre el que se tiene permiso
            if ($festivo) {
                exigir_gestionar_proyecto(Proyecto::find($festivo->proyecto_id));
                $festivo->eliminar();
            }
        }
        header('Location: /ajustes?proyecto=' . $proyectoId);
        exit;
    }

    /** Página de exportación (separada) */
    public static function exportarIndex(Router $router)
    {
        redirigir_si_no_auth();
        if (!puede_descargar()) {
            header('Location: /proyectos');
            exit;
        }
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }

        exigir_ver_proyecto($proyecto);
        $router->render('exportar/index', [
            'titulo' => 'Exportar - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'lunes' => \Controllers\LookaheadController::lunesDe(''),
            'curvas' => \Model\Curva::deProyecto((int)$proyecto->id),
            'laminasVplan' => \Model\VplanLamina::deProyecto((int)$proyecto->id)
        ]);
    }

    /**
     * Exportar todo: un ZIP con todos los entregables del proyecto, excepto Last Planner.
     * Lo que no tenga datos (sin MSR, curvas o láminas) se omite y queda anotado en LEEME.txt.
     */
    public static function exportarTodo()
    {
        redirigir_si_no_auth();
        if (!puede_descargar() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $proyecto = Proyecto::find(filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT));
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_ver_proyecto($proyecto);
        if (!class_exists('ZipArchive')) {
            header('Location: /exportar?proyecto=' . $proyecto->id . '&error=' . urlencode('El servidor no tiene habilitada la extensión ZIP de PHP'));
            exit;
        }
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        $nombreBase = trim($_POST['nombre_base'] ?? '') ?: 'Exportacion';
        $lunes = \Controllers\LookaheadController::lunesDe('')->format('Y-m-d');

        $zipRuta = __DIR__ . '/../public/exportaciones/' . generar_nombre_archivo($nombreBase, 'zip', $proyecto);
        if (!is_dir(dirname($zipRuta))) mkdir(dirname($zipRuta), 0775, true);
        $zip = new \ZipArchive();
        $zip->open($zipRuta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $incluidos = [];
        $omitidos = [];
        $temporales = [];
        // Genera un archivo con el exportador y lo agrega al ZIP en la carpeta indicada
        $agregar = function (string $carpeta, string $etiqueta, callable $crear) use ($zip, &$incluidos, &$omitidos, &$temporales) {
            try {
                $ruta = $crear();
                $zip->addFile($ruta, $carpeta . '/' . basename($ruta));
                $temporales[] = $ruta;
                $incluidos[] = "$carpeta/" . basename($ruta) . "  ($etiqueta)";
            } catch (\Throwable $e) {
                $omitidos[] = "$etiqueta: " . $e->getMessage();
            }
        };

        $agregar('01_Programa', 'Programa PDF', fn() => (new \Classes\Exportadores\PdfExporter($proyecto))->generar("Programa"));
        $agregar('01_Programa', 'Programa XML (MS Project)', fn() => (new \Classes\Exportadores\MspdiXmlExporter($proyecto))->generar("Programa"));
        $agregar('01_Programa', 'Programa POD (ProjectLibre)', fn() => (new \Classes\Exportadores\PodExporter($proyecto))->generar("Programa"));
        $agregar('02_4WLA', '4WLA PDF', fn() => (new \Classes\Exportadores\LookaheadPdfExporter($proyecto))->setFechaReferencia($lunes)->generar("4WLA"));
        $agregar('02_4WLA', '4WLA Excel', fn() => (new \Classes\Exportadores\LookaheadSheetExporter($proyecto))->setFormato('xlsx')->setFechaReferencia($lunes)->generar("4WLA"));

        $hayMsr = false;
        foreach (\Model\Msr::deProyecto((int)$proyecto->id) as $msr) {
            if (!$msr->n_renglones) continue;
            $hayMsr = true;
            $base = 'MSR_' . slug_archivo($msr->nombre);
            $agregar('03_MSR', "MSR PDF ($msr->nombre)", fn() => (new \Classes\Exportadores\MsrPdfExporter($proyecto))->setMsr($msr)->generar($base));
            $agregar('03_MSR', "MSR Excel ($msr->nombre)", fn() => (new \Classes\Exportadores\MsrSheetExporter($proyecto))->setMsr($msr)->setFormato('xlsx')->generar($base));
        }
        if (!$hayMsr) {
            $omitidos[] = 'MSR: el proyecto no tiene renglones';
        }

        $curvas = \Model\Curva::deProyecto((int)$proyecto->id);
        if ($curvas) {
            $agregar('04_Curvas', 'Gráfica maestra PDF', fn() => (new \Classes\Exportadores\CurvaPdfExporter($proyecto))->setCurva(null)->generar("Curva"));
            foreach ($curvas as $c) {
                $agregar('04_Curvas', 'Curva ' . $c->nombre, fn() => (new \Classes\Exportadores\CurvaPdfExporter($proyecto))->setCurva($c)->generar('Curva'));
            }
        } else {
            $omitidos[] = 'Curvas de productividad: el proyecto no tiene curvas';
        }

        if (\Model\VplanLamina::deProyecto((int)$proyecto->id)) {
            $agregar('05_vPlan', 'vPlan PDF (todas las láminas)', fn() => (new \Classes\Exportadores\VplanPdfExporter($proyecto))->setLamina('todas')->generar("vPlan"));
        } else {
            $omitidos[] = 'vPlan: el proyecto no tiene láminas';
        }

        $leeme = "Exportación completa de {$proyecto->nombre} ({$proyecto->codigo})\r\nGenerada: " . date('d/m/Y H:i') . "\r\n"
            . "No incluye Last Planner (se exporta aparte).\r\n\r\nArchivos incluidos:\r\n- " . implode("\r\n- ", $incluidos ?: ['(ninguno)'])
            . ($omitidos ? "\r\n\r\nOmitidos:\r\n- " . implode("\r\n- ", $omitidos) : '') . "\r\n";
        $zip->addFromString('LEEME.txt', $leeme);
        $zip->close();
        foreach ($temporales as $t) @unlink($t);

        if (!$incluidos || !is_file($zipRuta)) {
            @unlink($zipRuta);
            header('Location: /exportar?proyecto=' . $proyecto->id . '&error=' . urlencode('No se pudo generar ningún archivo'));
            exit;
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipRuta) . '"');
        header('Content-Length: ' . filesize($zipRuta));
        readfile($zipRuta);
        @unlink($zipRuta);   // sin historial: no se conserva en el servidor
        exit;
    }

    /** Exportar programa */
    public static function exportar()
    {
        redirigir_si_no_auth();
        if (!puede_descargar()) {
            header('Location: /proyectos');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }

        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $formato = $_POST['formato'] ?? '';
        $nombreBase = trim($_POST['nombre_base'] ?? 'Programa_Obra');

        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_ver_proyecto($proyecto);

        try {
            switch ($formato) {
                case 'pdf':
                    $exporter = new \Classes\Exportadores\PdfExporter($proyecto);
                    break;
                case 'lookahead':
                    $salida = $_POST['salida'] ?? 'pdf';
                    if (in_array($salida, ['xlsx', 'ods'], true)) {
                        $exporter = (new \Classes\Exportadores\LookaheadSheetExporter($proyecto))
                            ->setFormato($salida)
                            ->setFechaReferencia($_POST['fecha_ref'] ?? '');
                    } else {
                        $exporter = (new \Classes\Exportadores\LookaheadPdfExporter($proyecto))
                            ->setFechaReferencia($_POST['fecha_ref'] ?? '');
                    }
                    break;
                case 'vplan':
                    $exporter = (new \Classes\Exportadores\VplanPdfExporter($proyecto))->setLamina($_POST['lamina_id'] ?? 'todas');
                    break;
                case 'lastplanner':
                    $exporter = (new \Classes\Exportadores\LastPlannerPdfExporter($proyecto))->setOpciones(
                        $_POST['partida_id'] ?? 'todas', (int)($_POST['columnas'] ?? 2)
                    );
                    break;
                case 'curva':
                    $cid = $_POST['curva_id'] ?? 'maestra';
                    $curva = null;
                    if ($cid !== 'maestra') {
                        $curva = \Model\Curva::find(filter_var($cid, FILTER_VALIDATE_INT));
                        if (!$curva || (int)$curva->proyecto_id !== (int)$proyecto->id) throw new \Exception('Curva no encontrada');
                    }
                    $salida = $_POST['salida'] ?? 'pdf';
                    $exporter = in_array($salida, ['xlsx', 'ods'], true)
                        ? (new \Classes\Exportadores\CurvaSheetExporter($proyecto))->setCurva($curva)->setFormato($salida)
                        : (new \Classes\Exportadores\CurvaPdfExporter($proyecto))->setCurva($curva);
                    break;
                case 'msr':
                    $salida = $_POST['salida'] ?? 'pdf';
                    $msr = \Model\Msr::deProyectoPorId((int)$proyecto->id, $_POST['msr_id'] ?? null);
                    if (!$msr) throw new \Exception('MSR no encontrado');
                    $exporter = in_array($salida, ['xlsx', 'ods'], true)
                        ? (new \Classes\Exportadores\MsrSheetExporter($proyecto))->setMsr($msr)->setFormato($salida)
                        : (new \Classes\Exportadores\MsrPdfExporter($proyecto))->setMsr($msr);
                    break;
                case 'submittal':
                    if (!puede_gestionar_proyecto($proyecto)) throw new \Exception('No autorizado');
                    $sub = \Model\Submittal::find(filter_var($_POST['submittal_id'] ?? null, FILTER_VALIDATE_INT));
                    if (!$sub || (int)$sub->proyecto_id !== (int)$proyecto->id) throw new \Exception('Submittal no encontrado');
                    $exporter = (new \Classes\Exportadores\SubmittalPdfExporter($proyecto))->setSubmittal($sub);
                    break;
                case 'xml':
                    $exporter = new \Classes\Exportadores\MspdiXmlExporter($proyecto);
                    break;
                case 'pod':
                    $exporter = new \Classes\Exportadores\PodExporter($proyecto);
                    break;
                default:
                    throw new \Exception('Formato no soportado');
            }

            $ruta = $exporter->generar($nombreBase);
            $nombreArchivo = basename($ruta);

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            // Sin historial: el archivo no se conserva en el servidor (public/ es accesible por URL)
            @unlink($ruta);
            exit;
        } catch (\Exception $e) {
            header('Location: /exportar?proyecto=' . $proyectoId . '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}
