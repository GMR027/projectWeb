<?php

namespace Controllers;

use MVC\Router;
use Classes\HistorialCambios;

/**
 * Historial de cambios del proyecto (sección «Administración» del breadcrumb).
 * Lo ven admin, cliente y visitante (si el cliente le habilitó la herramienta): qué cambió, cuándo, en qué
 * herramienta y con qué valores de antes y después. Aceptar o cancelar es de admin y cliente dueño.
 */
class HistorialCambiosController
{
    /** Pestañas de la pantalla: clave = estado que muestran */
    public const PESTANAS = [
        'pendiente' => ['fa-inbox', 'Cambios pendientes'],
        'aceptado' => ['fa-circle-check', 'Cambios aceptados'],
        'revertido' => ['fa-rotate-left', 'Cambios revertidos'],
    ];

    /** Filtros que comparten la lista y su paginación (el estado lo pone la pestaña) */
    private static function filtros(): array
    {
        $f = [];
        foreach (['herramienta', 'evento', 'usuario', 'desde', 'hasta', 'q'] as $k) $f[$k] = trim((string)($_GET[$k] ?? ''));
        return $f;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();   // exige estar autenticado y poder ver el proyecto
        if (!herramienta_permitida('historial')) {
            header('Location: ' . url_inicio_proyecto((int)$proyecto->id));
            exit;
        }
        $pid = (int)$proyecto->id;
        $pestana = array_key_exists($_GET['pestana'] ?? '', self::PESTANAS) ? $_GET['pestana'] : 'pendiente';
        $f = self::filtros();

        $conteos = [];
        foreach (array_keys(self::PESTANAS) as $estado) $conteos[$estado] = HistorialCambios::contar($pid, $f + ['estado' => $estado]);
        $fActivo = $f + ['estado' => $pestana];
        $total = HistorialCambios::contar($pid, $fActivo);
        $pagina = max(1, min((int)($_GET['pagina'] ?? 1), max(1, (int)ceil($total / HistorialCambios::POR_PAGINA))));
        $filas = HistorialCambios::listar($pid, $fActivo, $pagina);
        foreach ($filas as &$x) $x['enlace'] = HistorialCambios::enlace($x, $pid);

        $router->render('historial/index', [
            'titulo' => 'Historial de cambios - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'pestana' => $pestana,
            'filtros' => $f,
            'filas' => $filas,
            'conteos' => $conteos,
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => max(1, (int)ceil($total / HistorialCambios::POR_PAGINA)),
            'usuarios' => HistorialCambios::usuarios($pid),
            'herramientas' => HistorialCambios::herramientas(),
            'puede' => puede_gestionar_proyecto($proyecto),
            'mensaje' => $_SESSION['historial_msg'] ?? null,
        ]);
        unset($_SESSION['historial_msg']);
    }

    /** Proyecto para las acciones, exigiendo además que la herramienta esté habilitada en el acceso */
    private static function proyectoAccion(): \Model\Proyecto
    {
        $p = proyecto_api('ver');
        if (!herramienta_permitida('historial')) json_respuesta(['success' => false, 'error' => 'Esa herramienta no está habilitada para tu acceso'], 403);
        if (!puede_gestionar_proyecto($p)) json_respuesta(['success' => false, 'error' => 'Solo el administrador y el cliente dueño del proyecto aceptan o cancelan cambios'], 403);
        return $p;
    }

    /** Acepta uno (id) o todos los pendientes que coincidan con el filtro (`todo=1`) */
    public static function aceptar()
    {
        $p = self::proyectoAccion();
        $pid = (int)$p->id;
        if (($_POST['todo'] ?? '') === '1') {
            $f = self::filtros();
            $n = HistorialCambios::aceptarTodos($pid, $f + ['estado' => 'pendiente']);
            json_respuesta(['success' => true, 'mensaje' => $n ? "$n cambio(s) pasaron a «Cambios aceptados»" : 'No había cambios pendientes con ese filtro']);
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) json_respuesta(['success' => false, 'error' => 'Falta el cambio a aceptar'], 422);
        if (!HistorialCambios::aceptar($pid, $id)) json_respuesta(['success' => false, 'error' => 'No se pudo aceptar (tal vez ya no estaba pendiente)'], 422);
        json_respuesta(['success' => true, 'mensaje' => 'Cambio aceptado']);
    }

    /** Cancela el cambio: revierte los datos y deja constancia de la reversión */
    public static function cancelar()
    {
        $p = self::proyectoAccion();
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) json_respuesta(['success' => false, 'error' => 'Falta el cambio a cancelar'], 422);
        $r = HistorialCambios::revertir((int)$p->id, $id);
        json_respuesta(['success' => $r['ok'], 'mensaje' => $r['ok'] ? 'Cambio cancelado: los datos volvieron a como estaban' : null, 'error' => $r['error'] ?? null, 'nota' => $r['nota'] ?? null],
            $r['ok'] ? 200 : 422);
    }

    /** Descarga el listado filtrado (Excel o PDF), igual que en el resto del sistema */
    public static function exportar()
    {
        $p = proyecto_de_peticion();
        if (!herramienta_permitida('historial')) json_respuesta(['success' => false, 'error' => 'Esa herramienta no está habilitada para tu acceso'], 403);
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'Solo el administrador y el cliente pueden descargar'], 403);
        $f = self::filtros() + ['estado' => in_array($_GET['pestana'] ?? '', array_keys(self::PESTANAS), true) ? $_GET['pestana'] : 'pendiente'];
        $pid = (int)$p->id;
        $filas = HistorialCambios::listar($pid, $f, 1, 2000);

        if (($_GET['formato'] ?? '') === 'pdf') {
            $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:8px;color:#111}h1{font-size:14px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:2px 4px;text-align:left;vertical-align:top}th{background:#e8ecf1}</style></head><body>'
                . '<h1>Historial de cambios · ' . $e($p->nombre) . '</h1><p>Generado el ' . date('d/m/Y H:i') . '</p><table><tr><th>Fecha</th><th>Usuario</th><th>Herramienta</th><th>Evento</th><th>Estado</th><th>Elemento</th><th>Cambio</th></tr>';
            foreach ($filas as $x) {
                $html .= '<tr><td>' . $e(date('d/m/Y H:i', strtotime($x['creado']))) . '</td><td>' . $e($x['usuario'] ?: '—') . '</td><td>'
                    . $e(HistorialCambios::herramientas()[$x['herramienta']] ?? $x['herramienta']) . '</td><td>'
                    . $e(HistorialCambios::EVENTOS[$x['evento']] ?? $x['evento']) . '</td><td>'
                    . $e(HistorialCambios::ESTADOS[$x['estado']] ?? $x['estado']) . '</td><td>' . $e($x['entidad_nombre'] ?: '—') . '</td><td>' . $e($x['resumen']) . '</td></tr>';
            }
            enviar_descarga(\Classes\Pdf::generar($html . '</table></body></html>', 'letter', 'landscape'),
                'application/pdf', 'historial-cambios-' . $p->codigo . '-' . date('Y-m-d') . '.pdf');
        }

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Historial de cambios');
        $datos = [['Fecha', 'Usuario', 'Herramienta', 'Evento', 'Estado', 'Elemento', 'Cambio']];
        foreach ($filas as $x) {
            $datos[] = [date('d/m/Y H:i', strtotime($x['creado'])), $x['usuario'] ?: '—', HistorialCambios::herramientas()[$x['herramienta']] ?? $x['herramienta'],
                HistorialCambios::EVENTOS[$x['evento']] ?? $x['evento'], HistorialCambios::ESTADOS[$x['estado']] ?? $x['estado'], $x['entidad_nombre'] ?: '—', $x['resumen']];
        }
        $hoja->fromArray($datos, null, 'A1');
        $hoja->getStyle('A1:G1')->getFont()->setBold(true);
        foreach (range('A', 'G') as $c) $hoja->getColumnDimension($c)->setAutoSize(true);
        $hoja->freezePane('A2');
        $tmp = tempnam(sys_get_temp_dir(), 'hist');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($tmp);
        $bin = (string)file_get_contents($tmp);
        @unlink($tmp);
        unset($libro);
        enviar_descarga($bin, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'historial-cambios-' . $p->codigo . '-' . date('Y-m-d') . '.xlsx');
    }
}
