<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Partida;
use Model\Usuario;
use Model\ProyectoAcceso;
use Model\Organizacion;

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
        if (org_solo_lectura()) {
            header('Location: /proyectos?error=' . urlencode('Tu organización está en solo lectura. Reactiva el plan para crear obras.'));
            exit;
        }
        if (!puede_crear_proyecto()) {
            $cupo = cupo_proyectos();
            if (!$cupo || $cupo['limite'] === null) {
                $msg = 'Tu plan no permite crear obras o no tienes permiso para hacerlo.';
            } elseif ($cupo['usados'] >= (int)$cupo['limite']) {
                $msg = "Alcanzaste tu límite de {$cupo['limite']} obra(s) activa(s). Archiva una o amplía el plan.";
            } else {
                $msg = 'Tu plan no permite crear obras o no tienes permiso para hacerlo.';
            }
            header('Location: /proyectos' . ($msg ? '?error=' . urlencode($msg) : ''));
            exit;
        }
        $alertas = [];
        $proyecto = new Proyecto();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $proyecto->sincronizar($_POST);
            $proyecto->id = null;
            $proyecto->archivado = 0;
            // El admin puede asignar un propietario; los demás siempre son dueños
            $proyecto->usuario_id = $_SESSION['id'];
            if (is_admin() && ($dueno = Usuario::find(filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT)))) {
                $proyecto->usuario_id = $dueno->id;
            }
            // Organización dueña: la elegida por el admin, la del propietario o la de la sesión
            $orgId = is_admin() ? (int)($_POST['organizacion_id'] ?? 0) : 0;
            if (!$orgId && $proyecto->usuario_id) {
                $orgDueno = Organizacion::deUsuario((int)$proyecto->usuario_id);
                if ($orgDueno) $orgId = (int)$orgDueno->id;
            }
            if (!$orgId) {
                $orgSesion = organizacion_actual();
                if ($orgSesion) $orgId = (int)$orgSesion->id;
            }
            if (!$orgId && is_admin()) {
                $interna = Organizacion::interna();
                if ($interna) $orgId = (int)$interna->id;
            }
            $proyecto->organizacion_id = $orgId ?: null;

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
                    if ($plantillaId = (int)($_POST['plantilla_id'] ?? 0)) {   // Fase 4.3: arrancar desde una plantilla de proyecto
                        [$okPl, $msgPl] = \Classes\PlantillaProyecto::aplicar($plantillaId, $proyecto, usuario_actual());
                        if (!$okPl) $_SESSION['pp_msg'] = ['ok' => false, 'texto' => 'El proyecto se creó, pero la plantilla no se aplicó: ' . $msgPl];
                    }
                    if (is_admin()) {
                        ProyectoAcceso::sincronizarProyecto((int)$proyecto->id, $_POST['usuarios_acceso'] ?? []);
                    }
                    $modo = $_POST['modo_inicio'] ?? 'guiado';
                    $destino = $modo === 'ninguno' || !isset(\Classes\PorDondeEmpezar::MODOS[$modo])
                        ? '/proyectos?creado=1'
                        : url_inicio_proyecto((int)$proyecto->id) . '&inicio=' . $modo;
                    header('Location: ' . $destino);
                    exit;
                }
            }
        }

        $router->render('proyectos/crear', [
            'usuariosAcceso' => array_map('intval', $_POST['usuarios_acceso'] ?? []),
            'titulo' => 'Nuevo Proyecto',
            'plantillas' => \Classes\PlantillaProyecto::disponible() ? \Classes\PlantillaProyecto::visibles(usuario_actual()) : [],
            'alertas' => $alertas,
            'proyecto' => $proyecto,
            'organizaciones' => is_admin() && Organizacion::disponible() ? Organizacion::all('nombre ASC') : [],
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
            $orgActual = $proyecto->organizacion_id;
            $logoActual = $proyecto->logo;
            $caratulaActual = $proyecto->caratula;
            $contratistaLogoActual = $proyecto->contratista_logo;
            $proyecto->sincronizar($_POST);
            $proyecto->id = $id;
            $proyecto->usuario_id = $duenoActual;
            if (is_admin() && ($dueno = Usuario::find(filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT)))) {
                $proyecto->usuario_id = $dueno->id;
            }
            // La organización solo la cambia el admin; el resto conserva la dueña.
            $proyecto->organizacion_id = is_admin() && isset($_POST['organizacion_id'])
                ? ((int)$_POST['organizacion_id'] ?: $orgActual)
                : $orgActual;

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
            'proyecto' => $proyecto,
            'organizaciones' => is_admin() && Organizacion::disponible() ? Organizacion::all('nombre ASC') : [],
        ]);
    }

    public static function ver(Router $router)
    {
        redirigir_si_no_auth();
        $id = filter_var($_GET['id'] ?? $_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);   // «proyecto» para los enlaces del menú
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
                if ($proyecto && puede_eliminar_proyecto($proyecto)) {
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

    /** Archiva una obra: libera cupo de obras activas, conserva datos y almacenamiento */
    public static function archivar()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = $id ? Proyecto::find($id) : null;
        if (!$proyecto || !puede_archivar_proyecto($proyecto)) {
            header('Location: /proyectos?error=' . urlencode('No puedes archivar esa obra'));
            exit;
        }
        $proyecto->archivar((int)$_SESSION['id']);
        header('Location: /proyectos?archivado=1');
        exit;
    }

    /** Desarchiva una obra si aún hay cupo de obras activas */
    public static function desarchivar()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = $id ? Proyecto::find($id) : null;
        if (!$proyecto || !puede_archivar_proyecto($proyecto)) {
            header('Location: /proyectos?error=' . urlencode('No puedes desarchivar esa obra'));
            exit;
        }
        $cupo = cupo_proyectos();
        if ($cupo && $cupo['limite'] !== null && $cupo['usados'] >= (int)$cupo['limite']) {
            header('Location: /proyectos?error=' . urlencode('No hay cupo de obras activas. Archiva otra o amplía el plan.'));
            exit;
        }
        $proyecto->desarchivar();
        header('Location: /proyectos?desarchivado=1');
        exit;
    }
}
