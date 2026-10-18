<?php

namespace Controllers;

use Model\Proyecto;
use Classes\Almacenamiento;
use Classes\Auditoria;
use Classes\CentroIA;
use Classes\Grabaciones;
use Classes\Normativa;

/** API JSON del Centro de IA para la app de escritorio: resultados guardados, espacio ocupado, pendientes y auditoría sin salir de Herramientas IA. Solo admin */
class AppIAController
{
    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function proyectoApi(): Proyecto
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $p = is_admin() ? Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    private static function suma(string $sql): int
    {
        try {
            $r = $GLOBALS['db']->query($sql);
            return (int)($r ? $r->fetch_row()[0] : 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Bytes que ocupa en la base de datos lo que guardan las herramientas de IA en el proyecto */
    private static function espacio(int $pid): array
    {
        $n = fn(string $c) => "IFNULL(LENGTH($c), 0)";
        $tablas = [
            'inconsistencias' => ['Inconsistencias', 'inconsistencias', ['titulo', 'descripcion', 'solucion_json', 'actividades_json', 'antes_json']],
            'materiales' => ['Análisis de materiales', 'analisis_materiales_hallazgos', ['titulo', 'descripcion', 'solucion_json', 'materiales_json', 'submittals_json', 'antes_json']],
            'programador' => ['Programador', 'programador_propuestas', ['resumen_json', 'filas_json', 'seguimiento_json', 'aceptadas_json']],
            'informes' => ['Informes de IA', 'informes_ia', ['titulo', 'parametros_json', 'contenido_json']],
            'inteligentes' => ['Materiales inteligentes', 'material_verificaciones', ['resumen', 'info']],
            'auditoria' => ['Auditoría (historial de cambios)', 'auditoria_cambios', ['resumen', 'entidad_nombre', 'antes_json', 'despues_json']],
        ];
        $out = [];
        foreach ($tablas as $k => [$titulo, $tabla, $cols]) {
            $out[] = ['clave' => $k, 'titulo' => $titulo, 'bytes' => self::suma("SELECT COALESCE(SUM(" . implode(' + ', array_map($n, $cols)) . "), 0) FROM $tabla WHERE proyecto_id = $pid")];
        }
        $out[] = ['clave' => 'grabaciones', 'titulo' => 'Grabaciones de reuniones (audio)', 'bytes' => Grabaciones::bytes($pid)];
        $out[] = ['clave' => 'normativa', 'titulo' => 'Normativa y especificaciones', 'bytes' => Normativa::bytes($pid)];
        return $out;
    }

    /** Resultados guardados + pendientes + espacio de un proyecto */
    public static function centro()
    {
        $p = self::proyectoApi();
        $pid = (int)$p->id;
        $esp = self::espacio($pid);
        $pend = CentroIA::pendientes($p);
        $lista = CentroIA::biblioteca($pid);
        foreach ($lista as &$x) $x['herramienta_titulo'] = CentroIA::HERRAMIENTAS[$x['herramienta']] ?? $x['herramienta'];
        $r = ['success' => true, 'biblioteca' => $lista, 'pendientes' => $pend['conteo'], 'espacio' => $esp, 'espacio_ia' => array_sum(array_column($esp, 'bytes'))];
        if (!empty($_GET['completo'])) $r += ['proyecto_usado' => Almacenamiento::usado($pid), 'proyecto_limite' => Almacenamiento::limite($pid)];   // recorre los archivos del proyecto: solo cuando se pide
        self::json($r);
    }

    public static function pendientes()
    {
        $p = self::proyectoApi();
        $r = CentroIA::pendientes($p, ['herramienta' => $_GET['herramienta'] ?? '', 'severidad' => $_GET['severidad'] ?? '']);
        self::json(['success' => true, 'items' => $r['items'], 'conteo' => $r['conteo']]);
    }

    /** Una sección del Análisis inteligente (conteo, segmentos y elementos) para la app de escritorio */
    public static function analisis()
    {
        $p = self::proyectoApi();
        $s = \Classes\AnalisisInteligente::seccion($p, (string)($_GET['clave'] ?? ''));
        if (!$s) self::json(['success' => false, 'error' => 'Tema desconocido'], 404);
        self::json(['success' => true, 'seccion' => $s]);
    }

    public static function auditoria()
    {
        $p = self::proyectoApi();
        $f = [];
        foreach (['herramienta', 'evento', 'usuario', 'entidad_tipo', 'entidad_id', 'desde', 'hasta', 'q'] as $k) $f[$k] = trim((string)($_GET[$k] ?? ''));
        $pid = (int)$p->id;
        $total = Auditoria::contar($pid, $f);
        $paginas = max(1, (int)ceil($total / Auditoria::POR_PAGINA));
        $pagina = max(1, min((int)($_GET['pagina'] ?? 1), $paginas));
        $filas = array_map(fn($x) => [
            'id' => (int)$x['id'], 'creado' => $x['creado'], 'usuario' => $x['usuario'], 'herramienta' => Auditoria::HERRAMIENTAS[$x['herramienta']] ?? $x['herramienta'], 'evento' => $x['evento'], 'evento_texto' => Auditoria::EVENTOS[$x['evento']] ?? $x['evento'],
            'entidad' => trim($x['entidad_tipo'] . ' ' . $x['entidad_nombre']), 'resumen' => $x['resumen'], 'modelo' => $x['modelo'],
        ], Auditoria::listar($pid, $f, $pagina));
        self::json(['success' => true, 'filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'usuarios' => Auditoria::usuarios($pid),
            'herramientas' => Auditoria::HERRAMIENTAS, 'eventos' => Auditoria::EVENTOS]);
    }

    /** accion = guardar | quitar | eliminar sobre un análisis de la biblioteca */
    public static function biblioteca()
    {
        $p = self::proyectoApi();
        $h = (string)($_POST['herramienta'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if (!isset(CentroIA::HERRAMIENTAS[$h]) || $id <= 0) self::json(['success' => false, 'error' => 'Análisis no válido'], 422);
        $accion = (string)($_POST['accion'] ?? '');
        if ($accion === 'eliminar') CentroIA::eliminar($p, $h, $id);
        elseif ($accion === 'guardar' || $accion === 'quitar') CentroIA::marcar($p, $h, $id, $accion === 'guardar', trim((string)($_POST['nombre'] ?? '')));
        else self::json(['success' => false, 'error' => 'Acción no válida'], 422);
        self::json(['success' => true]);
    }
}
