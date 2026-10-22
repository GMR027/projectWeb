<?php

namespace Controllers;

use MVC\Router;
use Classes\CamposPersonalizados;
use Classes\ListaDiseno;

/** Fase 4: diseño de columnas de las listas (4.1) y campos personalizados (4.2) */
class PersonalizacionController
{
    // ---------- 4.1 diseño de una lista ----------

    public static function diseno(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $herr = (string)($fuente['herramienta'] ?? '');
        if (!ListaDiseno::existe($herr)) {
            header('Location: /proyectos/ver?proyecto=' . $pid);
            exit;
        }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['restablecer'])) {
                ListaDiseno::restablecer($pid, $herr);
            } else {
                $cols = [];
                foreach ((array)($_POST['columnas'] ?? []) as $c) if (is_array($c) && !empty($c['k'])) $cols[] = ['k' => (string)$c['k'], 'n' => (string)($c['n'] ?? '')];
                if (!ListaDiseno::guardar($pid, $herr, $cols)) $error = 'No se pudo guardar el diseño (¿falta aplicar la migración de personalización?)';
            }
            if ($error === '') {
                header('Location: ' . ListaDiseno::HERRAMIENTAS[$herr][2] . '?proyecto=' . $pid);
                exit;
            }
        }
        $router->render('listas/diseno', [
            'titulo' => 'Diseñar ' . ListaDiseno::HERRAMIENTAS[$herr][0] . ' - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'herramienta' => $herr, 'info' => ListaDiseno::HERRAMIENTAS[$herr], 'columnas' => ListaDiseno::paraFormulario($pid, $herr), 'error' => $error,
        ]);
    }

    // ---------- 4.2 campos personalizados ----------

    public static function campos(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $router->render('campos/index', [
            'titulo' => 'Campos personalizados - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'campos' => CamposPersonalizados::deProyecto($pid), 'error' => (string)($_GET['error'] ?? ''), 'ok' => (string)($_GET['ok'] ?? ''),
        ]);
    }

    public static function guardarCampo()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = CamposPersonalizados::guardar((int)$p->id, (int)($_POST['id'] ?? 0), (string)($_POST['entidad'] ?? ''), (string)($_POST['nombre'] ?? ''), (string)($_POST['tipo'] ?? 'texto'), (string)($_POST['opciones'] ?? ''));
        header('Location: /campos?proyecto=' . $p->id . ($ok ? '&ok=1' : '&error=' . urlencode($msg)));
        exit;
    }

    public static function eliminarCampo()
    {
        $p = proyecto_de_peticion(true);
        CamposPersonalizados::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        header('Location: /campos?proyecto=' . $p->id . '&ok=1');
        exit;
    }

    /** Guarda el valor de un campo en un registro (se edita en la celda de la lista) */
    public static function guardarValor()
    {
        $p = proyecto_api('gestionar');
        [$ok, $v] = CamposPersonalizados::guardarValor((int)$p->id, (int)($_POST['campo'] ?? 0), (int)($_POST['registro'] ?? 0), (string)($_POST['valor'] ?? ''));
        json_respuesta($ok ? ['success' => true, 'valor' => $v] : ['success' => false, 'error' => $v], $ok ? 200 : 422);
    }
}
