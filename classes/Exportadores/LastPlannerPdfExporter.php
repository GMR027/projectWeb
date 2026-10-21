<?php

namespace Classes\Exportadores;

use Model\Partida;
use Classes\LastPlanner;
use Classes\Pdf;

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
        // Con una estampa como plantilla, las tarjetas miden lo que mide la estampa y se acomodan las que quepan en la hoja carta
        $plantilla = \Model\Estampa::lastPlanner((int)$this->proyecto->id);
        $wcm = $hcm = 7.0;
        $filas = 3;
        $cols = $this->columnas;
        if ($plantilla) {
            [$wu, $hu] = \Classes\Estampas::dimensiones($plantilla->config());
            [$wcm, $hcm] = [max(1.5, $wu * 0.035278), max(1.5, $hu * 0.035278)];
            $cols = max(1, (int)floor((21.0 + 0.3) / ($wcm + 0.3)));
            $filas = max(1, (int)floor((27.0 + 0.3) / ($hcm + 0.3)));
        }
        $porPagina = $cols * $filas;
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

        $proyecto = $this->proyecto;
        $logo = MsrPdfExporter::imagenBase64($proyecto->logo ?: $proyecto->logoEmpresa(), 'build/img/logos');
        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-lastplanner.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'letter', 'portrait', ['isHtml5ParserEnabled' => true]);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }
}
