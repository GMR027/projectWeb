<?php

namespace Classes\Exportadores;

use Model\Partida;
use Classes\LastPlanner;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Post-its de Last Planner en hojas carta, tarjetas de 7 x 7 cm con marcas de corte */
class LastPlannerPdfExporter extends BaseExporter
{
    private string $partida = 'todas';
    private int $columnas = 2;

    /** Siempre se exportan todos los post-its de la(s) partida(s) elegida(s) */
    public function setOpciones(string $partida, int $columnas): self
    {
        $this->partida = $partida;
        $this->columnas = $columnas === 3 ? 3 : 2;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $partidas = $this->proyecto->getPartidas();
        if ($this->partida === 'juntas') {
            $partidas = [];   // un solo juego: un post-it por día con todas las partidas
        } elseif ($this->partida !== 'todas') {
            $partidas = array_values(array_filter($partidas, fn($p) => (int)$p->id === (int)$this->partida));
        }
        $porPagina = $this->columnas * 3;
        $paginas = [];
        $total = 0;
        if ($this->partida === 'juntas') {
            $t = LastPlanner::tarjetasTodas($this->proyecto);
            $total += count($t);
            foreach (array_chunk($t, $porPagina) as $grupo) $paginas[] = $grupo;
        }
        foreach ($partidas as $p) {
            $t = LastPlanner::tarjetas($this->proyecto, $p, '1900-01-01', '2999-12-31');
            $total += count($t);
            foreach (array_chunk($t, $porPagina) as $grupo) $paginas[] = $grupo;
        }
        if (!$paginas) {
            throw new \Exception('La partida no tiene actividades');
        }

        $cols = $this->columnas;
        $proyecto = $this->proyecto;
        $logo = MsrPdfExporter::imagenBase64($proyecto->logo ?: $proyecto->logoEmpresa(), 'build/img/logos');
        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-lastplanner.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $dompdf->output());
        return $ruta;
    }
}
