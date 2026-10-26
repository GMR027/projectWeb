<?php

namespace Controllers;

use MVC\Router;
use Classes\Rfi;
use Classes\Revisiones;
use Model\Grafico;
use Model\GraficoCategoria;

/** RFI: registro con responsable, fecha límite y la actividad que bloquea (ver Classes\Rfi) */
class RfiController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Rfi::disponible();
        $filtro = $_GET['estado'] ?? '';
        if (!isset(Rfi::ESTADOS[$filtro]) && $filtro !== 'vencidos') $filtro = '';
        $gestiona = puede_gestionar_proyecto($proyecto);
        $lista = $disponible ? Rfi::lista($pid, $filtro === 'vencidos' ? '' : $filtro, $filtro === 'vencidos') : [];
        foreach ($lista as &$rf) $rf['laminas_n'] = count(Rfi::laminas((int)$rf['id']));
        unset($rf);
        $editar = null;
        $ver = (int)($_GET['editar'] ?? 0);
        if ($ver) foreach ($lista ?: Rfi::lista($pid) as $r) if ((int)$r['id'] === $ver) $editar = $r;
        $revs = $disponible ? Revisiones::mapa($pid, 'rfi') : [];
        $router->render('rfi/index', [
            'titulo' => 'RFI - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'disponible' => $disponible, 'filtro' => $filtro,
            'lista' => $lista, 'resumen' => $disponible ? Rfi::resumen($pid) : [], 'puedeEditar' => $gestiona, 'editar' => $editar,
            'actividades' => $disponible && $gestiona ? \Classes\Recursos::actividades($pid) : [],
            'revs' => $revs, 'puedeRev' => puede_revisar($proyecto), 'abrirForm' => isset($_GET['nuevo']) || $editar,
            'numero' => $disponible ? Rfi::siguienteNumero($pid) : '', 'error' => (string)($_GET['error'] ?? ''), 'ok' => (string)($_GET['ok'] ?? ''),
            'laminasDisponibles' => $disponible && $gestiona ? Grafico::laminasParaSeleccion($pid) : [],
            'categoriasLamina' => $disponible && $gestiona ? GraficoCategoria::deProyecto($pid) : [],
            'laminasMarcadas' => $editar ? Rfi::idsLaminas((int)$editar['id']) : [],
        ]);
    }

    private static function volver(int $pid, ?string $error = null, string $extra = ''): void
    {
        header('Location: /rfi?proyecto=' . $pid . ($error ? '&error=' . urlencode($error) : '&ok=1') . $extra);
        exit;
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        $error = Rfi::guardar((int)$p->id, $_POST, (string)($_SESSION['nombre'] ?? ''));
        if (!$error && Rfi::$ultimoId) {
            Rfi::sincronizarLaminas((int)$p->id, Rfi::$ultimoId, (array)($_POST['laminas'] ?? []));
        }
        self::volver((int)$p->id, $error, $error && !empty($_POST['id']) ? '&editar=' . (int)$_POST['id'] : ($error ? '&nuevo=1' : ''));
    }

    public static function estado()
    {
        $p = proyecto_de_peticion(true);
        Rfi::cambiarEstado((int)$p->id, (int)($_POST['id'] ?? 0), (string)($_POST['estado'] ?? ''));
        self::volver((int)$p->id);
    }

    /** PDF de un RFI con las láminas del Editor que tenga adjuntas */
    public static function pdf()
    {
        $p = proyecto_de_peticion();
        if (!puede_descargar()) {
            http_response_code(403);
            exit('No autorizado');
        }
        $rfi = Rfi::obtener((int)$p->id, (int)filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$rfi) {
            header('Location: /rfi?proyecto=' . $p->id . '&error=' . urlencode('El RFI no existe'));
            exit;
        }
        $ruta = (new \Classes\Exportadores\RfiPdfExporter($p))
            ->setRfi($rfi, Rfi::laminas((int)$rfi['id']))
            ->generar('RFI_' . slug_archivo($rfi['numero'] . '_' . $rfi['asunto']));
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        @unlink($ruta);
        exit;
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        Rfi::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id);
    }
}
