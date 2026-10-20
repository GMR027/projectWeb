<?php

namespace Classes;

use Model\Material;
use Model\MsrItem;
use Model\Proyecto;
use Model\Submittal;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

/** Importa un MSR en .xlsx: analiza a una carpeta temporal (vista previa) y luego aplica */
class MsrImportador
{
    public const BASE = __DIR__ . '/../storage/import';
    private const MESES = ['ene' => 1, 'jan' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'aug' => 8, 'sep' => 9, 'set' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12, 'dec' => 12];

    /** Analiza el archivo y guarda data.json + imágenes; devuelve token y resumen */
    public static function analizar(string $archivo, int $proyectoId): array
    {
        $libro = IOFactory::load($archivo);
        $hoja = $libro->getActiveSheet();
        $maxFila = min($hoja->getHighestDataRow(), 2000);

        // Fila de encabezados: la que contiene "Spec Number" en la columna C
        $cab = null;
        for ($r = 1; $r <= 60; $r++) {
            if (stripos((string)$hoja->getCell([3, $r])->getValue(), 'spec number') !== false) { $cab = $r; break; }
        }
        if (!$cab) {
            throw new \Exception('No se reconoce el formato: no se encontró la fila de encabezados (columna "Spec Number").');
        }

        $token = bin2hex(random_bytes(12));
        $dir = self::BASE . '/' . $token;
        mkdir($dir . '/img', 0775, true);

        // Imágenes por fila (columna A)
        $imagenes = [];
        $n = 0;
        foreach ($hoja->getDrawingCollection() as $d) {
            [$col, $fila] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($d->getCoordinates());
            if ($col !== 'A') continue;
            $archivoImg = $dir . '/img/' . (++$n) . '.png';
            if ($d instanceof MemoryDrawing) {
                ob_start();
                imagepng($d->getImageResource());
                file_put_contents($archivoImg, ob_get_clean());
            } else {
                $bin = @file_get_contents($d->getPath());
                if ($bin === false) continue;
                file_put_contents($archivoImg, $bin);
            }
            $imagenes[(int)$fila][] = basename($archivoImg);
        }

        $filas = [];
        $avisos = [];
        $seccion = '';
        $ultimoSub = '';
        $texto = fn($c, $r) => trim((string)$hoja->getCell([$c, $r])->getFormattedValue());
        for ($r = $cab + 1; $r <= $maxFila; $r++) {
            $desc = $texto(4, $r);
            if ($desc === '') continue;
            $otros = false;
            foreach ([2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17] as $c) {
                if (trim((string)$hoja->getCell([$c, $r])->getValue()) !== '') { $otros = true; break; }
            }
            if (!$otros) { $seccion = $desc; continue; }

            $sub = self::normalizarCodigo($texto(2, $r));
            if ($sub === '') {
                $sub = $ultimoSub ?: 'SIN-SUBMITTAL';
                $avisos[] = "Fila $r: sin número de submittal, se asignó a «{$sub}».";
            }
            $ultimoSub = $sub;
            $st = strtoupper($texto(9, $r));
            $st = in_array($st, ['A', 'AN', 'P', 'RA'], true) ? $st : 'P';
            $img = null;
            // La foto puede estar anclada en la fila o hasta 2 filas antes
            foreach ([$r, $r - 1, $r - 2] as $fr) {
                if (!empty($imagenes[$fr])) { $img = array_shift($imagenes[$fr]); break; }
            }
            $fecha = fn($c) => self::fecha($hoja->getCell([$c, $r])->getValue());
            $filas[] = [
                'fila' => $r, 'seccion' => $seccion, 'submittal' => $sub, 'referencia' => $texto(3, $r), 'nombre' => $desc,
                'fecha_envio_requerida' => $fecha(5), 'no_submittal_contratista' => $texto(6, $r),
                'fecha_envio_real' => $fecha(7), 'fecha_respuesta' => $fecha(8), 'status' => $st,
                'fecha_compra_prog' => $fecha(11), 'fecha_instalacion_prog' => $fecha(12), 'fecha_compra_real' => $fecha(13),
                'fecha_entrega_prog' => $fecha(14), 'fecha_entrega_real' => $fecha(15),
                'proveedor' => $texto(16, $r), 'observaciones' => $texto(17, $r), 'imagen' => $img,
            ];
        }
        file_put_contents($dir . '/data.json', json_encode(['proyecto' => $proyectoId, 'filas' => $filas], JSON_UNESCAPED_UNICODE));

        $subs = array_unique(array_column($filas, 'submittal'));
        $claves = array_unique(array_map(fn($f) => mb_strtolower($f['nombre'] . '|' . $f['referencia']), $filas));
        return [
            'token' => $token, 'filas' => $filas, 'avisos' => $avisos,
            'resumen' => ['renglones' => count($filas), 'submittals' => count($subs), 'materiales' => count($claves),
                'imagenes' => count(array_filter(array_column($filas, 'imagen')))],
        ];
    }

    /** Títulos de las partidas que ya tiene el MSR (las propias y las que vienen de sus renglones) */
    public static function partidasDe(int $msrId): array
    {
        $db = MsrItem::getDB();
        $out = [];
        foreach (["SELECT titulo FROM msr_partidas WHERE msr_id = $msrId ORDER BY orden", "SELECT DISTINCT seccion FROM msr_items WHERE msr_id = $msrId AND seccion <> ''"] as $q) {
            $r = $db->query($q);
            while ($r && ($f = $r->fetch_row())) $out[$f[0]] = $f[0];
        }
        return array_values($out);
    }

    /** Partidas del Excel que no existen en el MSR (candidatas a unirse a una existente) */
    public static function partidasNuevas(array $filas, int $msrId): array
    {
        $ya = array_flip(self::partidasDe($msrId));
        $nuevas = [];
        foreach ($filas as $f) if ($f['seccion'] !== '' && !isset($ya[$f['seccion']])) $nuevas[$f['seccion']] = true;
        return array_keys($nuevas);
    }

    /** Carga lo analizado (vista previa) */
    public static function cargar(string $token, int $proyectoId): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) return null;
        $json = @file_get_contents(self::BASE . "/$token/data.json");
        $d = $json ? json_decode($json, true) : null;
        return $d && (int)$d['proyecto'] === $proyectoId ? $d : null;
    }

    /** Crea materiales, paquetes y renglones del MSR a partir de lo analizado */
    public static function aplicar(string $token, Proyecto $proyecto, int $msrId, array $union = []): array
    {
        $d = self::cargar($token, (int)$proyecto->id);
        if (!$d) throw new \Exception('La importación expiró o no es válida. Sube el archivo de nuevo.');
        $pid = (int)$proyecto->id;
        $dir = self::BASE . "/$token";
        $cont = ['materiales' => 0, 'submittals' => 0, 'renglones' => 0, 'omitidos' => 0];
        $paquetes = [];
        $orden = 0;

        // Partidas del Excel que el usuario eligió unir a una ya existente del MSR (['Nombre en Excel' => 'Partida existente'])
        $permitidas = array_flip(self::partidasDe($msrId));
        $union = array_filter($union, fn($a, $de) => $a !== '' && $a !== $de && isset($permitidas[$a]), ARRAY_FILTER_USE_BOTH);

        foreach ($d['filas'] as $f) {
            if (isset($union[$f['seccion']])) $f['seccion'] = $union[$f['seccion']];
            $material = Material::buscar($pid, $f['nombre'], $f['referencia']);
            if (!$material) {
                $material = new Material(['proyecto_id' => $pid, 'nombre' => $f['nombre'], 'referencia' => $f['referencia'],
                    'especialidad' => \Model\Especialidad::resolver($pid, $f['seccion'])]);
                self::fechasMaterial($material, $f);
                if ($f['imagen'] && is_file("$dir/img/" . basename($f['imagen']))) {
                    $material->imagen = Imagenes::miniatura("$dir/img/" . basename($f['imagen']));
                }
                $material->id = $material->guardar()['id'];
                $cont['materiales']++;
            } else {
                $cambio = false;
                if (!$material->imagen && $f['imagen'] && is_file("$dir/img/" . basename($f['imagen']))) {
                    $material->imagen = Imagenes::miniatura("$dir/img/" . basename($f['imagen']));
                    $cambio = true;
                }
                if (!$material->especialidad && $f['seccion']) {
                    $material->especialidad = \Model\Especialidad::resolver($pid, $f['seccion']);
                    $cambio = true;
                }
                $cambio = self::fechasMaterial($material, $f) || $cambio;
                if ($cambio) $material->guardar();
            }

            if (!isset($paquetes[$f['submittal']])) {
                $sub = Submittal::buscarPorNumero($pid, $f['submittal']);
                if (!$sub) {
                    $sub = new Submittal([
                        'proyecto_id' => $pid, 'msr_id' => $msrId, 'numero_trabajo' => $proyecto->numero_trabajo ?? '',
                        'numero_submittal' => $f['submittal'], 'especialidad' => \Model\Especialidad::resolver($pid, $f['seccion']),
                        'estatus' => ['A' => 'aprobado', 'AN' => 'aprobado_an', 'RA' => 'rechazado'][$f['status']] ?? 'enviado', 'fecha_respuesta' => $f['fecha_respuesta'],
                        'fecha' => $f['fecha_envio_real'] ?: ($f['fecha_envio_requerida'] ?: date('Y-m-d')),
                    ]);
                    $sub->id = $sub->guardar()['id'];
                    $cont['submittals']++;
                }
                $paquetes[$f['submittal']] = $sub;
            }
            $sub = $paquetes[$f['submittal']];

            $existente = MsrItem::consultarSQL("SELECT * FROM msr_items WHERE submittal_id = " . (int)$sub->id . " AND material_id = " . (int)$material->id . " LIMIT 1")[0] ?? null;
            if ($existente) { $cont['omitidos']++; continue; }

            $item = new MsrItem(['proyecto_id' => $pid, 'msr_id' => $sub->msr_id ?: $msrId, 'submittal_id' => $sub->id, 'material_id' => $material->id, 'orden' => ++$orden + 100000]);
            foreach (['seccion', 'fecha_envio_requerida', 'no_submittal_contratista', 'fecha_envio_real', 'fecha_respuesta', 'status',
                      'fecha_compra_prog', 'fecha_instalacion_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real',
                      'proveedor', 'observaciones'] as $c) {
                $item->$c = $f[$c] === '' ? ($c === 'seccion' || $c === 'proveedor' || $c === 'observaciones' || $c === 'no_submittal_contratista' ? '' : null) : $f[$c];
            }
            $item->guardar();
            \Model\Submittal::vincular((int)$sub->id, (int)$material->id);
            $cont['renglones']++;
        }
        self::limpiar($token);
        return $cont;
    }

    /** Llena (solo vacíos) las fechas de compra y entrega del material con las del renglón; true si cambió algo */
    private static function fechasMaterial(Material $m, array $f): bool
    {
        $cambio = false;
        foreach (['fecha_compra_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real'] as $c) {
            if (!$m->$c && !empty($f[$c])) { $m->$c = $f[$c]; $cambio = true; }
        }
        if ($m->fecha_compra_real && $m->compra !== 'comprado') { $m->compra = 'comprado'; $cambio = true; }
        return $cambio;
    }

    public static function limpiar(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) return;
        $dir = self::BASE . "/$token";
        foreach (glob("$dir/img/*") ?: [] as $f) @unlink($f);
        @rmdir("$dir/img");
        @unlink("$dir/data.json");
        @rmdir($dir);
    }

    /** Unifica guiones raros y espacios: "ALT—GRX-003" => "ALT-GRX-003" */
    public static function normalizarCodigo(string $c): string
    {
        $c = str_replace(["\u{2014}", "\u{2013}", "\u{2212}"], '-', $c);
        return trim(preg_replace('/\s+/', ' ', $c));
    }

    /** Fecha de celda (serial Excel, Y-m-d o "06-may.-26" / "05-May-26") a Y-m-d o null */
    public static function fecha($valor): ?string
    {
        if ($valor === null || $valor === '') return null;
        if (is_numeric($valor)) {
            return $valor > 20000 ? Date::excelToDateTimeObject((float)$valor)->format('Y-m-d') : null;
        }
        $t = trim((string)$valor);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $t, $m)) return "$m[1]-$m[2]-$m[3]";
        if (preg_match('/^(\d{1,2})[-\/ ]+([A-Za-zñÑ]{3})[A-Za-z]*\.?[-\/ ]+(\d{2,4})$/u', $t, $m)) {
            $mes = self::MESES[strtolower($m[2])] ?? null;
            $anio = (int)$m[3] < 100 ? 2000 + (int)$m[3] : (int)$m[3];
            return $mes ? sprintf('%04d-%02d-%02d', $anio, $mes, (int)$m[1]) : null;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $t, $m)) {
            $anio = (int)$m[3] < 100 ? 2000 + (int)$m[3] : (int)$m[3];
            return sprintf('%04d-%02d-%02d', $anio, (int)$m[2], (int)$m[1]);
        }
        return null;
    }
}
