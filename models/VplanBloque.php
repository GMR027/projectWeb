<?php

namespace Model;

/** Bloque posicionable de una lámina de vPlan. x, y, w, h van en % de la hoja. */
class VplanBloque extends ActiveRecord
{
    protected static $tabla = 'vplan_bloques';
    protected static $columnasDB = ['id', 'lamina_id', 'tipo', 'x', 'y', 'w', 'h', 'z', 'config_json'];

    public $id, $lamina_id, $tipo, $x, $y, $w, $h, $z, $config_json;

    public const TIPOS = [
        'titulo' => 'Título',
        'logo' => 'Logo',
        'plano' => 'Plano',
        'tareas' => 'Tareas (partidas)',
        'tabla' => 'Tabla adicional',
        'simbologia' => 'Simbología',
        'schedule' => 'Simbología del schedule',
        'supervisor' => 'Supervisor',
        'fecha' => 'Fecha de actualización',
        'texto' => 'Texto libre',
        'imagen' => 'Símbolo o simbología',
        'cajetin' => 'Cajetín',
        'estampa' => 'Estampa inteligente',
    ];

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->lamina_id = $args['lamina_id'] ?? null;
        $this->tipo = $args['tipo'] ?? 'texto';
        $this->x = (float)($args['x'] ?? 0);
        $this->y = (float)($args['y'] ?? 0);
        $this->w = (float)($args['w'] ?? 20);
        $this->h = (float)($args['h'] ?? 10);
        $this->z = (int)($args['z'] ?? 0);
        $this->config_json = $args['config_json'] ?? null;
    }

    public static function deLamina(int $laminaId): array
    {
        return self::consultarSQL("SELECT * FROM vplan_bloques WHERE lamina_id = $laminaId ORDER BY z, id");
    }

    public function config(): array
    {
        $c = json_decode((string)$this->config_json, true);
        return is_array($c) ? $c : [];
    }

    public function setConfig(array $c): void
    {
        $this->config_json = json_encode($c, JSON_UNESCAPED_UNICODE);
    }

    /** Para el JSON del editor de diseño */
    public function aArray(): array
    {
        return ['id' => (int)$this->id, 'tipo' => $this->tipo, 'x' => (float)$this->x, 'y' => (float)$this->y, 'w' => (float)$this->w, 'h' => (float)$this->h, 'z' => (int)$this->z, 'config' => (object)$this->config()];
    }
}
