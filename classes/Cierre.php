<?php

namespace Classes;

/**
 * Cierre de obra: lista de entrega (as-builts, garantías, manuales, punch, documentos), su avance
 * y el acta de entrega-recepción en PDF.
 */
class Cierre
{
    use AccesoDb;

    public const CATEGORIAS = [
        'as_built' => 'Planos as-built',
        'garantia' => 'Garantías',
        'manual' => 'Manuales y operación',
        'punch' => 'Punch list / pendientes',
        'documento' => 'Documentos y actas',
        'otro' => 'Otros',
    ];
    public const ESTADOS = ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'completado' => 'Completado', 'na' => 'No aplica'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM cierre_items LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function config(int $pid): array
    {
        return self::filas("SELECT * FROM cierre_obra WHERE proyecto_id = $pid")[0]
            ?? ['proyecto_id' => $pid, 'fecha_entrega' => null, 'responsable' => '', 'notas' => ''];
    }

    public static function guardarConfig(int $pid, array $d): void
    {
        $fecha = self::fecha($d['fecha_entrega'] ?? '');
        $resp = self::esc(mb_substr(trim((string)($d['responsable'] ?? '')), 0, 150));
        $notas = self::esc(mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000));
        self::db()->query("INSERT INTO cierre_obra (proyecto_id, fecha_entrega, responsable, notas)
            VALUES ($pid, " . ($fecha ? "'$fecha'" : 'NULL') . ", '$resp', '$notas')
            ON DUPLICATE KEY UPDATE fecha_entrega = VALUES(fecha_entrega), responsable = VALUES(responsable), notas = VALUES(notas)");
    }

    public static function items(int $pid, string $categoria = ''): array
    {
        $w = isset(self::CATEGORIAS[$categoria]) ? " AND categoria = '$categoria'" : '';
        return self::filas("SELECT i.*, a.nombre archivo_nombre,
                DATEDIFF(i.fecha_compromiso, CURDATE()) dias
            FROM cierre_items i LEFT JOIN archivos a ON a.id = i.archivo_id
            WHERE i.proyecto_id = $pid$w
            ORDER BY FIELD(i.estado, 'pendiente', 'en_proceso', 'completado', 'na'), i.orden, i.id");
    }

    public static function item(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM cierre_items WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    public static function guardarItem(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $titulo = trim((string)($d['titulo'] ?? ''));
        if ($titulo === '') return [false, 'El título es obligatorio', 0];
        $cat = isset(self::CATEGORIAS[$d['categoria'] ?? '']) ? $d['categoria'] : 'documento';
        $estado = isset(self::ESTADOS[$d['estado'] ?? '']) ? $d['estado'] : 'pendiente';
        $fecha = self::fecha($d['fecha_compromiso'] ?? '');
        $archivoId = (int)($d['archivo_id'] ?? 0);
        if ($archivoId) {
            $ok = self::filas("SELECT 1 FROM archivos WHERE id = $archivoId AND proyecto_id = $pid");
            if (!$ok) $archivoId = 0;
        }
        $campos = "categoria = '$cat', titulo = '" . self::esc(mb_substr($titulo, 0, 250)) . "',
            responsable = '" . self::esc(mb_substr(trim((string)($d['responsable'] ?? '')), 0, 150)) . "',
            fecha_compromiso = " . ($fecha ? "'$fecha'" : 'NULL') . ", estado = '$estado',
            archivo_id = " . ($archivoId ?: 'NULL') . ",
            notas = '" . self::esc(mb_substr(trim((string)($d['notas'] ?? '')), 0, 1000)) . "',
            orden = " . max(0, (int)($d['orden'] ?? 0));
        if ($id) {
            if (!self::item($pid, $id)) return [false, 'El punto no existe', 0];
            self::db()->query("UPDATE cierre_items SET $campos WHERE id = $id AND proyecto_id = $pid");
        } else {
            self::db()->query("INSERT INTO cierre_items SET proyecto_id = $pid, $campos");
            $id = (int)self::db()->insert_id;
        }
        return [true, 'Punto guardado', $id];
    }

    public static function eliminarItem(int $pid, int $id): bool
    {
        if (!self::item($pid, $id)) return false;
        self::db()->query("DELETE FROM cierre_items WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    /** Lista estándar de entrega (solo si el proyecto aún no tiene puntos) */
    public static function crearSugeridos(int $pid): int
    {
        $n = (int)(self::filas("SELECT COUNT(*) n FROM cierre_items WHERE proyecto_id = $pid")[0]['n'] ?? 0);
        if ($n) return 0;
        $base = [
            ['as_built', 'Planos as-built de todas las disciplinas'],
            ['as_built', 'Planos de instalaciones y detalles constructivos finales'],
            ['garantia', 'Garantías de equipos y materiales'],
            ['garantia', 'Fianzas de vicios ocultos y cumplimiento'],
            ['manual', 'Manuales de operación y mantenimiento'],
            ['manual', 'Capacitación al personal del cliente'],
            ['punch', 'Punch list cerrado y firmado'],
            ['documento', 'Actas de entrega-recepción firmadas'],
            ['documento', 'Certificados de calidad y pruebas'],
            ['documento', 'Licencias, permisos y aprobaciones finales'],
        ];
        $i = 0;
        foreach ($base as [$cat, $titulo]) {
            self::db()->query("INSERT INTO cierre_items (proyecto_id, categoria, titulo, orden) VALUES ($pid, '$cat', '" . self::esc($titulo) . "', " . (++$i) . ")");
        }
        return $i;
    }

    public static function resumen(int $pid): array
    {
        $r = self::filas("SELECT
            COUNT(*) total,
            SUM(estado = 'completado') completados,
            SUM(estado = 'en_proceso') en_proceso,
            SUM(estado = 'pendiente') pendientes,
            SUM(estado = 'na') na
            FROM cierre_items WHERE proyecto_id = $pid")[0] ?? [];
        $total = (int)($r['total'] ?? 0);
        $hechos = (int)($r['completados'] ?? 0) + (int)($r['na'] ?? 0);
        $porCat = [];
        foreach (self::filas("SELECT categoria, COUNT(*) total, SUM(estado = 'completado') completados FROM cierre_items WHERE proyecto_id = $pid GROUP BY categoria") as $c) {
            $porCat[$c['categoria']] = ['total' => (int)$c['total'], 'completados' => (int)$c['completados']];
        }
        return ['total' => $total, 'completados' => (int)($r['completados'] ?? 0), 'en_proceso' => (int)($r['en_proceso'] ?? 0),
            'pendientes' => (int)($r['pendientes'] ?? 0), 'na' => (int)($r['na'] ?? 0),
            'pct' => $total > 0 ? round($hechos / $total * 100) : 0, 'por_categoria' => $porCat];
    }

    private static function fecha($v): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }
}
