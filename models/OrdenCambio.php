<?php

namespace Model;

class OrdenCambio extends ActiveRecord
{
    protected static $tabla = 'ordenes_cambio';
    protected static $columnasDB = ['id', 'proyecto_id', 'numero', 'titulo', 'descripcion', 'solicitante', 'estado', 'fecha', 'impacto_costo', 'impacto_dias', 'actividad_id', 'creado_por'];

    public $id;
    public $proyecto_id;
    public $numero;
    public $titulo;
    public $descripcion;
    public $solicitante;
    public $estado;
    public $fecha;
    public $impacto_costo;
    public $impacto_dias;
    public $actividad_id;
    public $creado_por;
    /** Solo lectura (la llena la BD): sirve para detectar ediciones simultáneas */
    public $actualizado;

    public const ESTADOS = ['borrador' => 'Borrador', 'solicitada' => 'Solicitada', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'ejecutada' => 'Ejecutada', 'cerrada' => 'Cerrada'];
    /** Estados cuyo cambio se considera autorizado para justificar una desviación del programa */
    public const AUTORIZADAS = ['aprobada', 'ejecutada', 'cerrada'];

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->numero = $args['numero'] ?? '';
        $this->titulo = $args['titulo'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->solicitante = $args['solicitante'] ?? '';
        $this->estado = $args['estado'] ?? 'borrador';
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->impacto_costo = $args['impacto_costo'] ?? 0;
        $this->impacto_dias = $args['impacto_dias'] ?? 0;
        $this->actividad_id = $args['actividad_id'] ?? null;
        $this->creado_por = $args['creado_por'] ?? '';
        $this->actualizado = $args['actualizado'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim((string)$this->titulo)) self::$alertas['error'][] = 'El título es obligatorio';
        if (!isset(self::ESTADOS[$this->estado])) self::$alertas['error'][] = 'El estado no es válido';
        if (!$this->fecha || !\DateTime::createFromFormat('Y-m-d', $this->fecha)) self::$alertas['error'][] = 'La fecha no es válida';
        return self::$alertas;
    }

    /** @return OrdenCambio[] */
    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM ordenes_cambio WHERE proyecto_id = $proyectoId ORDER BY fecha DESC, id DESC");
    }

    /** Siguiente número consecutivo sugerido: OC-001, OC-002… */
    public static function siguienteNumero(int $proyectoId): string
    {
        $n = (int)self::$db->query("SELECT COUNT(*) FROM ordenes_cambio WHERE proyecto_id = $proyectoId")->fetch_row()[0];
        do {
            $num = 'OC-' . str_pad((string)(++$n), 3, '0', STR_PAD_LEFT);
            $hay = (int)self::$db->query("SELECT COUNT(*) FROM ordenes_cambio WHERE proyecto_id = $proyectoId AND numero = '" . self::$db->escape_string($num) . "'")->fetch_row()[0];
        } while ($hay);
        return $num;
    }

    /** @return CambioPartida[] */
    public function partidas(): array
    {
        return CambioPartida::consultarSQL('SELECT * FROM cambio_partidas WHERE orden_id = ' . (int)$this->id . ' ORDER BY id');
    }
}
