<?php

namespace Controllers;

use MVC\Router;
use Classes\Sesiones;

/** Sesiones y dispositivos del usuario; el administrador además ve todas en Sistema → Sesiones */
class SesionController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $uid = (int)($_SESSION['id'] ?? 0);
        $token = Sesiones::tokenActual();
        $sesiones = Sesiones::listar($uid, $token);
        $router->render('auth/sesiones', [
            'titulo' => 'Sesiones y dispositivos',
            'authLayout' => true,
            'sesiones' => $sesiones,
            'total' => count($sesiones),
        ]);
    }

    public static function cerrar()
    {
        redirigir_si_no_auth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /cuenta/sesiones'); exit; }
        $id = (int)($_POST['id'] ?? 0);
        $ok = Sesiones::cerrar((int)$_SESSION['id'], $id);
        header('Location: /cuenta/sesiones?' . ($ok ? 'ok=' : 'error=') . urlencode($ok ? 'Sesión cerrada.' : 'No se pudo cerrar la sesión.') . '#sesiones');
        exit;
    }

    public static function cerrarOtras()
    {
        redirigir_si_no_auth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /cuenta/sesiones'); exit; }
        $n = Sesiones::cerrarOtras((int)$_SESSION['id'], Sesiones::tokenActual());
        $msg = $n > 0 ? "Se cerraron $n sesión(es) en otros dispositivos." : 'No había otras sesiones abiertas.';
        header('Location: /cuenta/sesiones?ok=' . urlencode($msg) . '#sesiones');
        exit;
    }

    public static function adminCerrar()
    {
        redirigir_si_no_admin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /sistema#sesiones'); exit; }
        $id = (int)($_POST['id'] ?? 0);
        $ok = Sesiones::cerrarAdmin($id, (int)$_SESSION['id']);
        header('Location: /sistema?' . ($ok ? 'ok=' : 'error=') . urlencode($ok ? 'Sesión cerrada.' : 'No se pudo cerrar la sesión.') . '#sesiones');
        exit;
    }
}
