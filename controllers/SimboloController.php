<?php

namespace Controllers;

use MVC\Router;
use Model\Simbolo;
use Classes\Imagenes;
use Classes\Almacenamiento;
use Model\Cajetin;

/** Biblioteca de símbolos por proyecto: imágenes reutilizables en el editor del vPlan y en el Editor de gráficos */
class SimboloController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $propios = Simbolo::deProyecto((int)$proyecto->id);
        $router->render('simbolos/index', [
            'titulo' => 'Símbolos - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'simbolos' => $propios, 'cajetines' => Cajetin::deProyecto((int)$proyecto->id), 'simbologias' => \Model\Grafico::simbologias((int)$proyecto->id),
            'predeterminados' => Simbolo::predeterminados(), 'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'categorias' => array_values(array_unique(array_filter(array_map(fn($s) => $s->categoria, $propios)))),
        ]);
    }

    private static function volver(int $proyectoId, string $clave = '', string $mensaje = ''): void
    {
        header('Location: /simbolos?proyecto=' . $proyectoId . ($clave ? '&' . $clave . '=' . urlencode($mensaje) : ''));
        exit;
    }

    /** Crea un símbolo o edita el nombre, la categoría y (opcionalmente) la imagen de uno existente */
    public static function guardar()
    {
        $proyecto = proyecto_de_peticion(true);
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $s = $id ? Simbolo::find($id) : null;
        if ($id && (!$s || (int)$s->proyecto_id !== (int)$proyecto->id)) self::volver((int)$proyecto->id);
        $nuevo = new Simbolo(['id' => $s->id ?? null, 'proyecto_id' => $proyecto->id, 'nombre' => $_POST['nombre'] ?? '', 'categoria' => $_POST['categoria'] ?? '', 'archivo' => $s->archivo ?? '']);
        if ($alertas = $nuevo->validar()) self::volver((int)$proyecto->id, 'error', $alertas['error'][0]);

        $f = $_FILES['archivo'] ?? null;
        $hay = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']);
        if (!$s && !$hay) self::volver((int)$proyecto->id, 'error', 'Elige la imagen del símbolo (png, jpg, webp, gif o svg)');
        if ($hay) {
            $crudo = (string)file_get_contents($f['tmp_name'], false, null, 0, 4096);
            $esSvg = preg_match('/<svg\b/i', $crudo) === 1;
            if ($f['size'] > 8 * 1024 * 1024 || (!$esSvg && !in_array(mime_content_type($f['tmp_name']), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true))) {
                self::volver((int)$proyecto->id, 'error', 'La imagen no es válida (png, jpg, webp, gif o svg de hasta 8 MB)');
            }
            if (!Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) self::volver((int)$proyecto->id, 'error', Almacenamiento::mensajeLleno((int)$proyecto->id));
            $archivo = Simbolo::guardarArchivo($f['tmp_name']);
            if (!$archivo && $esSvg) self::volver((int)$proyecto->id, 'error', 'El SVG no es válido o contiene elementos no permitidos');
            if (!$archivo) self::volver((int)$proyecto->id, 'error', 'No se pudo procesar la imagen');
            if ($s) Imagenes::eliminarEn(Simbolo::CARPETA, $s->archivo);
            $nuevo->archivo = $archivo;
        }
        $nuevo->guardar();
        self::volver((int)$proyecto->id, 'ok', $s ? 'Símbolo actualizado' : 'Símbolo agregado');
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $s = Simbolo::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($s && (int)$s->proyecto_id === (int)$proyecto->id) {
            $s->eliminarArchivo();   // las marcas ya colocadas conservan su propia copia de la imagen
            $s->eliminar();
            self::volver((int)$proyecto->id, 'ok', 'Símbolo eliminado');
        }
        self::volver((int)$proyecto->id);
    }

    // ---------- cajetines ----------

    public static function guardarCajetin()
    {
        $proyecto = proyecto_de_peticion(true);
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $c = $id ? Cajetin::find($id) : null;
        if ($id && (!$c || (int)$c->proyecto_id !== (int)$proyecto->id)) self::volver((int)$proyecto->id);
        $nuevo = new Cajetin(['id' => $c->id ?? null, 'proyecto_id' => $proyecto->id, 'nombre' => $_POST['nombre'] ?? '', 'orientacion' => $_POST['orientacion'] ?? '', 'ancho' => $_POST['ancho'] ?? 28,
            'filas_json' => Cajetin::filasDeTexto((string)($_POST['filas'] ?? ''))]);
        if ($alertas = $nuevo->validar()) self::volver((int)$proyecto->id, 'error', $alertas['error'][0]);
        $nuevo->guardar();
        self::volver((int)$proyecto->id, 'ok', $c ? 'Cajetín actualizado' : 'Cajetín agregado');
    }

    public static function eliminarCajetin()
    {
        $proyecto = proyecto_de_peticion(true);
        $c = Cajetin::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($c && (int)$c->proyecto_id === (int)$proyecto->id) $c->eliminar();
        self::volver((int)$proyecto->id, 'ok', 'Cajetín eliminado');
    }

    // ---------- exportar / importar la biblioteca ----------

    /** .zip con simbolos.json (símbolos y cajetines) y las imágenes */
    public static function exportar()
    {
        $proyecto = proyecto_de_peticion();
        $simbolos = Simbolo::deProyecto((int)$proyecto->id);
        $tmp = tempnam(sys_get_temp_dir(), 'sbz');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $lista = [];
        foreach ($simbolos as $s) {
            $ruta = __DIR__ . '/../public/uploads/' . Simbolo::CARPETA . '/' . basename((string)$s->archivo);
            if (!is_file($ruta)) continue;
            $zip->addFile($ruta, 'imagenes/' . basename($s->archivo));
            $lista[] = ['nombre' => $s->nombre, 'categoria' => $s->categoria, 'archivo' => basename($s->archivo)];
        }
        $zip->addFromString('simbolos.json', json_encode(['app' => 'projectweb', 'version' => 1, 'simbolos' => $lista,
            'cajetines' => array_map(fn($c) => $c->aArray(), Cajetin::deProyecto((int)$proyecto->id))], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();
        $datos = (string)file_get_contents($tmp);
        @unlink($tmp);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . date('Y_m_d') . '_simbolos_' . slug_archivo($proyecto->nombre) . '.zip"');
        header('Content-Length: ' . strlen($datos));
        echo $datos;
        exit;
    }

    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d);
        exit;
    }

    /** Importa un .zip de biblioteca y/o imágenes sueltas (el nombre del archivo es el del símbolo) */
    public static function importar()
    {
        header('Content-Type: application/json');
        $proyecto = ($_SERVER['REQUEST_METHOD'] === 'POST' && is_auth()) ? \Model\Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        @set_time_limit(120);
        $pid = (int)$proyecto->id;
        $existentes = [];
        foreach (Simbolo::deProyecto($pid) as $s) $existentes[mb_strtolower($s->categoria . '|' . $s->nombre)] = true;
        $imp = 0; $omit = 0; $cajNuevos = 0; $total = 0;
        $agregar = function (string $ruta, string $nombre, string $cat) use (&$imp, &$omit, &$existentes, $pid): void {
            $nombre = mb_substr(trim($nombre), 0, 100);
            $cat = mb_substr(trim($cat), 0, 60);
            $clave = mb_strtolower($cat . '|' . $nombre);
            if ($nombre === '' || isset($existentes[$clave]) || !Almacenamiento::cabe($pid, (int)@filesize($ruta))) { $omit++; return; }
            $archivo = Simbolo::guardarArchivo($ruta);
            if (!$archivo) { $omit++; return; }
            (new Simbolo(['proyecto_id' => $pid, 'nombre' => $nombre, 'categoria' => $cat, 'archivo' => $archivo]))->guardar();
            $existentes[$clave] = true;
            $imp++;
        };
        $f = $_FILES['archivos'] ?? null;
        $n = $f && is_array($f['name']) ? count($f['name']) : 0;
        if (!$n || $n > 300) self::json(['success' => false, 'error' => 'Elige entre 1 y 300 archivos'], 422);
        for ($i = 0; $i < $n; $i++) {
            $tmp = $f['tmp_name'][$i];
            if (!$tmp || !is_uploaded_file($tmp) || $f['size'][$i] > 60 * 1024 * 1024) { $omit++; continue; }
            $nom = (string)$f['name'][$i];
            if (strtolower(pathinfo($nom, PATHINFO_EXTENSION)) === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($tmp) !== true) { $omit++; continue; }
                $meta = json_decode((string)$zip->getFromName('simbolos.json'), true);
                $bytes = 0;
                foreach (array_slice($meta['simbolos'] ?? [], 0, 300) as $sm) {
                    $arch = basename((string)($sm['archivo'] ?? ''));
                    $st = $arch !== '' ? $zip->statName('imagenes/' . $arch) : false;
                    if (!$st || $st['size'] > 8 * 1024 * 1024 || ($bytes += $st['size']) > 60 * 1024 * 1024) { $omit++; continue; }
                    $t = tempnam(sys_get_temp_dir(), 'sbi');
                    file_put_contents($t, $zip->getFromName('imagenes/' . $arch));
                    $agregar($t, (string)($sm['nombre'] ?? ''), (string)($sm['categoria'] ?? ''));
                    @unlink($t);
                    $total++;
                }
                $conocidos = array_map(fn($c) => mb_strtolower($c->nombre), Cajetin::deProyecto($pid));
                foreach (array_slice($meta['cajetines'] ?? [], 0, 50) as $cj) {
                    $nuevo = new Cajetin(['proyecto_id' => $pid, 'nombre' => $cj['nombre'] ?? '', 'orientacion' => $cj['orientacion'] ?? '', 'ancho' => $cj['ancho'] ?? 28, 'filas_json' => json_encode($cj['filas'] ?? [], JSON_UNESCAPED_UNICODE)]);
                    if ($nuevo->validar() || in_array(mb_strtolower($nuevo->nombre), $conocidos, true)) continue;
                    $nuevo->guardar();
                    $cajNuevos++;
                }
                $zip->close();
            } else {
                $agregar($tmp, pathinfo($nom, PATHINFO_FILENAME), 'Importados');
                $total++;
            }
        }
        self::json(['success' => true, 'importados' => $imp, 'omitidos' => $omit, 'cajetines_nuevos' => $cajNuevos,
            'simbolos' => Simbolo::paraEditor($pid), 'cajetines' => Cajetin::paraEditor($pid)]);
    }
}
