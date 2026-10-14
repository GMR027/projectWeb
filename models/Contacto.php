<?php

namespace Model;

/** Mensaje enviado desde el formulario público de contacto */
class Contacto extends ActiveRecord
{
    protected static $tabla = 'contactos';
    protected static $columnasDB = ['id', 'nombre', 'empresa', 'puesto', 'correo', 'telefono', 'plan', 'proyectos_aprox', 'preferencia', 'origen', 'mensaje', 'ip', 'leido'];

    public const PLANES = ['Plan Pro', 'Plan Gold', 'Plan Corporativo', 'Aún no lo sé'];
    public const PROYECTOS = ['1 a 5', '6 a 50', '51 a 500', 'Más de 500'];
    public const PREFERENCIAS = ['correo' => 'Correo electrónico', 'telefono' => 'Llamada telefónica', 'whatsapp' => 'WhatsApp'];
    public const ORIGENES = ['Búsqueda en internet', 'Recomendación', 'Redes sociales', 'Evento o expo', 'Otro'];

    public $creado;
    public $id, $nombre, $empresa, $puesto, $correo, $telefono, $plan, $proyectos_aprox, $preferencia, $origen, $mensaje, $ip, $leido;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        foreach (['nombre', 'empresa', 'puesto', 'correo', 'telefono', 'plan', 'proyectos_aprox', 'origen', 'mensaje', 'ip'] as $c) {
            $this->$c = trim((string)($args[$c] ?? ''));
        }
        $this->preferencia = $args['preferencia'] ?? 'correo';
        $this->leido = (int)($args['leido'] ?? 0);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre es obligatorio';
        if ($this->empresa === '') self::$alertas['error'][] = 'La empresa es obligatoria';
        if (!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) self::$alertas['error'][] = 'El correo no es válido';
        if (!preg_match('/^[0-9 +()\-.]{7,25}$/', $this->telefono)) self::$alertas['error'][] = 'El teléfono no es válido';
        if ($this->plan !== '' && !in_array($this->plan, self::PLANES, true)) self::$alertas['error'][] = 'Plan no válido';
        if ($this->proyectos_aprox !== '' && !in_array($this->proyectos_aprox, self::PROYECTOS, true)) $this->proyectos_aprox = '';
        if (!isset(self::PREFERENCIAS[$this->preferencia])) $this->preferencia = 'correo';
        if ($this->origen !== '' && !in_array($this->origen, self::ORIGENES, true)) $this->origen = '';
        if (mb_strlen($this->mensaje) < 10) self::$alertas['error'][] = 'Cuéntanos un poco más en el mensaje (mínimo 10 caracteres)';
        foreach (['nombre' => 150, 'empresa' => 150, 'puesto' => 100, 'correo' => 150, 'mensaje' => 4000] as $c => $max) {
            if (mb_strlen($this->$c) > $max) self::$alertas['error'][] = "El campo $c es demasiado largo";
        }
        return self::$alertas;
    }
}
