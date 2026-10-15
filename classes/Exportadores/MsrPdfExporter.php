<?php

namespace Classes\Exportadores;

use Controllers\MsrController;
use Dompdf\Dompdf;
use Dompdf\Options;

/** MSR en PDF A4 horizontal */
class MsrPdfExporter extends BaseExporter
{
    private ?\Model\Msr $msr = null;

    public function setMsr(?\Model\Msr $msr): self
    {
        $this->msr = $msr;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $datos = MsrController::datos($this->proyecto, $this->msr);
        $datos['img'] = fn(?string $archivo, string $carpeta) => self::imagenBase64($archivo, $carpeta);
        $datos['fechaReporte'] = date('d/m/Y');
        extract($datos);

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-msr.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $dompdf->output());
        return $ruta;
    }

    /** data URI (webp se convierte a PNG porque Dompdf no lo lee) */
    public static function imagenBase64(?string $archivo, string $carpeta): ?string
    {
        if (!$archivo) return null;
        $ruta = __DIR__ . '/../../public/' . $carpeta . '/' . basename($archivo);
        if (!is_file($ruta)) return null;
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $im = imagecreatefromwebp($ruta);
            ob_start();
            imagepng($im);
            return 'data:image/png;base64,' . base64_encode(ob_get_clean());
        }
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)) return null;
        return 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($ruta));
    }
}
