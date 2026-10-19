<?php

namespace Model;

/** Un vPlan: agrupa las láminas (una por área) de un proyecto */
class VplanPaquete extends ActiveRecord
{
    protected static $tabla = 'vplan_paquetes';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'orden'];

    public $id, $proyecto_id, $nombre, $orden;
    // Unión (solo lectura)
    public $total, $visibles;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->orden = (int)($args['orden'] ?? 0);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre del vPlan es obligatorio';
        return self::$alertas;
    }

    /** Los vPlan del proyecto con cuántas láminas tienen; las láminas sin vPlan pasan al primero (o a uno nuevo) */
    public static function deProyecto(int $proyectoId): array
    {
        self::adoptarHuerfanas($proyectoId);
        return self::consultarSQL(
            "SELECT p.*, COUNT(l.id) AS total, COALESCE(SUM(l.visible = 1), 0) AS visibles
             FROM vplan_paquetes p LEFT JOIN vplan_laminas l ON l.paquete_id = p.id
             WHERE p.proyecto_id = $proyectoId GROUP BY p.id ORDER BY p.orden, p.id"
        );
    }

    /** Toda lámina debe pertenecer a un vPlan (las anteriores a esta función, o creadas por otras vías, se agrupan aquí) */
    public static function adoptarHuerfanas(int $proyectoId): void
    {
        $n = (int)self::$db->query("SELECT COUNT(*) FROM vplan_laminas WHERE proyecto_id = $proyectoId AND paquete_id IS NULL")->fetch_row()[0];
        if (!$n) return;
        $p = self::consultarSQL("SELECT * FROM vplan_paquetes WHERE proyecto_id = $proyectoId ORDER BY orden, id LIMIT 1")[0] ?? null;
        if (!$p) {
            $p = new self(['proyecto_id' => $proyectoId, 'nombre' => 'vPlan']);
            $p->id = $p->guardar()['id'];
        }
        self::$db->query("UPDATE vplan_laminas SET paquete_id = " . (int)$p->id . " WHERE proyecto_id = $proyectoId AND paquete_id IS NULL");
    }

    /** El primer vPlan del proyecto; se crea uno si no hay */
    public static function principal(int $proyectoId): self
    {
        $lista = self::deProyecto($proyectoId);
        if ($lista) return $lista[0];
        $p = new self(['proyecto_id' => $proyectoId, 'nombre' => 'vPlan']);
        $p->id = $p->guardar()['id'];
        return $p;
    }

    /** Láminas del vPlan en su orden; $soloVisibles las deja sin las ocultas */
    public function laminas(bool $soloVisibles = false): array
    {
        return VplanLamina::consultarSQL(
            "SELECT l.*, pa.nombre AS partida_nombre FROM vplan_laminas l LEFT JOIN partidas pa ON pa.id = l.partida_id
             WHERE l.paquete_id = " . (int)$this->id . ($soloVisibles ? ' AND l.visible = 1' : '') . " ORDER BY l.orden, l.id"
        );
    }
}
