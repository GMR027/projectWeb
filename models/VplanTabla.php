<?php

namespace Model;

class VplanTabla extends ActiveRecord
{
    protected static $tabla = 'vplan_tablas';
    protected static $columnasDB = ['id', 'proyecto_id', 'titulo', 'nombre_interno', 'tipo', 'columnas_json'];

    public $id, $proyecto_id, $titulo, $nombre_interno, $tipo, $columnas_json;

    public const TIPOS = [
        'punch' => 'Punch list',
        'restricciones' => 'Restricciones',
        'secundarias' => 'Actividades secundarias',
        'otro' => 'Otro',
    ];
    /** Campos de cada fila por tipo: clave => [etiqueta, tipo de input] */
    public const CAMPOS = [
        'punch' => ['descripcion' => ['Descripción', 'text'], 'inicio' => ['Inicio', 'date'], 'fin' => ['Fin', 'date'], 'pct' => ['%', 'number']],
        'restricciones' => ['descripcion' => ['Descripción', 'text']],
        'secundarias' => ['elemento' => ['Elemento', 'text'], 'fecha' => ['Fecha', 'text'], 'recovery' => ['Fecha recuperación', 'text'], 'estado' => ['Estado', 'estado']],
    ];
    public const ESTADOS = ['completo' => 'Completo', 'proceso' => 'En proceso', 'espera' => 'En espera'];

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->titulo = $args['titulo'] ?? '';
        $this->nombre_interno = $args['nombre_interno'] ?? null;
        $this->tipo = $args['tipo'] ?? 'punch';
        $this->columnas_json = $args['columnas_json'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->titulo)) self::$alertas['error'][] = 'El título de la tabla es obligatorio';
        if (!isset(self::TIPOS[$this->tipo])) self::$alertas['error'][] = 'Tipo de tabla no válido';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM vplan_tablas WHERE proyecto_id = $proyectoId ORDER BY COALESCE(NULLIF(nombre_interno, ''), titulo) ASC");
    }

    /** Nombre con el que se ve en el catálogo (el título es el que se imprime en la lámina) */
    public function etiqueta(): string
    {
        return trim((string)$this->nombre_interno) !== '' ? (string)$this->nombre_interno : (string)$this->titulo;
    }

    /** Columnas de la tabla "otro" (nombres) */
    public function columnasOtro(): array
    {
        $c = json_decode((string)$this->columnas_json, true);
        return is_array($c) ? array_values(array_filter(array_map('strval', $c), fn($x) => $x !== '')) : [];
    }

    /** Claves de campo de cada fila según el tipo */
    public function campos(): array
    {
        if ($this->tipo === 'otro') {
            $out = [];
            foreach ($this->columnasOtro() as $i => $n) $out['c' . $i] = [$n, 'text'];
            return $out;
        }
        return self::CAMPOS[$this->tipo] ?? [];
    }

    /** Filas (arrays de datos) en orden */
    public function filas(): array
    {
        $res = self::$db->query("SELECT datos FROM vplan_tabla_filas WHERE tabla_id = " . (int)$this->id . " ORDER BY orden, id");
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $d = json_decode((string)$r['datos'], true);
            $out[] = is_array($d) ? $d : [];
        }
        return $out;
    }

    /** Reemplaza todas las filas */
    public function guardarFilas(array $filas): void
    {
        $id = (int)$this->id;
        self::$db->query("DELETE FROM vplan_tabla_filas WHERE tabla_id = $id");
        foreach (array_values($filas) as $orden => $f) {
            $json = self::$db->escape_string(json_encode($f, JSON_UNESCAPED_UNICODE));
            self::$db->query("INSERT INTO vplan_tabla_filas (tabla_id, orden, datos) VALUES ($id, $orden, '$json')");
        }
    }
}
