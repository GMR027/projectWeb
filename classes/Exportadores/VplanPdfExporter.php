<?php

namespace Classes\Exportadores;

use Model\VplanLamina;
use Classes\Imagenes;
use Classes\Vplan;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Láminas de vPlan en PDF, cada una al tamaño de hoja que tenga (una lámina por página) */
class VplanPdfExporter extends BaseExporter
{
    private string $lamina = 'todas';
    private int $paquete = 0;

    /** Limita «todas» a las láminas de un vPlan (0 = todos los vPlan del proyecto) */
    public function setPaquete(int $paquete): self
    {
        $this->paquete = $paquete;
        return $this;
    }

    public function setLamina(string $lamina): self
    {
        $this->lamina = $lamina;
        return $this;
    }

    /** Rombo rojo con número como imagen (Dompdf no gira texto) */
    public static function rombo(int $n): string
    {
        static $cache = [];
        if (isset($cache[$n])) return $cache[$n];
        $t = 48;
        $im = imagecreatetruecolor($t, $t);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
        imagealphablending($im, true);
        imagefilledpolygon($im, [24, 1, 47, 24, 24, 47, 1, 24], imagecolorallocate($im, 220, 38, 38));
        $blanco = imagecolorallocate($im, 255, 255, 255);
        $f = __DIR__ . '/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
        $caja = imagettfbbox(15, 0, $f, (string)$n);
        $w = abs($caja[2] - $caja[0]);
        imagettftext($im, 15, 0, (int)(24 - $w / 2), 31, $blanco, $f, (string)$n);
        ob_start();
        imagepng($im);
        return $cache[$n] = 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    // Geometría de la hoja tabloide horizontal (cm): contenido de 41.18 de ancho; el plano usa la mitad
    private const ANCHO_PLANO_CM = 20.1;
    private const ALTO_HOJA_CM = 25.9;
    private const ANCHO_SCHEDULE_CM = 7.0;   // ancho de la simbología del schedule elegida en la lámina

    /**
     * Tamaño del plano en cm: ancho fijo a la mitad de la lámina y alto automático según la proporción.
     * La simbología y el SYMBOLOGY FOR SCHEDULE van fijos abajo (sobre el supervisor); si la imagen es muy
     * alta se reduce proporcionalmente para que todo quepa en la hoja (sin deformarla).
     * @return array{0:float,1:float}|null [ancho cm, alto cm]
     */
    public static function ajustarPlano(?string $archivo, ?string $simbologia, ?string $schedule = null): ?array
    {
        $ruta = $archivo ? Imagenes::CARPETA_VPLAN . '/' . basename($archivo) : null;
        $dim = $ruta && is_file($ruta) ? @getimagesize($ruta) : false;
        if (!$dim || $dim[0] < 1 || $dim[1] < 1) return null;

        // Altura que ocupa la fila de simbología (imagen de hasta 4.6 x 12 cm, o la leyenda como mínimo)
        $altoFila = 2.9;
        $sc = $schedule ? Imagenes::CARPETA_VPLAN . '/' . basename($schedule) : null;
        if ($sc && is_file($sc) && ($cd = @getimagesize($sc)) && $cd[0] > 0) $altoFila = min(4.6, self::ANCHO_SCHEDULE_CM * $cd[1] / $cd[0]);   // la simbología elegida marca el alto de la leyenda
        $rs = $simbologia ? Imagenes::CARPETA_VPLAN . '/' . basename($simbologia) : null;
        if ($rs && is_file($rs) && ($sd = @getimagesize($rs)) && $sd[0] > 0) {
            $altoFila = max($altoFila, min(4.6, 12 * $sd[1] / $sd[0]));
        }
        // hoja − encabezado (3.0) − pie y su separación (0.9) − fila fija de simbología − aire (0.4)
        $altoMax = self::ALTO_HOJA_CM - 3.0 - 0.9 - $altoFila - 0.4;
        $k = min(self::ANCHO_PLANO_CM / $dim[0], $altoMax / $dim[1]);
        return [round($dim[0] * $k, 2), round($dim[1] * $k, 2)];
    }

    private function nuevoDompdf(string $html, float $anchoMm, float $altoMm): Dompdf
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, $anchoMm * 72 / 25.4, $altoMm * 72 / 25.4]);
        return $dompdf;
    }

    private function contarPaginas(string $html, VplanLamina $l): int
    {
        $dompdf = $this->nuevoDompdf($html, (float)$l->ancho_mm, (float)$l->alto_mm);
        $dompdf->render();
        return $dompdf->getCanvas()->get_page_count();
    }

    /**
     * Las tablas se ajustan al espacio de su bloque con una estimación; se comprueba renderizando la lámina sola
     * y, si algo se sale de la hoja (más de una página), se baja la letra de todas las tablas un paso más.
     */
    private function elegirAjuste(array $d, \Closure $htmlDe): int
    {
        for ($extra = 0; $extra < 3; $extra++) {
            $d['extra'] = $extra;
            if ($this->contarPaginas($htmlDe([$d]), $d['lamina']) <= 1) return $extra;
        }
        return 2;
    }

    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        // Una lámina elegida se exporta aunque esté oculta; «todas» lleva solo las visibles, en el orden de los vPlan
        if ($this->lamina !== 'todas') {
            $laminas = array_values(array_filter(VplanLamina::deProyecto((int)$proyecto->id), fn($l) => (int)$l->id === (int)$this->lamina));
        } else {
            $laminas = VplanLamina::visiblesDeProyecto((int)$proyecto->id, $this->paquete ?: null);
        }
        if (!$laminas) throw new \Exception('No hay láminas para exportar');

        $logoC = MsrPdfExporter::imagenBase64($proyecto->contratista_logo, 'build/img/logos');
        $logoE = MsrPdfExporter::imagenBase64($proyecto->logoEmpresa(), 'build/img/logos');
        $htmlDe = function (array $paginas) use ($proyecto, $logoC, $logoE): string {
            ob_start();
            include __DIR__ . '/../../views/exportar/pdf-vplan.php';
            return ob_get_clean();
        };

        $paginas = [];
        foreach ($laminas as $l) {
            $d = Vplan::datos($l, $proyecto);
            $d['lamina'] = $l;
            $d['plano'] = Imagenes::vplanBase64($l->marcas_png ?: $l->plano, 2200);
            $d['simb'] = Imagenes::vplanBase64($l->simbologia, 1000);
            $d['simb_schedule'] = Imagenes::vplanBase64($l->imagenSimbologiaSchedule(), 1600);
            $d['extra'] = $this->elegirAjuste($d, $htmlDe);
            $paginas[] = $d;
        }

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        $tamanos = array_unique(array_map(fn($d) => $d['lamina']->ancho_mm . 'x' . $d['lamina']->alto_mm, $paginas));
        if (count($tamanos) === 1) {
            $dompdf = $this->nuevoDompdf($htmlDe($paginas), (float)$laminas[0]->ancho_mm, (float)$laminas[0]->alto_mm);
            $dompdf->render();
            file_put_contents($ruta, $dompdf->output());
            return $ruta;
        }

        // Láminas de tamaños distintos: un PDF por lámina y se unen en orden (un PDF no puede mezclar tamaños con Dompdf)
        $unir = trim((string)@shell_exec('command -v pdfunite'));
        if ($unir === '') throw new \Exception('Las láminas tienen tamaños de hoja distintos y el servidor no puede unirlas en un solo PDF. Exporta las láminas de una en una.');
        $partes = [];
        try {
            foreach ($paginas as $i => $d) {
                $dompdf = $this->nuevoDompdf($htmlDe([$d]), (float)$d['lamina']->ancho_mm, (float)$d['lamina']->alto_mm);
                $dompdf->render();
                $parte = $ruta . ".parte$i.pdf";
                file_put_contents($parte, $dompdf->output());
                $partes[] = $parte;
            }
            exec(escapeshellarg($unir) . ' ' . implode(' ', array_map('escapeshellarg', $partes)) . ' ' . escapeshellarg($ruta) . ' 2>&1', $salida, $codigo);
            if ($codigo !== 0 || !is_file($ruta)) throw new \Exception('No se pudieron unir las láminas en un solo PDF');
        } finally {
            foreach ($partes as $parte) @unlink($parte);
        }
        return $ruta;
    }
}
