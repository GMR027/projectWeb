<?php

namespace Model;

/** Mensaje de soporte de un cliente (llega a la bandeja del admin) */
class Soporte extends ActiveRecord
{
    protected static $tabla = 'soportes';
    protected static $columnasDB = ['id', 'usuario_id', 'tema', 'informacion', 'leido'];

    public $creado;
    public $id, $usuario_id, $tema, $informacion, $leido;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->usuario_id = $args['usuario_id'] ?? null;
        $this->tema = trim((string)($args['tema'] ?? ''));
        $this->informacion = trim((string)($args['informacion'] ?? ''));
        $this->leido = (int)($args['leido'] ?? 0);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->tema === '') self::$alertas['error'][] = 'El tema es obligatorio';
        if (mb_strlen($this->tema) > 200) self::$alertas['error'][] = 'El tema es demasiado largo (máx. 200)';
        if ($this->informacion === '') self::$alertas['error'][] = 'Cuéntanos tu duda o lo que necesitas';
        if (mb_strlen($this->informacion) > 5000) self::$alertas['error'][] = 'La información es demasiado larga (máx. 5000)';
        return self::$alertas;
    }

    public static function deUsuario(int $usuarioId): array
    {
        return self::consultarSQL("SELECT * FROM soportes WHERE usuario_id = $usuarioId ORDER BY id DESC");
    }
}
