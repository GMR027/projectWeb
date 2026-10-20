<?php

namespace Controllers;

use Classes\Instaladores;

/** API para la app de escritorio: solo el administrador con sesión iniciada (la vista de la app comparte sus cookies) */
class EscritorioApiController
{
    private static function autorizar(): void
    {
        if (!is_admin()) {
            http_response_code(401);
            header('Content-Type: application/json');
            exit(json_encode(['error' => 'Inicia sesión como administrador']));
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
