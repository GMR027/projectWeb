<?php

namespace Model;

/** Categoría para clasificar (y luego filtrar) los gráficos del Editor de un proyecto */
class GraficoCategoria extends ActiveRecord
{
    protected static $tabla = 'editor_categorias';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre'];

    public $id, $proyecto_id, $nombre;
    public $creado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = trim((string)($args['nombre'] ?? ''));
    }

    public function validar(): array
    {
        self::$alertas = [];
        $n = trim($this->nombre);
        if ($n === '') self::$alertas['error'][] = 'El nombre de la categoría es obligatorio';
        if (mb_strlen($n) > 100) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 100)';
        if ($n !== '') {
            $e = self::$db->escape_string(mb_strtolower($n));
            if (self::consultarSQL("SELECT * FROM editor_categorias WHERE proyecto_id = " . (int)$this->proyecto_id . " AND LOWER(nombre) = '$e' AND id <> " . (int)$this->id . " LIMIT 1")) {
                self::$alertas['error'][] = 'Ya existe una categoría con ese nombre';
            }
        }
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        try {
            return self::consultarSQL("SELECT * FROM editor_categorias WHERE proyecto_id = $proyectoId ORDER BY nombre ASC");
        } catch (\Throwable $e) {   // migración pendiente
            return [];
        }
    }

    /** Gráficos que usan la categoría */
    public function enUso(): int
    {
        return (int)Grafico::total("categoria_id = " . (int)$this->id);
    }

    /** id de categoría si pertenece al proyecto; null si no (o vacío) */
    public static function valida(int $proyectoId, $valor): ?int
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT) ?: null;
        return $id && in_array($id, array_map(fn($c) => (int)$c->id, self::deProyecto($proyectoId)), true) ? $id : null;
    }
}
