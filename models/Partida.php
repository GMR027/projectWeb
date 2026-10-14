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
