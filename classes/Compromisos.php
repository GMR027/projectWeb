<?php

namespace Classes;

use Model\Proyecto;

/**
 * Dinero comprometido: órdenes de compra, subcontratos y servicios que ya se prometieron
 * pero cuya factura todavía no llega. Reservan presupuesto desde que se emiten y, al aplicar
 * una factura, el monto pasa de «comprometido» a «costo real» sin contarse dos veces.
 */
class Compromisos
{
    use AccesoDb;

    public const TIPOS = ['orden_compra' => 'Orden de compra', 'subcontrato' => 'Subcontrato', 'servicio' => 'Orden de servicio'];
    public const ESTADOS = ['borrador' => 'Borrador', 'vigente' => 'Vigente', 'parcial' => 'Parcial', 'cerrado' => 'Cerrado', 'cancelado' => 'Cancelado'];
    /** Estados que reservan presupuesto */
    public const ABIERTOS = ['vigente', 'parcial'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM compromisos LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---------- lectura ----------

    public static function lista(int $pid): array
    {
        $filas = self::filas("SELECT c.*, pa.codigo partida_codigo, pa.nombre partida,
                a.codigo act_codigo, a.nombre act_nombre, COALESCE(ap.aplicado, 0) aplicado
            FROM compromisos c
            JOIN partidas pa ON pa.id = c.partida_id
            LEFT JOIN actividades a ON a.id = c.actividad_id
            LEFT JOIN (SELECT compromiso_id, SUM(monto) aplicado FROM compromiso_aplicaciones GROUP BY compromiso_id) ap ON ap.compromiso_id = c.id
            WHERE c.proyecto_id = $pid
            ORDER BY FIELD(c.estado, 'vigente', 'parcial', 'borrador', 'cerrado', 'cancelado'), c.fecha DESC, c.id DESC");
        foreach ($filas as &$c) {
            $c['aplicado'] = round((float)$c['aplicado'], 2);
            $c['abierto'] = in_array($c['estado'], self::ABIERTOS, true);
            $c['saldo'] = $c['abierto'] ? round(max(0, (float)$c['monto'] - $c['aplicado']), 2) : 0.0;
        }
        return $filas;
    }

    public static function compromiso(int $pid, int $id): ?array
    {
        $c = self::filas("SELECT c.*, pa.codigo partida_codigo, pa.nombre partida, a.codigo act_codigo, a.nombre act_nombre,
                COALESCE((SELECT SUM(monto) FROM compromiso_aplicaciones WHERE compromiso_id = c.id), 0) aplicado
            FROM compromisos c JOIN partidas pa ON pa.id = c.partida_id
            LEFT JOIN actividades a ON a.id = c.actividad_id
            WHERE c.id = $id AND c.proyecto_id = $pid")[0] ?? null;
        if ($c) {
            $c['aplicado'] = round((float)$c['aplicado'], 2);
            $c['abierto'] = in_array($c['estado'], self::ABIERTOS, true);
            $c['saldo'] = $c['abierto'] ? round(max(0, (float)$c['monto'] - $c['aplicado']), 2) : 0.0;
        }
        return $c;
    }

    public static function aplicaciones(int $compromisoId): array
    {
        return self::filas("SELECT * FROM compromiso_aplicaciones WHERE compromiso_id = $compromisoId ORDER BY fecha, id");
    }

    /**
     * Monto comprometido (saldo pendiente de facturar) por partida en dos consultas.
     * @return array<int, array{monto:float,aplicado:float,comprometido:float}>
     */
    public static function resumenProyecto(int $pid): array
    {
        $out = [];
        $est = "'" . implode("','", self::ABIERTOS) . "'";
        foreach (self::filas("SELECT c.partida_id, SUM(c.monto) monto, COALESCE(SUM(ap.aplicado), 0) aplicado
            FROM compromisos c
            LEFT JOIN (SELECT compromiso_id, SUM(monto) aplicado FROM compromiso_aplicaciones GROUP BY compromiso_id) ap ON ap.compromiso_id = c.id
            WHERE c.proyecto_id = $pid AND c.estado IN ($est)
            GROUP BY c.partida_id") as $f) {
            $monto = round((float)$f['monto'], 2);
            $aplicado = round((float)$f['aplicado'], 2);
            $out[(int)$f['partida_id']] = ['monto' => $monto, 'aplicado' => $aplicado, 'comprometido' => round(max(0, $monto - $aplicado), 2)];
        }
        return $out;
    }

    /** Disponible de una partida: presupuesto vigente − costo real − comprometido (sin contar el compromiso que se está editando) */
    public static function disponiblePartida(int $pid, int $partidaId, int $excluirId = 0): float
    {
        $proyecto = Proyecto::find($pid);
        if (!$proyecto) return 0.0;
        $resumen = Costos::resumen($proyecto);
        $fila = null;
        foreach ($resumen['partidas'] as $p) if ((int)$p['id'] === $partidaId) { $fila = $p; break; }
        if (!$fila) return 0.0;
        $disp = (float)$fila['disponible'];
        if ($excluirId) {
            $actual = self::compromiso($pid, $excluirId);
            if ($actual && $actual['abierto']) $disp += (float)$actual['saldo'];
        }
        return round($disp, 2);
    }

    // ---------- escritura ----------

    /** Crea o actualiza un compromiso. Devuelve [ok, mensaje, id]. */
    public static function guardar(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $tipo = is_string($d['tipo'] ?? null) && isset(self::TIPOS[$d['tipo']]) ? $d['tipo'] : 'orden_compra';
        $estado = is_string($d['estado'] ?? null) && isset(self::ESTADOS[$d['estado']]) ? $d['estado'] : 'vigente';
        $partida = (int)($d['partida_id'] ?? 0);
        if (!$partida || !self::filas("SELECT id FROM partidas WHERE id = $partida AND proyecto_id = $pid")) return [false, 'Elige una partida válida', 0];
        $actividad = (int)($d['actividad_id'] ?? 0);
        if ($actividad && !self::filas("SELECT 1 FROM actividades WHERE id = $actividad AND partida_id = $partida")) $actividad = 0;
        $monto = self::numero($d['monto'] ?? null);
        if ($monto === null || $monto <= 0) return [false, 'El monto debe ser mayor que cero', 0];
        $proveedor = mb_substr(trim((string)($d['proveedor'] ?? '')), 0, 200);
        if ($proveedor === '') return [false, 'Escribe el proveedor o contratista', 0];
        $numero = mb_substr(trim((string)($d['numero'] ?? '')), 0, 80);
        $concepto = mb_substr(trim((string)($d['concepto'] ?? '')), 0, 255);
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $entrega = self::fecha($d['entrega'] ?? '');
        $archivo = (int)($d['archivo_id'] ?? 0);
        if ($archivo && !self::filas("SELECT id FROM archivos WHERE id = $archivo AND proyecto_id = $pid")) $archivo = 0;
        $notas = mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000);
        $actual = $id ? self::compromiso($pid, $id) : null;
        if ($id && !$actual) return [false, 'El compromiso no pertenece al proyecto', 0];
        if ($actual && $monto < (float)$actual['aplicado']) return [false, 'El monto no puede ser menor que lo ya facturado (' . Costos::moneda((float)$actual['aplicado']) . ')', $id];

        // Sobregiro: se permite, pero con motivo firmado
        $motivo = mb_substr(trim((string)($d['motivo_sobregiro'] ?? '')), 0, 500);
        if (in_array($estado, self::ABIERTOS, true)) {
            $disponible = self::disponiblePartida($pid, $partida, $id);
            $reservado = $monto - ($actual ? (float)$actual['aplicado'] : 0);
            if ($reservado > $disponible + 0.005) {
                if (mb_strlen($motivo) < 10) {
                    return [false, 'El monto supera el disponible de la partida (' . Costos::moneda($disponible) . '). Escribe el motivo del sobregiro (mínimo 10 caracteres).', $id];
                }
            } else {
                $motivo = '';
            }
        } else {
            $motivo = '';
        }

        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "partida_id = $partida, actividad_id = " . ($actividad ?: 'NULL') . ", tipo = '$tipo', estado = '$estado',
            proveedor = {$v($proveedor)}, numero = {$v($numero)}, concepto = {$v($concepto)}, monto = " . round($monto, 2) . ",
            fecha = '$fecha', entrega = " . ($entrega ? "'$entrega'" : 'NULL') . ", archivo_id = " . ($archivo ?: 'NULL') . ",
            notas = {$v($notas)}, motivo_sobregiro = {$v($motivo)}";
        if ($id) {
            self::db()->query("UPDATE compromisos SET $campos WHERE id = $id AND proyecto_id = $pid");
            $evento = 'actualizar_compromiso';
        } else {
            self::db()->query("INSERT INTO compromisos SET proyecto_id = $pid, usuario = {$v($usuario)}, $campos");
            $id = (int)self::db()->insert_id;
            $evento = 'crear_compromiso';
        }
        self::auditar($pid, $evento, 'editar', ($evento === 'crear_compromiso' ? 'Creó' : 'Actualizó') . ' el compromiso «' . ($numero ?: $proveedor) . '» por ' . Costos::moneda($monto), $id);
        return [true, 'Compromiso guardado', $id];
    }

    /**
     * Aplica una factura al compromiso: crea el costo real y descuenta el saldo comprometido.
     * El disponible no cambia (el dinero pasa de comprometido a gastado).
     */
    public static function aplicar(int $pid, int $id, array $d): array
    {
        $c = self::compromiso($pid, $id);
        if (!$c) return [false, 'El compromiso no existe'];
        if (!$c['abierto']) return [false, 'Solo se pueden aplicar facturas a compromisos vigentes o parciales'];
        $monto = self::numero($d['monto'] ?? null);
        if ($monto === null || $monto <= 0) return [false, 'El monto de la factura debe ser mayor que cero'];
        if ($monto > $c['saldo'] + 0.005) return [false, 'El monto supera el saldo del compromiso (' . Costos::moneda((float)$c['saldo']) . ')'];
        $factura = mb_substr(trim((string)($d['factura'] ?? '')), 0, 100);
        if ($factura === '') return [false, 'Escribe el número o referencia de la factura'];
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $concepto = 'Factura ' . $factura . ' · ' . ($c['numero'] ?: (self::TIPOS[$c['tipo']] ?? 'Compromiso')) . (trim((string)$c['concepto']) !== '' ? ' · ' . $c['concepto'] : '');
        $v = fn($x) => "'" . self::esc($x) . "'";
        self::db()->query("INSERT INTO costos_movimientos (proyecto_id, partida_id, tipo, fecha, concepto, referencia, monto, estado, usuario)
            VALUES ($pid, " . (int)$c['partida_id'] . ", 'real', '$fecha', {$v(mb_substr($concepto, 0, 255))}, {$v($factura)}, " . round($monto, 2) . ", 'aprobada', {$v($usuario)})");
        $movId = (int)self::db()->insert_id;
        self::db()->query("INSERT INTO compromiso_aplicaciones (compromiso_id, movimiento_id, monto, fecha, factura, usuario)
            VALUES ($id, $movId, " . round($monto, 2) . ", '$fecha', {$v($factura)}, {$v($usuario)})");
        $saldo = round(max(0, (float)$c['saldo'] - $monto), 2);
        self::db()->query("UPDATE compromisos SET estado = '" . ($saldo <= 0.005 ? 'cerrado' : 'parcial') . "' WHERE id = $id AND proyecto_id = $pid");
        self::auditar($pid, 'aplicar_factura', 'editar', 'Aplicó la factura ' . $factura . ' por ' . Costos::moneda($monto) . ' al compromiso ' . ($c['numero'] ?: $id), $id);
        return [true, 'Factura aplicada; el saldo comprometido bajó a ' . Costos::moneda($saldo)];
    }

    /** Cierra o cancela un compromiso: si no tiene facturas queda cancelado; si tiene, cerrado con el saldo liberado */
    public static function cerrar(int $pid, int $id): array
    {
        $c = self::compromiso($pid, $id);
        if (!$c) return [false, 'El compromiso no existe'];
        if (in_array($c['estado'], ['cerrado', 'cancelado'], true)) return [false, 'Ese compromiso ya está cerrado'];
        $nuevo = $c['aplicado'] > 0 ? 'cerrado' : 'cancelado';
        self::db()->query("UPDATE compromisos SET estado = '$nuevo' WHERE id = $id AND proyecto_id = $pid");
        self::auditar($pid, 'cerrar_compromiso', 'editar', 'Cerró el compromiso ' . ($c['numero'] ?: $id) . ' y liberó ' . Costos::moneda((float)$c['saldo']) . ' comprometidos', $id);
        return [true, 'Compromiso ' . ($nuevo === 'cancelado' ? 'cancelado' : 'cerrado') . '; se liberaron ' . Costos::moneda((float)$c['saldo'])];
    }

    public static function eliminar(int $pid, int $id): bool
    {
        $c = self::compromiso($pid, $id);
        if (!$c || $c['aplicado'] > 0) return false;
        self::db()->query("DELETE FROM compromisos WHERE id = $id AND proyecto_id = $pid");
        self::auditar($pid, 'eliminar_compromiso', 'eliminar', 'Eliminó el compromiso ' . ($c['numero'] ?: $id), $id);
        return true;
    }

    /** Si se borra una factura, el compromiso recupera ese saldo (y se reabre si estaba cerrado) */
    public static function quitarAplicacionesDeMovimiento(int $movimientoId): void
    {
        try {
            $ids = array_column(self::filas("SELECT DISTINCT compromiso_id FROM compromiso_aplicaciones WHERE movimiento_id = $movimientoId"), 'compromiso_id');
            if (!$ids) return;
            self::db()->query("DELETE FROM compromiso_aplicaciones WHERE movimiento_id = $movimientoId");
            foreach ($ids as $cid) {
                $cid = (int)$cid;
                $aplicado = (float)(self::filas("SELECT COALESCE(SUM(monto),0) n FROM compromiso_aplicaciones WHERE compromiso_id = $cid")[0]['n'] ?? 0);
                $monto = (float)(self::filas("SELECT monto FROM compromisos WHERE id = $cid")[0]['monto'] ?? 0);
                $estado = $aplicado <= 0.005 ? 'vigente' : ($aplicado >= $monto - 0.005 ? 'cerrado' : 'parcial');
                self::db()->query("UPDATE compromisos SET estado = '$estado' WHERE id = $cid");
            }
        } catch (\Throwable $e) {
        }
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
            \Classes\Auditoria::registrar($pid, 'costos', $evento, $accion, $resumen, null, null, $id, 'Compromiso');
        } catch (\Throwable $e) {
        }
    }
}
