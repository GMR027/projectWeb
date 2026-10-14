<?php

namespace Controllers;

use MVC\Router;
use Model\Soporte;

/** Soporte: el cliente escribe al administrador. Cada cliente solo ve y edita lo suyo. */
class SoporteController
{
    private static function exigirCliente(): void
    {
        redirigir_si_no_auth();
        if (($_SESSION['rol'] ?? '') !== 'cliente') {
            header('Location: /dashboard');
            exit;
        }
    }

    private static function propio($id): ?Soporte
    {
        $s = $id ? Soporte::find((int)$id) : null;
        return ($s && (int)$s->usuario_id === (int)$_SESSION['id']) ? $s : null;
    }

    public static function index(Router $router)
    {
        self::exigirCliente();
        $router->render('soporte/index', [
            'titulo' => 'Soporte',
            'mensajes' => Soporte::deUsuario((int)$_SESSION['id']),
            'soporte' => new Soporte(),
            'alertas' => [],
        ]);
    }

    public static function crear(Router $router)
    {
        self::exigirCliente();
        $soporte = new Soporte($_POST);
        $soporte->usuario_id = (int)$_SESSION['id'];   // siempre el usuario en sesión
        $soporte->leido = 0;
        $alertas = $soporte->validar();
        if (!$alertas && $soporte->guardar()['resultado']) {
            header('Location: /soporte?enviado=1');
            exit;
        }
        $router->render('soporte/index', [
            'titulo' => 'Soporte', 'mensajes' => Soporte::deUsuario((int)$_SESSION['id']), 'soporte' => $soporte, 'alertas' => $alertas,
        ]);
    }

    public static function editar(Router $router)
    {
        self::exigirCliente();
        $soporte = self::propio(filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$soporte) {
            header('Location: /soporte');
            exit;
        }
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $soporte->tema = trim($_POST['tema'] ?? '');
            $soporte->informacion = trim($_POST['informacion'] ?? '');
            $soporte->leido = 0;   // al cambiar el mensaje vuelve a aparecer como nuevo para el admin
            $alertas = $soporte->validar();
            if (!$alertas && $soporte->guardar()) {
                header('Location: /soporte?editado=1');
                exit;
            }
        }
        $router->render('soporte/editar', ['titulo' => 'Editar soporte', 'soporte' => $soporte, 'alertas' => $alertas]);
    }

    public static function eliminar()
    {
        self::exigirCliente();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($s = self::propio(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)))) {
            $s->eliminar();
        }
        header('Location: /soporte');
        exit;
    }
}
