<?php

namespace Model;

class Submittal extends ActiveRecord
{
    protected static $tabla = 'submittals';
    protected static $columnasDB = ['id', 'proyecto_id', 'msr_id', 'fecha', 'numero_trabajo', 'departamento', 'para', 'numero_submittal', 'especialidad', 'estatus', 'fecha_respuesta'];

    public $id;
    public $proyecto_id;
    public $msr_id;
    public $fecha;
    public $numero_trabajo;
    public $departamento;
    public $para;
    public $numero_submittal;
    public $especialidad;
    public $estatus;
    public $fecha_respuesta;

    public const ESTATUS = ['enviado' => 'En revisión', 'rechazado' => 'Resubmitar', 'aprobado' => 'Aprobado', 'aprobado_an' => 'A/AN'];
    /** Status equivalente en el MSR */
    public const STATUS_MSR = ['enviado' => 'P', 'rechazado' => 'RA', 'aprobado' => 'A', 'aprobado_an' => 'AN'];

    public static function esAprobado(?string $estatus): bool
    {
        return in_array($estatus, ['aprobado', 'aprobado_an'], true);
    }

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->msr_id = $args['msr_id'] ?? null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->numero_trabajo = $args['numero_trabajo'] ?? '';
        $this->departamento = $args['departamento'] ?? '';
        $this->para = $args['para'] ?? '';
        $this->numero_submittal = $args['numero_submittal'] ?? '';
        $this->especialidad = $args['especialidad'] ?? '';
        $this->estatus = $args['estatus'] ?? 'enviado';
        $this->fecha_respuesta = $args['fecha_respuesta'] ?? null;
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

    /**
     * Pasa el estatus del submittal a sus renglones del MSR: status equivalente, y AnswerDate
     * al aprobar (se limpia si deja de estar aprobado).
     */
    public function sincronizarMsr(?string $estatusAnterior = null): void
    {
        $db = self::$db;
        $sid = (int)$this->id;
        $st = self::STATUS_MSR[$this->estatus] ?? 'P';
        $items = "UPDATE msr_items SET status = '$st'";
        if (self::esAprobado($this->estatus)) {
            if (!self::esAprobado($estatusAnterior)) {
                $this->fecha_respuesta = date('Y-m-d');
                $db->query("UPDATE submittals SET fecha_respuesta = CURDATE() WHERE id = $sid");
                $items .= ", fecha_respuesta = CURDATE()";
            } elseif ($this->fecha_respuesta) {
                $items .= ", fecha_respuesta = COALESCE(fecha_respuesta, '" . $db->escape_string($this->fecha_respuesta) . "')";
            } else {
                $this->fecha_respuesta = date('Y-m-d');
                $db->query("UPDATE submittals SET fecha_respuesta = CURDATE() WHERE id = $sid");
                $items .= ", fecha_respuesta = COALESCE(fecha_respuesta, CURDATE())";
            }
        } elseif (self::esAprobado($estatusAnterior)) {
            $this->fecha_respuesta = null;
            $db->query("UPDATE submittals SET fecha_respuesta = NULL WHERE id = $sid");
            $items .= ", fecha_respuesta = NULL";
        }
        $db->query("$items WHERE " . $this->condicionRenglones());
    }

    /** Renglones del paquete y los renglones sueltos (sin submittal) de sus mismos materiales: comparten status y fechas del submittal */
    private function condicionRenglones(): string
    {
        $sid = (int)$this->id;
        return "(submittal_id = $sid OR (submittal_id IS NULL AND proyecto_id = " . (int)$this->proyecto_id
            . " AND material_id IN (SELECT material_id FROM submittal_materiales WHERE submittal_id = $sid)))";
    }

    /** Fecha del submittal y de respuesta → renglones del MSR (Real Submittal Date y AnswerDate) */
    public function sincronizarFechas(): void
    {
        $f = self::$db->escape_string($this->fecha);
        $r = $this->fecha_respuesta ? "'" . self::$db->escape_string($this->fecha_respuesta) . "'" : 'NULL';
        self::$db->query("UPDATE msr_items SET fecha_envio_real = '$f', fecha_respuesta = $r WHERE " . $this->condicionRenglones());
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
        // Los renglones viven en el MSR del paquete; sin MSR no hay renglones
        $msr = $this->msr_id ? (int)$this->msr_id : null;
        if ($msr) {
            $db->query("UPDATE msr_items SET msr_id = $msr WHERE submittal_id = $sid");
        } else {
            $db->query("DELETE FROM msr_items WHERE submittal_id = $sid");
        }
        $db->query("DELETE FROM submittal_materiales WHERE submittal_id = $sid" . ($ids ? ' AND material_id NOT IN (' . implode(',', $ids) . ')' : ''));
        $db->query("DELETE FROM msr_items WHERE submittal_id = $sid" . ($ids ? ' AND material_id NOT IN (' . implode(',', $ids) . ')' : ''));
        $orden = (int)($db->query("SELECT COALESCE(MAX(orden),0) FROM msr_items WHERE proyecto_id = $pid")->fetch_row()[0]);
        foreach ($ids as $mid) {
            $db->query("INSERT IGNORE INTO submittal_materiales (submittal_id, material_id) VALUES ($sid, $mid)");
            if ($msr) {
                $db->query("INSERT IGNORE INTO msr_items (proyecto_id, msr_id, submittal_id, material_id, orden) VALUES ($pid, $msr, $sid, $mid, " . (++$orden) . ")");
                if ($db->affected_rows > 0) {
                    $nuevo = (int)$db->insert_id;
                    MsrItem::heredarDeOtroMsr($nuevo);
                    // El status y la respuesta son del submittal
                    $db->query("UPDATE msr_items SET status = '" . (self::STATUS_MSR[$this->estatus] ?? 'P') . "', fecha_respuesta = "
                        . ($this->fecha_respuesta ? "'" . $db->escape_string($this->fecha_respuesta) . "'" : 'NULL') . " WHERE id = $nuevo");
                }
            }
        }
        self::$db->query("UPDATE msr_items i JOIN materiales m ON m.id = i.material_id SET i.fecha_compra_prog = m.fecha_compra_prog, i.fecha_compra_real = m.fecha_compra_real, i.fecha_entrega_prog = m.fecha_entrega_prog, i.fecha_entrega_real = m.fecha_entrega_real WHERE i.submittal_id = $sid");
        self::$db->query("UPDATE msr_items SET fecha_envio_real = '" . self::$db->escape_string($this->fecha) . "' WHERE submittal_id = $sid");
        if ($this->fecha_respuesta) self::$db->query("UPDATE msr_items SET fecha_respuesta = '" . self::$db->escape_string($this->fecha_respuesta) . "' WHERE submittal_id = $sid AND fecha_respuesta IS NULL");
    }
}
