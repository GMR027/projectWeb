<?php

namespace Model;

/** Estampa inteligente de un proyecto: nombre + configuración (etiquetas con campos que se llenan solos). Ver Classes\Estampas */
class Estampa extends ActiveRecord
{
    protected static $tabla = 'estampas';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'config_json'];

    public $id, $proyecto_id, $nombre, $config_json;
    public $creado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 100);
        $this->config_json = $args['config_json'] ?? '{}';
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre de la estampa es obligatorio';
        $c = $this->config();
        if (empty($c['etiquetas']) && empty($c['elementos'])) self::$alertas['error'][] = 'Agrega al menos una etiqueta o un elemento';
        return self::$alertas;
    }

    public function config(): array
    {
        $c = json_decode((string)$this->config_json, true);
        return is_array($c) ? $c : [];
    }

    public function aArray(): array
    {
        return ['id' => (int)$this->id, 'nombre' => $this->nombre, 'config' => (object)$this->config()];
    }

    /** ¿Está creada la tabla? (migración pendiente en la BD) */
    public static function disponible(): bool
    {
        try {
            return (bool)self::$db->query('SELECT 1 FROM estampas LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function deProyecto(int $proyectoId): array
    {
        if (!self::disponible()) return [];
        return self::consultarSQL("SELECT * FROM estampas WHERE proyecto_id = $proyectoId ORDER BY nombre, id");
    }

    /** Lista corta para insertar en el modo diseño del vPlan */
    public static function paraInsertar(int $proyectoId): array
    {
        return array_map(function ($e) {
            $c = $e->config();
            return ['id' => (int)$e->id, 'nombre' => $e->nombre, 'w' => (float)($c['w'] ?? 26), 'h' => (float)($c['h'] ?? 16), 'disposicion' => (string)($c['disposicion'] ?? 'lineas')];
        }, self::deProyecto($proyectoId));
    }

    /** Estampa que el proyecto usa como plantilla del post-it de Last Planner (null = el diseño clásico) */
    public static function lastPlanner(int $proyectoId): ?self
    {
        foreach (self::deProyecto($proyectoId) as $e) {
            $c = $e->config();
            if (!empty($c['lp']) && ($c['modo'] ?? '') === 'lienzo') return $e;
        }
        return null;
    }

    /** Marca una estampa (id) como plantilla del post-it y desmarca las demás; 0 vuelve al diseño clásico */
    public static function usarEnLastPlanner(int $proyectoId, int $id): bool
    {
        $ok = $id === 0;
        foreach (self::deProyecto($proyectoId) as $e) {
            $c = $e->config();
            $marca = (int)$e->id === $id && ($c['modo'] ?? '') === 'lienzo';
            if ($marca) $ok = true;
            if (!empty($c['lp']) !== $marca) {
                $c['lp'] = $marca;
                $e->config_json = json_encode($c, JSON_UNESCAPED_UNICODE);
                $e->guardar();
            }
        }
        return $ok;
    }
}
