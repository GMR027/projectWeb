<?php

namespace Model;

class Submittal extends ActiveRecord
{
    protected static $tabla = 'submittals';
    protected static $columnasDB = ['id', 'proyecto_id', 'fecha', 'numero_trabajo', 'departamento', 'para', 'numero_submittal', 'especialidad'];

    public $id;
    public $proyecto_id;
    public $fecha;
    public $numero_trabajo;
    public $departamento;
    public $para;
    public $numero_submittal;
    public $especialidad;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->numero_trabajo = $args['numero_trabajo'] ?? '';
        $this->departamento = $args['departamento'] ?? '';
        $this->para = $args['para'] ?? '';
        $this->numero_submittal = $args['numero_submittal'] ?? '';
        $this->especialidad = $args['especialidad'] ?? '';
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->numero_submittal)) {
            self::$alertas['error'][] = 'El número de submittal es obligatorio';
        }
        if (!$this->fecha || !\DateTime::createFromFormat('Y-m-d', $this->fecha)) {
            self::$alertas['error'][] = 'La fecha no es válida';
        }
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM submittals WHERE proyecto_id = $proyectoId ORDER BY numero_submittal ASC, id ASC");   // alfabético por número de submittal
    }

    public static function buscarPorNumero(int $proyectoId, string $numero): ?self
    {
        $n = self::$db->escape_string($numero);
        $res = self::consultarSQL("SELECT * FROM submittals WHERE proyecto_id = $proyectoId AND numero_submittal = '$n' LIMIT 1");
        return $res[0] ?? null;
    }

    public static function vincular(int $submittalId, int $materialId): void
    {
        self::$db->query("INSERT IGNORE INTO submittal_materiales (submittal_id, material_id) VALUES ($submittalId, $materialId)");
    }

    /** [submittal_id => cantidad de materiales] */
    public static function conteos(int $proyectoId): array
    {
        $res = self::$db->query("SELECT sm.submittal_id, COUNT(*) n FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE s.proyecto_id = $proyectoId GROUP BY sm.submittal_id");
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(int)$r['submittal_id']] = (int)$r['n'];
        return $out;
    }

    /** @return Material[] */
    public function materiales(): array
    {
        return Material::consultarSQL(
            "SELECT m.* FROM materiales m JOIN submittal_materiales sm ON sm.material_id = m.id
             WHERE sm.submittal_id = " . (int)$this->id . " ORDER BY m.nombre ASC"
        );
    }

    /** Ids de materiales del paquete */
    public function idsMateriales(): array
    {
        return array_map(fn($m) => (int)$m->id, $this->materiales());
    }

    /**
     * Reemplaza los materiales del paquete (solo del mismo proyecto) y mantiene el MSR:
     * agrega renglones nuevos y quita los de materiales retirados, sin tocar lo ya capturado.
     */
    public function sincronizarMateriales(array $ids): void
    {
        $db = self::$db;
        $sid = (int)$this->id;
        $pid = (int)$this->proyecto_id;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids) {
            $lista = implode(',', $ids);
            $res = $db->query("SELECT id FROM materiales WHERE proyecto_id = $pid AND id IN ($lista)");
            $ids = [];
            while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
        }
        $db->query("DELETE FROM submittal_materiales WHERE submittal_id = $sid" . ($ids ? ' AND material_id NOT IN (' . implode(',', $ids) . ')' : ''));
        $db->query("DELETE FROM msr_items WHERE submittal_id = $sid" . ($ids ? ' AND material_id NOT IN (' . implode(',', $ids) . ')' : ''));
        $orden = (int)($db->query("SELECT COALESCE(MAX(orden),0) FROM msr_items WHERE proyecto_id = $pid")->fetch_row()[0]);
        foreach ($ids as $mid) {
            $db->query("INSERT IGNORE INTO submittal_materiales (submittal_id, material_id) VALUES ($sid, $mid)");
            $db->query("INSERT IGNORE INTO msr_items (proyecto_id, submittal_id, material_id, orden) VALUES ($pid, $sid, $mid, " . (++$orden) . ")");
        }
    }
}
