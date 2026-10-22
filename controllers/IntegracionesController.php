<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\ApiTokens;
use Classes\Webhooks;

/** Fase 5.1: tokens de la API REST y webhooks por proyecto */
class IntegracionesController
{
    private static function usuario(): array
    {
        redirigir_si_no_auth();
        if (is_visitante()) { header('Location: /proyectos'); exit; }
        return usuario_actual();
    }

    private static function volver(?int $pid, bool $ok, string $texto, string $ancla = ''): never
    {
        $_SESSION['integ_msg'] = ['ok' => $ok, 'texto' => $texto];
        header('Location: /integraciones' . ($pid ? '?proyecto=' . $pid : '') . $ancla);
        exit;
    }

    public static function index(Router $router)
    {
        $u = self::usuario();
        $proyectos = array_values(array_filter(Proyecto::visiblesPara($u), fn($p) => puede_gestionar_proyecto($p)));
        $sel = null;
        foreach ($proyectos as $p) if ((int)$p->id === (int)($_GET['proyecto'] ?? 0)) $sel = $p;
        $sel ??= $proyectos[0] ?? null;
        $wh = $sel && Webhooks::disponible() ? Webhooks::lista((int)$sel->id) : [];
        $ver = (int)($_GET['entregas'] ?? 0);
        $router->render('integraciones/index', [
            'titulo' => 'Integraciones', 'usuario' => $u, 'disponible' => ApiTokens::disponible() && Webhooks::disponible(),
            'tokens' => ApiTokens::disponible() ? ApiTokens::lista((int)$u['id']) : [], 'proyectos' => $proyectos, 'proyecto' => $sel, 'webhooks' => $wh,
            'entregas' => $sel && $ver ? Webhooks::entregas((int)$sel->id, $ver) : [], 'verEntregas' => $ver,
            'tokenNuevo' => $_SESSION['api_token_nuevo'] ?? null, 'mensaje' => $_SESSION['integ_msg'] ?? null, 'url' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        ]);
        unset($_SESSION['api_token_nuevo'], $_SESSION['integ_msg']);
    }

    public static function crearToken()
    {
        $u = self::usuario();
        $pid = (int)($_POST['proyecto_id'] ?? 0);
        if ($pid && !($p = Proyecto::find($pid)) || ($pid && !puede_ver_proyecto($p))) self::volver(null, false, 'Proyecto no válido.');
        [$ok, $r] = ApiTokens::crear((int)$u['id'], (string)($_POST['nombre'] ?? ''), !empty($_POST['escritura']), $pid, (int)($_POST['dias'] ?? 0));
        if ($ok) $_SESSION['api_token_nuevo'] = $r;
        self::volver(null, $ok, $ok ? 'Token creado. Cópialo ahora: no se vuelve a mostrar.' : $r, '#tokens');
    }

    public static function revocarToken()
    {
        $u = self::usuario();
        ApiTokens::revocar((int)$u['id'], (int)($_POST['id'] ?? 0));
        self::volver(null, true, 'Token revocado.', '#tokens');
    }

    private static function proyectoGestionable(): Proyecto
    {
        self::usuario();
        return proyecto_de_peticion(true);
    }

    public static function crearWebhook()
    {
        $p = self::proyectoGestionable();
        [$ok, $r] = Webhooks::crear((int)$p->id, (string)($_POST['nombre'] ?? ''), (string)($_POST['url'] ?? ''), $_POST['eventos'] ?? [], (string)($_SESSION['nombre'] ?? ''));
        self::volver((int)$p->id, $ok, $ok ? 'Webhook creado. Usa «Probar» para comprobar que responde.' : $r, '#webhooks');
    }

    public static function toggleWebhook()
    {
        $p = self::proyectoGestionable();
        Webhooks::cambiarActivo((int)$p->id, (int)($_POST['id'] ?? 0), !empty($_POST['activo']));
        self::volver((int)$p->id, true, !empty($_POST['activo']) ? 'Webhook activado.' : 'Webhook pausado.', '#webhooks');
    }

    public static function eliminarWebhook()
    {
        $p = self::proyectoGestionable();
        Webhooks::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, true, 'Webhook eliminado.', '#webhooks');
    }

    public static function probarWebhook()
    {
        $p = self::proyectoGestionable();
        [$ok, $codigo, $err] = Webhooks::probar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? "La prueba llegó (HTTP $codigo)." : "La prueba falló: $err", '#webhooks');
    }

    public static function reintentarEntrega()
    {
        $p = self::proyectoGestionable();
        [$ok, $codigo, $err] = Webhooks::reintentar((int)$p->id, (int)($_POST['entrega'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? "Reenviado (HTTP $codigo)." : "Falló: $err", '#webhooks');
    }
}
