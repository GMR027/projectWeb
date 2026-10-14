<?php

namespace Controllers;

use MVC\Router;
use Model\Material;
use Model\Submittal;

/** Paquetes de submittals (empaquetador de materiales) */
class SubmittalController
{
    private static function submittalDe($proyecto): ?Submittal
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $s = Submittal::find(filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT));
        return $s && (int)$s->proyecto_id === (int)$proyecto->id ? $s : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $paquetes = Submittal::deProyecto((int)$proyecto->id);
        $router->render('submittals/index', [
            'titulo' => 'Submittals - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'paquetes' => $paquetes,
            'conteos' => Submittal::conteos((int)$proyecto->id),
        ]);
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $submittal = self::submittalDe($proyecto);
        if (!$submittal) {
            header('Location: /submittals?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('submittals/ver', [
            'titulo' => 'Submittal ' . $submittal->numero_submittal, 'proyecto' => $proyecto,
            'submittal' => $submittal, 'materiales' => $submittal->materiales(),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $submittal = new Submittal(['numero_trabajo' => $proyecto->numero_trabajo ?? '']);
        self::formulario($router, $proyecto, $submittal, []);
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $submittal = self::submittalDe($proyecto);
        if (!$submittal) {
            header('Location: /submittals?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $proyecto, $submittal, $submittal->idsMateriales());
    }

    private static function formulario(Router $router, $proyecto, Submittal $submittal, array $marcados)
    {
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach (['fecha', 'numero_trabajo', 'departamento', 'para', 'numero_submittal', 'especialidad'] as $c) {
                $submittal->$c = trim($_POST[$c] ?? '');
            }
            $submittal->proyecto_id = $proyecto->id;
            $marcados = array_map('intval', $_POST['materiales'] ?? []);
            $alertas = $submittal->validar();
            if (!$alertas) {
                $r = $submittal->guardar();
                if (is_array($r)) $submittal->id = $r['id'];
                $submittal->sincronizarMateriales($marcados);
                header('Location: /submittals/ver?proyecto=' . $proyecto->id . '&id=' . $submittal->id);
                exit;
            }
        }
        $router->render('submittals/formulario', [
            'titulo' => $submittal->id ? 'Editar submittal' : 'Nuevo submittal', 'proyecto' => $proyecto,
            'submittal' => $submittal, 'marcados' => $marcados, 'alertas' => $alertas,
            'materiales' => Material::deProyecto((int)$proyecto->id),
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($s = self::submittalDe($proyecto)) $s->eliminar();
        header('Location: /submittals?proyecto=' . $proyecto->id);
        exit;
    }
}
