<?php

namespace Model;

class Proyecto extends ActiveRecord
{
    protected static $tabla = 'proyectos';
    protected static $columnasDB = ['id', 'codigo', 'nombre', 'descripcion', 'ubicacion', 'empresa', 'contratista', 'contratista_logo', 'numero_trabajo', 'msr_umbral_critico', 'logo', 'caratula', 'fecha_inicio', 'fecha_fin', 'color', 'usuario_id', 'modo_dependencias', 'modo_dependencias_materiales'];

    public $id;
    public $codigo;
    public $nombre;
    public $descripcion;
    public $ubicacion;
    public $empresa;
    public $contratista;
    public $contratista_logo;
    public $numero_trabajo;
    public $msr_umbral_critico;
    public $logo;
    public $caratula;
    public $fecha_inicio;
    public $fecha_fin;
    public $color;
    public $usuario_id;
    public $modo_dependencias;
    public $modo_dependencias_materiales;
    public $tema_modo;
    // Programa configurable (Classes\ProgramaColumnas): se escriben con SQL propio, no con guardar()
    public $programa_nombre;
    public $programa_columnas_json;
    public $programa_indicador_json;
    public $tema_paleta;
    // Barra superior de herramientas: escribe el nombre debajo de cada icono (Ajustes → Apariencia)
    public $nombres_herramientas;
    // Apariencia de la barra superior: clasico | panel | pestanas (Ajustes → Apariencia)
    public $menu_superior;
    // Espacio propio del proyecto en MB (lo asigna el admin; NULL = hereda del cliente dueño)
    public $limite_almacenamiento_mb;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->codigo = $args['codigo'] ?? '';
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->ubicacion = $args['ubicacion'] ?? '';
        $this->empresa = $args['empresa'] ?? '';
        $this->contratista = $args['contratista'] ?? '';
        $this->contratista_logo = $args['contratista_logo'] ?? null;
        $this->numero_trabajo = $args['numero_trabajo'] ?? '';
        $this->msr_umbral_critico = $args['msr_umbral_critico'] ?? 60;
        $this->logo = $args['logo'] ?? null;
        $this->caratula = $args['caratula'] ?? null;
        $this->fecha_inicio = $args['fecha_inicio'] ?? '';
        $this->fecha_fin = $args['fecha_fin'] ?? '';
        $this->color = $args['color'] ?? '#FF5A1F';
        $this->usuario_id = $args['usuario_id'] ?? null;
        $this->modo_dependencias = $args['modo_dependencias'] ?? 'apagado';
        $this->modo_dependencias_materiales = $args['modo_dependencias_materiales'] ?? 'apagado';
    }

    public function validar(): array
    {
        if (!$this->codigo) {
            self::$alertas['error'][] = 'El código del proyecto es obligatorio';
        }
        if (!$this->nombre) {
            self::$alertas['error'][] = 'El nombre del proyecto es obligatorio';
        }
        if (!$this->fecha_inicio) {
            self::$alertas['error'][] = 'La fecha de inicio es obligatoria';
        }
        if (!$this->fecha_fin) {
            self::$alertas['error'][] = 'La fecha de fin es obligatoria';
        }
        if ($this->fecha_inicio && $this->fecha_fin && strtotime($this->fecha_fin) < strtotime($this->fecha_inicio)) {
            self::$alertas['error'][] = 'La fecha de fin no puede ser anterior a la de inicio';
        }
        return self::$alertas;
    }

    /** Proyectos que el usuario puede ver: admin todos; resto propios + asignados */
    public static function visiblesPara(array $usuario): array
    {
        if ($usuario['rol'] === 'admin') {
            return self::all('creado DESC');
        }
        $uid = (int)$usuario['id'];
        return self::consultarSQL(
            "SELECT * FROM proyectos WHERE usuario_id = $uid
             OR id IN (SELECT proyecto_id FROM proyecto_accesos WHERE usuario_id = $uid)
             ORDER BY creado DESC"
        );
    }

    /** Logo de la empresa: el del usuario dueño (rol cliente) o, si no tiene, el del proyecto */
    public function logoEmpresa(): ?string
    {
        static $cache = [];   // en listas se repite el dueño: una consulta por usuario, no por proyecto
        $uid = (int)$this->usuario_id;
        if (!array_key_exists($uid, $cache)) $cache[$uid] = Usuario::find($uid);
        $dueno = $cache[$uid];
        return ($dueno && $dueno->logo) ? $dueno->logo : ($this->logo ?: null);
    }

    public function getPartidas(): array
    {
        return Partida::whereAll('proyecto_id', $this->id, 'orden ASC, codigo ASC');
    }

    /**
     * Partidas con sus actividades cargadas en dos consultas (no una por partida).
     * [ ['partida' => Partida, 'actividades' => [Actividad, …]], … ]
     */
    public function getPartidasConActividades(): array
    {
        $partidas = $this->getPartidas();
        if (!$partidas) return [];
        $porPartida = [];
        foreach (Actividad::deProyecto((int)$this->id) as $a) $porPartida[(int)$a->partida_id][] = $a;
        $out = [];
        foreach ($partidas as $p) $out[] = ['partida' => $p, 'actividades' => $porPartida[(int)$p->id] ?? []];
        return $out;
    }

    public function getJornada(): array
    {
        return CalendarioJornada::whereAll('proyecto_id', $this->id, 'dia_semana ASC');
    }

    public function getFestivos(): array
    {
        return CalendarioFestivo::whereAll('proyecto_id', $this->id, 'fecha ASC');
    }

    /** Inicializa la jornada por defecto L-V=1, S=0.5, D=0 */
    public function inicializarJornadaDefault(): void
    {
        $defaults = [
            1 => 1.0, 2 => 1.0, 3 => 1.0, 4 => 1.0, 5 => 1.0,
            6 => 0.5, // Sábado
            7 => 0.0  // Domingo
        ];
        // Es la puesta en marcha del proyecto, no una edición del usuario: no debe llenar el historial de cambios
        \Classes\HistorialCambios::silenciar(function () use ($defaults) {
            foreach ($defaults as $dia => $factor) {
                $jornada = new CalendarioJornada([
                    'proyecto_id' => $this->id,
                    'dia_semana' => $dia,
                    'factor' => $factor
                ]);
                $jornada->guardar();
            }
        });
    }

    /** Calcula progreso ponderado del proyecto */
    /**
     * Avance general del proyecto ponderado por duración (los hitos no pesan), la misma regla que la partida,
     * el Gantt y la Curva S. Si solo hay hitos se usa el promedio simple para no mostrar 0 %.
     * Se memoriza por proyecto: las vistas lo piden 2–3 veces.
     */
    public function getProgreso(): float
    {
        static $memo = [];
        $id = (int)$this->id;
        if (array_key_exists($id, $memo)) return $memo[$id];
        $r = self::$db->query(
            "SELECT ROUND(COALESCE(
                SUM(CASE WHEN a.hito = 0 THEN GREATEST(1, a.duracion_dias) * a.progreso ELSE 0 END) /
                NULLIF(SUM(CASE WHEN a.hito = 0 THEN GREATEST(1, a.duracion_dias) ELSE 0 END), 0),
                AVG(a.progreso)
            ), 1) pct
            FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $id"
        );
        return $memo[$id] = (float)($r ? ($r->fetch_row()[0] ?? 0) : 0);
    }
}
