<?php

namespace Model;

/** Receta del Ensamblador de documentos: lista ordenada de fuentes (documentos del Redactor y PDF del Archivo) que se unen en un PDF */
class Ensamble extends ActiveRecord
{
    protected static $tabla = 'ensambles';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'fuentes', 'archivo_id', 'firma', 'creado_por'];

    public const MAX_FUENTES = 40;

    public $id, $proyecto_id, $nombre, $fuentes, $archivo_id, $firma, $creado_por;
    public $creado, $actualizado;   // los pone la BD

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = trim((string)($args['nombre'] ?? ''));
        $this->fuentes = $args['fuentes'] ?? '[]';
        $this->archivo_id = $args['archivo_id'] ?? null;
        $this->firma = $args['firma'] ?? '';
        $this->creado_por = $args['creado_por'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre es obligatorio';
        if (mb_strlen($this->nombre) > 150) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 150)';
        $n = count($this->listaFuentes());
        if ($n < 1) self::$alertas['error'][] = 'Agrega al menos un documento';
        if ($n > self::MAX_FUENTES) self::$alertas['error'][] = 'Máximo ' . self::MAX_FUENTES . ' documentos por ensamble';
        return self::$alertas;
    }

    /** Fuentes como [['t' => 'redactor'|'archivo', 'id' => int], ...] (descarta lo mal formado) */
    public function listaFuentes(): array
    {
        $d = json_decode((string)$this->fuentes, true);
        $out = [];
        foreach (is_array($d) ? $d : [] as $f) {
            $t = $f['t'] ?? '';
            $id = (int)($f['id'] ?? 0);
            if (in_array($t, ['redactor', 'archivo'], true) && $id > 0) $out[] = ['t' => $t, 'id' => $id];
        }
        return $out;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL('SELECT * FROM ensambles WHERE proyecto_id = ' . (int)$proyectoId . ' ORDER BY actualizado DESC, id DESC');
    }

    public static function delArchivo(int $archivoId): ?self
    {
        $r = self::consultarSQL('SELECT * FROM ensambles WHERE archivo_id = ' . (int)$archivoId . ' LIMIT 1');
        return $r[0] ?? null;
    }

    /** ids de Archivo del proyecto que son un ensamble vivo (para marcarlos en el listado) */
    public static function archivosVivos(int $proyectoId): array
    {
        $ids = [];
        foreach (self::deProyecto($proyectoId) as $e) if ($e->archivo_id) $ids[(int)$e->archivo_id] = (int)$e->id;
        return $ids;
    }
}
