<?php

namespace Model;

class Partida extends ActiveRecord
{
    protected static $tabla = 'partidas';
    protected static $columnasDB = ['id', 'proyecto_id', 'codigo', 'nombre', 'descripcion', 'color_bg', 'orden', 'fecha_inicio', 'fecha_fin'];

    public $id;
    public $proyecto_id;
    public $codigo;
    public $nombre;
    public $descripcion;
    public $color_bg;
    public $orden;
    public $fecha_inicio;
    public $fecha_fin;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->codigo = $args['codigo'] ?? '';
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->color_bg = $args['color_bg'] ?? null;
        $this->orden = $args['orden'] ?? 0;
        $this->fecha_inicio = $args['fecha_inicio'] ?? null;
        $this->fecha_fin = $args['fecha_fin'] ?? null;
    }

    public function validar(): array
    {
        if (!$this->codigo) {
            self::$alertas['error'][] = 'El código de la partida es obligatorio';
        }
        if (!$this->nombre) {
            self::$alertas['error'][] = 'El nombre de la partida es obligatorio';
        }
        return self::$alertas;
    }

    public function getActividades(): array
    {
        return Actividad::whereAll('partida_id', $this->id, 'orden ASC, codigo ASC');
    }

    /**
     * Elimina la partida junto con todas sus actividades y lo que depende de ellas: restricciones, dependencias, materiales
     * vinculados, avance, recovery y capas del vPlan/mapa de avance (en cascada); evidencias, punch, permisos, incidentes y
     * órdenes de cambio conservan su registro sin actividad. Además retira la partida de los bloques de tareas del vPlan y
     * refresca las fechas de instalación del MSR.
     */
    public function eliminarConActividades(): bool
    {
        $id = (int)$this->id;
        $proyectoId = (int)$this->proyecto_id;
        $n = (int)(self::$db->query("SELECT COUNT(*) FROM actividades WHERE partida_id = $id")->fetch_row()[0]);
        self::$db->query("DELETE FROM actividades WHERE partida_id = $id");
        $ok = $this->eliminar();
        if ($ok && $n > 0) {
            // Las actividades se borran con SQL directo (cascada): se anotan aparte, sin qué revertir
            $etiqueta = trim(($this->codigo ?? '') . ' ' . ($this->nombre ?? ''));
            \Classes\HistorialCambios::registrar($proyectoId, 'programa', 'eliminado',
                "Se eliminaron $n actividad(es) con la partida " . $etiqueta, ['id' => $id, 'actividades' => $n], null,
                ['partida', $id, $etiqueta]);
        }
        if ($ok) {
            self::quitarDeBloquesVplan($proyectoId, $id);
            ActividadMaterial::sincronizarInstalacion($proyectoId);
        }
        return (bool)$ok;
    }

    /** Quita la partida de las fuentes de los bloques de tareas del vPlan (si un bloque se queda sin fuentes, queda vacío) */
    private static function quitarDeBloquesVplan(int $proyectoId, int $partidaId): void
    {
        $r = self::$db->query(
            "SELECT b.id, b.config_json FROM vplan_bloques b JOIN vplan_laminas l ON l.id = b.lamina_id
             WHERE l.proyecto_id = $proyectoId AND b.tipo = 'tareas' AND b.config_json LIKE '%\"partida\"%'"
        );
        while ($r && ($fila = $r->fetch_assoc())) {
            $cfg = json_decode((string)$fila['config_json'], true);
            if (!is_array($cfg) || !isset($cfg['fuentes']) || !is_array($cfg['fuentes'])) continue;
            $fuentes = array_values(array_filter($cfg['fuentes'], fn($f) => (int)($f['partida'] ?? 0) !== $partidaId));
            if (count($fuentes) === count($cfg['fuentes'])) continue;
            $cfg['fuentes'] = $fuentes;
            $json = self::$db->escape_string(json_encode($cfg, JSON_UNESCAPED_UNICODE));
            self::$db->query("UPDATE vplan_bloques SET config_json = '$json' WHERE id = " . (int)$fila['id']);
        }
    }

    /** Recalcula fecha_inicio y fecha_fin de la partida según sus actividades */
    public function recalcularFechas(): void
    {
        $actividades = $this->getActividades();
        if (empty($actividades)) return;
        $inicios = array_map(fn($a) => $a->fecha_inicio, $actividades);
        $fines = array_map(fn($a) => $a->fecha_fin, $actividades);
        $this->fecha_inicio = min($inicios);
        $this->fecha_fin = max($fines);
        $this->guardar();
    }

    /** Progreso ponderado de la partida */
    public function getProgreso(): float
    {
        $actividades = $this->getActividades();
        if (empty($actividades)) return 0;
        $total = 0;
        $suma = 0;
        foreach ($actividades as $act) {
            $peso = max(1, (int)$act->duracion_dias);
            $total += $peso;
            $suma += $peso * (int)$act->progreso;
        }
        return $total > 0 ? round($suma / $total, 1) : 0;
    }
}
