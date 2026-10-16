<?php

namespace Controllers;

use MVC\Router;
use Model\View360Grafico;
use Model\View360Nota;
use Model\Grafico;
use Classes\Imagenes;
use Classes\PdfRaster;
use Classes\Almacenamiento;

/** View360: gráficos interactivos con enlaces a vistas 360 del proyecto */
class View360Controller
{
    private static function graficoDe($proyecto): ?View360Grafico
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $g = View360Grafico::find(filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT));
        return $g && (int)$g->proyecto_id === (int)$proyecto->id ? $g : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('view360/index', [
            'titulo' => 'View360 - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'graficos' => View360Grafico::deProyecto((int)$proyecto->id), 'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    /** Vista de solo lectura del gráfico (sin herramientas de edición), con sus hipervínculos activos */
    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $g = self::graficoDe($proyecto);
        $lienzo = $g && $g->grafico_id ? Grafico::find((int)$g->grafico_id) : null;
        if (!$g || !$lienzo || (int)$lienzo->proyecto_id !== (int)$proyecto->id) {
            header('Location: /view360?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('view360/ver', [
            'titulo' => $g->nombre . ' - View360', 'proyecto' => $proyecto, 'grafico' => $g, 'lienzo' => $lienzo, 'laminas' => $lienzo->laminas(),
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'notas' => array_map(fn($n) => $n->aArray(), View360Nota::deGrafico((int)$g->id)),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        self::formulario($router, $proyecto, new View360Grafico());
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $g = self::graficoDe($proyecto);
        if (!$g) {
            header('Location: /view360?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $proyecto, $g);
    }

    private static function formulario(Router $router, $proyecto, View360Grafico $g)
    {
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $esNuevo = !$g->id;
            $g->fecha = trim($_POST['fecha'] ?? '');
            $g->nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 150);
            $g->version = mb_substr(trim($_POST['version'] ?? ''), 0, 30);
            $g->proyecto_id = $proyecto->id;
            $alertas = $g->validar();
            $fondo = null;   // [ruta del archivo de imagen, ruta temporal a borrar|null]
            if ($esNuevo && !$alertas) {
                [$fondo, $alertas] = self::prepararFondo($proyecto, $_FILES['fondo'] ?? null);
            }
            if (!$alertas) {
                if ($esNuevo) {
                    $lienzo = self::crearLienzo($proyecto, $g, $fondo[0]);
                    if ($fondo[1]) @unlink($fondo[1]);
                    if (!$lienzo) {
                        $alertas['error'][] = 'No se pudo procesar el archivo de fondo';
                    } else {
                        $g->grafico_id = $lienzo->id;
                        $g->guardar();
                        header('Location: /graficos/abrir?proyecto=' . $proyecto->id . '&id=' . $lienzo->id);
                        exit;
                    }
                } else {
                    $g->guardar();
                    if ($g->grafico_id && ($lienzo = Grafico::find((int)$g->grafico_id))) {
                        $lienzo->nombre = $g->nombre;
                        $lienzo->guardar();
                    }
                    header('Location: /view360?proyecto=' . $proyecto->id);
                    exit;
                }
            } elseif ($fondo && $fondo[1]) {
                @unlink($fondo[1]);
            }
        }
        $router->render('view360/formulario', [
            'titulo' => $g->id ? 'Editar gráfico interactivo' : 'Crear gráfico interactivo', 'proyecto' => $proyecto, 'grafico' => $g, 'alertas' => $alertas,
        ]);
    }

    /** Valida el archivo de fondo. Devuelve [[ruta de la imagen, temporal|null], alertas]; un PDF se convierte a PNG (página 1) */
    private static function prepararFondo($proyecto, ?array $f): array
    {
        $ok = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']) && $f['error'] === UPLOAD_ERR_OK;
        if (!$ok) return [null, ['error' => ['Elige el archivo de fondo (PDF o imagen)']]];
        if ($f['size'] > 30 * 1024 * 1024) return [null, ['error' => ['El archivo de fondo no puede pesar más de 30 MB']]];
        if (!Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) return [null, ['error' => [Almacenamiento::mensajeLleno((int)$proyecto->id)]]];
        $mime = mime_content_type($f['tmp_name']);
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) return [[$f['tmp_name'], null], []];
        if ($mime === 'application/pdf' && PdfRaster::esPdf($f['tmp_name'])) {
            if (!PdfRaster::disponible()) return [null, ['error' => ['Este servidor no puede convertir PDF a imagen. Sube el plano como imagen (PNG o JPG)']]];
            $png = PdfRaster::primeraPagina($f['tmp_name'], 4000);
            return $png ? [[$png, $png], []] : [null, ['error' => ['No se pudo leer el PDF. Revisa que no esté dañado o protegido con contraseña']]];
        }
        return [null, ['error' => ['El fondo debe ser un PDF o una imagen (jpg, png, webp o gif)']]];
    }

    /** Crea el gráfico del Editor (lienzo del tamaño del fondo, con éste de fondo) y su primera lámina */
    private static function crearLienzo($proyecto, View360Grafico $g, string $origen): ?Grafico
    {
        $archivo = Imagenes::guardarGrande($origen, 4000);
        if (!$archivo) return null;
        $dim = @getimagesize(Imagenes::CARPETA_VPLAN . '/' . $archivo);
        if (!$dim) {
            Imagenes::eliminarVplan($archivo);
            return null;
        }
        [$w, $h] = $dim;
        $k = max(1, Grafico::LADO_MIN / max(1, min($w, $h)), 1) ;
        $lienzo = new Grafico([
            'proyecto_id' => $proyecto->id, 'nombre' => $g->nombre, 'ancho' => min(Grafico::LADO_MAX, (int)round($w * $k)), 'alto' => min(Grafico::LADO_MAX, (int)round($h * $k)),
            'fondo' => 'imagen', 'fondo_color' => '#ffffff', 'fondo_imagen' => $archivo,
            'fondo_config' => json_encode(Grafico::limpiarConfigFondo(['modo' => 'estirar'])),
        ]);
        if ($lienzo->validar()) {
            Imagenes::eliminarVplan($archivo);
            return null;
        }
        $lienzo->id = $lienzo->guardar()['id'];
        $lienzo->crearLaminaInicial();
        return $lienzo;
    }

    /** API: sube la foto 360 (equirectangular) de un hipervínculo «Vista 360» del editor; devuelve su URL */
    public static function panorama()
    {
        header('Content-Type: application/json');
        $err = function (string $m, int $c = 422) {
            http_response_code($c);
            echo json_encode(['success' => false, 'error' => $m]);
            exit;
        };
        $p = is_auth() ? \Model\Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p || !puede_gestionar_proyecto($p)) $err('No autorizado', 403);
        $f = $_FILES['imagen'] ?? null;
        if (!$f || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name']) || $f['error'] !== UPLOAD_ERR_OK) $err('No se recibió la imagen');
        if ($f['size'] > 40 * 1024 * 1024) $err('La foto 360 no puede pesar más de 40 MB');
        if (!in_array(mime_content_type($f['tmp_name']), ['image/jpeg', 'image/png', 'image/webp'], true)) $err('La foto 360 debe ser JPG, PNG o WebP');
        $dim = @getimagesize($f['tmp_name']);
        if (!$dim || $dim[0] < 1024) $err('La foto 360 es demasiado pequeña (mínimo 1024 px de ancho)');
        $razon = $dim[0] / max(1, $dim[1]);
        if ($razon < 1.9 || $razon > 2.1) $err('La imagen no parece una foto 360: debe ser equirectangular, con proporción 2:1 (por ejemplo 4096 × 2048)');
        if (!Almacenamiento::cabe((int)$p->id, (int)$f['size'])) $err(Almacenamiento::mensajeLleno((int)$p->id));
        @ini_set('memory_limit', '1024M');
        @set_time_limit(120);
        $nombre = Imagenes::guardarGrande($f['tmp_name'], 8192);
        if (!$nombre) $err('No se pudo procesar la imagen');
        $d = @getimagesize(Imagenes::CARPETA_VPLAN . '/' . $nombre);
        echo json_encode(['success' => true, 'url' => '/uploads/vplan/' . $nombre, 'ancho' => $d[0] ?? 0, 'alto' => $d[1] ?? 0]);
        exit;
    }

    // ---------- notas (JSON; la fecha la pone el servidor al crear) ----------

    private static function notaResponder(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        echo json_encode($datos);
        exit;
    }

    /** Gráfico de la petición, solo si el usuario puede gestionar su proyecto */
    private static function graficoAutorizado(): View360Grafico
    {
        header('Content-Type: application/json');
        $g = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? View360Grafico::find(filter_var($_POST['grafico'] ?? null, FILTER_VALIDATE_INT)) : null;
        $p = $g ? \Model\Proyecto::find($g->proyecto_id) : null;
        if (!$p || !puede_gestionar_proyecto($p)) self::notaResponder(['success' => false, 'error' => 'No autorizado'], 403);
        return $g;
    }

    /** Crea (sin id) o edita (con id) una nota */
    public static function notaGuardar()
    {
        $g = self::graficoAutorizado();
        $texto = trim((string)($_POST['descripcion'] ?? ''));
        if ($texto === '') self::notaResponder(['success' => false, 'error' => 'La descripción es obligatoria'], 422);
        if (mb_strlen($texto) > View360Nota::MAX_LARGO) self::notaResponder(['success' => false, 'error' => 'Máximo ' . View360Nota::MAX_LARGO . ' caracteres'], 422);
        if (!empty($_POST['id'])) {
            $n = View360Nota::find(filter_var($_POST['id'], FILTER_VALIDATE_INT));
            if (!$n || (int)$n->view360_id !== (int)$g->id) self::notaResponder(['success' => false, 'error' => 'Nota no encontrada'], 404);
            $n->descripcion = $texto;   // la fecha original se conserva
            $n->guardar();
        } else {
            if (View360Nota::totalDeGrafico((int)$g->id) >= View360Nota::MAX_POR_GRAFICO) self::notaResponder(['success' => false, 'error' => 'Máximo ' . View360Nota::MAX_POR_GRAFICO . ' notas por gráfico'], 422);
            $n = new View360Nota(['view360_id' => $g->id, 'descripcion' => $texto]);
            $n->id = $n->guardar()['id'];
        }
        self::notaResponder(['success' => true, 'nota' => $n->aArray()]);
    }

    public static function notaEliminar()
    {
        $g = self::graficoAutorizado();
        $n = View360Nota::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$n || (int)$n->view360_id !== (int)$g->id) self::notaResponder(['success' => false, 'error' => 'Nota no encontrada'], 404);
        $n->eliminar();
        self::notaResponder(['success' => true]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($g = self::graficoDe($proyecto)) {
            if ($g->grafico_id && ($lienzo = Grafico::find((int)$g->grafico_id)) && (int)$lienzo->proyecto_id === (int)$proyecto->id) {
                $lienzo->eliminarArchivos();
                $lienzo->eliminar();
            }
            $g->eliminar();
        }
        header('Location: /view360?proyecto=' . $proyecto->id);
        exit;
    }
}
