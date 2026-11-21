<?php

namespace Model;

class ReporteDiario extends ActiveRecord
{
    protected static $tabla = 'reportes_diarios';
    protected static $columnasDB = [
        'id', 'proyecto_id', 'fecha', 'area', 'turno', 'supervisor', 'comentarios', 'estado',
        'clima_precip_mm', 'clima_viento_kmh', 'clima_tmax', 'clima_tmin', 'visitas',
        'firma_nombre', 'firma_imagen', 'offline_uuid', 'creado_por', 'cerrado_en',
        'personal_json', 'actividades_json', 'fotos_json',
    ];

    public const TURNOS = ['dia' => 'Día', 'noche' => 'Noche'];
    public const ESTADOS = ['borrador' => 'Borrador', 'cerrado' => 'Cerrado'];

    public $id;
    public $proyecto_id;
    public $fecha;
    public $area;
    public $turno;
    public $supervisor;
    public $comentarios;
    public $estado;
    public $clima_precip_mm;
    public $clima_viento_kmh;
    public $clima_tmax;
    public $clima_tmin;
    public $visitas;
    public $firma_nombre;
    public $firma_imagen;
    public $offline_uuid;
    public $creado_por;
    public $cerrado_en;
    public $personal_json;
    public $actividades_json;
    public $fotos_json;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->area = $args['area'] ?? '';
        $this->turno = $args['turno'] ?? 'dia';
        $this->supervisor = $args['supervisor'] ?? '';
        $this->comentarios = $args['comentarios'] ?? '';
        $this->estado = $args['estado'] ?? 'borrador';
        $this->clima_precip_mm = $args['clima_precip_mm'] ?? null;
        $this->clima_viento_kmh = $args['clima_viento_kmh'] ?? null;
        $this->clima_tmax = $args['clima_tmax'] ?? null;
        $this->clima_tmin = $args['clima_tmin'] ?? null;
        $this->visitas = $args['visitas'] ?? '';
        $this->firma_nombre = $args['firma_nombre'] ?? '';
        $this->firma_imagen = $args['firma_imagen'] ?? null;
        $this->offline_uuid = $args['offline_uuid'] ?? null;
        $this->creado_por = $args['creado_por'] ?? '';
        $this->cerrado_en = $args['cerrado_en'] ?? null;
        $this->personal_json = $args['personal_json'] ?? '[]';
        $this->actividades_json = $args['actividades_json'] ?? '[]';
        $this->fotos_json = $args['fotos_json'] ?? '[]';
    }

    public function validar(): array
    {
        self::$alertas = [];
        $f = \DateTime::createFromFormat('Y-m-d', (string)$this->fecha);
        if (!$f || $f->format('Y-m-d') !== $this->fecha) self::setAlertaCampo('fecha', 'La fecha no es válida');
        if (mb_strlen($this->area) > 150) self::setAlertaCampo('area', 'El área es demasiado larga (máx. 150)');
        if (mb_strlen($this->supervisor) > 150) self::setAlertaCampo('supervisor', 'El supervisor es demasiado largo (máx. 150)');
        if (mb_strlen($this->visitas) > 500) self::setAlertaCampo('visitas', 'Las visitas son demasiado largas (máx. 500)');
        if (!isset(self::TURNOS[$this->turno])) self::setAlertaCampo('turno', 'Turno no válido');
        if (!isset(self::ESTADOS[$this->estado])) self::setAlertaCampo('estado', 'Estado no válido');
        foreach (['clima_precip_mm' => 'La lluvia', 'clima_viento_kmh' => 'El viento', 'clima_tmax' => 'La temperatura máxima', 'clima_tmin' => 'La temperatura mínima'] as $c => $n) {
            if ($this->$c !== null && $this->$c !== '' && !is_numeric($this->$c)) self::setAlertaCampo($c, "$n no es un número válido");
        }
        if (empty($this->getActividades())) self::setAlertaCampo('actividades', 'Selecciona al menos una actividad del programa');
        return self::$alertas;
    }

    private static function lista($json): array
    {
        $d = json_decode((string)$json, true);
        return is_array($d) ? array_values($d) : [];
    }

    public function getPersonal(): array { return array_map('intval', self::lista($this->personal_json)); }
    public function getActividades(): array { return array_map('intval', self::lista($this->actividades_json)); }
    public function getFotos(): array { return array_map('basename', array_map('strval', self::lista($this->fotos_json))); }

    public function setPersonal(array $ids): void { $this->personal_json = json_encode(array_values(array_unique(array_map('intval', $ids)))); }
    public function setActividades(array $ids): void { $this->actividades_json = json_encode(array_values(array_unique(array_map('intval', $ids)))); }
    public function setFotos(array $nombres): void { $this->fotos_json = json_encode(array_values($nombres)); }

    public function estaCerrado(): bool
    {
        return $this->estado === 'cerrado';
    }

    /** Resumen corto para la bitácora al cerrar el parte */
    public function resumen(): string
    {
        $partes = [];
        if ($this->area !== '') $partes[] = 'Área: ' . $this->area;
        if ($this->supervisor !== '') $partes[] = 'Supervisor: ' . $this->supervisor;
        $partes[] = count($this->getActividades()) . ' actividad(es)';
        if (trim((string)$this->comentarios) !== '') $partes[] = mb_substr(trim($this->comentarios), 0, 300);
        return 'Parte diario cerrado · ' . implode(' · ', $partes);
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM reportes_diarios WHERE proyecto_id = $proyectoId ORDER BY fecha DESC, id DESC");
    }

    /** Últimos partes con totales, para el historial de la app (una sola consulta por obra) */
    public static function recientes(int $proyectoId, int $limite = 12): array
    {
        $limite = max(1, min(50, $limite));
        $res = self::$db->query("SELECT r.id, r.fecha, r.turno, r.area, r.supervisor, r.estado, r.comentarios, r.cerrado_en,
                (SELECT COUNT(*) FROM reporte_personal rp WHERE rp.reporte_id = r.id) personal,
                (SELECT COUNT(*) FROM reporte_equipo re WHERE re.reporte_id = r.id) equipo,
                (SELECT COUNT(*) FROM reporte_materiales rm WHERE rm.reporte_id = r.id) materiales,
                (SELECT COALESCE(SUM(horas), 0) FROM reporte_tiempos rt WHERE rt.reporte_id = r.id) tiempos,
                (SELECT COALESCE(SUM(horas_normal + horas_extra), 0) FROM reporte_personal rp WHERE rp.reporte_id = r.id) horas_personal,
                (SELECT COALESCE(SUM(horas), 0) FROM reporte_equipo re WHERE re.reporte_id = r.id) horas_equipo
            FROM reportes_diarios r WHERE r.proyecto_id = $proyectoId
            ORDER BY r.fecha DESC, r.id DESC LIMIT $limite");
        $filas = [];
        while ($res && ($fila = $res->fetch_assoc())) $filas[] = $fila;
        return $filas;
    }

    /** Busca por la marca local de un parte capturado sin conexión (idempotencia al sincronizar) */
    public static function porOfflineUuid(int $proyectoId, string $uuid): ?ReporteDiario
    {
        if ($uuid === '') return null;
        $uuid = self::escapar($uuid);
        $r = self::consultarSQL("SELECT * FROM reportes_diarios WHERE proyecto_id = $proyectoId AND offline_uuid = '$uuid' LIMIT 1");
        return $r[0] ?? null;
    }
}
