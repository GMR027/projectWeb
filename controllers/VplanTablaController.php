<?php

namespace Controllers;

use MVC\Router;
use Model\VplanTabla;

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
            $tabla->tipo = $_POST['tipo'] ?? 'punch';
            $tabla->columnas_json = self::columnas();
            $alertas = $tabla->validar();
            if ($tabla->tipo === 'otro' && !$tabla->columnasOtro()) $alertas['error'][] = 'Define al menos una columna para la tabla "Otro"';
            if (!$alertas) {
                $r = $tabla->guardar();
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
            $alertas = $tabla->validar();
            if ($tabla->tipo === 'otro' && !$tabla->columnasOtro()) $alertas['error'][] = 'Define al menos una columna';
            if (!$alertas) {
                $tabla->guardar();
                if (!$tabla->hoja()) $tabla->guardarFilas(self::limpiarFilas($tabla, $_POST['filas'] ?? []));   // vinculada: los renglones son los de SheetFlow
                header('Location: /vplan/tablas/editar?proyecto=' . $proyecto->id . '&id=' . $tabla->id . '&ok=1');
                exit;
            }
        }
        $router->render('vplan/tabla-formulario', [
            'titulo' => 'Editar tabla', 'proyecto' => $proyecto, 'tabla' => $tabla, 'alertas' => $alertas, 'filas' => $tabla->filas(),
        ]);
    }

    public static function duplicar()
    {
        $proyecto = proyecto_de_peticion(true);
        $copia = ($t = self::tablaDe($proyecto)) ? $t->duplicar() : null;
        header('Location: ' . ($copia ? '/vplan/tablas/editar?proyecto=' . $proyecto->id . '&id=' . $copia->id : '/vplan/tablas?proyecto=' . $proyecto->id));
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
}
