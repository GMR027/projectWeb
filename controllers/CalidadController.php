<?php

namespace Controllers;

use MVC\Router;
use Classes\Calidad;
use Classes\CalidadCompleta;
use Classes\CalidadExpediente;
use Model\Archivo;
use Classes\Pdf;

/** Calidad e inspecciones (ITP): planes de puntos de inspección, resultados y no conformidades */
class CalidadController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Calidad::disponible();
        $filtro = isset(Calidad::ESTADOS[$_GET['estado'] ?? '']) ? $_GET['estado'] : '';
        $lista = $disponible ? Calidad::planes($pid, $filtro) : [];
        $tab = in_array($_GET['tab'] ?? '', ['planes', 'ensayos', 'nc', 'expedientes'], true) ? $_GET['tab'] : 'planes';
        $ver = (int)($_GET['ver'] ?? 0);
        if ($ver) $tab = 'planes';
        $plan = $ver ? Calidad::plan($pid, $ver) : null;
        $editar = (int)($_GET['editar'] ?? 0);
        $editarPlan = $editar ? Calidad::plan($pid, $editar) : null;
        $router->render('calidad/index', [
            'titulo' => 'Calidad e inspecciones - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => $disponible,
            'filtro' => $filtro,
            'lista' => $lista,
            'resumen' => $disponible ? Calidad::resumen($pid) : [],
            'noConformidades' => $disponible ? Calidad::noConformidades($pid) : [],
            'plan' => $plan,
            'puntos' => $plan ? Calidad::puntos((int)$plan['id']) : [],
            'editarPlan' => $editarPlan,
            'abrirForm' => isset($_GET['nuevo']) || $editarPlan,
            'actividades' => Calidad::disponible() ? \Classes\Recursos::actividades($pid) : [],
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'puedeLiberar' => Calidad::disponible() && Calidad::puedeLiberar($proyecto),
            'detenidas' => $disponible ? Calidad::detenidas($pid) : [],
            'liberaciones' => $disponible ? Calidad::liberaciones($pid) : [],
            'ok' => texto_param($_GET['ok'] ?? ''),
            'error' => texto_param($_GET['error'] ?? ''),
            'tab' => $tab,
            'completa' => $disponible && CalidadCompleta::disponible() ? CalidadCompleta::resumen($pid) : [],
            'ensayos' => $disponible && CalidadCompleta::disponible() && $tab === 'ensayos' ? CalidadCompleta::ensayos($pid) : [],
            'ncs' => $disponible && CalidadCompleta::disponible() && $tab === 'nc' ? CalidadCompleta::ncs($pid) : [],
            'ncVer' => $disponible && CalidadCompleta::disponible() && !empty($_GET['nc']) ? CalidadCompleta::nc($pid, (int)$_GET['nc']) : null,
            'archivos' => $disponible ? Archivo::deProyecto($pid) : [],
            'siguienteNC' => $disponible && CalidadCompleta::disponible() ? CalidadCompleta::siguienteNC($pid) : '',
            'expedientes' => $disponible && $tab === 'expedientes' ? (function () use ($proyecto, $pid) {
                $out = [];
                foreach ($proyecto->getPartidas() as $pa) {
                    $datos = CalidadExpediente::datos($pid, (int)$pa->id);
                    if (!$datos) continue;
                    $out[] = ['partida' => $pa, 'estado' => CalidadExpediente::estado($pid, (int)$pa->id), 'resumen' => $datos['resumen']];
                }
                return $out;
            })() : [],
        ]);
    }

    /** Libera el avance de una actividad detenida por calidad (solo dueño del proyecto o admin) */
    public static function liberar()
    {
        $p = proyecto_de_peticion(true);
        if (!Calidad::puedeLiberar($p)) {
            self::volver((int)$p->id, false, 'Solo el dueño del proyecto o el administrador pueden liberar un avance');
        }
        [$ok, $msg] = Calidad::liberar((int)$p->id, (int)($_POST['actividad_id'] ?? 0), (int)($_POST['progreso_autorizado'] ?? 100), texto_param($_POST['motivo'] ?? ''));
        self::volver((int)$p->id, $ok, $msg, '&detenidas=1');
    }

    public static function revocar()
    {
        $p = proyecto_de_peticion(true);
        if (!Calidad::puedeLiberar($p)) {
            self::volver((int)$p->id, false, 'Solo el dueño del proyecto o el administrador pueden revocar una liberación');
        }
        $ok = Calidad::revocarLiberacion((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Liberación revocada' : 'La liberación no existe', '&detenidas=1');
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg, $id] = Calidad::guardarPlan((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, $ok ? '&ver=' . $id : ((int)($_POST['id'] ?? 0) ? '&editar=' . (int)$_POST['id'] : '&nuevo=1'));
    }

    public static function punto()
    {
        $p = proyecto_de_peticion(true);
        $ok = Calidad::marcarPunto((int)$p->id, (int)($_POST['punto_id'] ?? 0), (string)($_POST['resultado'] ?? 'pendiente'),
            texto_param($_POST['observacion'] ?? ''), texto_param($_POST['responsable'] ?? ''), !empty($_POST['resuelto']));
        $plan = (int)($_POST['plan_id'] ?? 0);
        self::volver((int)$p->id, $ok, $ok ? 'Punto actualizado' : 'No se pudo actualizar el punto', $plan ? '&ver=' . $plan : '');
    }

    public static function puntoAgregar()
    {
        $p = proyecto_de_peticion(true);
        $ok = Calidad::agregarPunto((int)$p->id, (int)($_POST['plan_id'] ?? 0), texto_param($_POST['descripcion'] ?? ''), texto_param($_POST['criterio'] ?? ''));
        $plan = (int)($_POST['plan_id'] ?? 0);
        self::volver((int)$p->id, $ok, $ok ? 'Punto agregado' : 'Escribe la descripción del punto', $plan ? '&ver=' . $plan : '');
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        $ok = Calidad::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Plan eliminado' : 'No se pudo eliminar');
    }

    public static function ensayo()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = CalidadCompleta::guardarEnsayo((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, '&tab=ensayos');
    }

    public static function ensayoEliminar()
    {
        $p = proyecto_de_peticion(true);
        $ok = CalidadCompleta::eliminarEnsayo((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Ensayo eliminado' : 'El ensayo no existe', '&tab=ensayos');
    }

    public static function ncGuardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg, $id] = CalidadCompleta::guardarNC((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, '&tab=nc' . ($id ? '&nc=' . $id : ''));
    }

    public static function ncAccion()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = CalidadCompleta::accion((int)$p->id, (int)($_POST['nc_id'] ?? 0), $_POST);
        self::volver((int)$p->id, $ok, $msg, '&tab=nc&nc=' . (int)($_POST['nc_id'] ?? 0));
    }

    public static function ncCerrar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = CalidadCompleta::cerrarNC((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $msg, '&tab=nc');
    }

    public static function ncCosto()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = CalidadCompleta::enviarCosto((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $msg, '&tab=nc');
    }

    public static function expedientePdf()
    {
        $p = proyecto_de_peticion();
        if (!puede_descargar() && !es_demo()) { http_response_code(403); exit('No autorizado'); }
        $partidaId = (int)($_GET['partida'] ?? 0);
        $e = CalidadExpediente::estado((int)$p->id, $partidaId);
        $pdf = null;
        if ($e['estado'] === 'cerrado' && $e['pdf_ruta'] !== '' && is_file(CalidadExpediente::rutaAbsoluta((string)$e['pdf_ruta']))) {
            $pdf = file_get_contents(CalidadExpediente::rutaAbsoluta((string)$e['pdf_ruta']));
        }
        if (!$pdf) $pdf = CalidadExpediente::pdf($p, $partidaId);
        if ($pdf === null) { http_response_code(404); exit('La partida no existe'); }
        enviar_descarga($pdf, 'application/pdf', 'Expediente-calidad-' . $partidaId . '-' . date('Y-m-d') . '.pdf');
    }

    public static function expedienteCerrar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = CalidadExpediente::cerrar($p, (int)($_POST['partida'] ?? 0));
        self::volver((int)$p->id, $ok, $msg, '&tab=expedientes');
    }

    public static function expedienteReabrir()
    {
        $p = proyecto_de_peticion(true);
        if (!is_admin() && !es_propietario($p)) self::volver((int)$p->id, false, 'Solo el administrador o el dueño pueden reabrir un expediente', '&tab=expedientes');
        CalidadExpediente::reabrir((int)$p->id, (int)($_POST['partida'] ?? 0));
        self::volver((int)$p->id, true, 'Expediente reabierto', '&tab=expedientes');
    }

    private static function volver(int $pid, bool $ok, string $msg, string $extra = ''): void
    {
        header('Location: /calidad?proyecto=' . $pid . ($extra ?: '') . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
