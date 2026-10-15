<?php

namespace Model;

/** Un MSR (reporte de status de materiales) de un proyecto; agrupa paquetes de submittals */
class Msr extends ActiveRecord
{
    protected static $tabla = 'msrs';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'descripcion'];

    public $id;
    public $proyecto_id;
    public $nombre;
    public $descripcion;
    // Conteos (solo lectura)
    public $n_submittals, $n_renglones;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre del MSR es obligatorio';
        if (mb_strlen($this->nombre) > 150) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 150)';
        if (mb_strlen($this->descripcion) > 500) self::$alertas['error'][] = 'La descripción es demasiado larga (máx. 500)';
        return self::$alertas;
    }

    /** MSR del proyecto con sus conteos */
    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL(
            "SELECT m.*,
                (SELECT COUNT(*) FROM submittals s WHERE s.msr_id = m.id) AS n_submittals,
                (SELECT COUNT(*) FROM msr_items i WHERE i.msr_id = m.id) AS n_renglones
             FROM msrs m WHERE m.proyecto_id = $proyectoId ORDER BY m.id ASC"
        );
    }

    /** Si el proyecto no tiene ningún MSR crea «MSR 1»; devuelve el primero */
    public static function asegurar(int $proyectoId): self
    {
        $res = self::consultarSQL("SELECT * FROM msrs WHERE proyecto_id = $proyectoId ORDER BY id ASC LIMIT 1");
        if ($res) return $res[0];
        $m = new self(['proyecto_id' => $proyectoId, 'nombre' => 'MSR 1']);
        $m->id = $m->guardar()['id'];
        return $m;
    }

    /** MSR por id, solo si es del proyecto */
    public static function deProyectoPorId(int $proyectoId, $id): ?self
    {
        $m = self::find(filter_var($id, FILTER_VALIDATE_INT));
        return $m && (int)$m->proyecto_id === $proyectoId ? $m : null;
    }

    /** Partidas propias: [['id','titulo','materiales'=>[ids]]] (materiales sin submittal en esa sección) */
    public function partidas(): array
    {
        $db = self::$db;
        $mid = (int)$this->id;
        $out = [];
        $res = $db->query("SELECT id, titulo FROM msr_partidas WHERE msr_id = $mid ORDER BY orden ASC, id ASC");
        while ($r = $res->fetch_assoc()) {
            $t = $db->escape_string($r['titulo']);
            $ids = [];
            $q = $db->query("SELECT material_id FROM msr_items WHERE msr_id = $mid AND submittal_id IS NULL AND seccion = '$t' ORDER BY orden ASC");
            while ($m = $q->fetch_row()) $ids[] = (int)$m[0];
            $out[] = ['id' => (int)$r['id'], 'titulo' => $r['titulo'], 'materiales' => $ids];
        }
        return $out;
    }

    /** Valida las partidas del formulario; devuelve mensajes de error */
    public static function validarPartidas(array $partidas): array
    {
        $errores = [];
        $vistos = [];
        foreach ($partidas as $p) {
            $t = trim($p['titulo'] ?? '');
            if ($t === '') {
                if (!empty($p['materiales'])) $errores[] = 'Hay una partida con materiales pero sin título';
                continue;
            }
            if (mb_strlen($t) > 150) $errores[] = "El título «" . mb_substr($t, 0, 30) . "…» es demasiado largo (máx. 150)";
            if (isset($vistos[mb_strtolower($t)])) $errores[] = "La partida «$t» está repetida";
            $vistos[mb_strtolower($t)] = true;
        }
        return $errores;
    }

    /**
     * Aplica las partidas del formulario: crea/renombra/elimina partidas y sincroniza sus materiales
     * (renglones sin submittal). No toca los renglones que vienen de paquetes de submittal.
     */
    public function sincronizarPartidas(array $partidas): void
    {
        $db = self::$db;
        $mid = (int)$this->id;
        $pid = (int)$this->proyecto_id;
        $esc = fn($v) => $db->escape_string($v);
        $existentes = [];
        $res = $db->query("SELECT id, titulo FROM msr_partidas WHERE msr_id = $mid");
        while ($r = $res->fetch_assoc()) $existentes[(int)$r['id']] = $r['titulo'];

        $conservar = [];
        $orden = (int)$db->query("SELECT COALESCE(MAX(orden),0) FROM msr_items WHERE proyecto_id = $pid")->fetch_row()[0];
        $nPart = 0;
        foreach ($partidas as $p) {
            $titulo = trim($p['titulo'] ?? '');
            if ($titulo === '') continue;
            $t = $esc($titulo);
            $id = (int)($p['id'] ?? 0);
            if ($id && isset($existentes[$id])) {
                if ($existentes[$id] !== $titulo) {
                    $viejo = $esc($existentes[$id]);
                    $db->query("UPDATE msr_items SET seccion = '$t' WHERE msr_id = $mid AND seccion = '$viejo'");
                }
                $db->query("UPDATE msr_partidas SET titulo = '$t', orden = $nPart WHERE id = $id");
            } else {
                $db->query("INSERT INTO msr_partidas (msr_id, titulo, orden) VALUES ($mid, '$t', $nPart)");
                $id = (int)$db->insert_id;
            }
            $conservar[] = $id;
            $nPart++;

            // Materiales del catálogo (solo de este proyecto)
            $ids = array_values(array_unique(array_filter(array_map('intval', $p['materiales'] ?? []))));
            if ($ids) {
                $r2 = $db->query("SELECT id FROM materiales WHERE proyecto_id = $pid AND id IN (" . implode(',', $ids) . ")");
                $ids = [];
                while ($m = $r2->fetch_row()) $ids[] = (int)$m[0];
            }
            $db->query("DELETE FROM msr_items WHERE msr_id = $mid AND submittal_id IS NULL AND seccion = '$t'" . ($ids ? ' AND material_id NOT IN (' . implode(',', $ids) . ')' : ''));
            foreach ($ids as $matId) {
                $ya = $db->query("SELECT 1 FROM msr_items WHERE msr_id = $mid AND submittal_id IS NULL AND material_id = $matId AND seccion = '$t' LIMIT 1");
                if ($ya && $ya->num_rows) continue;
                $db->query("INSERT INTO msr_items (proyecto_id, msr_id, submittal_id, material_id, seccion, orden) VALUES ($pid, $mid, NULL, $matId, '$t', " . (++$orden) . ")");
                MsrItem::heredarDeOtroMsr((int)$db->insert_id);
            }
        }
        // Partidas quitadas del formulario: se van con sus materiales sin submittal
        foreach ($existentes as $id => $titulo) {
            if (in_array($id, $conservar, true)) continue;
            $db->query("DELETE FROM msr_items WHERE msr_id = $mid AND submittal_id IS NULL AND seccion = '" . $esc($titulo) . "'");
            $db->query("DELETE FROM msr_partidas WHERE id = $id");
        }
        // Fechas de compra/entrega compartidas con el catálogo
        $db->query("UPDATE msr_items i JOIN materiales m ON m.id = i.material_id
            SET i.fecha_compra_prog = m.fecha_compra_prog, i.fecha_compra_real = m.fecha_compra_real,
                i.fecha_entrega_prog = m.fecha_entrega_prog, i.fecha_entrega_real = m.fecha_entrega_real
            WHERE i.msr_id = $mid AND i.submittal_id IS NULL");
    }
}
