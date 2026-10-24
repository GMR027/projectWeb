<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Auditoria;
use Classes\CentroIA;
use Classes\Inconsistencias;
use Classes\ResumenIA;
use Classes\RiesgoIA;
use Classes\TandaIA;
use Classes\UsoIA;
use Classes\VigilanciaServidor;

/** Centro de IA: bandeja de propuestas pendientes, historial de cambios (auditoría) y biblioteca de análisis guardados. Solo admin */
class CentroIAController
{
    private static function admin(): Proyecto
    {
        $proyecto = proyecto_de_peticion();
        if (!is_admin()) { http_response_code(403); exit('Solo el administrador puede usar el Centro de IA'); }
        return $proyecto;
    }

    /** Fase 5.3: comparación de modelos con la batería de casos fijos */
    public static function calidad(Router $router)
    {
        $proyecto = self::admin();
        $router->render('centro-ia/calidad', ['titulo' => 'Calidad de la IA - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'cmp' => \Classes\PruebasIA::comparar((int)$proyecto->id), 'casos' => \Classes\PruebasIA::casos()]);
    }

    // ---------- bandeja ----------

    public static function bandeja(Router $router)
    {
        $proyecto = self::admin();
        $f = ['herramienta' => (string)($_GET['herramienta'] ?? ''), 'severidad' => (string)($_GET['severidad'] ?? ''), 'origen' => (string)($_GET['origen'] ?? ''), 'riesgo' => (string)($_GET['riesgo'] ?? ''), 'partida' => (string)($_GET['partida'] ?? '')];
        $r = CentroIA::pendientes($proyecto, $f);
        $router->render('centro-ia/bandeja', ['titulo' => 'Propuestas de IA - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $f, 'items' => $r['items'], 'conteo' => $r['conteo'], 'partidas' => $r['partidas'], 'bajoRiesgo' => $r['bajo_riesgo'],
            'niveles' => RiesgoIA::niveles((int)$proyecto->id), 'mensaje' => $_SESSION['bandeja_msg'] ?? null]);
        unset($_SESSION['bandeja_msg']);
    }

    public static function aceptar()
    {
        $p = proyecto_api('admin');
        [$ok, $msg] = CentroIA::aceptar($p, (string)($_POST['herramienta'] ?? ''), (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => $ok, 'mensaje' => $msg], $ok ? 200 : 422);
    }

    public static function rechazar()
    {
        $p = proyecto_api('admin');
        [$ok, $msg] = CentroIA::rechazar($p, (string)($_POST['herramienta'] ?? ''), (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => $ok, 'mensaje' => $msg], $ok ? 200 : 422);
    }

    /** Acepta de una vez las de bajo riesgo que cumplen los filtros de la bandeja */
    public static function aceptarLote()
    {
        $p = proyecto_api('admin');
        $f = [];
        foreach (['herramienta', 'severidad', 'origen', 'partida'] as $k) $f[$k] = trim((string)($_POST[$k] ?? ''));
        $r = CentroIA::aceptarLote($p, $f);
        $txt = $r['aplicadas'] . ' propuesta(s) de bajo riesgo aplicada(s)' . ($r['fallos'] ? ' · ' . count($r['fallos']) . ' no se pudieron aplicar' : '') . ($r['omitidas'] ? ' · ' . $r['omitidas'] . ' quedan para otra tanda' : '');
        json_respuesta(['success' => true, 'mensaje' => $txt] + $r);
    }

    /** Niveles de riesgo de cada acción: simple (se acepta por lote) o pide permiso (una por una) */
    public static function guardarRiesgo()
    {
        $p = proyecto_api('admin');
        $ok = RiesgoIA::guardar((int)$p->id, (array)($_POST['nivel'] ?? []));
        $_SESSION['bandeja_msg'] = ['ok' => $ok, 'texto' => $ok ? 'Niveles de riesgo guardados.' : 'No se pudieron guardar (¿falta la migración ia_fase2?).'];
        header('Location: /propuestas-ia?proyecto=' . (int)$p->id . '#riesgo');
        exit;
    }

    // ---------- resumen diario ----------

    public static function resumen(Router $router)
    {
        $proyecto = self::admin();
        $pid = (int)$proyecto->id;
        VigilanciaServidor::ejecutarPendientes('web', false, $pid);   // sin cron, la primera visita después de vencido el intervalo hace la comprobación
        $horas = isset(ResumenIA::PERIODOS[(int)($_GET['horas'] ?? 24)]) ? (int)$_GET['horas'] : 24;
        $router->render('centro-ia/resumen', ['titulo' => 'Resumen de IA - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'horas' => $horas, 'r' => ResumenIA::datos($proyecto, $horas), 'mensaje' => $_SESSION['resumen_msg'] ?? null]);
        unset($_SESSION['resumen_msg']);
    }

    /** Vigilancia del servidor: activar/intervalo, comprobar ahora y marcar lo nuevo como visto */
    public static function vigilancia()
    {
        $p = proyecto_api('admin');
        $pid = (int)$p->id;
        $accion = (string)($_POST['accion'] ?? 'guardar');
        if ($accion === 'ahora') {
            VigilanciaServidor::comprobar($p, 'web');
            $txt = 'Proyecto comprobado.';
        } elseif ($accion === 'visto') {
            Inconsistencias::vigilar($p, true);
            $txt = 'Lo detectado quedó como visto: la próxima comprobación se compara con el estado de hoy.';
        } else {
            $ok = VigilanciaServidor::guardar($pid, !empty($_POST['activa']), (int)($_POST['minutos'] ?? 60));
            $txt = $ok ? (!empty($_POST['activa']) ? 'Vigilancia del servidor activada.' : 'Vigilancia del servidor desactivada.') : 'No se pudo guardar (¿falta la migración ia_fase2?).';
        }
        $_SESSION['resumen_msg'] = ['ok' => true, 'texto' => $txt];
        header('Location: /resumen-ia?proyecto=' . $pid . '#vigilancia');
        exit;
    }

    // ---------- uso de la IA ----------

    public static function uso(Router $router)
    {
        $proyecto = self::admin();
        $dias = in_array((int)($_GET['dias'] ?? 7), [1, 7, 30, 90], true) ? (int)$_GET['dias'] : 7;
        $router->render('centro-ia/uso', ['titulo' => 'Uso de la IA - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'dias' => $dias, 'u' => UsoIA::resumen((int)$proyecto->id, $dias)]);
    }

    /** La app de escritorio informa por lotes las llamadas que hizo al modelo */
    public static function registrarUso()
    {
        if (!is_admin()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $r = json_decode((string)($_POST['registros'] ?? '[]'), true);
        json_respuesta(['success' => true, 'guardadas' => is_array($r) ? UsoIA::registrar($r, usuario_actual()['nombre'] ?? '') : 0]);
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
        $router->render('centro-ia/historial', ['titulo' => 'Auditoría de la IA - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $f, 'filas' => Auditoria::listar($pid, $f, $pagina), 'total' => $total,
            'pagina' => $pagina, 'paginas' => max(1, (int)ceil($total / Auditoria::POR_PAGINA)), 'usuarios' => Auditoria::usuarios($pid)]);
    }

    /** Revertir por tanda: con modo=vista lista lo revertible desde la hora indicada; con modo=aplicar lo revierte */
    public static function revertirTanda()
    {
        $p = proyecto_api('admin');
        $desde = TandaIA::fecha((string)($_POST['desde'] ?? ''));
        if (!$desde) json_respuesta(['success' => false, 'error' => 'Indica desde qué fecha y hora revertir'], 422);
        if (($_POST['modo'] ?? 'vista') !== 'aplicar') json_respuesta(['success' => true, 'desde' => $desde, 'items' => TandaIA::candidatos((int)$p->id, $desde)]);
        $ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : null;
        $r = TandaIA::aplicar($p, $desde, $ids);
        json_respuesta(['success' => true, 'mensaje' => $r['revertidos'] . ' cambio(s) revertido(s)' . ($r['fallos'] ? ' · ' . count($r['fallos']) . ' no se pudieron revertir' : '')] + $r);
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
        $p = proyecto_api('admin');
        CentroIA::marcar($p, (string)($_POST['herramienta'] ?? ''), (int)($_POST['id'] ?? 0), empty($_POST['quitar']), (string)($_POST['nombre'] ?? ''));
        $_SESSION['biblioteca_msg'] = ['ok' => true, 'texto' => empty($_POST['quitar']) ? 'Análisis guardado en la biblioteca.' : 'El análisis ya no está guardado (puede rotar con los más recientes).'];
        header('Location: /biblioteca-ia?proyecto=' . (int)$p->id);
        exit;
    }

    public static function eliminarAnalisis()
    {
        $p = proyecto_api('admin');
        CentroIA::eliminar($p, (string)($_POST['herramienta'] ?? ''), (int)($_POST['id'] ?? 0));
        $_SESSION['biblioteca_msg'] = ['ok' => true, 'texto' => 'Análisis eliminado. Los cambios ya aplicados al proyecto se conservan en el historial.'];
        header('Location: /biblioteca-ia?proyecto=' . (int)$p->id);
        exit;
    }
}
