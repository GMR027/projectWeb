<?php

namespace Classes;

/**
 * Tabla a partir de un documento, sin IA: Excel/CSV/ODS (celdas), Word (tablas), PDF con texto (columnas por alineación con
 * `pdftotext -layout`) e imágenes (solo si el servidor tiene `tesseract`). Devuelve una matriz de textos (la primera fila = encabezados)
 * para mostrarla en una vista previa editable; nada se guarda hasta que el usuario la acepta.
 */
class TablaDeDocumento
{
    public const EXTENSIONES = ['xlsx', 'xls', 'ods', 'csv', 'docx', 'pdf', 'png', 'jpg', 'jpeg', 'webp'];
    public const MAX_BYTES = 25_000_000;

    private const IMAGENES = ['png', 'jpg', 'jpeg', 'webp'];

    public static function ocrDisponible(): bool
    {
        return ShellTools::disponible('tesseract');
    }

    /**
     * @return array{filas: array<int, array<int, string>>, metodo: string, avisos: array<int, string>, texto: string, imagen: bool}
     * `texto` = texto plano del documento (lo usa la IA del escritorio); `imagen` = el motor no pudo leerlo y requiere OCR/IA.
     * @throws \RuntimeException con un mensaje para el usuario
     */
    public static function leer(string $ruta, string $nombreOriginal): array
    {
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) throw new \RuntimeException('Formato no admitido. Usa Excel, CSV, ODS, Word (.docx), PDF o una imagen.');
        $avisos = [];
        $texto = '';
        switch (true) {
            case in_array($ext, ['xlsx', 'xls', 'ods', 'csv'], true):
                $filas = self::deHoja($ruta, $ext, $avisos);
                $metodo = 'celdas';
                break;
            case $ext === 'docx':
                $filas = self::deWord($ruta, $avisos);
                $metodo = 'tabla de Word';
                break;
            case $ext === 'pdf':
                $texto = self::textoPdf($ruta);
                if (trim($texto) === '') {
                    return ['filas' => [], 'metodo' => 'pdf', 'avisos' => ['El PDF no tiene texto (parece escaneado): hace falta OCR o la IA del administrador (app de escritorio, con una imagen del documento).'], 'texto' => '', 'imagen' => true];
                }
                $filas = self::dePlano($texto, $avisos);
                $metodo = 'columnas del PDF';
                break;
            default:
                if (!self::ocrDisponible()) {
                    return ['filas' => [], 'metodo' => 'imagen', 'avisos' => ['Este servidor no tiene OCR: una imagen solo se puede leer con la IA del administrador (app de escritorio).'], 'texto' => '', 'imagen' => true];
                }
                $texto = self::textoImagen($ruta);
                if (trim($texto) === '') throw new \RuntimeException('No se pudo leer texto en la imagen.');
                $filas = self::dePlano($texto, $avisos);
                $avisos[] = 'Texto leído con OCR: revisa las celdas antes de crear la tabla.';
                $metodo = 'OCR';
        }
        if (!$filas) throw new \RuntimeException('No se encontró ninguna tabla en el documento.');
        return ['filas' => $filas, 'metodo' => $metodo, 'avisos' => $avisos, 'texto' => mb_substr($texto, 0, 60000), 'imagen' => false];
    }

    // ---------- Excel / CSV / ODS ----------

    private static function deHoja(string $ruta, string $ext, array &$avisos): array
    {
        @ini_set('memory_limit', '512M');
        $tipo = $ext === 'xls' ? 'Xls' : ucfirst($ext);
        $lector = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($tipo);
        if ($ext === 'csv') {
            $muestra = (string)file_get_contents($ruta, false, null, 0, 20000);
            $lector->setInputEncoding(mb_check_encoding($muestra, 'UTF-8') ? 'UTF-8' : 'Windows-1252');
        }
        $libro = $lector->load($ruta);
        if ($libro->getSheetCount() > 1) $avisos[] = 'El archivo tiene ' . $libro->getSheetCount() . ' hojas; se usó «' . $libro->getSheet(0)->getTitle() . '».';
        $filas = [];
        foreach ($libro->getSheet(0)->toArray(null, true, true, false) as $f) {
            $f = array_map(fn($v) => trim((string)$v), $f);
            if (array_filter($f, fn($v) => $v !== '')) $filas[] = $f;
            if (count($filas) > \Classes\Sheetflow::MAX_FILAS + 1) { $avisos[] = 'Se leyeron solo los primeros ' . \Classes\Sheetflow::MAX_FILAS . ' renglones.'; break; }
        }
        return $filas;
    }

    // ---------- Word ----------

    private static function deWord(string $ruta, array &$avisos): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) throw new \RuntimeException('No se pudo abrir el documento de Word.');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) throw new \RuntimeException('El documento de Word no tiene contenido.');
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) throw new \RuntimeException('No se pudo leer el documento de Word.');
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $mejor = [];
        $n = 0;
        foreach ($xp->query('//w:body/w:tbl') as $tbl) {
            $n++;
            $filas = [];
            foreach ($xp->query('./w:tr', $tbl) as $tr) {
                $fila = [];
                foreach ($xp->query('./w:tc', $tr) as $tc) {
                    $partes = [];
                    foreach ($xp->query('.//w:p', $tc) as $p) $partes[] = trim($xp->evaluate('string(.)', $p));
                    $fila[] = trim(implode(' ', array_filter($partes, fn($x) => $x !== '')));
                }
                if (array_filter($fila, fn($v) => $v !== '')) $filas[] = $fila;
            }
            if (count($filas) * max(1, count($filas[0] ?? [])) > count($mejor) * max(1, count($mejor[0] ?? []))) $mejor = $filas;
        }
        if ($n > 1) $avisos[] = "El documento tiene $n tablas; se usó la más grande.";
        return $mejor;
    }

    // ---------- PDF / imagen: texto con la posición de las columnas ----------

    public static function textoPdf(string $ruta): string
    {
        return self::correr(['pdftotext', '-layout', '-enc', 'UTF-8', $ruta, '-'], 60);
    }

    private static function textoImagen(string $ruta): string
    {
        $idiomas = str_contains(self::correr(['tesseract', '--list-langs'], 10), 'spa') ? 'spa+eng' : 'eng';
        return self::correr(['tesseract', $ruta, 'stdout', '-l', $idiomas, '--psm', '6', '-c', 'preserve_interword_spaces=1'], 90);
    }

    private static function correr(array $cmd, int $segundos): string
    {
        $r = ShellTools::ejecutar($cmd, $segundos);
        if ($r === null) throw new \RuntimeException('No se pudo ejecutar ' . $cmd[0] . ' en el servidor.');
        if ($r['agotado']) throw new \RuntimeException('El documento tardó demasiado en leerse.');
        return $r['salida'];
    }

    /**
     * Busca la tabla más grande en un texto con columnas alineadas y la separa por los huecos verticales que comparten sus renglones.
     * @return array<int, array<int, string>>
     */
    public static function dePlano(string $texto, array &$avisos = []): array
    {
        $lineas = [];
        foreach (preg_split('/\r?\n|\f/', str_replace("\t", '    ', $texto)) as $l) $lineas[] = rtrim($l);
        $segmentos = fn(string $l) => count(preg_split('/\s{2,}/u', trim($l), -1, PREG_SPLIT_NO_EMPTY));

        // Bloques: renglones con ≥2 segmentos; se toleran renglones de continuación (1 segmento) y un hueco en blanco aislado
        $bloques = [];
        $actual = [];
        $blancos = 0;
        $cerrar = function () use (&$bloques, &$actual, &$blancos, $segmentos) {
            while ($actual && $segmentos(end($actual)) < 2) array_pop($actual);
            if (count(array_filter($actual, fn($l) => $segmentos($l) >= 2)) >= 3) $bloques[] = $actual;
            $actual = [];
            $blancos = 0;
        };
        $n = count($lineas);
        for ($i = 0; $i < $n; $i++) {
            $l = $lineas[$i];
            if (trim($l) === '') {
                if (!$actual) continue;
                $blancos++;
                $sig = $lineas[$i + 1] ?? '';
                if ($blancos > 1 || $segmentos($sig) < 2) { $cerrar(); }
                continue;
            }
            $blancos = 0;
            if (!$actual && $segmentos($l) < 2) continue;   // un bloque empieza en un renglón con columnas (los títulos sueltos no cuentan)
            $actual[] = $l;
        }
        $cerrar();
        if (!$bloques) return [];
        usort($bloques, fn($a, $b) => self::peso($b, $segmentos) <=> self::peso($a, $segmentos));
        if (count($bloques) > 1) $avisos[] = 'Se encontraron ' . count($bloques) . ' bloques con forma de tabla; se usó el más grande.';
        return self::columnas($bloques[0]);
    }

    private static function peso(array $bloque, callable $segmentos): int
    {
        $p = 0;
        foreach ($bloque as $l) $p += max(0, $segmentos($l) - 1);
        return $p;
    }

    /** @return array<int, array<int, string>> */
    private static function columnas(array $bloque): array
    {
        $ancho = max(array_map(fn($l) => mb_strlen($l), $bloque));
        $n = count($bloque);
        $ocupa = array_fill(0, $ancho + 1, 0);
        $chars = [];
        foreach ($bloque as $k => $l) {
            $chars[$k] = preg_split('//u', $l, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($chars[$k] as $p => $c) if ($c !== ' ') $ocupa[$p]++;
        }
        $tolera = (int)floor($n * 0.06);   // una palabra larga que invade el hueco no debe unir dos columnas
        // Huecos: tramos de ≥2 posiciones casi vacías entre texto
        $cortes = [];
        $p = 0;
        $primero = null;
        for ($i = 0; $i <= $ancho; $i++) if ($ocupa[$i] > 0) { $primero = $i; break; }
        $i = (int)$primero;
        while ($i <= $ancho) {
            if ($ocupa[$i] <= $tolera) {
                $j = $i;
                while ($j <= $ancho && $ocupa[$j] <= $tolera) $j++;
                if ($j - $i >= 2 && $j <= $ancho) $cortes[] = [$i, $j];   // [inicio, fin) del hueco
                $i = $j;
            } else {
                $i++;
            }
        }
        // Rangos de columna = lo que queda entre huecos
        $rangos = [];
        $desde = (int)$primero;
        foreach ($cortes as [$a, $b]) {
            if ($a > $desde) $rangos[] = [$desde, $a];
            $desde = $b;
        }
        $rangos[] = [$desde, $ancho + 1];
        if (count($rangos) < 2) return [];

        $filas = [];
        foreach ($chars as $c) {
            $fila = [];
            foreach ($rangos as [$a, $b]) $fila[] = trim(implode('', array_slice($c, $a, $b - $a)));
            $filas[] = $fila;
        }
        // Renglones de continuación (primera celda vacía) se unen al anterior; un encabezado repetido en otra página se descarta
        $nc = count($rangos);
        $unidas = [];
        foreach ($filas as $k => $f) {
            if (!array_filter($f, fn($v) => $v !== '')) continue;
            if ($unidas && $k > 0 && $f[0] === '' && count(array_filter($f, fn($v) => $v !== '')) < $nc) {
                $prev = array_key_last($unidas);
                foreach ($f as $c => $v) if ($v !== '') $unidas[$prev][$c] = trim($unidas[$prev][$c] . ' ' . $v);
                continue;
            }
            if ($unidas && $k > 0 && $f === $unidas[array_key_first($unidas)]) continue;
            $unidas[] = $f;
        }
        return array_values($unidas);
    }
}
