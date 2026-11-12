<?php

namespace Controllers;

use MVC\Router;
use Classes\Conceptos;
use Classes\CargosProyecto;
use Classes\ImportadorConceptos;
use Classes\Exportadores\PresupuestoPdfExporter;
use Classes\Exportadores\PresupuestoSheetExporter;

/** Catálogo de conceptos del presupuesto: conceptos, ligas con actividades, importación y exportación */
class ConceptosController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $partidaFiltro = (int)($_GET['partida'] ?? 0);
        $busca = texto_param($_GET['q'] ?? '');
        $editar = null;
        if (!empty($_GET['editar'])) {
            $editar = Conceptos::obtener($pid, (int)$_GET['editar']);
            if ($editar) {
                foreach (Conceptos::deProyecto($pid) as $x) {
                    if ((int)$x['id'] === (int)$editar['id']) { $editar['programado_qty'] = (float)$x['programado_qty']; break; }
                }
            }
        }
        $router->render('conceptos/index', [
            'titulo' => 'Catálogo de conceptos - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'partidas' => $proyecto->getPartidas(),
            'lista' => Conceptos::deProyecto($pid, $partidaFiltro ?: null, $busca),
            'resumen' => Conceptos::resumenPartidas($pid),
            'cargos' => CargosProyecto::deProyecto($pid),
            'editar' => $editar,
            'partidaFiltro' => $partidaFiltro,
            'busca' => $busca,
            'nuevo' => isset($_GET['nuevo']) || $editar !== null,
            'presetPartida' => (int)($_GET['partida'] ?? 0),
            'ok' => texto_param($_GET['ok'] ?? ''),
            'error' => texto_param($_GET['error'] ?? ''),
        ]);
    }

    public static function guardar()
    {
        $proyecto = proyecto_de_peticion(true);
        [$ok, $msg, $id] = Conceptos::guardar((int)$proyecto->id, $_POST);
        self::volver((int)$proyecto->id, $ok ? '&ok=' . urlencode($msg) : '&error=' . urlencode($msg), $id);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /conceptos?proyecto=' . $proyecto->id); exit; }
        if (!puede_borrar_contenido($proyecto)) {
            self::volver((int)$proyecto->id, '&error=' . urlencode('Solo el dueño o el administrador pueden eliminar conceptos.'));
        }
        [$ok, $msg] = Conceptos::eliminar((int)$proyecto->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$proyecto->id, $ok ? '&ok=' . urlencode($msg) : '&error=' . urlencode($msg));
    }

    public static function asignar()
    {
        $proyecto = proyecto_de_peticion(true);
        $aid = (int)($_POST['actividad_id'] ?? 0);
        $cantidad = str_replace([',', ' '], ['', ''], (string)($_POST['cantidad'] ?? '0'));
        [$ok, $msg] = Conceptos::asignar((int)$proyecto->id, $aid, (int)($_POST['concepto_id'] ?? 0),
            is_numeric($cantidad) ? (float)$cantidad : 0, texto_param($_POST['motivo'] ?? ''));
        header('Location: /programa/actividades/editar?id=' . $aid . ($ok ? '&concepto_ok=1' : '&concepto_error=' . urlencode($msg)));
        exit;
    }

    public static function desasignar()
    {
        $proyecto = proyecto_de_peticion(true);
        $aid = (int)($_POST['actividad_id'] ?? 0);
        [$ok, $msg] = Conceptos::desasignar((int)$proyecto->id, $aid, (int)($_POST['concepto_id'] ?? 0));
        header('Location: /programa/actividades/editar?id=' . $aid . ($ok ? '&concepto_ok=1' : '&concepto_error=' . urlencode($msg)));
        exit;
    }

    public static function repartir()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /conceptos?proyecto=' . $proyecto->id); exit; }
        [$ok, $msg] = Conceptos::repartir((int)$proyecto->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$proyecto->id, $ok ? '&ok=' . urlencode($msg) : '&error=' . urlencode($msg));
    }

    public static function crearActividad()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /conceptos?proyecto=' . $proyecto->id); exit; }
        try {
            [$ok, $msg, $aid] = Conceptos::crearActividad((int)$proyecto->id, (int)($_POST['id'] ?? 0));
        } catch (\Throwable $e) {
            self::fallo($e, '/conceptos?proyecto=' . (int)$proyecto->id);
        }
        if ($ok && $aid) {
            header('Location: /programa/actividades/editar?id=' . $aid . '&concepto_ok=1');
            exit;
        }
        self::volver((int)$proyecto->id, '&error=' . urlencode($msg));
    }

    public static function cargos()
    {
        $proyecto = proyecto_de_peticion(true);
        [$ok, $msg] = CargosProyecto::guardar((int)$proyecto->id, $_POST);
        header('Location: /costos?proyecto=' . $proyecto->id . '&pestana=resumen' . ($ok ? '&ok=' . urlencode($msg) : '&error=' . urlencode($msg)));
        exit;
    }

    // ---------- importar ----------

    public static function importar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $datos = null;
        $error = texto_param($_GET['error'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                [$ruta, $ext, $token] = self::subirArchivo();
                $lectura = ImportadorConceptos::leer($ruta, $ext);
                $map = ImportadorConceptos::mapear($lectura['encabezados']);
                $datos = [
                    'token' => $token, 'encabezados' => $lectura['encabezados'], 'map' => $map,
                    'filas' => $lectura['filas'], 'avisos' => $lectura['avisos'],
                    'analisis' => ImportadorConceptos::analizar($lectura['filas'], $map),
                ];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $router->render('conceptos/importar', [
            'titulo' => 'Importar catálogo - ' . $proyecto->nombre,
            'proyecto' => $proyecto, 'datos' => $datos, 'error' => $error, 'resultado' => null,
            'campos' => ImportadorConceptos::CAMPOS,
        ]);
    }

    public static function aplicarImportacion(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /conceptos/importar?proyecto=' . $proyecto->id); exit; }
        $token = (string)($_POST['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            header('Location: /conceptos/importar?proyecto=' . $proyecto->id . '&error=' . urlencode('La carga caducó; vuelve a subir el archivo'));
            exit;
        }
        $map = [];
        foreach (array_keys(ImportadorConceptos::CAMPOS) as $campo) {
            $v = (string)($_POST['map'][$campo] ?? '');
            if ($v !== '' && ctype_digit($v)) $map[$campo] = $v;
        }
        $resultado = null;
        $error = '';
        try {
            $ruta = null;
            $ext = '';
            foreach (['csv', 'xlsx', 'ods'] as $e) {
                $r = __DIR__ . '/../storage/import/conceptos_' . $token . '.' . $e;
                if (is_file($r)) { $ruta = $r; $ext = $e; break; }
            }
            if (!$ruta) throw new \RuntimeException('No se encontró el archivo cargado; vuelve a subirlo');
            @set_time_limit(300);
            $lectura = ImportadorConceptos::leer($ruta, $ext);
            $resultado = ImportadorConceptos::aplicar((int)$proyecto->id, $lectura['filas'], $map);
            @unlink($ruta);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $router->render('conceptos/importar', [
            'titulo' => 'Importar catálogo - ' . $proyecto->nombre,
            'proyecto' => $proyecto, 'datos' => null, 'error' => $error, 'resultado' => $resultado,
            'campos' => ImportadorConceptos::CAMPOS,
        ]);
    }

    /** @return array{0:string,1:string,2:string} ruta, extensión y token del archivo temporal */
    private static function subirArchivo(): array
    {
        if (empty($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Elige un archivo CSV, XLSX u ODS');
        }
        $nombre = (string)($_FILES['archivo']['name'] ?? '');
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'ods'], true)) throw new \RuntimeException('Usa un archivo .csv, .xlsx o .ods');
        if ((int)$_FILES['archivo']['size'] > 12 * 1024 * 1024) throw new \RuntimeException('El archivo supera los 12 MB');
        $tmp = (string)$_FILES['archivo']['tmp_name'];
        $problemas = \Classes\EscanerArchivos::revisar($tmp, $nombre);
        if ($problemas) throw new \RuntimeException(implode(' ', $problemas));
        $token = bin2hex(random_bytes(16));
        $carpeta = __DIR__ . '/../storage/import';
        if (!is_dir($carpeta)) mkdir($carpeta, 0775, true);
        foreach (glob($carpeta . '/conceptos_*.{csv,xlsx,ods}', GLOB_BRACE) ?: [] as $viejo) {
            if (is_file($viejo) && filemtime($viejo) < time() - 86400) @unlink($viejo);   // cargas abandonadas
        }
        $destino = $carpeta . '/conceptos_' . $token . '.' . $ext;
        if (!move_uploaded_file($tmp, $destino)) throw new \RuntimeException('No se pudo guardar el archivo');
        return [$destino, $ext, $token];
    }

    // ---------- exportar ----------

    public static function exportar()
    {
        $proyecto = proyecto_de_peticion(true);
        if (!puede_descargar()) {
            header('Location: /conceptos?proyecto=' . $proyecto->id . '&error=' . urlencode('Tu plan no permite descargar archivos'));
            exit;
        }
        $formato = in_array($_GET['formato'] ?? '', ['pdf', 'xlsx', 'ods'], true) ? $_GET['formato'] : 'pdf';
        try {
            $datos = Conceptos::presupuesto((int)$proyecto->id);
            if (!$datos['grupos'] && !$datos['sinPartida']) {
                header('Location: /conceptos?proyecto=' . $proyecto->id . '&error=' . urlencode('El catálogo está vacío; agrega o importa conceptos'));
                exit;
            }
            $exporter = $formato === 'pdf'
                ? new PresupuestoPdfExporter($proyecto)
                : (new PresupuestoSheetExporter($proyecto))->setFormato($formato);
            $ruta = $exporter->setDatos($datos)->generar('Presupuesto');
        } catch (\Throwable $e) {
            self::fallo($e, '/conceptos?proyecto=' . (int)$proyecto->id);
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        @unlink($ruta);
        exit;
    }

    /** Registra el error real y vuelve con un mensaje amable (nunca la pantalla genérica) */
    private static function fallo(\Throwable $e, string $destino): void
    {
        $ref = bin2hex(random_bytes(4));
        error_log("[conceptos $ref] " . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        $sep = str_contains($destino, '?') ? '&' : '?';
        header('Location: ' . $destino . $sep . 'error=' . urlencode('No se pudo completar (ref. ' . $ref . '). Revisa que la migración del catálogo esté aplicada con php bin/reparar_conceptos.php.'));
        exit;
    }

    private static function volver(int $pid, string $extra = '', int $editar = 0): void
    {
        header('Location: /conceptos?proyecto=' . $pid . ($editar ? '&editar=' . $editar : '') . $extra);
        exit;
    }
}
