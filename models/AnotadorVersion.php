<?php

namespace Model;

/** Versión anterior de un documento del Anotador (se guarda al volver a guardar, máx. 20) */
class AnotadorVersion extends ActiveRecord
{
    protected static $tabla = 'anotador_versiones';
    protected static $columnasDB = ['id', 'proyecto_id', 'documento_id', 'nombre', 'config_json', 'anotaciones_json', 'miniatura', 'creado_por'];

    public $id, $proyecto_id, $documento_id, $nombre, $config_json, $anotaciones_json, $miniatura, $creado_por;
    public $creado, $formas, $bytes;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->documento_id = $args['documento_id'] ?? null;
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 150);
        $this->config_json = $args['config_json'] ?? '{}';
        $this->anotaciones_json = $args['anotaciones_json'] ?? '{"shapes":[]}';
        $this->miniatura = $args['miniatura'] ?? null;
        $this->creado_por = $args['creado_por'] ?? '';
        $this->creado = $args['creado'] ?? null;
    }

    public static function deDocumento(int $documentoId): array
    {
        $id = (int)$documentoId;
        return self::consultarSQL("SELECT id, documento_id, nombre, creado_por, creado,
            CHAR_LENGTH(anotaciones_json) AS bytes,
            JSON_LENGTH(JSON_EXTRACT(anotaciones_json, '$.shapes')) AS formas
            FROM anotador_versiones WHERE documento_id = $id ORDER BY id DESC LIMIT 20");
    }
}
