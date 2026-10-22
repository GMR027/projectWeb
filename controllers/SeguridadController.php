<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Dependencias;
use Classes\Seguridad;

/** Seguridad e higiene: permisos de trabajo (con aprobación del admin) e incidentes */
class SeguridadController
{
    private static function gestion(): Proyecto
    {
        $p = Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT));
        if (!$p || !is_auth() || !puede_gestionar_proyecto($p)) { http_response_code(403); exit('No autorizado'); }
        return $p;
    }

    private static function volver(Proyecto $p, bool $ok, string $msg): void
    {
        $_SESSION['seguridad_msg'] = ['ok' => $ok, 'texto' => $msg];
        header('Location: /seguridad?proyecto=' . (int)$p->id);
        exit;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $estado = (string)($_GET['estado'] ?? '');
        $router->render('seguridad/index', ['titulo' => 'Seguridad y permisos - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'resumen' => Seguridad::resumen($pid), 'requeridos' => Seguridad::requeridos($pid, 14), 'climaPermisos' => \Classes\Salud::climaYPermisos($pid, 7), 'permisos' => Seguridad::listarPermisos($pid, $estado), 'estado' => $estado,
            'incidentes' => Seguridad::incidentes($pid), 'actividades' => Dependencias::actividades($pid), 'puede' => puede_gestionar_proyecto($proyecto), 'admin' => is_admin(), 'mensaje' => $_SESSION['seguridad_msg'] ?? null]);
        unset($_SESSION['seguridad_msg']);
    }

    public static function crear()
    {
        $p = self::gestion();
        $d = $_POST;
        $d['medidas'] = Seguridad::medidas((string)($d['tipo'] ?? 'general'), preg_split('/\R/', (string)($d['medidas_extra'] ?? '')) ?: [], (int)$p->id);
        [$ok, $msg] = Seguridad::crearPermiso((int)$p->id, $d, 'manual', is_admin() && ($d['accion'] ?? '') === 'aprobar_ya' ? 'solicitado' : 'solicitado');
        self::volver($p, $ok, $msg);
    }

    public static function crearTipo()
    {
        $p = self::gestion();
        [$ok, $msg] = Seguridad::guardarTipo((int)$p->id, (string)($_POST['nombre'] ?? ''), (string)($_POST['palabras'] ?? ''), (string)($_POST['medidas'] ?? ''));
        self::volver($p, $ok, $msg);
    }

    public static function eliminarTipo()
    {
        $p = self::gestion();
        [$ok, $msg] = Seguridad::eliminarTipo((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver($p, $ok, $msg);
    }

    public static function estado()
    {
        $p = self::gestion();
        [$ok, $msg] = Seguridad::cambiarEstado($p, (int)($_POST['id'] ?? 0), (string)($_POST['estado'] ?? ''), is_admin());
        self::volver($p, $ok, $msg);
    }

    public static function medidas()
    {
        $p = self::gestion();
        Seguridad::marcarMedidas((int)$p->id, (int)($_POST['id'] ?? 0), (array)($_POST['ok'] ?? []));
        self::volver($p, true, 'Medidas actualizadas.');
    }

    public static function eliminar()
    {
        $p = self::gestion();
        Seguridad::eliminarPermiso((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver($p, true, 'Permiso eliminado (solo se pueden eliminar los no aprobados).');
    }

    public static function pdf()
    {
        $p = proyecto_de_peticion();
        $x = Seguridad::obtenerPermiso((int)$p->id, (int)($_GET['id'] ?? 0));
        if (!$x) { http_response_code(404); exit('Permiso no encontrado'); }
        enviar_descarga(Seguridad::pdfPermiso($p, $x), 'application/pdf', date('Y_m_d') . '_permiso_' . (int)$x['id'] . '_' . slug_archivo($x['titulo']) . '.pdf');
    }

    public static function incidente()
    {
        $p = self::gestion();
        [$ok, $msg] = Seguridad::registrarIncidente((int)$p->id, $_POST);
        self::volver($p, $ok, $msg);
    }

    public static function cerrarIncidente()
    {
        $p = self::gestion();
        Seguridad::cerrarIncidente((int)$p->id, (int)($_POST['id'] ?? 0), (string)($_POST['acciones'] ?? ''));
        self::volver($p, true, 'Incidente cerrado.');
    }
}
