<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Personal;
use Model\ReporteDiario;
use Classes\Imagenes;

/** Reporte diario de obra y catálogo de personal del proyecto */
class ReporteController
{
    private const MAX_FOTOS_SUBIDA = 20;
    private const MAX_FOTO_BYTES = 15 * 1024 * 1024;

    /** [id => [Actividad, Partida]] de todas las actividades del programa del proyecto */
    private static function actividades(Proyecto $proyecto): array
    {
        $r = [];
        foreach ($proyecto->getPartidas() as $p) {
            foreach ($p->getActividades() as $a) $r[(int)$a->id] = [$a, $p];
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
        $router->render('reportes/index', ['titulo' => 'Reporte diario - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filas' => $filas]);
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $r = ReporteDiario::find(filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$r || (int)$r->proyecto_id !== (int)$proyecto->id) {
            header('Location: /reportes?proyecto=' . $proyecto->id);
            exit;
        }
        $acts = self::actividades($proyecto);
        $personal = Personal::indexado((int)$proyecto->id);
        $router->render('reportes/ver', [
            'titulo' => 'Reporte del ' . fecha_corta_es($r->fecha) . ' - ' . $proyecto->nombre,
            'proyecto' => $proyecto, 'reporte' => $r,
            'actividades' => array_values(array_filter(array_map(fn($id) => $acts[$id] ?? null, $r->getActividades()))),
            'personal' => array_values(array_filter(array_map(fn($id) => $personal[$id] ?? null, $r->getPersonal()))),
        ]);
    }

    public static function crear(Router $router)
    {
        self::formulario($router, new ReporteDiario());
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $fuente = self::esPost() ? $_POST : $_GET;
        $r = ReporteDiario::find(filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$r || (int)$r->proyecto_id !== (int)$proyecto->id) {
            header('Location: /reportes?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $r);
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
            $r->eliminar();
        }
        header('Location: /reportes?proyecto=' . $proyecto->id);
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
