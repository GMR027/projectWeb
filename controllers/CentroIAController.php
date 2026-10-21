<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Auditoria;
use Classes\CentroIA;

/** Centro de IA: bandeja de propuestas pendientes, historial de cambios (auditoría) y biblioteca de análisis guardados. Solo admin */
class CentroIAController
{
    private static function admin(): Proyecto
    {
        $proyecto = proyecto_de_peticion();
        if (!is_admin()) { http_response_code(403); exit('Solo el administrador puede usar el Centro de IA'); }
        return $proyecto;
    }

    private static function proyectoApi(): Proyecto
    {
        $p = is_admin() ? Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    // ---------- bandeja ----------

    public static function bandeja(Router $router)
    {
        $proyecto = self::admin();
        $f = ['herramienta' => (string)($_GET['herramienta'] ?? ''), 'severidad' => (string)($_GET['severidad'] ?? ''), 'origen' => (string)($_GET['origen'] ?? '')];
        $r = CentroIA::pendientes($proyecto, $f);
        $router->render('centro-ia/bandeja', ['titulo' => 'Propuestas de IA - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $f, 'items' => $r['items'], 'conteo' => $r['conteo']]);
    }

    public static function aceptar()
    {
        $p = self::proyectoApi();
        [$ok, $msg] = CentroIA::aceptar($p, (string)($_POST['herramienta'] ?? ''), (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => $ok, 'mensaje' => $msg], $ok ? 200 : 422);
    }

    public static function rechazar()
    {
        $p = self::proyectoApi();
        [$ok, $msg] = CentroIA::rechazar($p, (string)($_POST['herramienta'] ?? ''), (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => $ok, 'mensaje' => $msg], $ok ? 200 : 422);
    }

    // ---------- historial ----------

    private static function filtrosHistorial(): array
    {
        $f = [];
        foreach (['herramienta', 'evento', 'usuario', 'entidad_tipo', 'entidad_id', 'desde', 'hasta', 'q'] as $k) $f[$k] = trim((string)($_GET[$k] ?? ''));
        return $f;
    }

    public static function historial(Router $router)
    {
        $proyecto = self::admin();
        $f = self::filtrosHistorial();
        $pid = (int)$proyecto->id;
        $total = Auditoria::contar($pid, $f);
        $pagina = max(1, min((int)($_GET['pagina'] ?? 1), max(1, (int)ceil($total / Auditoria::POR_PAGINA))));
        $router->render('centro-ia/historial', ['titulo' => 'Historial de cambios - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $f, 'filas' => Auditoria::listar($pid, $f, $pagina), 'total' => $total,
            'pagina' => $pagina, 'paginas' => max(1, (int)ceil($total / Auditoria::POR_PAGINA)), 'usuarios' => Auditoria::usuarios($pid)]);
    }

    public static function exportarHistorial()
    {
        $p = self::admin();
        $f = self::filtrosHistorial();
        $base = date('Y_m_d') . '_' . slug_archivo(usuario_actual()['nombre'] ?? 'historial');
        if (($_GET['formato'] ?? '') === 'pdf') enviar_descarga(Auditoria::pdf($p, $f), 'application/pdf', $base . '.pdf');
        enviar_descarga(Auditoria::xlsx($p, $f), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $base . '.xlsx');
    }

    // ---------- biblioteca ----------

    public static function biblioteca(Router $router)
    {
        $proyecto = self::admin();
        $f = ['herramienta' => $_GET['herramienta'] ?? '', 'solo_guardadas' => !empty($_GET['guardadas'])];
        $comparacion = null;
        $h = (string)($_GET['cmp_herramienta'] ?? '');
        $a = (int)($_GET['a'] ?? 0);
        $b = (int)($_GET['b'] ?? 0);
        if ($a && $b && $a !== $b) $comparacion = CentroIA::comparar((int)$proyecto->id, $h, $a, $b);
        $router->render('centro-ia/biblioteca', ['titulo' => 'Biblioteca de análisis - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $f, 'lista' => CentroIA::biblioteca((int)$proyecto->id, $f),
            'comparacion' => $comparacion, 'cmp' => ['herramienta' => $h, 'a' => $a, 'b' => $b], 'mensaje' => $_SESSION['biblioteca_msg'] ?? null]);
        unset($_SESSION['biblioteca_msg']);
    }

    public static function guardarAnalisis()
    {
        $p = self::proyectoApi();
        CentroIA::marcar($p, (string)($_POST['herramienta'] ?? ''), (int)($_POST['id'] ?? 0), empty($_POST['quitar']), (string)($_POST['nombre'] ?? ''));
        $_SESSION['biblioteca_msg'] = ['ok' => true, 'texto' => empty($_POST['quitar']) ? 'Análisis guardado en la biblioteca.' : 'El análisis ya no está guardado (puede rotar con los más recientes).'];
        header('Location: /biblioteca-ia?proyecto=' . (int)$p->id);
        exit;
    }

    public static function eliminarAnalisis()
    {
        $p = self::proyectoApi();
        CentroIA::eliminar($p, (string)($_POST['herramienta'] ?? ''), (int)($_POST['id'] ?? 0));
        $_SESSION['biblioteca_msg'] = ['ok' => true, 'texto' => 'Análisis eliminado. Los cambios ya aplicados al proyecto se conservan en el historial.'];
        header('Location: /biblioteca-ia?proyecto=' . (int)$p->id);
        exit;
    }
}
