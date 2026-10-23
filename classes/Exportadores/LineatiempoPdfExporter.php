<?php

namespace Classes\Exportadores;

use Classes\Pdf;
use Classes\Marca;

/**
 * Línea de tiempo activa en PDF: replica la vista (Gantt diario agrupado por partida, con los
 * filtros del usuario) en UNA sola hoja del tamaño que haga falta: se calcula el papel a partir
 * del contenido (etiquetas + un ancho por día + filas), en vez de paginar.
 */
class LineatiempoPdfExporter extends BaseExporter
{
    private array $lt = [];
    private array $filtros = [];
    private array $partidasSel = [];

    public function setDatos(array $lt, array $filtros, array $partidasSel = []): self
    {
        $this->lt = $lt;
        $this->filtros = $filtros;
        $this->partidasSel = $partidasSel;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $lt = $this->lt;
        if (empty($lt['actividades'])) throw new \Exception('No hay actividades que cumplan los filtros para exportar.');

        $etq = 250;   // ancho de la columna de etiquetas (px)
        $eje = 62;    // alto del eje: banda de mes (22) + celdas de día (40)
        $cab = 124;   // cabecera (título, filtros, leyenda) con holgura para textos largos
        $nDias = count($lt['dias']);

        // Un ancho por día según cuántos días abarca; ≥9 deja que el número quepa en su celda
        $ppd = $nDias <= 200 ? 12 : ($nDias <= 400 ? 9 : ($nDias <= 800 ? 5 : 4));

        // Filas: grupo de partida + actividad (más altas con iconos, como en la vista)
        $conIconos = !empty($lt['iconos']);
        $filaGrp = 26;
        $filaAct = $conIconos ? 82 : 30;
        $filasVis = array_filter($lt['partidas'], function ($p) use ($lt) {
            return array_filter($lt['actividades'], fn($a) => (int)$a['partida_id'] === (int)$p['id']);
        });
        $altoFilas = count($filasVis) * $filaGrp + count($lt['actividades']) * $filaAct;
        // Tope de alto (los visores de PDF aguantan hojas de hasta ~200"): si hay demasiadas
        // actividades se estrechan las filas; nunca por debajo de 12 px.
        $maxFilas = 17000 - $eje - $cab;
        if ($altoFilas > $maxFilas) {
            $k = $maxFilas / $altoFilas;
            $filaAct = max(12, (int)($filaAct * $k));
            $filaGrp = max(12, (int)($filaGrp * $k));
            $conIconos = $conIconos && $filaAct >= 48;
            $altoFilas = count($filasVis) * $filaGrp + count($lt['actividades']) * $filaAct;
        }

        // Meses del rango (como la banda de la vista)
        $meses = [];
        foreach ($lt['dias'] as $i => $d) {
            $u = $meses ? array_key_last($meses) : null;
            if ($u !== null && $meses[$u]['mes'] === $d['mes'] && $meses[$u]['anio'] === $d['anio']) {
                $meses[$u]['i1'] = $i;
            } else {
                $meses[] = ['i0' => $i, 'i1' => $i, 'mes' => $d['mes'], 'anio' => $d['anio']];
            }
        }
        $MESES = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $canvasAlto = $eje + $altoFilas;
        // +52 de holgura a la derecha: los chips de estado/restricción van detrás de cada barra
        $anchoPx = $etq + $nDias * $ppd + 52;
        $altoPx = $cab + $canvasAlto + 24;
        $marcaAlto = Marca::de($this->proyecto) ? 58 : 0;   // la banda de marca se suma al papel
        $proyecto = $this->proyecto;
        // La vista se incluye en el ámbito del método: hay que exponerlas como variables locales
        $filtros = $this->filtros;
        $partidasSel = $this->partidasSel;

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-lineatiempo.php';
        $html = ob_get_clean();

        // Una sola hoja: el papel mide exactamente el contenido; si algo desbordara (redondeos,
        // la banda de marca) se agranda un poco y se vuelve a renderizar.
        $anchoPt = $anchoPx * 0.75;
        $altoPt = ($altoPx + $marcaAlto) * 0.75;
        $opciones = ['isHtml5ParserEnabled' => true, 'marca' => $this->proyecto,
            'chroot' => realpath(__DIR__ . '/../../public')];
        $pdf = null;
        for ($intento = 0; $intento < 3; $intento++) {
            $pdf = Pdf::preparar($html, [0, 0, $anchoPt, $altoPt], 'portrait', $opciones);
            $pdf->render();
            if ($pdf->getCanvas()->get_page_count() <= 1) break;
            $altoPt *= 1.06;
        }

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf->output());
        return $ruta;
    }

    /** Color de la barra aclarado (para el tramo completado, como el velo blanco de la vista) */
    public static function claro(string $hex, float $k = 0.72): string
    {
        $hex = ltrim($hex, '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) $hex = '3b82f6';
        $mez = fn(int $v) => str_pad(dechex((int)round($v + (255 - $v) * $k)), 2, '0', STR_PAD_LEFT);
        return '#' . $mez(hexdec(substr($hex, 0, 2))) . $mez(hexdec(substr($hex, 2, 2))) . $mez(hexdec(substr($hex, 4, 2)));
    }

    /** Texto de los filtros aplicados, para la cabecera del PDF y de la hoja */
    public static function textoFiltros(array $filtros, array $partidasSel): string
    {
        $partes = ['Partida: ' . (!empty($filtros['partida']) ? ($partidasSel[$filtros['partida']] ?? '#' . $filtros['partida']) : 'todas')];
        $nombres = ['no_iniciada' => 'no iniciadas', 'en_proceso' => 'en proceso', 'completada' => 'completadas'];
        $estados = array_values(array_intersect((array)($filtros['estados'] ?? []), array_keys($nombres)));
        if ($estados) {
            $partes[] = 'Estado: ' . implode(' + ', array_map(fn($e) => $nombres[$e], $estados));
        } else {
            $estadosLegacy = ['pendientes' => 'no iniciadas y en proceso', 'todas' => 'todas', 'completadas' => 'solo completadas'];
            $partes[] = 'Estado: ' . ($estadosLegacy[$filtros['estado']] ?? 'no iniciadas y en proceso');
        }
        if (!empty($filtros['restriccion'])) $partes[] = 'solo con restricción';
        if (!empty($filtros['icono'])) $partes[] = 'con icono de actividad';
        if (!empty($filtros['mes'])) {
            $MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $md = \DateTime::createFromFormat('!Y-m', (string)$filtros['mes']);
            if ($md) $partes[] = 'mes de ' . strtolower($MESES[(int)$md->format('n')] . ' ' . $md->format('Y'));
        }
        return implode(' · ', $partes);
    }
}
