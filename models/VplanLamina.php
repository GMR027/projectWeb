<?php

namespace Model;

class VplanLamina extends ActiveRecord
{
    protected static $tabla = 'vplan_laminas';
    protected static $columnasDB = ['id', 'proyecto_id', 'partida_id', 'titulo', 'supervisor', 'recovery_cols', 'restr_auto', 'plano', 'simbologia', 'marcas_json', 'marcas_png', 'marcas_estados_hash', 'orden'];

    public $id, $proyecto_id, $partida_id, $titulo, $supervisor, $recovery_cols, $restr_auto, $plano, $simbologia, $marcas_json, $marcas_png, $marcas_estados_hash, $orden;
    // Unión (solo lectura)
    public $partida_nombre;

    public const CAMPOS_RECOVERY = ['r1_inicio', 'r1_fin', 'r2_inicio', 'r2_fin'];

    public function __construct($args = [])
    {
        foreach (static::$columnasDB as $c) {
            $this->$c = $args[$c] ?? null;
        }
        $this->titulo = $args['titulo'] ?? '';
        $this->supervisor = $args['supervisor'] ?? '';
        $this->orden = $args['orden'] ?? 0;
        $this->recovery_cols = (int)($args['recovery_cols'] ?? 0);
        $this->restr_auto = (int)($args['restr_auto'] ?? 0);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->titulo)) self::$alertas['error'][] = 'El título de la lámina es obligatorio';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL(
            "SELECT l.*, p.nombre AS partida_nombre FROM vplan_laminas l
             LEFT JOIN partidas p ON p.id = l.partida_id
             WHERE l.proyecto_id = $proyectoId ORDER BY COALESCE(p.orden, 9999), p.codigo, l.orden, l.id"
        );
    }

    /** Tablas adicionales de la lámina, en orden */
    public function tablas(): array
    {
        return VplanTabla::consultarSQL(
            "SELECT t.* FROM vplan_tablas t JOIN vplan_lamina_tablas lt ON lt.tabla_id = t.id
             WHERE lt.lamina_id = " . (int)$this->id . " ORDER BY lt.orden, t.id"
        );
    }

    public function agregarTabla(int $tablaId): void
    {
        $id = (int)$this->id;
        $orden = (int)self::$db->query("SELECT COALESCE(MAX(orden),0)+1 FROM vplan_lamina_tablas WHERE lamina_id = $id")->fetch_row()[0];
        self::$db->query("INSERT IGNORE INTO vplan_lamina_tablas (lamina_id, tabla_id, orden) VALUES ($id, " . (int)$tablaId . ", $orden)");
    }

    public function quitarTabla(int $tablaId): void
    {
        self::$db->query("DELETE FROM vplan_lamina_tablas WHERE lamina_id = " . (int)$this->id . " AND tabla_id = " . (int)$tablaId);
    }

    /** Intercambia el orden con la tabla vecina (dir -1 sube, +1 baja) */
    public function moverTabla(int $tablaId, int $dir): void
    {
        $ids = array_map(fn($t) => (int)$t->id, $this->tablas());
        $i = array_search($tablaId, $ids, true);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) return;
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $orden => $tid) {
            self::$db->query("UPDATE vplan_lamina_tablas SET orden = $orden WHERE lamina_id = " . (int)$this->id . " AND tabla_id = $tid");
        }
    }

    /** [actividad_id => fila de recovery] */
    public function recovery(): array
    {
        $res = self::$db->query("SELECT * FROM vplan_recovery WHERE lamina_id = " . (int)$this->id);
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(int)$r['actividad_id']] = $r;
        return $out;
    }

    public function guardarRecovery(int $actividadId, string $campo, ?string $valor): void
    {
        if (!in_array($campo, self::CAMPOS_RECOVERY, true)) return;
        $id = (int)$this->id;
        $aid = (int)$actividadId;
        $v = $valor === null ? 'NULL' : "'" . self::$db->escape_string($valor) . "'";
        self::$db->query("INSERT INTO vplan_recovery (lamina_id, actividad_id, $campo) VALUES ($id, $aid, $v) ON DUPLICATE KEY UPDATE $campo = $v");
    }
}
