<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\RedactorDocumento;
use Model\Grafico;
use Classes\Redactor;
use Classes\Imagenes;
use Classes\Almacenamiento;
use Classes\EnlacesLamina;
use Classes\RedactorFirmas;
use Classes\RedactorPlantillas;
use Model\Usuario;

/** Redactor: documentos de texto con formato (lista por departamento, alta, edición, vista, descarga DOCX/ODT/PDF) */
class RedactorController
{
    private const MAX_CONTENIDO = 5_000_000;

    private static function documentoDe(Proyecto $proyecto, $id): ?RedactorDocumento
    {
        $d = RedactorDocumento::find(filter_var($id, FILTER_VALIDATE_INT));
        return $d && (int)$d->proyecto_id === (int)$proyecto->id ? $d : null;
    }

    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d);
        exit;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $porDepto = [];
        foreach (RedactorDocumento::deProyecto((int)$proyecto->id) as $d) $porDepto[$d->departamento !== '' ? $d->departamento : 'Sin departamento'][] = $d;
        $router->render('redactor/index', [
            'titulo' => 'Redactor - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'porDepto' => $porDepto,
            'puedeEditar' => puede_gestionar_proyecto($proyecto), 'puedeDescargar' => puede_descargar(),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $d = new RedactorDocumento(['proyecto_id' => $proyecto->id]);
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $d = new RedactorDocumento([
                'proyecto_id' => $proyecto->id, 'nombre' => $_POST['nombre'] ?? '', 'fecha' => $_POST['fecha'] ?? '', 'departamento' => $_POST['departamento'] ?? '',
                'idioma' => $_POST['idioma'] ?? 'es', 'papel' => $_POST['papel'] ?? 'carta', 'orientacion' => $_POST['orientacion'] ?? 'vertical',
                'creado_por' => $_SESSION['id'] ?? null,
            ]);
            $alertas = $d->validar();
            if (!$alertas) {
                if ($pl = RedactorPlantillas::aplicar((string)($_POST['plantilla'] ?? ''), (int)$proyecto->id, (string)$proyecto->nombre, $d, (string)($_SESSION['nombre'] ?? ''))) {
                    $d->contenido = $pl['contenido'];
                    if (isset($pl['papel'])) foreach (['papel', 'orientacion', 'margen_sup', 'margen_inf', 'margen_izq', 'margen_der'] as $c) $d->$c = $pl[$c];
                }
                $d->id = $d->guardar()['id'];
                header('Location: /redactor/abrir?proyecto=' . $proyecto->id . '&id=' . $d->id);
                exit;
            }
        }
        $router->render('redactor/crear', ['titulo' => 'Nuevo documento', 'proyecto' => $proyecto, 'documento' => $d, 'alertas' => $alertas, 'departamentos' => RedactorDocumento::departamentos((int)$proyecto->id),
            'incluidas' => RedactorPlantillas::incluidas(), 'propias' => RedactorPlantillas::deProyecto((int)$proyecto->id)]);
    }

    public static function eliminarPlantilla()
    {
        $proyecto = proyecto_de_peticion(true);
        RedactorPlantillas::eliminar((int)$proyecto->id, (int)($_POST['eliminar_plantilla'] ?? 0));
        header('Location: /redactor/crear?proyecto=' . $proyecto->id);
        exit;
    }

    public static function abrir(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $d = self::documentoDe($proyecto, $_GET['id'] ?? null);
        if (!$d) {
            header('Location: /redactor?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('redactor/abrir', [
            'titulo' => 'Redactor - ' . $d->nombre, 'proyecto' => $proyecto, 'documento' => self::conSheetflow($d),
            'departamentos' => RedactorDocumento::departamentos((int)$proyecto->id),
            'hojasSheetflow' => \Model\SheetflowHoja::resumen((int)$proyecto->id),
            'config' => [
                'id' => (int)$d->id, 'proyecto' => (int)$proyecto->id, 'version' => (string)$d->actualizado,
                'palabras' => RedactorDocumento::palabras((int)$proyecto->id),
                'comentarios' => json_decode((string)$d->comentarios, true) ?: [], 'rastrear' => (bool)$d->rastrear,
                'usuario' => (string)($_SESSION['nombre'] ?? ''), 'firmas' => RedactorFirmas::deUsuario((int)($_SESSION['id'] ?? 0)), 'sellos' => RedactorFirmas::sellos($d),
                'maxFirmas' => RedactorFirmas::MAX_POR_USUARIO,
                'destinos' => EnlacesLamina::destinos((int)$proyecto->id),
                'imagenes' => self::imagenesInsertables((int)$proyecto->id),
                'simbolos' => \Model\Simbolo::paraEditor((int)$proyecto->id),
                'fuentes' => Redactor::FUENTES,
                'papeles' => array_map(fn($p) => [$p[1], $p[2]], RedactorDocumento::PAPELES),
                'api' => ['guardar' => '/api/redactor/guardar', 'imagen' => '/api/redactor/imagen', 'palabra' => '/api/redactor/palabra', 'firma' => '/api/redactor/firma', 'firmaBorrar' => '/api/redactor/firma/borrar', 'sellar' => '/api/redactor/sellar', 'plantilla' => '/api/redactor/plantilla', 'versiones' => '/api/redactor/versiones', 'version' => '/api/redactor/version', 'programa' => '/api/redactor/programa', 'exportarSeleccion' => '/api/redactor/exportar-seleccion'],
                'descargar' => '/redactor/descargar?proyecto=' . $proyecto->id . '&id=' . $d->id,
                'dic' => '/vendor/redactor/',
            ],
        ]);
    }

    /** Gráficos del Editor y láminas del vPlan con imagen, para insertarlos en el documento */
    private static function imagenesInsertables(int $proyectoId): array
    {
        $out = Grafico::paraInsertar($proyectoId);
        try {
            foreach (\Model\VplanLamina::deProyecto($proyectoId) as $l) {
                if ($l->marcas_png) $out[] = ['id' => 0, 'nombre' => 'vPlan · ' . $l->titulo, 'src' => '/uploads/vplan/' . $l->marcas_png];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    /** Copia del documento con las tablas vinculadas a SheetFlow al día (no se guarda hasta que se edite) */
    private static function conSheetflow(RedactorDocumento $d): RedactorDocumento
    {
        $c = clone $d;
        $c->contenido = \Classes\Sheetflow::refrescarHtml((string)$d->contenido, (int)$d->proyecto_id);
        return $c;
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $d = self::documentoDe($proyecto, $_GET['id'] ?? null);
        if (!$d) {
            header('Location: /redactor?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('redactor/ver', [
            'titulo' => 'Redactor - ' . $d->nombre, 'proyecto' => $proyecto, 'documento' => self::conSheetflow($d),
            'puedeEditar' => puede_gestionar_proyecto($proyecto), 'puedeDescargar' => puede_descargar(),
            'comentarios' => json_decode((string)$d->comentarios, true) ?: [], 'sellos' => RedactorFirmas::sellos($d),
        ]);
    }

    /** Descarga con la nomenclatura Y_m_d_Nombre.ext (docx, odt o pdf; con inline=1 el PDF se abre en el navegador para imprimirlo) */
    public static function descargar()
    {
        $proyecto = proyecto_de_peticion();
        $d = self::documentoDe($proyecto, $_GET['id'] ?? null);
        if (!$d || !puede_descargar()) {
            header('Location: /redactor?proyecto=' . $proyecto->id . '&error=' . urlencode('No se puede descargar el documento'));
            exit;
        }
        $formato = in_array($_GET['formato'] ?? 'docx', ['docx', 'odt', 'pdf'], true) ? $_GET['formato'] : 'docx';
        $base = date('Y_m_d') . '_' . slug_archivo($d->nombre);
        @set_time_limit(120);
        try {
            $opc = ['cambios' => !empty($_GET['cambios']), 'comentarios' => !empty($_GET['comentarios']), 'firmas' => !isset($_GET['firmas']) || !empty($_GET['firmas'])];
            $datos = $formato === 'pdf' ? Redactor::pdf($d, $opc) : Redactor::exportar($d, $formato, $opc);
        } catch (\Throwable $e) {
            error_log('Redactor exportar: ' . $e->getMessage());
            header('Location: /redactor/ver?proyecto=' . $proyecto->id . '&id=' . $d->id . '&error=' . urlencode('No se pudo generar el archivo'));
            exit;
        }
        $tipos = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'odt' => 'application/vnd.oasis.opendocument.text', 'pdf' => 'application/pdf'];
        header_remove('Content-Type');
        header('Content-Type: ' . $tipos[$formato]);
        header('Content-Disposition: ' . ($formato === 'pdf' && !empty($_GET['inline']) ? 'inline' : 'attachment') . '; filename="' . $base . '.' . $formato . '"');
        header('Content-Length: ' . strlen($datos));
        echo $datos;
        exit;
    }

    public static function duplicar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($d = self::documentoDe($proyecto, $_POST['id'] ?? null)) {
            if (!Almacenamiento::cabe((int)$proyecto->id, $d->bytes())) {
                header('Location: /redactor?proyecto=' . $proyecto->id . '&error=' . urlencode(Almacenamiento::mensajeLleno((int)$proyecto->id)));
                exit;
            }
            $n = new RedactorDocumento(get_object_vars($d));
            $n->id = null;
            $n->nombre = mb_substr($d->nombre, 0, 140) . ' (copia)';
            $n->fecha = date('Y-m-d');
            $n->creado_por = $_SESSION['id'] ?? null;
            $n->guardar();
        }
        header('Location: /redactor?proyecto=' . $proyecto->id . '&ok=1');
        exit;
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($d = self::documentoDe($proyecto, $_POST['id'] ?? null)) {
            $d->eliminar();
            $d->eliminarArchivos();   // tras borrar la fila, para que no cuente como «otro documento»
        }
        header('Location: /redactor?proyecto=' . $proyecto->id . '&ok=1');
        exit;
    }

    /** Documento de una petición de la API (campo «id») si el usuario puede gestionarlo */
    private static function documentoApi(): array
    {
        $d = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? RedactorDocumento::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        $p = $d ? Proyecto::find($d->proyecto_id) : null;
        if (!$p || !puede_gestionar_proyecto($p)) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        return [$d, $p];
    }

    public static function guardar()
    {
        [$d, $p] = self::documentoApi();
        $html = (string)($_POST['contenido'] ?? '');
        if (strlen($html) > self::MAX_CONTENIDO) self::json(['success' => false, 'error' => 'El documento es demasiado grande'], 422);
        $ver = (string)($_POST['version'] ?? '');
        if ($ver !== '' && empty($_POST['forzar']) && $ver !== (string)$d->actualizado) {
            self::json(['success' => false, 'conflicto' => true, 'error' => 'Este documento se guardó desde otra sesión después de que lo abrieras.'], 409);
        }
        $nuevo = new RedactorDocumento([
            'id' => $d->id, 'proyecto_id' => $d->proyecto_id, 'creado_por' => $d->creado_por,
            'nombre' => $_POST['nombre'] ?? $d->nombre, 'fecha' => $_POST['fecha'] ?? $d->fecha, 'departamento' => $_POST['departamento'] ?? $d->departamento,
            'idioma' => $_POST['idioma'] ?? $d->idioma, 'papel' => $_POST['papel'] ?? $d->papel, 'orientacion' => $_POST['orientacion'] ?? $d->orientacion,
            'margen_sup' => $_POST['margen_sup'] ?? 2.54, 'margen_inf' => $_POST['margen_inf'] ?? 2.54, 'margen_izq' => $_POST['margen_izq'] ?? 2.54, 'margen_der' => $_POST['margen_der'] ?? 2.54,
            'numerar' => !empty($_POST['numerar']) ? 1 : 0, 'encabezado' => !empty($_POST['encabezado']) ? 1 : 0, 'rastrear' => !empty($_POST['rastrear']) ? 1 : 0,
            'marca_agua' => $_POST['marca_agua'] ?? $d->marca_agua, 'encabezado_texto' => $_POST['encabezado_texto'] ?? $d->encabezado_texto, 'pie_texto' => $_POST['pie_texto'] ?? $d->pie_texto,
            'contenido' => Redactor::limpiar($html), 'comentarios' => Redactor::limpiarComentarios((string)($_POST['comentarios'] ?? '[]')),
        ]);
        if ($alertas = $nuevo->validar()) self::json(['success' => false, 'error' => $alertas['error'][0]], 422);
        $extra = max(0, strlen((string)$nuevo->contenido) - strlen((string)$d->contenido));
        if ($extra && !Almacenamiento::cabe((int)$p->id, $extra)) self::json(['success' => false, 'error' => Almacenamiento::mensajeLleno((int)$p->id)], 422);
        $viejas = RedactorDocumento::imagenesDe($d->contenido);
        // Historial: copia del texto anterior si pasaron 10 minutos desde la última; «version_etiqueta» guarda además el texto nuevo
        if (trim((string)$d->contenido) !== '' && $d->contenido !== $nuevo->contenido) {
            $ult = RedactorDocumento::ultimaVersion((int)$d->id);
            if ($ult === null || time() - strtotime($ult) > 600) RedactorDocumento::guardarVersion((int)$d->id, (string)$d->contenido, (string)($_SESSION['nombre'] ?? ''), 'Automática');
        }
        $nuevo->guardar();
        if (trim((string)($_POST['version_etiqueta'] ?? '')) !== '') RedactorDocumento::guardarVersion((int)$d->id, (string)$nuevo->contenido, (string)($_SESSION['nombre'] ?? ''), trim((string)$_POST['version_etiqueta']));
        // Imágenes que el documento ya no usa ni usa otro: se borran del disco
        $sobran = array_diff($viejas, RedactorDocumento::imagenesDe($nuevo->contenido));
        if ($sobran) {
            $d->contenido = implode(' ', array_map(fn($n) => "/uploads/vplan/$n", $sobran));
            $d->eliminarArchivos();
        }
        $fresco = RedactorDocumento::find($d->id);
        self::json(['success' => true, 'version' => $fresco ? (string)$fresco->actualizado : '', 'contenido' => $nuevo->contenido, 'sellos' => RedactorFirmas::sellos($nuevo)]);
    }

    public static function imagen()
    {
        [, $p] = self::documentoApi();
        $f = $_FILES['imagen'] ?? null;
        if (!$f || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name']) || $f['size'] > 20 * 1024 * 1024) self::json(['success' => false, 'error' => 'Elige una imagen de hasta 20 MB'], 422);
        if (!Almacenamiento::cabe((int)$p->id, (int)$f['size'])) self::json(['success' => false, 'error' => Almacenamiento::mensajeLleno((int)$p->id)], 422);
        $n = Imagenes::guardarGrande($f['tmp_name'], 2400);
        if (!$n) self::json(['success' => false, 'error' => 'La imagen no es válida (jpg, png, webp o gif)'], 422);
        self::json(['success' => true, 'src' => '/uploads/vplan/' . $n]);
    }

    /** Guarda una firma manuscrita (PNG dibujado en el editor) en la lista personal del usuario */
    public static function firma()
    {
        self::documentoApi();
        $r = RedactorFirmas::guardar((int)($_SESSION['id'] ?? 0), (string)($_POST['nombre'] ?? ''), (string)($_POST['png'] ?? ''));
        if (is_string($r)) self::json(['success' => false, 'error' => $r], 422);
        self::json(['success' => true, 'firma' => $r]);
    }

    public static function firmaBorrar()
    {
        self::documentoApi();
        RedactorFirmas::eliminar((int)($_SESSION['id'] ?? 0), (int)($_POST['firma'] ?? 0));
        self::json(['success' => true]);
    }

    /** Firma electrónica interna: pide la contraseña, firma lo que está guardado y devuelve los sellos */
    public static function sellar()
    {
        [$d] = self::documentoApi();
        $u = Usuario::find((int)($_SESSION['id'] ?? 0));
        if (!$u) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        if (Redactor::cambiosPendientes((string)$d->contenido)) self::json(['success' => false, 'error' => 'Hay cambios sin aceptar o rechazar. Resuélvelos antes de firmar.'], 422);
        if ($m = RedactorFirmas::sellar($d, $u, (string)($_POST['cargo'] ?? ''), (string)($_POST['password'] ?? ''))) self::json(['success' => false, 'error' => $m], 422);
        self::json(['success' => true, 'sellos' => RedactorFirmas::sellos($d)]);
    }

    /** Guarda el documento guardado como plantilla del proyecto */
    public static function plantilla()
    {
        [$d, $p] = self::documentoApi();
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        if ($nombre === '') self::json(['success' => false, 'error' => 'Escribe un nombre para la plantilla'], 422);
        if (!RedactorPlantillas::guardar((int)$p->id, $nombre, (string)($_POST['descripcion'] ?? ''), $d)) self::json(['success' => false, 'error' => 'No se pudo guardar la plantilla'], 500);
        self::json(['success' => true]);
    }

    /** Lista de versiones guardadas del documento */
    public static function versiones()
    {
        [$d] = self::documentoApi();
        self::json(['success' => true, 'versiones' => RedactorDocumento::versiones((int)$d->id)]);
    }

    /** Contenido de una versión (el cliente lo compara o lo restaura; se guarda recién al guardar el documento) */
    public static function version()
    {
        [$d] = self::documentoApi();
        $v = RedactorDocumento::version((int)$d->id, (int)($_POST['version_id'] ?? 0));
        if (!$v) self::json(['success' => false, 'error' => 'Versión no encontrada'], 404);
        $v['contenido'] = Redactor::limpiar((string)$v['contenido']);
        self::json(['success' => true, 'version' => $v]);
    }

    /** Partidas y actividades del programa del proyecto, para insertarlas como tabla */
    public static function programa()
    {
        [, $p] = self::documentoApi();
        $tipo = (string)($_POST['tabla'] ?? '');
        if ($tipo === 'punchvista') {   // vista configurable del Punch list (columnas, fotos y filtros)
            $cfg = json_decode((string)($_POST['cfg'] ?? ''), true);
            $cfg = \Classes\PunchVista::normalizar(is_array($cfg) ? $cfg : []);
            self::json(['success' => true, 'html' => \Classes\PunchVista::htmlRedactor((int)$p->id, $cfg), 'cfg' => $cfg, 'total' => count(\Classes\PunchVista::items((int)$p->id, $cfg))]);
        }
        if ($tipo !== '') {
            $t = RedactorDocumento::tablaExtra((int)$p->id, $tipo);
            if (!$t) self::json(['success' => false, 'error' => 'Tabla no válida'], 422);
            self::json(['success' => true] + $t);
        }
        self::json(['success' => true, 'filas' => RedactorDocumento::programa((int)$p->id)]);
    }

    /** Descarga solo el fragmento enviado (la selección) con los ajustes de página del documento */
    public static function exportarSeleccion()
    {
        [$d] = self::documentoApi();
        if (!puede_descargar()) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        $html = Redactor::limpiar((string)($_POST['html'] ?? ''));
        if (trim(strip_tags($html)) === '' && !str_contains($html, '<img')) self::json(['success' => false, 'error' => 'La selección está vacía'], 422);
        $formato = in_array($_POST['formato'] ?? 'docx', ['docx', 'odt', 'pdf'], true) ? $_POST['formato'] : 'docx';
        $copia = new RedactorDocumento(get_object_vars($d));
        $copia->contenido = $html;
        @set_time_limit(120);
        try {
            $opc = ['cambios' => false, 'comentarios' => false, 'firmas' => false];
            $datos = $formato === 'pdf' ? Redactor::pdf($copia, $opc) : Redactor::exportar($copia, $formato, $opc);
        } catch (\Throwable $e) {
            error_log('Redactor exportar selección: ' . $e->getMessage());
            self::json(['success' => false, 'error' => 'No se pudo generar el archivo'], 500);
        }
        $tipos = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'odt' => 'application/vnd.oasis.opendocument.text', 'pdf' => 'application/pdf'];
        header_remove('Content-Type');
        header('Content-Type: ' . $tipos[$formato]);
        header('X-Nombre-Archivo: ' . date('Y_m_d') . '_' . slug_archivo($d->nombre) . '_seleccion.' . $formato);
        header('Content-Length: ' . strlen($datos));
        echo $datos;
        exit;
    }

    public static function palabra()
    {
        [, $p] = self::documentoApi();
        if (!RedactorDocumento::agregarPalabra((int)$p->id, (string)($_POST['palabra'] ?? ''))) self::json(['success' => false, 'error' => 'Palabra no válida'], 422);
        self::json(['success' => true]);
    }
}
