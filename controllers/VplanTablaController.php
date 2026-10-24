<?php

namespace Controllers;

use MVC\Router;
use Model\VplanTabla;
use Model\SheetflowHoja;
use Classes\Sheetflow;
use Classes\TablaDeDocumento;
use Classes\Auditoria;

/** Catálogo de tablas adicionales de vPlan (punch list, restricciones, actividades secundarias, otro) */
class VplanTablaController
{
    private static function tablaDe($proyecto): ?VplanTabla
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $t = VplanTabla::find(filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT));
        return $t && (int)$t->proyecto_id === (int)$proyecto->id ? $t : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $tablas = VplanTabla::deProyecto((int)$proyecto->id);
        $conteos = [];
        foreach ($tablas as $t) $conteos[$t->id] = count($t->filas());
        $router->render('vplan/tablas-index', [
            'titulo' => 'Tablas adicionales - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'tablas' => $tablas, 'conteos' => $conteos, 'hojasSheetflow' => \Model\SheetflowHoja::resumen((int)$proyecto->id),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $tabla = new VplanTabla(['proyecto_id' => $proyecto->id]);
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tabla->titulo = trim($_POST['titulo'] ?? '');
            $tabla->nombre_interno = mb_substr(trim($_POST['nombre_interno'] ?? ''), 0, 150) ?: null;
            $tabla->tipo = $_POST['tipo'] ?? 'punchlist';
            if ($tabla->esPunchVista()) $tabla->punch_config = json_encode(\Classes\PunchVista::desdePost((array)($_POST['punch'] ?? [])), JSON_UNESCAPED_UNICODE);
            $tabla->columnas_json = self::columnas();
            $alertas = $tabla->validar();
            if ($tabla->tipo === 'otro' && !$tabla->columnasOtro()) $alertas['error'][] = 'Define al menos una columna para la tabla "Otro"';
            if (!$alertas) {
                $r = $tabla->guardar();
                if (empty($tabla->id)) $tabla->id = $r['id'];
                if ($h = Sheetflow::deTablaVplan($tabla, $_SESSION['id'] ?? null)) {   // las tablas libres nacen como hoja de SheetFlow: se llenan allá
                    header('Location: /sheetflow/abrir?proyecto=' . $proyecto->id . '&id=' . $h->id);
                    exit;
                }
                header('Location: /vplan/tablas/editar?proyecto=' . $proyecto->id . '&id=' . $r['id']);
                exit;
            }
        }
        $router->render('vplan/tabla-formulario', [
            'titulo' => 'Nueva tabla', 'proyecto' => $proyecto, 'tabla' => $tabla, 'alertas' => $alertas, 'filas' => [],
        ]);
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $tabla = self::tablaDe($proyecto);
        if (!$tabla) {
            header('Location: /vplan/tablas?proyecto=' . $proyecto->id);
            exit;
        }
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tabla->titulo = trim($_POST['titulo'] ?? '');
            $tabla->nombre_interno = mb_substr(trim($_POST['nombre_interno'] ?? ''), 0, 150) ?: null;
            if ($tabla->tipo === 'otro') $tabla->columnas_json = self::columnas();
            if ($tabla->esPunchVista()) $tabla->punch_config = json_encode(\Classes\PunchVista::desdePost((array)($_POST['punch'] ?? [])), JSON_UNESCAPED_UNICODE);
            $alertas = $tabla->validar();
            if ($tabla->tipo === 'otro' && !$tabla->columnasOtro()) $alertas['error'][] = 'Define al menos una columna';
            if (!$alertas) {
                $tabla->guardar();
                if (!$tabla->hoja() && !$tabla->esPunchVista()) $tabla->guardarFilas(self::limpiarFilas($tabla, $_POST['filas'] ?? []));   // vinculada: los renglones son los de SheetFlow
                header('Location: /vplan/tablas/editar?proyecto=' . $proyecto->id . '&id=' . $tabla->id . '&ok=1');
                exit;
            }
        }
        $router->render('vplan/tabla-formulario', [
            'titulo' => 'Editar tabla', 'proyecto' => $proyecto, 'tabla' => $tabla, 'alertas' => $alertas, 'filas' => $tabla->filas(),
        ]);
    }

    /** Pasa a SheetFlow una tabla «otro» con datos propios (con `todas=1`, todas las del proyecto que falten) */
    public static function aSheetflow()
    {
        $proyecto = proyecto_de_peticion(true);
        $lista = !empty($_POST['todas']) ? VplanTabla::deProyecto((int)$proyecto->id) : array_filter([self::tablaDe($proyecto)]);
        $n = 0;
        $ultima = null;
        foreach ($lista as $t) if ($h = Sheetflow::deTablaVplan($t, $_SESSION['id'] ?? null)) { $n++; $ultima = $h; }
        if (empty($_POST['todas']) && $ultima) {
            header('Location: /sheetflow/abrir?proyecto=' . $proyecto->id . '&id=' . $ultima->id);
            exit;
        }
        header('Location: /vplan/tablas?proyecto=' . $proyecto->id . '&convertidas=' . $n);
        exit;
    }

    public static function duplicar()
    {
        $proyecto = proyecto_de_peticion(true);
        $copia = ($t = self::tablaDe($proyecto)) ? $t->duplicar() : null;
        header('Location: ' . ($copia ? '/vplan/tablas/editar?proyecto=' . $proyecto->id . '&id=' . $copia->id : '/vplan/tablas?proyecto=' . $proyecto->id));
        exit;
    }

    /** Convierte una tabla manual de punch en una vista del módulo Punch: cada renglón pasa a ser un pendiente (inicio/% se pierden: el % pasa al estado) */
    public static function convertirPunch()
    {
        $proyecto = proyecto_de_peticion(true);
        $t = self::tablaDe($proyecto);
        if (!$t || $t->tipo !== 'punch') {
            header('Location: /vplan/tablas?proyecto=' . $proyecto->id);
            exit;
        }
        $ids = [];
        foreach ($t->filas() as $f) {
            $titulo = trim((string)($f['descripcion'] ?? ''));
            if ($titulo === '') continue;
            $pct = (int)($f['pct'] ?? 0);
            [$ok, , $id] = \Classes\Punch::guardar((int)$proyecto->id, ['titulo' => $titulo, 'fecha_limite' => $f['fin'] ?? ''], null, 'manual');
            if (!$ok) continue;
            if ($pct >= 100) \Classes\Punch::estado($proyecto, $id, 'cerrado');
            elseif ($pct > 0) \Classes\Punch::estado($proyecto, $id, 'en_proceso');
            $ids[] = $id;
        }
        $t->tipo = 'punchlist';
        $t->punch_config = json_encode(\Classes\PunchVista::normalizar(['columnas' => ['titulo', 'fecha_limite', 'estado'], 'estado' => 'todos', 'limite' => 200, 'ids' => $ids]), JSON_UNESCAPED_UNICODE);
        $t->guardar();
        \Classes\Auditoria::registrar((int)$proyecto->id, 'punch', 'aplicado', 'punch_tabla_vplan', 'Tabla manual «' . mb_substr($t->titulo, 0, 80) . '» convertida a vista del Punch list (' . count($ids) . ' pendientes)', [], ['tabla' => (int)$t->id], (int)$t->id);
        header('Location: /vplan/tablas/editar?proyecto=' . $proyecto->id . '&id=' . $t->id . '&ok=1');
        exit;
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($t = self::tablaDe($proyecto)) $t->eliminar();
        header('Location: /vplan/tablas?proyecto=' . $proyecto->id);
        exit;
    }

    private static function columnas(): string
    {
        $c = array_slice(array_values(array_filter(array_map(fn($x) => mb_substr(trim((string)$x), 0, 40), (array)($_POST['columnas'] ?? [])), fn($x) => $x !== '')), 0, 8);
        return json_encode($c, JSON_UNESCAPED_UNICODE);
    }

    private static function limpiarCampo(string $tipo, string $v): string
    {
        $v = trim($v);
        if ($tipo === 'date') {
            $d = \DateTime::createFromFormat('Y-m-d', $v);
            return $d && $d->format('Y-m-d') === $v ? $v : '';
        }
        if ($tipo === 'number') return $v === '' ? '' : (string)max(0, min(100, (int)$v));
        if ($tipo === 'estado') return isset(VplanTabla::ESTADOS[$v]) ? $v : 'proceso';
        return mb_substr($v, 0, 300);
    }

    /** Edición en línea desde la lámina: set (un campo), add (renglón nuevo), del (quitar renglón) */
    public static function apiFila()
    {
        header('Content-Type: application/json');
        $t = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? VplanTabla::find(filter_var($_POST['tabla'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $t ? \Model\Proyecto::find($t->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $campos = $t->campos();
        $filas = $t->filas();
        $accion = $_POST['accion'] ?? '';
        $i = filter_var($_POST['indice'] ?? null, FILTER_VALIDATE_INT);
        $fallo = function (string $m) { http_response_code(422); echo json_encode(['success' => false, 'error' => $m]); exit; };

        $hoja = $t->hoja();
        if ($t->esPunchVista() && in_array($accion, ['add', 'del'], true)) $fallo('Los pendientes se agregan y se quitan en el módulo Punch list');
        if ($hoja && $accion === 'add' && \Classes\Sheetflow::matriz($hoja)['totales']) $fallo('La tabla de SheetFlow tiene fila de totales: agrega renglones en SheetFlow');
        if ($accion === 'add') {
            if (count($filas) >= 200) $fallo('Máximo 200 renglones');
            $nueva = [];
            foreach ($campos as $k => [$etq, $tipo]) $nueva[$k] = $tipo === 'estado' ? 'proceso' : '';
            $filas[] = $nueva;
            if (!$hoja) $t->guardarFilas($filas);   // vinculada: el renglón existe en SheetFlow al escribir en él
            $idx = count($filas) - 1;
            $base = max(0, min(500, (int)($_POST['base'] ?? 0)));
            $fila = $nueva + ['n' => ($t->tipo === 'restricciones' ? $base : 0) + $idx + 1, '_i' => $idx];
            $bloque = ['tabla' => $t, 'campos' => $campos];
            $puedeEditar = true;
            ob_start();
            include __DIR__ . '/../views/vplan/_fila-adicional.php';
            echo json_encode(['success' => true, 'html' => ob_get_clean(), 'indice' => $idx]);
            exit;
        }
        if ($i === false || $i === null || $i < 0 || $i >= count($filas)) $fallo('Renglón no válido');
        if ($accion === 'del') {
            if ($hoja && ($m = \Classes\Sheetflow::quitarFila($hoja, $i))) $fallo($m);
            array_splice($filas, $i, 1);
            if (!$hoja) $t->guardarFilas($filas);
            echo json_encode(['success' => true]);
            exit;
        }
        $campo = $_POST['campo'] ?? '';
        if ($accion !== 'set' || !isset($campos[$campo])) $fallo('Campo no válido');
        $valor = self::limpiarCampo($campos[$campo][1], (string)($_POST['valor'] ?? ''));
        if ($t->esPunchVista()) {   // se escribe en el pendiente del Punch list
            $clave = $t->configPunch()['columnas'][(int)substr($campo, 1)] ?? '';
            if ($m = \Classes\PunchVista::cambiar($proyecto, (int)($filas[$i]['_pid'] ?? 0), $clave, $valor)) $fallo($m);
            echo json_encode(['success' => true, 'valor' => $valor]);
            exit;
        }
        if ($hoja) {   // vinculada: se escribe en SheetFlow y se devuelve lo que ahí se muestra
            if ($m = \Classes\Sheetflow::cambiarCelda($hoja, $i, (int)substr($campo, 1), $valor)) $fallo($m);
            $valor = \Classes\Sheetflow::filasVplan(\Model\SheetflowHoja::find($hoja->id), count($campos))[$i][$campo] ?? $valor;
        } else {
            $filas[$i][$campo] = $valor;
            $t->guardarFilas($filas);
        }
        echo json_encode(['success' => true, 'valor' => $valor]);
        exit;
    }

    /** Valida cada campo según su tipo y descarta filas vacías */
    private static function limpiarFilas(VplanTabla $t, array $filas): array
    {
        $campos = $t->campos();
        $out = [];
        foreach ($filas as $f) {
            if (!is_array($f)) continue;
            $fila = [];
            foreach ($campos as $k => [$etq, $tipo]) {
                $fila[$k] = self::limpiarCampo($tipo, (string)($f[$k] ?? ''));
            }
            // Fila vacía: ningún campo con contenido (el estado por sí solo no cuenta)
            $contenido = array_filter($fila, fn($v, $k) => $v !== '' && $k !== 'estado', ARRAY_FILTER_USE_BOTH);
            if ($contenido) $out[] = $fila;
        }
        return $out;
    }

    // ---------- Tabla en base a un documento ----------

    /** Proyecto de una petición de la API (solo quien puede gestionarlo) */
    private static function proyectoApi(): \Model\Proyecto
    {
        $p = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? \Model\Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p || !puede_gestionar_proyecto($p)) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    public static function documento(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $router->render('vplan/tabla-documento', [
            'titulo' => 'Tabla en base a documento', 'proyecto' => $proyecto,
            'ocr' => TablaDeDocumento::ocrDisponible(), 'esAdmin' => is_admin(),
        ]);
    }

    /** Lee el documento con el motor (sin IA) y devuelve la tabla propuesta; no guarda nada */
    public static function apiDocumentoLeer()
    {
        self::proyectoApi();
        $f = $_FILES['archivo'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) json_respuesta(['success' => false, 'error' => 'No se pudo subir el archivo'], 422);
        if ($f['size'] > TablaDeDocumento::MAX_BYTES) json_respuesta(['success' => false, 'error' => 'El archivo es demasiado grande (máx. 25 MB)'], 422);
        try {
            $r = TablaDeDocumento::leer($f['tmp_name'], (string)$f['name']);
        } catch (\RuntimeException $e) {
            json_respuesta(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('Tabla en base a documento: ' . $e->getMessage());
            json_respuesta(['success' => false, 'error' => 'No se pudo leer el documento'], 422);
        }
        $d = $r['filas'] ? Sheetflow::deMatriz($r['filas']) : null;
        json_respuesta([
            'success' => true, 'metodo' => $r['metodo'], 'avisos' => $r['avisos'], 'imagen' => $r['imagen'],
            'filas' => $r['filas'] ? array_merge([array_map(fn($c) => $c['n'], $d['cols'])], $d['rows']) : [],
            'texto' => is_admin() ? $r['texto'] : '',   // el texto sirve a la IA del administrador (app de escritorio)
        ]);
    }

    /** Texto con columnas alineadas (el OCR de la app de escritorio) → tabla propuesta; no guarda nada */
    public static function apiDocumentoTexto()
    {
        self::proyectoApi();
        $texto = (string)($_POST['texto'] ?? '');
        if (trim($texto) === '' || strlen($texto) > 400_000) json_respuesta(['success' => false, 'error' => 'No hay texto que interpretar'], 422);
        $avisos = [];
        $filas = TablaDeDocumento::dePlano($texto, $avisos);
        $d = $filas ? Sheetflow::deMatriz($filas) : null;
        json_respuesta([
            'success' => true, 'metodo' => 'OCR', 'avisos' => array_merge($avisos, ['Texto leído con OCR: revisa las celdas antes de crear la tabla.']), 'imagen' => false,
            'filas' => $d ? array_merge([array_map(fn($c) => $c['n'], $d['cols'])], $d['rows']) : [], 'texto' => '',
        ]);
    }

    /** Crea la tabla de SheetFlow con la matriz ya revisada y la deja en el catálogo de vPlan */
    public static function apiDocumentoCrear()
    {
        $proyecto = self::proyectoApi();
        $metodo = ($_POST['metodo'] ?? '') === 'ia' ? 'ia' : 'motor';
        if ($metodo === 'ia' && !is_admin()) json_respuesta(['success' => false, 'error' => 'La IA es solo para el administrador'], 403);
        $filas = json_decode((string)($_POST['filas'] ?? ''), true);
        if (!is_array($filas) || strlen((string)$_POST['filas']) > Sheetflow::MAX_BYTES) json_respuesta(['success' => false, 'error' => 'La tabla no es válida'], 422);
        $filas = array_values(array_filter(array_map(fn($f) => is_array($f) ? array_map(fn($v) => is_scalar($v) ? (string)$v : '', array_values($f)) : [], $filas)));
        try {
            $datos = Sheetflow::deMatriz($filas);
        } catch (\RuntimeException $e) {
            json_respuesta(['success' => false, 'error' => 'La tabla está vacía'], 422);
        }
        $nombre = mb_substr(trim((string)($_POST['nombre'] ?? '')), 0, 140) ?: 'Tabla de documento';
        $h = new SheetflowHoja(['proyecto_id' => $proyecto->id, 'nombre' => $nombre, 'fecha' => date('Y-m-d'), 'departamento' => '', 'descripcion' => mb_substr('Creada desde ' . trim((string)($_POST['origen'] ?? 'un documento')), 0, 500), 'creado_por' => $_SESSION['id'] ?? null]);
        if ($alertas = $h->validar()) json_respuesta(['success' => false, 'error' => $alertas['error'][0]], 422);
        $h->datos = json_encode(Sheetflow::normalizar($datos), JSON_UNESCAPED_UNICODE);
        // Creada con IA: queda en la auditoría de la IA, no como cambio manual pendiente
        $h->id = \Classes\HistorialCambios::silenciar(fn() => $h->guardar()['id']);
        $t = \Classes\HistorialCambios::silenciar(fn() => Sheetflow::aVplan($h));
        if ($metodo === 'ia') {
            Auditoria::registrar((int)$proyecto->id, 'tabla-documento', 'aplicado', 'tabla_de_documento', 'Tabla «' . $nombre . '» creada con IA a partir de ' . mb_substr((string)($_POST['origen'] ?? 'un documento'), 0, 120), null, ['tabla' => (int)$t->id, 'hoja' => (int)$h->id, 'renglones' => count($datos['rows'])], (int)$t->id, mb_substr((string)($_POST['modelo'] ?? ''), 0, 150), ['vplan_tabla', (int)$t->id, $nombre]);
        }
        json_respuesta(['success' => true, 'url' => '/vplan/tablas/editar?proyecto=' . $proyecto->id . '&id=' . $t->id . '&ok=1', 'tabla' => (int)$t->id]);
    }
}
