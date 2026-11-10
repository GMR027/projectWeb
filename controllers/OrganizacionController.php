<?php

namespace Controllers;

use MVC\Router;
use Model\Organizacion;
use Model\Proyecto;
use Model\ProyectoAcceso;
use Model\Usuario;
use Classes\Almacenamiento;
use Classes\Plan;

/** Administración de organizaciones, planes y facturación manual (solo admin). */
class OrganizacionController
{
    /**
     * Sin la migración de planes la tabla no existe: se avisa en vez de dejar un 500
     * («Table 'project.organizaciones' doesn't exist») en la cara del usuario.
     */
    private static function faltaMigracion(Router $router): bool
    {
        if (Organizacion::disponible()) return false;
        $router->render('organizaciones/falta-migracion', ['titulo' => 'Organizaciones']);
        return true;
    }

    /** Organización por id (redirige a la lista si no existe) */
    private static function cargar(?int $id): ?Organizacion
    {
        $org = $id ? Organizacion::find($id) : null;
        if (!$org) {
            header('Location: /organizaciones');
            exit;
        }
        return $org;
    }

    public static function index(Router $router)
    {
        redirigir_si_no_admin();
        if (self::faltaMigracion($router)) return;
        $verArchivadas = !empty($_GET['archivadas']);
        $filas = [];
        foreach (Organizacion::listado($verArchivadas) as $org) {
            if ($verArchivadas && !$org->estaArchivada()) continue;
            $filas[] = [
                'org' => $org,
                'propietario' => $org->propietario(),
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
            'verArchivadas' => $verArchivadas,
        ]);
    }

    /** Alta de una organización vacía: los clientes se agregan desde su ficha */
    public static function crear(Router $router)
    {
        redirigir_si_no_admin();
        if (self::faltaMigracion($router)) return;
        $org = new Organizacion(['estado_plan' => 'trial', 'plan_inicio' => date('Y-m-d')]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::guardarDesdePost($org, $_POST);
            if (empty(Usuario::getAlertas())) {
                $r = $org->guardar();
                if (!empty($r['resultado'])) {
                    header('Location: /organizaciones/ver?id=' . (int)$r['id'] . '&creada=1');
                    exit;
                }
                Usuario::setAlerta('error', 'No se pudo guardar la organización.');
            }
        }

        $router->render('organizaciones/crear', [
            'titulo' => 'Nueva organización',
            'org' => $org,
            'planes' => Plan::todos(),
            'alertas' => Usuario::getAlertas(),
        ]);
    }

    /** Ficha de la organización: resumen, clientes, visitantes, obras y pagos */
    public static function ver(Router $router)
    {
        redirigir_si_no_admin();
        if (self::faltaMigracion($router)) return;
        $org = self::cargar(filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT));

        $usuarios = Usuario::whereAll('organizacion_id', (string)$org->id, 'nombre ASC');
        $clientes = array_values(array_filter($usuarios, fn($u) => (string)$u->rol === 'cliente' && in_array((string)$u->rol_org, ['propietario', 'gestor'], true)));
        $equipo = array_values(array_filter($usuarios, fn($u) => (string)$u->rol === 'visitante' && in_array((string)$u->rol_org, ['colaborador', 'visitante'], true)));

        $router->render('organizaciones/ver', [
            'titulo' => $org->nombre,
            'org' => $org,
            'propietario' => $org->propietario(),
            'plan' => $org->plan(),
            'limites' => $org->limites(),
            'obras_activas' => $org->contarObrasActivas(),
            'asientos' => $org->contarAsientos(),
            'visitantes' => $org->contarVisitantes(),
            'almacenamiento' => Almacenamiento::usadoCuenta((int)$org->id),
            'limite_almacenamiento' => Almacenamiento::limiteCuenta((int)$org->id),
            'clientes' => $clientes,
            'equipo' => $equipo,
            'obras' => Proyecto::whereAll('organizacion_id', (string)$org->id, 'nombre ASC'),
            'pagos' => $org->pagos(50),
            'puede_eliminarse' => $org->puedeEliminarse(),
        ]);
    }

    public static function editar(Router $router)
    {
        redirigir_si_no_admin();
        if (self::faltaMigracion($router)) return;
        $org = self::cargar(filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['accion'] ?? '') === 'pago') {
                $okPago = $org->registrarPago($_POST);
                header('Location: /organizaciones/ver?id=' . (int)$org->id . '&pago=' . ($okPago ? 'ok' : 'error') . '#pagos');
                exit;
            }
            self::guardarDesdePost($org, $_POST);
            if (!empty(Usuario::getAlertas())) {
                $alertas = Usuario::getAlertas();
            } else {
                header('Location: /organizaciones/ver?id=' . (int)$org->id . '&guardado=1');
                exit;
            }
        }

        $router->render('organizaciones/editar', [
            'titulo' => 'Editar ' . $org->nombre,
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
            'alertas' => $alertas ?? Usuario::getAlertas(),
        ]);
    }

    /** Campos de la organización comunes a crear y editar */
    private static function guardarDesdePost(Organizacion $org, array $post): void
    {
        $nombre = trim((string)($post['nombre'] ?? ''));
        if ($nombre === '') {
            Usuario::setAlerta('error', 'El nombre de la organización es obligatorio.');
        }
        $org->nombre = $nombre !== '' ? $nombre : $org->nombre;
        $org->razon_social = trim((string)($post['razon_social'] ?? ''));
        $org->rfc = strtoupper(trim((string)($post['rfc'] ?? '')));
        $org->correo_facturacion = trim((string)($post['correo_facturacion'] ?? ''));
        $org->telefono = trim((string)($post['telefono'] ?? ''));
        $logo = guardar_imagen_subida('logo', 'logos');
        if ($logo) $org->logo = $logo;

        $estado = (string)($post['estado_plan'] ?? 'trial');
        $estado = in_array($estado, ['trial', 'activo', 'suspendido', 'cancelado'], true) ? $estado : 'trial';
        $org->estado_plan = $estado;
        $org->plan_id = Plan::normalizar((string)($post['plan_id'] ?? Plan::POR_DEFECTO));
        $org->plan_inicio = ($post['plan_inicio'] ?? '') ?: ($org->plan_inicio ?: date('Y-m-d'));
        $org->plan_fin = ($post['plan_fin'] ?? '') ?: null;
        $org->gracia_hasta = ($post['gracia_hasta'] ?? '') ?: null;
        $org->precio_legado = isset($post['precio_legado']) && $post['precio_legado'] !== '' ? (float)$post['precio_legado'] : null;
        if ($estado === 'trial' && empty($org->trial_hasta)) {
            $org->trial_hasta = date('Y-m-d', strtotime('+14 days'));
        }
        if ($estado !== 'trial') {
            $org->trial_hasta = null;
        }
    }

    /** Archiva: deja de listarse, conserva obras, usuarios y pagos */
    public static function archivar()
    {
        redirigir_si_no_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Organizacion::disponible()) {
            header('Location: /organizaciones');
            exit;
        }
        $org = self::cargar(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($org->contarObrasActivas() > 0) {
            header('Location: /organizaciones/ver?id=' . (int)$org->id . '&error=' . urlencode('No se puede archivar: la organización tiene obras activas. Archívalas primero.'));
            exit;
        }
        $org->archivar();
        header('Location: /organizaciones?archivada=1');
        exit;
    }

    public static function desarchivar()
    {
        redirigir_si_no_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Organizacion::disponible()) {
            header('Location: /organizaciones');
            exit;
        }
        $org = self::cargar(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        $org->desarchivar();
        header('Location: /organizaciones/ver?id=' . (int)$org->id . '&desarchivada=1');
        exit;
    }

    /** Borrado real solo si la organización está vacía */
    public static function eliminar()
    {
        redirigir_si_no_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Organizacion::disponible()) {
            header('Location: /organizaciones');
            exit;
        }
        $org = self::cargar(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$org->puedeEliminarse()) {
            header('Location: /organizaciones/ver?id=' . (int)$org->id . '&error=' . urlencode('Solo se puede eliminar una organización sin obras, usuarios ni pagos.'));
            exit;
        }
        $org->eliminar();
        header('Location: /organizaciones?eliminada=1');
        exit;
    }

    /** Saca a un visitante/colaborador de la organización (el propietario debe transferir primero) */
    public static function quitarMiembro()
    {
        redirigir_si_no_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Organizacion::disponible()) {
            header('Location: /organizaciones');
            exit;
        }
        $org = self::cargar(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        $uid = filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        $u = $uid ? Usuario::find($uid) : null;
        if (!$u || (int)$u->organizacion_id !== (int)$org->id) {
            header('Location: /organizaciones/ver?id=' . (int)$org->id);
            exit;
        }
        if ((string)$u->rol_org === 'propietario') {
            header('Location: /organizaciones/ver?id=' . (int)$org->id . '&error=' . urlencode('El propietario no puede salir de la organización: transfiere la propiedad primero.') . '#clientes');
            exit;
        }
        ProyectoAcceso::quitarEnOrganizacion((int)$u->id, (int)$org->id);
        $u->organizacion_id = null;
        $u->rol = 'visitante';
        $u->nivel_acceso = 'ver';
        $u->rol_org = 'visitante';
        $u->puede_crear_proyectos = 0;
        $u->guardar();
        header('Location: /organizaciones/ver?id=' . (int)$org->id . '&quitado=1#equipo');
        exit;
    }

    /** Transfiere la propiedad de la organización a otro usuario de la misma organización */
    public static function transferir()
    {
        redirigir_si_no_admin();
        if (!Organizacion::disponible()) {
            header('Location: /organizaciones');
            exit;
        }
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
        header('Location: /organizaciones/ver?id=' . (int)$id . '&transferido=1#clientes');
        exit;
    }

    /** Recalcula el contador de almacenamiento de una organización */
    public static function recalcular()
    {
        redirigir_si_no_admin();
        if (!Organizacion::disponible()) {
            header('Location: /organizaciones');
            exit;
        }
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
