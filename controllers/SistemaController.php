<?php

namespace Controllers;

use MVC\Router;
use Classes\Volumen;
use Classes\Respaldo;

/** Ajustes del sistema (solo admin): ocupación de los volúmenes del servidor */
class SistemaController
{
    public static function ajustes(Router $router)
    {
        redirigir_si_no_admin();
        $archivos = Volumen::archivos();
        $bd = Volumen::baseDatos();
        Volumen::registrarHoy($archivos['usado'], $bd['usado']);
        $historial = Volumen::historial(90);

        $router->render('sistema/ajustes', [
            'titulo' => 'Ajustes',
            'archivos' => $archivos,
            'bd' => $bd,
            'disco' => Volumen::discoServidor(),
            'historial' => $historial,
            'pronArchivos' => Volumen::pronostico($historial, 'archivos', $archivos['usado'], $archivos['capacidad']),
            'pronBd' => Volumen::pronostico($historial, 'bd', $bd['usado'], $bd['capacidad']),
            'respaldos' => Respaldo::listar(),
            'programado' => Respaldo::programado(), 'ultimoAuto' => Respaldo::ultimoAutomatico(), 'proximoAuto' => Respaldo::proximo(),
            'ok' => $_GET['ok'] ?? '',
            'error' => $_GET['error'] ?? '',
        ]);
    }

    private static function volver(string $clave, string $msg)
    {
        header('Location: /sistema?' . $clave . '=' . urlencode($msg) . '#respaldo');
        exit;
    }

    public static function respaldoCrear()
    {
        redirigir_si_no_admin();
        try {
            $f = Respaldo::crear((string)($_POST['nombre'] ?? ''));
            self::volver('ok', "Respaldo creado: $f");
        } catch (\Throwable $e) {
            self::volver('error', $e->getMessage());
        }
    }

    /** Configura el respaldo diario automático */
    public static function respaldoProgramar()
    {
        redirigir_si_no_admin();
        Respaldo::guardarProgramado(!empty($_POST['activo']), (string)($_POST['hora'] ?? '23:00'), (int)($_POST['conservar'] ?? 5));
        self::volver('ok', 'Respaldo programado actualizado.');
    }

    public static function respaldoDescargar()
    {
        redirigir_si_no_admin();
        $ruta = Respaldo::ruta((string)($_GET['archivo'] ?? ''));
        if (!$ruta) { http_response_code(404); exit; }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    public static function respaldoEliminar()
    {
        redirigir_si_no_admin();
        Respaldo::eliminar((string)($_POST['archivo'] ?? ''));
        self::volver('ok', 'Respaldo eliminado.');
    }

    /** Restaura desde un respaldo guardado en el servidor o desde un .zip subido */
    public static function respaldoRestaurar()
    {
        redirigir_si_no_admin();
        $zip = null;
        $temporal = false;
        if (!empty($_FILES['respaldo']['tmp_name']) && is_uploaded_file($_FILES['respaldo']['tmp_name'])) {
            $zip = $_FILES['respaldo']['tmp_name'];
            $temporal = true;
        } elseif (!empty($_POST['archivo'])) {
            $zip = Respaldo::ruta((string)$_POST['archivo']);
        }
        if (!$zip) {
            $codigo = $_FILES['respaldo']['error'] ?? 0;
            self::volver('error', in_array($codigo, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'El archivo excede el tamaño máximo de subida del servidor.' : 'Selecciona un archivo de respaldo (.zip).');
        }
        try {
            Respaldo::restaurar($zip);
        } catch (\Throwable $e) {
            self::volver('error', $e->getMessage());
        } finally {
            if ($temporal) @unlink($zip);
        }
        // La tabla de usuarios cambió: se cierra la sesión para entrar con las credenciales restauradas
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header('Location: /login');
        exit;
    }
}
