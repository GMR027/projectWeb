<?php

namespace Model;

class Partida extends ActiveRecord
{
    protected static $tabla = 'partidas';
    protected static $columnasDB = ['id', 'proyecto_id', 'codigo', 'nombre', 'descripcion', 'color_bg', 'orden', 'fecha_inicio', 'fecha_fin', 'cerrada', 'cerrada_por', 'cerrada_en', 'cierre_motivo'];

    public $id;
    public $proyecto_id;
    public $codigo;
    public $nombre;
    public $descripcion;
    public $color_bg;
    public $orden;
    public $fecha_inicio;
    public $fecha_fin;
    public $cerrada;
    public $cerrada_por;
    public $cerrada_en;
    public $cierre_motivo;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->codigo = $args['codigo'] ?? '';
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->color_bg = $args['color_bg'] ?? null;
        $this->orden = $args['orden'] ?? 0;
        $this->fecha_inicio = $args['fecha_inicio'] ?? null;
        $this->fecha_fin = $args['fecha_fin'] ?? null;
        $this->cerrada = $args['cerrada'] ?? 0;
        $this->cerrada_por = $args['cerrada_por'] ?? '';
        $this->cerrada_en = $args['cerrada_en'] ?? null;
        $this->cierre_motivo = $args['cierre_motivo'] ?? '';
    }

    public function validar(): array
    {
        if (!$this->codigo) {
            self::setAlertaCampo('codigo', 'El código de la partida es obligatorio');
        }
        if (!$this->nombre) {
            self::setAlertaCampo('nombre', 'El nombre de la partida es obligatorio');
        }
        return self::$alertas;
    }

    /**
     * Cierra la partida si no tiene pendientes graves del punch. El admin/dueño puede
     * cerrarla igual con un motivo firmado (la autorización se valida en el controlador).
     * @return array{0:bool,1:string}
     */
    public static function cerrar(int $pid, int $id, string $motivo = ''): array
    {
        $p = self::find($id);
        if (!$p || (int)$p->proyecto_id !== $pid) return [false, 'La partida no pertenece al proyecto'];
        $n = \Classes\Punch::partidasBloqueadas($pid)[$id] ?? 0;
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo));
        if ($n && mb_strlen($motivo) < 10) return [false, 'No se puede cerrar: hay ' . $n . ' pendiente(s) grave(s) de punch. Escribe el motivo de la excepción (mínimo 10 caracteres).'];
        $usuario = self::$db->escape_string(mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150));
        $mot = self::$db->escape_string(mb_substr($n ? $motivo : '', 0, 500));
        self::$db->query("UPDATE partidas SET cerrada = 1, cerrada_por = '$usuario', cerrada_en = NOW(), cierre_motivo = '$mot' WHERE id = $id AND proyecto_id = $pid");
        try {
            \Classes\Auditoria::registrar($pid, 'programa', 'cerrar_partida', 'editar',
                'Cerró la partida ' . $p->codigo . ($n ? ' con excepción: ' . mb_substr($motivo, 0, 160) : ''), null, null, $id, 'Partida');
        } catch (\Throwable $e) {
        }
        return [true, $n ? 'Partida cerrada con excepción firmada' : 'Partida cerrada'];
    }

    public static function reabrir(int $pid, int $id): bool
    {
        $p = self::find($id);
        if (!$p || (int)$p->proyecto_id !== $pid) return false;
        self::$db->query("UPDATE partidas SET cerrada = 0, cerrada_por = '', cerrada_en = NULL, cierre_motivo = '' WHERE id = $id AND proyecto_id = $pid");
        try {
            \Classes\Auditoria::registrar($pid, 'programa', 'reabrir_partida', 'editar', 'Reabrió la partida ' . $p->codigo, null, null, $id, 'Partida');
        } catch (\Throwable $e) {
        }
        return true;
    }

    public function getActividades(): array
    {
        return Actividad::whereAll('partida_id', $this->id, 'orden ASC, codigo ASC');
    }

    /**
     * Elimina la partida junto con todas sus actividades y lo que depende de ellas: restricciones, dependencias, materiales
     * vinculados, avance, recovery y capas del vPlan/mapa de avance (en cascada); evidencias, punch, permisos, incidentes y
     * órdenes de cambio conservan su registro sin actividad. Además retira la partida de los bloques de tareas del vPlan y
     * refresca las fechas de instalación del MSR.
     */
    public function eliminarConActividades(): bool
    {
        $id = (int)$this->id;
        $proyectoId = (int)$this->proyecto_id;
        $n = (int)(self::$db->query("SELECT COUNT(*) FROM actividades WHERE partida_id = $id")->fetch_row()[0]);
        self::$db->query("DELETE FROM actividades WHERE partida_id = $id");
        $ok = $this->eliminar();
        if ($ok && $n > 0) {
            // Las actividades se borran con SQL directo (cascada): se anotan aparte, sin qué revertir
            $etiqueta = trim(($this->codigo ?? '') . ' ' . ($this->nombre ?? ''));
            \Classes\HistorialCambios::registrar($proyectoId, 'programa', 'eliminado',
                "Se eliminaron $n actividad(es) con la partida " . $etiqueta, ['id' => $id, 'actividades' => $n], null,
                ['partida', $id, $etiqueta]);
        }
        if ($ok) {
            self::quitarDeBloquesVplan($proyectoId, $id);
            ActividadMaterial::sincronizarInstalacion($proyectoId);
        }
        return (bool)$ok;
    }

    /** Quita la partida de las fuentes de los bloques de tareas del vPlan (si un bloque se queda sin fuentes, queda vacío) */
    private static function quitarDeBloquesVplan(int $proyectoId, int $partidaId): void
    {
        $r = self::$db->query(
            "SELECT b.id, b.config_json FROM vplan_bloques b JOIN vplan_laminas l ON l.id = b.lamina_id
             WHERE l.proyecto_id = $proyectoId AND b.tipo = 'tareas' AND b.config_json LIKE '%\"partida\"%'"
        );
        while ($r && ($fila = $r->fetch_assoc())) {
            $cfg = json_decode((string)$fila['config_json'], true);
            if (!is_array($cfg) || !isset($cfg['fuentes']) || !is_array($cfg['fuentes'])) continue;
            $fuentes = array_values(array_filter($cfg['fuentes'], fn($f) => (int)($f['partida'] ?? 0) !== $partidaId));
            if (count($fuentes) === count($cfg['fuentes'])) continue;
            $cfg['fuentes'] = $fuentes;
            $json = self::$db->escape_string(json_encode($cfg, JSON_UNESCAPED_UNICODE));
            self::$db->query("UPDATE vplan_bloques SET config_json = '$json' WHERE id = " . (int)$fila['id']);
        }
    }

    /** Recalcula fecha_inicio y fecha_fin de la partida según sus actividades */
    public function recalcularFechas(): void
    {
        // Una consulta en vez de cargar todas las actividades; sin actividades, la partida queda sin fechas
        $id = (int)$this->id;
        $r = self::$db->query("SELECT MIN(fecha_inicio) inicio, MAX(fecha_fin) fin FROM actividades WHERE partida_id = $id");
        $f = $r ? ($r->fetch_assoc() ?: []) : [];
        $this->fecha_inicio = $f['inicio'] ?? null;
        $this->fecha_fin = $f['fin'] ?? null;
        // Cálculo del sistema (las fechas salen de sus actividades): no es un cambio que el usuario hiciera a mano,
        // así que no se anota en el historial (el cambio real que lo provocó sí queda registrado)
        \Classes\HistorialCambios::silenciar(fn() => $this->guardar());
    }

    /**
     * Progreso ponderado de la partida por duración (los hitos no pesan; si solo hay hitos, promedio simple).
     * Una consulta, sin cargar las actividades.
     */
    public function getProgreso(): float
    {
        $id = (int)$this->id;
        $r = self::$db->query("SELECT ROUND(COALESCE(
            SUM(CASE WHEN hito = 0 THEN GREATEST(1, duracion_dias) * progreso ELSE 0 END) /
            NULLIF(SUM(CASE WHEN hito = 0 THEN GREATEST(1, duracion_dias) ELSE 0 END), 0),
            AVG(progreso)), 1) pct FROM actividades WHERE partida_id = $id");
        return (float)($r ? ($r->fetch_row()[0] ?? 0) : 0);
    }

    /** [partida_id => %] de todas las partidas del proyecto en una sola consulta (Gantt y listas) */
    public static function progresosDeProyecto(int $proyectoId): array
    {
        $id = (int)$proyectoId;
        $r = self::$db->query(
            "SELECT a.partida_id, ROUND(COALESCE(
                SUM(CASE WHEN a.hito = 0 THEN GREATEST(1, a.duracion_dias) * a.progreso ELSE 0 END) /
                NULLIF(SUM(CASE WHEN a.hito = 0 THEN GREATEST(1, a.duracion_dias) ELSE 0 END), 0),
                AVG(a.progreso)
             ), 1) pct
             FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $id GROUP BY a.partida_id"
        );
        $out = [];
        while ($r && ($f = $r->fetch_assoc())) $out[(int)$f['partida_id']] = (float)$f['pct'];
        return $out;
    }
}
