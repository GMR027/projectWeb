<?php

namespace Model;

/**
 * Versión anterior de una comparación guardada. Se crea automáticamente al
 * actualizar (no al crear) para poder consultar o restaurar cómo estaba antes.
 */
class ComparadorVersion extends ActiveRecord
{
    protected static $tabla = 'comparador_versiones';
    protected static $columnasDB = ['id', 'proyecto_id', 'guardado_id', 'nombre', 'descripcion', 'vista_json', 'ajuste_json', 'modo_json', 'anotaciones_json', 'creado_por', 'usuario_id'];

    public $id, $proyecto_id, $guardado_id, $nombre, $descripcion, $vista_json, $ajuste_json, $modo_json, $anotaciones_json, $creado_por, $usuario_id;
    public $creado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->guardado_id = $args['guardado_id'] ?? null;
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 150);
        $this->descripcion = mb_substr(trim((string)($args['descripcion'] ?? '')), 0, 500);
        $this->vista_json = $args['vista_json'] ?? '{}';
        $this->ajuste_json = $args['ajuste_json'] ?? '{}';
        $this->modo_json = $args['modo_json'] ?? '{}';
        $this->anotaciones_json = $args['anotaciones_json'] ?? '{"shapes":[]}';
        $this->creado_por = $args['creado_por'] ?? '';
        $this->usuario_id = $args['usuario_id'] ?? null;
        $this->creado = $args['creado'] ?? null;
    }

    /** Versiones de una comparación, de la más reciente a la más antigua */
    public static function deGuardado(int $pid, int $gid): array
    {
        return self::consultarSQL("SELECT * FROM comparador_versiones WHERE proyecto_id = $pid AND guardado_id = $gid ORDER BY creado DESC, id DESC");
    }

    public static function deGuardadoPorId(int $pid, int $gid, int $id): ?self
    {
        return self::consultarSQL("SELECT * FROM comparador_versiones WHERE id = $id AND proyecto_id = $pid AND guardado_id = $gid LIMIT 1")[0] ?? null;
    }

    /** Resumen para el historial (sin el JSON completo) */
    public function tarjeta(): array
    {
        $an = json_decode((string)$this->anotaciones_json, true);
        return [
            'id' => (int)$this->id, 'nombre' => (string)$this->nombre, 'creado' => (string)$this->creado,
            'autor' => (string)$this->creado_por, 'anotaciones' => is_array($an) && is_array($an['shapes'] ?? null) ? count($an['shapes']) : 0,
        ];
    }

    /** Anotaciones, vista, alineación y modo de la versión */
    public function datos(): array
    {
        return [
            'vista' => json_decode((string)$this->vista_json, true) ?: null,
            'ajuste' => json_decode((string)$this->ajuste_json, true) ?: null,
            'modo' => json_decode((string)$this->modo_json, true) ?: null,
            'anotaciones' => json_decode((string)$this->anotaciones_json, true) ?: null,
        ];
    }
}
