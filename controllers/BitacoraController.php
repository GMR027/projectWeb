<?php

namespace Controllers;

use MVC\Router;
use Model\Bitacora;
use Model\BitacoraDepartamento;

/** Bitácora de obra: registros por fecha, departamento e importancia */
class BitacoraController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $depto = trim($_GET['departamento'] ?? '');
        $impGet = $_GET['importancia'] ?? '';
        $imp = is_string($impGet) && isset(Bitacora::IMPORTANCIAS[$impGet]) ? $impGet : '';
        $registros = Bitacora::deProyecto((int)$proyecto->id);
        $deptos = \Classes\Catalogo::departamentos((int)$proyecto->id);
        $registros = array_values(array_filter($registros, fn($r) =>
            ($depto === '' || ($depto === '__sin' ? $r->departamento === '' : $r->departamento === $depto)) && ($imp === '' || $r->importancia === $imp)));
        $pid = (int)$proyecto->id;
        $cp = \Classes\CamposPersonalizados::aplicar($pid, 'bitacora', $registros, fn($r) => (int)$r->id);
        $router->render('bitacora/index', [
            'titulo' => 'Bitácora - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'registros' => $cp['items'], 'cp' => $cp, 'diseno' => \Classes\ListaDiseno::para($pid, 'bitacora'),
            'deptos' => $deptos, 'filtroDepto' => $depto, 'filtroImp' => $imp,
        ]);
    }

    public static function crear(Router $router)
    {
        self::formulario($router, new Bitacora());
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $b = Bitacora::find(filter_var(($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET)['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$b || (int)$b->proyecto_id !== (int)$proyecto->id) {
            header('Location: /bitacora?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $b);
    }

    private static function formulario(Router $router, Bitacora $b)
    {
        $proyecto = proyecto_de_peticion(true);
        $alertas = [];
        $departamentoAnterior = $b->departamento;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $b->proyecto_id = $proyecto->id;
            $b->fecha = trim($_POST['fecha'] ?? '');
            $b->departamento = trim($_POST['departamento'] ?? '');
            $b->descripcion = trim($_POST['descripcion'] ?? '');
            $b->importancia = $_POST['importancia'] ?? '';
            $alertas = $b->validar();
            $catalogo = \Classes\Catalogo::departamentos((int)$proyecto->id);
            if ($b->departamento !== '' && !in_array($b->departamento, $catalogo, true) && $b->departamento !== $departamentoAnterior) {
                $alertas['error'][] = 'Elige un departamento del catálogo';
            }
            if (!$alertas) {
                $b->guardar();
                header('Location: /bitacora?proyecto=' . $proyecto->id . '&ok=1');
                exit;
            }
        }
        $deptos = \Classes\Catalogo::departamentos((int)$proyecto->id);
        $router->render('bitacora/formulario', [
            'titulo' => $b->id ? 'Editar registro de bitácora' : 'Nuevo registro de bitácora',
            'proyecto' => $proyecto, 'registro' => $b, 'alertas' => $alertas,
            'departamentos' => $deptos,
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $b = Bitacora::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($b && (int)$b->proyecto_id === (int)$proyecto->id) $b->eliminar();
        header('Location: /bitacora?proyecto=' . $proyecto->id);
        exit;
    }

    // ========================= DEPARTAMENTOS =========================

    public static function departamentos(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $lista = [];
        foreach (BitacoraDepartamento::deProyecto((int)$proyecto->id) as $d) $lista[] = ['depto' => $d, 'uso' => $d->enUso()];
        $router->render('bitacora/departamentos', ['titulo' => 'Departamentos de bitácora - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'lista' => $lista]);
    }

    public static function departamentoCrear(Router $router)
    {
        self::departamentoFormulario($router, new BitacoraDepartamento());
    }

    public static function departamentoEditar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $d = BitacoraDepartamento::find(filter_var(($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET)['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$d || (int)$d->proyecto_id !== (int)$proyecto->id) {
            header('Location: /bitacora/departamentos?proyecto=' . $proyecto->id);
            exit;
        }
        self::departamentoFormulario($router, $d);
    }

    private static function departamentoFormulario(Router $router, BitacoraDepartamento $d)
    {
        $proyecto = proyecto_de_peticion(true);
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $anterior = $d->nombre;
            $d->proyecto_id = $proyecto->id;
            $d->nombre = trim($_POST['nombre'] ?? '');
            $alertas = $d->validar();
            if (!$alertas) {
                $d->guardar();
                // Al renombrar, los registros de la bitácora siguen al nuevo nombre
                if ($anterior !== '' && $anterior !== $d->nombre) {
                    $n = BitacoraDepartamento::escapar($d->nombre);
                    $o = BitacoraDepartamento::escapar($anterior);
                    BitacoraDepartamento::ejecutar("UPDATE bitacora SET departamento = '$n' WHERE proyecto_id = " . (int)$proyecto->id . " AND departamento = '$o'");
                }
                header('Location: /bitacora/departamentos?proyecto=' . $proyecto->id . '&ok=1');
                exit;
            }
        }
        $router->render('bitacora/departamento-formulario', [
            'titulo' => $d->id ? 'Editar departamento' : 'Nuevo departamento', 'proyecto' => $proyecto, 'depto' => $d, 'alertas' => $alertas,
        ]);
    }

    public static function departamentoEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $d = BitacoraDepartamento::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        $error = '';
        if ($d && (int)$d->proyecto_id === (int)$proyecto->id) {
            $uso = $d->enUso();
            if ($uso > 0) $error = '&error=' . urlencode("No se puede eliminar «{$d->nombre}»: lo usan $uso registro(s) de la bitácora.");
            else $d->eliminar();
        }
        header('Location: /bitacora/departamentos?proyecto=' . $proyecto->id . $error);
        exit;
    }
}
