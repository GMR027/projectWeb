<?php

namespace Model;

/** Ajustes del Lookahead de un proyecto (una fila por proyecto). Sin la tabla (migración pendiente) se usan los valores de siempre. */
class LookaheadConfig extends ActiveRecord
{
    protected static $tabla = 'lookahead_config';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'pta_nombre', 'semanas', 'titulo', 'encabezado_id'];

    public const MIN_SEMANAS = 1;
    public const MAX_SEMANAS = 8;
    public const TITULO_DEFECTO = '{semanas} Weeks Look Ahead · {proyecto}';

    public $id, $proyecto_id, $nombre, $pta_nombre, $semanas, $titulo, $encabezado_id;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 60);
        $this->pta_nombre = mb_substr(trim((string)($args['pta_nombre'] ?? '')), 0, 20);
        $this->semanas = max(self::MIN_SEMANAS, min(self::MAX_SEMANAS, (int)($args['semanas'] ?? 4)));
        $this->titulo = mb_substr(trim((string)($args['titulo'] ?? '')), 0, 150);
        $this->encabezado_id = !empty($args['encabezado_id']) ? (int)$args['encabezado_id'] : null;
    }

    public static function disponible(): bool
    {
        static $r;
        try {
            return $r ??= (bool)self::$db->query('SELECT 1 FROM lookahead_config LIMIT 1');
        } catch (\Throwable $e) {
            return $r = false;
        }
    }

    /** Ajustes del proyecto (valores de siempre si no hay fila o falta la tabla) */
    public static function deProyecto(int $proyectoId): self
    {
        if (self::disponible()) {
            $fila = self::where('proyecto_id', $proyectoId);
            if ($fila) return $fila;
        }
        return new self(['proyecto_id' => $proyectoId]);
    }

    public function nombreHerramienta(): string
    {
        return $this->nombre !== '' ? $this->nombre : 'Lookahead';
    }

    public function pta(): string
    {
        return $this->pta_nombre !== '' ? $this->pta_nombre : 'PTA';
    }

    /** Banner de la tabla con sus variables resueltas */
    public function tituloBanner(Proyecto $proyecto): string
    {
        $t = $this->titulo !== '' ? $this->titulo : self::TITULO_DEFECTO;
        return strtr($t, ['{semanas}' => (string)(int)$this->semanas, '{proyecto}' => (string)$proyecto->nombre, '{codigo}' => (string)$proyecto->codigo]);
    }

    /** Estampa de lienzo elegida como encabezado (null = el encabezado de siempre) */
    public function encabezado(): ?Estampa
    {
        if (!$this->encabezado_id || !Estampa::disponible()) return null;
        $e = Estampa::find((int)$this->encabezado_id);
        return $e && (int)$e->proyecto_id === (int)$this->proyecto_id && (($e->config()['modo'] ?? '') === 'lienzo') ? $e : null;
    }

    /** Estampas de lienzo del proyecto que sirven de encabezado */
    public static function encabezadosDisponibles(int $proyectoId): array
    {
        return array_values(array_filter(Estampa::deProyecto($proyectoId), fn($e) => ($e->config()['modo'] ?? '') === 'lienzo'));
    }
}
