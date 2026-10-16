<?php

namespace Model;

use Classes\Imagenes;

/** Una lámina de un gráfico del Editor (como una diapositiva): sus marcas (JSON) y la imagen compuesta */
class GraficoLamina extends ActiveRecord
{
    protected static $tabla = 'editor_laminas';
    protected static $columnasDB = ['id', 'grafico_id', 'orden', 'nombre', 'marcas_json', 'marcas_png'];

    public $id, $grafico_id, $orden, $nombre, $marcas_json, $marcas_png;
    public $creado, $actualizado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->grafico_id = $args['grafico_id'] ?? null;
        $this->orden = (int)($args['orden'] ?? 1);
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 60);
        $this->marcas_json = $args['marcas_json'] ?? null;
        $this->marcas_png = $args['marcas_png'] ?? null;
    }

    public static function de(int $graficoId): array
    {
        return self::consultarSQL("SELECT * FROM editor_laminas WHERE grafico_id = $graficoId ORDER BY orden, id");
    }

    /** Imágenes insertadas por las otras láminas del mismo gráfico (no se pueden borrar mientras alguna las use) */
    public function imagenesDeHermanas(): array
    {
        $out = [];
        foreach (self::de((int)$this->grafico_id) as $l) {
            if ((int)$l->id !== (int)$this->id) $out = array_merge($out, Grafico::imagenesDe($l->marcas_json));
        }
        return $out;
    }

    /** Borra la imagen guardada y las imágenes insertadas que no use otra lámina */
    public function eliminarArchivos(): void
    {
        Imagenes::eliminarVplan($this->marcas_png);
        foreach (array_diff(Grafico::imagenesDe($this->marcas_json), $this->imagenesDeHermanas()) as $img) Imagenes::eliminarVplan($img);
    }

    public function bytes(): int
    {
        $t = 0;
        foreach (array_merge($this->marcas_png ? [$this->marcas_png] : [], Grafico::imagenesDe($this->marcas_json)) as $f) {
            $t += (int)@filesize(Imagenes::CARPETA_VPLAN . '/' . basename($f));
        }
        return $t;
    }

    /** Copia los archivos con nombres nuevos para que la copia sea independiente: ['json' => ..., 'png' => ...] */
    public function copiarArchivos(): array
    {
        $dir = Imagenes::CARPETA_VPLAN;
        $copiar = function (string $nombre) use ($dir): ?string {
            $origen = $dir . '/' . basename($nombre);
            if (!is_file($origen)) return null;
            $nuevo = md5(uniqid('', true)) . '.' . pathinfo($nombre, PATHINFO_EXTENSION);
            return copy($origen, $dir . '/' . $nuevo) ? $nuevo : null;
        };
        $json = $this->marcas_json;
        foreach (Grafico::imagenesDe($json) as $img) {
            if ($nuevo = $copiar($img)) $json = str_replace('/uploads/vplan/' . $img, '/uploads/vplan/' . $nuevo, (string)$json);
        }
        return ['json' => $json, 'png' => $this->marcas_png ? $copiar($this->marcas_png) : null];
    }

    /** Nombre mostrado: el propio o «Lámina N» */
    public function titulo(int $posicion): string
    {
        return $this->nombre !== '' ? $this->nombre : 'Lámina ' . $posicion;
    }
}
