<?php

namespace Controllers;

use Classes\Instaladores;

/** API para la app de escritorio: se autentica con el token de dispositivo que genera el admin (no con la sesión web) */
class EscritorioApiController
{
    private static function autorizar(): void
    {
        $cab = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer (\S+)$/', $cab, $m) || !Instaladores::tokenValido($m[1])) {
            usleep(300000);   // frena los intentos a ciegas
            http_response_code(401);
            header('Content-Type: application/json');
            exit(json_encode(['error' => 'Token no válido']));
        }
    }

    public static function version()
    {
        self::autorizar();
        $archivos = [];
        foreach (Instaladores::listar() as $a) {
            if (!preg_match('/\.(deb|appimage)$/i', $a['nombre'])) continue;
            $archivos[] = ['nombre' => $a['nombre'], 'tam' => $a['tam'], 'sha256' => $a['sha']];
        }
        header('Content-Type: application/json');
        echo json_encode(['version' => Instaladores::version(), 'archivos' => $archivos]);
    }

    public static function descargar()
    {
        self::autorizar();
        if (!Instaladores::entregar((string)($_GET['archivo'] ?? ''))) { http_response_code(404); exit('Archivo no disponible'); }
        exit;
    }
}
