<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\ReporteDiario;
use Model\Evidencia;
use Classes\Imagenes;
use Classes\Almacenamiento;

/** Evidencias fotográficas: línea de tiempo con las fotos de los reportes diarios y las subidas directo a una actividad */
class EvidenciaController
{
    private const POR_PAGINA = 15;   // días por página
    private const MAX_FOTOS = 10;
    private const MAX_BYTES = 15 * 1024 * 1024;

    /** [id => ['actividad' => Actividad, 'partida' => Partida]] */
    private static function actividades(Proyecto $proyecto): array
    {
        $r = [];
        foreach ($proyecto->getPartidasConActividades() as $bloque) {
            foreach ($bloque['actividades'] as $a) $r[(int)$a->id] = ['actividad' => $a, 'partida' => $bloque['partida']];
        }
        return $r;
    }

    private static function fecha(?string $f): string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$f);
        return $d && $d->format('Y-m-d') === $f ? $f : '';
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $acts = self::actividades($proyecto);
        $fActividad = (int)($_GET['actividad'] ?? 0);
        $fPartida = (int)($_GET['partida'] ?? 0);
        $fDesde = self::fecha($_GET['desde'] ?? '');
        $fHasta = self::fecha($_GET['hasta'] ?? '');
        $fDepto = trim((string)($_GET['departamento'] ?? ''));
        $fCat = trim((string)($_GET['categoria'] ?? ''));
        $fOrigen = in_array($_GET['origen'] ?? '', ['reportes', 'subidas'], true) ? $_GET['origen'] : '';

        // La actividad o partida elegida define qué actividades cuentan
        $permitidas = null;
        if ($fActividad) $permitidas = [$fActividad];
        elseif ($fPartida) $permitidas = array_keys(array_filter($acts, fn($x) => (int)$x['partida']->id === $fPartida));
        $coincide = fn(array $ids) => $permitidas === null || array_intersect($ids, $permitidas);

        $dias = [];   // fecha => ['reportes' => [...], 'subidas' => [...]]
        $totalFotos = 0;
        if ($fOrigen !== 'subidas' && $fDepto === '' && $fCat === '') {   // las fotos de los reportes no llevan departamento ni categoría
            foreach (ReporteDiario::deProyecto((int)$proyecto->id) as $r) {
                $fotos = $r->getFotos();
                if (!$fotos || !$coincide($r->getActividades())) continue;
                if (($fDesde && $r->fecha < $fDesde) || ($fHasta && $r->fecha > $fHasta)) continue;
                $dias[$r->fecha]['reportes'][] = ['id' => (int)$r->id, 'area' => $r->area, 'comentarios' => $r->comentarios, 'acts' => $r->getActividades(), 'fotos' => $fotos];
                $totalFotos += count($fotos);
            }
        }
        if ($fOrigen !== 'reportes') {
            try {
                foreach (Evidencia::deProyecto((int)$proyecto->id) as $e) {
                    if (!$coincide($e->actividad_id ? [(int)$e->actividad_id] : [])) continue;
                    if (($fDepto !== '' && $e->departamento !== $fDepto) || ($fCat !== '' && $e->categoria !== $fCat)) continue;
                    if (($fDesde && $e->fecha < $fDesde) || ($fHasta && $e->fecha > $fHasta)) continue;
                    $dias[$e->fecha]['subidas'][] = $e;
                    $totalFotos++;
                }
            } catch (\Throwable $ex) {   // migración pendiente
            }
        }
        krsort($dias);
        $totalDias = count($dias);
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $dias = array_slice($dias, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA, true);

        $router->render('evidencias/index', [
            'titulo' => 'Evidencias - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'acts' => $acts, 'dias' => $dias,
            'totalDias' => $totalDias, 'totalFotos' => $totalFotos, 'pagina' => $pagina, 'porPagina' => self::POR_PAGINA,
            'filtros' => ['actividad' => $fActividad, 'partida' => $fPartida, 'desde' => $fDesde, 'hasta' => $fHasta, 'origen' => $fOrigen, 'departamento' => $fDepto, 'categoria' => $fCat],
            'departamentos' => \Classes\Catalogo::departamentos((int)$proyecto->id), 'categorias' => \Classes\Catalogo::categorias((int)$proyecto->id, 'evidencias'),
            'bloques' => $proyecto->getPartidasConActividades(), 'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    private static function volver(int $proyectoId, string $clave = '', string $msg = ''): void
    {
        header('Location: /evidencias?proyecto=' . $proyectoId . ($clave ? '&' . $clave . '=' . urlencode($msg) : ''));
        exit;
    }

    public static function subir()
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $acts = self::actividades($proyecto);
        $actId = (int)($_POST['actividad_id'] ?? 0);
        if (!isset($acts[$actId])) self::volver($pid, 'error', 'Elige la actividad a la que pertenece la evidencia');
        $fecha = self::fecha($_POST['fecha'] ?? '') ?: date('Y-m-d');
        $depto = \Classes\Catalogo::limpiar((string)($_POST['departamento'] ?? ''));
        $cat = \Classes\Catalogo::limpiar((string)($_POST['categoria'] ?? ''));
        $f = $_FILES['fotos'] ?? null;
        $n = $f && is_array($f['name']) ? count($f['name']) : 0;
        if (!$n || $n > self::MAX_FOTOS) self::volver($pid, 'error', 'Elige entre 1 y ' . self::MAX_FOTOS . ' fotos');
        $guardadas = 0;
        for ($i = 0; $i < $n; $i++) {
            $tmp = $f['tmp_name'][$i];
            if (!$tmp || !is_uploaded_file($tmp) || $f['size'][$i] > self::MAX_BYTES) continue;
            if (!Almacenamiento::cabe($pid, (int)$f['size'][$i])) self::volver($pid, $guardadas ? 'ok' : 'error', Almacenamiento::mensajeLleno($pid));
            $nombre = Imagenes::guardarEn(Evidencia::CARPETA, $tmp, 1920, 85);
            if (!$nombre) continue;
            (new Evidencia(['proyecto_id' => $pid, 'actividad_id' => $actId, 'fecha' => $fecha, 'descripcion' => $_POST['descripcion'] ?? '', 'departamento' => $depto, 'categoria' => $cat, 'foto' => $nombre, 'usuario_id' => $_SESSION['id'] ?? null]))->guardar();
            $guardadas++;
        }
        self::volver($pid, $guardadas ? 'ok' : 'error', $guardadas ? "$guardadas foto(s) agregada(s)" : 'No se pudo guardar ninguna foto (jpg, png, webp o gif de hasta 15 MB)');
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $e = Evidencia::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($e && (int)$e->proyecto_id === (int)$proyecto->id) {
            $e->eliminarArchivo();
            $e->eliminar();
        }
        self::volver((int)$proyecto->id, 'ok', 'Evidencia eliminada');
    }
}
