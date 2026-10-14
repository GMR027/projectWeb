<?php

namespace Classes\Exportadores;

use Model\VplanLamina;
use Classes\Imagenes;
use Classes\Vplan;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Láminas de vPlan en PDF tabloide horizontal (una lámina por página) */
class VplanPdfExporter extends BaseExporter
{
    private string $lamina = 'todas';

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

    /**
     * Tamaño del plano en cm: ancho fijo a la mitad de la lámina y alto automático según la proporción.
     * La simbología y el SYMBOLOGY FOR SCHEDULE van fijos abajo (sobre el supervisor); si la imagen es muy
     * alta se reduce proporcionalmente para que todo quepa en la hoja (sin deformarla).
     * @return array{0:float,1:float}|null [ancho cm, alto cm]
     */
    public static function ajustarPlano(?string $archivo, ?string $simbologia): ?array
    {
        $ruta = $archivo ? Imagenes::CARPETA_VPLAN . '/' . basename($archivo) : null;
        $dim = $ruta && is_file($ruta) ? @getimagesize($ruta) : false;
        if (!$dim || $dim[0] < 1 || $dim[1] < 1) return null;

        // Altura que ocupa la fila de simbología (imagen de hasta 4.6 x 12 cm, o la leyenda como mínimo)
        $altoFila = 2.9;
        $rs = $simbologia ? Imagenes::CARPETA_VPLAN . '/' . basename($simbologia) : null;
        if ($rs && is_file($rs) && ($sd = @getimagesize($rs)) && $sd[0] > 0) {
            $altoFila = max($altoFila, min(4.6, 12 * $sd[1] / $sd[0]));
        }
        // hoja − encabezado (3.0) − pie y su separación (0.9) − fila fija de simbología − aire (0.4)
        $altoMax = self::ALTO_HOJA_CM - 3.0 - 0.9 - $altoFila - 0.4;
        $k = min(self::ANCHO_PLANO_CM / $dim[0], $altoMax / $dim[1]);
        return [round($dim[0] * $k, 2), round($dim[1] * $k, 2)];
    }

    // Escalas de la columna derecha: [clase CSS, tamaño de letra pt, relleno vertical de celda pt]
    private const ESCALAS = [['e0', 9.0, 3.0], ['e1', 7.5, 2.0], ['e2', 6.2, 1.5], ['e3', 5.2, 0.9], ['e4', 4.4, 0.5]];
    private const ALTO_COLUMNA_PT = 612;   // ≈ 21.6 cm: hoja − encabezado − pie

    /** Alto estimado (pt) de la columna derecha con una escala dada */
    private static function estimarAlto(array $d, float $f, float $p): float
    {
        $ancho = 20.5;                                                  // cm de la mitad derecha
        $cpl = fn(float $cm) => max(8, (int)floor($cm / (0.55 * $f * 0.03528)));   // caracteres por línea
        $fila = fn(int $lineas) => $lineas * 1.25 * $f + 2 * $p + 0.8;
        $lin = fn(string $t, float $cm) => max(1, (int)ceil(mb_strlen($t) / $cpl($cm)));

        $nRec = (int)$d['rec1'] + (int)$d['rec2'];
        $anchoTarea = max(3.5, $ancho - 1.0 - 3.8 - 3.8 * $nRec - 1.2 - 2.4);
        $h = $fila(1) * 3;                                              // título + 2 filas de encabezado
        foreach ($d['tareas'] as $t) $h += $fila($lin((string)$t['actividad']->nombre, $anchoTarea));
        $h += 7;
        foreach ($d['tablas'] as $b) {
            $tipo = $b['tabla']->tipo;
            $h += $fila(1) * ($tipo === 'restricciones' ? 1 : ($tipo === 'punch' ? 3 : 2));
            foreach ($b['filas'] as $f2) {
                $texto = (string)($f2['descripcion'] ?? $f2['elemento'] ?? '');
                $h += $fila($lin($texto, $tipo === 'punch' ? 13.5 : ($tipo === 'restricciones' ? 18.5 : ($tipo === 'secundarias' ? 7.5 : 5.5))));
            }
            $h += 7;
        }
        return $h;
    }

    /**
     * Elige la letra más grande con la que la lámina cabe en una sola hoja. Parte de una estimación y lo
     * comprueba renderizando la lámina sola (el estimador por sí mismo se queda corto con textos largos).
     */
    private function elegirEscala(array $d, \Closure $htmlDe): string
    {
        $inicio = 0;
        foreach (self::ESCALAS as $i => [$clase, $f, $p]) {
            if (self::estimarAlto($d, $f, $p) <= self::ALTO_COLUMNA_PT) { $inicio = $i; break; }
            $inicio = count(self::ESCALAS) - 1;
        }
        for ($i = $inicio; $i < count(self::ESCALAS); $i++) {
            $d['escala'] = self::ESCALAS[$i][0];
            if ($this->contarPaginas($htmlDe([$d])) <= 1) return $d['escala'];
        }
        return 'e4';
    }

    private function nuevoDompdf(string $html): Dompdf
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('tabloid', 'landscape');
        return $dompdf;
    }

    private function contarPaginas(string $html): int
    {
        $dompdf = $this->nuevoDompdf($html);
        $dompdf->render();
        return $dompdf->getCanvas()->get_page_count();
    }

    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $laminas = VplanLamina::deProyecto((int)$proyecto->id);
        if ($this->lamina !== 'todas') {
            $laminas = array_values(array_filter($laminas, fn($l) => (int)$l->id === (int)$this->lamina));
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
            $d['plano_dim'] = self::ajustarPlano($l->marcas_png ?: $l->plano, $l->simbologia);
            // Letra más grande con la que la lámina completa cabe en una hoja
            $d['escala'] = $this->elegirEscala($d, $htmlDe);
            $paginas[] = $d;
        }

        $dompdf = $this->nuevoDompdf($htmlDe($paginas));
        $dompdf->render();

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $dompdf->output());
        return $ruta;
    }
}
