<?php

namespace Controllers;

use MVC\Router;
use Model\Partida;
use Model\VplanLamina;
use Model\VplanBloque;
use Model\VplanPaquete;
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

    private static function paqueteDe($proyecto): ?VplanPaquete
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $p = VplanPaquete::find(filter_var($fuente['paquete'] ?? $fuente['id'] ?? null, FILTER_VALIDATE_INT));
        return $p && (int)$p->proyecto_id === (int)$proyecto->id ? $p : null;
    }

    /** Lista de los vPlan del proyecto */
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $puedeEditar = puede_gestionar_proyecto($proyecto);
        $paquetes = VplanPaquete::deProyecto((int)$proyecto->id);
        if (!$puedeEditar) $paquetes = array_values(array_filter($paquetes, fn($p) => (int)$p->visibles > 0));
        $portadas = [];
        foreach ($paquetes as $p) {
            foreach ($p->laminas(true) as $l) {
                if ($img = $l->marcas_png ?: $l->plano) $portadas[(int)$p->id][] = $img;
                if (count($portadas[(int)$p->id] ?? []) >= 3) break;
            }
        }
        $router->render('vplan/index', [
            'titulo' => 'vPlan - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'paquetes' => $paquetes, 'portadas' => $portadas,
            'puedeEditar' => $puedeEditar, 'alertas' => [],
        ]);
    }

    /** Gestor de un vPlan: sus láminas (áreas) con visibilidad, orden, grupo, duplicar… */
    public static function paquete(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $paquete = self::paqueteDe($proyecto);
        if (!$paquete) {
            header('Location: /vplan?proyecto=' . $proyecto->id);
            exit;
        }
        $puedeEditar = puede_gestionar_proyecto($proyecto);
        $laminas = $paquete->laminas(!$puedeEditar);
        $grupos = array_values(array_unique(array_filter(array_map(fn($l) => trim((string)$l->grupo), $paquete->laminas()))));
        $router->render('vplan/paquete', [
            'titulo' => 'vPlan - ' . $paquete->nombre, 'proyecto' => $proyecto, 'paquete' => $paquete, 'laminas' => $laminas,
            'grupos' => $grupos, 'puedeEditar' => $puedeEditar,
        ]);
    }

    public static function paqueteCrear()
    {
        $proyecto = proyecto_de_peticion(true);
        $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 200) ?: 'vPlan';
        $p = new VplanPaquete(['proyecto_id' => $proyecto->id, 'nombre' => $nombre, 'orden' => count(VplanPaquete::deProyecto((int)$proyecto->id))]);
        $p->id = $p->guardar()['id'];
        header('Location: /vplan/paquete?proyecto=' . $proyecto->id . '&id=' . $p->id);
        exit;
    }

    public static function paqueteRenombrar()
    {
        $proyecto = proyecto_de_peticion(true);
        if (($p = self::paqueteDe($proyecto)) && ($n = mb_substr(trim($_POST['nombre'] ?? ''), 0, 200)) !== '') {
            $p->nombre = $n;
            $p->guardar();
        }
        header('Location: ' . (($_POST['volver'] ?? '') === 'lista' ? '/vplan?proyecto=' . $proyecto->id : '/vplan/paquete?proyecto=' . $proyecto->id . '&id=' . ($p->id ?? '')));
        exit;
    }

    public static function paqueteEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($p = self::paqueteDe($proyecto)) {
            foreach ($p->laminas() as $l) self::borrarLamina($l);
            $p->eliminar();
        }
        header('Location: /vplan?proyecto=' . $proyecto->id);
        exit;
    }

    /** Muestra u oculta todas las láminas de un grupo del vPlan */
    public static function paqueteGrupoVisible()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($p = self::paqueteDe($proyecto)) {
            $v = !empty($_POST['visible']) ? 1 : 0;
            VplanLamina::ejecutar("UPDATE vplan_laminas SET visible = $v WHERE paquete_id = " . (int)$p->id . " AND grupo = '" . VplanLamina::escapar(trim((string)($_POST['grupo'] ?? ''))) . "'");
        }
        header('Location: /vplan/paquete?proyecto=' . $proyecto->id . '&id=' . ($p->id ?? ''));
        exit;
    }

    /** Cambia la visibilidad, el grupo o el título de una lámina (JSON) */
    public static function ajustarLamina()
    {
        header('Content-Type: application/json');
        $l = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $l ? \Model\Proyecto::find($l->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        if (isset($_POST['visible'])) $l->visible = (int)(!empty($_POST['visible']));
        if (isset($_POST['grupo'])) $l->grupo = mb_substr(trim((string)$_POST['grupo']), 0, 100);
        if (isset($_POST['titulo']) && trim((string)$_POST['titulo']) !== '') $l->titulo = mb_substr(trim((string)$_POST['titulo']), 0, 200);
        $l->guardar();
        echo json_encode(['success' => true, 'visible' => (int)$l->visible, 'grupo' => $l->grupo, 'titulo' => $l->titulo]);
        exit;
    }

    public static function moverLamina()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::laminaDe($proyecto);
        if ($l) $l->mover(($_POST['dir'] ?? '') === 'subir' ? -1 : 1);
        header('Location: /vplan/paquete?proyecto=' . $proyecto->id . '&id=' . ($l->paquete_id ?? ''));
        exit;
    }

    public static function duplicarLamina()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::laminaDe($proyecto);
        if ($l) {
            $copia = $l->duplicar();
            header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . $copia->id . '&diseno=1');
            exit;
        }
        header('Location: /vplan?proyecto=' . $proyecto->id);
        exit;
    }

    /** Borra una lámina con sus imágenes y las que usan sus marcas */
    private static function borrarLamina(VplanLamina $l): void
    {
        foreach (['plano', 'simbologia', 'marcas_png'] as $c) Imagenes::eliminarVplan($l->$c);
        foreach (self::imagenesDeMarcas($l->marcas_json) as $img) Imagenes::eliminarVplan($img);
        $l->eliminar();
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $paquete = self::paqueteDe($proyecto) ?? VplanPaquete::principal((int)$proyecto->id);
        self::formulario($router, $proyecto, new VplanLamina(['proyecto_id' => $proyecto->id, 'paquete_id' => $paquete->id]));
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $esNueva = !$l->id;
            if ($esNueva) {   // en una lámina existente la partida y las actividades se cambian en el bloque de tareas (modo diseño)
                $pid = filter_var($_POST['partida_id'] ?? null, FILTER_VALIDATE_INT);
                $partida = $pid ? Partida::find($pid) : null;
                $l->partida_id = $partida && (int)$partida->proyecto_id === (int)$proyecto->id ? $partida->id : null;
            } else {
                $partida = $l->partida_id ? Partida::find($l->partida_id) : null;
            }
            $l->titulo = trim($_POST['titulo'] ?? '') ?: ($partida ? $partida->nombre : '');
            [$l->ancho_mm, $l->alto_mm, $l->preset] = \Classes\VplanDiseno::tamanoDe($_POST);
            $l->supervisor = mb_substr(trim($_POST['supervisor'] ?? ''), 0, 200);
            $l->proyecto_id = $proyecto->id;
            if ($esNueva) {
                $paq = self::paqueteDe($proyecto) ?? VplanPaquete::principal((int)$proyecto->id);
                $l->paquete_id = $paq->id;
                $l->orden = VplanLamina::siguienteOrden((int)$paq->id);
            }
            $l->grupo = mb_substr(trim($_POST['grupo'] ?? $l->grupo), 0, 100);
            $sg = (int)($_POST['simbologia_grafico_id'] ?? 0);
            $simb = $sg ? \Model\Grafico::find($sg) : null;
            $l->simbologia_grafico_id = $simb && $simb->tipo === 'simbologia' && (int)$simb->proyecto_id === (int)$proyecto->id ? $simb->id : null;
            $alertas = $l->validar();

            // Actividades del bloque de tareas inicial: solo las de la partida elegida; todas o ninguna = partida completa
            $seleccion = [];
            if ($esNueva && $partida && $l->partida_id) {
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
                $r = $l->guardar();
                if (is_array($r)) $l->id = $r['id'];
                if ($esNueva) $l->guardarSeleccion($seleccion);   // el diseño inicial (bloques) se arma con esto al abrir la lámina
                Vplan::asegurarRestricciones($l, $proyecto);
                header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . $l->id);
                exit;
            }
        }
        $router->render('vplan/formulario', [
            'titulo' => $l->id ? 'Editar lámina' : 'Nueva lámina', 'proyecto' => $proyecto, 'lamina' => $l,
            'partidas' => $partidas, 'simbologias' => \Model\Grafico::simbologias((int)$proyecto->id), 'alertas' => $alertas, 'actividadesPorPartida' => self::actividadesPorPartida($proyecto, $partidas, $l),
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
        $l = self::laminaDe($proyecto);
        $paquete = $l ? $l->paquete_id : null;
        if ($l) self::borrarLamina($l);
        header('Location: ' . ($paquete ? '/vplan/paquete?proyecto=' . $proyecto->id . '&id=' . $paquete : '/vplan?proyecto=' . $proyecto->id));
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
        if (!puede_gestionar_proyecto($proyecto) && !(int)$l->visible) {   // una lámina oculta solo la ve quien administra el vPlan
            header('Location: /vplan?proyecto=' . $proyecto->id);
            exit;
        }
        if (puede_gestionar_proyecto($proyecto)) Vplan::asegurarRestricciones($l, $proyecto);   // láminas anteriores a esta regla
        $paquete = $l->paquete_id ? VplanPaquete::find($l->paquete_id) : null;
        $hermanas = $paquete ? $paquete->laminas(!puede_gestionar_proyecto($proyecto)) : [];
        $datos = Vplan::datos($l, $proyecto);
        $enLamina = array_map(fn($t) => (int)$t['tabla']->id, $datos['tablas']);
        $router->render('vplan/ver', $datos + [
            'titulo' => 'vPlan - ' . $l->titulo, 'proyecto' => $proyecto, 'lamina' => $l, 'paquete' => $paquete, 'hermanas' => $hermanas,
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'notas' => array_map(fn($n) => $n->aArray(), \Model\VplanNota::deLamina((int)$l->id)),
            'catalogo' => array_values(array_filter(VplanTabla::deProyecto((int)$proyecto->id), fn($t) => !in_array((int)$t->id, $enLamina, true))),
            'catalogoTablas' => puede_gestionar_proyecto($proyecto) ? self::catalogoTablas($proyecto, $enLamina) : [],
            'navLaminas' => self::navLaminas($hermanas),
            'partidasDiseno' => puede_gestionar_proyecto($proyecto) ? self::partidasParaDiseno($proyecto) : [],
            'desactualizada' => $l->marcas_json && $l->marcas_estados_hash !== $datos['hash'],
        ]);
    }

    /** Todas las tablas que se pueden insertar, de todos los tipos: las del catálogo y las hojas de SheetFlow que aún no son tabla del vPlan */
    private static function catalogoTablas($proyecto, array $enLamina): array
    {
        $out = [];
        $vinculadas = [];
        foreach (VplanTabla::deProyecto((int)$proyecto->id) as $t) {
            if ($t->sheetflow_id) $vinculadas[(int)$t->sheetflow_id] = true;
            $out[] = [
                'id' => (int)$t->id, 'nombre' => $t->etiqueta(), 'titulo' => $t->titulo, 'tipo' => $t->tipo, 'tipoNombre' => VplanTabla::TIPOS[$t->tipo] ?? $t->tipo,
                'filas' => count($t->filas()), 'sheetflow' => (bool)$t->sheetflow_id, 'enLamina' => in_array((int)$t->id, $enLamina, true),
            ];
        }
        foreach (\Model\SheetflowHoja::resumen((int)$proyecto->id) as $h) {
            if (isset($vinculadas[(int)$h['id']])) continue;
            $out[] = ['id' => 0, 'sheetflow_id' => (int)$h['id'], 'nombre' => $h['nombre'], 'titulo' => mb_strtoupper($h['nombre']), 'tipo' => 'sheetflow', 'tipoNombre' => 'SheetFlow (hoja nueva)',
                'filas' => (int)$h['filas'], 'sheetflow' => true, 'enLamina' => false];
        }
        return $out;
    }

    /** Miniaturas del navegador de láminas: cada lámina con sus bloques en % (más el plano si tiene) */
    private static function navLaminas(array $laminas): array
    {
        $out = [];
        foreach ($laminas as $h) {
            $out[] = [
                'lamina' => $h,
                'bloques' => array_map(fn($b) => ['tipo' => $b->tipo, 'x' => (float)$b->x, 'y' => (float)$b->y, 'w' => (float)$b->w, 'h' => (float)$b->h, 'oculto' => !empty($b->config()['oculto'])], $h->bloques()),
                'img' => $h->marcas_png ?: $h->plano,
            ];
        }
        return $out;
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

    /** Agrega una columna de Recovery al final (Recovery, Recovery 02, 03…) o quita la última. Las fechas capturadas se conservan */
    public static function recoveryCols()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::laminaDe($proyecto);
        if ($l) {
            $n = (int)$l->recovery_n;
            $l->recovery_n = ($_POST['accion'] ?? '') === 'quitar' ? max(0, $n - 1) : min(VplanLamina::MAX_RECOVERY, $n + 1);
            $l->guardar();
        }
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => (bool)$l, 'n' => $l ? (int)$l->recovery_n : 0]);
            exit;
        }
        header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . ($l->id ?? '') . (!empty($_POST['diseno']) ? '&diseno=1' : ''));
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
        if (!$t && $l && !empty($_POST['sheetflow_id'])) {   // hoja de SheetFlow: se crea (o reutiliza) su tabla de vPlan
            $h = \Model\SheetflowHoja::find(filter_var($_POST['sheetflow_id'], FILTER_VALIDATE_INT));
            if ($h && (int)$h->proyecto_id === (int)$proyecto->id) {
                foreach (VplanTabla::deProyecto((int)$proyecto->id) as $x) if ((int)$x->sheetflow_id === (int)$h->id) { $t = $x; break; }
                $t ??= \Classes\Sheetflow::aVplan($h);
            }
        }
        $ok = false;
        if ($l && $t && (int)$t->proyecto_id === (int)$proyecto->id) {
            $ok = true;
            match ($_POST['accion'] ?? '') {
                'agregar' => $l->agregarTabla((int)(!empty($_POST['copia']) ? $t->duplicar()->id : $t->id)),
                'quitar' => $l->quitarTabla((int)$t->id),
                'subir' => $l->moverTabla((int)$t->id, -1),
                'bajar' => $l->moverTabla((int)$t->id, 1),
                default => $ok = false,
            };
        }
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $ok]);
            exit;
        }
        header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . ($l->id ?? '') . (!empty($_POST['diseno']) ? '&diseno=1' : ''));
        exit;
    }


    /** Partidas del proyecto con sus actividades (y su SEQ original), para elegir qué muestra un bloque de tareas */
    private static function partidasParaDiseno($proyecto): array
    {
        $out = [];
        foreach ($proyecto->getPartidas() as $p) {
            $out[] = ['id' => (int)$p->id, 'codigo' => (string)$p->codigo, 'nombre' => (string)$p->nombre, 'color' => (string)$p->color_bg,
                'actividades' => array_map(fn($a, $i) => ['id' => (int)$a->id, 'seq' => $i + 1, 'nombre' => (string)$a->nombre], $p->getActividades(), array_keys($p->getActividades()))];
        }
        return $out;
    }

    private static function hex(?string $c): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? strtolower($c) : '';
    }

    /** Deja de la configuración de un bloque solo lo que su tipo entiende (y que sea válido para este proyecto) */
    private static function configDeBloque(string $tipo, array $c, $proyecto, VplanLamina $l): array
    {
        $out = self::configBase($tipo, $c, $proyecto, $l);
        if (!empty($c['bloqueado'])) $out['bloqueado'] = true;
        $nombre = mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($c['nombre'] ?? ''))), 0, 60);
        if ($nombre !== '') $out['nombre'] = $nombre;
        return $out;
    }

    private static function configBase(string $tipo, array $c, $proyecto, VplanLamina $l): array
    {
        $texto = fn($k, $max = 1000) => mb_substr(str_replace("\r", '', trim((string)($c[$k] ?? ''))), 0, $max);
        $num = fn($k, $min, $max, $def) => isset($c[$k]) && is_numeric($c[$k]) ? max($min, min($max, (float)$c[$k])) : $def;
        $alinear = in_array($c['alinear'] ?? '', ['izquierda', 'centro', 'derecha'], true) ? $c['alinear'] : null;
        switch ($tipo) {
            case 'titulo': case 'supervisor': case 'fecha': case 'texto':
                $out = ['texto' => $texto('texto'), 'tamano' => (float)($c['tamano'] ?? 0) > 0 ? $num('tamano', 3, 120, 0) : 0, 'negrita' => !empty($c['negrita']), 'color' => self::hex($c['color'] ?? '')];
                if ($alinear) $out['alinear'] = $alinear;
                return $out;
            case 'logo':
                return ['cual' => ($c['cual'] ?? '') === 'empresa' ? 'empresa' : 'contratista'] + ($alinear ? ['alinear' => $alinear] : []);
            case 'tareas':
                $fuentes = [];
                foreach (array_slice((array)($c['fuentes'] ?? []), 0, 40) as $f) {
                    $p = Partida::find((int)($f['partida'] ?? 0));
                    if (!$p || (int)$p->proyecto_id !== (int)$proyecto->id) continue;
                    $ids = array_map(fn($a) => (int)$a->id, $p->getActividades());
                    $sel = isset($f['actividades']) && is_array($f['actividades']) ? array_values(array_intersect(array_map('intval', $f['actividades']), $ids)) : null;
                    $fuentes[] = ['partida' => (int)$p->id, 'actividades' => $sel && count($sel) < count($ids) ? $sel : null];
                }
                return ['fuentes' => $fuentes, 'seq' => ($c['seq'] ?? '') === 'partida' ? 'partida' : 'continuo', 'titulo' => $texto('titulo', 200), 'color' => self::hex($c['color'] ?? '')];
            case 'tabla':
                return ['tabla_id' => (int)($c['tabla_id'] ?? 0)];
            default:   // plano, simbologia, schedule
                return $alinear ? ['alinear' => $alinear] : [];
        }
    }

    /**
     * Guarda el diseño de la lámina: tamaño de la hoja y bloques (posición, tamaño y configuración).
     * Los bloques que ya no vienen se eliminan; un bloque de tabla que se quita saca la tabla de la lámina.
     */
    public static function guardarDiseno()
    {
        header('Content-Type: application/json');
        $l = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $l ? \Model\Proyecto::find($l->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $datos = json_decode((string)($_POST['diseno'] ?? ''), true);
        if (!is_array($datos) || !is_array($datos['bloques'] ?? null) || count($datos['bloques']) > 80) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Diseño no válido']);
            exit;
        }
        $actuales = [];
        foreach ($l->bloques() as $b) $actuales[(int)$b->id] = $b;
        $miembros = array_map(fn($t) => (int)$t->id, $l->tablas());
        $antes = md5(json_encode(array_map(fn($b) => $b->tipo === 'tareas' ? $b->config()['fuentes'] ?? [] : null, $l->bloques())));

        $conservados = [];
        $nuevos = [];
        $z = 0;
        foreach ($datos['bloques'] as $in) {
            $tipo = (string)($in['tipo'] ?? '');
            if (!isset(VplanBloque::TIPOS[$tipo])) continue;
            $id = (int)($in['id'] ?? 0);
            $b = $actuales[$id] ?? null;
            if ($b && $b->tipo !== $tipo) $b = null;
            if (!$b && $tipo === 'tabla') continue;   // las tablas se agregan desde su lista, no con el diseñador
            $b ??= new VplanBloque(['lamina_id' => $l->id, 'tipo' => $tipo]);
            $cfg = self::configDeBloque($tipo, (array)($in['config'] ?? []), $proyecto, $l);
            if (!empty($in['config']['oculto'])) $cfg['oculto'] = true;
            if ($tipo === 'tabla' && !in_array($cfg['tabla_id'], $miembros, true)) continue;
            $clamp = fn($v, $min, $max) => round(max($min, min($max, is_numeric($v) ? (float)$v : $min)), 3);
            $b->w = $clamp($in['w'] ?? 10, 1, 100);
            $b->h = $clamp($in['h'] ?? 5, 0.5, 100);
            $b->x = $clamp($in['x'] ?? 0, 0, 100 - (float)$b->w);
            $b->y = $clamp($in['y'] ?? 0, 0, 100 - (float)$b->h);
            $b->z = $z++;
            $b->setConfig($cfg);
            $nuevos[] = $b;
            if ($b->id) $conservados[(int)$b->id] = true;
        }
        foreach ($actuales as $id => $b) {
            if (isset($conservados[$id])) continue;
            if ($b->tipo === 'tabla') $l->quitarTabla((int)($b->config()['tabla_id'] ?? 0));
            else $b->eliminar();
        }
        foreach ($nuevos as $b) {
            $r = $b->guardar();
            if (is_array($r)) $b->id = $r['id'];
        }

        if (isset($datos['tamano']) && is_array($datos['tamano'])) {
            [$l->ancho_mm, $l->alto_mm, $l->preset] = \Classes\VplanDiseno::tamanoDe($datos['tamano']);
        }
        if (isset($datos['guias']) && is_array($datos['guias'])) {
            $guias = [];
            foreach (array_slice($datos['guias'], 0, 60) as $g) {
                if (!is_array($g) || !in_array($g['eje'] ?? '', ['v', 'h'], true) || !isset($g['pos']) || !is_numeric($g['pos'])) continue;
                $nombre = mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)($g['nombre'] ?? ''))), 0, 60);
                $guias[] = ['id' => substr(preg_replace('/[^a-z0-9]/i', '', (string)($g['id'] ?? '')) ?: bin2hex(random_bytes(3)), 0, 12), 'eje' => $g['eje'],
                    'pos' => round(max(-50, min(150, (float)$g['pos'])), 3), 'visible' => ($g['visible'] ?? true) !== false, 'bloqueada' => !empty($g['bloqueada']), 'nombre' => $nombre];
            }
            $l->guias_json = $guias ? json_encode($guias, JSON_UNESCAPED_UNICODE) : null;
        }
        $l->olvidarBloques();
        $despues = md5(json_encode(array_map(fn($b) => $b->tipo === 'tareas' ? $b->config()['fuentes'] ?? [] : null, $l->bloques())));
        if ($antes !== $despues) $l->restr_auto = 0;   // otras actividades: se reevalúan las restricciones
        $l->guardar();
        Vplan::asegurarRestricciones($l, $proyecto);
        echo json_encode(['success' => true]);
        exit;
    }

    /** Vuelve al diseño estándar de la hoja (conserva el contenido del primer bloque de tareas y las tablas) */
    public static function restablecerDiseno()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::laminaDe($proyecto);
        if ($l) {
            $fuentes = null;
            $seq = 'continuo';
            foreach ($l->bloques() as $b) {
                if ($b->tipo === 'tareas') { $fuentes = $b->config()['fuentes'] ?? null; $seq = $b->config()['seq'] ?? 'continuo'; break; }
            }
            \Model\VplanBloque::ejecutar("DELETE FROM vplan_bloques WHERE lamina_id = " . (int)$l->id);
            $l->olvidarBloques();
            foreach (\Classes\VplanDiseno::porDefecto($l, $fuentes, $seq) as $b) {
                $b->lamina_id = $l->id;
                $b->guardar();
            }
            $l->olvidarBloques();
        }
        header('Location: /vplan/ver?proyecto=' . $proyecto->id . '&id=' . ($l->id ?? '') . '&diseno=1');
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
        $v = trim((string)($_POST['valor'] ?? ''));
        $d = $v === '' ? null : \DateTime::createFromFormat('Y-m-d', $v);
        $n = filter_var($_POST['n'] ?? null, FILTER_VALIDATE_INT);
        if (!$act || !$n || $n < 1 || $n > VplanLamina::MAX_RECOVERY || !in_array($_POST['campo'] ?? '', ['inicio', 'fin'], true) || ($v !== '' && (!$d || $d->format('Y-m-d') !== $v))) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Dato no válido']);
            exit;
        }
        if (!in_array((int)$act->id, array_map(fn($t) => (int)$t['actividad']->id, Vplan::datos($l, $proyecto)['tareas']), true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'La actividad no está en esta lámina']);
            exit;
        }
        $l->guardarRecovery((int)$act->id, (int)$n, $_POST['campo'], $v === '' ? null : $v);
        echo json_encode(['success' => true]);
        exit;
    }
}
