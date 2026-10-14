<?php

namespace Classes\Exportadores;

use Classes\Calendario;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfExporter extends BaseExporter
{
    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $calendario = new Calendario($proyecto->id);
        $gantt = new \Classes\GanttRenderer($proyecto);
        $filas = $gantt->getFilas();

        $inicio = new \DateTime($proyecto->fecha_inicio);
        $fin = new \DateTime($proyecto->fecha_fin);
        $totalDias = $inicio->diff($fin)->days;
        $totalSemanas = (int)ceil($totalDias / 7);

        // ============================================================
        // Elegir granularidad según la duración del proyecto:
        // - ≤ 16 semanas (~4 meses):  columnas = semanas
        // - ≤ 40 semanas (~9 meses):  columnas = quincenas (2 semanas)
        // - > 40 semanas:             columnas = meses
        // ============================================================
        if ($totalSemanas <= 16) {
            $modo = 'semanal';
            $columnas = $this->generarColumnasSemanas($inicio, $fin);
        } elseif ($totalSemanas <= 40) {
            $modo = 'quincenal';
            $columnas = $this->generarColumnasQuincenas($inicio, $fin);
        } else {
            $modo = 'mensual';
            $columnas = $this->generarColumnasMeses($inicio, $fin);
        }

        $totalColumnas = count($columnas);
        $totalFilas = count($filas);

        // Calcular posición de cada fila en las columnas del PDF
        foreach ($filas as &$fila) {
            if (empty($fila['fecha_inicio']) || empty($fila['fecha_fin'])) {
                $fila['pdf_pos'] = [1, 1];
                continue;
            }
            $fila['pdf_pos'] = $this->calcularPosicionEnColumnas(
                $fila['fecha_inicio'],
                $fila['fecha_fin'],
                $columnas
            );
        }
        unset($fila);

        // Paginación: máximo 14 columnas por página si hay muchas
        $maxColumnasPorPagina = 14;
        if ($totalColumnas <= $maxColumnasPorPagina) {
            $paginas = [$columnas];
            $modoCompacto = true; // 1 sola hoja de gantt
        } else {
            $paginas = array_chunk($columnas, $maxColumnasPorPagina, true);
            $modoCompacto = false;
        }

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-gantt.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        // Permitir cargar imágenes locales
        $options->set('chroot', realpath(__DIR__ . '/../../public'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('tabloid', 'landscape');
        $dompdf->render();

        $nombreArchivo = $this->nombreFinal($nombreBase, 'pdf');
        $ruta = $this->carpetaSalida . '/' . $nombreArchivo;
        file_put_contents($ruta, $dompdf->output());
        return $ruta;
    }

    /** Columnas semanales (lunes a domingo) */
    private function generarColumnasSemanas(\DateTime $inicio, \DateTime $fin): array
    {
        $inicio = clone $inicio;
        $inicio->modify('monday this week');
        $columnas = [];
        $curr = clone $inicio;
        while ($curr <= $fin) {
            $ini = clone $curr;
            $finS = (clone $curr)->modify('+6 days');
            $columnas[] = [
                'etiqueta' => 'S' . $curr->format('W'),
                'sub' => $ini->format('d M'),
                'inicio' => $ini->format('Y-m-d'),
                'fin' => $finS->format('Y-m-d'),
            ];
            $curr->modify('+7 days');
        }
        return $columnas;
    }

    /** Columnas quincenales (1-15 y 16-fin de mes) */
    private function generarColumnasQuincenas(\DateTime $inicio, \DateTime $fin): array
    {
        $columnas = [];
        $curr = new \DateTime($inicio->format('Y-m-01'));
        while ($curr <= $fin) {
            $mes = (int)$curr->format('n');
            $anio = $curr->format('Y');
            $ultimoDia = (int)$curr->format('t');

            // Q1: 1-15
            $columnas[] = [
                'etiqueta' => 'Q1 ' . $this->mesCorto($mes),
                'sub' => "1-15 " . $this->mesCorto($mes),
                'inicio' => "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01",
                'fin' => "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-15",
            ];
            // Q2: 16-fin
            $columnas[] = [
                'etiqueta' => 'Q2 ' . $this->mesCorto($mes),
                'sub' => "16-$ultimoDia " . $this->mesCorto($mes),
                'inicio' => "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-16",
                'fin' => "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-" . str_pad($ultimoDia, 2, '0', STR_PAD_LEFT),
            ];
            $curr->modify('first day of next month');
        }
        return $columnas;
    }

    /** Columnas mensuales */
    private function generarColumnasMeses(\DateTime $inicio, \DateTime $fin): array
    {
        $columnas = [];
        $curr = new \DateTime($inicio->format('Y-m-01'));
        while ($curr <= $fin) {
            $mes = (int)$curr->format('n');
            $anio = $curr->format('Y');
            $ultimoDia = (int)$curr->format('t');
            $columnas[] = [
                'etiqueta' => $this->mesCorto($mes) . ' ' . $curr->format('y'),
                'sub' => $this->mesCorto($mes) . ' ' . $anio,
                'inicio' => "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01",
                'fin' => "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-" . str_pad($ultimoDia, 2, '0', STR_PAD_LEFT),
            ];
            $curr->modify('first day of next month');
        }
        return $columnas;
    }

    private function mesCorto(int $mes): string
    {
        return ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][$mes];
    }

    /** Calcula en qué columnas cae una actividad (1-based) */
    private function calcularPosicionEnColumnas(string $fechaInicio, string $fechaFin, array $columnas): array
    {
        $colI = 1;
        $colF = 1;
        $ultima = count($columnas);
        foreach ($columnas as $i => $col) {
            if ($fechaInicio >= $col['inicio'] && $fechaInicio <= $col['fin']) {
                $colI = $i + 1;
            }
            if ($fechaFin >= $col['inicio'] && $fechaFin <= $col['fin']) {
                $colF = $i + 1;
            }
        }
        // Ajustes de borde
        if ($fechaInicio < $columnas[0]['inicio']) $colI = 1;
        if ($fechaFin > $columnas[$ultima - 1]['fin']) $colF = $ultima;
        if ($colF < $colI) $colF = $colI;
        return [$colI, $colF];
    }
}
