<?php

namespace Controllers;

use Model\Contacto;

/** Formulario público de contacto de la página principal */
class ContactoController
{
    public static function enviar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /#contacto');
            exit;
        }
        $ajax = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        $responder = function (bool $ok, array $errores = []) use ($ajax) {
            if ($ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => $ok, 'errores' => $errores]);
            } else {
                header('Location: /?contacto=' . ($ok ? 'ok' : 'error') . '#contacto');
            }
            exit;
        };

        // Trampas anti-spam: campo oculto que un humano no llena y tiempo mínimo de llenado
        $tardo = (int)round(microtime(true) * 1000) - (int)($_POST['t'] ?? 0);
        if (!empty($_POST['sitio_web']) || $tardo < 3000) {
            $responder(true);   // se finge éxito para no dar pistas al bot
        }
        // Límite por IP: 5 mensajes por hora
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        if (Contacto::total("ip = '" . Contacto::escapar($ip) . "' AND creado > (NOW() - INTERVAL 1 HOUR)") >= 5) {
            $responder(false, ['Has enviado varios mensajes seguidos. Intenta de nuevo en un rato o escríbenos por otro medio.']);
        }
        if (empty($_POST['privacidad'])) {
            $responder(false, ['Debes aceptar que usemos tus datos para contactarte']);
        }

        $c = new Contacto($_POST + ['ip' => $ip]);
        $c->ip = $ip;
        $alertas = $c->validar();
        if ($alertas) $responder(false, $alertas['error'] ?? []);
        $r = $c->guardar();
        $responder((bool)$r['resultado'], $r['resultado'] ? [] : ['No pudimos guardar tu mensaje. Intenta de nuevo.']);
    }
}
