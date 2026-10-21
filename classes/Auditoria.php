<?php

namespace Classes;

/**
 * Historial de cambios global: cada acción que aplican, revierten o rechazan las herramientas de IA deja una fila que no se edita.
 * Si la tabla aún no existe (migración pendiente) registrar() no rompe la acción que lo llamó.
 */
class Auditoria
{
    use AccesoDb;

    public const HERRAMIENTAS = ['programador' => 'Programador de actividades', 'inconsistencias' => 'Inconsistencias de proyecto', 'materiales' => 'Análisis de materiales', 'informes' => 'Informes de IA', 'normativa' => 'Normativa y especificaciones', 'seguridad' => 'Seguridad y permisos', 'punch' => 'Punch list', 'plantillas' => 'Plantillas por tipo de obra', 'ensamblador' => 'Ensamblador de documentos', 'ordenar' => 'Ordenar y renumerar', 'tabla-documento' => 'Tabla desde documento'];
    public const EVENTOS = ['aplicado' => 'Aplicado', 'revertido' => 'Revertido', 'rechazado' => 'Rechazado', 'reabierto' => 'Reabierto'];
    public const POR_PAGINA = 50;

    /** Qué entidad toca una acción: [tipo, id, nombre] */
    public static function entidad(int $pid, string $accion, array $p): array
    {
        $uno = fn(string $sql) => self::filas($sql)[0] ?? null;
        switch ($accion) {
            case 'ajustar_fechas':
            case 'sincronizar_status':
            case 'ajustar_progreso':
            case 'ajustar_duracion':
            case 'renombrar_actividad':
            case 'editar_descripcion':
                $id = (int)($p['actividad_id'] ?? 0);
                $a = $uno("SELECT a.codigo, a.nombre FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $id AND pa.proyecto_id = $pid");
                return ['actividad', $id, $a ? $a['codigo'] . ' ' . $a['nombre'] : ''];
            case 'recalcular_partida':
            case 'aceptar_partida':
                $id = (int)($p['partida_id'] ?? 0);
                $a = $uno("SELECT codigo, nombre FROM partidas WHERE id = $id AND proyecto_id = $pid");
                return ['partida', $id, $a ? $a['codigo'] . ' ' . $a['nombre'] : ''];
            case 'marcar_comprado':
            case 'renombrar_material':
            case 'sincronizar_msr_material':
                $id = (int)($p['material_id'] ?? 0);
                $a = $uno("SELECT nombre FROM materiales WHERE id = $id AND proyecto_id = $pid");
                return ['material', $id, $a['nombre'] ?? ''];
            case 'sincronizar_msr_submittal':
                $id = (int)($p['submittal_id'] ?? 0);
                $a = $uno("SELECT COALESCE(NULLIF(numero_submittal,''), CONCAT('#', id)) n FROM submittals WHERE id = $id AND proyecto_id = $pid");
                return ['submittal', $id, $a['n'] ?? ''];
            case 'resolver_restriccion':
                $id = (int)($p['restriccion_id'] ?? 0);
                $a = $uno("SELECT LEFT(descripcion, 120) d FROM restricciones WHERE id = $id");
                return ['restriccion', $id, $a['d'] ?? ''];
            case 'quitar_dependencia':
            case 'agregar_dependencia':
                $a = $uno('SELECT p.codigo pc, s.codigo sc FROM actividades p, actividades s WHERE p.id = ' . (int)($p['pred'] ?? 0) . ' AND s.id = ' . (int)($p['suc'] ?? 0));
                return ['dependencia', (int)($p['suc'] ?? 0), $a ? $a['pc'] . ' → ' . $a['sc'] : ''];
            case 'sincronizar_instalacion':
                return ['proyecto', $pid, 'Instalación del MSR'];
            case 'agregar_dia_no_habil':
                return ['calendario', null, (string)($p['fecha'] ?? '')];
        }
        return ['', null, ''];
    }

    /** Anota un evento. Nunca lanza: la auditoría no debe impedir la acción */
    public static function registrar(int $pid, string $herramienta, string $evento, string $accion, string $resumen, ?array $antes = null, ?array $despues = null, ?int $referencia = null, string $modelo = '', ?array $entidad = null): void
    {
        try {
            $db = self::db();
            [$tipo, $eid, $nombre] = $entidad ?? self::entidad($pid, $accion, $despues ?? []);
            $esc = fn($v) => $db->real_escape_string((string)$v);
            $js = fn($v) => $v === null ? 'NULL' : "'" . $esc(json_encode($v, JSON_UNESCAPED_UNICODE)) . "'";
            $db->query("INSERT INTO auditoria_cambios (proyecto_id, herramienta, evento, accion, entidad_tipo, entidad_id, entidad_nombre, resumen, antes_json, despues_json, referencia_id, modelo, usuario) VALUES ($pid, '{$esc($herramienta)}', '{$esc($evento)}', '{$esc($accion)}', '{$esc($tipo)}', "
                . ($eid === null ? 'NULL' : (int)$eid) . ", '" . $esc(mb_substr((string)$nombre, 0, 255)) . "', '" . $esc(mb_substr($resumen, 0, 500)) . "', {$js($antes)}, {$js($despues)}, " . ($referencia === null ? 'NULL' : (int)$referencia) . ", '" . $esc(mb_substr($modelo, 0, 150)) . "', '" . $esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "')");
        } catch (\Throwable $e) {
            error_log('Auditoria::registrar: ' . $e->getMessage());
        }
    }

    private static function donde(int $pid, array $f): string
    {
        $db = self::db();
        $w = ["proyecto_id = $pid"];
        if (isset(self::HERRAMIENTAS[$f['herramienta'] ?? ''])) $w[] = "herramienta = '{$f['herramienta']}'";
        if (isset(self::EVENTOS[$f['evento'] ?? ''])) $w[] = "evento = '{$f['evento']}'";
        if (($f['usuario'] ?? '') !== '') $w[] = "usuario = '" . $db->real_escape_string($f['usuario']) . "'";
        if (preg_match('/^[a-z]{3,20}$/', $f['entidad_tipo'] ?? '')) {
            $w[] = "entidad_tipo = '{$f['entidad_tipo']}'";
            if ((int)($f['entidad_id'] ?? 0) > 0) $w[] = 'entidad_id = ' . (int)$f['entidad_id'];
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $k => $op) {
            $d = \DateTime::createFromFormat('Y-m-d', $f[$k] ?? '');
            if ($d && $d->format('Y-m-d') === $f[$k]) $w[] = "creado $op '" . $f[$k] . ($k === 'hasta' ? " 23:59:59" : " 00:00:00") . "'";
        }
        if (trim($f['q'] ?? '') !== '') {
            $q = $db->real_escape_string(str_replace(['%', '_'], ['\\%', '\\_'], trim($f['q'])));
            $w[] = "(resumen LIKE '%$q%' OR entidad_nombre LIKE '%$q%')";
        }
        return implode(' AND ', $w);
    }

    public static function contar(int $pid, array $f = []): int
    {
        return (int)(self::filas('SELECT COUNT(*) n FROM auditoria_cambios WHERE ' . self::donde($pid, $f))[0]['n'] ?? 0);
    }

    public static function listar(int $pid, array $f = [], int $pagina = 1, int $porPagina = self::POR_PAGINA): array
    {
        $off = max(0, ($pagina - 1) * $porPagina);
        $filas = self::filas('SELECT * FROM auditoria_cambios WHERE ' . self::donde($pid, $f) . " ORDER BY id DESC LIMIT $porPagina OFFSET $off");
        foreach ($filas as &$x) {
            $x['antes'] = $x['antes_json'] !== null ? json_decode($x['antes_json'], true) : null;
            $x['despues'] = $x['despues_json'] !== null ? json_decode($x['despues_json'], true) : null;
        }
        return $filas;
    }

    public static function usuarios(int $pid): array
    {
        return array_column(self::filas("SELECT DISTINCT usuario FROM auditoria_cambios WHERE proyecto_id = $pid AND usuario <> '' ORDER BY usuario"), 'usuario');
    }

    // ---------- exportación ----------

    private static function tabla(int $pid, array $f): array
    {
        $t = [['Fecha', 'Usuario', 'Herramienta', 'Evento', 'Acción', 'Entidad', 'Resumen', 'Modelo']];
        foreach (self::listar($pid, $f, 1, 2000) as $x) {
            $t[] = [$x['creado'], $x['usuario'], self::HERRAMIENTAS[$x['herramienta']] ?? $x['herramienta'], self::EVENTOS[$x['evento']] ?? $x['evento'], $x['accion'], trim($x['entidad_tipo'] . ' ' . $x['entidad_nombre']), $x['resumen'], $x['modelo']];
        }
        return $t;
    }

    public static function xlsx(\Model\Proyecto $p, array $f): string
    {
        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $h = $libro->getActiveSheet();
        $h->setTitle('Historial de cambios');
        $h->fromArray(self::tabla((int)$p->id, $f), null, 'A1');
        $h->getStyle('A1:H1')->getFont()->setBold(true);
        foreach (range('A', 'H') as $c) $h->getColumnDimension($c)->setAutoSize(true);
        $h->freezePane('A2');
        $tmp = tempnam(sys_get_temp_dir(), 'aud');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($tmp);
        $bin = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    public static function pdf(\Model\Proyecto $p, array $f): string
    {
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:8px;color:#111}h1{font-size:14px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:2px 4px;text-align:left;vertical-align:top}th{background:#e8ecf1}</style></head><body><h1>Historial de cambios · ' . $e($p->nombre) . '</h1><p>Generado el ' . date('d/m/Y H:i') . '</p><table>';
        foreach (self::tabla((int)$p->id, $f) as $i => $fila) $html .= '<tr>' . implode('', array_map(fn($c) => ($i === 0 ? '<th>' : '<td>') . $e($c) . ($i === 0 ? '</th>' : '</td>'), $fila)) . '</tr>';
        return Pdf::generar($html . '</table></body></html>', 'letter', 'landscape');
    }
}
