<?php

namespace Model;

use Classes\Anotador;

/** Documento del Anotador: fuente (PDF/imagen) + capa de anotaciones, escalas por página y estado de firma */
class AnotadorDocumento extends ActiveRecord
{
    protected static $tabla = 'anotador_documentos';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'descripcion', 'fuente_tipo', 'fuente_id', 'archivo', 'nombre_archivo', 'ext', 'paginas', 'config_json', 'anotaciones_json', 'miniatura', 'creado_por', 'usuario_id'];

    public $id, $proyecto_id, $nombre, $descripcion, $fuente_tipo, $fuente_id, $archivo, $nombre_archivo, $ext, $paginas, $config_json, $anotaciones_json, $miniatura, $creado_por, $usuario_id;
    public $creado, $actualizado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 150);
        $this->descripcion = mb_substr(trim((string)($args['descripcion'] ?? '')), 0, 500);
        $this->fuente_tipo = in_array($args['fuente_tipo'] ?? '', ['archivo', 'vplan', 'editor', 'redactor', 'subido'], true) ? $args['fuente_tipo'] : 'archivo';
        $this->fuente_id = isset($args['fuente_id']) && (int)$args['fuente_id'] > 0 ? (int)$args['fuente_id'] : null;
        $this->archivo = $args['archivo'] ?? null;
        $this->nombre_archivo = mb_substr(trim((string)($args['nombre_archivo'] ?? '')), 0, 255);
        $this->ext = mb_substr(strtolower(trim((string)($args['ext'] ?? ''))), 0, 10);
        $this->paginas = max(1, min(10000, (int)($args['paginas'] ?? 1)));
        $this->config_json = $args['config_json'] ?? '{}';
        $this->anotaciones_json = $args['anotaciones_json'] ?? '{"shapes":[]}';
        $this->miniatura = $args['miniatura'] ?? null;
        $this->creado_por = $args['creado_por'] ?? '';
        $this->usuario_id = $args['usuario_id'] ?? null;
        $this->creado = $args['creado'] ?? null;
        $this->actualizado = $args['actualizado'] ?? null;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM anotador_documentos WHERE proyecto_id = $proyectoId ORDER BY actualizado DESC, id DESC");
    }

    public static function deProyectoPorId(int $proyectoId, int $id): ?self
    {
        return self::consultarSQL("SELECT * FROM anotador_documentos WHERE id = $id AND proyecto_id = $proyectoId LIMIT 1")[0] ?? null;
    }

    /** Archivos propios del documento: la copia externa, imágenes/firmas/estampas de las anotaciones y la miniatura */
    public function archivos(): array
    {
        $out = [];
        if ($this->archivo) $out[] = basename((string)$this->archivo);
        if (preg_match_all('/[a-f0-9]{32}\.(?:png|jpg|jpeg|webp|gif|pdf)/i', (string)$this->anotaciones_json, $m)) {
            foreach ($m[0] as $f) $out[] = strtolower($f);
        }
        return array_values(array_unique($out));
    }

    public function bytes(): int
    {
        $t = 0;
        foreach ($this->archivos() as $f) $t += (int)@filesize(Anotador::CARPETA . '/' . $f);
        if ($this->miniatura) $t += (int)@filesize(Anotador::MINIATURAS . '/' . basename((string)$this->miniatura));
        return $t;
    }

    public function config(): array
    {
        $c = json_decode((string)$this->config_json, true);
        return is_array($c) ? $c : [];
    }

    public function anotaciones(): array
    {
        $a = json_decode((string)$this->anotaciones_json, true);
        return is_array($a) ? $a : ['shapes' => []];
    }

    public function finalizado(): bool
    {
        return !empty($this->config()['finalizado']);
    }

    /** Para las tarjetas de la lista (sin los JSON completos) */
    public function tarjeta(): array
    {
        $an = $this->anotaciones();
        $cfg = $this->config();
        $n = is_array($an['shapes'] ?? null) ? count($an['shapes']) : 0;
        return [
            'id' => (int)$this->id, 'nombre' => (string)$this->nombre, 'descripcion' => (string)$this->descripcion,
            'autor' => (string)$this->creado_por, 'creado' => (string)$this->creado, 'actualizado' => (string)$this->actualizado,
            'anotaciones' => $n, 'paginas' => (int)$this->paginas, 'ext' => (string)$this->ext,
            'fuente' => (string)$this->nombre_archivo,
            'finalizado' => !empty($cfg['finalizado']),
            'miniatura' => $this->miniatura && is_file(Anotador::MINIATURAS . '/' . basename((string)$this->miniatura))
                ? '/anotador/miniatura?proyecto=' . (int)$this->proyecto_id . '&id=' . (int)$this->id : '',
        ];
    }

    /** Configuración para abrir el documento en el navegador */
    public function paraJs(): array
    {
        return [
            'id' => (int)$this->id, 'nombre' => (string)$this->nombre, 'descripcion' => (string)$this->descripcion,
            'fuente_tipo' => (string)$this->fuente_tipo, 'fuente_id' => $this->fuente_id ? (int)$this->fuente_id : null,
            'archivo' => (string)$this->archivo, 'nombre_archivo' => (string)$this->nombre_archivo,
            'ext' => (string)$this->ext, 'paginas' => (int)$this->paginas,
            'config' => $this->config(), 'anotaciones' => $this->anotaciones(),
            'creado_por' => (string)$this->creado_por, 'actualizado' => (string)$this->actualizado,
            'finalizado' => $this->finalizado(),
            'puede_editar' => Anotador::puedeEditar(['usuario_id' => $this->usuario_id, 'finalizado' => $this->finalizado()]),
        ];
    }
}
