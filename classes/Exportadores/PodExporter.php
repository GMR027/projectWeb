<?php

namespace Classes\Exportadores;

/**
 * Genera archivo .pod compatible con ProjectLibre.
 * ProjectLibre usa internamente el mismo XML de MSPDI pero con extensión .pod.
 */
class PodExporter extends BaseExporter
{
    public function generar(string $nombreBase): string
    {
        $ms = new MspdiXmlExporter($this->proyecto);
        $rutaXml = $ms->generar($nombreBase . '_tmp');
        // Renombrar a .pod
        $nombrePod = $this->nombreFinal($nombreBase, 'pod');
        $rutaPod = $this->carpetaSalida . '/' . $nombrePod;
        rename($rutaXml, $rutaPod);
        return $rutaPod;
    }
}
