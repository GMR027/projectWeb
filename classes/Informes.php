<?php

namespace Classes;

use Classes\Herramientas\Registro;
use Model\Proyecto;

/**
 * Informes de IA: contenedor común de lo que producen las herramientas (reporte diario, resumen semanal, lookahead, minutas…).
 * Un informe son secciones tipadas (texto, lista, tabla, kpis) que se muestran en la web y se exportan a PDF, más un bloque `aplicar`
 * opcional con los datos para llevarlo a su módulo (p. ej. crear el reporte diario). Siempre nace como borrador.
 */
class Informes
{
    use AccesoDb;

    public const ESTADOS = ['borrador' => 'Borrador', 'aprobado' => 'Aprobado', 'descartado' => 'Descartado'];

    // ---------- contenido ----------

    public static function texto(string $titulo, string $texto): array
    {
        return ['titulo' => $titulo, 'tipo' => 'texto', 'texto' => trim($texto)];
    }

    public static function lista(string $titulo, array $items): array
    {
        return ['titulo' => $titulo, 'tipo' => 'lista', 'items' => array_values(array_map('strval', $items))];
    }

    public static function tabla(string $titulo, array $columnas, array $filas): array
    {
        return ['titulo' => $titulo, 'tipo' => 'tabla', 'columnas' => $columnas, 'filas' => array_map(fn($f) => array_map(fn($c) => is_scalar($c) || $c === null ? (string)$c : json_encode($c), array_values($f)), $filas)];
    }

    /** @param array $kpis lista de [etiqueta, valor, nota?] */
    public static function kpis(string $titulo, array $kpis): array
    {
        return ['titulo' => $titulo, 'tipo' => 'kpis', 'kpis' => array_map(fn($k) => ['etq' => (string)$k[0], 'valor' => (string)$k[1], 'nota' => (string)($k[2] ?? '')], $kpis)];
    }

    /** Limita el texto que viene de un modelo: sin HTML, sin espacios raros y con tope de longitud */
    public static function limpiarTexto($t, int $max = 3000): string
    {
        $t = trim(strip_tags((string)$t));
        $t = preg_replace("/[ \t]+/u", ' ', $t);
        $t = preg_replace("/\n{3,}/", "\n\n", (string)$t);
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
    }

    /** Una sección sin información que mostrar (lista, tabla o texto vacíos); los indicadores se conservan siempre */
    public static function seccionVacia(array $s): bool
    {
        return match ($s['tipo'] ?? 'texto') {
            'kpis' => false,
            'lista' => !($s['items'] ?? []),
            'tabla' => !($s['filas'] ?? []),
            default => trim((string)($s['texto'] ?? '')) === '',
        };
    }

    /** Quita las secciones vacías para que el informe no las muestre ni las exporte */
    public static function sinVacias(array $contenido): array
    {
        $contenido['secciones'] = array_values(array_filter($contenido['secciones'] ?? [], fn($s) => !self::seccionVacia($s)));
        return $contenido;
    }

    // ---------- persistencia ----------

    public static function crear(Proyecto $p, string $tipo, string $titulo, array $contenido, array $params = [], string $origen = 'sistema', string $modelo = ''): int
    {
        $db = self::db();
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $contenido = self::sinVacias($contenido);
        $db->query("INSERT INTO informes_ia (proyecto_id, tipo, titulo, parametros_json, contenido_json, origen, modelo, creado_por) VALUES (" . (int)$p->id . ", '{$esc($tipo)}', '{$esc(mb_substr($titulo, 0, 200))}', '"
            . $esc(json_encode($params, JSON_UNESCAPED_UNICODE)) . "', '" . $esc(json_encode($contenido, JSON_UNESCAPED_UNICODE)) . "', '" . ($origen === 'ia' ? 'ia' : 'sistema') . "', '" . $esc(mb_substr($modelo, 0, 150)) . "', '" . $esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "')");
        $id = (int)$db->insert_id;
        Revisiones::registrarEjecucion((int)$p->id, 'informe:' . $tipo);
        return $id;
    }

    public static function obtener(int $pid, int $id): ?array
    {
        $f = self::filas("SELECT * FROM informes_ia WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
        if (!$f) return null;
        $f['id'] = (int)$f['id'];
        $f['contenido'] = json_decode($f['contenido_json'], true) ?: ['secciones' => []];
        $f['parametros'] = json_decode((string)$f['parametros_json'], true) ?: [];
        return $f;
    }

    public static function listar(int $pid, array $f = []): array
    {
        $w = ["proyecto_id = $pid"];
        if (isset(Registro::todas()[$f['tipo'] ?? ''])) $w[] = "tipo = '{$f['tipo']}'";
        if (isset(self::ESTADOS[$f['estado'] ?? ''])) $w[] = "estado = '{$f['estado']}'";
        return self::filas('SELECT id, tipo, titulo, origen, modelo, estado, aplicado_en, creado, creado_por FROM informes_ia WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 200');
    }

    public static function estado(int $pid, int $id, string $estado): bool
    {
        if (!isset(self::ESTADOS[$estado]) || !self::obtener($pid, $id)) return false;
        self::db()->query("UPDATE informes_ia SET estado = '$estado' WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    public static function eliminar(int $pid, int $id): void
    {
        self::db()->query("DELETE FROM informes_ia WHERE id = $id AND proyecto_id = $pid");
    }

    public static function marcarAplicado(int $pid, int $id, string $ref): void
    {
        self::db()->query("UPDATE informes_ia SET aplicado_en = NOW(), aplicado_ref = '" . self::db()->real_escape_string($ref) . "', estado = 'aprobado' WHERE id = $id AND proyecto_id = $pid");
    }

    // ---------- presentación ----------

    /** HTML (ya escapado) del contenido, para la web y el PDF */
    public static function html(array $contenido): string
    {
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $h = '';
        foreach (self::sinVacias($contenido)['secciones'] as $s) {
            $h .= '<section class="inf-sec"><h3>' . $e($s['titulo'] ?? '') . '</h3>';
            switch ($s['tipo'] ?? 'texto') {
                case 'kpis':
                    $h .= '<div class="inf-kpis">';
                    foreach ($s['kpis'] as $k) $h .= '<div class="inf-kpi"><small>' . $e($k['etq']) . '</small><strong>' . $e($k['valor']) . '</strong>' . ($k['nota'] !== '' ? '<span>' . $e($k['nota']) . '</span>' : '') . '</div>';
                    $h .= '</div>';
                    break;
                case 'lista':
                    $h .= $s['items'] ? '<ul>' . implode('', array_map(fn($i) => '<li>' . $e($i) . '</li>', $s['items'])) . '</ul>' : '<p class="inf-vacio">Sin elementos.</p>';
                    break;
                case 'tabla':
                    if (!$s['filas']) { $h .= '<p class="inf-vacio">Sin elementos.</p>'; break; }
                    $h .= '<div class="inf-tabla-wrap"><table class="inf-tabla"><thead><tr>' . implode('', array_map(fn($c) => '<th>' . $e($c) . '</th>', $s['columnas'])) . '</tr></thead><tbody>';
                    foreach ($s['filas'] as $f) $h .= '<tr>' . implode('', array_map(fn($c) => '<td>' . $e($c) . '</td>', $f)) . '</tr>';
                    $h .= '</tbody></table></div>';
                    break;
                default:
                    foreach (preg_split("/\n{2,}/", (string)($s['texto'] ?? '')) as $par) if (trim($par) !== '') $h .= '<p>' . nl2br($e(trim($par))) . '</p>';
            }
            $h .= '</section>';
        }
        return $h;
    }

    public static function pdf(Proyecto $p, array $inf): string
    {
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $css = 'body{font-family:DejaVu Sans;font-size:10px;color:#111}h1{font-size:17px;margin:0 0 2px}h2{font-size:11px;color:#555;margin:0 0 12px;font-weight:normal}h3{font-size:12px;border-bottom:1px solid #999;padding-bottom:2px;margin:14px 0 6px}'
            . 'p{margin:3px 0}ul{margin:3px 0 3px 16px;padding:0}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:2px 4px;text-align:left;vertical-align:top;font-size:9px}th{background:#e8ecf1}'
            . '.inf-kpis{width:100%}.inf-kpi{display:inline-block;width:23%;margin:0 1% 6px 0;border:1px solid #ccc;padding:4px 6px}.inf-kpi small{display:block;color:#555}.inf-kpi strong{font-size:14px}.inf-kpi span{display:block;color:#555;font-size:8px}.inf-vacio{color:#777}';
        $html = '<html><head><meta charset="utf-8"><style>' . $css . '</style></head><body><h1>' . $e($inf['titulo']) . '</h1><h2>' . $e($p->nombre) . ' · generado el ' . date('d/m/Y H:i', strtotime($inf['creado'])) . ($inf['origen'] === 'ia' ? ' · redactado con IA (' . $e($inf['modelo']) . ')' : '') . '</h2>'
            . self::html($inf['contenido']) . '</body></html>';
        return Pdf::generar($html, 'letter', 'portrait');
    }
}
