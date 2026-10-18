<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Normativa;

/** Normativa y especificaciones: documentos PDF indexados, vínculos con materiales y actividades. Ver: quien tiene acceso al proyecto; gestionar: admin / dueño */
class NormativaController
{
    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function gestion(): Proyecto
    {
        $p = proyecto_de_peticion(true);
        return $p;
    }

    private static function gestionApi(): Proyecto
    {
        $p = Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT));
        if (!$p || !is_auth() || !puede_gestionar_proyecto($p)) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $q = trim((string)($_GET['q'] ?? ''));
        $router->render('normativa/index', ['titulo' => 'Normativa y especificaciones - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'documentos' => Normativa::listar($pid), 'pendientes' => Normativa::conteoPendientes($pid),
            'puede' => puede_gestionar_proyecto($proyecto), 'q' => $q, 'resultados' => $q !== '' ? Normativa::buscar($pid, \Classes\Glosario::palabras($q), 15) : [], 'mensaje' => $_SESSION['normativa_msg'] ?? null]);
        unset($_SESSION['normativa_msg']);
    }

    public static function subir()
    {
        $p = self::gestionApi();
        $f = $_FILES['archivos'] ?? [];
        if (!is_array($f['name'] ?? null)) $f = [];
        [$ok, $fallos, $avisos] = $f ? Normativa::subirLote($p, $f, (array)($_POST['titulo'] ?? []), (array)($_POST['codigo'] ?? []), (array)($_POST['tipo'] ?? [])) : [0, 1, ['No llegó ningún archivo.']];
        $texto = $ok ? "Se subieron e indexaron $ok documento" . ($ok === 1 ? '' : 's') . '.' : 'No se subió ningún documento.';
        if ($avisos) $texto .= ' ' . implode(' ', array_slice($avisos, 0, 5));
        $_SESSION['normativa_msg'] = ['ok' => $ok > 0 && !$fallos, 'texto' => $texto];
        header('Location: /normativa?proyecto=' . (int)$p->id);
        exit;
    }

    public static function documento(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $d = Normativa::obtener((int)$proyecto->id, (int)($_GET['id'] ?? 0));
        if (!$d) { header('Location: /normativa?proyecto=' . (int)$proyecto->id); exit; }
        $q = trim((string)($_GET['q'] ?? ''));
        $router->render('normativa/documento', ['titulo' => $d['titulo'] . ' - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'doc' => $d, 'fragmentos' => Normativa::fragmentos((int)$d['id'], $q), 'q' => $q, 'puede' => puede_gestionar_proyecto($proyecto)]);
    }

    /** Entrega el PDF original (con control de acceso; fuera de public) */
    public static function pdf()
    {
        $p = proyecto_de_peticion();
        $d = Normativa::obtener((int)$p->id, (int)($_GET['id'] ?? 0));
        $ruta = $d ? Normativa::ruta($d) : '';
        if (!$d || !is_file($ruta)) { http_response_code(404); exit('Documento no encontrado'); }
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $esPdf = $ext === 'pdf';
        header('Content-Type: ' . ($esPdf ? 'application/pdf' : ($ext === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/msword')));
        header('Content-Disposition: ' . ($esPdf ? 'inline' : 'attachment') . '; filename="' . slug_archivo($d['titulo']) . '.' . $ext . '"');
        header('Content-Length: ' . filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        readfile($ruta);
        exit;
    }

    public static function reindexar()
    {
        $p = self::gestionApi();
        $d = Normativa::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        $_SESSION['normativa_msg'] = $d ? (fn($r) => ['ok' => $r[0], 'texto' => $r[1]])(Normativa::indexar((int)$d['id'])) : ['ok' => false, 'texto' => 'Documento no encontrado'];
        header('Location: /normativa?proyecto=' . (int)$p->id);
        exit;
    }

    public static function eliminar()
    {
        $p = self::gestionApi();
        Normativa::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        $_SESSION['normativa_msg'] = ['ok' => true, 'texto' => 'Documento eliminado junto con sus cláusulas y los vínculos que tenía.'];
        header('Location: /normativa?proyecto=' . (int)$p->id);
        exit;
    }

    public static function vinculos(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $f = ['estado' => $_GET['estado'] ?? 'propuesta', 'entidad_tipo' => $_GET['tipo'] ?? ''];
        $router->render('normativa/vinculos', ['titulo' => 'Vínculos de normativa - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $f, 'lista' => Normativa::aplicaciones((int)$proyecto->id, $f), 'puede' => puede_gestionar_proyecto($proyecto)]);
    }

    public static function resolver()
    {
        $p = self::gestionApi();
        [$ok, $msg] = Normativa::resolver($p, (int)($_POST['id'] ?? 0), (string)($_POST['estado'] ?? ''));
        self::json(['success' => $ok, 'mensaje' => $msg, 'pendientes' => Normativa::conteoPendientes((int)$p->id)], $ok ? 200 : 422);
    }

    public static function quitar()
    {
        $p = self::gestionApi();
        $ok = Normativa::quitar($p, (int)($_POST['id'] ?? 0));
        self::json(['success' => $ok], $ok ? 200 : 404);
    }

    /** Vincula a mano desde el formulario de una actividad o un material ya guardados */
    public static function agregar()
    {
        $p = self::gestionApi();
        $tipo = in_array($_POST['tipo'] ?? '', ['actividad', 'material'], true) ? $_POST['tipo'] : '';
        $ok = $tipo && Normativa::agregarManual((int)$p->id, $tipo, (int)($_POST['entidad'] ?? 0), ((int)($_POST['documento'] ?? 0)) ?: null, (string)($_POST['referencia'] ?? ''), (string)($_POST['nota'] ?? ''));
        self::json(['success' => $ok, 'error' => $ok ? '' : 'Indica la referencia o el documento'], $ok ? 200 : 422);
    }

    /** Sugiere (sin IA) vínculos para una sola actividad o material */
    public static function sugerir()
    {
        $p = self::gestionApi();
        $tipo = in_array($_POST['tipo'] ?? '', ['actividad', 'material'], true) ? $_POST['tipo'] : '';
        $id = (int)($_POST['entidad'] ?? 0);
        $n = 0;
        if ($tipo && Normativa::entidad((int)$p->id, $tipo, $id)) foreach (Normativa::candidatos((int)$p->id, $tipo, $id, 3) as $c) if ($c['origen'] === 'csi' || $c['puntaje'] >= 40) if (Normativa::proponerRegla((int)$p->id, $tipo, $id, $c)) $n++;
        self::json(['success' => true, 'propuestas' => $n]);
    }
}
