<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;
use Model\ArchivoCategoria;

/** Archivos del proyecto (pdf, office, libreoffice, imágenes…) con categorías */
class ArchivoController
{
    private static function esPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    private static function idPeticion(): ?int
    {
        return filter_var((self::esPost() ? $_POST : $_GET)['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $categorias = ArchivoCategoria::deProyecto((int)$proyecto->id);
        $filtro = filter_var($_GET['categoria'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($filtro && !in_array($filtro, array_map(fn($c) => (int)$c->id, $categorias), true)) $filtro = null;
        $router->render('archivos/index', [
            'titulo' => 'Archivo - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'categorias' => $categorias, 'filtro' => $filtro, 'archivos' => Archivo::deProyecto((int)$proyecto->id, $filtro),
        ]);
    }

    public static function crear(Router $router)
    {
        self::formulario($router, new Archivo());
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $a = Archivo::find(self::idPeticion());
        if (!$a || (int)$a->proyecto_id !== (int)$proyecto->id) {
            header('Location: /archivos?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $a);
    }

    private static function formulario(Router $router, Archivo $a)
    {
        $proyecto = proyecto_de_peticion(true);
        $categorias = ArchivoCategoria::deProyecto((int)$proyecto->id);
        $alertas = [];
        if (self::esPost()) {
            $a->proyecto_id = $proyecto->id;
            $a->fecha = trim($_POST['fecha'] ?? '');
            $a->nombre = trim($_POST['nombre'] ?? '');
            $a->numero_revision = trim($_POST['numero_revision'] ?? '');
            $cat = filter_var($_POST['categoria_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $a->categoria_id = $cat && in_array($cat, array_map(fn($c) => (int)$c->id, $categorias), true) ? $cat : null;
            $alertas = $a->validar();

            $f = $_FILES['archivo'] ?? null;
            $hay = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']);
            if (!$a->id && !$hay) $alertas['error'][] = 'Selecciona el archivo a subir';
            if ($hay) {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, Archivo::EXTENSIONES, true)) $alertas['error'][] = 'Tipo de archivo no permitido (.' . s($ext) . ')';
                if ($f['size'] > Archivo::MAX_BYTES) $alertas['error'][] = 'El archivo supera los 100 MB';
                elseif (!\Classes\Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) $alertas['error'][] = \Classes\Almacenamiento::mensajeLleno((int)$proyecto->id);
            } elseif (!empty($f['error']) && $f['error'] !== UPLOAD_ERR_NO_FILE) {
                $alertas['error'][] = 'No se pudo subir el archivo (¿supera el límite del servidor?)';
            }

            if (!$alertas) {
                if ($hay) {
                    $dir = Archivo::carpeta((int)$proyecto->id);
                    if (!is_dir($dir)) mkdir($dir, 0775, true);
                    $guardado = bin2hex(random_bytes(12)) . '.' . $ext;
                    if (move_uploaded_file($f['tmp_name'], $dir . '/' . $guardado)) {
                        if ($a->archivo_guardado) @unlink($dir . '/' . basename($a->archivo_guardado));   // reemplazo
                        $a->archivo_guardado = $guardado;
                        $a->archivo_original = mb_substr(basename($f['name']), 0, 255);
                        $a->tamano = (int)$f['size'];
                    } else {
                        $alertas['error'][] = 'No se pudo guardar el archivo en el servidor';
                    }
                }
                if (!$alertas) {
                    $a->guardar();
                    header('Location: /archivos?proyecto=' . $proyecto->id . '&ok=1');
                    exit;
                }
            }
        }
        $router->render('archivos/formulario', [
            'titulo' => $a->id ? 'Editar archivo' : 'Nuevo archivo', 'proyecto' => $proyecto, 'archivo' => $a, 'alertas' => $alertas,
            'categorias' => $categorias, 'extensiones' => Archivo::EXTENSIONES,
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $a = Archivo::find(self::idPeticion());
        if ($a && (int)$a->proyecto_id === (int)$proyecto->id) {
            if ($r = $a->ruta()) @unlink($r);
            $a->eliminar();
        }
        header('Location: /archivos?proyecto=' . $proyecto->id);
        exit;
    }

    /** Descarga (admin y cliente con acceso al proyecto; el visitante solo ve el listado) */
    public static function descargar()
    {
        redirigir_si_no_auth();
        $a = Archivo::find(self::idPeticion());
        $proyecto = $a ? \Model\Proyecto::find($a->proyecto_id) : null;
        if (!$proyecto) { http_response_code(404); exit; }
        exigir_ver_proyecto($proyecto);
        if (!puede_descargar()) { http_response_code(403); exit('No autorizado'); }
        $ruta = $a->ruta();
        if (!$ruta) { http_response_code(404); exit('El archivo ya no existe en el servidor'); }
        $nombre = preg_replace('/[\r\n"\\\\\/]+/', '_', $a->archivo_original);
        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $nombre . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    // ========================= CATEGORÍAS =========================

    public static function categorias(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $lista = [];
        foreach (ArchivoCategoria::deProyecto((int)$proyecto->id) as $c) $lista[] = ['cat' => $c, 'uso' => $c->enUso()];
        $router->render('archivos/categorias', ['titulo' => 'Categorías de archivo - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'lista' => $lista]);
    }

    public static function categoriaCrear(Router $router)
    {
        self::categoriaFormulario($router, new ArchivoCategoria());
    }

    public static function categoriaEditar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $c = ArchivoCategoria::find(self::idPeticion());
        if (!$c || (int)$c->proyecto_id !== (int)$proyecto->id) {
            header('Location: /archivos/categorias?proyecto=' . $proyecto->id);
            exit;
        }
        self::categoriaFormulario($router, $c);
    }

    private static function categoriaFormulario(Router $router, ArchivoCategoria $c)
    {
        $proyecto = proyecto_de_peticion(true);
        $alertas = [];
        if (self::esPost()) {
            $c->proyecto_id = $proyecto->id;
            $c->nombre = trim($_POST['nombre'] ?? '');
            $alertas = $c->validar();
            if (!$alertas) {
                $c->guardar();
                header('Location: /archivos/categorias?proyecto=' . $proyecto->id . '&ok=1');
                exit;
            }
        }
        $router->render('archivos/categoria-formulario', ['titulo' => $c->id ? 'Editar categoría' : 'Nueva categoría', 'proyecto' => $proyecto, 'cat' => $c, 'alertas' => $alertas]);
    }

    public static function categoriaEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $c = ArchivoCategoria::find(self::idPeticion());
        $error = '';
        if ($c && (int)$c->proyecto_id === (int)$proyecto->id) {
            $uso = $c->enUso();
            if ($uso > 0) $error = '&error=' . urlencode("No se puede eliminar «{$c->nombre}»: la usan $uso archivo(s).");
            else $c->eliminar();
        }
        header('Location: /archivos/categorias?proyecto=' . $proyecto->id . $error);
        exit;
    }
}
