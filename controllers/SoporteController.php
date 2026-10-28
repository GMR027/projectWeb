<?php

namespace Controllers;

use MVC\Router;
use Model\Soporte;
use Model\SoporteRespuesta;

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

    /** [soporte_id => [respuestas como arreglo]] de los mensajes del usuario */
    private static function respuestasDe(array $mensajes): array
    {
        $por = SoporteRespuesta::deSoporte(array_map(fn($m) => (int)$m->id, $mensajes));
        return array_map(fn($lista) => array_map(fn($r) => $r->aArray(), $lista), $por);
    }

    public static function index(Router $router)
    {
        self::exigirCliente();
        $router->render('soporte/index', [
            'titulo' => 'Soporte',
            'mensajes' => $mensajes = Soporte::deUsuario((int)$_SESSION['id']),
            'respuestas' => self::respuestasDe($mensajes),
            'soporte' => new Soporte(),
            'alertas' => [],
        ]);
    }

    public static function crear(Router $router)
    {
        self::exigirCliente();
        // Límite anti-abuso: 10 mensajes por hora y usuario (el formulario público de contacto ya tiene el suyo)
        if (Soporte::total('usuario_id = ' . (int)$_SESSION['id'] . ' AND creado > (NOW() - INTERVAL 1 HOUR)') >= 10) {
            $router->render('soporte/index', [
                'titulo' => 'Soporte', 'mensajes' => $mensajes = Soporte::deUsuario((int)$_SESSION['id']), 'respuestas' => self::respuestasDe($mensajes),
                'soporte' => new Soporte(), 'alertas' => ['error' => ['Has enviado varios mensajes seguidos. Espera un momento o responde en el hilo que ya abriste.']],
            ]);
            return;
        }
        $soporte = new Soporte($_POST);
        $soporte->usuario_id = (int)$_SESSION['id'];   // siempre el usuario en sesión
        $soporte->leido = 0;
        $alertas = $soporte->validar();
        if (!$alertas && $soporte->guardar()['resultado']) {
            header('Location: /soporte?enviado=1');
            exit;
        }
        $router->render('soporte/index', [
            'titulo' => 'Soporte', 'mensajes' => $mensajes = Soporte::deUsuario((int)$_SESSION['id']), 'respuestas' => self::respuestasDe($mensajes), 'soporte' => $soporte, 'alertas' => $alertas,
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
            SoporteRespuesta::borrarFotosDeSoporte((int)$s->id);
            $s->eliminar();
        }
        header('Location: /soporte');
        exit;
    }

    /** El cliente abrió las respuestas de uno de sus mensajes (JSON): se quitan como «nuevas» */
    public static function vistas()
    {
        header('Content-Type: application/json');
        $s = ($_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() && ($_SESSION['rol'] ?? '') === 'cliente') ? self::propio(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$s) {
            http_response_code(403);
            echo json_encode(['success' => false]);
            exit;
        }
        SoporteRespuesta::marcarVistas((int)$s->id);
        echo json_encode(['success' => true, 'nuevas' => soporte_respuestas_nuevas()]);
        exit;
    }
}
