<?php

namespace Model;

use Classes\Calendario;

/**
 * Subactividad de una actividad del programa.
 *
 * Se comporta como una actividad hija: tiene sus propios campos (fechas,
 * avance, prioridad, responsable, restricción programada, etc.), su código se
 * deriva del padre (`1.2` → `1.2.1`) y su duración se calcula con el calendario.
 * El avance de la actividad padre es el promedio simple de sus subactividades.
 */
class Subactividad extends ActiveRecord
{
    protected static $tabla = 'subactividades';
    protected static $columnasDB = [
        'id', 'actividad_id', 'calendario_id', 'frente_id', 'nombre', 'descripcion',
        'fecha_inicio', 'fecha_fin', 'fecha_inicio_real', 'fecha_fin_real', 'duracion_restante',
        'restriccion_tipo', 'restriccion_fecha', 'restriccion_motivo',
        'progreso', 'status', 'prioridad', 'pta', 'hito', 'sin_material', 'orden', 'fecha_fija',
        'responsable', 'rec_inicio', 'rec_fin', 'libre_texto', 'libre_numero', 'libre_fecha', 'icono',
    ];

    public const ESTADOS = ['no_iniciada', 'en_proceso', 'completada'];
    public const PRIORIDADES = ['baja', 'media', 'alta', 'critica'];
    public const RESTRICCIONES = ['ninguna', 'no_antes', 'no_despues', 'terminar_antes'];

    public $id;
    public $actividad_id;
    public $calendario_id;
    public $frente_id;
    public $nombre;
    public $descripcion;
    public $fecha_inicio;
    public $fecha_fin;
    public $fecha_inicio_real;
    public $fecha_fin_real;
    public $duracion_restante;
    public $restriccion_tipo;
    public $restriccion_fecha;
    public $restriccion_motivo;
    /** Avance 0–100 */
    public $progreso;
    /** no_iniciada | en_proceso | completada */
    public $status;
    public $prioridad;
    public $pta;
    public $hito;
    public $sin_material;
    public $orden;
    public $fecha_fija;
    public $responsable;
    public $rec_inicio;
    public $rec_fin;
    public $libre_texto;
    public $libre_numero;
    public $libre_fecha;
    public $icono;

    /** Caché actividad_id → proyecto_id para no consultar en cada fila */
    private static array $proyectoCache = [];

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->actividad_id = $args['actividad_id'] ?? null;
        $this->calendario_id = $args['calendario_id'] ?? null;
        $this->frente_id = $args['frente_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->fecha_inicio = $args['fecha_inicio'] ?? null;
        $this->fecha_fin = $args['fecha_fin'] ?? null;
        $this->fecha_inicio_real = $args['fecha_inicio_real'] ?? null;
        $this->fecha_fin_real = $args['fecha_fin_real'] ?? null;
        $this->duracion_restante = $args['duracion_restante'] ?? null;
        $this->restriccion_tipo = $args['restriccion_tipo'] ?? 'ninguna';
        $this->restriccion_fecha = $args['restriccion_fecha'] ?? null;
        $this->restriccion_motivo = $args['restriccion_motivo'] ?? '';
        // Compatibilidad: el antiguo `status` era el avance (0-100).
        $progreso = $args['progreso'] ?? null;
        $status = $args['status'] ?? null;
        if ($progreso === null && $status !== null && is_numeric($status)) $progreso = $status;
        $this->progreso = max(0, min(100, (int)round((float)($progreso ?? 0))));
        $this->status = is_string($status) && in_array($status, self::ESTADOS, true) ? $status : 'no_iniciada';
        $this->prioridad = $args['prioridad'] ?? 'media';
        $this->pta = (int)($args['pta'] ?? 1);
        $this->hito = (int)($args['hito'] ?? 0);
        $this->sin_material = (int)($args['sin_material'] ?? 0);
        $this->orden = (int)($args['orden'] ?? 0);
        $this->fecha_fija = (int)($args['fecha_fija'] ?? 0);
        $this->responsable = $args['responsable'] ?? null;
        $this->rec_inicio = $args['rec_inicio'] ?? null;
        $this->rec_fin = $args['rec_fin'] ?? null;
        $this->libre_texto = $args['libre_texto'] ?? null;
        $this->libre_numero = $args['libre_numero'] ?? null;
        $this->libre_fecha = $args['libre_fecha'] ?? null;
        $this->icono = $args['icono'] ?? null;
        if (!is_string($status) || !in_array($status, self::ESTADOS, true)) $this->sincronizarStatus();
    }

    public function esHito(): bool
    {
        return (int)$this->hito === 1;
    }

    /** Un hito no dura: termina el mismo día que empieza */
    public function normalizarHito(): void
    {
        if ($this->esHito()) {
            $this->fecha_fin = $this->fecha_inicio;
        }
    }

    /** Sincroniza el estado con el avance, igual que una actividad */
    public function sincronizarStatus(): void
    {
        $p = max(0, min(100, (int)round((float)$this->progreso)));
        $this->progreso = $p;
        if ($p <= 0) $this->status = 'no_iniciada';
        elseif ($p >= 100) $this->status = 'completada';
        else $this->status = 'en_proceso';
    }

    public function validar(): array
    {
        self::$alertas = [];
        $this->nombre = trim((string)$this->nombre);
        $this->sincronizarStatus();
        $this->normalizarHito();

        if ($this->nombre === '') {
            self::$alertas['error'][] = 'El nombre de la subactividad es obligatorio';
        } elseif (mb_strlen($this->nombre) > 200) {
            self::$alertas['error'][] = 'El nombre de la subactividad no puede superar los 200 caracteres';
        }

        if (!$this->fecha_inicio || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$this->fecha_inicio)) {
            self::$alertas['error'][] = 'La fecha de inicio de la subactividad es obligatoria';
        }
        if (!$this->fecha_fin || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$this->fecha_fin)) {
            self::$alertas['error'][] = 'La fecha de fin de la subactividad es obligatoria';
        }
        if (!$this->esHito() && $this->fecha_inicio && $this->fecha_fin && $this->fecha_fin < $this->fecha_inicio) {
            self::$alertas['error'][] = 'La fecha de fin no puede ser anterior a la de inicio';
        }
        foreach (['fecha_inicio_real' => 'inicio real', 'fecha_fin_real' => 'fin real', 'rec_inicio' => 'inicio de recuperación', 'rec_fin' => 'fin de recuperación', 'libre_fecha' => 'campo libre de fecha'] as $campo => $etiqueta) {
            $v = $this->$campo;
            if ($v !== null && $v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v)) {
                self::$alertas['error'][] = "La fecha de $etiqueta no es válida";
            }
        }
        if ($this->fecha_inicio_real && $this->fecha_fin_real && $this->fecha_fin_real < $this->fecha_inicio_real) {
            self::$alertas['error'][] = 'El fin real no puede ser anterior al inicio real';
        }
        if ($this->rec_inicio && $this->rec_fin && $this->rec_fin < $this->rec_inicio) {
            self::$alertas['error'][] = 'El fin de recuperación no puede ser anterior al inicio';
        }
        if (!in_array($this->prioridad, self::PRIORIDADES, true)) $this->prioridad = 'media';
        if (!in_array($this->restriccion_tipo, self::RESTRICCIONES, true)) $this->restriccion_tipo = 'ninguna';
        if ($this->restriccion_tipo === 'ninguna') {
            $this->restriccion_fecha = null;
            $this->restriccion_motivo = '';
        }
        if ($this->duracion_restante !== null && $this->duracion_restante !== '' && (!is_numeric($this->duracion_restante) || (float)$this->duracion_restante < 0)) {
            self::$alertas['error'][] = 'La duración restante no es válida';
        }
        foreach (['pta', 'sin_material', 'fecha_fija', 'hito'] as $flag) $this->$flag = (int)(bool)$this->$flag;

        return self::$alertas;
    }

    /** Guarda siempre con el estado y la duración de hito coherentes */
    public function guardar()
    {
        $this->sincronizarStatus();
        $this->normalizarHito();
        return parent::guardar();
    }

    /** Promedio entero (0–100) de los avances; 0 si no hay */
    public static function promedio(array $valores): int
    {
        $out = [];
        foreach ($valores as $s) {
            if (is_array($s)) $s = $s['progreso'] ?? $s['status'] ?? 0;
            $out[] = max(0, min(100, (int)$s));
        }
        if (!$out) return 0;
        return (int)round(array_sum($out) / count($out));
    }

    /** Subactividades de una actividad, en orden estable */
    public static function deActividad(int $actividadId): array
    {
        return self::whereAll('actividad_id', $actividadId, 'orden ASC, fecha_inicio ASC, id ASC');
    }

    /**
     * Subactividades del proyecto agrupadas por actividad: [actividad_id => [Subactividad, …]].
     * Una sola consulta para no repetirla por cada actividad (programa, vPlan, materiales…).
     */
    public static function deProyecto(int $proyectoId): array
    {
        $out = [];
        try {
            $filas = self::consultarSQL(
                "SELECT s.* FROM subactividades s
                 JOIN actividades a ON a.id = s.actividad_id
                 JOIN partidas p ON p.id = a.partida_id
                 WHERE p.proyecto_id = " . (int)$proyectoId . "
                 ORDER BY s.actividad_id, s.orden, s.fecha_inicio, s.id"
            );
            foreach ($filas as $s) $out[(int)$s->actividad_id][] = $s;
        } catch (\Throwable $e) {   // migración pendiente: el programa sigue funcionando sin subactividades
        }
        return $out;
    }

    /** [actividad_id => total] de las subactividades que tiene cada actividad del proyecto */
    public static function conteoPorProyecto(int $proyectoId): array
    {
        $query = "SELECT s.actividad_id, COUNT(*) AS total
                  FROM subactividades s
                  JOIN actividades a ON a.id = s.actividad_id
                  JOIN partidas p ON p.id = a.partida_id
                  WHERE p.proyecto_id = " . (int)$proyectoId . "
                  GROUP BY s.actividad_id";
        $conteo = [];
        try {
            $r = self::$db->query($query);
            if ($r) {
                while ($fila = $r->fetch_assoc()) {
                    $conteo[(int)$fila['actividad_id']] = (int)$fila['total'];
                }
            }
        } catch (\Throwable $e) {   // migración pendiente: el programa sigue funcionando sin subactividades
        }
        return $conteo;
    }

    /** ¿La actividad tiene subactividades? (una sola consulta, tolerante a la migración pendiente) */
    public static function existePara(int $actividadId): bool
    {
        try {
            $r = self::$db->query('SELECT 1 FROM subactividades WHERE actividad_id = ' . (int)$actividadId . ' LIMIT 1');
            return $r && $r->num_rows > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getActividad(): ?Actividad
    {
        return $this->actividad_id ? Actividad::find((int)$this->actividad_id) : null;
    }

    /** Proyecto de la subactividad (a través de su actividad y partida), memorizado */
    public function proyectoId(): int
    {
        $aid = (int)$this->actividad_id;
        if (!$aid) return 0;
        if (!array_key_exists($aid, self::$proyectoCache)) {
            $r = self::$db->query(
                'SELECT p.proyecto_id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = ' . $aid
            );
            $f = $r ? $r->fetch_row() : null;
            self::$proyectoCache[$aid] = (int)($f[0] ?? 0);
        }
        return self::$proyectoCache[$aid];
    }

    /** Códigos derivados del padre: [id => '1.2.1', …] en orden estable */
    public static function codigosDeActividad(int $actividadId, string $codigoPadre = ''): array
    {
        if ($codigoPadre === '') {
            $a = Actividad::find($actividadId);
            $codigoPadre = (string)($a->codigo ?? $actividadId);
        }
        $out = [];
        foreach (self::deActividad($actividadId) as $i => $s) {
            $out[(int)$s->id] = $codigoPadre . '.' . ($i + 1);
        }
        return $out;
    }

    /** Código de una subactividad según su posición (1-based) dentro de la actividad */
    public function codigo(string $codigoPadre, int $indice): string
    {
        return rtrim($codigoPadre, '.') . '.' . max(1, $indice);
    }

    /** Duración en días hábiles (0 en un hito) */
    public function duracion(?Calendario $calendario = null): float
    {
        if ($this->esHito() || !$this->fecha_inicio || !$this->fecha_fin) return 0.0;
        $calendario ??= new Calendario($this->proyectoId(), (int)($this->calendario_id ?? 0));
        return $calendario->calcularDuracion((string)$this->fecha_inicio, (string)$this->fecha_fin);
    }

    /** Estado derivado (por si la fila viene de una consulta antigua sin `status`) */
    public function estado(): string
    {
        if (in_array($this->status, self::ESTADOS, true)) return $this->status;
        $p = (int)$this->progreso;
        return $p <= 0 ? 'no_iniciada' : ($p >= 100 ? 'completada' : 'en_proceso');
    }

    /** Ruta pública del icono (o null si no tiene) */
    public function urlIcono(): ?string
    {
        return $this->icono ? '/build/img/actividades-iconos/' . basename((string)$this->icono) : null;
    }

    /**
     * Fila normalizada para las vistas que mezclan actividades y subactividades.
     * El código se pasa en $ctx['codigo'] (mapa de codigosDeActividad) para no
     * consultar una vez por fila.
     */
    public function aFila(array $ctx = []): array
    {
        return [
            'tipo' => 'subactividad',
            'id' => (int)$this->id,
            'actividad_id' => (int)$this->actividad_id,
            'codigo' => (string)($ctx['codigo'] ?? ''),
            'nombre' => (string)$this->nombre,
            'descripcion' => (string)($this->descripcion ?? ''),
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'duracion_dias' => $this->duracion($ctx['calendario'] ?? null),
            'progreso' => (int)$this->progreso,
            'status' => $this->estado(),
            'prioridad' => (string)($this->prioridad ?: 'media'),
            'hito' => $this->esHito(),
            'responsable' => (string)($this->responsable ?? ''),
            'icono' => $this->icono,
            'rec_inicio' => $this->rec_inicio,
            'rec_fin' => $this->rec_fin,
            'libre_texto' => $this->libre_texto,
            'libre_numero' => $this->libre_numero,
            'libre_fecha' => $this->libre_fecha,
            'fecha_inicio_real' => $this->fecha_inicio_real,
            'fecha_fin_real' => $this->fecha_fin_real,
            'duracion_restante' => $this->duracion_restante,
            'restriccion_tipo' => $this->restriccion_tipo,
            'restriccion_fecha' => $this->restriccion_fecha,
            'restriccion_motivo' => $this->restriccion_motivo,
            'pta' => (int)$this->pta,
            'sin_material' => (int)$this->sin_material,
            'fecha_fija' => (int)$this->fecha_fija,
            'calendario_id' => $this->calendario_id,
            'frente_id' => $this->frente_id,
        ];
    }
}
