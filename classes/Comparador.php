<?php

namespace Classes;

use Model\Archivo;
use Model\ComparadorGuardado;
use Model\ComparadorVersion;
use Model\Grafico;
use Model\GraficoLamina;
use Model\Proyecto;
use Model\RedactorDocumento;
use Model\VplanLamina;

/**
 * Comparador de documentos: comparaciones guardadas y anotaciones.
 *
 * Los documentos del proyecto se referencian por su id; los externos se copian a
 * `public/uploads/comparador` solo cuando la persona guarda la comparación. Las
 * anotaciones viven en coordenadas del documento A (no de la pantalla).
 */
class Comparador
{
    use AccesoDb;

    public const CARPETA = __DIR__ . '/../public/uploads/comparador';
    public const MINIATURAS = self::CARPETA . '/min';
    public const MAX_ARCHIVO = 60 * 1024 * 1024;   // 60 MB por documento externo
    public const MAX_ANOTACIONES = 300;
    public const MAX_JSON_ANOTACIONES = 2_000_000;
    private const TIPOS = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    private const HERRAMIENTAS = ['globo', 'texto', 'resaltador', 'resaltado', 'rect', 'elipse', 'flecha', 'linea', 'mano', 'nube', 'medir', 'area'];

    public const COLUMNAS = [
        'nombre' => 150, 'descripcion' => 500,
    ];

    /** Tipos de fuente del propio proyecto (se siguen leyendo de su herramienta; no se copian) */
    public const FUENTES_PROYECTO = ['archivo', 'vplan', 'editor', 'redactor'];

    /**
     * Catálogo de documentos del proyecto que se pueden comparar, agrupados por herramienta.
     * Cada elemento: ['valor' => "tipo:id", 'nombre' => ..., 'ext' => ...].
     */
    public static function fuentes(int $pid): array
    {
        $grupos = [];

        $items = [];
        $arch = [];
        foreach (Archivo::deProyecto($pid) as $a) {
            $ext = $a->extension();
            if (!isset(self::TIPOS[$ext]) || !$a->ruta()) continue;
            $nombre = trim((string)($a->nombre ?: $a->archivo_original));
            $rev = trim((string)$a->numero_revision);
            if ($rev !== '' && !str_contains($nombre, 'Rev. ' . $rev)) $nombre .= ' · Rev. ' . $rev;
            $arch[] = ['id' => (int)$a->id, 'nombre' => $nombre, 'ext' => $ext, 'rev' => $rev, 'clave' => self::claveDocumento((string)($a->nombre ?: $a->archivo_original))];
        }
        // Revisión anterior del mismo documento (para comparar dos revisiones con un clic)
        $gruposRev = [];
        foreach ($arch as $i => $a) $gruposRev[$a['clave']][] = $i;
        $anterior = [];
        foreach ($gruposRev as $idx) {
            if (count($idx) < 2) continue;
            usort($idx, fn($x, $y) => [self::claveRevision($arch[$x]['rev']), $arch[$x]['id']] <=> [self::claveRevision($arch[$y]['rev']), $arch[$y]['id']]);
            for ($j = 1; $j < count($idx); $j++) $anterior[$arch[$idx[$j]]['id']] = $arch[$idx[$j - 1]];
        }
        foreach ($arch as $a) {
            $item = ['valor' => 'archivo:' . $a['id'], 'nombre' => $a['nombre'], 'ext' => $a['ext']];
            if (isset($anterior[$a['id']])) {
                $item['prev'] = 'archivo:' . $anterior[$a['id']]['id'];
                $item['prev_nombre'] = $anterior[$a['id']]['nombre'];
            }
            $items[] = $item;
        }
        if ($items) $grupos[] = ['grupo' => 'Archivo del proyecto', 'items' => $items];

        $items = [];
        try {
            foreach (VplanLamina::deProyecto($pid) as $l) {
                $img = self::lamina($l->marcas_png ?: $l->plano);
                if (!$img) continue;
                $nombre = trim((string)$l->titulo) !== '' ? (string)$l->titulo : 'Lámina ' . (int)$l->id;
                if (trim((string)$l->partida_nombre) !== '') $nombre .= ' · ' . $l->partida_nombre;
                $items[] = ['valor' => 'vplan:' . (int)$l->id, 'nombre' => $nombre, 'ext' => $img['ext']];
            }
        } catch (\Throwable $e) {   // migración pendiente o tabla ausente
        }
        if ($items) $grupos[] = ['grupo' => 'vPlan · láminas', 'items' => $items];

        $items = [];
        try {
            $sql = "SELECT l.id, l.nombre, l.marcas_png, g.nombre AS grafico_nombre
                    FROM editor_laminas l JOIN editor_graficos g ON g.id = l.grafico_id
                    WHERE g.proyecto_id = $pid AND l.marcas_png IS NOT NULL AND l.marcas_png <> ''
                    ORDER BY g.nombre, l.orden, l.id";
            foreach (GraficoLamina::consultarSQL($sql) as $l) {
                $img = self::lamina($l->marcas_png);
                if (!$img) continue;
                $nombre = trim((string)$l->grafico_nombre);
                if (trim((string)$l->nombre) !== '') $nombre .= ($nombre !== '' ? ' · ' : '') . $l->nombre;
                $items[] = ['valor' => 'editor:' . (int)$l->id, 'nombre' => $nombre !== '' ? $nombre : 'Gráfico ' . (int)$l->id, 'ext' => $img['ext']];
            }
        } catch (\Throwable $e) {
        }
        if ($items) $grupos[] = ['grupo' => 'Editor · gráficos y símbolos', 'items' => $items];

        $items = [];
        try {
            foreach (RedactorDocumento::deProyecto($pid) as $d) {
                if (trim((string)$d->nombre) === '') continue;
                $items[] = ['valor' => 'redactor:' . (int)$d->id, 'nombre' => (string)$d->nombre, 'ext' => 'pdf'];
            }
        } catch (\Throwable $e) {
        }
        if ($items) $grupos[] = ['grupo' => 'Redactor · documentos (PDF)', 'items' => $items];

        return $grupos;
    }

    /** Clave para agrupar revisiones del mismo documento (sin «Rev. A», «R2» ni sufijos de versión) */
    private static function claveDocumento(string $nombre): string
    {
        $k = mb_strtolower(trim($nombre));
        $k = preg_replace('/\s*[·\-–|]?\s*rev\.?\s*[a-z0-9]+\.?\s*$/u', '', $k) ?? $k;
        $k = preg_replace('/\s*[·\-–|]?\s*r\d+\s*$/u', '', $k) ?? $k;
        $k = preg_replace('/\s+/u', ' ', $k) ?? $k;
        return trim($k);
    }

    /** Orden de una revisión: R2 → 2, «B» → 2, «Firmado» → 0 */
    private static function claveRevision(string $rev): int
    {
        if (preg_match('/(\d+)/', $rev, $m)) return (int)$m[1];
        if (preg_match('/^\s*([a-z])\s*$/i', $rev, $m)) return ord(mb_strtoupper($m[1])) - 64;
        return 0;
    }

    /** Imagen guardada de una lámina de vPlan/Editor (png, jpg…) */
    private static function lamina($nombre): ?array
    {
        $n = basename((string)$nombre);
        if ($n === '') return null;
        $ruta = Imagenes::CARPETA_VPLAN . '/' . $n;
        if (!is_file($ruta)) return null;
        $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) return null;
        return ['ruta' => $ruta, 'ext' => $ext === 'jpeg' ? 'jpg' : $ext];
    }

    /**
     * Documento de una fuente del proyecto listo para entregar.
     * @return array{ruta?: string, bytes?: string, mime: string, nombre: string, ext: string}|null
     */
    public static function fuenteDeProyecto(int $pid, string $tipo, int $id, bool $generar = true): ?array
    {
        if ($id <= 0) return null;
        switch ($tipo) {
            case 'archivo':
                $a = Archivo::find($id);
                if (!$a || (int)$a->proyecto_id !== $pid) return null;
                $ext = $a->extension();
                $ruta = $a->ruta();
                if (!isset(self::TIPOS[$ext]) || !$ruta) return null;
                return ['ruta' => $ruta, 'mime' => self::TIPOS[$ext], 'nombre' => trim((string)($a->nombre ?: $a->archivo_original)), 'ext' => $ext];

            case 'vplan':
                $l = VplanLamina::find($id);
                if (!$l || (int)$l->proyecto_id !== $pid) return null;
                $img = self::lamina($l->marcas_png ?: $l->plano);
                if (!$img) return null;
                return ['ruta' => $img['ruta'], 'mime' => self::TIPOS[$img['ext']] ?? 'image/png', 'nombre' => trim((string)$l->titulo) ?: 'Lámina ' . $id, 'ext' => $img['ext']];

            case 'editor':
                $l = GraficoLamina::find($id);
                $g = $l ? Grafico::find($l->grafico_id) : null;
                if (!$g || (int)$g->proyecto_id !== $pid) return null;
                $img = self::lamina($l->marcas_png);
                if (!$img) return null;
                $nombre = trim((string)$g->nombre) . (trim((string)$l->nombre) !== '' ? ' · ' . $l->nombre : '');
                return ['ruta' => $img['ruta'], 'mime' => self::TIPOS[$img['ext']] ?? 'image/png', 'nombre' => $nombre !== '' ? $nombre : 'Gráfico ' . (int)$g->id, 'ext' => $img['ext']];

            case 'redactor':
                $d = RedactorDocumento::find($id);
                if (!$d || (int)$d->proyecto_id !== $pid) return null;
                $out = ['mime' => 'application/pdf', 'nombre' => trim((string)$d->nombre) ?: 'Documento ' . $id, 'ext' => 'pdf'];
                if ($generar) {
                    @set_time_limit(180);
                    try {
                        $out['bytes'] = \Classes\Redactor::pdf($d);
                    } catch (\Throwable $e) {
                        error_log('Comparador · PDF del Redactor: ' . $e->getMessage());
                        return null;
                    }
                }
                return $out;
        }
        return null;
    }

    /** Comparaciones guardadas del proyecto (vacío si la migración aún no está aplicada) */
    public static function listar(int $pid): array
    {
        try {
            return array_map(fn(ComparadorGuardado $g) => $g->tarjeta(), ComparadorGuardado::deProyecto($pid));
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function obtener(int $pid, int $id): ?ComparadorGuardado
    {
        try {
            return ComparadorGuardado::deProyectoPorId($pid, $id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Solo el autor o el administrador pueden modificar o eliminar una comparación */
    public static function puedeEditar(array $g): bool
    {
        if (!is_auth()) return false;
        if (is_admin()) return true;
        return (int)($g['usuario_id'] ?? 0) === (int)($_SESSION['id'] ?? 0);
    }

    /**
     * Guarda (crea o actualiza) una comparación.
     * @return array{0: bool, 1: string, 2: int}
     */
    public static function guardar(Proyecto $p, array $post, array $files = []): array
    {
        $pid = (int)$p->id;
        $nombre = trim((string)($post['nombre'] ?? ''));
        if ($nombre === '') return [false, 'Escribe un nombre para la comparación.', 0];

        $id = (int)($post['id'] ?? 0);
        $existente = $id ? self::obtener($pid, $id) : null;
        if ($id && !$existente) return [false, 'La comparación ya no existe.', 0];

        $nuevos = [];
        $extra = 0;
        $fuentes = [];
        foreach (['a', 'b'] as $slot) {
            $src = json_decode((string)($post[$slot . '_json'] ?? ''), true);
            if (!is_array($src)) return [false, 'Faltan los datos del documento ' . strtoupper($slot) . '.', 0];
            $subida = self::subirDocumento($p, $slot, $files, (string)($src['nombre'] ?? ''), $extra);
            if (!$subida['ok']) {
                foreach ($nuevos as $f) @unlink(self::CARPETA . '/' . $f);
                return [false, $subida['error'], 0];
            }
            if ($subida['fuente']) {
                $fuente = $subida['fuente'];
                $fuente['pagina'] = max(1, min(10000, (int)($src['pagina'] ?? 1)));
                $nuevos[] = $fuente['archivo'];
                $extra += (int)@filesize(self::CARPETA . '/' . $fuente['archivo']);
            } else {
                $fuente = self::fuenteValida($pid, $src, $existente, $slot);
                if (!$fuente) {
                    foreach ($nuevos as $f) @unlink(self::CARPETA . '/' . $f);
                    return [false, 'El documento ' . strtoupper($slot) . ' no es válido o ya no existe.', 0];
                }
            }
            $fuentes[$slot] = $fuente;
        }

        $vista = self::vistaValida($post['vista'] ?? null);
        $ajuste = self::ajusteValido($post['ajuste'] ?? null);
        $modo = self::modoValido($post['modo'] ?? null);
        $anotaciones = self::anotaciones($post['anotaciones'] ?? null);
        if ($vista === null || $ajuste === null || $modo === null || $anotaciones === null) {
            foreach ($nuevos as $f) @unlink(self::CARPETA . '/' . $f);
            return [false, 'La configuración de la comparación no es válida.', 0];
        }

        $miniatura = null;
        if (trim((string)($post['miniatura'] ?? '')) !== '') {
            $miniatura = self::guardarMiniatura((string)$post['miniatura']);
            if (!$miniatura) {
                foreach ($nuevos as $f) @unlink(self::CARPETA . '/' . $f);
                return [false, 'No se pudo guardar la miniatura de la comparación.', 0];
            }
        }

        $usuario = usuario_actual();
        $vistaJson = json_encode($vista, JSON_UNESCAPED_UNICODE);
        $ajusteJson = json_encode($ajuste, JSON_UNESCAPED_UNICODE);
        $modoJson = json_encode($modo, JSON_UNESCAPED_UNICODE);
        $anotJson = json_encode($anotaciones, JSON_UNESCAPED_UNICODE);
        if ($existente) self::guardarVersion($existente);
        $g = new ComparadorGuardado([
            'id' => $existente?->id,
            'proyecto_id' => $pid,
            'nombre' => mb_substr($nombre, 0, self::COLUMNAS['nombre']),
            'descripcion' => mb_substr(trim((string)($post['descripcion'] ?? '')), 0, self::COLUMNAS['descripcion']),
            'a_json' => json_encode($fuentes['a'], JSON_UNESCAPED_UNICODE),
            'b_json' => json_encode($fuentes['b'], JSON_UNESCAPED_UNICODE),
            'vista_json' => $vistaJson,
            'ajuste_json' => $ajusteJson,
            'modo_json' => $modoJson,
            'anotaciones_json' => $anotJson,
            'miniatura' => $miniatura ?: ($existente?->miniatura ?? null),
            'creado_por' => $existente ? (string)$existente->creado_por : mb_substr((string)($usuario['nombre'] ?? ''), 0, 150),
            'usuario_id' => $existente ? $existente->usuario_id : (int)($usuario['id'] ?? 0),
            'token' => $existente ? $existente->token : null,
        ]);
        $r = $g->guardar();
        if (!$r || (is_array($r) && empty($r['resultado']))) {
            foreach ($nuevos as $f) @unlink(self::CARPETA . '/' . $f);
            if ($miniatura) @unlink(self::MINIATURAS . '/' . $miniatura);
            return [false, 'No se pudo guardar la comparación.', 0];
        }
        $nuevoId = $existente ? (int)$existente->id : (int)($r['id'] ?? 0);
        if ($existente) {
            $usados = array_filter([$fuentes['a']['archivo'] ?? null, $fuentes['b']['archivo'] ?? null]);
            foreach (array_diff($existente->archivos(), $usados) as $viejo) @unlink(self::CARPETA . '/' . basename($viejo));
        }
        if ($existente && $miniatura && $existente->miniatura && $existente->miniatura !== $miniatura) {
            @unlink(self::MINIATURAS . '/' . basename((string)$existente->miniatura));
        }
        return [true, $existente ? 'Comparación actualizada.' : 'Comparación guardada.', $nuevoId];
    }

    public static function eliminar(int $pid, int $id): bool
    {
        $g = self::obtener($pid, $id);
        if (!$g) return false;
        if (!$g->eliminar()) return false;
        foreach ($g->archivos() as $f) @unlink(self::CARPETA . '/' . basename($f));
        if ($g->miniatura) @unlink(self::MINIATURAS . '/' . basename((string)$g->miniatura));
        return true;
    }

    // ---------- historial de versiones ----------

    /** Guarda el estado actual como versión (antes de sobrescribirlo) */
    private static function guardarVersion(ComparadorGuardado $g): void
    {
        try {
            $ultima = ComparadorVersion::consultarSQL("SELECT * FROM comparador_versiones WHERE guardado_id = " . (int)$g->id . " ORDER BY creado DESC, id DESC LIMIT 1")[0] ?? null;
            if ($ultima && $ultima->anotaciones_json === $g->anotaciones_json && $ultima->vista_json === $g->vista_json
                && $ultima->ajuste_json === $g->ajuste_json && $ultima->modo_json === $g->modo_json) return;
            $usuario = usuario_actual();
            (new ComparadorVersion([
                'proyecto_id' => (int)$g->proyecto_id, 'guardado_id' => (int)$g->id,
                'nombre' => (string)$g->nombre, 'descripcion' => (string)$g->descripcion,
                'vista_json' => (string)$g->vista_json, 'ajuste_json' => (string)$g->ajuste_json,
                'modo_json' => (string)$g->modo_json, 'anotaciones_json' => (string)$g->anotaciones_json,
                'creado_por' => mb_substr((string)($usuario['nombre'] ?? ''), 0, 150), 'usuario_id' => (int)($usuario['id'] ?? 0),
            ]))->guardar();
            // Conserva las 20 más recientes
            ComparadorGuardado::ejecutar("DELETE FROM comparador_versiones WHERE guardado_id = " . (int)$g->id . "
                AND id NOT IN (SELECT id FROM (SELECT id FROM comparador_versiones WHERE guardado_id = " . (int)$g->id . " ORDER BY creado DESC, id DESC LIMIT 20) t)");
        } catch (\Throwable $e) {
            error_log('Comparador · versión: ' . $e->getMessage());
        }
    }

    /** @return array<int, array{id:int,nombre:string,creado:string,autor:string,anotaciones:int}> */
    public static function versiones(int $pid, int $gid): array
    {
        try {
            return array_map(fn(ComparadorVersion $v) => $v->tarjeta(), ComparadorVersion::deGuardado($pid, $gid));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Restaura una versión (guarda antes el estado actual para poder deshacer) */
    public static function restaurarVersion(int $pid, int $gid, int $vid): ?array
    {
        $g = self::obtener($pid, $gid);
        $v = $g ? ComparadorVersion::deGuardadoPorId($pid, $gid, $vid) : null;
        if (!$g || !$v) return null;
        self::guardarVersion($g);
        $g->nombre = (string)$v->nombre;
        $g->descripcion = (string)$v->descripcion;
        $g->vista_json = (string)$v->vista_json;
        $g->ajuste_json = (string)$v->ajuste_json;
        $g->modo_json = (string)$v->modo_json;
        $g->anotaciones_json = (string)$v->anotaciones_json;
        if (!$g->guardar()) return null;
        return $g->paraJs();
    }

    // ---------- enlace público de solo lectura ----------

    /** Token del enlace público (se crea la primera vez) */
    public static function token(int $pid, int $gid): ?string
    {
        $g = self::obtener($pid, $gid);
        if (!$g) return null;
        if (preg_match('/^[0-9a-f]{48}$/', (string)$g->token)) return (string)$g->token;
        $t = bin2hex(random_bytes(24));
        ComparadorGuardado::ejecutar("UPDATE comparador_guardados SET token = '$t' WHERE id = " . (int)$gid . " AND proyecto_id = $pid");
        $g->token = $t;
        return $t;
    }

    /** Revoca el enlace público (la comparación sigue existiendo) */
    public static function revocarToken(int $pid, int $gid): bool
    {
        try {
            ComparadorGuardado::ejecutar("UPDATE comparador_guardados SET token = NULL WHERE id = " . (int)$gid . " AND proyecto_id = $pid");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function publicoPorToken(string $token): ?ComparadorGuardado
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) return null;
        try {
            return ComparadorGuardado::consultarSQL("SELECT * FROM comparador_guardados WHERE token = '$token' LIMIT 1")[0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function urlPublica(string $token): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $base . '/comparador/publico?t=' . $token;
    }

    /** Documento de una comparación guardada para el enlace público (sin sesión) */
    public static function fuentePublica(ComparadorGuardado $g, string $slot): ?array
    {
        if (!in_array($slot, ['a', 'b'], true)) return null;
        $d = json_decode((string)($slot === 'a' ? $g->a_json : $g->b_json), true);
        if (!is_array($d)) return null;
        $tipo = (string)($d['tipo'] ?? '');
        if ($tipo === 'subido' || $tipo === 'nuevo') return self::archivoSubido($g, $slot);
        return self::fuenteDeProyecto((int)$g->proyecto_id, $tipo, (int)($d['id'] ?? 0));
    }

    /** Copia una comparación y sus documentos externos; devuelve el id nuevo o 0 */
    public static function duplicar(Proyecto $p, ComparadorGuardado $g): int
    {
        $copias = [];
        $fuentes = [];
        foreach (['a', 'b'] as $slot) {
            $d = json_decode((string)($slot === 'a' ? $g->a_json : $g->b_json), true);
            if (is_array($d) && ($d['tipo'] ?? '') === 'subido' && ($d['archivo'] ?? '') !== '') {
                $origen = self::CARPETA . '/' . basename((string)$d['archivo']);
                $ext = strtolower(pathinfo($origen, PATHINFO_EXTENSION));
                $nuevo = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
                if (!is_file($origen) || !@copy($origen, self::CARPETA . '/' . $nuevo)) {
                    foreach ($copias as $f) @unlink(self::CARPETA . '/' . $f);
                    return 0;
                }
                $copias[] = $nuevo;
                $d['archivo'] = $nuevo;
            }
            $fuentes[$slot] = $d;
        }
        $miniatura = null;
        if ($g->miniatura && is_file(self::MINIATURAS . '/' . basename((string)$g->miniatura))) {
            $miniatura = bin2hex(random_bytes(16)) . '.jpg';
            if (!@copy(self::MINIATURAS . '/' . basename((string)$g->miniatura), self::MINIATURAS . '/' . $miniatura)) $miniatura = null;
        }
        $usuario = usuario_actual();
        $nuevo = new ComparadorGuardado([
            'proyecto_id' => (int)$p->id,
            'nombre' => mb_substr(trim((string)$g->nombre) . ' (copia)', 0, 150),
            'descripcion' => (string)$g->descripcion,
            'a_json' => json_encode($fuentes['a'], JSON_UNESCAPED_UNICODE),
            'b_json' => json_encode($fuentes['b'], JSON_UNESCAPED_UNICODE),
            'vista_json' => (string)$g->vista_json,
            'ajuste_json' => (string)$g->ajuste_json,
            'modo_json' => (string)$g->modo_json,
            'anotaciones_json' => (string)$g->anotaciones_json,
            'miniatura' => $miniatura,
            'creado_por' => mb_substr((string)($usuario['nombre'] ?? ''), 0, 150),
            'usuario_id' => (int)($usuario['id'] ?? 0),
        ]);
        $r = $nuevo->guardar();
        if (!$r || (is_array($r) && empty($r['resultado']))) {
            foreach ($copias as $f) @unlink(self::CARPETA . '/' . $f);
            if ($miniatura) @unlink(self::MINIATURAS . '/' . $miniatura);
            return 0;
        }
        return (int)($r['id'] ?? 0);
    }

    /** Archivo copiado de un documento externo de una comparación guardada */
    public static function archivoSubido(ComparadorGuardado $g, string $slot): ?array
    {
        if (!in_array($slot, ['a', 'b'], true)) return null;
        $d = json_decode((string)($slot === 'a' ? $g->a_json : $g->b_json), true);
        if (!is_array($d) || ($d['tipo'] ?? '') !== 'subido') return null;
        $nombre = basename((string)($d['archivo'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}\.(pdf|png|jpg|jpeg|gif|webp)$/', $nombre)) return null;
        $ruta = self::CARPETA . '/' . $nombre;
        if (!is_file($ruta)) return null;
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        return ['ruta' => $ruta, 'mime' => self::TIPOS[$ext] ?? 'application/octet-stream', 'nombre' => (string)($d['nombre'] ?? $nombre)];
    }

    /** Ruta de la miniatura de una comparación guardada */
    public static function rutaMiniatura(ComparadorGuardado $g): ?string
    {
        if (!$g->miniatura) return null;
        $ruta = self::MINIATURAS . '/' . basename((string)$g->miniatura);
        return is_file($ruta) ? $ruta : null;
    }

    public static function bytes(int $pid): int
    {
        try {
            $t = 0;
            foreach (ComparadorGuardado::deProyecto($pid) as $g) $t += $g->bytes();
            return $t;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ---------- validación ----------

    /** @return array{ok: bool, fuente: ?array, error: string} */
    private static function subirDocumento(Proyecto $p, string $slot, array $files, string $nombreOriginal, int $extra = 0): array
    {
        $f = $files[$slot . '_archivo'] ?? null;
        if (!$f) return ['ok' => true, 'fuente' => null, 'error' => ''];
        if (!empty($f['error']) && (int)$f['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'fuente' => null, 'error' => 'No se pudo subir el documento ' . strtoupper($slot) . '.'];
        if (empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) return ['ok' => true, 'fuente' => null, 'error' => ''];        $nombre = basename((string)($f['name'] ?? $nombreOriginal));
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!isset(self::TIPOS[$ext])) return ['ok' => false, 'fuente' => null, 'error' => 'El documento ' . strtoupper($slot) . ' debe ser PDF o imagen (png, jpg, webp, gif).'];
        if ((int)$f['size'] > self::MAX_ARCHIVO) return ['ok' => false, 'fuente' => null, 'error' => 'El documento ' . strtoupper($slot) . ' supera 60 MB.'];
        if (!Almacenamiento::cabe((int)$p->id, (int)$f['size'] + $extra)) return ['ok' => false, 'fuente' => null, 'error' => Almacenamiento::mensajeLleno((int)$p->id)];
        foreach (EscanerArchivos::revisar($f['tmp_name'], $nombre) as $motivo) return ['ok' => false, 'fuente' => null, 'error' => 'Documento ' . strtoupper($slot) . ': ' . $motivo];
        if (!is_dir(self::CARPETA) && !@mkdir(self::CARPETA, 0775, true)) return ['ok' => false, 'fuente' => null, 'error' => 'No se pudo crear la carpeta del Comparador.'];

        if ($ext !== 'pdf') {
            $guardado = Imagenes::guardarEn('comparador', $f['tmp_name'], 4200, 90);
            if (!$guardado) return ['ok' => false, 'fuente' => null, 'error' => 'La imagen ' . strtoupper($slot) . ' no es válida.'];
            $ext = strtolower(pathinfo($guardado, PATHINFO_EXTENSION)) ?: $ext;
            $archivo = $guardado;
        } else {
            $archivo = bin2hex(random_bytes(16)) . '.pdf';
            if (!move_uploaded_file($f['tmp_name'], self::CARPETA . '/' . $archivo)) return ['ok' => false, 'fuente' => null, 'error' => 'No se pudo guardar el PDF ' . strtoupper($slot) . '.'];
        }
        return ['ok' => true, 'fuente' => ['tipo' => 'subido', 'archivo' => $archivo, 'nombre' => mb_substr($nombre, 0, 255), 'ext' => $ext, 'pagina' => 1], 'error' => ''];
    }

    /** Valida una fuente que no se sube en esta petición (archivo del proyecto o copia ya guardada) */
    private static function fuenteValida(int $pid, array $d, ?ComparadorGuardado $existente, string $slot): ?array
    {
        $tipo = (string)($d['tipo'] ?? '');
        $pagina = max(1, min(10000, (int)($d['pagina'] ?? 1)));
        if ($tipo === 'archivo') {
            $a = Archivo::find((int)($d['id'] ?? 0));
            if (!$a || (int)$a->proyecto_id !== $pid) return null;
            $ext = $a->extension();
            if (!isset(self::TIPOS[$ext]) || !$a->ruta()) return null;
            return ['tipo' => 'archivo', 'id' => (int)$a->id, 'nombre' => mb_substr((string)($a->nombre ?: $a->archivo_original), 0, 255), 'ext' => $ext, 'pagina' => $pagina];
        }
        if (in_array($tipo, ['vplan', 'editor', 'redactor'], true)) {
            $f = self::fuenteDeProyecto($pid, $tipo, (int)($d['id'] ?? 0), false);
            if (!$f) return null;
            return ['tipo' => $tipo, 'id' => (int)$d['id'], 'nombre' => mb_substr($f['nombre'], 0, 255), 'ext' => $f['ext'], 'pagina' => $pagina];
        }
        if ($tipo === 'subido' || $tipo === 'nuevo') {
            $archivo = basename((string)($d['archivo'] ?? ''));
            if (!preg_match('/^[a-f0-9]{32}\.(pdf|png|jpg|jpeg|gif|webp)$/', $archivo)) return null;
            if (!is_file(self::CARPETA . '/' . $archivo)) return null;
            // Una comparación nueva solo puede usar archivos subidos en esta misma petición.
            if (!$existente || !in_array($archivo, $existente->archivos(), true)) return null;
            $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
            return ['tipo' => 'subido', 'archivo' => $archivo, 'nombre' => mb_substr(trim((string)($d['nombre'] ?? $archivo)), 0, 255), 'ext' => $ext, 'pagina' => $pagina];
        }
        return null;
    }

    private static function vistaValida($d): ?array
    {
        if (is_string($d)) $d = json_decode($d, true);
        if (!is_array($d)) return null;
        $f = fn($v, $min, $max, $def) => is_numeric($v) ? max($min, min($max, (float)$v)) : $def;
        return ['x' => $f($d['x'] ?? null, -1e7, 1e7, 0.0), 'y' => $f($d['y'] ?? null, -1e7, 1e7, 0.0), 'k' => $f($d['k'] ?? null, 0.02, 20, 1.0)];
    }

    private static function ajusteValido($d): ?array
    {
        if (is_string($d)) $d = json_decode($d, true);
        if (!is_array($d)) return null;
        $f = fn($v, $min, $max, $def) => is_numeric($v) ? max($min, min($max, (float)$v)) : $def;
        return ['x' => $f($d['x'] ?? null, -1e7, 1e7, 0.0), 'y' => $f($d['y'] ?? null, -1e7, 1e7, 0.0), 's' => $f($d['s'] ?? null, 0.05, 10, 1.0), 'rot' => $f($d['rot'] ?? null, -180, 180, 0.0)];
    }

    private static function modoValido($d): ?array
    {
        if (is_string($d)) $d = json_decode($d, true);
        if (!is_array($d)) return null;
        $modo = in_array($d['modo'] ?? '', ['slider', 'fade', 'diff'], true) ? $d['modo'] : 'slider';
        $dir = ($d['dir'] ?? 'v') === 'h' ? 'h' : 'v';
        return ['modo' => $modo, 'pos' => max(0, min(1, (float)($d['pos'] ?? 0.5))), 'dir' => $dir, 'fade' => max(0, min(100, (int)($d['fade'] ?? 50))), 'tol' => max(0, min(100, (int)($d['tol'] ?? 24)))];
    }

    /**
     * Normaliza las anotaciones que llegan del navegador. Devuelve null si el JSON no es válido.
     * Cada forma usa coordenadas en píxeles del documento A y tamaños base (se escalan al dibujar).
     */
    public static function anotaciones($d): ?array
    {
        if (is_string($d)) {
            if (strlen($d) > self::MAX_JSON_ANOTACIONES) return null;
            $d = json_decode($d, true);
        }
        if (!is_array($d)) return null;
        $shapes = $d['shapes'] ?? [];
        if (!is_array($shapes) || count($shapes) > self::MAX_ANOTACIONES) return null;
        $numero = 0;
        $out = [];
        $escala = is_numeric($d['escala'] ?? null) ? max(0, min(1e6, (float)$d['escala'])) : 0;
        $unidad = mb_substr(trim((string)($d['unidad'] ?? 'm')), 0, 12);
        if ($unidad === '') $unidad = 'm';
        foreach ($shapes as $s) {
            if (!is_array($s)) continue;
            $tipo = (string)($s['tipo'] ?? '');
            if (!in_array($tipo, self::HERRAMIENTAS, true)) continue;
            $color = preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', (string)($s['color'] ?? '')) ? $s['color'] : '#e11d48';
            $base = [
                'tipo' => $tipo, 'color' => $color,
                'grosor' => max(1, min(80, (float)($s['grosor'] ?? 5))),
                'relleno' => !empty($s['relleno']),
                'texto' => mb_substr(trim((string)($s['texto'] ?? '')), 0, 600),
                'autor' => mb_substr(trim((string)($s['autor'] ?? '')), 0, 100),
                'creado' => mb_substr(trim((string)($s['creado'] ?? '')), 0, 30),
                'estado' => ($s['estado'] ?? '') === 'resuelta' ? 'resuelta' : 'abierta',
                'grupo' => max(0, min(1_000_000_000, (int)($s['grupo'] ?? 0))),
                'pagina' => max(1, min(10000, (int)($s['pagina'] ?? 1))),
            ];
            $notas = [];
            foreach (array_slice((array)($s['notas'] ?? []), 0, 50) as $n) {
                if (!is_array($n)) continue;
                $txt = mb_substr(trim((string)($n['texto'] ?? '')), 0, 1000);
                if ($txt === '') continue;
                $notas[] = [
                    'texto' => $txt,
                    'autor' => mb_substr(trim((string)($n['autor'] ?? '')), 0, 100),
                    'fecha' => mb_substr(trim((string)($n['fecha'] ?? '')), 0, 30),
                ];
            }
            $base['notas'] = $notas;
            $n = fn($v, $min, $max, $def = 0) => is_numeric($v) ? max($min, min($max, (float)$v)) : $def;
            switch ($tipo) {
                case 'rect': case 'elipse': case 'nube': case 'resaltado': case 'area':
                    $x = $n($s['x'] ?? null, -200000, 200000, 0); $y = $n($s['y'] ?? null, -200000, 200000, 0);
                    $w = $n($s['w'] ?? null, -200000, 200000, 0); $h = $n($s['h'] ?? null, -200000, 200000, 0);
                    $base += ['x' => min($x, $x + $w), 'y' => min($y, $y + $h), 'w' => abs($w), 'h' => abs($h)];
                    if ($tipo === 'resaltado') $base['relleno'] = true;
                    break;
                case 'flecha': case 'linea': case 'medir':
                    $base += ['x1' => $n($s['x1'] ?? null, -200000, 200000, 0), 'y1' => $n($s['y1'] ?? null, -200000, 200000, 0), 'x2' => $n($s['x2'] ?? null, -200000, 200000, 0), 'y2' => $n($s['y2'] ?? null, -200000, 200000, 0)];
                    break;
                case 'mano':
                    $pts = [];
                    foreach (array_slice((array)($s['pts'] ?? []), 0, 3000) as $p) {
                        if (is_array($p) && count($p) >= 2) $pts[] = [$n($p[0], -200000, 200000, 0), $n($p[1], -200000, 200000, 0)];
                    }
                    if (count($pts) < 2) continue 2;
                    $base['pts'] = $pts;
                    break;
                case 'texto':
                    $base += ['x' => $n($s['x'] ?? null, -200000, 200000, 0), 'y' => $n($s['y'] ?? null, -200000, 200000, 0), 'fuente' => $n($s['fuente'] ?? null, 6, 200, 16)];
                    if ($base['texto'] === '') continue 2;
                    break;
                case 'globo':
                    $numero++;
                    $base += ['x' => $n($s['x'] ?? null, -200000, 200000, 0), 'y' => $n($s['y'] ?? null, -200000, 200000, 0), 'ax' => $n($s['ax'] ?? null, -200000, 200000, 0), 'ay' => $n($s['ay'] ?? null, -200000, 200000, 0), 'numero' => max(1, min(9999, (int)($s['numero'] ?? $numero))), 'radio' => $n($s['radio'] ?? null, 8, 400, 28)];
                    $numero = max($numero, (int)$base['numero']);
                    break;
            }
            $out[] = $base;
        }
        $tamanos = [];
        foreach ((array)($d['tamanos'] ?? []) as $p => $t) {
            if (!is_numeric($p) || !is_array($t) || count($t) < 2) continue;
            $p = (int)$p;
            if ($p < 1 || $p > 10000 || count($tamanos) >= 300) continue;
            $tamanos[$p] = [max(1, min(200000, (int)$t[0])), max(1, min(200000, (int)$t[1]))];
        }
        return ['ancho' => max(1, (int)($d['ancho'] ?? 1)), 'alto' => max(1, (int)($d['alto'] ?? 1)), 'escala' => $escala, 'unidad' => $unidad, 'tamanos' => $tamanos, 'shapes' => $out];
    }

    private static function guardarMiniatura(string $dataUrl): ?string
    {
        $bin = MarcasLienzo::binarioDeDataUrl($dataUrl, 'jpeg|jpg|png');
        if ($bin === false || strlen($bin) > 4 * 1024 * 1024) return null;
        if (!is_dir(self::MINIATURAS) && !@mkdir(self::MINIATURAS, 0775, true)) return null;
        $tmp = tempnam(sys_get_temp_dir(), 'cmp');
        file_put_contents($tmp, $bin);
        try {
            $img = (new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()))
                ->read($tmp)->scaleDown(width: 640, height: 640);
            $nombre = bin2hex(random_bytes(16)) . '.jpg';
            $img->toJpeg(78)->save(self::MINIATURAS . '/' . $nombre);
            return $nombre;
        } catch (\Throwable $e) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }
}
