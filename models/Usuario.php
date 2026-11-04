<?php

namespace Model;

class Usuario extends ActiveRecord
{
    protected static $tabla = 'usuarios';
    protected static $columnasDB = ['id', 'nombre', 'email', 'password', 'rol', 'nivel_acceso', 'logo', 'limite_proyectos', 'limite_almacenamiento_mb', 'creado_por', 'limite_visitantes', 'limite_colaboradores', 'herramientas', 'herramientas_edicion', 'puede_archivos_ajenos', 'token', 'confirmado'];

    public $id;
    public $nombre;
    public $email;
    public $password;
    public $password2;
    public $password_actual;
    public $password_nuevo;
    public $rol;
    public $nivel_acceso;
    public $logo;
    public $limite_proyectos;
    public $limite_almacenamiento_mb;
    public $creado_por;
    public $limite_visitantes;
    public $limite_colaboradores;
    public $herramientas;
    public $herramientas_edicion;
    public $puede_archivos_ajenos;

    /**
     * Herramientas del proyecto que se pueden mostrar u ocultar a un visitante: clave => [etiqueta, prefijos de ruta].
     * Toda herramienta con URL propia debe estar aquí: lo que no se declara se niega al visitante (ver
     * `herramienta_permitida()`), y `tests/run.php` lo comprueba contra las rutas registradas.
     */
    public const HERRAMIENTAS = [
        'programa' => ['Programa', ['/programa', '/api/programa', '/api/actividad', '/api/duracion-sugerida']],
        'dependencias' => ['Dependencias de obra', ['/dependencias', '/api/dependencias']],
        'lineatiempo' => ['Línea de tiempo activa', ['/lineatiempo', '/api/lineatiempo']],
        'planificador' => ['Planificador', ['/planificador']],
        'lookahead' => ['Lookahead', ['/lookahead', '/api/lookahead']],
        'vplan' => ['vPlan', ['/vplan', '/api/vplan']],
        'lastplanner' => ['Last Planner', ['/lastplanner']],
        'reportes' => ['Reporte diario', ['/reportes']],
        'bitacora' => ['Bitácora de obra', ['/bitacora']],
        'minutas' => ['Minutas', ['/minutas']],
        'evidencias' => ['Evidencias', ['/evidencias']],
        'archivos' => ['Archivo', ['/archivos']],
        'visor' => ['Visor', ['/visor']],
        'curvas' => ['Curvas de productividad', ['/curvas', '/api/curvas']],
        'msr' => ['MSR · Materiales', ['/msr', '/api/msr']],
        'cambios' => ['Órdenes de cambio y líneas base', ['/cambios', '/api/cambios', '/lineas-base']],
        'calendario' => ['Calendario', ['/calendario']],
        'graficos' => ['Editor y símbolos', ['/graficos', '/api/graficos', '/simbolos', '/api/simbolos', '/api/estampas']],
        'redactor' => ['Redactor', ['/redactor', '/api/redactor']],
        'sheetflow' => ['SheetFlow (tablas)', ['/sheetflow', '/api/sheetflow']],
        'view360' => ['View360', ['/view360', '/api/view360']],
        'comparador' => ['Comparador de documentos', ['/comparador']],
        'anotador' => ['Anotador de planos', ['/anotador']],
        'ensamble' => ['Ensamblador de documentos', ['/ensamblador-documentos', '/api/ensamblador', '/api/ordenar-ia']],
        'traductor' => ['Traductor', ['/traductor', '/api/traductor']],
        'acciones' => ['Acciones rápidas', ['/acciones']],
        'historial' => ['Historial de cambios', ['/historial', '/api/historial']],
        'costos' => ['Costos y presupuesto', ['/costos']],
        'recursos' => ['Recursos y cuadrillas', ['/recursos']],
        'rfi' => ['RFI', ['/rfi']],
        'seguridad' => ['Seguridad y permisos', ['/seguridad']],
        'punch' => ['Punch list', ['/punch']],
        'calidad' => ['Calidad e inspecciones', ['/calidad']],
        'riesgos' => ['Riesgos', ['/riesgos']],
        'equipo' => ['Equipo y maquinaria', ['/equipo']],
        'contratos' => ['Contratos, fianzas y seguros', ['/contratos']],
        'cierre' => ['Cierre de obra', ['/cierre']],
        'mapa-avance' => ['Mapa de avance', ['/mapa-avance']],
        'submittals' => ['Submittals', ['/submittals', '/api/submittals']],
        'materiales' => ['Catálogo de materiales', ['/materiales', '/api/materiales']],
        'plantillas' => ['Tipo de obra', ['/plantillas']],
        'datos-obra' => ['Datos de obra', ['/datos-obra']],
        'normativa' => ['Normativa y especificaciones', ['/normativa']],
        'programador' => ['Programador de actividades', ['/programador', '/api/programador']],
        'revision-datos' => ['Revisión de datos (IA)', ['/inconsistencias', '/api/inconsistencias', '/analisis-materiales', '/api/analisis-materiales', '/api/materiales-inteligentes']],
        'centro-ia' => ['Centro de IA', ['/propuestas-ia', '/resumen-ia', '/uso-ia', '/calidad-ia', '/api/ia', '/biblioteca-ia', '/informes-ia', '/grabaciones', '/historial-cambios']],
        'revisiones' => ['Revisiones', ['/revisiones', '/api/revisiones']],
        'directorio' => ['Directorio', ['/directorio']],
        'especialidades' => ['Especialidades', ['/especialidades']],
        'campos' => ['Campos personalizados', ['/campos', '/api/campos']],
        'lista-diseno' => ['Diseño de listas', ['/lista']],
        'plantillas-proyecto' => ['Plantillas de proyecto', ['/plantillas-proyecto']],
        'marca' => ['Marca del cliente', ['/marca']],
        'integraciones' => ['Integraciones', ['/integraciones', '/api/escritorio']],
        'compartir' => ['Compartir en solo lectura', ['/compartir']],
        'ajustes' => ['Ajustes del proyecto', ['/ajustes']],
    ];

    /**
     * Agrupación de HERRAMIENTAS por sección, solo para presentarlas ordenadas en el formulario de
     * usuarios. La fuente de verdad sigue siendo HERRAMIENTAS: una clave nueva que no esté aquí cae
     * automáticamente en «Otras herramientas» (ver `gruposHerramientas()`).
     */
    public const HERRAMIENTAS_GRUPOS = [
        'Planeación' => ['programa', 'dependencias', 'lineatiempo', 'planificador', 'lookahead', 'vplan', 'lastplanner', 'riesgos', 'programador', 'calendario', 'curvas'],
        'Obra y seguimiento' => ['reportes', 'bitacora', 'minutas', 'evidencias', 'archivos', 'visor', 'historial', 'mapa-avance', 'acciones', 'punch', 'rfi', 'calidad', 'seguridad'],
        'Documentos y planos' => ['redactor', 'sheetflow', 'comparador', 'anotador', 'ensamble', 'traductor', 'view360', 'graficos', 'submittals'],
        'Materiales y costos' => ['msr', 'materiales', 'costos', 'recursos', 'cambios'],
        'Administración del proyecto' => ['equipo', 'contratos', 'cierre', 'datos-obra', 'plantillas', 'directorio', 'especialidades', 'normativa', 'campos', 'lista-diseno', 'plantillas-proyecto', 'marca', 'integraciones', 'compartir', 'ajustes'],
        'IA y revisión' => ['revision-datos', 'centro-ia', 'revisiones'],
    ];

    /**
     * Herramientas agrupadas y listas para pintar: [sección => [clave => etiqueta], …]. Nunca pierde
     * ninguna clave de HERRAMIENTAS (lo que falte en HERRAMIENTAS_GRUPOS va a «Otras herramientas»).
     */
    public static function gruposHerramientas(): array
    {
        $grupos = [];
        $usadas = [];
        foreach (self::HERRAMIENTAS_GRUPOS as $seccion => $claves) {
            foreach ($claves as $clave) {
                if (!isset(self::HERRAMIENTAS[$clave])) continue;
                $grupos[$seccion][$clave] = self::HERRAMIENTAS[$clave][0];
                $usadas[$clave] = true;
            }
        }
        foreach (self::HERRAMIENTAS as $clave => [$etiqueta]) {
            if (!isset($usadas[$clave])) $grupos['Otras herramientas'][$clave] = $etiqueta;
        }
        return $grupos;
    }

    /**
     * Rutas que no son una herramienta y que un visitante siempre puede usar (navegación, soporte,
     * enlaces públicos, media autenticada y landing). Todo lo demás se le niega si no está en su lista.
     */
    public const RUTAS_GENERALES = [
        '/', '/proyectos', '/dashboard', '/inicio', '/manual', '/novedades', '/buscar', '/idioma',
        '/soporte', '/api/soporte', '/api/empezar', '/api/acciones', '/cliente', '/calendario/ics', '/media',
        '/login', '/logout', '/contacto', '/terminos', '/servicios', '/demo',
        '/exportar',   // el Centro de Exportación filtra cada formato por la herramienta que el usuario ve
    ];

    /** Claves permitidas al visitante (null = todas) */
    public function herramientasPermitidas(): ?array
    {
        if ($this->herramientas === null || $this->herramientas === '') return null;
        $d = json_decode((string)$this->herramientas, true);
        return is_array($d) ? array_values(array_intersect(array_keys(self::HERRAMIENTAS), $d)) : null;
    }

    /** Casillas marcadas del formulario → JSON (null si están todas: «sin restricción») */
    public static function herramientasDePost($marcadas): ?string
    {
        $validas = array_values(array_intersect(array_keys(self::HERRAMIENTAS), is_array($marcadas) ? $marcadas : []));
        return count($validas) === count(self::HERRAMIENTAS) ? null : json_encode($validas);
    }

    /** Herramientas que un colaborador puede editar ([] = ninguna) */
    public function herramientasEdicionPermitidas(): array
    {
        if ($this->herramientas_edicion === null || $this->herramientas_edicion === '') return [];
        $d = json_decode((string)$this->herramientas_edicion, true);
        if (!is_array($d)) return [];
        $validas = array_values(array_intersect(array_keys(self::HERRAMIENTAS), $d));
        // Nunca se editan las herramientas de configuración/administración
        return array_values(array_diff($validas, self::HERRAMIENTAS_NO_EDITABLES));
    }

    /**
     * Casillas «Editar» del formulario → JSON. Solo cuentan las herramientas que el usuario ve y no están
     * en la lista negra. Devuelve NULL si no puede editar ninguna.
     */
    public static function herramientasEdicionDePost($marcadas, ?array $visibles = null): ?string
    {
        $validas = array_values(array_intersect(array_keys(self::HERRAMIENTAS), is_array($marcadas) ? $marcadas : []));
        $validas = array_values(array_diff($validas, self::HERRAMIENTAS_NO_EDITABLES));
        if ($visibles !== null) $validas = array_values(array_intersect($visibles, $validas));
        if (!$validas) return null;
        return json_encode($validas);
    }

    /** ¿Esta cuenta es un colaborador (puede editar donde se le permita)? */
    public function esColaborador(): bool
    {
        return $this->rol === 'visitante' && (string)$this->nivel_acceso === 'editar';
    }

    /** Valida los topes de equipo que fija el admin en una cuenta cliente */
    public function validarLimitesEquipo(): void
    {
        foreach (['limite_visitantes' => self::MAX_VISITANTES_CLIENTE, 'limite_colaboradores' => self::MAX_COLABORADORES_CLIENTE] as $campo => $defecto) {
            $v = filter_var($this->$campo, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);
            if ($v === false) {
                if ($this->rol === 'cliente') self::setAlerta('error', 'El límite de ' . str_replace('_', ' ', substr($campo, 7)) . ' debe ser un número entero entre 0 y 999');
                $this->$campo = $defecto;
            } else {
                $this->$campo = $v;
            }
        }
    }

    /** Longitud mínima de contraseña y lista de las más usadas (no se aceptan) */
    public const PASSWORD_MIN = 10;
    private const PASSWORD_COMUNES = [
        '1234567890', '123456789', '12345678', '12345678910', 'password', 'password1', 'password123',
        'qwertyuiop', 'qwerty123', 'abc123456', 'admin123', 'administrador', 'iloveyou', 'bienvenido',
        'contraseña', 'contrasena', '123456789a', 'a123456789', '1q2w3e4r5t', '0000000000',
    ];

    /** Devuelve el motivo si la contraseña es débil, o null si es aceptable */
    public static function passwordDebil(string $password): ?string
    {
        if (strlen($password) < self::PASSWORD_MIN) return 'La contraseña debe tener al menos ' . self::PASSWORD_MIN . ' caracteres';
        if (in_array(mb_strtolower($password), self::PASSWORD_COMUNES, true)) return 'Esa contraseña es demasiado común; elige otra';
        if (preg_match('/^(.)\1+$/', $password)) return 'La contraseña no puede ser un solo carácter repetido';
        return null;
    }

    /**
     * ¿Esta cuenta debe cambiar la contraseña inicial antes de usar la plataforma?
     * Columna `debe_cambiar_password` (database/migracion_password_inicial.sql). Sin ella, false.
     */
    public static function debeCambiarPassword(int $id): bool
    {
        static $cache = [];
        if (array_key_exists($id, $cache)) return $cache[$id];
        try {
            $r = self::$db->query('SELECT debe_cambiar_password FROM usuarios WHERE id = ' . (int)$id);
            $f = $r ? $r->fetch_row() : null;
            return $cache[$id] = ($f && (int)$f[0] === 1);
        } catch (\Throwable $e) {
            return $cache[$id] = false;   // sin la migración aplicada no se bloquea a nadie
        }
    }

    /** La cuenta ya eligió su contraseña: no se le vuelve a pedir */
    public static function marcarPasswordCambiada(int $id): void
    {
        try {
            self::$db->query('UPDATE usuarios SET debe_cambiar_password = 0 WHERE id = ' . (int)$id);
        } catch (\Throwable $e) {
        }
    }

    /** Marca una contraseña temporal (creada por el admin o por el dueño del equipo) para cambiarla al entrar */
    public static function marcarPasswordTemporal(int $id): void
    {
        try {
            self::$db->query('UPDATE usuarios SET debe_cambiar_password = 1 WHERE id = ' . (int)$id);
        } catch (\Throwable $e) {
        }
    }

    /** Guarda la fecha del último acceso correcto (columna `ultimo_acceso`, ya en el esquema) */
    public static function marcarUltimoAcceso(int $id): void
    {
        try {
            self::$db->query('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ' . (int)$id);
        } catch (\Throwable $e) {
        }
    }

    /** Visitantes de solo lectura que un cliente puede crear por defecto (el admin lo cambia por cuenta) */
    public const MAX_VISITANTES_CLIENTE = 2;
    /** Colaboradores (pueden editar) que un cliente puede crear por defecto */
    public const MAX_COLABORADORES_CLIENTE = 3;

    /**
     * Herramientas que un colaborador nunca puede editar aunque el dueño las marque:
     * cambian la configuración del proyecto, la cuenta o los permisos, o son solo del administrador.
     */
    public const HERRAMIENTAS_NO_EDITABLES = [
        'ajustes', 'compartir', 'integraciones', 'marca', 'plantillas-proyecto', 'directorio',
        'especialidades', 'centro-ia', 'revisiones', 'plantillas', 'historial', 'lista-diseno', 'campos',
    ];

    public $token;
    public $confirmado;
    public $ultimo_acceso;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->email = $args['email'] ?? '';
        $this->password = $args['password'] ?? '';
        $this->password2 = $args['password2'] ?? '';
        $this->rol = $args['rol'] ?? 'visitante';
        $this->nivel_acceso = $args['nivel_acceso'] ?? 'ver';
        $this->logo = $args['logo'] ?? null;
        $this->limite_proyectos = $args['limite_proyectos'] ?? 1;
        $this->limite_almacenamiento_mb = $args['limite_almacenamiento_mb'] ?? 300;
        $this->creado_por = $args['creado_por'] ?? null;
        $this->limite_visitantes = $args['limite_visitantes'] ?? self::MAX_VISITANTES_CLIENTE;
        $this->limite_colaboradores = $args['limite_colaboradores'] ?? self::MAX_COLABORADORES_CLIENTE;
        $this->herramientas = $args['herramientas'] ?? null;
        $this->herramientas_edicion = $args['herramientas_edicion'] ?? null;
        $this->puede_archivos_ajenos = $args['puede_archivos_ajenos'] ?? 0;
        $this->token = $args['token'] ?? '';
        $this->confirmado = $args['confirmado'] ?? 0;
    }

    public function validarLogin(): array
    {
        if (!$this->email) {
            self::setAlertaCampo('email', 'El email es obligatorio');
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            self::setAlertaCampo('email', 'Email no válido');
        }
        if (!$this->password) {
            self::setAlertaCampo('password', 'El password no puede ir vacío');
        }
        return self::$alertas;
    }

    public function validarNuevaCuenta(): array
    {
        if (!$this->nombre) {
            self::setAlertaCampo('nombre', 'El nombre es obligatorio');
        }
        if (!$this->email) {
            self::setAlertaCampo('email', 'El email es obligatorio');
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            self::setAlertaCampo('email', 'Email no válido');
        }
        if (!$this->password) {
            self::setAlertaCampo('password', 'El password no puede ir vacío');
        } elseif ($motivo = self::passwordDebil((string)$this->password)) {
            self::setAlertaCampo('password', $motivo);
        }
        if ($this->password !== $this->password2) {
            self::setAlertaCampo('password2', 'Los passwords son diferentes');
        }
        return self::$alertas;
    }

    /** Cliente: el límite debe ser un entero entre 0 y 999 (0 = no puede crear proyectos) */
    public function validarLimiteProyectos(): void
    {
        $v = filter_var($this->limite_proyectos, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);
        if ($v === false) {
            if ($this->rol === 'cliente') self::setAlertaCampo('limite_proyectos', 'El límite de proyectos debe ser un número entero entre 0 y 999');
            $this->limite_proyectos = 1;
        } else {
            $this->limite_proyectos = $v;
        }

        // Espacio por proyecto (MB): entero entre 1 y 100000
        $mb = filter_var($this->limite_almacenamiento_mb, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($mb === false) {
            if ($this->rol === 'cliente') self::setAlertaCampo('limite_almacenamiento_mb', 'El espacio por proyecto debe ser un número entero de MB entre 1 y 100000');
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

    /**
     * Equipo que creó un cliente: 'visitante' (solo lectura) o 'colaborador' (puede editar).
     * Ambos son `rol = 'visitante'`; los distingue `nivel_acceso`.
     */
    public static function equipoDe(int $clienteId, string $tipo = 'visitante'): array
    {
        return array_values(array_filter(self::visitantesDe($clienteId), fn($u) => $tipo === 'colaborador' ? $u->esColaborador() : !$u->esColaborador()));
    }

    /** Tope de equipo de esta cuenta para el tipo dado */
    public function limiteEquipo(string $tipo): int
    {
        return $tipo === 'colaborador' ? (int)$this->limite_colaboradores : (int)$this->limite_visitantes;
    }

    public function hashPassword(): void
    {
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
    }

    /** Token de un solo uso (32 bytes al azar, no predecible como uniqid) */
    public function crearToken(): void
    {
        $this->token = bin2hex(random_bytes(32));
    }

    public function comprobarPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }
}
