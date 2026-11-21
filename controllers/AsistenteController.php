<?php

namespace Controllers;

use MVC\Router;
use Classes\Asistente;
use Model\Proyecto;

/** Historial web del asistente (solo lectura) y revisión de borradores. */
class AsistenteController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $uid = (int)($_SESSION['id'] ?? 0);
        $admin = is_admin();
        $proyectos = Proyecto::visiblesPara(['id' => $uid, 'rol' => (string)($_SESSION['rol'] ?? 'visitante')]);
        $pid = (int)($_GET['proyecto'] ?? 0);
        if ($pid > 0) {
            $p = Proyecto::find($pid);
            if (!$p || !puede_ver_proyecto($p)) $pid = 0;
        }
        $q = texto_param($_GET['q'] ?? '');
        $convs = Asistente::disponible() ? Asistente::conversaciones($uid, $pid, $admin, $q) : [];
        $convId = (int)($_GET['conversacion'] ?? 0);
        if ($convId <= 0 && $convs) $convId = (int)$convs[0]['id'];
        $conv = $convId > 0 ? Asistente::conversacion($convId, $uid, $admin) : null;
        $mensajes = $conv ? Asistente::mensajes((int)$conv['id']) : [];
        $borradores = Asistente::disponible() ? Asistente::borradores($uid, $pid, $admin) : [];

        $router->render('asistente/index', [
            'titulo' => 'Asistente digital',
            'proyectos' => $proyectos,
            'proyectoFiltro' => $pid,
            'q' => $q,
            'conversaciones' => $convs,
            'conversacion' => $conv,
            'mensajes' => $mensajes,
            'borradores' => $borradores,
            'admin' => $admin,
        ]);
    }

    public static function aplicar()
    {
        redirigir_si_no_auth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /asistente'); exit; }
        $id = (int)($_POST['id'] ?? 0);
        $r = Asistente::aplicarBorrador($id, (int)$_SESSION['id'], is_admin());
        self::volver(($r['ok'] ? 'ok=' : 'error=') . urlencode($r['mensaje']));
    }

    public static function rechazar()
    {
        redirigir_si_no_auth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /asistente'); exit; }
        $ok = Asistente::rechazarBorrador((int)($_POST['id'] ?? 0), (int)$_SESSION['id'], is_admin());
        self::volver(($ok ? 'ok=' : 'error=') . urlencode($ok ? 'Borrador rechazado.' : 'No se pudo rechazar el borrador.'));
    }

    public static function eliminar()
    {
        redirigir_si_no_auth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /asistente'); exit; }
        $ok = Asistente::eliminarConversacion((int)($_POST['id'] ?? 0), (int)$_SESSION['id'], is_admin());
        self::volver(($ok ? 'ok=' : 'error=') . urlencode($ok ? 'Conversación eliminada.' : 'No se pudo eliminar la conversación.'));
    }

    private static function volver(string $query): never
    {
        $pid = (int)($_POST['proyecto'] ?? 0);
        $conv = (int)($_POST['conversacion'] ?? 0);
        $extra = ($pid ? '&proyecto=' . $pid : '') . ($conv ? '&conversacion=' . $conv : '');
        header('Location: /asistente?' . $query . $extra . '#conversaciones');
        exit;
    }
}
