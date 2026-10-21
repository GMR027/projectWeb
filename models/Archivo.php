<?php

namespace Model;

class Archivo extends ActiveRecord
{
    protected static $tabla = 'archivos';
    protected static $columnasDB = ['id', 'proyecto_id', 'categoria_id', 'carpeta_id', 'fecha', 'nombre', 'numero_revision', 'archivo_original', 'archivo_guardado', 'tamano'];

    /** Extensiones permitidas (se rechaza todo lo que pueda ejecutarse en el servidor o el navegador; además cada archivo pasa por Classes\EscanerArchivos) */
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'rtf', 'txt', 'csv', 'xls', 'xlsx', 'ppt', 'pptx', 'xml',
        'odt', 'ods', 'odp', 'odg', 'otg', 'ott', 'ots', 'otp', 'odf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'svg',
        'dwg', 'dxf', 'dwt', 'dws', 'dwf', 'dgn', 'rvt', 'rfa', 'rte', 'rft', 'ifc', 'ifczip', 'skp', 'nwd', 'nwc', '3dm', 'stp', 'step', 'obj', 'kml',
        'zip', 'rar', '7z', 'mpp', 'pod', 'sheetflow'];
    public const MAX_BYTES = 100 * 1024 * 1024;

    public $id;
    public $proyecto_id;
    public $categoria_id;
    public $carpeta_id;
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
        $this->carpeta_id = $args['carpeta_id'] ?? null;
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

    /** $carpeta: null = todas, 0 = sin carpeta, >0 = esa carpeta */
    public static function deProyecto(int $proyectoId, ?int $categoriaId = null, ?int $carpeta = null): array
    {
        $w = $categoriaId ? " AND a.categoria_id = $categoriaId" : '';
        if ($carpeta === 0) $w .= ' AND a.carpeta_id IS NULL';
        elseif ($carpeta) $w .= " AND a.carpeta_id = $carpeta";
        return self::consultarSQL("SELECT a.*, c.nombre AS categoria_nombre FROM archivos a LEFT JOIN archivo_categorias c ON c.id = a.categoria_id
            WHERE a.proyecto_id = $proyectoId$w ORDER BY a.fecha DESC, a.id DESC");
    }

    public static function carpeta(int $proyectoId): string
    {
        return __DIR__ . '/../storage/archivos/' . $proyectoId;
    }

    /** Carpeta del proyecto, creada si no existe */
    public static function carpetaCreada(int $proyectoId): string
    {
        $dir = self::carpeta($proyectoId);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        return $dir;
    }

    public static function nombreGuardado(string $ext): string
    {
        return bin2hex(random_bytes(12)) . '.' . $ext;
    }

    /** Mueve un archivo subido (o uno ya en el servidor con $subido = false) a la carpeta del proyecto. Devuelve el nombre guardado o null */
    public static function guardarArchivoSubido(int $proyectoId, string $tmp, string $ext, bool $subido = true): ?string
    {
        $dir = self::carpetaCreada($proyectoId);
        $guardado = self::nombreGuardado($ext);
        $ok = $subido ? move_uploaded_file($tmp, "$dir/$guardado") : (@rename($tmp, "$dir/$guardado") || (@copy($tmp, "$dir/$guardado") && @unlink($tmp)));
        return $ok ? $guardado : null;
    }

    /** Pone $origen en la ruta de un archivo ya registrado (rename, o copia y borra si están en otro disco) */
    public static function reemplazarContenido(string $origen, string $destino): bool
    {
        return @rename($origen, $destino) || (@copy($origen, $destino) && @unlink($origen));
    }

    public function ruta(): ?string
    {
        if ($this->id && $this->extension() === 'pdf') \Classes\EnsamblePdf::refrescar($this);   // ensamble vivo: se regenera si cambió una fuente
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
