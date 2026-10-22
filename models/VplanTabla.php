<?php

namespace Model;

class VplanTabla extends ActiveRecord
{
    protected static $tabla = 'vplan_tablas';
    protected static $columnasDB = ['id', 'proyecto_id', 'titulo', 'nombre_interno', 'tipo', 'columnas_json', 'sheetflow_id', 'punch_config'];

    public $id, $proyecto_id, $titulo, $nombre_interno, $tipo, $columnas_json, $sheetflow_id, $punch_config;

    public const TIPOS = [
        'punchlist' => 'Punch list (conectado al módulo Punch)',
        'punch' => 'Punch list manual (antiguo)',
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
        $this->tipo = $args['tipo'] ?? 'punchlist';
        $this->columnas_json = $args['columnas_json'] ?? null;
        $this->sheetflow_id = $args['sheetflow_id'] ?? null;
        $this->punch_config = $args['punch_config'] ?? null;
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

    private $hojaCache = false;

    /** Hoja de SheetFlow a la que está vinculada (null si no lo está o ya no existe: entonces valen los datos propios) */
    public function hoja(): ?SheetflowHoja
    {
        if ($this->hojaCache === false) {
            $h = $this->sheetflow_id ? SheetflowHoja::find((int)$this->sheetflow_id) : null;
            $this->hojaCache = $h && (int)$h->proyecto_id === (int)$this->proyecto_id ? $h : null;
        }
        return $this->hojaCache;
    }

    public function esPunchVista(): bool
    {
        return $this->tipo === 'punchlist';
    }

    /** Configuración de la vista del Punch list (columnas visibles, filtros) */
    public function configPunch(): array
    {
        return \Classes\PunchVista::normalizar($this->punch_config);
    }

    /** Columnas de la tabla "otro" (nombres); si está vinculada, las de SheetFlow en este momento */
    public function columnasOtro(): array
    {
        if ($this->esPunchVista()) return \Classes\PunchVista::etiquetas($this->configPunch());
        if ($h = $this->hoja()) {
            $n = array_slice(\Classes\Sheetflow::matriz($h)['columnas'], 0, 8);
            return array_map(fn($x, $i) => mb_substr($x !== '' ? $x : 'Col. ' . ($i + 1), 0, 40), $n, array_keys($n));
        }
        $c = json_decode((string)$this->columnas_json, true);
        return is_array($c) ? array_values(array_filter(array_map('strval', $c), fn($x) => $x !== '')) : [];
    }

    /** Tipo y opciones de cada columna de una tabla vinculada a SheetFlow: ['c0' => ['t' => 'fecha', 'op' => []], …] (vacío si no lo está) */
    public function metaColumnas(): array
    {
        if ($this->esPunchVista()) return \Classes\PunchVista::meta($this->configPunch());
        $h = $this->hoja();
        if (!$h) return [];
        $m = \Classes\Sheetflow::matriz($h);
        $out = [];
        foreach (array_slice($m['tipos'], 0, 8) as $i => $t) $out['c' . $i] = ['t' => $t, 'op' => $m['opciones'][$i] ?? []];
        return $out;
    }

    /** Estilo (CSS) de los encabezados cuando la tabla está vinculada a SheetFlow: ['c0' => css, …] (vacío si no lo está) */
    public function estiloEncabezados(): array
    {
        $h = $this->hoja();
        return $h ? \Classes\Sheetflow::estiloEncabezados($h, count($this->columnasOtro())) : [];
    }

    /** Claves de campo de cada fila según el tipo */
    public function campos(): array
    {
        if ($this->tipo === 'otro' || $this->esPunchVista()) {
            $out = [];
            foreach ($this->columnasOtro() as $i => $n) $out['c' . $i] = [$n, 'text'];
            return $out;
        }
        return self::CAMPOS[$this->tipo] ?? [];
    }

    /** Filas (arrays de datos) en orden */
    public function filas(): array
    {
        if ($this->esPunchVista()) return \Classes\PunchVista::filas((int)$this->proyecto_id, $this->configPunch());
        if ($h = $this->hoja()) return \Classes\Sheetflow::filasVplan($h, count($this->columnasOtro()));
        $res = self::$db->query("SELECT datos FROM vplan_tabla_filas WHERE tabla_id = " . (int)$this->id . " ORDER BY orden, id");
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $d = json_decode((string)$r['datos'], true);
            $out[] = is_array($d) ? $d : [];
        }
        return $out;
    }

    /** Copia independiente (mismas columnas y renglones) para usarla en otra lámina sin compartir datos */
    public function duplicar(): self
    {
        $copia = new self([
            'proyecto_id' => $this->proyecto_id, 'titulo' => $this->titulo, 'tipo' => $this->tipo,
            'nombre_interno' => mb_substr($this->etiqueta(), 0, 140) . ' (copia)', 'punch_config' => $this->punch_config,
            'columnas_json' => $this->hoja() ? json_encode($this->columnasOtro(), JSON_UNESCAPED_UNICODE) : $this->columnas_json,
        ]);
        $r = $copia->guardar();
        $copia->id = $r['id'];
        if (!$this->esPunchVista()) $copia->guardarFilas($this->filas());
        return $copia;
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
