<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Almacenamiento;
use Classes\Dependencias;
use Classes\Imagenes;
use Classes\Punch;

/** Punch list: pendientes de calidad con fotos antes/después, inspecciones con listas de verificación y análisis de causas repetidas */
class PunchController
{
    private const MAX_FOTO = 15 * 1024 * 1024;

    private static function gestion(): Proyecto
    {
        $p = Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT));
        if (!$p || !is_auth() || !puede_gestionar_proyecto($p)) { http_response_code(403); exit('No autorizado'); }
        return $p;
    }

    private static function volver(Proyecto $p, bool $ok, string $msg, string $ruta = '/punch'): void
    {
        $_SESSION['punch_msg'] = ['ok' => $ok, 'texto' => $msg];
        header('Location: ' . $ruta . (str_contains($ruta, '?') ? '&' : '?') . 'proyecto=' . (int)$p->id);
        exit;
    }

    /** Guarda las fotos antes/después subidas con el formulario (si caben en la cuota) */
    private static function fotos(Proyecto $p, int $id): ?string
    {
        foreach (['foto_antes', 'foto_despues'] as $campo) {
            $f = $_FILES[$campo] ?? null;
            if (!$f || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) continue;
            if ($f['size'] > self::MAX_FOTO) return 'Una foto supera 15 MB y no se guardó.';
            if (!Almacenamiento::cabe((int)$p->id, (int)$f['size'])) return Almacenamiento::mensajeLleno((int)$p->id);
            $nombre = Imagenes::guardarEn(Punch::CARPETA, $f['tmp_name'], 1600, 85);
            if ($nombre) Punch::ponerFoto((int)$p->id, $id, $campo, $nombre); else return 'Una foto no es válida (jpg, png, webp o gif).';
        }
        return null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $f = ['estado' => $_GET['estado'] ?? 'pendientes', 'severidad' => $_GET['severidad'] ?? '', 'contratista' => $_GET['contratista'] ?? '', 'categoria' => $_GET['categoria'] ?? '', 'q' => trim((string)($_GET['q'] ?? ''))];
        $router->render('punch/index', ['titulo' => 'Punch list - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $f, 'lista' => Punch::listar($pid, $f), 'analisis' => Punch::analisis($pid), 'contratistas' => Punch::contratistas($pid),
            'actividades' => Dependencias::actividades($pid), 'puede' => puede_gestionar_proyecto($proyecto), 'mensaje' => $_SESSION['punch_msg'] ?? null]);
        unset($_SESSION['punch_msg']);
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $x = Punch::obtener((int)$proyecto->id, (int)($_GET['id'] ?? 0));
        if (!$x) { header('Location: /punch?proyecto=' . (int)$proyecto->id); exit; }
        $router->render('punch/ver', ['titulo' => $x['titulo'] . ' - Punch list', 'proyecto' => $proyecto, 'x' => $x, 'actividades' => Dependencias::actividades((int)$proyecto->id), 'puede' => puede_gestionar_proyecto($proyecto), 'mensaje' => $_SESSION['punch_msg'] ?? null]);
        unset($_SESSION['punch_msg']);
    }

    public static function guardar()
    {
        $p = self::gestion();
        $pid = (int)$p->id;
        $ex = ($id = (int)($_POST['id'] ?? 0)) ? Punch::obtener($pid, $id) : null;
        [$ok, $msg, $nid] = Punch::guardar($pid, $_POST, $ex);
        if ($ok) { $aviso = self::fotos($p, $nid); if ($aviso) $msg .= ' ' . $aviso; }
        self::volver($p, $ok, $msg, $ok ? '/punch/ver?id=' . $nid : '/punch');
    }

    public static function estado()
    {
        $p = self::gestion();
        [$ok, $msg] = Punch::estado($p, (int)($_POST['id'] ?? 0), (string)($_POST['estado'] ?? ''));
        self::volver($p, $ok, $msg, !empty($_POST['volver_ver']) ? '/punch/ver?id=' . (int)$_POST['id'] : '/punch');
    }

    public static function eliminar()
    {
        $p = self::gestion();
        Punch::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver($p, true, 'Pendiente eliminado.');
    }

    public static function inspeccion(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $router->render('punch/inspeccion', ['titulo' => 'Inspección - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'plantilla' => isset(Punch::PLANTILLAS[$_GET['plantilla'] ?? '']) ? $_GET['plantilla'] : '']);
    }

    public static function inspeccionar()
    {
        $p = self::gestion();
        $pl = isset(Punch::PLANTILLAS[$_POST['plantilla'] ?? '']) ? $_POST['plantilla'] : 'general';
        $res = [];
        foreach (Punch::PLANTILLAS[$pl]['items'] as $i => [$texto, $cat]) $res[] = ['texto' => $texto, 'categoria' => $cat, 'estado' => ($_POST['r'][$i] ?? 'na'), 'nota' => (string)($_POST['nota'][$i] ?? '')];
        $n = Punch::desdeInspeccion((int)$p->id, $pl, (string)($_POST['ubicacion'] ?? ''), (string)($_POST['contratista'] ?? ''), (string)($_POST['responsable'] ?? ''), $res);
        self::volver($p, true, $n ? "Inspección registrada: $n falla(s) se convirtieron en pendientes." : 'Inspección registrada: sin fallas.');
    }

    public static function pdf()
    {
        $p = proyecto_de_peticion();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . date('Y_m_d') . '_punch_list.pdf"');
        echo Punch::pdf($p, trim((string)($_GET['contratista'] ?? '')));
        exit;
    }
}
