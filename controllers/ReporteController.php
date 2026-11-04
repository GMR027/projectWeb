<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Personal;
use Model\ReporteDiario;
use Model\Subactividad;
use Classes\Imagenes;
use Classes\ParteDiario;

/** Reporte diario de obra y catálogo de personal del proyecto */
class ReporteController
{
    private const MAX_FOTOS_SUBIDA = 20;
    private const MAX_FOTO_BYTES = 15 * 1024 * 1024;

    /** [id => [Actividad, Partida]] de todas las actividades del programa del proyecto */
    private static function actividades(Proyecto $proyecto): array
    {
        $r = [];
        foreach ($proyecto->getPartidasConActividades() as $bloque) {
            foreach ($bloque['actividades'] as $a) $r[(int)$a->id] = [$a, $bloque['partida']];
        }
        return $r;
    }

    private static function esPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    // ============================== REPORTES ==============================

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $acts = self::actividades($proyecto);
        $filas = [];
        foreach (ReporteDiario::deProyecto((int)$proyecto->id) as $r) {
            $nombres = [];
            foreach ($r->getActividades() as $id) if (isset($acts[$id])) $nombres[] = $acts[$id][0]->nombre;
            $filas[] = ['reporte' => $r, 'actividades' => $nombres];
        }
        $pid = (int)$proyecto->id;
        $cp = \Classes\CamposPersonalizados::aplicar($pid, 'reporte', $filas, fn($f) => (int)$f['reporte']->id);
        $router->render('reportes/index', ['titulo' => 'Reporte diario - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filas' => $cp['items'], 'cp' => $cp, 'diseno' => \Classes\ListaDiseno::para($pid, 'reportes')]);
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $parte = $id ? ParteDiario::detalle($proyecto, (int)$id) : null;
        if (!$parte) {
            header('Location: /reportes?proyecto=' . $proyecto->id);
            exit;
        }
        $alertas = (array)($_SESSION['parte_alertas'] ?? []);
        unset($_SESSION['parte_alertas']);
        $router->render('reportes/ver', [
            'titulo' => 'Reporte del ' . fecha_corta_es($parte['reporte']->fecha) . ' - ' . $proyecto->nombre,
            'proyecto' => $proyecto, 'reporte' => $parte['reporte'], 'parte' => $parte,
            'subactividades' => Subactividad::conteoPorProyecto((int)$proyecto->id),
            'puede_cerrar' => ParteDiario::puedeCerrar($proyecto),
            'alertas' => $alertas,
        ]);
    }

    public static function crear(Router $router)
    {
        header('Location: /reportes/parte?proyecto=' . (int)($_GET['proyecto'] ?? $_POST['proyecto'] ?? 0));
        exit;
    }

    public static function editar(Router $router)
    {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        header('Location: /reportes/parte?proyecto=' . (int)($_GET['proyecto'] ?? $_POST['proyecto'] ?? 0) . ($id ? '&id=' . $id : ''));
        exit;
    }

    /** Pantalla única del parte diario de campo */
    public static function parte(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $id = (int)($_GET['id'] ?? 0);
        $post = null;
        if (!empty($_SESSION['parte_post'])) {
            $post = (array)$_SESSION['parte_post'];
            unset($_SESSION['parte_post']);
            if (!empty($post['id'])) $id = (int)$post['id'];
        }
        if (!$post && $id) {
            $detalle = ParteDiario::detalle($proyecto, $id);
            if (!$detalle) { header('Location: /reportes?proyecto=' . $pid); exit; }
            if ($detalle['reporte']->estaCerrado()) { header('Location: /reportes/ver?proyecto=' . $pid . '&id=' . $id); exit; }
        }
        $vista = ParteDiario::vista($proyecto, $id, $post);
        $alertas = (array)($_SESSION['parte_alertas'] ?? []);
        unset($_SESSION['parte_alertas']);
        $router->render('reportes/parte', [
            'titulo' => ($id ? 'Editar' : 'Nuevo') . ' parte diario - ' . $proyecto->nombre,
            'proyecto' => $proyecto, 'vista' => $vista, 'alertas' => $alertas,
            'puede_cerrar' => ParteDiario::puedeCerrar($proyecto),
            'puede_borrar' => puede_eliminar_contenido($proyecto),
        ]);
    }

    public static function guardarParte(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $json = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        $res = ParteDiario::guardar($proyecto, $_POST, $_FILES);
        if (!$res['ok']) {
            if ($json) json_respuesta(['success' => false, 'error' => implode(' · ', $res['errores'] ?: ['No se pudo guardar el parte'])], 422);
            $_SESSION['parte_post'] = $_POST;
            $_SESSION['parte_alertas'] = ['error' => $res['errores'] ?: ['No se pudo guardar el parte']];
            $id = (int)($res['id'] ?: ($_POST['id'] ?? 0));
            header('Location: /reportes/parte?proyecto=' . $pid . ($id ? '&id=' . $id : ''));
            exit;
        }
        if (($_POST['accion'] ?? '') === 'cerrar' && ParteDiario::puedeCerrar($proyecto)) {
            ParteDiario::cerrar($proyecto, (int)$res['id']);
        }
        $avisos = array_values(array_filter((array)($res['avisos'] ?? [])));
        if ($avisos) $_SESSION['parte_alertas'] = ['exito' => $avisos];
        if ($json) json_respuesta(['success' => true, 'id' => (int)$res['id'], 'avisos' => $avisos]);
        header('Location: /reportes/ver?proyecto=' . $pid . '&id=' . $res['id'] . '&ok=1');
        exit;
    }

    public static function cerrarParte()
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $id = (int)($_POST['id'] ?? 0);
        if (!ParteDiario::puedeCerrar($proyecto)) {
            http_response_code(403);
            exit('Solo el dueño del proyecto o el administrador pueden cerrar el parte.');
        }
        [$ok, $msg] = ParteDiario::cerrar($proyecto, $id);
        $_SESSION['parte_alertas'] = [($ok ? 'exito' : 'error') => [$msg]];
        header('Location: /reportes/ver?proyecto=' . $pid . '&id=' . $id);
        exit;
    }

    public static function reabrirParte()
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $id = (int)($_POST['id'] ?? 0);
        if (!ParteDiario::puedeCerrar($proyecto)) {
            http_response_code(403);
            exit('Solo el dueño del proyecto o el administrador pueden reabrir el parte.');
        }
        [$ok, $msg] = ParteDiario::reabrir($proyecto, $id);
        $_SESSION['parte_alertas'] = [($ok ? 'exito' : 'error') => [$msg]];
        header('Location: /reportes/ver?proyecto=' . $pid . '&id=' . $id);
        exit;
    }

    /** PDF de un solo parte (lo que firma el residente al cerrar) */
    public static function partePdf()
    {
        $proyecto = proyecto_de_peticion();
        $id = (int)($_GET['id'] ?? 0);
        $parte = ParteDiario::detalle($proyecto, $id);
        if (!$parte) { http_response_code(404); exit('Parte no encontrado'); }
        ob_start();
        include __DIR__ . '/../views/exportar/pdf-parte.php';
        $html = ob_get_clean();
        $pdf = \Classes\Pdf::generar($html, 'letter', 'portrait', ['isHtml5ParserEnabled' => true, 'marca' => $proyecto]);
        enviar_descarga($pdf, 'application/pdf', 'parte-diario-' . $parte['reporte']->fecha . '.pdf', true);
    }

    private static function formulario(Router $router, ReporteDiario $r)
    {
        $proyecto = proyecto_de_peticion(true);
        $alertas = [];
        $acts = self::actividades($proyecto);
        $personal = Personal::indexado((int)$proyecto->id);

        if (self::esPost()) {
            $fotosAnteriores = $r->getFotos();
            $r->proyecto_id = $proyecto->id;
            $r->fecha = trim($_POST['fecha'] ?? '');
            $r->area = trim($_POST['area'] ?? '');
            $r->comentarios = trim($_POST['comentarios'] ?? '');
            $r->setActividades(array_values(array_filter(array_map('intval', (array)($_POST['actividades'] ?? [])), fn($i) => isset($acts[$i]))));
            $r->setPersonal(array_values(array_filter(array_map('intval', (array)($_POST['personal'] ?? [])), fn($i) => isset($personal[$i]))));
            $alertas = $r->validar();

            // Fotos nuevas
            $nuevas = [];
            $libre = \Classes\Almacenamiento::disponible((int)$proyecto->id);
            $subidas = $_FILES['fotos'] ?? null;
            if ($subidas && is_array($subidas['tmp_name'])) {
                $n = 0;
                foreach ($subidas['tmp_name'] as $i => $tmp) {
                    if (!$tmp || !is_uploaded_file($tmp) || ++$n > self::MAX_FOTOS_SUBIDA) continue;
                    if ($subidas['size'][$i] > self::MAX_FOTO_BYTES) { $alertas['error'][] = 'Una foto supera los 15 MB y no se guardó'; continue; }
                    if ($subidas['size'][$i] > $libre) { $alertas['error'][] = \Classes\Almacenamiento::mensajeLleno((int)$proyecto->id); break; }
                    $nombre = Imagenes::guardarEn('reportes', $tmp);
                    if ($nombre) $libre -= (int)@filesize(__DIR__ . '/../public/uploads/reportes/' . $nombre);
                    if ($nombre) $nuevas[] = $nombre; else $alertas['error'][] = 'Un archivo no es una imagen válida (jpg, png, webp o gif)';
                }
            }

            if (!$alertas) {
                $quitar = array_map('basename', (array)($_POST['quitar_fotos'] ?? []));
                $conservadas = array_values(array_diff($fotosAnteriores, $quitar));
                foreach (array_intersect($fotosAnteriores, $quitar) as $f) Imagenes::eliminarEn('reportes', $f);
                $r->setFotos(array_merge($conservadas, $nuevas));
                $r->guardar();
                header('Location: /reportes?proyecto=' . $proyecto->id . '&ok=1');
                exit;
            }
            foreach ($nuevas as $f) Imagenes::eliminarEn('reportes', $f);   // no dejar archivos huérfanos si hubo errores
        }

        $router->render('reportes/formulario', [
            'titulo' => $r->id ? 'Editar reporte diario' : 'Nuevo reporte diario',
            'proyecto' => $proyecto, 'reporte' => $r, 'alertas' => $alertas,
            'actividades' => $acts, 'personal' => $personal,
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $r = ReporteDiario::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($r && (int)$r->proyecto_id === (int)$proyecto->id) {
            foreach ($r->getFotos() as $f) Imagenes::eliminarEn('reportes', $f);
            if ($r->firma_imagen) Imagenes::eliminarEn('reportes', $r->firma_imagen);
            $r->eliminar();
        }
        header('Location: /reportes?proyecto=' . $proyecto->id);
        exit;
    }

    /**
     * Actualiza desde el reporte el % de avance de las actividades que trabajó ese día. Solo cambia las que el usuario modificó; el valor queda anotado
     * en la fecha del reporte (Curva S real) y no en la de hoy.
     */
    public static function avance()
    {
        $proyecto = proyecto_de_peticion(true);
        $r = ReporteDiario::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$r || (int)$r->proyecto_id !== (int)$proyecto->id) { header('Location: /reportes?proyecto=' . $proyecto->id); exit; }
        $acts = self::actividades($proyecto);
        $db = $GLOBALS['db'];
        $fecha = $db->real_escape_string($r->fecha);
        $cambios = 0;
        foreach ((array)($_POST['avance'] ?? []) as $actId => $valor) {
            $actId = (int)$actId;
            if (!in_array($actId, $r->getActividades(), true) || !isset($acts[$actId]) || !is_numeric($valor)) continue;
            [$a] = $acts[$actId];
            if ($a->esHito()) continue;
            if ($a->tieneSubactividades()) continue;   // su avance es el promedio de las subactividades
            $nuevo = max(0, min(100, (int)round((float)$valor)));
            if ($nuevo === (int)$a->progreso) continue;
            $res = $db->query("SELECT progreso FROM actividad_avance WHERE actividad_id = $actId AND fecha = CURDATE()");
            $previoHoy = $res && ($f = $res->fetch_row()) ? (int)$f[0] : null;
            $a->progreso = $nuevo;
            $a->sincronizarStatus();
            if (!$a->guardar()) continue;
            $cambios++;
            if ($r->fecha !== date('Y-m-d')) {   // Actividad::guardar anotó hoy; el avance pertenece a la fecha del reporte
                $db->query("INSERT INTO actividad_avance (actividad_id, fecha, progreso) VALUES ($actId, '$fecha', " . (int)$a->progreso . ") ON DUPLICATE KEY UPDATE progreso = VALUES(progreso)");
                $db->query($previoHoy === null ? "DELETE FROM actividad_avance WHERE actividad_id = $actId AND fecha = CURDATE()" : "UPDATE actividad_avance SET progreso = $previoHoy WHERE actividad_id = $actId AND fecha = CURDATE()");
            }
        }
        header('Location: /reportes/ver?proyecto=' . $proyecto->id . '&id=' . $r->id . '&avance=' . $cambios);
        exit;
    }

    // ============================== PERSONAL ==============================

    /** A dónde volver tras editar personal: el Directorio si se llegó desde ahí */
    private static function listaPersonal(int $proyectoId, string $extra = ''): string
    {
        return (($_REQUEST['volver'] ?? '') === 'directorio' ? '/directorio?tab=personal&' : '/reportes/personal?') . 'proyecto=' . $proyectoId . $extra;
    }

    public static function personal(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('reportes/personal', ['titulo' => 'Personal - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'personal' => Personal::deProyecto((int)$proyecto->id)]);
    }

    public static function personalCrear(Router $router)
    {
        self::personalFormulario($router, new Personal());
    }

    public static function personalEditar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $p = Personal::find(filter_var((self::esPost() ? $_POST : $_GET)['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$p || (int)$p->proyecto_id !== (int)$proyecto->id) {
            header('Location: ' . self::listaPersonal((int)$proyecto->id));
            exit;
        }
        self::personalFormulario($router, $p);
    }

    private static function personalFormulario(Router $router, Personal $p)
    {
        $proyecto = proyecto_de_peticion(true);
        $alertas = [];
        if (self::esPost()) {
            $p->proyecto_id = $proyecto->id;
            $p->nombre = trim($_POST['nombre'] ?? '');
            $p->departamento = mb_substr(trim($_POST['departamento'] ?? ''), 0, 100);
            $p->tarifa_hora = max(0, round((float)($_POST['tarifa_hora'] ?? 0), 2));
            $alertas = $p->validar();
            $contacto = [];
            foreach (array_keys(Personal::CONTACTO) as $c) $contacto[$c] = $c === 'notas' ? trim((string)($_POST[$c] ?? '')) : trim(preg_replace('/\s+/u', ' ', (string)($_POST[$c] ?? '')));
            $contacto['rfc'] = mb_strtoupper(str_replace(' ', '', $contacto['rfc']));
            foreach (Personal::validarContacto($contacto) as $m) $alertas['error'][] = $m;
            $hayImg = !empty($_FILES['imagen']['tmp_name']) && is_uploaded_file($_FILES['imagen']['tmp_name']);
            $nueva = null;
            if (!$alertas && $hayImg && !\Classes\Almacenamiento::cabe((int)$proyecto->id, (int)$_FILES['imagen']['size'])) {
                $alertas['error'][] = \Classes\Almacenamiento::mensajeLleno((int)$proyecto->id);
            }
            if (!$alertas && $hayImg) {
                $nueva = Imagenes::guardarEn('personal', $_FILES['imagen']['tmp_name'], 600, 85);
                if (!$nueva) $alertas['error'][] = 'La imagen no es válida (jpg, png, webp o gif)';
            }
            if (!$alertas) {
                if ($nueva) { Imagenes::eliminarEn('personal', $p->imagen); $p->imagen = $nueva; }
                elseif (!empty($_POST['quitar_imagen'])) { Imagenes::eliminarEn('personal', $p->imagen); $p->imagen = null; }
                $r = $p->guardar();
                Personal::guardarContacto((int)($p->id ?: ($r['id'] ?? 0)), $contacto);
                header('Location: ' . self::listaPersonal((int)$proyecto->id, '&ok=1'));
                exit;
            }
        }
        $router->render('reportes/personal-formulario', [
            'titulo' => $p->id ? 'Editar personal' : 'Nuevo personal', 'proyecto' => $proyecto, 'persona' => $p, 'alertas' => $alertas, 'contacto' => $contacto ?? $p->contacto(),
            'departamentos' => \Classes\Catalogo::departamentos((int)$proyecto->id),
        ]);
    }

    public static function personalEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $p = Personal::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if ($p && (int)$p->proyecto_id === (int)$proyecto->id) {
            Imagenes::eliminarEn('personal', $p->imagen);
            $p->eliminar();   // los reportes ya guardados ignoran el id que ya no existe
        }
        header('Location: ' . self::listaPersonal((int)$proyecto->id));
        exit;
    }
}
