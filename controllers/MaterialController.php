<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Material;
use Classes\Imagenes;

/** Catálogo de materiales por proyecto (alimenta submittals y MSR) */
class MaterialController
{
    private const MAX_PDF = 20 * 1024 * 1024;

    /** Proyecto de la petición con permiso de gestión */
    private static function proyecto(string $origen = 'GET'): Proyecto
    {
        redirigir_si_no_auth();
        $fuente = $origen === 'POST' ? $_POST : $_GET;
        $proyecto = Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT));
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_gestionar_proyecto($proyecto);
        return $proyecto;
    }

    private static function fichaDir(int $proyectoId): string
    {
        return __DIR__ . '/../storage/fichas/' . $proyectoId;
    }

    public static function index(Router $router)
    {
        $proyecto = self::proyecto();
        $q = trim($_GET['q'] ?? '');
        $fEsp = trim($_GET['especialidad'] ?? '');
        $fEntrega = in_array($_GET['entrega'] ?? '', ['entregado', 'pendiente'], true) ? $_GET['entrega'] : '';
        $fCompra = isset(Material::COMPRA[$_GET['compra'] ?? '']) ? $_GET['compra'] : '';
        $materiales = array_values(array_filter(
            Material::deProyecto((int)$proyecto->id),
            fn($m) => ($q === '' || stripos($m->nombre . ' ' . $m->referencia . ' ' . $m->especialidad, $q) !== false)
                && ($fEsp === '' || ($fEsp === '__sin' ? $m->especialidad === '' : $m->especialidad === $fEsp))
                && ($fEntrega === '' || ($fEntrega === 'entregado') === $m->entregado())
                && ($fCompra === '' || $m->compra === $fCompra)
        ));
        $router->render('materiales/index', [
            'titulo' => 'Materiales - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'materiales' => $materiales,
            'q' => $q,
            'fEsp' => $fEsp, 'fEntrega' => $fEntrega, 'fCompra' => $fCompra,
            'especialidades' => \Model\Especialidad::nombres((int)$proyecto->id),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = self::proyecto($_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : 'GET');
        $material = new Material();
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $alertas = self::guardarDesdePost($material, $proyecto);
            if (!$alertas) {
                header('Location: /materiales?proyecto=' . $proyecto->id . '&ok=1');
                exit;
            }
        }
        $router->render('materiales/formulario', [
            'titulo' => 'Nuevo material', 'proyecto' => $proyecto, 'material' => $material, 'alertas' => $alertas,
        ]);
    }

    public static function editar(Router $router)
    {
        $proyecto = self::proyecto($_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : 'GET');
        $id = filter_var(($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET)['id'] ?? null, FILTER_VALIDATE_INT);
        $material = Material::find($id);
        if (!$material || (int)$material->proyecto_id !== (int)$proyecto->id) {
            header('Location: /materiales?proyecto=' . $proyecto->id);
            exit;
        }
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $alertas = self::guardarDesdePost($material, $proyecto);
            if (!$alertas) {
                header('Location: /materiales?proyecto=' . $proyecto->id . '&ok=1');
                exit;
            }
        }
        $router->render('materiales/formulario', [
            'titulo' => 'Editar material', 'proyecto' => $proyecto, 'material' => $material, 'alertas' => $alertas,
        ]);
    }

    public static function eliminar()
    {
        $proyecto = self::proyecto('POST');
        $material = Material::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($material && (int)$material->proyecto_id === (int)$proyecto->id) {
            Imagenes::eliminar($material->imagen);
            if ($material->ficha_tecnica) @unlink(self::fichaDir((int)$proyecto->id) . '/' . basename($material->ficha_tecnica));
            $material->eliminar();
        }
        header('Location: /materiales?proyecto=' . $proyecto->id);
        exit;
    }

    /** Toggles de la tabla: compra (pendiente/comprado) y entregado */
    public static function estado()
    {
        $proyecto = self::proyecto('POST');
        $m = Material::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        header('Content-Type: application/json');
        $campo = $_POST['campo'] ?? '';
        if (!$m || (int)$m->proyecto_id !== (int)$proyecto->id || !in_array($campo, ['compra', 'entregado'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false]);
            exit;
        }
        if ($campo === 'compra') $m->fijarCompra($_POST['valor'] ?? '');
        else $m->fijarEntregado(($_POST['valor'] ?? '') === '1');
        $m->guardar();
        $m->sincronizarMsr();
        echo json_encode(['success' => true, 'compra' => $m->compra, 'entregado' => $m->entregado(), 'fecha_entrega_real' => $m->fecha_entrega_real, 'fecha_compra_real' => $m->fecha_compra_real]);
        exit;
    }

    /** Descarga de ficha técnica (cualquiera que pueda ver el proyecto) */
    public static function ficha()
    {
        redirigir_si_no_auth();
        $material = Material::find(filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT));
        $proyecto = $material ? Proyecto::find($material->proyecto_id) : null;
        if (!$proyecto) {
            http_response_code(404);
            exit;
        }
        exigir_ver_proyecto($proyecto);
        $ruta = $material->ficha_tecnica ? self::fichaDir((int)$proyecto->id) . '/' . basename($material->ficha_tecnica) : null;
        if (!$ruta || !is_file($ruta)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    /** Aplica el formulario al material y lo guarda; devuelve alertas */
    private static function guardarDesdePost(Material $material, Proyecto $proyecto): array
    {
        $anterior = ['nombre' => $material->nombre, 'especialidad' => $material->especialidad, 'ficha' => $material->ficha_tecnica];
        $imagenActual = $material->imagen;
        $material->nombre_base = mb_substr(trim($_POST['nombre_base'] ?? ''), 0, 255);
        $material->marca = mb_substr(trim($_POST['marca'] ?? ''), 0, 100);
        $material->modelo = mb_substr(trim($_POST['modelo'] ?? ''), 0, 100);
        $material->valor_extra = mb_substr(trim($_POST['valor_extra'] ?? ''), 0, 150);
        $material->armarNombre();
        $material->referencia = trim($_POST['referencia'] ?? '');
        $material->especialidad = \Model\Especialidad::resolver((int)$proyecto->id, $_POST['especialidad'] ?? '');
        $material->link_web = trim($_POST['link_web'] ?? '') ?: null;
        $material->certificado_calidad = trim($_POST['certificado_calidad'] ?? '') ?: null;
        $cant = str_replace(',', '.', trim($_POST['cantidad'] ?? ''));
        $material->cantidad = $cant === '' ? null : (is_numeric($cant) ? $cant : 'x');
        $material->unidad = mb_substr(trim($_POST['unidad'] ?? ''), 0, 30);
        $compraAnterior = $material->compra;
        foreach (['fecha_compra_prog', 'fecha_entrega_prog', 'fecha_entrega_real'] as $f) {
            $material->$f = trim($_POST[$f] ?? '') ?: null;
        }
        $compraNueva = isset(Material::COMPRA[$_POST['compra'] ?? '']) ? $_POST['compra'] : 'pendiente';
        if ($compraNueva !== $compraAnterior) $material->fijarCompra($compraNueva);
        $material->proyecto_id = $proyecto->id;

        $alertas = $material->validar();
        $pdf = $_FILES['ficha_tecnica'] ?? null;
        $hayPdf = $pdf && !empty($pdf['tmp_name']) && is_uploaded_file($pdf['tmp_name']);
        if ($hayPdf && (mime_content_type($pdf['tmp_name']) !== 'application/pdf' || $pdf['size'] > self::MAX_PDF)) {
            $alertas['error'][] = 'La ficha técnica debe ser un PDF de máximo 20 MB';
        }
        $hayImg = !empty($_FILES['imagen']['tmp_name']) && is_uploaded_file($_FILES['imagen']['tmp_name']);
        if ($hayImg && !in_array(mime_content_type($_FILES['imagen']['tmp_name']), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $alertas['error'][] = 'La imagen no es válida (jpg, png, webp o gif)';
        }
        if ($hayPdf && !$alertas && !\Classes\Almacenamiento::cabe((int)$proyecto->id, (int)$pdf['size'])) $alertas['error'][] = \Classes\Almacenamiento::mensajeLleno((int)$proyecto->id);
        if ($alertas) return $alertas;

        if ($hayImg) {
            if ($nueva = Imagenes::miniatura($_FILES['imagen']['tmp_name'])) {
                Imagenes::eliminar($imagenActual);
                $material->imagen = $nueva;
            }
        } elseif (!empty($_POST['quitar_imagen'])) {
            Imagenes::eliminar($imagenActual);
            $material->imagen = null;
        }

        $dir = self::fichaDir((int)$proyecto->id);
        if ($hayPdf || $anterior['ficha']) {
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $nombre = $material->nombreFicha();
            $destino = $dir . '/' . $nombre;
            // Si otro material ya usa ese nombre, se agrega sufijo
            $n = 2;
            while (is_file($destino) && $destino !== $dir . '/' . basename((string)$anterior['ficha'])) {
                $destino = $dir . '/' . preg_replace('/\.pdf$/', "-$n.pdf", $nombre);
                $n++;
            }
            if ($hayPdf) {
                if ($anterior['ficha']) @unlink($dir . '/' . basename($anterior['ficha']));
                move_uploaded_file($pdf['tmp_name'], $destino);
                $material->ficha_tecnica = basename($destino);
            } elseif (is_file($dir . '/' . basename($anterior['ficha'])) && basename($destino) !== basename($anterior['ficha'])) {
                // Cambió el nombre o la especialidad: se renombra el PDF existente
                rename($dir . '/' . basename($anterior['ficha']), $destino);
                $material->ficha_tecnica = basename($destino);
            }
        }
        if (!empty($_POST['quitar_ficha']) && !$hayPdf && $material->ficha_tecnica) {
            @unlink($dir . '/' . basename($material->ficha_tecnica));
            $material->ficha_tecnica = null;
        }

        $r = $material->guardar();
        if (!$material->id && is_array($r)) $material->id = $r['id'];
        $material->sincronizarMsr();
        return [];
    }
}
