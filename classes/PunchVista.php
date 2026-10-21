<?php

namespace Classes;

use Model\Proyecto;

/**
 * Vista configurable del Punch list: qué columnas se ven (fotos incluidas), qué pendientes entran (filtros) y cuántos. Es la única forma en que
 * el punch llega a vPlan (tabla `punchlist` de `vplan_tablas.punch_config`); los datos viven siempre en `punch_items`, la vista no guarda copias.
 */
class PunchVista
{
    public const MAX_COLUMNAS = 8;
    /** clave => [etiqueta, tipo, editable desde la lámina] */
    public const COLUMNAS = [
        'titulo' => ['Pendiente', 'texto', true],
        'descripcion' => ['Descripción', 'texto', false],
        'ubicacion' => ['Ubicación', 'texto', true],
        'categoria' => ['Categoría', 'texto', true],
        'severidad' => ['Severidad', 'severidad', true],
        'responsable' => ['Responsable', 'texto', true],
        'contratista' => ['Contratista', 'texto', true],
        'fecha_limite' => ['Fecha límite', 'fecha', true],
        'estado' => ['Estado', 'estado', true],
        'actividad' => ['Actividad', 'texto', false],
        'foto_antes' => ['Foto antes', 'foto', false],
        'foto_despues' => ['Foto después', 'foto', false],
    ];
    public const POR_DEFECTO = ['titulo', 'ubicacion', 'severidad', 'responsable', 'fecha_limite', 'estado', 'foto_antes', 'foto_despues'];

    public static function normalizar($cfg): array
    {
        $cfg = is_array($cfg) ? $cfg : (json_decode((string)$cfg, true) ?: []);
        $cols = [];
        foreach ((array)($cfg['columnas'] ?? self::POR_DEFECTO) as $c) if (isset(self::COLUMNAS[$c]) && !in_array($c, $cols, true)) $cols[] = $c;
        $estado = (string)($cfg['estado'] ?? 'pendientes');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($cfg['ids'] ?? [])))));
        return [
            'columnas' => array_slice($cols ?: self::POR_DEFECTO, 0, self::MAX_COLUMNAS),
            'estado' => $estado === 'pendientes' || isset(Punch::ESTADOS[$estado]) ? $estado : 'todos',
            'severidad' => isset(Punch::SEVERIDADES[$cfg['severidad'] ?? '']) ? $cfg['severidad'] : '',
            'contratista' => mb_substr(trim((string)($cfg['contratista'] ?? '')), 0, 150),
            'categoria' => mb_substr(trim((string)($cfg['categoria'] ?? '')), 0, 60),
            'limite' => max(1, min(200, (int)($cfg['limite'] ?? 50))),
            'ids' => $ids,
        ];
    }

    /** Configuración desde el formulario (`punch[columnas][]`, `punch[estado]`…) */
    public static function desdePost(array $p): array
    {
        $cfg = $p;
        // Las casillas llegan sin orden: se respeta el del catálogo
        $marcadas = (array)($p['columnas'] ?? []);
        $cfg['columnas'] = array_values(array_filter(array_keys(self::COLUMNAS), fn($k) => in_array($k, $marcadas, true)));
        $cfg['ids'] = json_decode((string)($p['ids'] ?? '[]'), true) ?: [];
        return self::normalizar($cfg);
    }

    public static function items(int $pid, array $cfg): array
    {
        $cfg = self::normalizar($cfg);
        $f = ['estado' => $cfg['estado'] === 'todos' ? '' : $cfg['estado'], 'severidad' => $cfg['severidad'], 'contratista' => $cfg['contratista'], 'categoria' => $cfg['categoria']];
        $items = Punch::listar($pid, $f);
        if ($cfg['ids']) $items = array_values(array_filter($items, fn($x) => in_array((int)$x['id'], $cfg['ids'], true)));
        return array_slice($items, 0, $cfg['limite']);
    }

    public static function etiquetas(array $cfg): array
    {
        return array_map(fn($k) => self::COLUMNAS[$k][0], self::normalizar($cfg)['columnas']);
    }

    /** Renglones para una tabla de vPlan: c0..cN (texto mostrado), v0..vN (valor crudo), _pid (pendiente) */
    public static function filas(int $pid, array $cfg): array
    {
        $cfg = self::normalizar($cfg);
        $fecha = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '';
        $out = [];
        foreach (self::items($pid, $cfg) as $x) {
            $fila = ['_pid' => (int)$x['id']];
            foreach ($cfg['columnas'] as $i => $k) {
                $crudo = match ($k) {
                    'actividad' => (string)($x['act_codigo'] ?? ''),
                    default => (string)($x[$k] ?? ''),
                };
                $fila['v' . $i] = $crudo;
                $fila['c' . $i] = match ($k) {
                    'severidad' => Punch::SEVERIDADES[$crudo] ?? $crudo,
                    'estado' => Punch::ESTADOS[$crudo] ?? $crudo,
                    'fecha_limite' => $fecha($crudo),
                    'foto_antes', 'foto_despues' => $crudo !== '' ? 'foto' : '',
                    default => $crudo,
                };
            }
            $out[] = $fila;
        }
        return $out;
    }

    /** Tipo de control de cada columna: ['c0' => ['t' => 'lista', 'op' => [...], 'ro' => bool], …] */
    public static function meta(array $cfg): array
    {
        $out = [];
        foreach (self::normalizar($cfg)['columnas'] as $i => $k) {
            [, $tipo, $edit] = self::COLUMNAS[$k];
            $m = match ($tipo) {
                'severidad' => ['t' => 'lista', 'op' => Punch::SEVERIDADES],
                'estado' => ['t' => 'lista', 'op' => Punch::ESTADOS],
                'fecha' => ['t' => 'fecha', 'op' => []],
                'foto' => ['t' => 'foto', 'op' => []],
                default => ['t' => 'texto', 'op' => []],
            };
            $m['ro'] = !$edit;
            $out['c' . $i] = $m;
        }
        return $out;
    }

    /** Cambia un campo de un pendiente desde la lámina. @return string|null mensaje de error */
    public static function cambiar(Proyecto $p, int $id, string $clave, string $valor): ?string
    {
        $pid = (int)$p->id;
        if (!isset(self::COLUMNAS[$clave]) || !self::COLUMNAS[$clave][2]) return 'Este campo no se edita aquí';
        $x = Punch::obtener($pid, $id);
        if (!$x) return 'El pendiente ya no existe';
        if ($clave === 'estado') {
            [$ok, $msg] = Punch::estado($p, $id, $valor);
            return $ok ? null : $msg;
        }
        $db = $GLOBALS['db'];
        if ($clave === 'severidad') {
            if (!isset(Punch::SEVERIDADES[$valor])) return 'Severidad no válida';
            $sql = "severidad = '$valor'";
        } elseif ($clave === 'fecha_limite') {
            $d = \DateTime::createFromFormat('Y-m-d', $valor);
            if ($valor !== '' && !($d && $d->format('Y-m-d') === $valor)) return 'Fecha no válida';
            $sql = 'fecha_limite = ' . ($valor === '' ? 'NULL' : "'$valor'");
        } else {
            $max = ['titulo' => 200, 'ubicacion' => 200, 'categoria' => 60, 'responsable' => 150, 'contratista' => 150][$clave];
            if ($clave === 'titulo' && trim($valor) === '') return 'El título es obligatorio';
            $sql = "$clave = '" . $db->real_escape_string(mb_substr(trim($valor), 0, $max)) . "'";
        }
        $db->query("UPDATE punch_items SET $sql WHERE id = $id AND proyecto_id = $pid");
        return null;
    }

    // ---------- Redactor: tabla `data-punch` (la configuración viaja en el propio atributo) ----------

    public static function codificar(array $cfg): string
    {
        $cfg = self::normalizar($cfg);
        $cfg['ids'] = [];
        return rtrim(strtr(base64_encode(json_encode($cfg, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    }

    public static function decodificar(string $v): array
    {
        return self::normalizar(json_decode((string)base64_decode(strtr($v, '-_', '+/')), true) ?: []);
    }

    /** Miniatura en disco (public/uploads/punch/min/<hash>.jpg, 400 px) para documentos; devuelve la ruta web o null */
    public static function rutaMiniatura(string $archivo): ?string
    {
        $n = basename($archivo);
        if (!preg_match('/^([a-f0-9]{32})\.(?:png|jpg)$/', $n, $m)) return null;
        $dir = dirname(__DIR__) . '/public/uploads/' . Punch::CARPETA . '/min';
        $destino = $dir . '/' . $m[1] . '.jpg';
        if (!is_file($destino)) {
            $origen = dirname(__DIR__) . '/public/uploads/' . Punch::CARPETA . '/' . $n;
            if (!is_file($origen)) return null;
            try {
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                (new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()))->read($origen)->scaleDown(width: 400, height: 400)->toJpeg(75)->save($destino);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return '/uploads/' . Punch::CARPETA . '/min/' . $m[1] . '.jpg';
    }

    /** Quita la miniatura de una foto que ya no se usa */
    public static function borrarMiniatura(?string $archivo): void
    {
        if ($archivo && preg_match('/^([a-f0-9]{32})\.(?:png|jpg)$/', basename($archivo), $m)) @unlink(dirname(__DIR__) . '/public/uploads/' . Punch::CARPETA . '/min/' . $m[1] . '.jpg');
    }

    /** Tabla HTML del Redactor (se rehace al abrir, ver y exportar). Bordes en el orden «1px #color solid» que lee PhpWord */
    public static function htmlRedactor(int $pid, array $cfg): string
    {
        $cfg = self::normalizar($cfg);
        $esc = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        $b = 'border:1px #888888 solid;padding:3pt 6pt;';
        $o = '<table data-punch="' . self::codificar($cfg) . '" style="width:100%;border-collapse:collapse"><tbody><tr>';
        foreach (self::etiquetas($cfg) as $e) $o .= '<th style="' . $b . 'background-color:#e2e8f0;text-align:center">' . $esc($e) . '</th>';
        $o .= '</tr>';
        $filas = self::filas($pid, $cfg);
        if (!$filas) return $o . '<tr><td colspan="' . count($cfg['columnas']) . '" style="' . $b . '">Sin pendientes con este filtro.</td></tr></tbody></table>';
        foreach ($filas as $f) {
            $o .= '<tr>';
            foreach ($cfg['columnas'] as $i => $k) {
                if (self::COLUMNAS[$k][1] === 'foto') {
                    $src = $f['v' . $i] !== '' ? self::rutaMiniatura($f['v' . $i]) : null;
                    $o .= '<td style="' . $b . 'text-align:center">' . ($src ? '<img src="' . $esc($src) . '" style="max-width:100%;max-height:60pt">' : '<br>') . '</td>';
                } else {
                    $o .= '<td style="' . $b . '">' . ($f['c' . $i] !== '' ? $esc($f['c' . $i]) : '<br>') . '</td>';
                }
            }
            $o .= '</tr>';
        }
        return $o . '</tbody></table>';
    }

    /** Reemplaza las tablas `data-punch` de un HTML del Redactor por su versión actual */
    public static function refrescarHtml(string $html, int $pid): string
    {
        if (!str_contains($html, 'data-punch=')) return $html;
        return preg_replace_callback('#<table\b[^>]*\bdata-punch="([A-Za-z0-9_-]{1,6000})"[^>]*>.*?</table>#s', fn($m) => self::htmlRedactor($pid, self::decodificar($m[1])), $html);
    }

    /** Miniatura JPEG en base64 para el PDF (las fotos originales pesan mucho) */
    public static function miniatura(string $archivo, int $lado = 220): ?string
    {
        static $cache = [];
        if (isset($cache[$archivo])) return $cache[$archivo];
        $ruta = dirname(__DIR__) . '/public/uploads/' . Punch::CARPETA . '/' . basename($archivo);
        $datos = is_file($ruta) ? @file_get_contents($ruta) : false;
        $im = $datos ? @imagecreatefromstring($datos) : false;
        if (!$im) return $cache[$archivo] = null;
        [$w, $h] = [imagesx($im), imagesy($im)];
        $e = min(1, $lado / max($w, $h));
        $nw = max(1, (int)round($w * $e));
        $nh = max(1, (int)round($h * $e));
        $mini = imagecreatetruecolor($nw, $nh);
        imagefill($mini, 0, 0, imagecolorallocate($mini, 255, 255, 255));
        imagecopyresampled($mini, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($mini, null, 78);
        return $cache[$archivo] = 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
    }
}
