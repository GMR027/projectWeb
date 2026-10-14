<?php

namespace Controllers;

use MVC\Router;
use Model\Contacto;
use Model\Soporte;
use Model\Usuario;

/** Bandeja de entrada del administrador: mensajes de contacto (público) y de soporte (clientes) */
class BandejaController
{
    private static function modelo(string $tipo): ?string
    {
        return ['contacto' => Contacto::class, 'soporte' => Soporte::class][$tipo] ?? null;
    }

    public static function index(Router $router)
    {
        redirigir_si_no_admin();
        $filtro = in_array($_GET['tipo'] ?? '', ['contacto', 'soporte'], true) ? $_GET['tipo'] : 'todos';
        $items = [];
        if ($filtro !== 'soporte') {
            foreach (Contacto::all('id DESC') as $c) {
                $items[] = ['tipo' => 'contacto', 'id' => $c->id, 'leido' => (bool)$c->leido, 'de' => $c->nombre . ($c->empresa ? ' · ' . $c->empresa : ''),
                    'asunto' => ($c->plan ? $c->plan . ': ' : '') . mb_strimwidth($c->mensaje, 0, 90, '…'), 'fecha' => $c->creado];
            }
        }
        if ($filtro !== 'contacto') {
            $nombres = array_column(array_map(fn($u) => ['id' => $u->id, 'n' => $u->nombre], Usuario::all('id ASC')), 'n', 'id');
            foreach (Soporte::all('id DESC') as $s) {
                $items[] = ['tipo' => 'soporte', 'id' => $s->id, 'leido' => (bool)$s->leido, 'de' => $nombres[$s->usuario_id] ?? 'Usuario eliminado',
                    'asunto' => $s->tema, 'fecha' => $s->creado];
            }
        }
        usort($items, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        $router->render('bandeja/index', [
            'titulo' => 'Bandeja de entrada', 'items' => $items, 'filtro' => $filtro,
            'sinLeer' => bandeja_sin_leer(),
        ]);
    }

    public static function ver(Router $router)
    {
        redirigir_si_no_admin();
        $tipo = $_GET['tipo'] ?? '';
        $clase = self::modelo($tipo);
        $m = $clase ? $clase::find(filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$m) {
            header('Location: /bandeja');
            exit;
        }
        if (!$m->leido) {
            $m->leido = 1;
            $m->guardar();
        }
        $remitente = $tipo === 'soporte' ? Usuario::find($m->usuario_id) : null;
        $creado = $m->creado;
        $router->render('bandeja/ver', ['titulo' => 'Mensaje', 'tipo' => $tipo, 'm' => $m, 'remitente' => $remitente, 'creado' => $creado]);
    }

    /** Marcar como leído / no leído o eliminar */
    public static function accion()
    {
        redirigir_si_no_admin();
        $clase = self::modelo($_POST['tipo'] ?? '');
        $m = ($clase && $_SERVER['REQUEST_METHOD'] === 'POST') ? $clase::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        if ($m) {
            switch ($_POST['accion'] ?? '') {
                case 'no_leido': $m->leido = 0; $m->guardar(); break;
                case 'leido': $m->leido = 1; $m->guardar(); break;
                case 'eliminar': $m->eliminar(); break;
            }
        }
        header('Location: /bandeja' . (in_array($_POST['volver'] ?? '', ['contacto', 'soporte'], true) ? '?tipo=' . $_POST['volver'] : ''));
        exit;
    }
}
