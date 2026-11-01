<?php

namespace Model;

/** Comparación guardada del Comparador de documentos: documentos, vista, alineación y anotaciones */
class ComparadorGuardado extends ActiveRecord
{
    protected static $tabla = 'comparador_guardados';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'descripcion', 'a_json', 'b_json', 'vista_json', 'ajuste_json', 'modo_json', 'anotaciones_json', 'miniatura', 'creado_por', 'usuario_id', 'token'];

    public $id, $proyecto_id, $nombre, $descripcion, $a_json, $b_json, $vista_json, $ajuste_json, $modo_json, $anotaciones_json, $miniatura, $creado_por, $usuario_id, $token;
    public $creado, $actualizado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 150);
        $this->descripcion = mb_substr(trim((string)($args['descripcion'] ?? '')), 0, 500);
        $this->a_json = $args['a_json'] ?? '{}';
        $this->b_json = $args['b_json'] ?? '{}';
        $this->vista_json = $args['vista_json'] ?? '{}';
        $this->ajuste_json = $args['ajuste_json'] ?? '{}';
        $this->modo_json = $args['modo_json'] ?? '{}';
        $this->anotaciones_json = $args['anotaciones_json'] ?? '{"shapes":[]}';
        $this->miniatura = $args['miniatura'] ?? null;
        $this->creado_por = $args['creado_por'] ?? '';
        $this->usuario_id = $args['usuario_id'] ?? null;
        $this->token = $args['token'] ?? null;
        $this->creado = $args['creado'] ?? null;
        $this->actualizado = $args['actualizado'] ?? null;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM comparador_guardados WHERE proyecto_id = $proyectoId ORDER BY actualizado DESC, id DESC");
    }

    /** Comparación del proyecto (null si no existe o es de otro proyecto) */
    public static function deProyectoPorId(int $proyectoId, int $id): ?self
    {
        return self::consultarSQL("SELECT * FROM comparador_guardados WHERE id = $id AND proyecto_id = $proyectoId LIMIT 1")[0] ?? null;
    }

    /** Archivos subidos que usa la comparación */
    public function archivos(): array
    {
        $out = [];
        foreach (['a_json', 'b_json'] as $c) {
            $d = json_decode((string)$this->$c, true);
            if (is_array($d) && ($d['tipo'] ?? '') === 'subido' && ($d['archivo'] ?? '') !== '') $out[] = basename((string)$d['archivo']);
        }
        return array_values(array_unique($out));
    }

    public function bytes(): int
    {
        $t = 0;
        foreach ($this->archivos() as $f) $t += (int)@filesize(\Classes\Comparador::CARPETA . '/' . $f);
        if ($this->miniatura) $t += (int)@filesize(\Classes\Comparador::MINIATURAS . '/' . basename($this->miniatura));
        return $t;
    }

    /** Para las tarjetas de la lista (sin los JSON completos) */
    public function tarjeta(): array
    {
        $an = json_decode((string)$this->anotaciones_json, true);
        $a = json_decode((string)$this->a_json, true);
        $b = json_decode((string)$this->b_json, true);
        return [
            'id' => (int)$this->id, 'nombre' => (string)$this->nombre, 'descripcion' => (string)$this->descripcion,
            'autor' => (string)$this->creado_por, 'creado' => (string)$this->creado, 'actualizado' => (string)$this->actualizado,
            'anotaciones' => is_array($an) && is_array($an['shapes'] ?? null) ? count($an['shapes']) : 0,
            'abiertas' => is_array($an) && is_array($an['shapes'] ?? null) ? count(array_filter($an['shapes'], fn($s) => ($s['tipo'] ?? '') === 'globo' && ($s['estado'] ?? 'abierta') !== 'resuelta')) : 0,
            'token' => (string)$this->token,
            'miniatura' => $this->miniatura && is_file(\Classes\Comparador::MINIATURAS . '/' . basename((string)$this->miniatura)) ? '/comparador/miniatura?proyecto=' . (int)$this->proyecto_id . '&id=' . (int)$this->id : '',
            'a' => is_array($a) ? (string)($a['nombre'] ?? '') : '', 'b' => is_array($b) ? (string)($b['nombre'] ?? '') : '',
        ];
    }

    /** Configuración para abrir la comparación en el navegador */
    public function paraJs(): array
    {
        return [
            'id' => (int)$this->id, 'nombre' => (string)$this->nombre, 'descripcion' => (string)$this->descripcion,
            'token' => (string)$this->token,
            'a' => json_decode((string)$this->a_json, true) ?: null,
            'b' => json_decode((string)$this->b_json, true) ?: null,
            'vista' => json_decode((string)$this->vista_json, true) ?: null,
            'ajuste' => json_decode((string)$this->ajuste_json, true) ?: null,
            'modo' => json_decode((string)$this->modo_json, true) ?: null,
            'anotaciones' => json_decode((string)$this->anotaciones_json, true) ?: null,
        ];
    }
}
