<?php

namespace Model;

use Classes\Imagenes;

/** Gráfico del Editor: lienzo libre (tamaño y fondo de plantilla) con las mismas marcas del editor del vPlan */
class Grafico extends ActiveRecord
{
    protected static $tabla = 'editor_graficos';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'tipo', 'categoria_id', 'ancho', 'alto', 'fondo', 'fondo_color', 'fondo_imagen', 'fondo_config'];

    public $id, $proyecto_id, $nombre, $tipo, $categoria_id, $ancho, $alto, $fondo, $fondo_color, $fondo_imagen, $fondo_config;
    public $creado, $actualizado;   // los pone la BD
    public $laminasCache = null;
    public $portada, $num_laminas, $categoria_nombre;  // solo en la lista: imagen de la lámina 1 y cuántas láminas hay

    public const TIPOS = ['grafico', 'simbologia'];
    public const LADO_MIN = 100;
    public const LADO_MAX = 8000;

    /** Fondos de plantilla (las mismas claves las dibuja public/vendor/editor-fondos.js) */
    public const FONDOS = [
        'liso' => 'Liso',
        'imagen' => 'Imagen propia',
        // Papel y dibujo
        'reticula' => 'Retícula',
        'milimetrado' => 'Milimetrado',
        'cuadros-azul' => 'Cuadriculado azul',
        'ingenieria' => 'Papel de ingeniería',
        'lineas' => 'Líneas (cuaderno)',
        'puntos' => 'Puntos',
        'isometrico' => 'Isométrico',
        'hexagonos' => 'Hexágonos',
        'diagonal' => 'Líneas diagonales',
        'tablero' => 'Tablero (transparencia)',
        // Texturas
        'kraft' => 'Papel kraft',
        'crema' => 'Papel crema',
        'reciclado' => 'Papel reciclado',
        'lino' => 'Lino',
        'concreto' => 'Concreto',
        'ladrillo' => 'Ladrillo',
        'madera' => 'Madera',
        // Oscuros
        'plano-azul' => 'Plano azul',
        'pizarron' => 'Pizarrón',
        'pizarra-verde' => 'Pizarra verde',
        'puntos-oscuro' => 'Puntos sobre oscuro',
    ];

    public const MODOS_IMAGEN = ['cubrir', 'contener', 'estirar', 'mosaico'];

    /** Ajustes del fondo personalizado, siempre dentro de rangos válidos */
    public static function limpiarConfigFondo($json): array
    {
        $c = is_array($json) ? $json : (json_decode((string)$json, true) ?: []);
        $n = fn($v, $min, $max, $def) => is_numeric($v) ? max($min, min($max, (float)$v)) : $def;
        return [
            'modo' => in_array($c['modo'] ?? '', self::MODOS_IMAGEN, true) ? $c['modo'] : 'cubrir',
            'escala' => round($n($c['escala'] ?? 1, 0.2, 5, 1), 2),
            'posX' => round($n($c['posX'] ?? 50, 0, 100, 50), 1),
            'posY' => round($n($c['posY'] ?? 50, 0, 100, 50), 1),
            'repX' => (int)$n($c['repX'] ?? 4, 1, 60, 4),
            'repY' => (int)$n($c['repY'] ?? 0, 0, 60, 0),   // 0 = automático (mantiene la proporción de la imagen)
            'opacidad' => (int)$n($c['opacidad'] ?? 100, 5, 100, 100),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($c['color'] ?? '')) ? $c['color'] : '#ffffff',
        ];
    }

    /** Tamaños comunes [grupo => [clave => [etiqueta, ancho, alto]]] (en píxeles; el papel a 300 dpi) */
    public const TAMANOS = [
        'Pantalla' => [
            'hd' => ['HD 1280 × 720', 1280, 720],
            'fullhd' => ['Full HD 1920 × 1080', 1920, 1080],
            '4k' => ['4K 3840 × 2160', 3840, 2160],
        ],
        'Papel (300 dpi, horizontal)' => [
            'carta' => ['Carta 11 × 8.5 in', 3300, 2550],
            'a4' => ['A4 297 × 210 mm', 3508, 2480],
            'a3' => ['A3 420 × 297 mm', 4961, 3508],
            'tabloide' => ['Tabloide 17 × 11 in (como el vPlan)', 5100, 3300],
        ],
        'Simbología (leyenda)' => [
            'simb-schedule' => ['Leyenda del schedule 1200 × 700', 1200, 700],
            'simb-cuadrada' => ['Leyenda cuadrada 800 × 800', 800, 800],
            'simb-ancha' => ['Leyenda ancha 2400 × 700', 2400, 700],
        ],
        'Redes y presentaciones' => [
            'cuadrado' => ['Cuadrado 1080 × 1080', 1080, 1080],
            'portada' => ['Portada 1200 × 630', 1200, 630],
            'historia' => ['Historia 1080 × 1920', 1080, 1920],
        ],
    ];

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = trim((string)($args['nombre'] ?? ''));
        $this->tipo = in_array($args['tipo'] ?? '', self::TIPOS, true) ? $args['tipo'] : 'grafico';
        $this->categoria_id = $args['categoria_id'] ?? null;
        $this->ancho = (int)($args['ancho'] ?? 1920);
        $this->alto = (int)($args['alto'] ?? 1080);
        $this->fondo = (string)($args['fondo'] ?? 'liso');
        $this->fondo_color = (string)($args['fondo_color'] ?? '#ffffff');
        $this->fondo_imagen = $args['fondo_imagen'] ?? null;
        $this->fondo_config = $args['fondo_config'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre es obligatorio';
        if (mb_strlen($this->nombre) > 150) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 150)';
        foreach (['ancho' => 'ancho', 'alto' => 'alto'] as $c => $etq) {
            if ($this->$c < self::LADO_MIN || $this->$c > self::LADO_MAX) self::$alertas['error'][] = "El $etq debe estar entre " . self::LADO_MIN . ' y ' . self::LADO_MAX . ' px';
        }
        if (!isset(self::FONDOS[$this->fondo])) self::$alertas['error'][] = 'Fondo no válido';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $this->fondo_color)) self::$alertas['error'][] = 'Color de fondo no válido';
        return self::$alertas;
    }

    /** Gráficos del proyecto del tipo dado ('grafico' por defecto; null = todos, p. ej. para sumar el almacenamiento) */
    public static function deProyecto(int $proyectoId, ?string $tipo = 'grafico'): array
    {
        $filtro = $tipo !== null && in_array($tipo, self::TIPOS, true) ? " AND g.tipo = '$tipo'" : '';
        $lista = self::consultarSQL(
            "SELECT g.*, (SELECT l.marcas_png FROM editor_laminas l WHERE l.grafico_id = g.id ORDER BY l.orden, l.id LIMIT 1) AS portada,
                    (SELECT COUNT(*) FROM editor_laminas l WHERE l.grafico_id = g.id) AS num_laminas,
                    (SELECT c.nombre FROM editor_categorias c WHERE c.id = g.categoria_id) AS categoria_nombre
             FROM editor_graficos g WHERE g.proyecto_id = $proyectoId" . $filtro . self::sinView360() . " ORDER BY g.creado DESC, g.id DESC"
        );
        // Todas las láminas del proyecto en una sola consulta (evita una consulta por gráfico)
        $porGrafico = [];
        foreach (GraficoLamina::consultarSQL("SELECT l.* FROM editor_laminas l JOIN editor_graficos g ON g.id = l.grafico_id WHERE g.proyecto_id = $proyectoId" . $filtro . " ORDER BY l.orden, l.id") as $l) $porGrafico[(int)$l->grafico_id][] = $l;
        foreach ($lista as $g) $g->laminasCache = $porGrafico[(int)$g->id] ?? [];
        return $lista;
    }

    /** Condición SQL que deja fuera los gráficos que pertenecen a View360 (tienen su propia sección) */
    private static function sinView360(): string
    {
        static $sql = null;
        if ($sql === null) {
            try {
                $r = $GLOBALS['db']->query("SHOW COLUMNS FROM view360_graficos LIKE 'grafico_id'");
                $sql = $r && $r->num_rows ? ' AND g.id NOT IN (SELECT grafico_id FROM view360_graficos WHERE grafico_id IS NOT NULL)' : '';
            } catch (\Throwable $e) {
                $sql = '';
            }
        }
        return $sql;
    }

    /** Archivos de imagen insertados dentro de las marcas */
    public static function imagenesDe(?string $json): array
    {
        $d = $json ? json_decode($json, true) : null;
        $out = [];
        foreach (($d['shapes'] ?? []) as $s) {
            if (($s['type'] ?? '') === 'image' && preg_match('#^/uploads/vplan/([a-f0-9]{32}\.(?:png|jpg))$#', (string)($s['src'] ?? ''), $m)) $out[] = $m[1];
            if (($s['type'] ?? '') === 'link' && ($s['kind'] ?? '') === '360' && preg_match('#^/uploads/vplan/([a-f0-9]{32}\.(?:png|jpg))$#', (string)($s['url'] ?? ''), $m)) $out[] = $m[1];
        }
        return array_values(array_unique($out));
    }

    /** Simbologías del proyecto con su imagen (primera lámina): para listarlas y para elegirlas en una lámina del vPlan */
    public static function simbologias(int $proyectoId): array
    {
        return self::deProyecto($proyectoId, 'simbologia');
    }

    /** Primera lámina guardada con imagen (la que se muestra como simbología) */
    public function imagenSimbologia(): ?string
    {
        foreach ($this->laminas() as $l) if ($l->marcas_png) return $l->marcas_png;
        return null;
    }

    /** Imágenes de gráficos del proyecto para insertarlas en otro dibujo: [['nombre', 'src'], ...] (una por lámina guardada) */
    public static function paraInsertar(int $proyectoId, ?int $excluir = null): array
    {
        $out = [];
        try {
            foreach (self::deProyecto($proyectoId, null) as $g) {
                if ($excluir !== null && (int)$g->id === $excluir) continue;
                $laminas = $g->laminas();
                foreach ($laminas as $i => $l) {
                    if ($l->marcas_png) $out[] = ['id' => (int)$g->id, 'tipo' => $g->tipo, 'nombre' => ($g->tipo === 'simbologia' ? 'Simbología · ' : '') . $g->nombre . (count($laminas) > 1 ? ' · ' . $l->titulo($i + 1) : ''), 'src' => '/uploads/vplan/' . $l->marcas_png];
                }
            }
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /** Láminas del gráfico, en orden */
    public function laminas(): array
    {
        return $this->laminasCache ?? GraficoLamina::de((int)$this->id);
    }

    /** Crea la primera lámina (en blanco) de un gráfico recién creado */
    public function crearLaminaInicial(): void
    {
        (new GraficoLamina(['grafico_id' => $this->id, 'orden' => 1]))->guardar();
    }

    /** Borra del disco todo lo del gráfico: fondo personalizado y, de cada lámina, su imagen guardada e imágenes insertadas */
    public function eliminarArchivos(): void
    {
        Imagenes::eliminarVplan($this->fondo_imagen);
        foreach ($this->laminas() as $l) $l->eliminarArchivos();
    }

    /** Peso aproximado en disco del fondo y de todas las láminas */
    public function bytes(): int
    {
        $t = $this->fondo_imagen ? (int)@filesize(Imagenes::CARPETA_VPLAN . '/' . basename($this->fondo_imagen)) : 0;
        foreach ($this->laminas() as $l) $t += $l->bytes();
        return $t;
    }
}
