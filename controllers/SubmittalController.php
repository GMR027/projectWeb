<?php

namespace Controllers;

use MVC\Router;
use Model\Material;
use Model\Submittal;
use Model\Msr;

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
            'msrNombres' => array_column(array_map(fn($m) => ['id' => $m->id, 'n' => $m->nombre], Msr::deProyecto((int)$proyecto->id)), 'n', 'id'),
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
            'submittal' => $submittal, 'materiales' => $submittal->materiales(), 'verif' => \Classes\MaterialesInteligentes::deProyecto((int)$proyecto->id),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $msrId = Msr::deProyectoPorId((int)$proyecto->id, $_GET['msr'] ?? null)?->id ?? Msr::asegurar((int)$proyecto->id)->id;
        $submittal = new Submittal(['numero_trabajo' => $proyecto->numero_trabajo ?? '', 'msr_id' => $msrId]);
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
            $submittal->especialidad = \Model\Especialidad::resolver((int)$proyecto->id, $submittal->especialidad);
            $submittal->proyecto_id = $proyecto->id;
            $submittal->msr_id = Msr::deProyectoPorId((int)$proyecto->id, $_POST['msr_id'] ?? null)?->id;
            $marcados = array_map('intval', $_POST['materiales'] ?? []);
            $alertas = $submittal->validar();
            // Materiales inteligentes: un material no aprobado por la normativa solo se envía si se confirma expresamente
            $malos = $alertas ? [] : \Classes\MaterialesInteligentes::noAprobados((int)$proyecto->id, $marcados);
            $nuevosMalos = array_diff_key($malos, array_flip(array_map('intval', $submittal->id ? $submittal->idsMateriales() : [])));
            if ($nuevosMalos && empty($_POST['confirmar_no_aprobados'])) {
                $alertas['error'][] = 'Materiales NO aprobados por las normativas y especificaciones: ' . implode('; ', array_map(fn($m) => $m['nombre'] . ' (' . $m['resumen'] . ')', $nuevosMalos)) . '. Quítalos del paquete o marca la casilla de confirmación para enviarlos de todas formas.';
                $pedirConfirmacion = true;
            }
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
            'materiales' => Material::deProyecto((int)$proyecto->id), 'msrs' => Msr::deProyecto((int)$proyecto->id),
            'noAprobados' => \Classes\MaterialesInteligentes::noAprobados((int)$proyecto->id, array_map(fn($m) => (int)$m->id, Material::deProyecto((int)$proyecto->id))),
            'pedirConfirmacion' => $pedirConfirmacion ?? false,
        ]);
    }

    /** Cicla el estatus: enviado → rechazado → aprobado (o fija el indicado) */
    public static function estatus()
    {
        $proyecto = proyecto_de_peticion(true);
        $s = self::submittalDe($proyecto);
        $nuevo = $_POST['estatus'] ?? '';
        if (!$s || !isset(Submittal::ESTATUS[$nuevo])) {
            header('Content-Type: application/json');
            http_response_code(422);
            echo json_encode(['success' => false]);
            exit;
        }
        $anterior = $s->estatus;
        $s->estatus = $nuevo;
        $s->guardar();
        $s->sincronizarMsr($anterior);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'estatus' => $nuevo]);
        exit;
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($s = self::submittalDe($proyecto)) $s->eliminar();
        header('Location: /submittals?proyecto=' . $proyecto->id);
        exit;
    }
}
