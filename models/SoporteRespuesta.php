<?php

namespace Model;

use Classes\Imagenes;

/** Respuesta del administrador a un mensaje de soporte (con hasta MAX_FOTOS fotos adjuntas) */
class SoporteRespuesta extends ActiveRecord
{
    protected static $tabla = 'soporte_respuestas';
    protected static $columnasDB = ['id', 'soporte_id', 'titulo', 'descripcion', 'fecha', 'visto'];
    public const CARPETA = 'soporte';
    public const MAX_FOTOS = 5;
    public const MAX_BYTES_FOTO = 8 * 1024 * 1024;

    public $id, $soporte_id, $titulo, $descripcion, $fecha, $visto;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->soporte_id = $args['soporte_id'] ?? null;
        $this->titulo = trim((string)($args['titulo'] ?? ''));
        $this->descripcion = trim((string)($args['descripcion'] ?? ''));
        $this->fecha = $args['fecha'] ?? date('Y-m-d H:i:s');   // la fecha se pone sola
        $this->visto = (int)($args['visto'] ?? 0);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->titulo === '') self::$alertas['error'][] = 'El título es obligatorio';
        if (mb_strlen($this->titulo) > 200) self::$alertas['error'][] = 'El título es demasiado largo (máx. 200)';
        if ($this->descripcion === '') self::$alertas['error'][] = 'La descripción es obligatoria';
        if (mb_strlen($this->descripcion) > 5000) self::$alertas['error'][] = 'La descripción es demasiado larga (máx. 5000)';
        return self::$alertas;
    }

    /** [id => nombre de archivo] */
    public function fotos(): array
    {
        $res = self::$db->query("SELECT id, archivo FROM soporte_respuesta_fotos WHERE respuesta_id = " . (int)$this->id . " ORDER BY id");
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(int)$r['id']] = $r['archivo'];
        return $out;
    }

    public function agregarFoto(string $archivo): void
    {
        self::$db->query("INSERT INTO soporte_respuesta_fotos (respuesta_id, archivo) VALUES (" . (int)$this->id . ", '" . self::$db->escape_string($archivo) . "')");
    }

    /** Quita una foto de esta respuesta (BD y disco) */
    public function quitarFoto(int $fotoId): void
    {
        $fotos = $this->fotos();
        if (!isset($fotos[$fotoId])) return;
        Imagenes::eliminarEn(self::CARPETA, $fotos[$fotoId]);
        self::$db->query("DELETE FROM soporte_respuesta_fotos WHERE id = $fotoId AND respuesta_id = " . (int)$this->id);
    }

    /** Elimina la respuesta y sus fotos del disco */
    public function eliminarConFotos(): void
    {
        foreach ($this->fotos() as $f) Imagenes::eliminarEn(self::CARPETA, $f);
        $this->eliminar();
    }

    /** Borra del disco las fotos de todas las respuestas de un mensaje (antes de eliminar el mensaje) */
    public static function borrarFotosDeSoporte(int $soporteId): void
    {
        foreach (self::deSoporte([$soporteId])[$soporteId] ?? [] as $r) {
            foreach ($r->fotos() as $f) Imagenes::eliminarEn(self::CARPETA, $f);
        }
    }

    /** Respuestas que el cliente aún no ha visto (todas sus conversaciones) */
    public static function nuevasDeUsuario(int $usuarioId): int
    {
        return (int)self::$db->query("SELECT COUNT(*) FROM soporte_respuestas r JOIN soportes s ON s.id = r.soporte_id WHERE s.usuario_id = $usuarioId AND r.visto = 0")->fetch_row()[0];
    }

    /** El cliente abrió las respuestas de un mensaje: quedan como vistas */
    public static function marcarVistas(int $soporteId): void
    {
        self::$db->query("UPDATE soporte_respuestas SET visto = 1 WHERE soporte_id = $soporteId AND visto = 0");
    }

    /** [soporte_id => [SoporteRespuesta, …]] en orden cronológico */
    public static function deSoporte(array $soporteIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $soporteIds)));
        if (!$ids) return [];
        $out = [];
        foreach (self::consultarSQL("SELECT * FROM soporte_respuestas WHERE soporte_id IN (" . implode(',', $ids) . ") ORDER BY fecha ASC, id ASC") as $r) {
            $out[(int)$r->soporte_id][] = $r;
        }
        return $out;
    }

    public function aArray(): array
    {
        return [
            'id' => (int)$this->id, 'soporte_id' => (int)$this->soporte_id, 'titulo' => $this->titulo, 'descripcion' => $this->descripcion,
            'fecha' => date('d/m/Y H:i', strtotime((string)$this->fecha)), 'visto' => (int)$this->visto === 1,
            'fotos' => array_map(fn($id, $f) => ['id' => $id, 'url' => '/uploads/' . self::CARPETA . '/' . $f], array_keys($this->fotos()), array_values($this->fotos())),
        ];
    }
}
