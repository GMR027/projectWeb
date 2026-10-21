<?php

namespace Model;

use Classes\Sheetflow;

/** Tabla de SheetFlow: nombre, fecha y departamento más sus columnas y renglones (JSON saneado por Classes\Sheetflow::limpiar) */
class SheetflowHoja extends ActiveRecord
{
    protected static $tabla = 'sheetflow_hojas';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'fecha', 'departamento', 'descripcion', 'datos', 'creado_por'];

    public $id, $proyecto_id, $nombre, $fecha, $departamento, $descripcion, $datos, $creado_por;
    public $creado, $actualizado;   // los pone la BD

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = trim((string)($args['nombre'] ?? ''));
        $this->fecha = (string)($args['fecha'] ?? date('Y-m-d'));
        $this->departamento = trim((string)($args['departamento'] ?? ''));
        $this->descripcion = mb_substr(trim(preg_replace('/[\x00-\x1f]/u', ' ', (string)($args['descripcion'] ?? ''))), 0, 300);
        $this->datos = $args['datos'] ?? null;
        $this->creado_por = $args['creado_por'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre es obligatorio';
        if (mb_strlen($this->nombre) > 150) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 150)';
        if (mb_strlen($this->departamento) > 100) self::$alertas['error'][] = 'El departamento es demasiado largo (máx. 100)';
        $f = \DateTime::createFromFormat('Y-m-d', $this->fecha);
        if (!$f || $f->format('Y-m-d') !== $this->fecha) self::$alertas['error'][] = 'La fecha no es válida';
        return self::$alertas;
    }

    /** Contenido ya decodificado y completo (siempre con al menos una columna) */
    public function contenido(): array
    {
        return Sheetflow::limpiar(json_decode((string)$this->datos, true));
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM sheetflow_hojas WHERE proyecto_id = $proyectoId ORDER BY departamento ASC, fecha DESC, id DESC");
    }

    /** Departamentos sugeridos: los de la bitácora y los ya usados por otras tablas */
    public static function departamentos(int $proyectoId): array
    {
        return \Classes\Catalogo::departamentos($proyectoId);
    }

    /** Lista corta (id, nombre, tamaño) para ofrecerlas en vPlan, Editor y Redactor */
    public static function resumen(int $proyectoId): array
    {
        $out = [];
        try {
            foreach (self::deProyecto($proyectoId) as $h) {
                $c = $h->contenido();
                $out[] = ['id' => (int)$h->id, 'nombre' => (string)$h->nombre, 'departamento' => (string)$h->departamento, 'filas' => count($c['rows']), 'cols' => count($c['cols'])];
            }
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /** Tablas de vPlan creadas desde esta hoja: [id, titulo, laminas donde se usa] */
    public function usosVplan(): array
    {
        $out = [];
        try {
            $r = self::$db->query("SELECT t.id, t.titulo, COUNT(lt.lamina_id) AS laminas FROM vplan_tablas t LEFT JOIN vplan_lamina_tablas lt ON lt.tabla_id = t.id WHERE t.sheetflow_id = " . (int)$this->id . " GROUP BY t.id, t.titulo");
            while ($r && ($f = $r->fetch_assoc())) $out[] = $f;
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /** Documentos del Redactor que insertan esta hoja (`data-sf`): [id, nombre] */
    public function usosRedactor(): array
    {
        $out = [];
        try {
            $r = self::$db->query("SELECT id, nombre FROM redactor_documentos WHERE proyecto_id = " . (int)$this->proyecto_id . " AND contenido LIKE '%data-sf=\\\"" . (int)$this->id . "\\\"%' ORDER BY nombre");
            while ($r && ($f = $r->fetch_assoc())) $out[] = $f;
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /** Peso del contenido de todas las tablas del proyecto (cuenta en el almacenamiento) */
    public static function bytesDe(int $proyectoId): int
    {
        $r = self::$db->query("SELECT datos FROM sheetflow_hojas WHERE proyecto_id = $proyectoId");
        $t = 0;
        $vistas = [];
        while ($r && ($f = $r->fetch_assoc())) {
            $t += strlen((string)$f['datos']);
            foreach (Sheetflow::imagenesDe($f['datos']) as $n) {
                if (isset($vistas[$n])) continue;
                $vistas[$n] = true;
                $t += (int)@filesize(\Classes\Imagenes::CARPETA_VPLAN . '/' . $n);
            }
        }
        return $t;
    }

    public static function conexion()
    {
        return self::$db;
    }

    /** Imágenes de celdas que usa esta tabla */
    public function imagenes(): array
    {
        return Sheetflow::imagenesDe((string)$this->datos);
    }
}
