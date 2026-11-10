<?php

namespace Model;

use Classes\Plan;

/**
 * Organización (empresa) que contrata el plan. Es la entidad que paga, tiene
 * usuarios, proyectos y almacenamiento. Un usuario pertenece a una sola
 * organización en esta versión.
 */
class Organizacion extends ActiveRecord
{
    protected static $tabla = 'organizaciones';
    protected static $columnasDB = [
        'id', 'nombre', 'razon_social', 'rfc', 'correo_facturacion', 'telefono', 'logo',
        'propietario_id', 'plan_id', 'estado_plan', 'trial_hasta', 'plan_inicio', 'plan_fin',
        'gracia_hasta', 'precio_legado', 'limites_json', 'almacenamiento_bytes',
        'marca_activa', 'marca_nombre', 'marca_logo', 'marca_color', 'marca_color2',
        'archivada', 'archivada_en',
    ];

    public $id;
    public $nombre;
    public $razon_social;
    public $rfc;
    public $correo_facturacion;
    public $telefono;
    public $logo;
    public $propietario_id;
    public $plan_id;
    public $estado_plan;
    public $trial_hasta;
    public $plan_inicio;
    public $plan_fin;
    public $gracia_hasta;
    public $precio_legado;
    public $limites_json;
    public $almacenamiento_bytes;
    public $marca_activa;
    public $marca_nombre;
    public $marca_logo;
    public $marca_color;
    public $marca_color2;
    public $archivada;
    public $archivada_en;

    private static ?bool $disponible = null;
    private static array $cacheUsuario = [];

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->razon_social = $args['razon_social'] ?? '';
        $this->rfc = $args['rfc'] ?? '';
        $this->correo_facturacion = $args['correo_facturacion'] ?? '';
        $this->telefono = $args['telefono'] ?? '';
        $this->logo = $args['logo'] ?? null;
        $this->propietario_id = $args['propietario_id'] ?? null;
        $this->plan_id = $args['plan_id'] ?? Plan::POR_DEFECTO;
        $this->estado_plan = $args['estado_plan'] ?? 'trial';
        $this->trial_hasta = $args['trial_hasta'] ?? null;
        $this->plan_inicio = $args['plan_inicio'] ?? null;
        $this->plan_fin = $args['plan_fin'] ?? null;
        $this->gracia_hasta = $args['gracia_hasta'] ?? null;
        $this->precio_legado = $args['precio_legado'] ?? null;
        $this->limites_json = $args['limites_json'] ?? null;
        $this->almacenamiento_bytes = $args['almacenamiento_bytes'] ?? 0;
        $this->marca_activa = $args['marca_activa'] ?? 0;
        $this->marca_nombre = $args['marca_nombre'] ?? '';
        $this->marca_logo = $args['marca_logo'] ?? null;
        $this->marca_color = $args['marca_color'] ?? '';
        $this->marca_color2 = $args['marca_color2'] ?? '';
        $this->archivada = $args['archivada'] ?? 0;
        $this->archivada_en = $args['archivada_en'] ?? null;
    }

    /**
     * Organizaciones para listas y selectores. Por defecto oculta las archivadas
     * (archivar no borra obras ni usuarios, solo deja de ofrecerse la empresa).
     */
    public static function listado(bool $incluirArchivadas = false): array
    {
        if (!self::disponible()) return [];
        $where = $incluirArchivadas ? '' : ' WHERE archivada = 0';
        return self::consultarSQL('SELECT * FROM organizaciones' . $where . ' ORDER BY nombre ASC');
    }

    /** Solo las activas: para selectores de alta de clientes, proyectos y equipo */
    public static function activas(): array
    {
        return self::listado(false);
    }

    public function estaArchivada(): bool
    {
        return (int)($this->archivada ?? 0) === 1;
    }

    /** Archivar: deja de listarse pero conserva obras, usuarios y pagos */
    public function archivar(): bool
    {
        if (!$this->id || $this->estaArchivada()) return false;
        $this->archivada = 1;
        $this->archivada_en = date('Y-m-d H:i:s');
        return (bool)$this->guardar();
    }

    public function desarchivar(): bool
    {
        if (!$this->id || !$this->estaArchivada()) return false;
        $this->archivada = 0;
        $this->archivada_en = null;
        return (bool)$this->guardar();
    }

    /** Solo se puede borrar de verdad una organización vacía (sin obras, usuarios ni pagos) */
    public function puedeEliminarse(): bool
    {
        if (!$this->id) return false;
        try {
            $obras = Proyecto::total('organizacion_id = ' . (int)$this->id);
            $pagos = (int)(self::$db->query('SELECT COUNT(*) FROM organizacion_pagos WHERE organizacion_id = ' . (int)$this->id)->fetch_row()[0] ?? 0);
            return $obras === 0 && $this->contarAsientos() === 0 && $this->contarVisitantes() === 0 && $pagos === 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** ¿Existe la tabla? (migración aplicada) */
    public static function disponible(): bool
    {
        if (self::$disponible !== null) return self::$disponible;
        try {
            $r = self::$db->query("SHOW TABLES LIKE 'organizaciones'");
            return self::$disponible = (bool)($r && $r->fetch_row());
        } catch (\Throwable $e) {
            return self::$disponible = false;
        }
    }

    /** Organización del usuario (por su columna o, en la migración, por propietario_id) */
    public static function deUsuario(int $usuarioId): ?self
    {
        if ($usuarioId <= 0 || !self::disponible()) return null;
        if (array_key_exists($usuarioId, self::$cacheUsuario)) return self::$cacheUsuario[$usuarioId];
        try {
            $u = Usuario::find($usuarioId);
            $org = null;
            if ($u && !empty($u->organizacion_id)) {
                $org = self::find((int)$u->organizacion_id);
            }
            if (!$org) {
                $org = self::where('propietario_id', (string)$usuarioId);
            }
            return self::$cacheUsuario[$usuarioId] = ($org instanceof self ? $org : null);
        } catch (\Throwable $e) {
            return self::$cacheUsuario[$usuarioId] = null;
        }
    }

    /** Organización de la sesión actual */
    public static function actual(): ?self
    {
        if (empty($_SESSION['id'])) return null;
        return self::deUsuario((int)$_SESSION['id']);
    }

    /** Organización interna de ProjectWeb (admin, demo y semillas) */
    public static function interna(): ?self
    {
        if (!self::disponible()) return null;
        try {
            $org = self::where('nombre', 'ProjectWeb');
            if ($org) return $org;
            $admin = Usuario::where('email', 'admin@po.local');
            if ($admin) return self::where('propietario_id', (string)$admin->id);
        } catch (\Throwable $e) {
        }
        return null;
    }

    /** Clave del plan (normalizada) */
    public function planClave(): string
    {
        return Plan::normalizar($this->plan_id ?? Plan::POR_DEFECTO);
    }

    /** Definición del plan */
    public function plan(): array
    {
        return Plan::de($this->planClave());
    }

    /** Overrides guardados (legacy o acuerdos) */
    public function overrides(): array
    {
        if (!$this->limites_json) return [];
        $d = json_decode((string)$this->limites_json, true);
        return is_array($d) ? $d : [];
    }

    /**
     * Límites efectivos. Para legacy, si no hay overrides, se derivan de los
     * campos históricos del propietario (limite_proyectos, etc.).
     */
    public function limites(): array
    {
        $override = $this->overrides();
        if ($this->planClave() === Plan::LEGACY && !$override) {
            $u = $this->propietario_id ? Usuario::find((int)$this->propietario_id) : null;
            if ($u) {
                $override = [
                    'obras' => (int)($u->limite_proyectos ?? 0),
                    'usuarios' => 1 + (int)($u->limite_colaboradores ?? 0),
                    'visitantes' => (int)($u->limite_visitantes ?? 0),
                    'almacenamiento_gb' => null,
                    'almacenamiento_por_obra_mb' => (int)($u->limite_almacenamiento_mb ?? 300),
                ];
            }
        }
        return Plan::limites($this->planClave(), $override);
    }

    /** ¿La organización puede usar una función del plan? */
    public function permite(string $feature): bool
    {
        return in_array($feature, $this->limites()['features'], true);
    }

    /** ¿El plan permite que haya gestores (crean obras)? */
    public function permiteGestores(): bool
    {
        return (bool)$this->limites()['permite_gestores'];
    }

    public function esPropietario(int $usuarioId): bool
    {
        return $usuarioId > 0 && (int)$this->propietario_id === $usuarioId;
    }

    public function propietario(): ?Usuario
    {
        return $this->propietario_id ? Usuario::find((int)$this->propietario_id) : null;
    }

    public function contarObrasActivas(): int
    {
        if (!$this->id) return 0;
        try {
            return Proyecto::total('organizacion_id = ' . (int)$this->id . ' AND archivado = 0');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function contarAsientos(): int
    {
        if (!$this->id) return 0;
        try {
            $r = self::$db->query("SELECT COUNT(*) FROM usuarios WHERE organizacion_id = " . (int)$this->id
                . " AND rol_org IN ('propietario','gestor','colaborador')");
            return $r ? (int)$r->fetch_row()[0] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function contarVisitantes(): int
    {
        if (!$this->id) return 0;
        try {
            $r = self::$db->query("SELECT COUNT(*) FROM usuarios WHERE organizacion_id = " . (int)$this->id
                . " AND rol_org = 'visitante'");
            return $r ? (int)$r->fetch_row()[0] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Días que quedan de trial (null si no está en trial) */
    public function diasTrial(): ?int
    {
        if (($this->estado_plan ?? '') !== 'trial' || empty($this->trial_hasta)) return null;
        $fin = strtotime((string)$this->trial_hasta);
        if (!$fin) return null;
        return (int)ceil(($fin - strtotime(date('Y-m-d'))) / 86400);
    }

    /** ¿La organización está en solo lectura? (suspendida, cancelada, trial vencido o fin de plan + gracia) */
    public function soloLectura(): bool
    {
        $estado = (string)($this->estado_plan ?? 'activo');
        if (in_array($estado, ['suspendido', 'cancelado'], true)) return true;
        $hoy = date('Y-m-d');
        if ($estado === 'trial' && !empty($this->trial_hasta) && $this->trial_hasta < $hoy) return true;
        if (!empty($this->plan_fin)) {
            $fin = $this->gracia_hasta ?: date('Y-m-d', strtotime($this->plan_fin . ' +30 days'));
            if ($fin < $hoy) return true;
        }
        return false;
    }

    public function estadoEtiqueta(): string
    {
        return match ((string)($this->estado_plan ?? 'activo')) {
            'trial' => 'Prueba',
            'activo' => 'Activo',
            'suspendido' => 'Suspendido',
            'cancelado' => 'Cancelado',
            default => ucfirst((string)$this->estado_plan),
        };
    }

    public function estadoBadge(): string
    {
        return match ((string)($this->estado_plan ?? 'activo')) {
            'trial' => 'badge-warn',
            'activo' => 'badge-ok',
            'suspendido', 'cancelado' => 'badge-danger',
            default => 'badge',
        };
    }

    /** Crea la organización de un cliente nuevo y la vincula al propietario */
    public static function crearPara(Usuario $u, string $planClave = Plan::POR_DEFECTO, array $datos = []): ?self
    {
        if (!self::disponible()) return null;
        $planClave = Plan::normalizar($planClave);
        $legacy = $planClave === Plan::LEGACY;
        $hoy = date('Y-m-d');
        $org = new self([
            'nombre' => trim((string)($datos['organizacion_nombre'] ?? '')) ?: ($u->nombre ?: 'Mi empresa'),
            'razon_social' => trim((string)($datos['razon_social'] ?? '')),
            'rfc' => strtoupper(trim((string)($datos['rfc'] ?? ''))),
            'correo_facturacion' => trim((string)($datos['correo_facturacion'] ?? $u->email)),
            'telefono' => trim((string)($datos['telefono'] ?? '')),
            'propietario_id' => (int)$u->id,
            'plan_id' => $planClave,
            'estado_plan' => $legacy ? 'activo' : 'trial',
            'trial_hasta' => $legacy ? null : date('Y-m-d', strtotime($hoy . ' +14 days')),
            'plan_inicio' => $hoy,
            'plan_fin' => $legacy ? date('Y-m-d', strtotime($hoy . ' +12 months')) : null,
            'precio_legado' => $legacy ? null : null,
            'limites_json' => null,
        ]);
        $r = $org->guardar();
        if (empty($r['resultado'])) return null;
        $org->id = (int)$r['id'];
        return $org;
    }

    /** Cambia plan y estado (admin). */
    /** Fecha Y-m-d válida o NULL. Los formularios mandan '' cuando el campo se deja vacío. */
    public static function fechaONull($valor): ?string
    {
        $valor = trim((string)$valor);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)) return null;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $valor : null;
    }

    public function fijarPlan(string $planClave, string $estado, ?string $inicio = null, ?string $fin = null, ?string $gracia = null, ?float $precio = null): bool
    {
        $planClave = Plan::normalizar($planClave);
        $estado = in_array($estado, ['trial', 'activo', 'suspendido', 'cancelado'], true) ? $estado : 'activo';
        $this->plan_id = $planClave;
        $this->estado_plan = $estado;
        // Normalizar fechas: '' rompe las columnas DATE con MySQL en modo estricto
        $this->plan_inicio = self::fechaONull($inicio) ?: $this->plan_inicio;
        $this->plan_fin = self::fechaONull($fin);
        $this->gracia_hasta = self::fechaONull($gracia);
        $this->precio_legado = $precio;
        if ($estado === 'trial' && empty($this->trial_hasta)) {
            $this->trial_hasta = date('Y-m-d', strtotime('+14 days'));
        } elseif ($estado !== 'trial') {
            $this->trial_hasta = null;
        }
        return (bool)$this->guardar();
    }

    /** Últimos pagos manuales de la organización */
    public function pagos(int $limite = 20): array
    {
        if (!$this->id) return [];
        try {
            $r = self::$db->query('SELECT * FROM organizacion_pagos WHERE organizacion_id = ' . (int)$this->id . ' ORDER BY fecha DESC, id DESC LIMIT ' . max(1, $limite));
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Registra un pago manual (sin pasarela). Devuelve false si falta la tabla o el monto es 0. */
    public function registrarPago(array $d): bool
    {
        if (!$this->id) return false;
        $monto = (float)($d['monto'] ?? 0);
        if ($monto <= 0) return false;
        try {
            $fecha = (string)($d['fecha'] ?? date('Y-m-d'));
            $ini = (string)($d['periodo_inicio'] ?? '');
            $fin = (string)($d['periodo_fin'] ?? '');
            $metodo = mb_substr(trim((string)($d['metodo'] ?? '')), 0, 40);
            $ref = mb_substr(trim((string)($d['referencia'] ?? '')), 0, 100);
            $uuid = mb_substr(trim((string)($d['cfdi_uuid'] ?? '')), 0, 50);
            $notas = mb_substr(trim((string)($d['notas'] ?? '')), 0, 255);
            $st = self::$db->prepare('INSERT INTO organizacion_pagos (organizacion_id, fecha, monto, periodo_inicio, periodo_fin, metodo, referencia, cfdi_uuid, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $orgId = (int)$this->id;
            $iniV = $ini !== '' ? $ini : null;
            $finV = $fin !== '' ? $fin : null;
            $st->bind_param('isdssssss', $orgId, $fecha, $monto, $iniV, $finV, $metodo, $ref, $uuid, $notas);
            return (bool)$st->execute();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Transfiere la propiedad a otro usuario de la misma organización.
     * El anterior queda como gestor (puede crear obras, no factura ni administra equipo).
     */
    public function transferirPropiedad(int $nuevoId): bool
    {
        if (!$this->id || $nuevoId <= 0 || $nuevoId === (int)$this->propietario_id) return false;
        $nuevo = Usuario::find($nuevoId);
        if (!$nuevo || (int)$nuevo->organizacion_id !== (int)$this->id) return false;
        $viejo = (int)$this->propietario_id;
        $this->propietario_id = $nuevoId;
        if (!$this->guardar()) return false;
        self::$db->query("UPDATE usuarios SET rol_org = 'propietario', puede_crear_proyectos = 1 WHERE id = " . (int)$nuevoId);
        if ($viejo > 0) {
            self::$db->query("UPDATE usuarios SET rol_org = 'gestor', puede_crear_proyectos = 1 WHERE id = " . $viejo);
        }
        return true;
    }
}
