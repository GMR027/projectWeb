<?php

namespace Classes\Exportadores;

/**
 * Genera XML en formato MSPDI (Microsoft Project Data Interchange).
 * Este XML puede abrirse directamente en MS Project y guardarse como .mpp.
 */
class MspdiXmlExporter extends BaseExporter
{
    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $partidas = $proyecto->getPartidasConActividades();

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('Project');
        $root->setAttribute('xmlns', 'http://schemas.microsoft.com/project');
        $xml->appendChild($root);

        $root->appendChild($xml->createElement('Name', htmlspecialchars($proyecto->nombre)));
        $root->appendChild($xml->createElement('Title', htmlspecialchars($proyecto->nombre)));
        $root->appendChild($xml->createElement('StartDate', $proyecto->fecha_inicio . 'T08:00:00'));
        $root->appendChild($xml->createElement('FinishDate', $proyecto->fecha_fin . 'T17:00:00'));

        $tasks = $xml->createElement('Tasks');
        $root->appendChild($tasks);

        // Tarea 0 obligatoria (proyecto resumen)
        $tarea0 = $xml->createElement('Task');
        $tarea0->appendChild($xml->createElement('UID', '0'));
        $tarea0->appendChild($xml->createElement('ID', '0'));
        $tarea0->appendChild($xml->createElement('Name', htmlspecialchars($proyecto->nombre)));
        $tarea0->appendChild($xml->createElement('Summary', '1'));
        $tasks->appendChild($tarea0);

        $uid = 1;
        foreach ($partidas as $bloque) {
            $partida = $bloque['partida'];
            $partidaUid = $uid;
            $tareaP = $xml->createElement('Task');
            $tareaP->appendChild($xml->createElement('UID', (string)$uid));
            $tareaP->appendChild($xml->createElement('ID', (string)$uid));
            $tareaP->appendChild($xml->createElement('Name', htmlspecialchars($partida->codigo . ' - ' . $partida->nombre)));
            $tareaP->appendChild($xml->createElement('Summary', '1'));
            $tareaP->appendChild($xml->createElement('OutlineLevel', '1'));
            if ($partida->fecha_inicio) {
                $tareaP->appendChild($xml->createElement('Start', $partida->fecha_inicio . 'T08:00:00'));
            }
            if ($partida->fecha_fin) {
                $tareaP->appendChild($xml->createElement('Finish', $partida->fecha_fin . 'T17:00:00'));
            }
            $tasks->appendChild($tareaP);
            $uid++;

            foreach ($bloque['actividades'] as $act) {
                $tareaA = $xml->createElement('Task');
                $tareaA->appendChild($xml->createElement('UID', (string)$uid));
                $tareaA->appendChild($xml->createElement('ID', (string)$uid));
                $tareaA->appendChild($xml->createElement('Name', htmlspecialchars($act->codigo . ' - ' . $act->nombre)));
                $tareaA->appendChild($xml->createElement('Summary', '0'));
                $tareaA->appendChild($xml->createElement('OutlineLevel', '2'));
                $tareaA->appendChild($xml->createElement('Start', $act->fecha_inicio . 'T08:00:00'));
                $tareaA->appendChild($xml->createElement('Finish', $act->fecha_fin . 'T17:00:00'));
                if ($act->esHito()) {
                    $tareaA->appendChild($xml->createElement('Milestone', '1'));
                    $tareaA->appendChild($xml->createElement('Duration', 'PT0H0M0S'));
                }
                $tareaA->appendChild($xml->createElement('PercentComplete', (string)(int)$act->progreso));
                if ($act->descripcion) {
                    $tareaA->appendChild($xml->createElement('Notes', htmlspecialchars($act->descripcion)));
                }
                // Prioridad: mapear a 0-1000
                $prioridad = match($act->prioridad) {
                    'critica' => 1000,
                    'alta' => 750,
                    'media' => 500,
                    default => 250
                };
                $tareaA->appendChild($xml->createElement('Priority', (string)$prioridad));
                $tasks->appendChild($tareaA);
                $uid++;
            }
        }

        $nombreArchivo = $this->nombreFinal($nombreBase, 'xml');
        $ruta = $this->carpetaSalida . '/' . $nombreArchivo;
        $xml->save($ruta);
        return $ruta;
    }
}
