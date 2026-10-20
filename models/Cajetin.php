<?php

namespace Model;

/** Cajetín (título del dibujo) reutilizable de un proyecto: filas «Etiqueta | valor» con variables {proyecto}, {titulo}, {fecha}, {autor}, {lamina}, {total}, {escala} */
class Cajetin extends ActiveRecord
{
    protected static $tabla = 'cajetines';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'orientacion', 'ancho', 'filas_json'];

    public $id, $proyecto_id, $nombre, $orientacion, $ancho, $filas_json;
    public $creado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 100);
        $this->orientacion = ($args['orientacion'] ?? '') === 'banda' ? 'banda' : 'vertical';
        $this->ancho = max(15, min(100, (int)($args['ancho'] ?? 28)));
        $this->filas_json = $args['filas_json'] ?? '[]';
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre del cajetín es obligatorio';
        if (!$this->filas()) self::$alertas['error'][] = 'Escribe al menos una fila (Etiqueta | valor)';
        return self::$alertas;
    }

    /** [[etiqueta, valor], ...] */
    public function filas(): array
    {
        $d = json_decode((string)$this->filas_json, true);
        return is_array($d) ? array_values(array_filter(array_map(fn($f) => is_array($f) ? [mb_substr((string)($f[0] ?? ''), 0, 60), mb_substr((string)($f[1] ?? ''), 0, 120)] : null, $d))) : [];
    }

    /** Texto del formulario (una fila por línea, «Etiqueta | valor») → JSON de filas (máx. 20) */
    public static function filasDeTexto(string $texto): string
    {
        $filas = [];
        foreach (preg_split('/\R/', $texto) as $linea) {
            if (trim($linea) === '') continue;
            [$e, $v] = array_pad(explode('|', $linea, 2), 2, '');
            $filas[] = [trim($e), trim($v)];
            if (count($filas) >= 20) break;
        }
        return json_encode($filas, JSON_UNESCAPED_UNICODE);
    }

    public function textoFilas(): string
    {
        return implode("\n", array_map(fn($f) => $f[0] . ' | ' . $f[1], $this->filas()));
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM cajetines WHERE proyecto_id = $proyectoId ORDER BY nombre, id");
    }

    public function aArray(): array
    {
        return ['id' => (int)$this->id, 'nombre' => $this->nombre, 'orientacion' => $this->orientacion, 'ancho' => (int)$this->ancho, 'filas' => $this->filas()];
    }

    /** Cajetín incluido por defecto + los del proyecto, para los editores */
    public static function paraEditor(int $proyectoId): array
    {
        $out = [['id' => 0, 'nombre' => 'Estándar', 'orientacion' => 'vertical', 'ancho' => 26, 'filas' => [
            ['Proyecto', '{proyecto}'], ['Título', '{titulo}'], ['Fecha', '{fecha}'], ['Elaboró', '{autor}'], ['Revisó', ''], ['Lámina', '{lamina} de {total}'],
        ]]];
        try {
            foreach (self::deProyecto($proyectoId) as $c) $out[] = $c->aArray();
        } catch (\Throwable $e) {   // migración pendiente
        }
        // Las variables nuevas ({codigo}, {avance_real}…) se resuelven aquí; las de siempre las llena cada editor según su lámina
        try {
            $ctx = new \Classes\EstampaContexto(\Model\Proyecto::find($proyectoId));
            foreach ($out as &$c) {
                $c['filas'] = array_map(fn($f) => [\Classes\Estampas::texto((string)$f[0], $ctx, true), \Classes\Estampas::texto((string)$f[1], $ctx, true)], $c['filas']);
            }
            unset($c);
        } catch (\Throwable $e) {
        }
        return $out;
    }
}
