<?php

namespace Classes\Exportadores;

use Controllers\LookaheadController;
use Classes\Pdf;

/** 4WLA en PDF tabloide (11x17") horizontal */
class LookaheadPdfExporter extends BaseExporter
{
    private \DateTime $lunes;

    public function setFechaReferencia(string $fechaYmd): self
    {
        $this->lunes = LookaheadController::lunesDe($fechaYmd);
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $lunes = $this->lunes ?? LookaheadController::lunesDe('');
        $datos = LookaheadController::datos($proyecto, $lunes);
        extract($datos);

        // Logos (empresa y contratista) en base64; webp se convierte a PNG
        $logoBase64 = MsrPdfExporter::imagenBase64($proyecto->logoEmpresa(), 'build/img/logos');
        $logoContratista = MsrPdfExporter::imagenBase64($proyecto->contratista_logo, 'build/img/logos');

        // Dompdf no soporta texto vertical: los rótulos de limitaciones se dibujan rotados como imagen
        $tiposImg = [];
        foreach ($tipos as $t) {
            $tiposImg[$t->id] = $this->textoVertical($t->nombre);
        }

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-lookahead.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'tabloid', 'landscape', ['isHtml5ParserEnabled' => true, 'marca' => $proyecto]);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }

    /**
     * PNG con el texto girado 90° (se lee de abajo hacia arriba, igual que en pantalla).
     * @return array{src:string, ancho:float, alto:float} medidas en pt
     */
    private function textoVertical(string $texto): array
    {
        $fuente = __DIR__ . '/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
        $escala = 8;                 // px por pt
        $tam = 5.6 * $escala * 0.75; // tamaño GD (pt a 96 dpi) equivalente a ~5.6pt
        $caja = imagettfbbox($tam, 0, $fuente, $texto);
        $anchoTxt = (int)ceil(abs($caja[2] - $caja[0]));
        $altoTxt = (int)ceil(abs($caja[7] - $caja[1]));
        $pad = 3 * $escala;

        $w = $anchoTxt + 2 * $pad;
        $h = $altoTxt + 2 * $pad;
        $img = imagecreatetruecolor($w, $h);
        $fondo = imagecolorallocate($img, 0x0b, 0x6f, 0xc0);
        imagefill($img, 0, 0, $fondo);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        imagettftext($img, $tam, 0, $pad - $caja[0], $pad - $caja[7], $blanco, $fuente, $texto);
        $rot = imagerotate($img, 90, $fondo);

        ob_start();
        imagepng($rot);
        $png = ob_get_clean();

        return [
            'src' => 'data:image/png;base64,' . base64_encode($png),
            'ancho' => imagesx($rot) / $escala,
            'alto' => imagesy($rot) / $escala,
        ];
    }
}
