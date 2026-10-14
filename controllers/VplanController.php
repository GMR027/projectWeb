<?php

namespace Controllers;

use MVC\Router;
use Model\Partida;
use Model\VplanLamina;
use Model\VplanTabla;
use Classes\Imagenes;
use Classes\Vplan;

/** vPlan: láminas de plan visual por partida */
class VplanController
{
    private static function laminaDe($proyecto): ?VplanLamina
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $l = VplanLamina::find(filter_var($fuente['id'] ?? $fuente['lamina'] ?? null, FILTER_VALIDATE_INT));
        return $l && (int)$l->proyecto_id === (int)$proyecto->id ? $l : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('vplan/index', [
            'titulo' => 'vPlan - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'laminas' => VplanLamina::deProyecto((int)$proyecto->id), 'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        self::formulario($router, $proyecto, new VplanLamina(['proyecto_id' => $proyecto->id]));
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::laminaDe($proyecto);
        if (!$l) {
            header('Location: /vplan?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $proyecto, $l);
    }

    private static function formulario(Router $router, $proyecto, VplanLamina $l)
    {
        $alertas = [];
        $partidas = $proyecto->getPartidas();
        $partidaAnterior = $l->partida_id;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pid = filter_var($_POST['partida_id'] ?? null, FILTER_VALIDATE_INT);
            $partida = $pid ? Partida::find($pid) : null;
            $l->partida_id = $partida && (int)$partida->proyecto_id === (int)$proyecto->id ? $partida->id : null;
            $l->titulo = trim($_POST['titulo'] ?? '') ?: ($partida ? $partida->nombre : '');
            $l->supervisor = mb_substr(trim($_POST['supervisor'] ?? ''), 0, 200);
            $l->proyecto_id = $proyecto->id;
            if ($l->id && (int)$l->partida_id !== (int)$partidaAnterior) $l->restr_auto = 0;   // otra partida: se reevalúa
            $alertas = $l->validar();

            foreach (['plano', 'simbologia'] as $campo) {
                $hay = !empty($_FILES[$campo]['tmp_name']) && is_uploaded_file($_FILES[$campo]['tmp_name']);
                if ($hay && !in_array(mime_content_type($_FILES[$campo]['tmp_name']), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                    $alertas['error'][] = "La imagen de $campo debe ser jpg, png, webp o gif";
                }
            }
            $bytesNuevos = 0;
            foreach (['plano', 'simbologia'] as $campo) {
                if (!empty($_FILES[$campo]['tmp_name']) && is_uploaded_file($_FILES[$campo]['tmp_name'])) $bytesNuevos += (int)$_FILES[$campo]['size'];
            }
            if ($bytesNuevos && !\Classes\Almacenamiento::cabe((int)$proyecto->id, $bytesNuevos)) $alertas['error'][] = \Classes\Almacenamiento::mensajeLleno((int)$proyecto->id);
            if (!$alertas) {
                foreach (['plano', 'simbologia'] as $campo) {
                    if (!empty($_FILES[$campo]['tmp_name']) && is_uploaded_file($_FILES[$campo]['tmp_name'])) {
                        if ($nuevo = Imagenes::guardarGrande($_FILES[$campo]['tmp_name'])) {
                            Imagenes::eliminarVplan($l->$campo);
                            $l->$campo = $nuevo;
                            if ($campo === 'plano') {   // un plano nuevo invalida las marcas dibujadas sobre el anterior
                                Imagenes::eliminarVplan($l->marcas_png);
                                $l->marcas_png = null;
                                $l->marcas_json = null;
                            }
                        }
                    } elseif (!empty($_POST['quitar_' . $campo])) {
                        Imagenes::eliminarVplan($l->$campo);
                        $l->$campo = null;
                        if ($campo === 'plano') {
                            Imagenes::eliminarVplan($l->marcas_png);
                            $l->marcas_png = null;
                            $l->marcas_json = null;
                        }
                    }
                }
                $r = $l->guardar();
                if (is_array($r)) $l->id = $r['id'];
                Vplan::asegurarRestricciones($l, $proyecto);
                header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . $l->id);
                exit;
            }
        }
        $router->render('vplan/formulario', [
            'titulo' => $l->id ? 'Editar lámina' : 'Nueva lámina', 'proyecto' => $proyecto, 'lamina' => $l,
            'partidas' => $partidas, 'alertas' => $alertas,
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($l = self::laminaDe($proyecto)) {
            foreach (['plano', 'simbologia', 'marcas_png'] as $c) Imagenes::eliminarVplan($l->$c);
            foreach (self::imagenesDeMarcas($l->marcas_json) as $img) Imagenes::eliminarVplan($img);
            $l->eliminar();
        }
        header('Location: /vplan?proyecto=' . $proyecto->id);
        exit;
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $l = self::laminaDe($proyecto);
        if (!$l) {
            header('Location: /vplan?proyecto=' . $proyecto->id);
            exit;
        }
        if (puede_gestionar_proyecto($proyecto)) Vplan::asegurarRestricciones($l, $proyecto);   // láminas anteriores a esta regla
        $datos = Vplan::datos($l, $proyecto);
        $enLamina = array_map(fn($t) => (int)$t['tabla']->id, $datos['tablas']);
        $router->render('vplan/ver', $datos + [
            'titulo' => 'vPlan - ' . $l->titulo, 'proyecto' => $proyecto, 'lamina' => $l,
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'catalogo' => array_values(array_filter(VplanTabla::deProyecto((int)$proyecto->id), fn($t) => !in_array((int)$t->id, $enLamina, true))),
            'desactualizada' => $l->marcas_json && $l->marcas_estados_hash !== $datos['hash'],
        ]);
    }

    /** Editor de marcas (Konva) sobre el plano */
    public static function editor(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::laminaDe($proyecto);
        if (!$l || !$l->plano) {
            header('Location: /vplan' . ($l ? '/ver?proyecto=' . $proyecto->id . '&id=' . $l->id : '?proyecto=' . $proyecto->id) . '&error=' . urlencode('Sube primero la imagen del plano'));
            exit;
        }
        $datos = Vplan::datos($l, $proyecto);
        $router->render('vplan/editor', [
            'titulo' => 'Marcas - ' . $l->titulo, 'proyecto' => $proyecto, 'lamina' => $l,
            'config' => [
                'lamina' => (int)$l->id, 'proyecto' => (int)$proyecto->id,
                'plano' => '/uploads/vplan/' . $l->plano,
                'marcas' => $l->marcas_json ? json_decode($l->marcas_json, true) : null,
                'actividades' => array_map(fn($t) => ['id' => (int)$t['actividad']->id, 'seq' => $t['seq'], 'nombre' => $t['actividad']->nombre, 'estado' => $t['estado']], $datos['tareas']),
                'restricciones' => $datos['restricciones'],
                'hash' => $datos['hash'],
                'auto' => !empty($_GET['auto']),
                'volver' => '/vplan/ver?proyecto=' . $proyecto->id . '&id=' . $l->id,
            ],
        ]);
    }

    /** Guarda las marcas (JSON) y la imagen compuesta (plano + marcas) */
    public static function guardarMarcas()
    {
        header('Content-Type: application/json');
        $l = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $l ? \Model\Proyecto::find($l->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $json = (string)($_POST['json'] ?? '');
        $datos = json_decode($json, true);
        // Las imágenes dentro de las marcas deben ser archivos subidos por el editor
        foreach (($datos['shapes'] ?? []) as $sh) {
            if (($sh['type'] ?? '') === 'image' && !preg_match('#^/uploads/vplan/[a-f0-9]{32}\.(png|jpg)$#', (string)($sh['src'] ?? ''))) $datos = null;
        }
        $png = (string)($_POST['png'] ?? '');
        $bin = preg_match('#^data:image/(png|jpeg);base64,#', $png) ? base64_decode(substr($png, strpos($png, ',') + 1), true) : false;
        if (!is_array($datos) || strlen($json) > 3_000_000 || !isset($datos['shapes']) || count($datos['shapes']) > 2000 || !$bin || strlen($bin) > 30_000_000) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Datos no válidos']);
            exit;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'vpm');
        file_put_contents($tmp, $bin);
        $nuevo = Imagenes::guardarGrande($tmp, 3840);
        @unlink($tmp);
        if (!$nuevo) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Imagen no válida']);
            exit;
        }
        Imagenes::eliminarVplan($l->marcas_png);
        // Borra del disco las imágenes que ya no usa ninguna marca
        foreach (array_diff(self::imagenesDeMarcas($l->marcas_json), self::imagenesDeMarcas($json)) as $viejo) Imagenes::eliminarVplan($viejo);
        $l->marcas_png = $nuevo;
        $l->marcas_json = $json;
        $l->marcas_estados_hash = Vplan::datos($l, $proyecto)['hash'];
        $l->guardar();
        echo json_encode(['success' => true]);
        exit;
    }

    /** Agrega o quita una columna de Recovery (cual = 1 Recovery, 2 Recovery 02) */
    public static function recoveryCols()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::laminaDe($proyecto);
        $bit = ($_POST['cual'] ?? '1') === '2' ? 2 : 1;
        if ($l) {
            $v = (int)$l->recovery_cols;
            $l->recovery_cols = ($_POST['accion'] ?? '') === 'quitar' ? ($v & ~$bit) : ($v | $bit);
            $l->guardar();
        }
        header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . ($l->id ?? ''));
        exit;
    }

    /** Sube una imagen para usarla como marca dentro del editor */
    public static function subirImagen()
    {
        header('Content-Type: application/json');
        $l = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $l ? \Model\Proyecto::find($l->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $f = $_FILES['imagen'] ?? null;
        $ok = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']) && $f['size'] <= 20 * 1024 * 1024
            && in_array(mime_content_type($f['tmp_name']), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
        if ($ok && !\Classes\Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => \Classes\Almacenamiento::mensajeLleno((int)$proyecto->id)]);
            exit;
        }
        $nombre = $ok ? Imagenes::guardarGrande($f['tmp_name'], 1600) : null;
        if (!$nombre) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Imagen no válida (jpg, png, webp o gif de hasta 20 MB)']);
            exit;
        }
        echo json_encode(['success' => true, 'src' => '/uploads/vplan/' . $nombre]);
        exit;
    }

    /** Archivos de imagen de vPlan que usan las marcas (type 'image') */
    public static function imagenesDeMarcas(?string $json): array
    {
        $d = $json ? json_decode($json, true) : null;
        $out = [];
        foreach (($d['shapes'] ?? []) as $s) {
            if (($s['type'] ?? '') === 'image' && preg_match('#^/uploads/vplan/([a-f0-9]{32}\.(?:png|jpg))$#', (string)($s['src'] ?? ''), $m)) $out[] = $m[1];
        }
        return array_values(array_unique($out));
    }

    /** Agrega, quita o mueve una tabla adicional de la lámina */
    public static function tablaLamina()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::laminaDe($proyecto);
        $t = VplanTabla::find(filter_var($_POST['tabla_id'] ?? null, FILTER_VALIDATE_INT));
        if ($l && $t && (int)$t->proyecto_id === (int)$proyecto->id) {
            match ($_POST['accion'] ?? '') {
                'agregar' => $l->agregarTabla((int)$t->id),
                'quitar' => $l->quitarTabla((int)$t->id),
                'subir' => $l->moverTabla((int)$t->id, -1),
                'bajar' => $l->moverTabla((int)$t->id, 1),
                default => null,
            };
        }
        header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . ($l->id ?? ''));
        exit;
    }

    /** Guarda una fecha de recovery de una actividad en la lámina */
    public static function recovery()
    {
        header('Content-Type: application/json');
        $l = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $l ? \Model\Proyecto::find($l->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $act = \Model\Actividad::find(filter_var($_POST['actividad'] ?? null, FILTER_VALIDATE_INT));
        $partida = $act ? Partida::find($act->partida_id) : null;
        $v = trim((string)($_POST['valor'] ?? ''));
        $d = $v === '' ? null : \DateTime::createFromFormat('Y-m-d', $v);
        if (!$partida || (int)$partida->id !== (int)$l->partida_id || !in_array($_POST['campo'] ?? '', VplanLamina::CAMPOS_RECOVERY, true)
            || ($v !== '' && (!$d || $d->format('Y-m-d') !== $v))) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Dato no válido']);
            exit;
        }
        $l->guardarRecovery((int)$act->id, $_POST['campo'], $v === '' ? null : $v);
        // Aviso de si ahora hay datos de Recovery 02
        $usaR2 = Vplan::datos($l, $proyecto)['usaR2'];
        echo json_encode(['success' => true, 'usaR2' => $usaR2]);
        exit;
    }
}
