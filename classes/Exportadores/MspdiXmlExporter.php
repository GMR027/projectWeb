<?php

namespace Classes\Exportadores;

use Model\Subactividad;

/**
 * Genera XML en formato MSPDI (Microsoft Project Data Interchange).
 * Este XML puede abrirse directamente en MS Project y guardarse como .mpp.
 * Con la bandera de subactividades, la actividad con hijas es una tarea resumen
 * de nivel 2 y cada subactividad una tarea de nivel 3; también se exportan las
 * relaciones (PredecessorLink) de actividades y subactividades.
 */
class MspdiXmlExporter extends BaseExporter
{
    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $pid = (int)$proyecto->id;
        $partidas = $proyecto->getPartidasConActividades();
        $subs = $this->incluirSubactividades ? Subactividad::deProyecto($pid) : [];

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
        $nodos = [];   // clave a<id>/s<id> => elemento Task
        $nodoPorUid = [];   // uid => clave
        $crearTarea = function (string $nombre, int $nivel, bool $resumen, bool $hito, string $inicio, string $fin, int $progreso, string $prioridad, string $notas) use ($xml, $tasks, &$uid, &$nodos, &$nodoPorUid, $proyecto) {
            $t = $xml->createElement('Task');
            $t->appendChild($xml->createElement('UID', (string)$uid));
            $t->appendChild($xml->createElement('ID', (string)$uid));
            $t->appendChild($xml->createElement('Name', htmlspecialchars($nombre)));
            $t->appendChild($xml->createElement('Summary', $resumen ? '1' : '0'));
            $t->appendChild($xml->createElement('OutlineLevel', (string)$nivel));
            if ($inicio !== '') $t->appendChild($xml->createElement('Start', $inicio . 'T08:00:00'));
            if ($fin !== '') $t->appendChild($xml->createElement('Finish', $fin . 'T17:00:00'));
            if ($hito) {
                $t->appendChild($xml->createElement('Milestone', '1'));
                $t->appendChild($xml->createElement('Duration', 'PT0H0M0S'));
            }
            $t->appendChild($xml->createElement('PercentComplete', (string)max(0, min(100, $progreso))));
            if ($notas !== '') $t->appendChild($xml->createElement('Notes', htmlspecialchars($notas)));
            $prio = match ($prioridad) {
                'critica' => 1000,
                'alta' => 750,
                'media' => 500,
                default => 250,
            };
            $t->appendChild($xml->createElement('Priority', (string)$prio));
            $tasks->appendChild($t);
            $uidActual = $uid++;
            return [$t, $uidActual];
        };

        foreach ($partidas as $bloque) {
            $partida = $bloque['partida'];
            $crearTarea($partida->codigo . ' - ' . $partida->nombre, 1, true, false, (string)($partida->fecha_inicio ?? ''), (string)($partida->fecha_fin ?? ''), 0, 'media', '');

            foreach ($bloque['actividades'] as $act) {
                $hijas = $subs[(int)$act->id] ?? [];
                [$tareaA, $uidA] = $crearTarea($act->codigo . ' - ' . $act->nombre, 2, !empty($hijas), $act->esHito(),
                    (string)$act->fecha_inicio, (string)$act->fecha_fin, (int)$act->progreso, (string)$act->prioridad, (string)$act->descripcion);
                $nodos['a' . (int)$act->id] = $tareaA;
                $nodoPorUid[$uidA] = 'a' . (int)$act->id;

                foreach ($hijas as $i => $sub) {
                    [$tareaS, $uidS] = $crearTarea($act->codigo . '.' . ($i + 1) . ' - ' . $sub->nombre, 3, false, $sub->esHito(),
                        (string)$sub->fecha_inicio, (string)$sub->fecha_fin, (int)$sub->progreso, (string)$sub->prioridad, (string)($sub->descripcion ?? ''));
                    $nodos['s' . (int)$sub->id] = $tareaS;
                    $nodoPorUid[$uidS] = 's' . (int)$sub->id;
                }
            }
        }

        // Relaciones: se agregan al elemento de la tarea sucesora
        $tipos = ['FS' => 1, 'SS' => 2, 'FF' => 3, 'SF' => 4];
        foreach (\Classes\Dependencias::relacionesNodos($pid) as $r) {
            if (!isset($nodos[$r['pred']], $nodos[$r['suc']])) continue;
            $predUid = array_search($r['pred'], $nodoPorUid, true);
            if ($predUid === false) continue;
            $link = $xml->createElement('PredecessorLink');
            $link->appendChild($xml->createElement('PredecessorUID', (string)$predUid));
            $link->appendChild($xml->createElement('Type', (string)($tipos[$r['tipo']] ?? 1)));
            $link->appendChild($xml->createElement('CrossProject', '0'));
            $link->appendChild($xml->createElement('LinkLag', (string)((int)$r['retraso'] * 4800)));
            $link->appendChild($xml->createElement('LagFormat', '7'));
            $nodos[$r['suc']]->appendChild($link);
        }

        $nombreArchivo = $this->nombreFinal($nombreBase, 'xml');
        $ruta = $this->carpetaSalida . '/' . $nombreArchivo;
        $xml->save($ruta);
        return $ruta;
    }
}
