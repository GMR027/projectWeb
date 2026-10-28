<?php

namespace Model;

use Classes\Imagenes;

/** Símbolo de la biblioteca de un proyecto (imagen reutilizable en el vPlan y en el Editor) */
class Simbolo extends ActiveRecord
{
    protected static $tabla = 'simbolos';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'categoria', 'archivo'];

    public $id, $proyecto_id, $nombre, $categoria, $archivo;
    public $creado;

    public const CARPETA = 'simbolos';
    public const LADO = 640;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 100);
        $this->categoria = mb_substr(trim((string)($args['categoria'] ?? '')), 0, 60);
        $this->archivo = $args['archivo'] ?? '';
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre del símbolo es obligatorio';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM simbolos WHERE proyecto_id = $proyectoId ORDER BY categoria, nombre, id");
    }

    /** Guarda un archivo de imagen (raster reducido o SVG limpio) en la carpeta de símbolos; devuelve el nombre o null si no es válido */
    public static function guardarArchivo(string $ruta): ?string
    {
        $crudo = (string)@file_get_contents($ruta, false, null, 0, 1024 * 1024 + 1);
        if (preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg\b/is', $crudo)) {
            $limpio = \Classes\SvgSeguro::limpiar($crudo);
            if (!$limpio) return null;
            $dir = __DIR__ . '/../public/uploads/' . self::CARPETA;
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $archivo = bin2hex(random_bytes(16)) . '.svg';
            return file_put_contents($dir . '/' . $archivo, $limpio) !== false ? $archivo : null;
        }
        return Imagenes::guardarEn(self::CARPETA, $ruta, self::LADO, 90);
    }

    public function url(): string
    {
        return '/uploads/' . self::CARPETA . '/' . $this->archivo;
    }

    public function eliminarArchivo(): void
    {
        Imagenes::eliminarEn(self::CARPETA, $this->archivo);
    }

    public function bytes(): int
    {
        $r = __DIR__ . '/../public/uploads/' . self::CARPETA . '/' . basename((string)$this->archivo);
        return is_file($r) ? (int)filesize($r) : 0;
    }

    /** Símbolos de obra incluidos con la aplicación (SVG en public/vendor/simbolos): [['nombre', 'cat', 'src'], ...] */
    public static function predeterminados(): array
    {
        $dir = __DIR__ . '/../public/vendor/simbolos';
        $nombres = [
            'camara' => ['Cámara CCTV', 'Seguridad'], 'camara-domo' => ['Cámara domo', 'Seguridad'], 'detector-humo' => ['Detector de humo', 'Seguridad'], 'extintor' => ['Extintor', 'Seguridad'],
            'salida-emergencia' => ['Salida de emergencia', 'Seguridad'], 'alarma' => ['Estación manual de alarma', 'Seguridad'],
            'switch' => ['Switch de red', 'Redes'], 'rack' => ['Rack', 'Redes'], 'access-point' => ['Access point', 'Redes'], 'nodo-datos' => ['Nodo de datos', 'Redes'], 'servidor' => ['Servidor', 'Redes'],
            'tablero' => ['Tablero eléctrico', 'Eléctrico'], 'luminaria' => ['Luminaria', 'Eléctrico'], 'contacto' => ['Contacto', 'Eléctrico'], 'apagador' => ['Apagador', 'Eléctrico'], 'transformador' => ['Transformador', 'Eléctrico'],
            'grua' => ['Grúa', 'Obra'], 'casco' => ['Casco / EPP', 'Obra'], 'valla' => ['Valla / acceso', 'Obra'],
        ];
        $out = [];
        foreach ($nombres as $archivo => [$nombre, $cat]) {
            if (is_file("$dir/$archivo.svg")) $out[] = ['nombre' => $nombre, 'cat' => $cat, 'src' => "/vendor/simbolos/$archivo.svg"];
        }
        return $out;
    }

    /** Para el editor: predeterminados + los del proyecto */
    public static function paraEditor(int $proyectoId): array
    {
        $out = self::predeterminados();
        try {
            foreach (self::deProyecto($proyectoId) as $s) $out[] = ['nombre' => $s->nombre, 'cat' => $s->categoria !== '' ? $s->categoria : 'Del proyecto', 'src' => $s->url(), 'ref' => 's:' . (int)$s->id, 'v' => basename((string)$s->archivo)];
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /**
     * Cuántas láminas usan cada símbolo (id => nº). Se cuenta en PHP sobre el JSON de las marcas
     * (vPlan y Editor): un símbolo insertado queda como imagen con su `src`, así que sirve aunque
     * la marca no lleve la referencia `s:<id>`. Solo lectura, para la tarjeta de la biblioteca.
     */
    public static function usos(int $proyectoId): array
    {
        $out = [];
        try {
            $simbolos = self::deProyecto($proyectoId);
            if (!$simbolos) return $out;
            $sql = 'SELECT marcas_json FROM vplan_laminas WHERE proyecto_id = ' . (int)$proyectoId . ' AND marcas_json IS NOT NULL'
                . ' UNION ALL SELECT l.marcas_json FROM editor_laminas l JOIN editor_graficos g ON g.id = l.grafico_id WHERE g.proyecto_id = ' . (int)$proyectoId . ' AND l.marcas_json IS NOT NULL';
            $r = self::$db->query($sql);
            $blobs = [];
            while ($r && ($fila = $r->fetch_row())) $blobs[] = (string)$fila[0];
            foreach ($simbolos as $s) {
                $url = $s->url();
                if ($url === '/uploads/' . self::CARPETA . '/') continue;
                $n = 0;
                foreach ($blobs as $b) if (strpos($b, '"src":"' . $url . '"') !== false) $n++;
                if ($n) $out[(int)$s->id] = $n;
            }
        } catch (\Throwable $e) {   // migraciones pendientes
        }
        return $out;
    }
}
