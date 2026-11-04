<?php

namespace Classes;

/**
 * Subcontratos: alcance, monto, retención, anticipo, documentos obligatorios y estimaciones.
 * El monto se reserva como compromiso y, antes de aprobar/pagar una estimación, se exige
 * que los documentos obligatorios estén cargados, validados y vigentes. Solo el admin
 * puede autorizar un pago con motivo firmado cuando falta algo.
 */
class Subcontratos
{
    use AccesoDb;

    public const ESTADOS = ['borrador' => 'Borrador', 'vigente' => 'Vigente', 'cerrado' => 'Cerrado', 'cancelado' => 'Cancelado'];
    public const DOCS = [
        'fianza' => 'Fianza de cumplimiento',
        'seguro' => 'Seguro de responsabilidad civil',
        'permisos' => 'Permisos de trabajo',
        'alta_personal' => 'Alta de personal',
        'otro' => 'Otro documento',
    ];
    public const DOCS_OBLIGATORIOS = ['fianza', 'seguro', 'permisos', 'alta_personal'];
    public const ESTADOS_ESTIMACION = ['borrador' => 'Borrador', 'aprobada' => 'Aprobada', 'pagada' => 'Pagada'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM subcontratos LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function lista(int $pid): array
    {
        $filas = self::filas("SELECT s.*, pa.codigo partida_codigo, pa.nombre partida,
                (SELECT COALESCE(SUM(e.neto), 0) FROM subcontrato_estimaciones e WHERE e.subcontrato_id = s.id AND e.estado IN ('aprobada','pagada')) pagado,
                (SELECT COUNT(*) FROM subcontrato_estimaciones e WHERE e.subcontrato_id = s.id) estimaciones
            FROM subcontratos s JOIN partidas pa ON pa.id = s.partida_id
            WHERE s.proyecto_id = $pid
            ORDER BY FIELD(s.estado, 'vigente', 'borrador', 'cerrado', 'cancelado'), s.fecha_inicio IS NULL, s.fecha_inicio, s.id DESC");
        foreach ($filas as &$s) {
            $s['pagado'] = round((float)$s['pagado'], 2);
            $s['saldo'] = round(max(0, (float)$s['monto'] - $s['pagado']), 2);
            $s['docs'] = self::documentosEstado((int)$s['id']);
        }
        return $filas;
    }

    public static function subcontrato(int $pid, int $id): ?array
    {
        $s = self::filas("SELECT s.*, pa.codigo partida_codigo, pa.nombre partida
            FROM subcontratos s JOIN partidas pa ON pa.id = s.partida_id
            WHERE s.id = $id AND s.proyecto_id = $pid")[0] ?? null;
        if (!$s) return null;
        $s['documentos'] = self::filas("SELECT d.*, a.nombre archivo FROM subcontrato_documentos d
            LEFT JOIN archivos a ON a.id = d.archivo_id WHERE d.subcontrato_id = $id ORDER BY d.tipo");
        $s['estimaciones'] = self::estimaciones($id);
        $s['docs'] = self::documentosEstado($id);
        $s['pagado'] = 0.0;
        foreach ($s['estimaciones'] as $e) if (in_array($e['estado'], ['aprobada', 'pagada'], true)) $s['pagado'] += (float)$e['neto'];
        $s['pagado'] = round($s['pagado'], 2);
        $s['saldo'] = round(max(0, (float)$s['monto'] - $s['pagado']), 2);
        return $s;
    }

    public static function estimaciones(int $sid): array
    {
        return self::filas("SELECT * FROM subcontrato_estimaciones WHERE subcontrato_id = $sid ORDER BY fecha DESC, id DESC");
    }

    public static function siguienteCodigo(int $pid): string
    {
        $n = (int)(self::filas("SELECT COUNT(*) n FROM subcontratos WHERE proyecto_id = $pid")[0]['n'] ?? 0) + 1;
        return 'SUB-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
    }

    /** Crea o actualiza el subcontrato y su compromiso de dinero. Devuelve [ok, mensaje, id]. */
    public static function guardar(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $actual = $id ? self::subcontrato($pid, $id) : null;
        if ($id && !$actual) return [false, 'El subcontrato no pertenece al proyecto', 0];
        $partida = (int)($d['partida_id'] ?? 0);
        if (!$partida || !self::filas("SELECT id FROM partidas WHERE id = $partida AND proyecto_id = $pid")) return [false, 'Elige una partida válida', $id];
        $proveedor = mb_substr(trim((string)($d['proveedor'] ?? '')), 0, 200);
        if ($proveedor === '') return [false, 'Escribe el subcontratista o proveedor', $id];
        $monto = self::numero($d['monto'] ?? null);
        if ($monto === null || $monto <= 0) return [false, 'El monto del subcontrato debe ser mayor que cero', $id];
        $codigo = mb_substr(trim((string)($d['codigo'] ?? '')), 0, 40) ?: self::siguienteCodigo($pid);
        $alcance = mb_substr(trim((string)($d['alcance'] ?? '')), 0, 5000);
        $ret = max(0, min(100, self::numero($d['retencion_pct'] ?? null) ?? 10));
        $ant = max(0, min(100, self::numero($d['anticipo_pct'] ?? null) ?? 0));
        $inicio = self::fecha($d['fecha_inicio'] ?? '');
        $fin = self::fecha($d['fecha_fin'] ?? '');
        $estado = is_string($d['estado'] ?? null) && isset(self::ESTADOS[$d['estado']]) ? $d['estado'] : 'vigente';
        $notas = mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000);
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "partida_id = $partida, codigo = {$v($codigo)}, proveedor = {$v($proveedor)}, alcance = {$v($alcance)},
            monto = " . round($monto, 2) . ", retencion_pct = " . round($ret, 2) . ", anticipo_pct = " . round($ant, 2) . ",
            fecha_inicio = " . ($inicio ? "'$inicio'" : 'NULL') . ", fecha_fin = " . ($fin ? "'$fin'" : 'NULL') . ",
            estado = '$estado', notas = {$v($notas)}";
        if ($id) self::db()->query("UPDATE subcontratos SET $campos WHERE id = $id AND proyecto_id = $pid");
        else {
            self::db()->query("INSERT INTO subcontratos SET proyecto_id = $pid, usuario = {$v($usuario)}, $campos");
            $id = (int)self::db()->insert_id;
        }

        // Compromiso de dinero del subcontrato
        $cid = (int)($actual['compromiso_id'] ?? 0);
        [$ok, $msg, $cidNuevo] = Compromisos::guardar($pid, [
            'id' => $cid, 'tipo' => 'subcontrato',
            'estado' => in_array($estado, ['vigente'], true) ? 'vigente' : 'borrador',
            'proveedor' => $proveedor, 'numero' => $codigo,
            'concepto' => 'Subcontrato ' . $codigo . ($alcance !== '' ? ' · ' . mb_substr($alcance, 0, 160) : ''),
            'monto' => $monto, 'fecha' => $inicio ?: date('Y-m-d'), 'entrega' => $fin ?: '',
            'partida_id' => $partida, 'actividad_id' => 0, 'notas' => $notas,
            'motivo_sobregiro' => (string)($d['motivo_sobregiro'] ?? ''),
        ]);
        if (!$ok) return [false, $msg, $id];
        if ($cidNuevo && $cidNuevo !== $cid) self::db()->query("UPDATE subcontratos SET compromiso_id = $cidNuevo WHERE id = $id AND proyecto_id = $pid");
        if (in_array($estado, ['cerrado', 'cancelado'], true) && $cidNuevo) Compromisos::cerrar($pid, $cidNuevo);
        self::auditar($pid, $id && $actual ? 'actualizar_subcontrato' : 'crear_subcontrato', 'editar',
            ($id && $actual ? 'Actualizó' : 'Creó') . ' el subcontrato ' . $codigo . ' por ' . Costos::moneda($monto), $id);
        return [true, 'Subcontrato guardado', $id];
    }

    public static function guardarDocumento(int $pid, int $sid, array $d): array
    {
        $s = self::subcontrato($pid, $sid);
        if (!$s) return [false, 'El subcontrato no existe'];
        $tipo = is_string($d['tipo'] ?? null) && isset(self::DOCS[$d['tipo']]) ? $d['tipo'] : null;
        if (!$tipo) return [false, 'Tipo de documento inválido'];
        $archivo = (int)($d['archivo_id'] ?? 0);
        if ($archivo && !self::filas("SELECT id FROM archivos WHERE id = $archivo AND proyecto_id = $pid")) $archivo = 0;
        $venc = self::fecha($d['vencimiento'] ?? '');
        $validado = !empty($d['validado']) ? 1 : 0;
        if ($validado && !$archivo) return [false, 'Para validar el documento primero elige un archivo'];
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $v = fn($x) => "'" . self::esc($x) . "'";
        self::db()->query("INSERT INTO subcontrato_documentos (subcontrato_id, tipo, archivo_id, vencimiento, validado, validado_por, validado_en, notas)
            VALUES ($sid, '$tipo', " . ($archivo ?: 'NULL') . ", " . ($venc ? "'$venc'" : 'NULL') . ", $validado,
                " . ($validado ? $v($usuario) : "''") . ", " . ($validado ? 'NOW()' : 'NULL') . ", {$v(mb_substr((string)($d['notas'] ?? ''), 0, 500))})
            ON DUPLICATE KEY UPDATE archivo_id = VALUES(archivo_id), vencimiento = VALUES(vencimiento), validado = VALUES(validado),
                validado_por = VALUES(validado_por), validado_en = VALUES(validado_en), notas = VALUES(notas)");
        self::auditar($pid, 'documento_subcontrato', 'editar', 'Actualizó ' . self::DOCS[$tipo] . ' del subcontrato ' . ($s['codigo'] ?: $sid), $sid);
        return [true, 'Documento guardado'];
    }

    /**
     * Estado de los documentos obligatorios.
     * @return array{ok:bool,faltan:string[],vencidos:string[],por_vencer:string[],filas:array}
     */
    public static function documentosEstado(int $sid): array
    {
        $filas = [];
        foreach (self::filas("SELECT d.*, a.nombre archivo FROM subcontrato_documentos d
            LEFT JOIN archivos a ON a.id = d.archivo_id WHERE d.subcontrato_id = $sid") as $d) $filas[$d['tipo']] = $d;
        $hoy = date('Y-m-d');
        $en30 = date('Y-m-d', strtotime('+30 days'));
        $out = ['ok' => true, 'faltan' => [], 'vencidos' => [], 'por_vencer' => [], 'filas' => $filas];
        foreach (self::DOCS_OBLIGATORIOS as $tipo) {
            $d = $filas[$tipo] ?? null;
            if (!$d || !(int)$d['validado'] || empty($d['archivo_id'])) { $out['faltan'][] = self::DOCS[$tipo]; $out['ok'] = false; continue; }
            if (!empty($d['vencimiento']) && $d['vencimiento'] < $hoy) { $out['vencidos'][] = self::DOCS[$tipo]; $out['ok'] = false; continue; }
            if (!empty($d['vencimiento']) && $d['vencimiento'] <= $en30) $out['por_vencer'][] = self::DOCS[$tipo];
        }
        return $out;
    }

    /** @return array{0:bool,1:string} */
    public static function puedePagar(int $sid): array
    {
        $e = self::documentosEstado($sid);
        if ($e['ok']) return [true, ''];
        $partes = [];
        if ($e['faltan']) $partes[] = 'falta: ' . implode(', ', $e['faltan']);
        if ($e['vencidos']) $partes[] = 'vencido: ' . implode(', ', $e['vencidos']);
        return [false, 'No se puede pagar porque ' . implode('; ', $partes) . '.'];
    }

    /**
     * Guarda una estimación del subcontratista. Si se aprueba o paga, exige documentos
     * vigentes (salvo autorización firmada del admin) y aplica el neto al compromiso.
     */
    public static function guardarEstimacion(int $pid, int $sid, array $d): array
    {
        $s = self::subcontrato($pid, $sid);
        if (!$s) return [false, 'El subcontrato no existe'];
        $id = (int)($d['id'] ?? 0);
        $actual = $id ? self::filas("SELECT * FROM subcontrato_estimaciones WHERE id = $id AND subcontrato_id = $sid AND proyecto_id = $pid")[0] ?? null : null;
        if ($id && !$actual) return [false, 'La estimación no pertenece al subcontrato', $id];
        $numero = mb_substr(trim((string)($d['numero'] ?? '')), 0, 40);
        if ($numero === '') return [false, 'Escribe el número de la estimación', $id];
        $subtotal = self::numero($d['subtotal'] ?? null);
        if ($subtotal === null || $subtotal <= 0) return [false, 'El subtotal debe ser mayor que cero', $id];
        $ivaPct = max(0, min(100, self::numero($d['iva_pct'] ?? null) ?? 16));
        $retPct = max(0, min(100, self::numero($d['retencion_pct'] ?? null) ?? $s['retencion_pct']));
        $antPct = max(0, min(100, self::numero($d['anticipo_pct'] ?? null) ?? $s['anticipo_pct']));
        $ajuste = self::numero($d['ajuste'] ?? null) ?? 0;
        $estado = is_string($d['estado'] ?? null) && isset(self::ESTADOS_ESTIMACION[$d['estado']]) ? $d['estado'] : 'borrador';
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $desde = self::fecha($d['periodo_desde'] ?? '');
        $hasta = self::fecha($d['periodo_hasta'] ?? '');
        $notas = mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000);
        $motivo = mb_substr(trim((string)($d['motivo_autorizacion'] ?? '')), 0, 500);

        $calc = Estimaciones::calcular($subtotal, $ajuste, $ivaPct, $retPct, $antPct);
        // Tope de amortización: el anticipo pactado menos lo ya amortizado en otras estimaciones
        $anticipoTotal = round((float)$s['monto'] * (float)$s['anticipo_pct'] / 100, 2);
        $amortizado = 0.0;
        foreach ($s['estimaciones'] as $e) if ((int)$e['id'] !== $id && in_array($e['estado'], ['aprobada', 'pagada'], true)) $amortizado += (float)$e['amortizacion'];
        $maxAmort = max(0, $anticipoTotal - $amortizado);
        if ($calc['amortizacion'] > $maxAmort) {
            $calc['amortizacion'] = $maxAmort;
            $calc['neto'] = round($calc['total_bruto'] - $calc['retencion'] - $calc['amortizacion'], 2);
        }

        $autorizadoPor = '';
        if (in_array($estado, ['aprobada', 'pagada'], true)) {
            [$puede, $msg] = self::puedePagar($sid);
            if (!$puede) {
                if (!is_admin() || mb_strlen($motivo) < 10) return [false, $msg . ' Solo el administrador puede autorizar el pago con un motivo (mínimo 10 caracteres).', $id];
                $autorizadoPor = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
            } else {
                $motivo = '';
            }
        } else {
            $motivo = '';
        }

        // Si ya tenía un movimiento, se revierte antes de recalcular
        if ($actual && !empty($actual['movimiento_id'])) {
            Costos::eliminarMovimiento($pid, (int)$actual['movimiento_id']);
        }
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "numero = {$v($numero)}, fecha = '$fecha', periodo_desde = " . ($desde ? "'$desde'" : 'NULL') . ", periodo_hasta = " . ($hasta ? "'$hasta'" : 'NULL') . ",
            subtotal = " . round($subtotal, 2) . ", iva_pct = " . round($ivaPct, 2) . ", iva = " . $calc['iva'] . ",
            retencion_pct = " . round($retPct, 2) . ", retencion = " . $calc['retencion'] . ", anticipo_pct = " . round($antPct, 2) . ", amortizacion = " . $calc['amortizacion'] . ",
            ajuste = " . round($ajuste, 2) . ", total_bruto = " . $calc['total_bruto'] . ", neto = " . $calc['neto'] . ",
            estado = '$estado', notas = {$v($notas)}, motivo_autorizacion = {$v($motivo)}, autorizado_por = {$v($autorizadoPor)}";
        if ($id) self::db()->query("UPDATE subcontrato_estimaciones SET $campos WHERE id = $id AND subcontrato_id = $sid");
        else {
            self::db()->query("INSERT INTO subcontrato_estimaciones SET subcontrato_id = $sid, proyecto_id = $pid, usuario = {$v($usuario)}, $campos");
            $id = (int)self::db()->insert_id;
        }
        if (in_array($estado, ['aprobada', 'pagada'], true) && !empty($s['compromiso_id'])) {
            [$ok, $msg] = Compromisos::aplicar($pid, (int)$s['compromiso_id'], ['monto' => $calc['neto'], 'factura' => $numero, 'fecha' => $fecha]);
            if (!$ok) return [false, $msg, $id];
            $mov = self::filas("SELECT id FROM costos_movimientos WHERE proyecto_id = $pid AND referencia = '" . self::esc($numero) . "' ORDER BY id DESC LIMIT 1")[0] ?? null;
            if ($mov) self::db()->query("UPDATE subcontrato_estimaciones SET movimiento_id = " . (int)$mov['id'] . " WHERE id = $id");
        }
        self::auditar($pid, 'estimacion_subcontrato', 'editar', 'Guardó la estimación ' . $numero . ' del subcontrato ' . $s['codigo'] . ' por ' . Costos::moneda($calc['neto']) . ' netos', $sid);
        return [true, 'Estimación guardada', $id];
    }

    public static function resumen(int $pid): array
    {
        $filas = self::lista($pid);
        $monto = 0.0; $pagado = 0.0; $faltan = 0; $porVencer = 0;
        foreach ($filas as $s) {
            $monto += (float)$s['monto']; $pagado += (float)$s['pagado'];
            if ($s['docs']['faltan'] || $s['docs']['vencidos']) $faltan++;
            if ($s['docs']['por_vencer']) $porVencer++;
        }
        return ['total' => count($filas), 'monto' => round($monto, 2), 'pagado' => round($pagado, 2),
            'saldo' => round(max(0, $monto - $pagado), 2), 'con_faltantes' => $faltan, 'por_vencer' => $porVencer];
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
            \Classes\Auditoria::registrar($pid, 'subcontratos', $evento, $accion, $resumen, null, null, $id, 'Subcontrato');
        } catch (\Throwable $e) {
        }
    }
}
