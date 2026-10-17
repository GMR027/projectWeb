<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Partida;
use Model\Usuario;
use Model\ProyectoAcceso;

class ProyectoController
{
    public static function listar(Router $router)
    {
        redirigir_si_no_auth();
        $proyectos = Proyecto::visiblesPara(usuario_actual());
        $router->render('proyectos/listar', [
            'titulo' => 'Proyectos',
            'proyectos' => $proyectos
        ]);
    }

    public static function crear(Router $router)
    {
        redirigir_si_no_auth();
        if (!puede_crear_proyecto()) {
            $cupo = cupo_proyectos();
            $msg = $cupo ? "Alcanzaste tu límite de {$cupo['limite']} proyecto(s). Solicita al administrador ampliarlo." : '';
            header('Location: /proyectos' . ($msg ? '?error=' . urlencode($msg) : ''));
            exit;
        }
        $alertas = [];
        $proyecto = new Proyecto();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $proyecto->sincronizar($_POST);
            $proyecto->id = null;
            // El admin puede asignar un propietario; los demás siempre son dueños
            $proyecto->usuario_id = $_SESSION['id'];
            if (is_admin() && ($dueno = Usuario::find(filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT)))) {
                $proyecto->usuario_id = $dueno->id;
            }

            $proyecto->contratista_logo = guardar_imagen_subida('contratista_logo', 'logos');

            // Logo y carátula: solo imágenes reales (tipo MIME comprobado, nombre y extensión decididos por el servidor)
            $proyecto->logo = guardar_imagen_subida('logo', 'logos');
            $proyecto->caratula = guardar_imagen_subida('caratula', 'caratulas');

            $alertas = $proyecto->validar();
            if (empty($alertas)) {
                $resultado = $proyecto->guardar();
                if ($resultado['resultado']) {
                    $proyecto->id = $resultado['id'];
                    $proyecto->inicializarJornadaDefault();
                    if (is_admin()) {
                        ProyectoAcceso::sincronizarProyecto((int)$proyecto->id, $_POST['usuarios_acceso'] ?? []);
                    }
                    header('Location: /proyectos?creado=1');
                    exit;
                }
            }
        }

        $router->render('proyectos/crear', [
            'usuariosAcceso' => array_map('intval', $_POST['usuarios_acceso'] ?? []),
            'titulo' => 'Nuevo Proyecto',
            'alertas' => $alertas,
            'proyecto' => $proyecto
        ]);
    }

    public static function editar(Router $router)
    {
        redirigir_si_no_auth();
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: /proyectos');
            exit;
        }
        $proyecto = Proyecto::find($id);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_gestionar_proyecto($proyecto);
        $alertas = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $duenoActual = $proyecto->usuario_id;
            $logoActual = $proyecto->logo;
            $caratulaActual = $proyecto->caratula;
            $contratistaLogoActual = $proyecto->contratista_logo;
            $proyecto->sincronizar($_POST);
            $proyecto->id = $id;
            $proyecto->usuario_id = $duenoActual;
            if (is_admin() && ($dueno = Usuario::find(filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT)))) {
                $proyecto->usuario_id = $dueno->id;
            }

            $proyecto->contratista_logo = guardar_imagen_subida('contratista_logo', 'logos') ?? $contratistaLogoActual;

            $proyecto->logo = guardar_imagen_subida('logo', 'logos') ?? $logoActual;
            $proyecto->caratula = guardar_imagen_subida('caratula', 'caratulas') ?? $caratulaActual;

            $alertas = $proyecto->validar();
            if (empty($alertas)) {
                if ($proyecto->guardar()) {
                    if (is_admin()) {
                        ProyectoAcceso::sincronizarProyecto((int)$id, $_POST['usuarios_acceso'] ?? []);
                    }
                    header('Location: /proyectos?editado=1');
                    exit;
                }
            }
        }

        $router->render('proyectos/editar', [
            'usuariosAcceso' => $_SERVER['REQUEST_METHOD'] === 'POST'
                ? array_map('intval', $_POST['usuarios_acceso'] ?? [])
                : ProyectoAcceso::usuariosDe((int)$id),
            'titulo' => 'Editar Proyecto',
            'alertas' => $alertas,
            'proyecto' => $proyecto
        ]);
    }

    public static function ver(Router $router)
    {
        redirigir_si_no_auth();
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: /proyectos');
            exit;
        }
        $proyecto = Proyecto::find($id);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_ver_proyecto($proyecto);
        $router->render('proyectos/ver', [
            'titulo' => $proyecto->nombre,
            'proyecto' => $proyecto
        ]);
    }

    public static function eliminar()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id) {
                $proyecto = Proyecto::find($id);
                if ($proyecto && puede_gestionar_proyecto($proyecto)) {
                    // Las filas se borran en cascada; los archivos de fotos hay que quitarlos aparte
                    foreach (\Model\ReporteDiario::deProyecto((int)$proyecto->id) as $rd) {
                        foreach ($rd->getFotos() as $f) \Classes\Imagenes::eliminarEn('reportes', $f);
                    }
                    foreach (\Model\Personal::deProyecto((int)$proyecto->id) as $pe) \Classes\Imagenes::eliminarEn('personal', $pe->imagen);
                    foreach (\Model\Archivo::deProyecto((int)$proyecto->id) as $ar) { if ($r = $ar->ruta()) @unlink($r); }
                    $proyecto->eliminar();
                }
            }
            header('Location: /proyectos');
            exit;
        }
    }
}
