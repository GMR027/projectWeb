<?php

namespace Classes;

/**
 * Almacén: existencias por material calculadas con los movimientos.
 * La orden de compra es un compromiso con líneas de material; la recepción entra
 * al almacén (parcial) y la salida se carga a una actividad. No se permite
 * existencia negativa: un faltante se corrige con un ajuste firmado.
 */
class Almacen
{
    use AccesoDb;

    public const TIPOS_MOV = [
        'entrada' => 'Entrada por recepción', 'salida' => 'Salida a actividad',
        'ajuste_entrada' => 'Ajuste de entrada', 'ajuste_salida' => 'Ajuste de salida',
    ];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM almacen_movimientos LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function principal(int $pid): array
    {
        $a = self::filas("SELECT * FROM almacenes WHERE proyecto_id = $pid AND activo = 1 ORDER BY principal DESC, id LIMIT 1")[0] ?? null;
        if ($a) return $a;
        self::db()->query("INSERT INTO almacenes (proyecto_id, nombre, principal) VALUES ($pid, 'Almacén principal', 1)");
        return self::filas("SELECT * FROM almacenes WHERE proyecto_id = $pid AND activo = 1 ORDER BY principal DESC, id LIMIT 1")[0]
            ?? ['id' => 0, 'proyecto_id' => $pid, 'nombre' => 'Almacén principal'];
    }

    public static function materiales(int $pid): array
    {
        return self::filas("SELECT id, nombre, unidad, costo_unitario, stock_minimo FROM materiales WHERE proyecto_id = $pid ORDER BY nombre");
    }

    /** Existencias por material; si $almacenId es 0 suma todos los almacenes del proyecto */
    public static function stock(int $pid, int $almacenId = 0): array
    {
        $w = $almacenId ? " AND am.almacen_id = " . (int)$almacenId : '';
        $filas = self::filas("SELECT m.id, m.nombre, m.unidad, m.stock_minimo, m.costo_unitario,
                COALESCE(SUM(CASE WHEN am.tipo IN ('entrada','ajuste_entrada') THEN am.cantidad
                                  WHEN am.tipo IN ('salida','ajuste_salida') THEN -am.cantidad ELSE 0 END), 0) existencia,
                (SELECT COALESCE(SUM(x.cantidad * x.costo_unitario) / NULLIF(SUM(x.cantidad), 0), 0)
                   FROM almacen_movimientos x WHERE x.material_id = m.id AND x.proyecto_id = $pid AND x.tipo = 'entrada') costo_promedio
            FROM materiales m
            LEFT JOIN almacen_movimientos am ON am.material_id = m.id AND am.proyecto_id = $pid$w
            WHERE m.proyecto_id = $pid
            GROUP BY m.id, m.nombre, m.unidad, m.stock_minimo, m.costo_unitario
            ORDER BY m.nombre");
        foreach ($filas as &$f) {
            $f['existencia'] = round((float)$f['existencia'], 3);
            $f['costo_promedio'] = (float)$f['costo_promedio'] > 0 ? round((float)$f['costo_promedio'], 2) : (float)$f['costo_unitario'];
            $f['valor'] = round(max(0, $f['existencia']) * $f['costo_promedio'], 2);
            $f['bajo'] = (float)$f['stock_minimo'] > 0 && $f['existencia'] < (float)$f['stock_minimo'];
        }
        return $filas;
    }

    public static function resumen(int $pid): array
    {
        $stock = self::stock($pid);
        $bajos = array_filter($stock, fn($s) => !empty($s['bajo']));
        $abiertas = (int)(self::filas("SELECT COUNT(*) n FROM compromisos WHERE proyecto_id = $pid AND tipo = 'orden_compra' AND estado IN ('vigente','parcial')")[0]['n'] ?? 0);
        return [
            'materiales' => count($stock),
            'bajos' => count($bajos),
            'abiertas' => $abiertas,
            'valor' => round(array_sum(array_column($stock, 'valor')), 2),
            'movimientos' => (int)(self::filas("SELECT COUNT(*) n FROM almacen_movimientos WHERE proyecto_id = $pid")[0]['n'] ?? 0),
        ];
    }

    public static function ordenes(int $pid): array
    {
        $filas = self::filas("SELECT c.*,
                (SELECT COUNT(*) FROM compra_lineas cl WHERE cl.compromiso_id = c.id) lineas,
                (SELECT COALESCE(SUM(cl.importe), 0) FROM compra_lineas cl WHERE cl.compromiso_id = c.id) importe_lineas,
                (SELECT COALESCE(SUM(cl.recibido * cl.precio_unitario), 0) FROM compra_lineas cl WHERE cl.compromiso_id = c.id) recibido_importe,
                (SELECT COALESCE(SUM(cl.recibido), 0) FROM compra_lineas cl WHERE cl.compromiso_id = c.id) recibido_unidades
            FROM compromisos c WHERE c.proyecto_id = $pid AND c.tipo = 'orden_compra'
            ORDER BY FIELD(c.estado, 'vigente', 'parcial', 'borrador', 'cerrado', 'cancelado'), c.fecha DESC, c.id DESC");
        foreach ($filas as &$o) {
            $o['avance'] = (float)$o['importe_lineas'] > 0 ? round((float)$o['recibido_importe'] / (float)$o['importe_lineas'] * 100, 1) : 0;
        }
        return $filas;
    }

    public static function orden(int $pid, int $id): ?array
    {
        $o = Compromisos::compromiso($pid, $id);
        if (!$o || $o['tipo'] !== 'orden_compra') return null;
        $o['lineas'] = self::filas("SELECT cl.*, m.nombre material, m.unidad
            FROM compra_lineas cl JOIN materiales m ON m.id = cl.material_id
            WHERE cl.compromiso_id = $id ORDER BY cl.id");
        foreach ($o['lineas'] as &$l) {
            $l['pendiente'] = round(max(0, (float)$l['cantidad'] - (float)$l['recibido']), 3);
        }
        return $o;
    }

    public static function recepciones(int $pid, int $limit = 100): array
    {
        return self::filas("SELECT r.*, c.numero oc_numero, a.nombre almacen,
                (SELECT COALESCE(SUM(m.importe), 0) FROM almacen_movimientos m WHERE m.recepcion_id = r.id) total
            FROM recepciones r LEFT JOIN compromisos c ON c.id = r.compromiso_id LEFT JOIN almacenes a ON a.id = r.almacen_id
            WHERE r.proyecto_id = $pid ORDER BY r.fecha DESC, r.id DESC LIMIT " . (int)$limit);
    }

    public static function salidas(int $pid, int $limit = 100): array
    {
        return self::filas("SELECT s.*, a.codigo act_codigo, a.nombre act_nombre, al.nombre almacen,
                (SELECT COALESCE(SUM(m.importe), 0) FROM almacen_movimientos m WHERE m.salida_id = s.id) total
            FROM salidas s JOIN actividades a ON a.id = s.actividad_id LEFT JOIN almacenes al ON al.id = s.almacen_id
            WHERE s.proyecto_id = $pid ORDER BY s.fecha DESC, s.id DESC LIMIT " . (int)$limit);
    }

    public static function movimientos(int $pid, int $limit = 150): array
    {
        return self::filas("SELECT m.*, mat.nombre material, mat.unidad, al.nombre almacen, a.codigo act_codigo, a.nombre act_nombre
            FROM almacen_movimientos m JOIN materiales mat ON mat.id = m.material_id
            LEFT JOIN almacenes al ON al.id = m.almacen_id LEFT JOIN actividades a ON a.id = m.actividad_id
            WHERE m.proyecto_id = $pid ORDER BY m.fecha DESC, m.id DESC LIMIT " . (int)$limit);
    }

    public static function costoPromedio(int $pid, int $materialId): float
    {
        $r = self::filas("SELECT COALESCE(SUM(cantidad * costo_unitario) / NULLIF(SUM(cantidad), 0), 0) n
            FROM almacen_movimientos WHERE proyecto_id = $pid AND material_id = $materialId AND tipo = 'entrada'")[0] ?? ['n' => 0];
        if ((float)$r['n'] > 0) return round((float)$r['n'], 2);
        $m = self::filas("SELECT costo_unitario FROM materiales WHERE id = $materialId AND proyecto_id = $pid")[0] ?? ['costo_unitario' => 0];
        return (float)$m['costo_unitario'];
    }

    public static function stockMaterial(int $pid, int $materialId, int $almacenId = 0): float
    {
        $w = $almacenId ? " AND almacen_id = " . (int)$almacenId : '';
        $r = self::filas("SELECT COALESCE(SUM(CASE WHEN tipo IN ('entrada','ajuste_entrada') THEN cantidad
                WHEN tipo IN ('salida','ajuste_salida') THEN -cantidad ELSE 0 END), 0) n
            FROM almacen_movimientos WHERE proyecto_id = $pid AND material_id = $materialId$w")[0] ?? ['n' => 0];
        return round((float)$r['n'], 3);
    }

    // ---------- órdenes de compra ----------

    /** Crea/actualiza una orden de compra (compromiso) con su detalle de materiales */
    public static function guardarOrden(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $actual = $id ? self::orden($pid, $id) : null;
        if ($id && !$actual) return [false, 'La orden no pertenece al proyecto', 0];
        if ($id) {
            $recibido = (float)(self::filas("SELECT COALESCE(SUM(recibido), 0) n FROM compra_lineas WHERE compromiso_id = $id")[0]['n'] ?? 0);
            if ($recibido > 0) return [false, 'La orden ya tiene recepciones: no se puede cambiar el detalle', $id];
        }

        $materiales = self::materiales($pid);
        $validos = array_column($materiales, 'id', 'id');
        $lineas = [];
        $total = 0.0;
        foreach ((array)($d['linea_material'] ?? []) as $i => $materialId) {
            $materialId = (int)$materialId;
            if (!$materialId || !isset($validos[$materialId])) continue;
            $cantidad = self::numero($d['linea_cantidad'][$i] ?? null);
            $precio = self::numero($d['linea_precio'][$i] ?? null);
            if ($cantidad === null || $cantidad <= 0) continue;
            if ($precio === null || $precio < 0) $precio = 0;
            $importe = round($cantidad * $precio, 2);
            $lineas[] = ['material_id' => $materialId, 'cantidad' => round($cantidad, 3), 'precio' => round($precio, 2), 'importe' => $importe];
            $total += $importe;
        }
        if (!$lineas) return [false, 'Agrega al menos un material con cantidad', $id];
        $total = round($total, 2);

        [$ok, $msg, $cid] = Compromisos::guardar($pid, [
            'id' => $id, 'tipo' => 'orden_compra', 'estado' => $d['estado'] ?? 'vigente',
            'proveedor' => $d['proveedor'] ?? '', 'numero' => $d['numero'] ?? '', 'concepto' => $d['concepto'] ?? 'Orden de compra',
            'monto' => $total, 'fecha' => $d['fecha'] ?? date('Y-m-d'), 'entrega' => $d['entrega'] ?? '',
            'partida_id' => $d['partida_id'] ?? 0, 'actividad_id' => $d['actividad_id'] ?? 0,
            'archivo_id' => $d['archivo_id'] ?? 0, 'notas' => $d['notas'] ?? '', 'motivo_sobregiro' => $d['motivo_sobregiro'] ?? '',
        ]);
        if (!$ok) return [false, $msg, $id];

        $db = self::db();
        $db->query("DELETE FROM compra_lineas WHERE compromiso_id = $cid");
        foreach ($lineas as $l) {
            $db->query("INSERT INTO compra_lineas (compromiso_id, material_id, cantidad, precio_unitario, importe, recibido)
                VALUES ($cid, " . (int)$l['material_id'] . ", " . $l['cantidad'] . ", " . $l['precio'] . ", " . $l['importe'] . ", 0)");
        }
        self::auditar($pid, $id ? 'actualizar_orden' : 'crear_orden', 'editar', ($id ? 'Actualizó' : 'Creó') . ' la orden de compra ' . ($d['numero'] ?? $cid) . ' por ' . Costos::moneda($total), $cid);
        return [true, 'Orden de compra guardada', $cid];
    }

    /** Recibe cantidades (parciales o totales) de una orden y las ingresa al almacén */
    public static function recibir(int $pid, int $id, array $d): array
    {
        $o = self::orden($pid, $id);
        if (!$o) return [false, 'La orden no existe'];
        if (!in_array($o['estado'], ['vigente', 'parcial'], true)) return [false, 'La orden no está abierta'];
        $almacen = self::principal($pid);
        $lineas = [];
        $total = 0.0;
        foreach ($o['lineas'] as $l) {
            $cant = self::numero($d['recibir'][(int)$l['id']] ?? null);
            if ($cant === null || $cant <= 0) continue;
            if ($cant > (float)$l['pendiente'] + 0.0005) return [false, 'La cantidad recibida de «' . $l['material'] . '» supera lo pendiente (' . $l['pendiente'] . ')'];
            $lineas[] = ['linea' => $l, 'cantidad' => round($cant, 3), 'importe' => round($cant * (float)$l['precio_unitario'], 2)];
            $total += $cant * (float)$l['precio_unitario'];
        }
        if (!$lineas) return [false, 'Captura al menos una cantidad recibida'];
        $total = round($total, 2);
        $factura = mb_substr(trim((string)($d['factura'] ?? '')), 0, 100);
        if ($factura !== '' && $total > (float)$o['saldo'] + 0.005) {
            return [false, 'La factura de esta recepción (' . Costos::moneda($total) . ') supera el saldo comprometido (' . Costos::moneda((float)$o['saldo']) . ')'];
        }
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $v = fn($x) => "'" . self::esc($x) . "'";
        $db = self::db();
        $db->begin_transaction();
        try {
            $db->query("INSERT INTO recepciones (proyecto_id, compromiso_id, almacen_id, fecha, factura, proveedor, notas, usuario)
                VALUES ($pid, $id, " . (int)$almacen['id'] . ", '$fecha', {$v($factura)}, {$v($o['proveedor'])}, {$v(mb_substr((string)($d['notas'] ?? ''), 0, 5000))}, {$v($usuario)})");
            $rid = (int)$db->insert_id;
            foreach ($lineas as $x) {
                $l = $x['linea'];
                $db->query("INSERT INTO almacen_movimientos (proyecto_id, almacen_id, material_id, tipo, cantidad, costo_unitario, importe, fecha, compromiso_id, recepcion_id, motivo, usuario)
                    VALUES ($pid, " . (int)$almacen['id'] . ", " . (int)$l['material_id'] . ", 'entrada', " . $x['cantidad'] . ", " . (float)$l['precio_unitario'] . ", " . $x['importe'] . ", '$fecha', $id, $rid, {$v('Recepción de ' . ($o['numero'] ?: 'orden ' . $id))}, {$v($usuario)})");
                $db->query("UPDATE compra_lineas SET recibido = recibido + " . $x['cantidad'] . " WHERE id = " . (int)$l['id']);
            }
            if ($factura !== '') {
                [$ok, $msg] = Compromisos::aplicar($pid, $id, ['monto' => $total, 'factura' => $factura, 'fecha' => $fecha]);
                if (!$ok) throw new \RuntimeException($msg);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo registrar la recepción'];
        }
        self::auditar($pid, 'recibir_orden', 'editar', 'Recibió material de la orden ' . ($o['numero'] ?: $id) . ' por ' . Costos::moneda($total), $id);
        return [true, 'Recepción registrada' . ($factura !== '' ? ' y factura aplicada al compromiso' : '')];
    }

    // ---------- salidas y ajustes ----------

    public static function salida(int $pid, array $d): array
    {
        $actividad = (int)($d['actividad_id'] ?? 0);
        $act = self::filas("SELECT a.id, a.codigo, a.nombre FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE a.id = $actividad AND p.proyecto_id = $pid")[0] ?? null;
        if (!$act) return [false, 'Elige una actividad válida'];
        $almacen = self::principal($pid);
        $materiales = array_column(self::materiales($pid), null, 'id');
        $lineas = [];
        foreach ((array)($d['linea_material'] ?? []) as $i => $materialId) {
            $materialId = (int)$materialId;
            if (!$materialId || !isset($materiales[$materialId])) continue;
            $cant = self::numero($d['linea_cantidad'][$i] ?? null);
            if ($cant === null || $cant <= 0) continue;
            $lineas[$materialId] = ($lineas[$materialId] ?? 0) + $cant;
        }
        if (!$lineas) return [false, 'Agrega al menos un material con cantidad'];
        foreach ($lineas as $materialId => $cant) {
            $stock = self::stockMaterial($pid, $materialId, (int)$almacen['id']);
            if ($cant > $stock + 0.0005) return [false, 'No hay existencia suficiente de «' . $materiales[$materialId]['nombre'] . '» (' . $stock . ' ' . $materiales[$materialId]['unidad'] . ')'];
        }
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $v = fn($x) => "'" . self::esc($x) . "'";
        $db = self::db();
        $db->begin_transaction();
        try {
            $db->query("INSERT INTO salidas (proyecto_id, almacen_id, actividad_id, fecha, recibio, notas, usuario)
                VALUES ($pid, " . (int)$almacen['id'] . ", $actividad, '$fecha', {$v(mb_substr((string)($d['recibio'] ?? ''), 0, 150))}, {$v(mb_substr((string)($d['notas'] ?? ''), 0, 5000))}, {$v($usuario)})");
            $sid = (int)$db->insert_id;
            foreach ($lineas as $materialId => $cant) {
                $costo = self::costoPromedio($pid, $materialId);
                $db->query("INSERT INTO almacen_movimientos (proyecto_id, almacen_id, material_id, tipo, cantidad, costo_unitario, importe, fecha, actividad_id, salida_id, motivo, usuario)
                    VALUES ($pid, " . (int)$almacen['id'] . ", $materialId, 'salida', " . round($cant, 3) . ", $costo, " . round($cant * $costo, 2) . ", '$fecha', $actividad, $sid, {$v('Salida a ' . $act['codigo'])}, {$v($usuario)})");
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo registrar la salida'];
        }
        self::auditar($pid, 'salida_almacen', 'editar', 'Sacó material del almacén para la actividad ' . $act['codigo'], $sid);
        return [true, 'Salida registrada'];
    }

    public static function ajuste(int $pid, array $d): array
    {
        $tipo = ($d['tipo'] ?? '') === 'salida' ? 'ajuste_salida' : 'ajuste_entrada';
        $materialId = (int)($d['material_id'] ?? 0);
        $material = self::filas("SELECT * FROM materiales WHERE id = $materialId AND proyecto_id = $pid")[0] ?? null;
        if (!$material) return [false, 'Elige un material válido'];
        $cant = self::numero($d['cantidad'] ?? null);
        if ($cant === null || $cant <= 0) return [false, 'La cantidad debe ser mayor que cero'];
        $motivo = mb_substr(trim((string)($d['motivo'] ?? '')), 0, 300);
        if (mb_strlen($motivo) < 5) return [false, 'Escribe el motivo del ajuste (mínimo 5 caracteres)'];
        $almacen = self::principal($pid);
        if ($tipo === 'ajuste_salida') {
            $stock = self::stockMaterial($pid, $materialId, (int)$almacen['id']);
            if ($cant > $stock + 0.0005) return [false, 'No hay existencia suficiente (' . $stock . ' ' . $material['unidad'] . ')'];
        }
        $costo = self::costoPromedio($pid, $materialId);
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $v = fn($x) => "'" . self::esc($x) . "'";
        self::db()->query("INSERT INTO almacen_movimientos (proyecto_id, almacen_id, material_id, tipo, cantidad, costo_unitario, importe, fecha, motivo, usuario)
            VALUES ($pid, " . (int)$almacen['id'] . ", $materialId, '$tipo', " . round($cant, 3) . ", $costo, " . round($cant * $costo, 2) . ", '$fecha', {$v($motivo)}, {$v($usuario)})");
        self::auditar($pid, 'ajuste_almacen', 'editar', 'Ajuste de ' . ($tipo === 'ajuste_entrada' ? 'entrada' : 'salida') . ' de ' . $material['nombre'] . ': ' . $motivo, $materialId);
        return [true, 'Ajuste registrado'];
    }

    private static function fecha($v): ?string
    {
        $v = trim((string)$v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return null;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
    }

    private static function numero($v): ?float
    {
        if ($v === null || $v === '') return null;
        $t = str_replace([',', '$', ' '], '', (string)$v);
        return is_numeric($t) ? (float)$t : null;
    }

    private static function auditar(int $pid, string $evento, string $accion, string $resumen, int $id): void
    {
        try {
            \Classes\Auditoria::registrar($pid, 'almacen', $evento, $accion, $resumen, null, null, $id, 'Almacén');
        } catch (\Throwable $e) {
        }
    }
}
