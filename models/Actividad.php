<?php

namespace Model;

class Actividad extends ActiveRecord
{
    protected static $tabla = 'actividades';
    protected static $columnasDB = ['id', 'partida_id', 'codigo', 'nombre', 'descripcion', 'fecha_inicio', 'duracion_dias', 'fecha_fin', 'progreso', 'prioridad', 'status', 'pta', 'hito', 'sin_material', 'fecha_fija', 'orden'];

    public $id;
    public $partida_id;
    public $codigo;
    public $nombre;
    public $descripcion;
    public $fecha_inicio;
    public $duracion_dias;
    public $fecha_fin;
    public $progreso;
    public $prioridad;
    public $status;
    public $pta;
    public $hito;
    public $sin_material;
    public $fecha_fija;
    public $orden;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->partida_id = $args['partida_id'] ?? null;
        $this->codigo = $args['codigo'] ?? '';
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->fecha_inicio = $args['fecha_inicio'] ?? '';
        $this->duracion_dias = $args['duracion_dias'] ?? 1;
        $this->fecha_fin = $args['fecha_fin'] ?? '';
        $this->progreso = $args['progreso'] ?? 0;
        $this->prioridad = $args['prioridad'] ?? 'media';
        $this->status = $args['status'] ?? 'no_iniciada';
        $this->pta = $args['pta'] ?? 1;
        $this->hito = (int)($args['hito'] ?? 0);
        $this->sin_material = (int)($args['sin_material'] ?? 0);
        $this->fecha_fija = (int)($args['fecha_fija'] ?? 0);
        $this->orden = $args['orden'] ?? 0;
    }

    public function validar(): array
    {
        if (!$this->codigo) {
            self::$alertas['error'][] = 'El código de la actividad es obligatorio';
        }
        if (!$this->nombre) {
            self::$alertas['error'][] = 'El nombre es obligatorio';
        }
        if (!$this->fecha_inicio) {
            self::$alertas['error'][] = 'La fecha de inicio es obligatoria';
        }
        if (!$this->esHito() && (int)$this->duracion_dias < 1) {
            self::$alertas['error'][] = 'La duración debe ser al menos 1 día hábil (o marca la actividad como hito)';
        }
        if ((int)$this->progreso < 0 || (int)$this->progreso > 100) {
            self::$alertas['error'][] = 'El progreso debe estar entre 0 y 100';
        }
        return self::$alertas;
    }

    public function esHito(): bool
    {
        return (int)$this->hito === 1;
    }

    /** Un hito dura 0 días: termina el mismo día que empieza */
    public function normalizarHito(): void
    {
        if ($this->esHito()) {
            $this->duracion_dias = 0;
            $this->fecha_fin = $this->fecha_inicio;
        }
    }

    /** Guarda y, si cambió el avance, anota el valor del día para la Curva S (cuando una actividad se marca completada queda su fecha) */
    public function guardar()
    {
        $antes = null;
        if (!is_null($this->id)) {
            $r = self::$db->query('SELECT progreso FROM actividades WHERE id = ' . (int)$this->id);
            $f = $r ? $r->fetch_row() : null;
            $antes = $f ? (int)$f[0] : null;
        }
        $res = parent::guardar();
        $id = is_array($res) ? ($res['id'] ?? $this->id) : $this->id;
        if ($res && $id && (int)$this->progreso !== ($antes ?? 0)) {
            try {
                self::$db->query('INSERT INTO actividad_avance (actividad_id, fecha, progreso) VALUES (' . (int)$id . ', CURDATE(), ' . (int)$this->progreso . ') ON DUPLICATE KEY UPDATE progreso = VALUES(progreso)');
            } catch (\Throwable $e) {   // migración de la Curva S pendiente: no debe impedir guardar la actividad
            }
        }
        return $res;
    }

    /** Sincroniza status según progreso */
    public function sincronizarStatus(): void
    {
        $p = (int)$this->progreso;
        if ($p === 0) {
            $this->status = 'no_iniciada';
        } elseif ($p >= 100) {
            $this->status = 'completada';
            $this->progreso = 100;
        } else {
            $this->status = 'en_proceso';
        }
    }

    public function getRestricciones(): array
    {
        return Restriccion::whereAll('actividad_id', $this->id, 'creado DESC');
    }

    public function getRestriccionesActivas(): array
    {
        $query = "SELECT * FROM restricciones WHERE actividad_id = '" . intval($this->id) . "' AND estado = 'activa' ORDER BY creado DESC";
        return Restriccion::consultarSQL($query);
    }

    public function tieneRestriccionActiva(): bool
    {
        return count($this->getRestriccionesActivas()) > 0;
    }
}
