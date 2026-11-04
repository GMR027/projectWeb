<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;
use Model\ArchivoCategoria;
use Model\ArchivoCarpeta;

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
        $carpetas = ArchivoCarpeta::deProyecto((int)$proyecto->id);
        // carpeta: vacío = todas, 'sin' = sin carpeta, número = esa carpeta
        $carpeta = null;
        if (($_GET['carpeta'] ?? '') === 'sin') $carpeta = 0;
        elseif ($c = filter_var($_GET['carpeta'] ?? null, FILTER_VALIDATE_INT)) {
            if (in_array($c, array_map(fn($x) => (int)$x->id, $carpetas), true)) $carpeta = $c;
        }
        $mensaje = $_SESSION['archivo_msg'] ?? null;
        unset($_SESSION['archivo_msg']);
        $router->render('archivos/index', [
            'titulo' => 'Archivo - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'categorias' => $categorias, 'filtro' => $filtro, 'archivos' => Archivo::deProyecto((int)$proyecto->id, $filtro, $carpeta),
            'carpetas' => $carpetas, 'carpeta' => $carpeta, 'sinCarpeta' => ArchivoCarpeta::sinCarpeta((int)$proyecto->id),
            'totalArchivos' => count(Archivo::deProyecto((int)$proyecto->id)), 'ensamblados' => \Model\Ensamble::archivosVivos((int)$proyecto->id),
            'mensaje' => $mensaje,
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

    private const MAX_LOTE = 20;
    private const MAX_LOTE_BYTES = 200 * 1024 * 1024;

    /** Subir varios archivos a la vez, con fecha, categoría, carpeta, nombre y revisión comunes o por archivo */
    public static function lote(Router $router)
    {
        // Si PHP descarta el POST por superar post_max_size no llega «proyecto»: se avisa y se vuelve a Archivo
        if (self::esPost() && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $pid = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT) ?: 0;
            if ($pid) {
                self::responderLote(false, 'El lote es demasiado grande para el servidor. Reduce el número o el tamaño de los archivos o pide al administrador que aumente los límites de subida.');
                header('Location: /archivos?proyecto=' . $pid);
                exit;
            }
        }
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $categorias = ArchivoCategoria::deProyecto($pid);
        $carpetas = ArchivoCarpeta::deProyecto($pid);
        if (self::esPost()) self::guardarLote($proyecto, $categorias, $carpetas);
        $carpeta = filter_var($_GET['carpeta'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($carpeta && !in_array($carpeta, array_map(fn($c) => (int)$c->id, $carpetas), true)) $carpeta = null;
        $router->render('archivos/lote', [
            'titulo' => 'Subir varios archivos - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'categorias' => $categorias,
            'carpetas' => $carpetas,
            'extensiones' => Archivo::EXTENSIONES,
            'maxLote' => self::MAX_LOTE,
            'maxBytes' => Archivo::MAX_BYTES,
            'maxLoteBytes' => self::MAX_LOTE_BYTES,
            'fechaHoy' => date('Y-m-d'),
            'carpetaInicial' => $carpeta,
        ]);
    }

    /** Id de una categoría o carpeta solo si pertenece al proyecto */
    private static function idEnLista($valor, array $lista): ?int
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if (!$id) return null;
        foreach ($lista as $x) if ((int)$x->id === (int)$id) return (int)$id;
        return null;
    }

    private static function fechaISO($valor): ?string
    {
        if (!is_string($valor)) return null;
        $v = trim($valor);
        $f = \DateTime::createFromFormat('Y-m-d', $v);
        return $f && $f->format('Y-m-d') === $v ? $v : null;
    }

    /** Nombre visible a partir del nombre original (sin la extensión) */
    private static function nombreDesdeArchivo(string $archivo): string
    {
        $base = basename($archivo);
        $nombre = pathinfo($base, PATHINFO_FILENAME);
        return mb_substr($nombre !== '' ? $nombre : $base, 0, 200);
    }

    private static function responderLote(bool $ok, string $texto, array $detalles = []): void
    {
        $_SESSION['archivo_msg'] = ['ok' => $ok, 'texto' => $texto, 'detalles' => $detalles];
    }

    private static function guardarLote($proyecto, array $categorias, array $carpetas): void
    {
        $pid = (int)$proyecto->id;
        $f = $_FILES['archivos'] ?? null;
        if (!$f || !is_array($f['name'] ?? null) || !count($f['name'])) {
            self::responderLote(false, 'No llegó ningún archivo. Selecciona al menos uno.');
            header('Location: /archivos/lote?proyecto=' . $pid);
            exit;
        }
        $total = count($f['name']);
        if ($total > self::MAX_LOTE) {
            self::responderLote(false, 'Puedes subir como máximo ' . self::MAX_LOTE . ' archivos por lote.');
            header('Location: /archivos/lote?proyecto=' . $pid);
            exit;
        }
        $bytes = 0;
        foreach ((array)$f['size'] as $b) $bytes += (int)$b;
        if ($bytes > self::MAX_LOTE_BYTES) {
            self::responderLote(false, 'El lote supera el máximo de ' . \Classes\Almacenamiento::formato(self::MAX_LOTE_BYTES) . '.');
            header('Location: /archivos/lote?proyecto=' . $pid);
            exit;
        }
        $disponible = \Classes\Almacenamiento::disponible($pid);
        if ($bytes > $disponible) {
            self::responderLote(false, 'No hay espacio suficiente: el lote ocupa ' . \Classes\Almacenamiento::formato($bytes) . ' y quedan ' . \Classes\Almacenamiento::formato($disponible) . '.');
            header('Location: /archivos/lote?proyecto=' . $pid);
            exit;
        }

        $nombres = (array)($_POST['nombre'] ?? []);
        $fechas = (array)($_POST['fecha'] ?? []);
        $cats = (array)($_POST['categoria_id'] ?? []);
        $cars = (array)($_POST['carpeta_id'] ?? []);
        $revs = (array)($_POST['numero_revision'] ?? []);
        $aplicarFecha = isset($_POST['aplicar_fecha']);
        $aplicarCategoria = isset($_POST['aplicar_categoria']);
        $aplicarCarpeta = isset($_POST['aplicar_carpeta']);
        $aplicarRevision = isset($_POST['aplicar_revision']);
        $nombreOriginal = isset($_POST['nombre_original']);
        $fechaComun = self::fechaISO($_POST['fecha_comun'] ?? '') ?: date('Y-m-d');
        $categoriaComun = self::idEnLista($_POST['categoria_comun'] ?? null, $categorias);
        $carpetaComun = self::idEnLista($_POST['carpeta_comun'] ?? null, $carpetas);
        $revisionComun = mb_substr(trim(is_string($_POST['revision_comun'] ?? null) ? $_POST['revision_comun'] : ''), 0, 30);
        $fallos = [];
        $subidos = 0;

        for ($i = 0; $i < $total; $i++) {
            $original = is_string($f['name'][$i] ?? null) ? $f['name'][$i] : '';
            if ($original === '') continue;
            $error = (int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            $motivos = [];
            if ($error !== UPLOAD_ERR_OK) {
                $motivos[] = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'supera el límite del servidor' : 'no se pudo recibir';
            }
            $tmp = is_string($f['tmp_name'][$i] ?? null) ? $f['tmp_name'][$i] : '';
            $size = (int)($f['size'][$i] ?? 0);
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            if (!$motivos && !is_uploaded_file($tmp)) $motivos[] = 'no llegó al servidor';
            if (!$motivos && !in_array($ext, Archivo::EXTENSIONES, true)) $motivos[] = 'tipo no permitido (.' . $ext . ')';
            if (!$motivos && $size <= 0) $motivos[] = 'archivo vacío';
            if (!$motivos && $size > Archivo::MAX_BYTES) $motivos[] = 'supera ' . \Classes\Almacenamiento::formato(Archivo::MAX_BYTES);
            if (!$motivos && $size > $disponible) $motivos[] = 'no hay espacio suficiente en el proyecto';

            $fecha = $aplicarFecha ? $fechaComun : (is_string($fechas[$i] ?? null) ? trim($fechas[$i]) : '');
            if ($fecha === '') $fecha = date('Y-m-d');
            elseif (!self::fechaISO($fecha)) $motivos[] = 'la fecha no es válida';

            $nombre = $nombreOriginal ? self::nombreDesdeArchivo($original) : trim(is_string($nombres[$i] ?? null) ? $nombres[$i] : '');
            if ($nombre === '') $nombre = self::nombreDesdeArchivo($original);
            if (mb_strlen($nombre) > 200) $motivos[] = 'el nombre supera 200 caracteres';

            $cat = $aplicarCategoria ? $categoriaComun : self::idEnLista($cats[$i] ?? null, $categorias);
            $car = $aplicarCarpeta ? $carpetaComun : self::idEnLista($cars[$i] ?? null, $carpetas);
            $rev = $aplicarRevision ? $revisionComun : mb_substr(trim(is_string($revs[$i] ?? null) ? $revs[$i] : ''), 0, 30);

            if (!$motivos) foreach (\Classes\EscanerArchivos::revisar($tmp, $original) as $m) $motivos[] = $m;
            if ($motivos) { $fallos[] = $original . ': ' . implode(' ', $motivos); continue; }

            $guardado = Archivo::guardarArchivoSubido($pid, $tmp, $ext);
            if (!$guardado) { $fallos[] = $original . ': no se pudo guardar en el servidor'; continue; }
            $a = new Archivo([
                'proyecto_id' => $pid,
                'subido_por' => (int)$_SESSION['id'],
                'categoria_id' => $cat,
                'carpeta_id' => $car,
                'fecha' => $fecha,
                'nombre' => $nombre,
                'numero_revision' => $rev,
                'archivo_original' => mb_substr(basename($original), 0, 255),
                'archivo_guardado' => $guardado,
                'tamano' => $size,
            ]);
            $alertas = $a->validar();
            if ($alertas) {
                @unlink(Archivo::carpeta($pid) . '/' . $guardado);
                $fallos[] = $original . ': ' . implode(' ', $alertas['error'] ?? ['datos no válidos']);
                continue;
            }
            $r = $a->guardar();
            if (empty($r['resultado'])) {
                @unlink(Archivo::carpeta($pid) . '/' . $guardado);
                $fallos[] = $original . ': no se pudo registrar en la base de datos';
                continue;
            }
            $subidos++;
            $disponible -= $size;
        }

        $texto = $subidos > 0 ? 'Se subieron ' . $subidos . ' archivo' . ($subidos === 1 ? '' : 's') . '.' : 'No se pudo subir ningún archivo.';
        if ($fallos) $texto .= ' ' . count($fallos) . ' no se pudo subir' . (count($fallos) === 1 ? '' : 'n') . '.';
        self::responderLote($subidos > 0 && !$fallos, $texto, $fallos);
        header('Location: /archivos?proyecto=' . $pid . ($subidos ? '&ok=1' : ''));
        exit;
    }

    private static function formulario(Router $router, Archivo $a)
    {
        $proyecto = proyecto_de_peticion(true);
        if ($a->id && !puede_gestionar_archivo($a, $proyecto)) {
            header('Location: /archivos?proyecto=' . $proyecto->id);
            exit;
        }
        $categorias = ArchivoCategoria::deProyecto((int)$proyecto->id);
        $carpetas = ArchivoCarpeta::deProyecto((int)$proyecto->id);
        $alertas = [];
        if (!self::esPost() && !$a->id && ($c = filter_var($_GET['carpeta'] ?? null, FILTER_VALIDATE_INT))) $a->carpeta_id = $c;   // subir desde una carpeta
        if (self::esPost()) {
            $a->proyecto_id = $proyecto->id;
            $a->fecha = trim($_POST['fecha'] ?? '');
            $a->nombre = trim($_POST['nombre'] ?? '');
            $a->numero_revision = trim($_POST['numero_revision'] ?? '');
            $cat = filter_var($_POST['categoria_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $a->categoria_id = $cat && in_array($cat, array_map(fn($c) => (int)$c->id, $categorias), true) ? $cat : null;
            $car = filter_var($_POST['carpeta_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $a->carpeta_id = $car && in_array($car, array_map(fn($c) => (int)$c->id, $carpetas), true) ? $car : null;
            $alertas = $a->validar();

            $f = $_FILES['archivo'] ?? null;
            $hay = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']);
            if (!$a->id && !$hay) $alertas['error'][] = 'Selecciona el archivo a subir';
            if ($hay) {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, Archivo::EXTENSIONES, true)) $alertas['error'][] = 'Tipo de archivo no permitido (.' . s($ext) . ')';
                if ($f['size'] > Archivo::MAX_BYTES) $alertas['error'][] = 'El archivo supera los 100 MB';
                elseif (!\Classes\Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) $alertas['error'][] = \Classes\Almacenamiento::mensajeLleno((int)$proyecto->id);
                elseif (in_array($ext, Archivo::EXTENSIONES, true)) {
                    foreach (\Classes\EscanerArchivos::revisar($f['tmp_name'], $f['name']) as $motivo) $alertas['error'][] = $motivo;
                }
            } elseif (!empty($f['error']) && $f['error'] !== UPLOAD_ERR_NO_FILE) {
                $alertas['error'][] = 'No se pudo subir el archivo (¿supera el límite del servidor?)';
            }

            if (!$alertas) {
                if ($hay) {
                    $dir = Archivo::carpeta((int)$proyecto->id);
                    $guardado = Archivo::guardarArchivoSubido((int)$proyecto->id, $f['tmp_name'], $ext);
                    if ($guardado) {
                        if ($a->archivo_guardado) @unlink($dir . '/' . basename($a->archivo_guardado));   // reemplazo
                        $a->archivo_guardado = $guardado;
                        $a->archivo_original = mb_substr(basename($f['name']), 0, 255);
                        $a->tamano = (int)$f['size'];
                    } else {
                        $alertas['error'][] = 'No se pudo guardar el archivo en el servidor';
                    }
                }
                if (!$alertas) {
                    if (!$a->id) $a->subido_por = (int)$_SESSION['id'];   // quién lo subió (para los permisos de colaborador)
                    $a->guardar();
                    header('Location: /archivos?proyecto=' . $proyecto->id . '&ok=1');
                    exit;
                }
            }
        }
        $router->render('archivos/formulario', [
            'titulo' => $a->id ? 'Editar archivo' : 'Nuevo archivo', 'proyecto' => $proyecto, 'archivo' => $a, 'alertas' => $alertas,
            'categorias' => $categorias, 'carpetas' => $carpetas, 'extensiones' => Archivo::EXTENSIONES,
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $a = Archivo::find(self::idPeticion());
        if ($a && (int)$a->proyecto_id === (int)$proyecto->id && puede_gestionar_archivo($a, $proyecto)) {
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
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Content-Disposition: attachment; filename="' . $nombre . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    /** Muestra el archivo en el navegador (PDF, imágenes, texto; la ofimática se convierte a PDF). Mismo permiso que descargar */
    public static function ver()
    {
        redirigir_si_no_auth();
        $a = Archivo::find(self::idPeticion());
        $proyecto = $a ? \Model\Proyecto::find($a->proyecto_id) : null;
        if (!$proyecto) { http_response_code(404); exit; }
        exigir_ver_proyecto($proyecto);
        if (!puede_descargar()) { http_response_code(403); exit('No autorizado'); }
        $ext = $a->extension();
        $tipo = \Classes\VistaArchivo::tipo($ext);
        $ruta = $a->ruta();
        if (!$tipo || !$ruta) { http_response_code(404); exit('Este archivo no se puede ver en el navegador'); }
        $mime = \Classes\VistaArchivo::mime($ext);
        if ($tipo === 'convertir') {
            $ruta = \Classes\VistaArchivo::pdfDe($ruta, $ext);
            if (!$ruta) { http_response_code(500); exit('No se pudo convertir el documento para verlo; descárgalo.'); }
            $mime = 'application/pdf';
        }
        $nombre = preg_replace('/[\r\n"\\\\\/]+/', '_', $tipo === 'convertir' ? pathinfo($a->archivo_original, PATHINFO_FILENAME) . '.pdf' : $a->archivo_original);
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        if ($mime !== 'application/pdf') header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");   // con sandbox el visor de PDF del navegador no abre
        header('Content-Disposition: inline; filename="' . $nombre . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    // ========================= ARCHIVERO (CARPETAS) =========================

    private static function volverAlArchivero(int $proyectoId, string $extra = '')
    {
        header('Location: /archivos?proyecto=' . $proyectoId . $extra);
        exit;
    }

    /** Crea una carpeta o, con `id`, la renombra */
    public static function carpetaGuardar()
    {
        $proyecto = proyecto_de_peticion(true);
        $c = self::idPeticion() ? ArchivoCarpeta::find(self::idPeticion()) : new ArchivoCarpeta();
        if (!$c || ($c->id && (int)$c->proyecto_id !== (int)$proyecto->id)) self::volverAlArchivero((int)$proyecto->id);
        $c->proyecto_id = $proyecto->id;
        $c->nombre = trim($_POST['nombre'] ?? '');
        $alertas = $c->validar();
        if ($alertas) self::volverAlArchivero((int)$proyecto->id, '&error=' . urlencode($alertas['error'][0]));
        $r = $c->guardar();
        $id = $c->id ?: ($r['id'] ?? 0);
        self::volverAlArchivero((int)$proyecto->id, '&carpeta=' . (int)$id . '&ok=1');
    }

    /** modo=solo: la carpeta desaparece y sus archivos quedan «sin carpeta»; modo=todo: también se borran sus archivos */
    public static function carpetaEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $c = ArchivoCarpeta::find(self::idPeticion());
        if ($c && (int)$c->proyecto_id === (int)$proyecto->id) {
            if (($_POST['modo'] ?? '') === 'todo') {
                foreach (Archivo::deProyecto((int)$proyecto->id, null, (int)$c->id) as $a) {
                    if ($r = $a->ruta()) @unlink($r);
                    $a->eliminar();
                }
            }
            $c->eliminar();   // ON DELETE SET NULL deja los archivos restantes sin carpeta
        }
        self::volverAlArchivero((int)$proyecto->id, '&ok=1');
    }

    /** Mueve varios archivos a una carpeta (carpeta_id vacío/0 = sin carpeta) */
    public static function mover()
    {
        $proyecto = proyecto_de_peticion(true);
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        $destino = filter_var($_POST['carpeta_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($destino) {
            $k = ArchivoCarpeta::find($destino);
            if (!$k || (int)$k->proyecto_id !== (int)$proyecto->id) $destino = null;
        }
        foreach ($ids as $id) {
            $a = Archivo::find($id);
            if ($a && (int)$a->proyecto_id === (int)$proyecto->id && puede_gestionar_archivo($a, $proyecto)) {
                $a->carpeta_id = $destino;
                $a->guardar();
            }
        }
        self::volverAlArchivero((int)$proyecto->id, '&ok=1' . ($destino ? '&carpeta=' . $destino : ''));
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
