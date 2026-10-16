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

            // Actividades que se muestran: solo las de la partida elegida; todas o ninguna = partida completa
            $seleccion = [];
            if ($partida && $l->partida_id) {
                $idsPartida = array_map(fn($a) => (int)$a->id, $partida->getActividades());
                $pedidas = array_unique(array_map('intval', (array)($_POST['actividades'] ?? [])));
                $seleccion = array_values(array_intersect($pedidas, $idsPartida));
                if ($pedidas && !$seleccion) $alertas['error'][] = 'Las actividades elegidas no pertenecen a la partida';
                if (count($seleccion) === count($idsPartida)) $seleccion = [];   // todas marcadas = partida completa
            }

            // Subidas que el servidor rechazó antes de llegar aquí (más pesadas que upload_max_filesize / post_max_size): avisar en vez de ignorarlas
            if (!empty($_SERVER['CONTENT_LENGTH']) && empty($_POST) && empty($_FILES)) {
                $alertas['error'][] = 'Las imágenes superan el tamaño máximo permitido por el servidor (' . ini_get('post_max_size') . ' en total). Reduce su tamaño e inténtalo de nuevo.';
            }
            foreach (['plano', 'simbologia'] as $campo) {
                $codigo = $_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE;
                if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) $alertas['error'][] = "La imagen de $campo pesa más de lo que permite el servidor (" . ini_get('upload_max_filesize') . ').';
                elseif (!in_array($codigo, [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) $alertas['error'][] = "No se pudo subir la imagen de $campo (error $codigo). Inténtalo de nuevo.";
            }
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
                $seleccionAnterior = $l->id ? $l->seleccion() : [];
                $r = $l->guardar();
                if (is_array($r)) $l->id = $r['id'];
                if ((int)$l->partida_id !== (int)$partidaAnterior) $seleccionAnterior = [];
                sort($seleccionAnterior);
                sort($seleccion);
                if ($seleccion !== $seleccionAnterior || (int)$l->partida_id !== (int)$partidaAnterior) {
                    $l->guardarSeleccion($seleccion);
                    $l->restr_auto = 0;   // otras actividades: se reevalúan las restricciones
                    $l->guardar();
                }
                Vplan::asegurarRestricciones($l, $proyecto);
                header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . $l->id);
                exit;
            }
        }
        $router->render('vplan/formulario', [
            'titulo' => $l->id ? 'Editar lámina' : 'Nueva lámina', 'proyecto' => $proyecto, 'lamina' => $l,
            'partidas' => $partidas, 'alertas' => $alertas, 'actividadesPorPartida' => self::actividadesPorPartida($proyecto, $partidas, $l),
            'seleccion' => $_SERVER['REQUEST_METHOD'] === 'POST' ? array_map('intval', (array)($_POST['actividades'] ?? [])) : $l->seleccion(),
        ]);
    }

    /**
     * Para el formulario: por partida, sus actividades con su número original y en qué OTRAS láminas ya se muestran.
     * Una lámina sin selección muestra toda su partida.
     */
    private static function actividadesPorPartida($proyecto, array $partidas, VplanLamina $actual): array
    {
        $porPartida = [];   // partida_id => [actividad_id|'*' => [títulos]]
        foreach (VplanLamina::deProyecto((int)$proyecto->id) as $o) {
            if (!$o->partida_id || (int)$o->id === (int)$actual->id) continue;
            $sel = $o->seleccion();
            foreach ($sel ?: ['*'] as $aid) $porPartida[(int)$o->partida_id][$aid][] = $o->titulo;
        }
        $out = [];
        foreach ($partidas as $p) {
            $lista = [];
            foreach ($p->getActividades() as $i => $a) {
                $en = array_merge($porPartida[(int)$p->id]['*'] ?? [], $porPartida[(int)$p->id][(int)$a->id] ?? []);
                $lista[] = ['id' => (int)$a->id, 'seq' => $i + 1, 'nombre' => $a->nombre, 'en' => array_values(array_unique($en))];
            }
            $out[(int)$p->id] = $lista;
        }
        return $out;
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
            'notas' => array_map(fn($n) => $n->aArray(), \Model\VplanNota::deLamina((int)$l->id)),
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
                'plano' => '/uploads/vplan/' . $l->plano, 'nombre' => $l->titulo,
                'marcas' => $l->marcas_json ? json_decode($l->marcas_json, true) : null,
                'actividades' => array_map(fn($t) => ['id' => (int)$t['actividad']->id, 'seq' => $t['seq'], 'nombre' => $t['actividad']->nombre, 'estado' => $t['estado']], $datos['tareas']),
                'restricciones' => $datos['restricciones'],
                'simbolos' => \Model\Simbolo::paraEditor((int)$proyecto->id), 'graficos' => \Model\Grafico::paraInsertar((int)$proyecto->id),
                'urlSimbolos' => '/simbolos?proyecto=' . $proyecto->id,
                'cajetines' => \Model\Cajetin::paraEditor((int)$proyecto->id), 'proyectoNombre' => $proyecto->nombre, 'usuario' => (string)($_SESSION['nombre'] ?? ''),
                'destinos' => \Classes\EnlacesLamina::destinos((int)$proyecto->id),
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
            if (($s['type'] ?? '') === 'link' && ($s['kind'] ?? '') === '360' && preg_match('#^/uploads/vplan/([a-f0-9]{32}\.(?:png|jpg))$#', (string)($s['url'] ?? ''), $m)) $out[] = $m[1];
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
        [$visibles] = Vplan::visibles($l, $partida);
        if (!in_array((int)$act->id, array_map(fn($v) => (int)$v['actividad']->id, $visibles), true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'La actividad no está en esta lámina']);
            exit;
        }
        $l->guardarRecovery((int)$act->id, $_POST['campo'], $v === '' ? null : $v);
        // Aviso de si ahora hay datos de Recovery 02
        $usaR2 = Vplan::datos($l, $proyecto)['usaR2'];
        echo json_encode(['success' => true, 'usaR2' => $usaR2]);
        exit;
    }
}
