<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\SheetflowHoja;
use Model\VplanTabla;
use Classes\Sheetflow;
use Classes\Imagenes;
use Classes\Almacenamiento;

/** SheetFlow: tablas del proyecto (lista por departamento, alta con plantilla o importación, edición, vista, descarga y uso en vPlan/Editor/Redactor) */
class SheetflowController
{
    private static function hojaDe(Proyecto $proyecto, $id): ?SheetflowHoja
    {
        $h = SheetflowHoja::find(filter_var($id, FILTER_VALIDATE_INT));
        return $h && (int)$h->proyecto_id === (int)$proyecto->id ? $h : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $porDepto = [];
        $tamanos = [];
        foreach (SheetflowHoja::deProyecto((int)$proyecto->id) as $h) {
            $porDepto[$h->departamento !== '' ? $h->departamento : 'Sin departamento'][] = $h;
            $c = $h->contenido();
            $tamanos[$h->id] = [count($c['rows']), count($c['cols'])];
        }
        $router->render('sheetflow/index', [
            'titulo' => 'SheetFlow - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'porDepto' => $porDepto, 'tamanos' => $tamanos,
            'puedeEditar' => puede_gestionar_proyecto($proyecto), 'puedeDescargar' => puede_descargar(),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $h = new SheetflowHoja(['proyecto_id' => $proyecto->id]);
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $h = new SheetflowHoja([
                'proyecto_id' => $proyecto->id, 'nombre' => $_POST['nombre'] ?? '', 'fecha' => $_POST['fecha'] ?? '', 'departamento' => $_POST['departamento'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '', 'creado_por' => $_SESSION['id'] ?? null,
            ]);
            $alertas = $h->validar();
            $datos = null;
            if (!$alertas) {
                $subida = $_FILES['archivo'] ?? null;
                if ($subida && ($subida['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    try {
                        if (strtolower(pathinfo((string)$subida['name'], PATHINFO_EXTENSION)) === 'sheetflow') {   // archivo propio: trae nombre, descripción y departamento si no los escribiste
                            [$datos, $meta] = Sheetflow::deArchivo($subida['tmp_name']);
                        } else {
                            $datos = null;
                        }
                        if ($subida['size'] > 25_000_000) throw new \RuntimeException('El archivo es demasiado grande (máx. 5 MB)');
                        $datos ??= Sheetflow::importar($subida['tmp_name'], (string)$subida['name']);
                    } catch (\Throwable $e) {
                        $alertas['error'][] = $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo leer el archivo';
                        if (!$e instanceof \RuntimeException) error_log('SheetFlow importar: ' . $e->getMessage());
                    }
                } elseif ($subida && ($subida['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) {
                    $alertas['error'][] = 'No se pudo subir el archivo';
                } elseif (($_POST['plantilla'] ?? '') === 'inteligente') {   // constructor guiado de tabla inteligente
                    $datos = Sheetflow::inteligente(json_decode((string)($_POST['int_cols'] ?? ''), true), (int)($_POST['int_filas'] ?? 10));
                } else {
                    $datos = Sheetflow::plantilla((string)($_POST['plantilla'] ?? ''), (int)$proyecto->id) ?? Sheetflow::vacia();
                }
            }
            if (!$alertas) {
                $h->datos = json_encode($datos, JSON_UNESCAPED_UNICODE);
                $h->id = $h->guardar()['id'];
                header('Location: /sheetflow/abrir?proyecto=' . $proyecto->id . '&id=' . $h->id);
                exit;
            }
        }
        $router->render('sheetflow/crear', ['titulo' => 'Nueva tabla', 'proyecto' => $proyecto, 'hoja' => $h, 'alertas' => $alertas,
            'departamentos' => SheetflowHoja::departamentos((int)$proyecto->id), 'plantillas' => Sheetflow::plantillas()]);
    }

    public static function abrir(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $h = self::hojaDe($proyecto, $_GET['id'] ?? null);
        if (!$h) {
            header('Location: /sheetflow?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('sheetflow/abrir', [
            'titulo' => 'SheetFlow - ' . $h->nombre, 'proyecto' => $proyecto, 'hoja' => $h,
            'departamentos' => SheetflowHoja::departamentos((int)$proyecto->id),
            'usos' => $h->usosVplan(),
            'config' => [
                'id' => (int)$h->id, 'proyecto' => (int)$proyecto->id, 'version' => (string)$h->actualizado, 'datos' => $h->contenido(),
                'max' => ['cols' => Sheetflow::MAX_COLS, 'filas' => Sheetflow::MAX_FILAS],
                'api' => ['guardar' => '/api/sheetflow/guardar'],
                'simbolos' => \Model\Simbolo::paraEditor((int)$proyecto->id),
            ],
        ]);
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $h = self::hojaDe($proyecto, $_GET['id'] ?? null);
        if (!$h) {
            header('Location: /sheetflow?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('sheetflow/ver', [
            'titulo' => 'SheetFlow - ' . $h->nombre, 'proyecto' => $proyecto, 'hoja' => $h, 'tabla' => Sheetflow::html($h),
            'puedeEditar' => puede_gestionar_proyecto($proyecto), 'puedeDescargar' => puede_descargar(),
            'vplan' => puede_gestionar_proyecto($proyecto) ? $h->usosVplan() : [],
        ]);
    }

    /** Descarga con la nomenclatura Y_m_d_Nombre.ext (xlsx, csv o pdf; con inline=1 el PDF se abre en el navegador) */
    public static function descargar()
    {
        $proyecto = proyecto_de_peticion();
        $h = self::hojaDe($proyecto, $_GET['id'] ?? null);
        if (!$h || !puede_descargar()) {
            header('Location: /sheetflow?proyecto=' . $proyecto->id . '&error=' . urlencode('No se puede descargar la tabla'));
            exit;
        }
        $formato = in_array($_GET['formato'] ?? 'xlsx', ['xlsx', 'csv', 'pdf', 'sheetflow'], true) ? $_GET['formato'] : 'xlsx';
        $base = date('Y_m_d') . '_' . slug_archivo($h->nombre);
        @set_time_limit(120);
        try {
            $datos = match ($formato) { 'csv' => Sheetflow::csv($h), 'pdf' => Sheetflow::pdf($h), 'sheetflow' => Sheetflow::aArchivo($h), default => Sheetflow::xlsx($h) };
        } catch (\Throwable $e) {
            error_log('SheetFlow exportar: ' . $e->getMessage());
            header('Location: /sheetflow/ver?proyecto=' . $proyecto->id . '&id=' . $h->id . '&error=' . urlencode('No se pudo generar el archivo'));
            exit;
        }
        $tipos = ['xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv; charset=utf-8', 'pdf' => 'application/pdf', 'sheetflow' => 'application/octet-stream'];
        enviar_descarga($datos, $tipos[$formato], $base . '.' . $formato, $formato === 'pdf' && !empty($_GET['inline']));
    }

    public static function duplicar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($h = self::hojaDe($proyecto, $_POST['id'] ?? null)) {
            $n = new SheetflowHoja(get_object_vars($h));
            $n->id = null;
            $n->nombre = mb_substr($h->nombre, 0, 140) . ' (copia)';
            $n->fecha = date('Y-m-d');
            $n->creado_por = $_SESSION['id'] ?? null;
            $n->guardar();
        }
        header('Location: /sheetflow?proyecto=' . $proyecto->id . '&ok=1');
        exit;
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($h = self::hojaDe($proyecto, $_POST['id'] ?? null)) {
            $h->eliminar();
            Sheetflow::limpiarImagenes($h->imagenes(), [], (int)$proyecto->id, (int)$h->id);
        }   // las tablas de vPlan que la usaban se quedan con sus datos (solo pierden el vínculo)
        header('Location: /sheetflow?proyecto=' . $proyecto->id . '&ok=1');
        exit;
    }

    /** Hoja de una petición de la API (campo «id»); $gestionar = true exige poder editar el proyecto */
    private static function hojaApi(bool $gestionar): SheetflowHoja
    {
        $h = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? SheetflowHoja::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        $p = $h ? Proyecto::find($h->proyecto_id) : null;
        if (!$p || !($gestionar ? puede_gestionar_proyecto($p) : puede_ver_proyecto($p))) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        return $h;
    }

    public static function guardar()
    {
        $h = self::hojaApi(true);
        $ver = (string)($_POST['version'] ?? '');
        if ($ver !== '' && empty($_POST['forzar']) && $ver !== (string)$h->actualizado) {
            json_respuesta(['success' => false, 'conflicto' => true, 'error' => 'Esta tabla se guardó desde otra sesión después de que la abrieras.'], 409);
        }
        $crudo = (string)($_POST['datos'] ?? '');
        if (strlen($crudo) > Sheetflow::MAX_BYTES) json_respuesta(['success' => false, 'error' => 'La tabla es demasiado grande'], 422);
        $limpio = Sheetflow::normalizar(json_decode($crudo, true));
        $nuevo = new SheetflowHoja([
            'id' => $h->id, 'proyecto_id' => $h->proyecto_id, 'creado_por' => $h->creado_por, 'descripcion' => $_POST['descripcion'] ?? $h->descripcion,
            'nombre' => $_POST['nombre'] ?? $h->nombre, 'fecha' => $_POST['fecha'] ?? $h->fecha, 'departamento' => $_POST['departamento'] ?? $h->departamento,
            'datos' => json_encode($limpio, JSON_UNESCAPED_UNICODE),
        ]);
        if ($alertas = $nuevo->validar()) json_respuesta(['success' => false, 'error' => $alertas['error'][0]], 422);
        $nuevo->guardar();
        Sheetflow::limpiarImagenes($h->imagenes(), $nuevo->imagenes(), (int)$h->proyecto_id, (int)$h->id);
        $act = SheetflowHoja::find($h->id);
        json_respuesta(['success' => true, 'version' => (string)$act->actualizado]);
    }

    /** Sube una imagen para una celda; devuelve su ruta (se guarda al guardar la tabla) */
    public static function imagen()
    {
        $h = self::hojaApi(true);
        $f = $_FILES['imagen'] ?? null;
        if (!$f || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name']) || $f['size'] > 20 * 1024 * 1024) json_respuesta(['success' => false, 'error' => 'Elige una imagen de hasta 20 MB'], 422);
        if (!Almacenamiento::cabe((int)$h->proyecto_id, (int)$f['size'])) json_respuesta(['success' => false, 'error' => Almacenamiento::mensajeLleno((int)$h->proyecto_id)], 422);
        $n = Imagenes::guardarGrande($f['tmp_name'], 1600);
        if (!$n) json_respuesta(['success' => false, 'error' => 'La imagen no es válida (jpg, png, webp o gif)'], 422);
        json_respuesta(['success' => true, 'src' => '/uploads/vplan/' . $n]);
    }

    /** Tabla ya formateada (columnas, tipos, renglones, totales) para usarla en el Editor y el Redactor */
    public static function datos()
    {
        $h = self::hojaApi(false);
        json_respuesta(['success' => true, 'nombre' => $h->nombre] + Sheetflow::matriz($h));
    }

    /** Huella de la hoja (su última modificación) para que las vistas se actualicen solas */
    public static function version()
    {
        $h = self::hojaApi(false);
        json_respuesta(['success' => true, 'version' => md5($h->actualizado . '|' . $h->nombre . '|' . strlen((string)$h->datos))]);
    }

    /** Crea una tabla de vPlan con esta hoja, o (con «tabla») la actualiza con los datos actuales */
    public static function aVplan()
    {
        $proyecto = proyecto_de_peticion(true);
        $h = self::hojaDe($proyecto, $_POST['id'] ?? null);
        $destino = '/sheetflow?proyecto=' . $proyecto->id;
        if ($h) {
            $existente = null;
            if (!empty($_POST['tabla'])) {
                $existente = VplanTabla::find(filter_var($_POST['tabla'], FILTER_VALIDATE_INT));
                if (!$existente || (int)$existente->proyecto_id !== (int)$proyecto->id || (int)$existente->sheetflow_id !== (int)$h->id) $existente = null;
            }
            $t = Sheetflow::aVplan($h, $existente);
            $destino = '/vplan/tablas/editar?proyecto=' . $proyecto->id . '&id=' . $t->id . '&ok=1';
        }
        header('Location: ' . $destino);
        exit;
    }
}
