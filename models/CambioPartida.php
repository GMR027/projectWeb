<?php

namespace Model;

/** Renglón de una orden de cambio: lo que se instala, retira, reubica, demuele o agrega, con su propio ciclo de vida */
class CambioPartida extends ActiveRecord
{
    protected static $tabla = 'cambio_partidas';
    protected static $columnasDB = ['id', 'orden_id', 'proyecto_id', 'tipo', 'descripcion', 'ubicacion_original', 'ubicacion_nueva', 'cantidad', 'unidad', 'actividad_id', 'material_id', 'requiere_retiro', 'estado', 'fecha_prevista', 'fecha_ejecucion', 'fecha_retiro_prevista', 'fecha_retiro_real', 'notas'];

    public $id;
    public $orden_id;
    public $proyecto_id;
    public $tipo;
    public $descripcion;
    public $ubicacion_original;
    public $ubicacion_nueva;
    public $cantidad;
    public $unidad;
    public $actividad_id;
    public $material_id;
    public $requiere_retiro;
    public $estado;
    public $fecha_prevista;
    public $fecha_ejecucion;
    public $fecha_retiro_prevista;
    public $fecha_retiro_real;
    public $notas;
    /** Solo lectura (la llena la BD) */
    public $actualizado;

    public const TIPOS = [
        'instalacion_temporal' => 'Instalación temporal (se retira después)',
        'instalacion' => 'Instalación',
        'reubicacion' => 'Reubicación',
        'demolicion' => 'Demolición / desmontaje',
        'adicion' => 'Adición (columnas, muros…)',
        'modificacion' => 'Modificación',
    ];
    public const ESTADOS = ['pendiente' => 'Pendiente', 'ejecutado' => 'Ejecutado', 'retirado' => 'Retirado', 'verificado' => 'Verificado', 'cancelado' => 'Cancelado'];

    /** Estados que admite cada tipo: lo temporal se cierra al retirarlo; lo demás, al verificarlo */
    public static function estadosDe(string $tipo, bool $requiereRetiro): array
    {
        return $requiereRetiro ? ['pendiente', 'ejecutado', 'retirado', 'cancelado'] : ['pendiente', 'ejecutado', 'verificado', 'cancelado'];
    }

    /** Nombre del estado «ejecutado» según el tipo (instalado, desmontado, reubicado…) */
    public static function etiquetaEjecutado(string $tipo): string
    {
        return ['instalacion_temporal' => 'Instalado', 'instalacion' => 'Instalado', 'reubicacion' => 'Reubicado', 'demolicion' => 'Desmontado', 'adicion' => 'Construido'][$tipo] ?? 'Ejecutado';
    }

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->orden_id = $args['orden_id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->tipo = $args['tipo'] ?? 'modificacion';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->ubicacion_original = $args['ubicacion_original'] ?? '';
        $this->ubicacion_nueva = $args['ubicacion_nueva'] ?? '';
        $this->cantidad = $args['cantidad'] ?? null;
        $this->unidad = $args['unidad'] ?? '';
        $this->actividad_id = $args['actividad_id'] ?? null;
        $this->material_id = $args['material_id'] ?? null;
        $this->requiere_retiro = (int)($args['requiere_retiro'] ?? 0);
        $this->estado = $args['estado'] ?? 'pendiente';
        $this->fecha_prevista = $args['fecha_prevista'] ?? null;
        $this->fecha_ejecucion = $args['fecha_ejecucion'] ?? null;
        $this->fecha_retiro_prevista = $args['fecha_retiro_prevista'] ?? null;
        $this->fecha_retiro_real = $args['fecha_retiro_real'] ?? null;
        $this->notas = $args['notas'] ?? '';
        $this->actualizado = $args['actualizado'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim((string)$this->descripcion)) self::$alertas['error'][] = 'La descripción del renglón es obligatoria';
        if (!isset(self::TIPOS[$this->tipo])) self::$alertas['error'][] = 'El tipo no es válido';
        foreach (['fecha_prevista', 'fecha_ejecucion', 'fecha_retiro_prevista', 'fecha_retiro_real'] as $c) {
            if ($this->$c && !\DateTime::createFromFormat('Y-m-d', $this->$c)) self::$alertas['error'][] = 'Una de las fechas no es válida';
        }
        return self::$alertas;
    }

    /**
     * Cambia el estado y completa las fechas reales (ejecución / retiro) si faltan.
     * @return bool false si el estado no corresponde al tipo
     */
    public function cambiarEstado(string $nuevo): bool
    {
        if (!in_array($nuevo, self::estadosDe($this->tipo, (bool)$this->requiere_retiro), true)) return false;
        $hoy = date('Y-m-d');
        $this->estado = $nuevo;
        if (in_array($nuevo, ['ejecutado', 'retirado', 'verificado'], true) && !$this->fecha_ejecucion) $this->fecha_ejecucion = $hoy;
        if ($nuevo === 'retirado' && !$this->fecha_retiro_real) $this->fecha_retiro_real = $hoy;
        if ($nuevo === 'pendiente') $this->fecha_ejecucion = $this->fecha_retiro_real = null;
        if ($nuevo === 'ejecutado') $this->fecha_retiro_real = null;
        $this->guardar();
        return true;
    }

    /** Un renglón temporal ya instalado al que aún no se le ha retirado */
    public function retiroPendiente(): bool
    {
        return (int)$this->requiere_retiro === 1 && $this->estado === 'ejecutado';
    }
}
