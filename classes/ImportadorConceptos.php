<?php

namespace Classes;

use Model\Partida;

/**
 * Importa un catálogo de conceptos desde CSV, XLSX u ODS.
 * Flujo: leer → proponer mapeo de columnas → vista previa → aplicar (transacción).
 * Si falta una partida se crea; si la clave ya existe se actualiza sin perder sus vínculos.
 */
class ImportadorConceptos
{
    public const CAMPOS = [
        'partida' => 'Partida / capítulo', 'clave' => 'Clave', 'descripcion' => 'Descripción',
        'unidad' => 'Unidad', 'cantidad' => 'Cantidad', 'precio_unitario' => 'Precio unitario', 'importe' => 'Importe',
    ];

    /** @return array{filas: array<int,array<int,string>>, encabezados: array<int,string>, avisos: array<int,string>} */
    public static function leer(string $ruta, string $ext): array
    {
        $avisos = [];
        $filas = TablaDeDocumento::deHoja($ruta, $ext, $avisos);
        if (!$filas) throw new \RuntimeException('El archivo no tiene datos');
        $encabezados = array_map(fn($h) => trim((string)$h), array_shift($filas));
        if (!array_filter($encabezados, fn($h) => $h !== '')) throw new \RuntimeException('La primera fila debe tener los encabezados de las columnas');
        return ['filas' => $filas, 'encabezados' => $encabezados, 'avisos' => $avisos];
    }

    /** Propone el índice de columna para cada campo, sin repetir columnas */
    public static function mapear(array $encabezados): array
    {
        $norm = fn($s) => preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower(trim((string)$s)), 'áéíóúüñ', 'aeiouun'));
        $columnas = array_map($norm, $encabezados);
        $reglas = [
            'partida' => ['partida', 'capitulo', 'wbs'],
            'clave' => ['clave', 'codigo', 'item', 'num', 'numero'],
            'descripcion' => ['descripcion', 'concepto', 'nombre'],
            'unidad' => ['unidad', 'um', 'unidadmedida'],
            'cantidad' => ['cantidad', 'cant'],
            'precio_unitario' => ['preciounitario', 'precio', 'pu', 'unitario'],
            'importe' => ['importe', 'total', 'monto'],
        ];
        $map = [];
        $usados = [];
        foreach ($reglas as $campo => $pistas) {
            foreach ($columnas as $i => $col) {
                if ($col === '' || isset($usados[$i])) continue;
                foreach ($pistas as $p) {
                    if ($col === $p || str_contains($col, $p)) { $map[$campo] = (string)$i; $usados[$i] = true; continue 3; }
                }
            }
        }
        return $map;
    }

    /**
     * Normaliza los renglones con el mapeo elegido.
     * @return array{filas: array<int,array>, validas:int, rechazos: array<int,string>}
     */
    public static function analizar(array $filas, array $map): array
    {
        $out = [];
        $rechazos = [];
        $num = 1;
        foreach ($filas as $f) {
            $num++;
            $get = fn(string $campo) => ((string)($map[$campo] ?? '')) !== '' ? trim((string)($f[(int)$map[$campo]] ?? '')) : '';
            $clave = mb_substr($get('clave'), 0, 60);
            $desc = mb_substr($get('descripcion'), 0, 255);
            if ($desc === '' && $clave === '') continue;
            $cant = self::numero($get('cantidad'));
            $precio = self::numero($get('precio_unitario'));
            $importe = self::numero($get('importe'));
            if ($precio === null && $importe !== null && $cant !== null && $cant > 0) $precio = round($importe / $cant, 2);
            $errores = [];
            if ($desc === '') $errores[] = 'sin descripción';
            if ($cant !== null && $cant < 0) $errores[] = 'cantidad negativa';
            if ($precio !== null && $precio < 0) $errores[] = 'precio negativo';
            $out[] = [
                'fila' => $num, 'partida' => $get('partida'), 'clave' => $clave, 'descripcion' => $desc,
                'unidad' => mb_substr($get('unidad'), 0, 30), 'cantidad' => $cant, 'precio_unitario' => $precio,
                'importe' => $importe, 'errores' => $errores,
            ];
            if ($errores) $rechazos[] = 'Fila ' . $num . ': ' . implode(', ', $errores);
        }
        $validas = count(array_filter($out, fn($r) => !$r['errores']));
        return ['filas' => $out, 'validas' => $validas, 'rechazos' => $rechazos];
    }

    /**
     * Aplica el catálogo (transacción): crea partidas faltantes y actualiza conceptos por clave.
     * @return array{creados:int,actualizados:int,partidas:int,rechazos:array<int,string>,avisos:array<int,string>}
     */
    public static function aplicar(int $pid, array $filas, array $map): array
    {
        if (!Conceptos::disponible()) throw new \RuntimeException('Falta aplicar database/migracion_catalogo_conceptos.sql');
        $db = $GLOBALS['db'];
        $analisis = self::analizar($filas, $map);
        $res = ['creados' => 0, 'actualizados' => 0, 'partidas' => 0, 'rechazos' => [], 'avisos' => []];
        $partidas = [];
        foreach (Partida::whereAll('proyecto_id', $pid, 'orden, codigo') as $p) {
            self::cachePartida($partidas, $p);
        }
        $db->begin_transaction();
        try {
            foreach ($analisis['filas'] as $r) {
                if ($r['errores']) { $res['rechazos'][] = 'Fila ' . $r['fila'] . ': ' . implode(', ', $r['errores']); continue; }
                try {
                    $partida = self::resolverPartida($pid, $r['partida'], $partidas, $res);
                    $existente = self::buscar($pid, $r, $partida);
                    $datos = [
                        'partida_id' => $partida ? (int)$partida->id : 0,
                        'clave' => $r['clave'], 'descripcion' => $r['descripcion'], 'unidad' => $r['unidad'],
                        'cantidad' => $r['cantidad'] ?? ($existente['cantidad'] ?? 0),
                        'precio_unitario' => $r['precio_unitario'] ?? ($existente['precio_unitario'] ?? 0),
                        'origen' => 'Importado',
                    ];
                    if ($existente) {
                        $datos['id'] = (int)$existente['id'];
                        $datos['origen'] = (string)$existente['origen'];
                        [$ok, $msg] = Conceptos::guardar($pid, $datos);
                        if (!$ok) throw new \RuntimeException($msg);
                        $res['actualizados']++;
                        $programado = (float)(self::fila("SELECT COALESCE(SUM(cantidad), 0) n FROM actividad_conceptos WHERE concepto_id = " . (int)$existente['id'])[0]['n'] ?? 0);
                        if ((float)$datos['cantidad'] + 0.0005 < $programado) {
                            $res['avisos'][] = 'Fila ' . $r['fila'] . ': la cantidad del catálogo (' . $datos['cantidad'] . ') es menor a lo ya programado (' . $programado . ') en «' . $r['descripcion'] . '».';
                        }
                    } else {
                        [$ok, $msg] = Conceptos::guardar($pid, $datos);
                        if (!$ok) throw new \RuntimeException($msg);
                        $res['creados']++;
                    }
                } catch (\Throwable $e) {
                    $res['rechazos'][] = 'Fila ' . $r['fila'] . ': ' . $e->getMessage();
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return $res;
    }

    /** Crea la partida si el Excel trae una que no existe */
    private static function resolverPartida(int $pid, string $texto, array &$partidas, array &$res): ?Partida
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));
        if ($texto === '') return null;
        $clave = mb_strtolower($texto);
        if (isset($partidas[$clave])) return $partidas[$clave];
        // «01 Preliminares» puede corresponder a una partida con código 01 y nombre Preliminares
        $codigo = '';
        $nombre = $texto;
        if (preg_match('/^([A-Za-z0-9][A-Za-z0-9._-]{0,14})\s*[-–—.:)]?\s+(.+)$/u', $texto, $m)) {
            $codigo = $m[1];
            $nombre = trim($m[2]);
            if (isset($partidas[mb_strtolower($codigo)])) return $partidas[mb_strtolower($codigo)];
            $combinada = mb_strtolower($codigo . ' ' . $nombre);
            if (isset($partidas[$combinada])) return $partidas[$combinada];
        }
        $orden = (int)(self::fila("SELECT COALESCE(MAX(orden), 0) n FROM partidas WHERE proyecto_id = $pid")[0]['n'] ?? 0) + 1;
        $p = new Partida(['proyecto_id' => $pid, 'codigo' => $codigo !== '' ? $codigo : (string)$orden, 'nombre' => mb_substr($nombre, 0, 255), 'orden' => $orden]);
        $r = $p->guardar();
        if (!is_array($r) || empty($r['resultado'])) throw new \RuntimeException('No se pudo crear la partida «' . $texto . '»');
        $p = Partida::find((int)$r['id']);
        self::cachePartida($partidas, $p);
        $partidas[$clave] = $p;
        $res['partidas']++;
        $res['avisos'][] = 'Se creó la partida «' . $p->codigo . ' · ' . $p->nombre . '».';
        return $p;
    }

    private static function cachePartida(array &$partidas, Partida $p): void
    {
        $cod = mb_strtolower(trim((string)$p->codigo));
        $nom = mb_strtolower(trim((string)$p->nombre));
        if ($cod !== '') $partidas[$cod] ??= $p;
        if ($nom !== '') $partidas[$nom] ??= $p;
        if ($cod !== '' && $nom !== '') $partidas[$cod . ' ' . $nom] ??= $p;
    }

    private static function buscar(int $pid, array $r, ?Partida $partida): ?array
    {
        if ($r['clave'] !== '') {
            return self::fila("SELECT * FROM conceptos_obra WHERE proyecto_id = $pid AND clave = '" . self::esc($r['clave']) . "'")[0] ?? null;
        }
        $w = $partida ? ' AND partida_id = ' . (int)$partida->id : ' AND partida_id IS NULL';
        return self::fila("SELECT * FROM conceptos_obra WHERE proyecto_id = $pid$w
            AND LOWER(descripcion) = '" . self::esc(mb_strtolower($r['descripcion'])) . "'
            AND unidad = '" . self::esc($r['unidad']) . "'")[0] ?? null;
    }

    private static function fila(string $sql): array
    {
        try {
            $r = $GLOBALS['db']->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function esc(string $v): string
    {
        return $GLOBALS['db']->real_escape_string($v);
    }

    private static function numero($v): ?float
    {
        if ($v === null || trim((string)$v) === '') return null;
        // Acepta «1,234.56», «1.234,56» y «$ 1,234.56»
        $t = preg_replace('/[^0-9,.\-]/', '', (string)$v);
        if (str_contains($t, ',') && str_contains($t, '.')) {
            $t = strrpos($t, ',') > strrpos($t, '.') ? str_replace(['.', ','], ['', '.'], $t) : str_replace(',', '', $t);
        } elseif (str_contains($t, ',')) {
            $t = substr_count($t, ',') === 1 && strlen(substr($t, strrpos($t, ',') + 1)) <= 2 ? str_replace(',', '.', $t) : str_replace(',', '', $t);
        }
        return is_numeric($t) ? (float)$t : null;
    }
}
