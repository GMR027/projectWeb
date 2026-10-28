<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\PlantillaProyecto;
use Classes\Plantillas;
use Classes\Ensamblador;
use Classes\EscanerArchivos;
use Classes\Auditoria;
use Classes\Revisiones;

/** Fase 4.3: guardar un proyecto terminado como plantilla y arrancar proyectos nuevos desde ella */
class PlantillasProyectoController
{
    private static function volver(?Proyecto $p, bool $ok, string $texto): never
    {
        $_SESSION['pp_msg'] = ['ok' => $ok, 'texto' => $texto];
        header('Location: /plantillas-proyecto' . ($p ? '?proyecto=' . (int)$p->id : ''));
        exit;
    }

    private static function borrador(array $datos, string $nombre, string $descripcion, string $aviso = ''): void
    {
        $_SESSION['pp_borrador'] = ['token' => bin2hex(random_bytes(8)), 'datos' => $datos, 'nombre' => $nombre, 'descripcion' => $descripcion, 'aviso' => $aviso];
    }

    private static function volverBorrador(?Proyecto $p = null): never
    {
        header('Location: /plantillas-proyecto?borrador=1' . ($p ? '&proyecto=' . (int)$p->id : ''));
        exit;
    }

    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        if (is_visitante()) { header('Location: /proyectos'); exit; }
        $proyecto = null;
        if (isset($_GET['proyecto'])) $proyecto = proyecto_de_peticion(true);
        $usuario = usuario_actual();
        $router->render('plantillas-proyecto/index', [
            'titulo' => 'Plantillas de proyecto', 'proyecto' => $proyecto, 'disponible' => PlantillaProyecto::disponible(),
            'plantillas' => PlantillaProyecto::disponible() ? PlantillaProyecto::visibles($usuario) : [], 'usuario' => $usuario,
            'vacio' => $proyecto ? PlantillaProyecto::estaVacio((int)$proyecto->id) : false,
            'catalogo' => Plantillas::CATALOGO,
            'borrador' => $_SESSION['pp_borrador'] ?? null,
            'mensaje' => $_SESSION['pp_msg'] ?? null,
        ]);
        unset($_SESSION['pp_msg']);
    }

    /** Crea el borrador de una plantilla desde el catálogo de tipos de obra */
    public static function desdeTipoObra()
    {
        redirigir_si_no_auth();
        if (is_visitante()) { header('Location: /proyectos'); exit; }
        $clave = (string)($_POST['clave'] ?? '');
        $datos = PlantillaProyecto::desdeTipoObra($clave);
        if (!$datos) self::volver(null, false, 'Ese tipo de obra no existe.');
        self::borrador($datos, (string)$datos['meta']['fuente']['nombre'], (string)(Plantillas::CATALOGO[$clave]['descripcion'] ?? ''));
        self::volverBorrador(isset($_POST['proyecto']) ? Proyecto::find((int)$_POST['proyecto']) : null);
    }

    /** Lee un documento (sin IA) y propone una plantilla según el tipo de obra que detecte */
    public static function procesar()
    {
        redirigir_si_no_auth();
        if (is_visitante()) { header('Location: /proyectos'); exit; }
        $f = $_FILES['documento'] ?? null;
        if (!$f || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) self::volver(null, false, 'Sube un documento para analizarlo.');
        $nombre = basename((string)($f['name'] ?? 'documento'));
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, Ensamblador::EXTENSIONES, true)) self::volver(null, false, 'Formato no admitido. Usa PDF, Word (.docx/.doc), PowerPoint (.pptx), OpenDocument (.odt) o texto.');
        if ((int)($f['size'] ?? 0) > Ensamblador::MAX_BYTES) self::volver(null, false, 'El documento supera los 50 MB.');
        if ($motivos = EscanerArchivos::revisar($f['tmp_name'], $nombre)) self::volver(null, false, implode(' ', $motivos));
        [$paginas, $aviso] = Ensamblador::extraer($f['tmp_name'], $ext);
        if (!$paginas) self::volver(null, false, $aviso ?: 'No se pudo leer el documento.');
        $texto = implode("\n", $paginas);
        if (trim($texto) === '') self::volver(null, false, 'El documento no tiene texto legible (¿es un PDF escaneado?).');
        $deteccion = Plantillas::detectarTexto($texto);
        $clave = array_key_first($deteccion['ranking']);
        $top = $clave ? $deteccion['ranking'][$clave] : null;
        if (!$top || $top['puntos'] < 2) self::volver(null, false, 'No reconocí el proceso en el documento. Elige el tipo de obra a mano abajo.');
        $datos = PlantillaProyecto::desdeTipoObra((string)$clave);
        if (!$datos) self::volver(null, false, 'No se pudo preparar la plantilla.');
        $datos['meta']['fuente'] = ['tipo' => 'documento', 'nombre' => $nombre, 'detectado' => $clave,
            'confianza' => (int)$top['porcentaje'], 'indicios' => array_slice($top['indicios'], 0, 8)];
        $nombrePlantilla = mb_substr(pathinfo($nombre, PATHINFO_FILENAME), 0, 150);
        self::borrador($datos, $nombrePlantilla, 'Detectado como ' . Plantillas::CATALOGO[$clave]['nombre'] . ' (' . (int)$top['porcentaje'] . ' %).',
            $aviso !== '' ? $aviso : (count($deteccion['ranking']) > 1 ? 'Si el tipo de obra no es el correcto, descarta el borrador y elige otro.' : ''));
        self::volverBorrador(isset($_POST['proyecto']) ? Proyecto::find((int)$_POST['proyecto']) : null);
    }

    /** Guarda o descarta el borrador (permite ajustar nombres y duraciones antes de guardar) */
    public static function guardarBorrador()
    {
        redirigir_si_no_auth();
        if (is_visitante()) { header('Location: /proyectos'); exit; }
        $b = $_SESSION['pp_borrador'] ?? null;
        $p = isset($_POST['proyecto']) ? Proyecto::find((int)$_POST['proyecto']) : null;
        if (!$b || !hash_equals((string)$b['token'], (string)($_POST['token'] ?? ''))) self::volver($p, false, 'El borrador expiró o no es válido. Vuelve a analizar el documento.');
        if (($_POST['accion'] ?? 'guardar') === 'descartar') {
            unset($_SESSION['pp_borrador']);
            self::volver($p, true, 'Borrador descartado.');
        }
        $datos = $b['datos'];
        $quitar = (array)($_POST['quitar'] ?? []);
        foreach ($datos['actividades'] as &$a) {
            $ref = (int)$a['ref'];
            if (!empty($quitar[$ref])) { $a['_quitar'] = true; continue; }
            if (isset($_POST['act'][$ref]['nombre'])) $a['nombre'] = mb_substr(trim(strip_tags((string)$_POST['act'][$ref]['nombre'])), 0, 200);
            if (isset($_POST['act'][$ref]['duracion_dias'])) $a['duracion_dias'] = max(1, min(365, (int)$_POST['act'][$ref]['duracion_dias']));
        }
        unset($a);
        $datos['actividades'] = array_values(array_filter($datos['actividades'], fn($a) => empty($a['_quitar'])));
        if (!$datos['actividades']) self::volver($p, false, 'La plantilla se quedaría sin actividades.');
        $refs = array_column($datos['actividades'], 'ref');
        $datos['dependencias'] = array_values(array_filter((array)($datos['dependencias'] ?? []), fn($x) => in_array((int)$x['pred'], $refs, true) && in_array((int)$x['suc'], $refs, true)));
        $usadas = array_unique(array_column($datos['actividades'], 'partida'));
        $datos['partidas'] = array_values(array_filter($datos['partidas'], fn($x) => in_array((int)$x['ref'], $usadas, true)));
        $datos['duracion_dias'] = array_sum(array_column($datos['actividades'], 'duracion_dias'));
        [$ok, $r] = PlantillaProyecto::guardarDatos($datos, usuario_actual(), (string)($_POST['nombre'] ?? $b['nombre']), (string)($_POST['descripcion'] ?? $b['descripcion']), !empty($_POST['compartida']));
        if (!$ok) { $_SESSION['pp_msg'] = ['ok' => false, 'texto' => (string)$r]; self::volverBorrador($p); }
        unset($_SESSION['pp_borrador']);
        self::volver($p, true, 'Plantilla «' . (string)($_POST['nombre'] ?? $b['nombre']) . '» guardada. Ya puedes elegirla al crear un proyecto.');
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $r] = PlantillaProyecto::guardar($p, usuario_actual(), (string)($_POST['nombre'] ?? ''), (string)($_POST['descripcion'] ?? ''), !empty($_POST['compartida']));
        self::volver($p, $ok, $ok ? 'Plantilla guardada. Ya puedes elegirla al crear un proyecto nuevo.' : (string)$r);
    }

    public static function aplicar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = PlantillaProyecto::aplicar((int)($_POST['id'] ?? 0), $p, usuario_actual());
        if ($ok) { header('Location: ' . url_inicio_proyecto((int)$p->id)); exit; }
        self::volver($p, false, $msg);
    }

    public static function eliminar()
    {
        redirigir_si_no_auth();
        $ok = PlantillaProyecto::eliminar((int)($_POST['id'] ?? 0), usuario_actual());
        $p = isset($_POST['proyecto']) ? Proyecto::find((int)$_POST['proyecto']) : null;
        self::volver($p && puede_gestionar_proyecto($p) ? $p : null, $ok, $ok ? 'Plantilla eliminada.' : 'No se pudo eliminar la plantilla.');
    }
}
