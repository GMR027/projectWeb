<?php

namespace Controllers;

use MVC\Router;
use Model\Curva;
use Model\Material;
use Classes\CurvaProductividad;

/** Curvas de productividad por proyecto + gráfica maestra */
class CurvaController
{
    private static function curvaDe($proyecto): ?Curva
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $c = Curva::find(filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT));
        return $c && (int)$c->proyecto_id === (int)$proyecto->id ? $c : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $curvas = Curva::deProyecto((int)$proyecto->id);
        $resumen = [];
        foreach ($curvas as $c) {
            $s = CurvaProductividad::series($c);
            $ultimo = null;
            foreach ($s['actual_acum'] as $v) if ($v !== null) $ultimo = $v;
            $resumen[$c->id] = [
                'periodos' => $s['n'],
                'avance_real' => $ultimo === null ? null : round($ultimo / max(1, (int)$c->cantidad) * 100, 1),
            ];
        }
        $router->render('curvas/index', [
            'titulo' => 'Curvas de productividad - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'curvas' => $curvas, 'resumen' => $resumen, 'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        self::formulario($router, $proyecto, new Curva([
            'fecha_inicio' => $proyecto->fecha_inicio, 'fecha_fin' => $proyecto->fecha_fin,
        ]));
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $curva = self::curvaDe($proyecto);
        if (!$curva) {
            header('Location: /curvas?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $proyecto, $curva);
    }

    private static function formulario(Router $router, $proyecto, Curva $curva)
    {
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $curva->nombre = trim($_POST['nombre'] ?? '');
            $curva->cantidad = (int)($_POST['cantidad'] ?? 0);
            $curva->unidad = mb_substr(trim($_POST['unidad'] ?? '') ?: 'm', 0, 20);
            $curva->tipo_curva = $_POST['tipo_curva'] ?? 'campana';
            $curva->fecha_inicio = $_POST['fecha_inicio'] ?? '';
            $curva->fecha_fin = $_POST['fecha_fin'] ?? '';
            $curva->proyecto_id = $proyecto->id;
            // El material debe pertenecer al mismo proyecto
            $mid = filter_var($_POST['material_id'] ?? null, FILTER_VALIDATE_INT);
            $m = $mid ? Material::find($mid) : null;
            $curva->material_id = $m && (int)$m->proyecto_id === (int)$proyecto->id ? $m->id : null;
            $alertas = $curva->validar();
            if (!$alertas) {
                $r = $curva->guardar();
                if (is_array($r)) $curva->id = $r['id'];
                header('Location: /curvas/ver?proyecto=' . $proyecto->id . '&id=' . $curva->id);
                exit;
            }
        }
        $router->render('curvas/formulario', [
            'titulo' => $curva->id ? 'Editar curva' : 'Nueva curva', 'proyecto' => $proyecto, 'curva' => $curva,
            'alertas' => $alertas, 'materiales' => Material::deProyecto((int)$proyecto->id),
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($c = self::curvaDe($proyecto)) $c->eliminar();
        header('Location: /curvas?proyecto=' . $proyecto->id);
        exit;
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $curva = self::curvaDe($proyecto);
        if (!$curva) {
            header('Location: /curvas?proyecto=' . $proyecto->id);
            exit;
        }
        $material = $curva->material_id ? Material::find($curva->material_id) : null;
        $router->render('curvas/ver', [
            'titulo' => 'Curva - ' . $curva->nombre, 'proyecto' => $proyecto, 'curva' => $curva,
            'material' => $material, 'serie' => CurvaProductividad::series($curva),
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    /** Curva S del programa: avance programado contra real */
    public static function curvaS(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $d = \Classes\CurvaS::calcular($proyecto);
        $router->render('curvas/s', [
            'titulo' => 'Curva S - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'datos' => $d,
            'desde' => \Classes\CurvaS::primerHistorial((int)$proyecto->id), 'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    /** Avance real acumulado capturado a mano por semana (vacío = se calcula solo) */
    public static function guardarCurvaSManual()
    {
        $proyecto = proyecto_de_peticion(true);
        \Classes\CurvaS::guardarManual((int)$proyecto->id, is_array($_POST['manual'] ?? null) ? $_POST['manual'] : []);
        header('Location: /curvas/s?proyecto=' . $proyecto->id . '&ok=1');
        exit;
    }

    public static function maestra(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('curvas/maestra', [
            'titulo' => 'Gráfica maestra - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'maestra' => self::datosMaestra($proyecto),
        ]);
    }

    /** Datos de la gráfica maestra (también para exportar): fechas del programa de obra */
    public static function datosMaestra($proyecto): array
    {
        return CurvaProductividad::maestra(Curva::deProyecto((int)$proyecto->id), $proyecto->fecha_inicio, $proyecto->fecha_fin);
    }

    /** Guarda el real de un periodo (vacío = sin captura) */
    public static function guardarReal()
    {
        header('Content-Type: application/json');
        $curva = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth()
            ? Curva::find(filter_var($_POST['curva'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $curva ? \Model\Proyecto::find($curva->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $periodo = filter_var($_POST['periodo'] ?? null, FILTER_VALIDATE_INT);
        $n = CurvaProductividad::numPeriodos($curva->fecha_inicio, $curva->fecha_fin);
        $txt = trim((string)($_POST['valor'] ?? ''));
        if ($periodo === false || $periodo === null || $periodo < 0 || $periodo >= $n || ($txt !== '' && (!is_numeric($txt) || (float)$txt < 0 || (float)$txt > 1e9))) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Dato no válido']);
            exit;
        }
        $curva->guardarReal($periodo, $txt === '' ? null : (float)$txt);
        echo json_encode(['success' => true]);
        exit;
    }
}
