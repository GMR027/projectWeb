<?php

namespace Model;

class Usuario extends ActiveRecord
{
    protected static $tabla = 'usuarios';
    protected static $columnasDB = ['id', 'nombre', 'email', 'password', 'rol', 'logo', 'limite_proyectos', 'limite_almacenamiento_mb', 'creado_por', 'token', 'confirmado'];

    public $id;
    public $nombre;
    public $email;
    public $password;
    public $password2;
    public $password_actual;
    public $password_nuevo;
    public $rol;
    public $logo;
    public $limite_proyectos;
    public $limite_almacenamiento_mb;
    public $creado_por;

    /** Visitantes (solo lectura) que un cliente puede crear */
    public const MAX_VISITANTES_CLIENTE = 2;
    public $token;
    public $confirmado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->email = $args['email'] ?? '';
        $this->password = $args['password'] ?? '';
        $this->password2 = $args['password2'] ?? '';
        $this->rol = $args['rol'] ?? 'visitante';
        $this->logo = $args['logo'] ?? null;
        $this->limite_proyectos = $args['limite_proyectos'] ?? 1;
        $this->limite_almacenamiento_mb = $args['limite_almacenamiento_mb'] ?? 300;
        $this->creado_por = $args['creado_por'] ?? null;
        $this->token = $args['token'] ?? '';
        $this->confirmado = $args['confirmado'] ?? 0;
    }

    public function validarLogin(): array
    {
        if (!$this->email) {
            self::$alertas['error'][] = 'El email es obligatorio';
        }
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            self::$alertas['error'][] = 'Email no válido';
        }
        if (!$this->password) {
            self::$alertas['error'][] = 'El password no puede ir vacío';
        }
        return self::$alertas;
    }

    public function validarNuevaCuenta(): array
    {
        if (!$this->nombre) {
            self::$alertas['error'][] = 'El nombre es obligatorio';
        }
        if (!$this->email) {
            self::$alertas['error'][] = 'El email es obligatorio';
        }
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            self::$alertas['error'][] = 'Email no válido';
        }
        if (!$this->password) {
            self::$alertas['error'][] = 'El password no puede ir vacío';
        }
        if (strlen($this->password) < 6) {
            self::$alertas['error'][] = 'El password debe contener al menos 6 caracteres';
        }
        if ($this->password !== $this->password2) {
            self::$alertas['error'][] = 'Los passwords son diferentes';
        }
        return self::$alertas;
    }

    /** Cliente: el límite debe ser un entero entre 0 y 999 (0 = no puede crear proyectos) */
    public function validarLimiteProyectos(): void
    {
        $v = filter_var($this->limite_proyectos, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);
        if ($v === false) {
            if ($this->rol === 'cliente') self::$alertas['error'][] = 'El límite de proyectos debe ser un número entero entre 0 y 999';
            $this->limite_proyectos = 1;
        } else {
            $this->limite_proyectos = $v;
        }

        // Espacio por proyecto (MB): entero entre 1 y 100000
        $mb = filter_var($this->limite_almacenamiento_mb, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($mb === false) {
            if ($this->rol === 'cliente') self::$alertas['error'][] = 'El espacio por proyecto debe ser un número entero de MB entre 1 y 100000';
            $this->limite_almacenamiento_mb = 300;
        } else {
            $this->limite_almacenamiento_mb = $mb;
        }
    }

    /** Búsqueda por nombre o correo con paginación. Devuelve ['usuarios' => [...], 'total' => n] */
    public static function buscar(string $q, int $limite, int $desplazamiento): array
    {
        $q = trim($q);
        $donde = '';
        if ($q !== '') {
            // Se escapan también los comodines de LIKE para que % y _ se busquen como texto
            $like = self::escapar(addcslashes(mb_substr($q, 0, 100), '%_\\'));
            $donde = "WHERE nombre LIKE '%$like%' OR email LIKE '%$like%'";
        }
        $total = self::total($donde ? substr($donde, 6) : null);
        $usuarios = self::consultarSQL("SELECT * FROM usuarios $donde ORDER BY id ASC LIMIT " . (int)$limite . ' OFFSET ' . (int)$desplazamiento);
        return ['usuarios' => $usuarios, 'total' => $total];
    }

    /** Visitantes creados por un cliente */
    public static function visitantesDe(int $clienteId): array
    {
        return array_values(array_filter(self::whereAll('creado_por', (string)$clienteId, 'id ASC'), fn($u) => $u->rol === 'visitante'));
    }

    public function hashPassword(): void
    {
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
    }

    public function crearToken(): void
    {
        $this->token = uniqid();
    }

    public function comprobarPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }
}
