<?php

namespace Classes\Exportadores;

use Model\Proyecto;
use Classes\Calendario;

abstract class BaseExporter
{
    protected Proyecto $proyecto;
    protected string $carpetaSalida;

    public function __construct(Proyecto $proyecto)
    {
        $this->proyecto = $proyecto;
        $this->carpetaSalida = __DIR__ . '/../../public/exportaciones';
        if (!is_dir($this->carpetaSalida)) {
            mkdir($this->carpetaSalida, 0775, true);
        }
    }

    abstract public function generar(string $nombreBase): string; // retorna ruta absoluta

    protected function nombreFinal(string $nombreBase, string $extension): string
    {
        return generar_nombre_archivo($nombreBase, $extension, $this->proyecto, $this->prefijoNombre());
    }

    /** Texto que va antes del nombre en el archivo (p. ej. el nombre de la curva); vacío por defecto */
    protected function prefijoNombre(): string
    {
        return '';
    }
}
