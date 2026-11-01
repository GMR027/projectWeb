<?php

namespace Controllers;

use MVC\Router;
use Model\Usuario;
use Model\Proyecto;
use Model\ProyectoAcceso;
use Classes\Email;
use Classes\LoginLimite;

class AuthController
{
    public static function login(Router $router)
    {
        $alertas = [];
        $usuario = new Usuario();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Solo textos: un formulario manipulado (email[]=…) no debe romper la autenticación
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $usuario = new Usuario(['email' => $email, 'password' => $password]);
            $alertas = $usuario->validarLogin();

            if (empty($alertas)) {
                $ip = ip_cliente();
                if (random_int(1, 100) === 1) LoginLimite::limpiar();   // ~1 % de los intentos purga registros de +24 h
                $espera = LoginLimite::espera($ip, $email);
                if ($espera > 0) {
                    Usuario::setAlerta('error', 'Demasiados intentos fallidos. Espera ' . (int)ceil($espera / 60) . ' minuto(s) antes de volver a probar.');
                } else {
                    $usuarioDB = Usuario::where('email', $usuario->email);
                    if (!$usuarioDB || !$usuarioDB->confirmado) {
                        LoginLimite::fallo($ip, $email);
                        Usuario::setAlerta('error', 'El usuario no existe o no está confirmado');
                    } else {
                        if ($usuarioDB->comprobarPassword($password)) {
                            LoginLimite::exito($ip, $email);
                            // Coste del hash actualizado con el tiempo: se rehace sin pedir nada al usuario
                            if (password_needs_rehash((string)$usuarioDB->password, PASSWORD_BCRYPT)) {
                                $usuarioDB->password = $password;
                                $usuarioDB->hashPassword();
                                $usuarioDB->guardar();
                            }
                            session_regenerate_id(true);
                            $_SESSION['id'] = $usuarioDB->id;
                            $_SESSION['nombre'] = $usuarioDB->nombre;
                            $_SESSION['email'] = $usuarioDB->email;
                            $_SESSION['rol'] = $usuarioDB->rol;
                            header('Location: /dashboard');
                            exit;
                        } else {
                            LoginLimite::fallo($ip, $email);
                            Usuario::setAlerta('error', 'Password incorrecto');
                        }
                    }
                }
            }
        }

        $alertas = Usuario::getAlertas();
        $router->render('auth/login', [
            'titulo' => 'Iniciar Sesión',
            'alertas' => $alertas,
            'usuario' => $usuario,
            'authLayout' => true
        ]);
    }

    public static function logout()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {   // cerrar sesión cambia estado: nada de enlaces GET
            header('Location: /');
            exit;
        }
        $_SESSION = [];
        session_destroy();
        header('Location: /');   // al cerrar sesión se vuelve a la página principal
        exit;
    }

    public static function crear(Router $router)
    {
        if (self::esClienteGestor()) {
            self::crearVisitante($router);
            return;
        }
        redirigir_si_no_admin();
        $alertas = [];
        $usuario = new Usuario();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario->sincronizar($_POST);
            $esVis = $usuario->rol === 'visitante';
            // Colaborador = visitante con nivel_acceso 'editar' (solo puede escribir donde el dueño lo marque)
            $usuario->nivel_acceso = $esVis && ($_POST['nivel_acceso'] ?? '') === 'editar' ? 'editar' : 'ver';
            $usuario->herramientas = $esVis ? Usuario::herramientasDePost($_POST['herr'] ?? []) : null;
            $usuario->herramientas_edicion = $esVis && $usuario->nivel_acceso === 'editar'
                ? Usuario::herramientasEdicionDePost($_POST['herr_edit'] ?? [], $usuario->herramientasPermitidas()) : null;
            $usuario->puede_archivos_ajenos = $esVis && $usuario->nivel_acceso === 'editar' && !empty($_POST['archivos_ajenos']) ? 1 : 0;
            $usuario->logo = guardar_imagen_subida('logo', 'logos');
            $alertas = $usuario->validarNuevaCuenta();
            $usuario->validarLimiteProyectos();
            $usuario->validarLimitesEquipo();
            $alertas = Usuario::getAlertas();

            if (empty($alertas)) {
                $existe = Usuario::where('email', $usuario->email);
                if ($existe) {
                    Usuario::setAlerta('error', 'El email ya está registrado');
                } else {
                    $usuario->hashPassword();
                    unset($usuario->password2);
                    $usuario->confirmado = 1; // admin crea usuarios ya confirmados
                    $usuario->crearToken();
                    $resultado = $usuario->guardar();
                    if ($resultado['resultado']) {
                        if ($usuario->rol !== 'admin') {
                            ProyectoAcceso::sincronizarUsuario((int)$resultado['id'], $_POST['proyectos'] ?? []);
                        }
                        header('Location: /usuarios?creado=1');
                        exit;
                    }
                }
            }
        }

        $alertas = Usuario::getAlertas();
        $router->render('auth/crear', [
            'titulo' => 'Crear Usuario',
            'alertas' => $alertas,
            'usuario' => $usuario,
            'proyectos' => Proyecto::all('nombre ASC'),
            'asignados' => array_map('intval', $_POST['proyectos'] ?? []),
            'herramientasMarcadas' => $usuario->herramientasPermitidas(),
            'herramientasEdicionMarcadas' => $usuario->herramientasEdicionPermitidas(),
            'archivosAjenos' => (int)$usuario->puede_archivos_ajenos,
            'tipo' => ($usuario->nivel_acceso ?? 'ver') === 'editar' ? 'colaborador' : 'visitante',
        ]);
    }

    public static function listar(Router $router)
    {
        if (self::esClienteGestor()) {
            self::listarVisitantes($router);
            return;
        }
        redirigir_si_no_admin();
        $q = trim($_GET['q'] ?? '');
        $porPagina = 25;
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $r = Usuario::buscar($q, $porPagina, ($pagina - 1) * $porPagina);
        $router->render('auth/listar', [
            'titulo' => 'Usuarios',
            'usuarios' => $r['usuarios'],
            'q' => $q,
            'total' => $r['total'],
            'pagina' => $pagina,
            'paginas' => max(1, (int)ceil($r['total'] / $porPagina)),
        ]);
    }

    public static function editar(Router $router)
    {
        if (self::esClienteGestor()) {
            self::editarVisitante($router);
            return;
        }
        redirigir_si_no_admin();
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: /usuarios');
            exit;
        }
        $usuario = Usuario::find($id);
        if (!$usuario) {
            header('Location: /usuarios');
            exit;
        }
        $alertas = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $logoActual = $usuario->logo;
            $usuario->sincronizar($_POST);
            $usuario->id = $id;
            $esVis = $usuario->rol === 'visitante';
            $usuario->nivel_acceso = $esVis && ($_POST['nivel_acceso'] ?? '') === 'editar' ? 'editar' : 'ver';
            $usuario->herramientas = $esVis ? Usuario::herramientasDePost($_POST['herr'] ?? []) : null;
            $usuario->herramientas_edicion = $esVis && $usuario->nivel_acceso === 'editar'
                ? Usuario::herramientasEdicionDePost($_POST['herr_edit'] ?? [], $usuario->herramientasPermitidas()) : null;
            $usuario->puede_archivos_ajenos = $esVis && $usuario->nivel_acceso === 'editar' && !empty($_POST['archivos_ajenos']) ? 1 : 0;
            $usuario->logo = guardar_imagen_subida('logo', 'logos') ?? $logoActual;
            $usuario->validarLimiteProyectos();
            $usuario->validarLimitesEquipo();
            if (!in_array($usuario->rol, ['admin', 'cliente', 'visitante'], true)) {
                Usuario::setAlerta('error', 'Rol no válido');
            }
            // Evita que el admin se quite a sí mismo el acceso de administrador
            if ($id == $_SESSION['id'] && $usuario->rol !== 'admin') {
                Usuario::setAlerta('error', 'No puedes quitarte tu propio rol de administrador');
            }
            if (!empty($_POST['password'])) {
                if ($motivo = Usuario::passwordDebil((string)$_POST['password'])) {
                    Usuario::setAlerta('error', $motivo);
                } elseif ($_POST['password'] !== $_POST['password2']) {
                    Usuario::setAlerta('error', 'Los passwords no coinciden');
                } else {
                    $usuario->hashPassword();
                }
            } else {
                // Mantener password actual
                $usuarioDB = Usuario::find($id);
                $usuario->password = $usuarioDB->password;
            }
            unset($usuario->password2);
            $alertas = Usuario::getAlertas();
            if (empty($alertas)) {
                $resultado = $usuario->guardar();
                if ($resultado) {
                    // Admin no necesita asignaciones; para los demás se reemplazan con las marcadas
                    ProyectoAcceso::sincronizarUsuario((int)$id, $usuario->rol === 'admin' ? [] : ($_POST['proyectos'] ?? []));
                    header('Location: /usuarios?editado=1');
                    exit;
                }
            }
        }

        $router->render('auth/editar', [
            'titulo' => 'Editar Usuario',
            'alertas' => $alertas,
            'usuario' => $usuario,
            'proyectos' => Proyecto::all('nombre ASC'),
            'asignados' => $_SERVER['REQUEST_METHOD'] === 'POST'
                ? array_map('intval', $_POST['proyectos'] ?? [])
                : ProyectoAcceso::proyectosDe((int)$id),
            'herramientasMarcadas' => $usuario->herramientasPermitidas(),
            'herramientasEdicionMarcadas' => $usuario->herramientasEdicionPermitidas(),
            'archivosAjenos' => (int)$usuario->puede_archivos_ajenos,
            'tipo' => ($usuario->nivel_acceso ?? 'ver') === 'editar' ? 'colaborador' : 'visitante',
        ]);
    }

    public static function eliminar()
    {
        if (self::esClienteGestor()) {
            self::eliminarVisitante();
            return;
        }
        redirigir_si_no_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id && $id != $_SESSION['id']) {
                $usuario = Usuario::find($id);
                if ($usuario) {
                    $usuario->eliminar();
                }
            }
            header('Location: /usuarios');
            exit;
        }
    }

    /** El cliente cambia su propia contraseña desde Ajustes (pide la actual) */
    public static function cambiarPassword()
    {
        redirigir_si_no_auth();
        $proyecto = filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $volver = $proyecto ? '/ajustes?proyecto=' . $proyecto : '/dashboard';
        $fallo = function (string $m) use ($volver) {
            header('Location: ' . $volver . (str_contains($volver, '?') ? '&' : '?') . 'pw_error=' . urlencode($m) . '#cuenta');
            exit;
        };
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['rol'] ?? '') !== 'cliente') {
            header('Location: /dashboard');
            exit;
        }
        $usuario = Usuario::find((int)$_SESSION['id']);
        $actual = (string)($_POST['password_actual'] ?? '');
        $nueva = (string)($_POST['password_nueva'] ?? '');
        if (!$usuario || !$usuario->comprobarPassword($actual)) $fallo('La contraseña actual no es correcta');
        if ($motivo = Usuario::passwordDebil($nueva)) $fallo($motivo);
        if ($nueva !== (string)($_POST['password_nueva2'] ?? '')) $fallo('Las contraseñas nuevas no coinciden');
        if ($nueva === $actual) $fallo('La nueva contraseña debe ser distinta a la actual');

        $usuario->password = $nueva;
        $usuario->hashPassword();
        unset($usuario->password2);
        if (!$usuario->guardar()) $fallo('No se pudo guardar la nueva contraseña. Intenta de nuevo.');
        session_regenerate_id(true);
        header('Location: ' . $volver . (str_contains($volver, '?') ? '&' : '?') . 'pw=ok#cuenta');
        exit;
    }

    // ==================== VISITANTES DE UN CLIENTE ====================
    // El rol cliente puede crear hasta Usuario::MAX_VISITANTES_CLIENTE usuarios con rol visitante, solo para
    // sus propios proyectos. Nunca se leen rol, límites, logo ni creado_por del formulario: se fijan aquí.

    private static function esClienteGestor(): bool
    {
        return is_auth() && !is_admin() && ($_SESSION['rol'] ?? '') === 'cliente';
    }

    /** ids de los proyectos que el cliente en sesión puede gestionar: los que creó + los que el admin le asignó */
    private static function proyectosGestionables(): array
    {
        return array_map(fn($p) => (int)$p->id, Proyecto::gestionablesDe((int)$_SESSION['id']));
    }

    /** Proyectos gestionables como objetos, para las listas de «Mi equipo» */
    private static function proyectosDeGestion(): array
    {
        return Proyecto::gestionablesDe((int)$_SESSION['id']);
    }

    /** Visitante creado por el cliente en sesión, o null (también si es de otro cliente o de otro rol) */
    private static function visitantePropio($id): ?Usuario
    {
        $u = $id ? Usuario::find((int)$id) : null;
        return ($u && $u->rol === 'visitante' && (int)$u->creado_por === (int)$_SESSION['id']) ? $u : null;
    }

    private static function listarVisitantes(Router $router)
    {
        $yo = Usuario::find((int)$_SESSION['id']);
        $visitantes = Usuario::equipoDe((int)$_SESSION['id'], 'visitante');
        $colaboradores = Usuario::equipoDe((int)$_SESSION['id'], 'colaborador');
        $router->render('auth/visitantes', [
            'titulo' => 'Mi equipo',
            'visitantes' => $visitantes,
            'colaboradores' => $colaboradores,
            'limiteVisitantes' => $yo ? $yo->limiteEquipo('visitante') : Usuario::MAX_VISITANTES_CLIENTE,
            'limiteColaboradores' => $yo ? $yo->limiteEquipo('colaborador') : Usuario::MAX_COLABORADORES_CLIENTE,
        ]);
    }

    /** Normaliza el tipo de cuenta que puede crear el cliente: 'visitante' (solo lectura) o 'colaborador' */
    private static function tipoEquipo($valor): string
    {
        return ($valor === 'colaborador') ? 'colaborador' : 'visitante';
    }

    private static function crearVisitante(Router $router)
    {
        $yo = (int)$_SESSION['id'];
        $cuenta = Usuario::find($yo);
        $tipo = self::tipoEquipo($_POST['tipo'] ?? ($_GET['tipo'] ?? 'visitante'));
        $usar = Usuario::equipoDe($yo, $tipo);
        $limite = $cuenta ? $cuenta->limiteEquipo($tipo) : ($tipo === 'colaborador' ? Usuario::MAX_COLABORADORES_CLIENTE : Usuario::MAX_VISITANTES_CLIENTE);
        $lleno = count($usar) >= $limite;
        $usuario = new Usuario(['nivel_acceso' => $tipo === 'colaborador' ? 'editar' : 'ver']);
        if ($lleno) Usuario::setAlerta('error', 'Ya tienes el máximo de ' . $limite . ' ' . ($tipo === 'colaborador' ? 'colaboradores' : 'visitantes') . '. Elimina uno para crear otro.');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$lleno) {
            $visibles = Usuario::herramientasDePost($_POST['herr'] ?? []);
            $usuario = new Usuario([
                'nombre' => trim($_POST['nombre'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '', 'password2' => $_POST['password2'] ?? '',
                'rol' => 'visitante', 'creado_por' => $yo, 'confirmado' => 1,
                'nivel_acceso' => $tipo === 'colaborador' ? 'editar' : 'ver',
                'herramientas' => $visibles,
                'herramientas_edicion' => null,
                'puede_archivos_ajenos' => $tipo === 'colaborador' && !empty($_POST['archivos_ajenos']) ? 1 : 0,
            ]);
            // La lista de edición nunca puede incluir herramientas que no ve
            $usuario->herramientas_edicion = $tipo === 'colaborador'
                ? Usuario::herramientasEdicionDePost($_POST['herr_edit'] ?? [], $usuario->herramientasPermitidas()) : null;
            $usuario->validarNuevaCuenta();
            if (!Usuario::getAlertas() && Usuario::where('email', $usuario->email)) Usuario::setAlerta('error', 'El email ya está registrado');
            if (!Usuario::getAlertas()) {
                $usuario->hashPassword();
                unset($usuario->password2);
                $usuario->crearToken();
                $r = $usuario->guardar();
                if ($r['resultado']) {
                    ProyectoAcceso::sincronizarUsuarioEn((int)$r['id'], $_POST['proyectos'] ?? [], self::proyectosGestionables());
                    header('Location: /usuarios?creado=1');
                    exit;
                }
            }
        }
        $router->render('auth/visitante-form', [
            'titulo' => $tipo === 'colaborador' ? 'Nuevo colaborador' : 'Nuevo visitante',
            'alertas' => Usuario::getAlertas(), 'usuario' => $usuario, 'lleno' => $lleno, 'tipo' => $tipo,
            'limite' => $limite, 'usados' => count($usar),
            'proyectos' => self::proyectosDeGestion(),
            'asignados' => array_map('intval', $_POST['proyectos'] ?? []),
            'herramientasMarcadas' => $usuario->herramientasPermitidas(),
            'herramientasEdicionMarcadas' => $usuario->herramientasEdicionPermitidas(),
            'archivosAjenos' => (int)$usuario->puede_archivos_ajenos,
        ]);
    }

    private static function editarVisitante(Router $router)
    {
        $usuario = self::visitantePropio(filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$usuario) {
            header('Location: /usuarios');
            exit;
        }
        $yo = (int)$_SESSION['id'];
        $cuenta = Usuario::find($yo);
        $tipo = self::tipoEquipo($_POST['tipo'] ?? ($usuario->esColaborador() ? 'colaborador' : 'visitante'));
        $limite = $cuenta ? $cuenta->limiteEquipo($tipo) : Usuario::MAX_VISITANTES_CLIENTE;
        $usar = Usuario::equipoDe($yo, $tipo);
        // Cambiar de visitante a colaborador (o al revés) consume del tope del tipo nuevo
        $cambiaTipo = ($tipo === 'colaborador') !== $usuario->esColaborador();
        $lleno = $cambiaTipo && count($usar) >= $limite;

        $gestionables = self::proyectosGestionables();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($lleno) {
                Usuario::setAlerta('error', 'Ya tienes el máximo de ' . $limite . ' ' . ($tipo === 'colaborador' ? 'colaboradores' : 'visitantes') . '.');
            } else {
                $usuario->nombre = trim($_POST['nombre'] ?? '');
                $usuario->email = trim($_POST['email'] ?? '');
                $usuario->nivel_acceso = $tipo === 'colaborador' ? 'editar' : 'ver';
                $usuario->herramientas = Usuario::herramientasDePost($_POST['herr'] ?? []);
                $usuario->herramientas_edicion = $tipo === 'colaborador'
                    ? Usuario::herramientasEdicionDePost($_POST['herr_edit'] ?? [], $usuario->herramientasPermitidas()) : null;
                $usuario->puede_archivos_ajenos = $tipo === 'colaborador' && !empty($_POST['archivos_ajenos']) ? 1 : 0;
                if (!$usuario->nombre) Usuario::setAlerta('error', 'El nombre es obligatorio');
                if (!filter_var($usuario->email, FILTER_VALIDATE_EMAIL)) Usuario::setAlerta('error', 'Email no válido');
                $otro = Usuario::where('email', $usuario->email);
                if ($otro && (int)$otro->id !== (int)$usuario->id) Usuario::setAlerta('error', 'El email ya está registrado');
                if (!empty($_POST['password'])) {
                    if ($motivo = Usuario::passwordDebil((string)$_POST['password'])) Usuario::setAlerta('error', $motivo);
                    elseif ($_POST['password'] !== ($_POST['password2'] ?? '')) Usuario::setAlerta('error', 'Los passwords no coinciden');
                    else {
                        $usuario->password = $_POST['password'];
                        $usuario->hashPassword();
                    }
                }
                unset($usuario->password2);
                if (!Usuario::getAlertas() && $usuario->guardar()) {
                    ProyectoAcceso::sincronizarUsuarioEn((int)$usuario->id, $_POST['proyectos'] ?? [], $gestionables);
                    header('Location: /usuarios?editado=1');
                    exit;
                }
            }
        }
        $router->render('auth/visitante-form', [
            'titulo' => 'Editar ' . ($usuario->esColaborador() ? 'colaborador' : 'visitante'),
            'alertas' => Usuario::getAlertas(), 'usuario' => $usuario, 'lleno' => false,
            'tipo' => $tipo,
            'limite' => $limite, 'usados' => count($usar),
            'proyectos' => self::proyectosDeGestion(),
            'asignados' => $_SERVER['REQUEST_METHOD'] === 'POST' ? array_map('intval', $_POST['proyectos'] ?? [])
                : array_values(array_intersect(ProyectoAcceso::proyectosDe((int)$usuario->id), $gestionables)),
            'herramientasMarcadas' => $usuario->herramientasPermitidas(),
            'herramientasEdicionMarcadas' => $usuario->herramientasEdicionPermitidas(),
            'archivosAjenos' => (int)$usuario->puede_archivos_ajenos,
        ]);
    }

    private static function eliminarVisitante()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($u = self::visitantePropio(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)))) {
            $u->eliminar();
        }
        header('Location: /usuarios');
        exit;
    }
}
