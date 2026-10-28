<?php

namespace Classes;

/**
 * Contratos, fianzas, seguros y licencias del proyecto, con su vencimiento y aviso previo.
 * La situación (vigente / por vencer / vencido) se calcula con la fecha, no se guarda.
 */
class Contratos
{
    use AccesoDb;

    public const TIPOS = ['contrato' => 'Contrato', 'fianza' => 'Fianza', 'seguro' => 'Seguro', 'licencia' => 'Licencia / permiso', 'otro' => 'Otro'];
    public const MONEDAS = ['MXN', 'USD', 'EUR'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM contratos LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Situación calculada de una fila */
    public static function situacion(array $c): string
    {
        if ($c['estado'] === 'cerrado') return 'cerrado';
        if (empty($c['vencimiento'])) return 'vigente';
        $hoy = date('Y-m-d');
        if ($c['vencimiento'] < $hoy) return 'vencido';
        $aviso = date('Y-m-d', strtotime('+' . max(0, (int)($c['aviso_dias'] ?? 30)) . ' days'));
        return $c['vencimiento'] <= $aviso ? 'por_vencer' : 'vigente';
    }

    public static function lista(int $pid, string $filtro = ''): array
    {
        $rows = self::filas("SELECT c.*, a.nombre archivo_nombre, DATEDIFF(c.vencimiento, CURDATE()) dias
            FROM contratos c LEFT JOIN archivos a ON a.id = c.archivo_id
            WHERE c.proyecto_id = $pid ORDER BY c.estado = 'cerrado', c.vencimiento IS NULL, c.vencimiento, c.id DESC");
        $out = [];
        foreach ($rows as $r) {
            $r['situacion'] = self::situacion($r);
            if ($filtro !== '' && $r['situacion'] !== $filtro) continue;
            $out[] = $r;
        }
        return $out;
    }

    public static function obtener(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM contratos WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    public static function guardar(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'contrato';
        $moneda = in_array($d['moneda'] ?? 'MXN', self::MONEDAS, true) ? $d['moneda'] : 'MXN';
        $inicio = self::fecha($d['inicio'] ?? '');
        $vencimiento = self::fecha($d['vencimiento'] ?? '');
        if ($inicio && $vencimiento && $vencimiento < $inicio) return [false, 'El vencimiento no puede ser anterior al inicio', 0];
        $archivoId = (int)($d['archivo_id'] ?? 0);
        if ($archivoId) {
            $ok = self::filas("SELECT 1 FROM archivos WHERE id = $archivoId AND proyecto_id = $pid");
            if (!$ok) $archivoId = 0;
        }
        $campos = "tipo = '$tipo', numero = '" . self::esc(mb_substr(trim((string)($d['numero'] ?? '')), 0, 80)) . "',
            contraparte = '" . self::esc(mb_substr(trim((string)($d['contraparte'] ?? '')), 0, 200)) . "',
            objeto = '" . self::esc(mb_substr(trim((string)($d['objeto'] ?? '')), 0, 300)) . "',
            monto = " . max(0, (float)($d['monto'] ?? 0)) . ", moneda = '$moneda',
            inicio = " . ($inicio ? "'$inicio'" : 'NULL') . ", vencimiento = " . ($vencimiento ? "'$vencimiento'" : 'NULL') . ",
            aviso_dias = " . max(0, min(365, (int)($d['aviso_dias'] ?? 30))) . ",
            estado = '" . (($d['estado'] ?? 'vigente') === 'cerrado' ? 'cerrado' : 'vigente') . "',
            archivo_id = " . ($archivoId ?: 'NULL') . ",
            notas = '" . self::esc(mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000)) . "'";
        if ($id) {
            if (!self::obtener($pid, $id)) return [false, 'El registro no existe', 0];
            self::db()->query("UPDATE contratos SET $campos WHERE id = $id AND proyecto_id = $pid");
        } else {
            self::db()->query("INSERT INTO contratos SET proyecto_id = $pid, $campos");
            $id = (int)self::db()->insert_id;
        }
        return [true, 'Guardado', $id];
    }

    public static function eliminar(int $pid, int $id): bool
    {
        if (!self::obtener($pid, $id)) return false;
        self::db()->query("DELETE FROM contratos WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    /** Conteos por situación y monto vigente */
    public static function resumen(int $pid): array
    {
        $r = ['vigente' => 0, 'por_vencer' => 0, 'vencido' => 0, 'cerrado' => 0, 'monto' => 0.0];
        foreach (self::lista($pid) as $c) {
            $r[$c['situacion']]++;
            if ($c['situacion'] !== 'cerrado') $r['monto'] += (float)$c['monto'];
        }
        return $r;
    }

    /** Avisos para el inicio/acciones: por vencer o vencidos, con días restantes */
    public static function porVencer(int $pid): array
    {
        return array_values(array_filter(self::lista($pid), fn($c) => in_array($c['situacion'], ['por_vencer', 'vencido'], true)));
    }

    private static function fecha($v): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }
}
