<?php

namespace Model;

class Archivo extends ActiveRecord
{
    protected static $tabla = 'archivos';
    protected static $columnasDB = ['id', 'proyecto_id', 'categoria_id', 'fecha', 'nombre', 'numero_revision', 'archivo_original', 'archivo_guardado', 'tamano'];

    /** Extensiones permitidas (se rechaza todo lo que pueda ejecutarse en el servidor o el navegador) */
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'rtf', 'txt', 'csv', 'xls', 'xlsx', 'xlsm', 'ppt', 'pptx', 'xml',
        'odt', 'ods', 'odp', 'odg', 'otg', 'ott', 'ots', 'otp', 'odf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'svg',
        'dwg', 'dxf', 'zip', 'rar', '7z', 'mpp', 'pod'];
    public const MAX_BYTES = 100 * 1024 * 1024;

    public $id;
    public $proyecto_id;
    public $categoria_id;
    public $fecha;
    public $nombre;
    public $numero_revision;
    public $archivo_original;
    public $archivo_guardado;
    public $tamano;
    // Unión (solo lectura)
    public $categoria_nombre;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->categoria_id = $args['categoria_id'] ?? null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->nombre = $args['nombre'] ?? '';
        $this->numero_revision = $args['numero_revision'] ?? '';
        $this->archivo_original = $args['archivo_original'] ?? '';
        $this->archivo_guardado = $args['archivo_guardado'] ?? '';
        $this->tamano = $args['tamano'] ?? 0;
    }

    public function validar(): array
    {
        self::$alertas = [];
        $f = \DateTime::createFromFormat('Y-m-d', (string)$this->fecha);
        if (!$f || $f->format('Y-m-d') !== $this->fecha) self::$alertas['error'][] = 'La fecha no es válida';
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre del archivo es obligatorio';
        if (mb_strlen($this->nombre) > 200) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 200)';
        if (mb_strlen($this->numero_revision) > 30) self::$alertas['error'][] = 'El número de revisión es demasiado largo (máx. 30)';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId, ?int $categoriaId = null): array
    {
        $w = $categoriaId ? " AND a.categoria_id = $categoriaId" : '';
        return self::consultarSQL("SELECT a.*, c.nombre AS categoria_nombre FROM archivos a LEFT JOIN archivo_categorias c ON c.id = a.categoria_id
            WHERE a.proyecto_id = $proyectoId$w ORDER BY a.fecha DESC, a.id DESC");
    }

    public static function carpeta(int $proyectoId): string
    {
        return __DIR__ . '/../storage/archivos/' . $proyectoId;
    }

    public function ruta(): ?string
    {
        $r = self::carpeta((int)$this->proyecto_id) . '/' . basename((string)$this->archivo_guardado);
        return is_file($r) ? $r : null;
    }

    public function extension(): string
    {
        return strtolower(pathinfo((string)$this->archivo_original, PATHINFO_EXTENSION));
    }

    public function tamanoLegible(): string
    {
        $b = (int)$this->tamano;
        return $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
    }
}
