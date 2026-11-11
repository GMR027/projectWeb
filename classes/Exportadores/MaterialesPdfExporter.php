<?php

namespace Classes\Exportadores;

use Classes\MaterialesCatalogo;
use Classes\Pdf;

/** Catálogo de materiales en PDF A4 horizontal, agrupado por especialidad y con la imagen de cada material */
class MaterialesPdfExporter extends BaseExporter
{
    private array $materiales = [];
    private array $filtros = [];

    public function setMateriales(array $materiales): self
    {
        $this->materiales = $materiales;
        return $this;
    }

    public function setFiltros(array $filtros): self
    {
        $this->filtros = $filtros;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $secciones = MaterialesCatalogo::secciones($this->materiales);
        $proyecto = $this->proyecto;
        $filtros = $this->filtros;
        $total = count($this->materiales);
        $img = fn(?string $archivo) => self::imagenBase64($archivo);

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-materiales.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'a4', 'landscape', ['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }

    /** data URI de la miniatura (webp se convierte a PNG porque Dompdf no lo lee) */
    public static function imagenBase64(?string $archivo): ?string
    {
        if (!$archivo) return null;
        $ruta = __DIR__ . '/../../public/uploads/materiales/' . basename($archivo);
        if (!is_file($ruta)) return null;
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $im = imagecreatefromwebp($ruta);
            if (!$im) return null;
            ob_start();
            imagepng($im);
            return 'data:image/png;base64,' . base64_encode(ob_get_clean());
        }
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)) return null;
        return 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($ruta));
    }
}
