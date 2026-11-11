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
        $deMateriales = Simbolo::deMateriales((int)$proyecto->id);
        $categorias = array_values(array_unique(array_filter(array_merge(
            array_map(fn($s) => $s->categoria, $propios),
            array_map(fn($m) => $m['categoria'], $deMateriales)
        ))));
        sort($categorias, SORT_NATURAL | SORT_FLAG_CASE);
        $router->render('simbolos/index', [
            'titulo' => 'Símbolos - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'simbolos' => $propios, 'cajetines' => Cajetin::deProyecto((int)$proyecto->id), 'simbologias' => \Model\Grafico::simbologias((int)$proyecto->id),
            'predeterminados' => Simbolo::predeterminados(), 'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'estampas' => \Model\Estampa::deProyecto((int)$proyecto->id), 'plantillasEstampa' => \Classes\Estampas::plantillas(),
            'materialesSimbolos' => $deMateriales, 'categorias' => $categorias,
            'usosSimbolos' => Simbolo::usos((int)$proyecto->id),
        ]);
    }

    private static function volver(int $proyectoId, string $clave = '', string $mensaje = '', string $tab = ''): void
    {
        $q = 'proyecto=' . $proyectoId;
        if ($clave !== '') $q .= '&' . $clave . '=' . urlencode($mensaje);
        if ($tab !== '') $q .= '&tab=' . urlencode($tab);
        header('Location: /simbolos?' . $q);
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
        if ($alertas = $nuevo->validar()) self::volver((int)$proyecto->id, 'error', $alertas['error'][0], 'proyecto');

        $f = $_FILES['archivo'] ?? null;
        $codigo = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        $hay = $f && $codigo === UPLOAD_ERR_OK && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']);
        if (!$s && $codigo === UPLOAD_ERR_NO_FILE) self::volver((int)$proyecto->id, 'error', 'Elige la imagen del símbolo (png, jpg, webp, gif o svg)', 'proyecto');
        if ($codigo !== UPLOAD_ERR_OK && $codigo !== UPLOAD_ERR_NO_FILE) {
            $motivos = [
                UPLOAD_ERR_INI_SIZE => 'La imagen supera el límite del servidor (' . ini_get('upload_max_filesize') . ').',
                UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño permitido por el formulario.',
                UPLOAD_ERR_PARTIAL => 'La imagen se subió incompleta. Vuelve a intentarlo.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal para las subidas.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir la imagen en disco.',
                UPLOAD_ERR_EXTENSION => 'El servidor rechazó la subida de la imagen.',
            ];
            self::volver((int)$proyecto->id, 'error', $motivos[$codigo] ?? 'No se pudo subir la imagen.', 'proyecto');
        }
        if ($hay) {
            $crudo = (string)file_get_contents($f['tmp_name'], false, null, 0, 4096);
            $esSvg = preg_match('/<svg\b/i', $crudo) === 1;
            $mime = $esSvg ? 'image/svg+xml' : Imagenes::tipoImagen($f['tmp_name']);
            if ($f['size'] > 8 * 1024 * 1024) {
                self::volver((int)$proyecto->id, 'error', 'La imagen no es válida (png, jpg, webp, gif o svg de hasta 8 MB)', 'proyecto');
            }
            if (!$esSvg && $mime === null) {
                self::volver((int)$proyecto->id, 'error', 'La imagen no es válida (png, jpg, webp, gif o svg de hasta 8 MB)', 'proyecto');
            }
            if (!Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) self::volver((int)$proyecto->id, 'error', Almacenamiento::mensajeLleno((int)$proyecto->id), 'proyecto');
            $archivo = Simbolo::guardarArchivo($f['tmp_name']);
            if (!$archivo && $esSvg) self::volver((int)$proyecto->id, 'error', 'El SVG no es válido o contiene elementos no permitidos', 'proyecto');
            if (!$archivo) {
                $extra = ($mime === 'image/webp' && !function_exists('imagecreatefromwebp')) ? ' La imagen es WebP y este servidor no tiene soporte WebP; guárdala como PNG o JPG.' : '';
                self::volver((int)$proyecto->id, 'error', 'No se pudo procesar la imagen. Revisa que no esté dañada y que el servidor pueda escribir en uploads/simbolos.' . $extra, 'proyecto');
            }
            if ($s) Imagenes::eliminarEn(Simbolo::CARPETA, $s->archivo);
            $nuevo->archivo = $archivo;
        }
        $r = $nuevo->guardar();
        $ok = is_array($r) ? (!empty($r['resultado']) && !empty($r['id'])) : (bool)$r;
        if (!$ok) {
            if (!empty($archivo)) Imagenes::eliminarEn(Simbolo::CARPETA, $archivo);
            self::volver((int)$proyecto->id, 'error', 'No se pudo guardar el símbolo en la base de datos.', 'proyecto');
        }
        self::volver((int)$proyecto->id, 'ok', $s ? 'Símbolo actualizado' : 'Símbolo agregado', 'proyecto');
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $s = Simbolo::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($s && (int)$s->proyecto_id === (int)$proyecto->id) {
            $s->eliminarArchivo();   // las marcas ya colocadas conservan su propia copia de la imagen
            $s->eliminar();
            self::volver((int)$proyecto->id, 'ok', 'Símbolo eliminado', 'proyecto');
        }
        self::volver((int)$proyecto->id, '', '', 'proyecto');
    }

    // ---------- cajetines ----------

    public static function guardarCajetin()
    {
        $proyecto = proyecto_de_peticion(true);
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $c = $id ? Cajetin::find($id) : null;
        if ($id && (!$c || (int)$c->proyecto_id !== (int)$proyecto->id)) self::volver((int)$proyecto->id);
        $nuevo = new Cajetin(['id' => $c->id ?? null, 'proyecto_id' => $proyecto->id, 'nombre' => $_POST['nombre'] ?? '', 'orientacion' => $_POST['orientacion'] ?? '', 'ancho' => $_POST['ancho'] ?? 28,
            'filas_json' => Cajetin::filasDeTexto(texto_param($_POST['filas'] ?? ''))]);
        if ($alertas = $nuevo->validar()) self::volver((int)$proyecto->id, 'error', $alertas['error'][0], 'cajetines');
        $nuevo->guardar();
        self::volver((int)$proyecto->id, 'ok', $c ? 'Cajetín actualizado' : 'Cajetín agregado', 'cajetines');
    }

    public static function eliminarCajetin()
    {
        $proyecto = proyecto_de_peticion(true);
        $c = Cajetin::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($c && (int)$c->proyecto_id === (int)$proyecto->id) $c->eliminar();
        self::volver((int)$proyecto->id, 'ok', 'Cajetín eliminado', 'cajetines');
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

    /** Importa un .zip de biblioteca y/o imágenes sueltas (el nombre del archivo es el del símbolo) */
    public static function importar()
    {
        header('Content-Type: application/json');
        $proyecto = ($_SERVER['REQUEST_METHOD'] === 'POST' && is_auth()) ? \Model\Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        @set_time_limit(120);
        $pid = (int)$proyecto->id;
        $existentes = [];
        foreach (Simbolo::deProyecto($pid) as $s) $existentes[mb_strtolower($s->categoria . '|' . $s->nombre)] = true;
        $imp = 0; $omit = 0; $cajNuevos = 0; $total = 0; $motivos = [];
        $motivo = function (string $texto) use (&$omit, &$motivos): void {
            $omit++;
            $motivos[$texto] = ($motivos[$texto] ?? 0) + 1;
        };
        $agregar = function (string $ruta, string $nombre, string $cat) use (&$imp, &$existentes, $pid, $motivo): void {
            $nombre = mb_substr(trim($nombre), 0, 100);
            $cat = mb_substr(trim($cat), 0, 60);
            $clave = mb_strtolower($cat . '|' . $nombre);
            if ($nombre === '') { $motivo('Archivo sin nombre'); return; }
            if (isset($existentes[$clave])) { $motivo('Ya existe un símbolo con ese nombre y categoría'); return; }
            if (!Almacenamiento::cabe($pid, (int)@filesize($ruta))) { $motivo('No hay espacio de almacenamiento'); return; }
            $archivo = Simbolo::guardarArchivo($ruta);
            if (!$archivo) { $motivo('No se pudo procesar la imagen'); return; }
            $r = (new Simbolo(['proyecto_id' => $pid, 'nombre' => $nombre, 'categoria' => $cat, 'archivo' => $archivo]))->guardar();
            if (is_array($r) && empty($r['id'])) {
                Imagenes::eliminarEn(Simbolo::CARPETA, $archivo);
                $motivo('No se pudo guardar en la base de datos');
                return;
            }
            $existentes[$clave] = true;
            $imp++;
        };
        $f = $_FILES['archivos'] ?? null;
        $n = $f && is_array($f['name']) ? count($f['name']) : 0;
        if (!$n || $n > 300) json_respuesta(['success' => false, 'error' => 'Elige entre 1 y 300 archivos'], 422);
        for ($i = 0; $i < $n; $i++) {
            $tmp = $f['tmp_name'][$i];
            if (!$tmp || !is_uploaded_file($tmp) || $f['size'][$i] > 60 * 1024 * 1024) { $motivo('Archivo no válido o demasiado grande'); continue; }
            $nom = (string)$f['name'][$i];
            if (strtolower(pathinfo($nom, PATHINFO_EXTENSION)) === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($tmp) !== true) { $motivo('El archivo ZIP no se pudo abrir'); continue; }
                $meta = json_decode((string)$zip->getFromName('simbolos.json'), true);
                $bytes = 0;
                foreach (array_slice($meta['simbolos'] ?? [], 0, 300) as $sm) {
                    $arch = basename((string)($sm['archivo'] ?? ''));
                    $st = $arch !== '' ? $zip->statName('imagenes/' . $arch) : false;
                    if (!$st || $st['size'] > 8 * 1024 * 1024 || ($bytes += $st['size']) > 60 * 1024 * 1024) { $motivo('Imagen del ZIP no válida o demasiado grande'); continue; }
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
        json_respuesta(['success' => true, 'importados' => $imp, 'omitidos' => $omit, 'cajetines_nuevos' => $cajNuevos,
            'motivos' => $motivos, 'simbolos' => Simbolo::paraEditor($pid), 'cajetines' => Cajetin::paraEditor($pid)]);
    }
}
