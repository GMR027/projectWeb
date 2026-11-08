<?php

namespace Controllers;

use MVC\Router;
use Model\Organizacion;
use Model\Proyecto;
use Classes\Almacenamiento;
use Classes\Plan;

/** Administración de organizaciones, planes y facturación manual (solo admin). */
class OrganizacionController
{
    public static function index(Router $router)
    {
        redirigir_si_no_admin();
        $filas = [];
        foreach (Organizacion::all('nombre ASC') as $org) {
            $prop = $org->propietario();
            $filas[] = [
                'org' => $org,
                'propietario' => $prop,
                'plan' => $org->plan(),
                'limites' => $org->limites(),
                'obras' => $org->contarObrasActivas(),
                'asientos' => $org->contarAsientos(),
                'visitantes' => $org->contarVisitantes(),
                'almacenamiento' => Almacenamiento::usadoCuenta((int)$org->id),
                'limite_almacenamiento' => Almacenamiento::limiteCuenta((int)$org->id),
            ];
        }
        $router->render('organizaciones/index', [
            'titulo' => 'Organizaciones',
            'filas' => $filas,
            'planes' => Plan::todos(),
        ]);
    }

    public static function editar(Router $router)
    {
        redirigir_si_no_admin();
        $id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $org = $id ? Organizacion::find($id) : null;
        if (!$org) {
            header('Location: /organizaciones');
            exit;
        }
        $msg = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['accion'] ?? '') === 'pago') {
                $okPago = $org->registrarPago($_POST);
                header('Location: /organizaciones/editar?id=' . (int)$org->id . '&pago=' . ($okPago ? 'ok' : 'error'));
                exit;
            }
            $org->nombre = trim((string)($_POST['nombre'] ?? '')) ?: $org->nombre;
            $org->razon_social = trim((string)($_POST['razon_social'] ?? ''));
            $org->rfc = strtoupper(trim((string)($_POST['rfc'] ?? '')));
            $org->correo_facturacion = trim((string)($_POST['correo_facturacion'] ?? ''));
            $org->telefono = trim((string)($_POST['telefono'] ?? ''));
            $org->guardar();
            $precioLegado = isset($_POST['precio_legado']) && $_POST['precio_legado'] !== ''
                ? (float)$_POST['precio_legado'] : $org->precio_legado;
            $org->fijarPlan(
                (string)($_POST['plan_id'] ?? $org->plan_id),
                (string)($_POST['estado_plan'] ?? $org->estado_plan),
                $_POST['plan_inicio'] ?? $org->plan_inicio,
                $_POST['plan_fin'] ?? $org->plan_fin,
                $_POST['gracia_hasta'] ?? $org->gracia_hasta,
                $precioLegado
            );
            header('Location: /organizaciones?editado=1');
            exit;
        }
        $router->render('organizaciones/editar', [
            'titulo' => 'Editar organización',
            'org' => $org,
            'propietario' => $org->propietario(),
            'plan' => $org->plan(),
            'limites' => $org->limites(),
            'planes' => Plan::todos(),
            'obras' => $org->contarObrasActivas(),
            'asientos' => $org->contarAsientos(),
            'visitantes' => $org->contarVisitantes(),
            'almacenamiento' => Almacenamiento::usadoCuenta((int)$org->id),
            'limite_almacenamiento' => Almacenamiento::limiteCuenta((int)$org->id),
            'pagos' => $org->pagos(20),
            'usuarios_org' => \Model\Usuario::whereAll('organizacion_id', (string)$org->id, 'nombre ASC'),
        ]);
    }

    /** Transfiere la propiedad de la organización a otro usuario de la misma organización */
    public static function transferir()
    {
        redirigir_si_no_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /organizaciones');
            exit;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $nuevo = filter_var($_POST['nuevo_propietario_id'] ?? null, FILTER_VALIDATE_INT);
        $org = $id ? Organizacion::find($id) : null;
        if ($org && $nuevo) {
            $org->transferirPropiedad((int)$nuevo);
        }
        header('Location: /organizaciones/editar?id=' . (int)$id . '&transferido=1');
        exit;
    }

    /** Recalcula el contador de almacenamiento de una organización */
    public static function recalcular()
    {
        redirigir_si_no_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /organizaciones');
            exit;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id && Organizacion::find($id)) {
            Almacenamiento::recalcularCuenta((int)$id);
        }
        header('Location: /organizaciones?recalculado=1');
        exit;
    }
}
