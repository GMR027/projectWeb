<?php

namespace Model;

/** Conexión de IA registrada por el admin (local, CLI o API). Nunca guarda claves: se escriben solo en la app de escritorio */
class HerramientaIA extends ActiveRecord
{
    protected static $tabla = 'herramientas_ia';
    protected static $columnasDB = ['id', 'nombre', 'tipo', 'url', 'modelo', 'notas', 'activa'];

    /** tipo => [etiqueta, ¿pide URL?, URL de ejemplo] */
    public const TIPOS = [
        'ollama'   => ['Ollama (local)', true, 'http://localhost:11434'],
        'openai'   => ['API compatible con OpenAI', true, 'https://api.openai.com/v1'],
        'anthropic' => ['API de Anthropic', true, 'https://api.anthropic.com'],
        'claude-code' => ['Claude Code (CLI)', false, ''],
        'opencode' => ['opencode (CLI)', false, ''],
        'otro'     => ['Otra', false, ''],
    ];

    public $id, $nombre, $tipo, $url, $modelo, $notas, $activa;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombre = trim((string)($args['nombre'] ?? ''));
        $this->tipo = (string)($args['tipo'] ?? '');
        $this->url = trim((string)($args['url'] ?? ''));
        $this->modelo = trim((string)($args['modelo'] ?? ''));
        $this->notas = trim((string)($args['notas'] ?? ''));
        $this->activa = (int)($args['activa'] ?? 1);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '' || mb_strlen($this->nombre) > 100) self::$alertas['error'][] = 'El nombre es obligatorio (máx. 100)';
        if (!isset(self::TIPOS[$this->tipo])) self::$alertas['error'][] = 'Tipo no válido';
        if ($this->url !== '') {
            $p = parse_url($this->url);
            if (!in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || mb_strlen($this->url) > 255) {
                self::$alertas['error'][] = 'La URL debe ser http(s) sin usuario, contraseña ni parámetros (las claves no se guardan aquí)';
            }
        }
        if (mb_strlen($this->modelo) > 100 || mb_strlen($this->notas) > 255) self::$alertas['error'][] = 'Modelo o notas demasiado largos';
        return self::$alertas;
    }
}
