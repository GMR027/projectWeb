<?php

namespace Classes;

use Model\AnotadorDocumento;
use Model\AnotadorVersion;
use Model\Proyecto;

/** Anotador: abre un PDF o plano, normaliza sus anotaciones (formas, medidas, imágenes, estampas y firmas) y guarda versiones */
class Anotador
{
    use AccesoDb;

    public const CARPETA = __DIR__ . '/../public/uploads/anotador';
    public const MINIATURAS = self::CARPETA . '/min';
    public const MAX_ARCHIVO = 80 * 1024 * 1024;    // 80 MB por documento externo
    public const MAX_IMAGEN = 20 * 1024 * 1024;
    public const MAX_ANOTACIONES = 500;
    public const MAX_JSON_ANOTACIONES = 4_000_000;
    public const MAX_VERSIONES = 20;
    public const TIPOS = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    public const FUENTES_PROYECTO = ['archivo', 'vplan', 'editor', 'redactor'];

    public const HERRAMIENTAS = [
        'globo', 'texto', 'resaltador', 'resaltado', 'rect', 'elipse', 'flecha', 'linea', 'mano', 'nube',
        'medir', 'area', 'poligono', 'volumen', 'imagen', 'estampa', 'firma', 'resaltado_texto', 'subrayado', 'tachado',
    ];

    /** Catálogo de fuentes del proyecto (se reutiliza el del Comparador: Archivo, vPlan, Editor y Redactor) */
    public static function fuentes(int $pid): array
    {
        return Comparador::fuentes($pid);
    }

    public static function fuenteDeProyecto(int $pid, string $tipo, int $id, bool $generar = true): ?array
    {
        return Comparador::fuenteDeProyecto($pid, $tipo, $id, $generar);
    }

    /** Documento que muestra un Anotador guardado */
    public static function fuenteDocumento(AnotadorDocumento $d): ?array
    {
        if ($d->archivo) {
            $n = basename((string)$d->archivo);
            $ruta = self::CARPETA . '/' . $n;
            $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
            if (!is_file($ruta) || !isset(self::TIPOS[$ext])) return null;
            return ['ruta' => $ruta, 'mime' => self::TIPOS[$ext], 'nombre' => (string)($d->nombre_archivo ?: $d->nombre), 'ext' => $ext];
        }
        if (in_array($d->fuente_tipo, self::FUENTES_PROYECTO, true)) {
            return self::fuenteDeProyecto((int)$d->proyecto_id, (string)$d->fuente_tipo, (int)$d->fuente_id);
        }
        return null;
    }

    public static function listar(int $pid): array
    {
        try {
            return AnotadorDocumento::deProyecto($pid);
        } catch (\Throwable $e) {   // migración pendiente
            return [];
        }
    }

    public static function obtener(int $pid, int $id): ?AnotadorDocumento
    {
        try {
            return AnotadorDocumento::deProyectoPorId($pid, $id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function puedeEditar(array $d): bool
    {
        if (!empty($d['finalizado'])) return false;
        return is_admin() || (int)($d['usuario_id'] ?? 0) === (int)($_SESSION['id'] ?? 0);
    }

    /**
     * Crea o actualiza un documento con sus anotaciones.
     * @return array{ok:bool, id?:int, error?:string}
     */
    public static function guardar(Proyecto $p, array $post, array $files = []): array
    {
        $pid = (int)$p->id;
        $id = (int)($post['id'] ?? 0);
        $existente = $id ? self::obtener($pid, $id) : null;
        if ($id && !$existente) return ['ok' => false, 'error' => 'El documento ya no existe.'];
        if ($existente && !self::puedeEditar(['usuario_id' => $existente->usuario_id, 'finalizado' => $existente->finalizado()])) return ['ok' => false, 'error' => 'Este documento está finalizado o no lo puedes editar. Duplícalo para anotarlo.'];

        $an = self::anotaciones($post['anotaciones'] ?? null);
        if ($an === null) return ['ok' => false, 'error' => 'Las anotaciones no son válidas o superan el límite.'];

        $cfg = self::config($post['config'] ?? null);
        if ($existente) {   // la finalización solo la cambia el endpoint /anotador/firmar
            $antes = $existente->config();
            $cfg['finalizado'] = !empty($antes['finalizado']);
            $cfg['firmado_por'] = $antes['firmado_por'] ?? '';
            $cfg['firmado_fecha'] = $antes['firmado_fecha'] ?? '';
            $cfg['firma_hash'] = $antes['firma_hash'] ?? '';
        }

        $nombre = mb_substr(trim((string)($post['nombre'] ?? '')), 0, 150);
        $descripcion = mb_substr(trim((string)($post['descripcion'] ?? '')), 0, 500);
        $paginas = max(1, min(10000, (int)($post['paginas'] ?? 1)));

        // Fuente: copia externa nueva o fuente del proyecto
        $fuente = [
            'fuente_tipo' => $existente ? (string)$existente->fuente_tipo : 'archivo',
            'fuente_id' => $existente ? $existente->fuente_id : null,
            'archivo' => $existente ? $existente->archivo : null,
            'nombre_archivo' => $existente ? (string)$existente->nombre_archivo : '',
            'ext' => $existente ? (string)$existente->ext : '',
        ];
        $f = $files['documento'] ?? null;
        if ($f && (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subido = self::subirDocumento($p, $f);
            if (!$subido['ok']) return $subido;
            $fuente = ['fuente_tipo' => 'subido', 'fuente_id' => null, 'archivo' => $subido['archivo'], 'nombre_archivo' => $subido['nombre'], 'ext' => $subido['ext']];
        } elseif (!$existente) {
            $tipo = (string)($post['fuente_tipo'] ?? '');
            $fid = (int)($post['fuente_id'] ?? 0);
            if (!in_array($tipo, self::FUENTES_PROYECTO, true) || $fid <= 0) {
                return ['ok' => false, 'error' => 'Elige un documento del proyecto o sube un PDF/imagen.'];
            }
            $doc = self::fuenteDeProyecto($pid, $tipo, $fid, false);
            if (!$doc) return ['ok' => false, 'error' => 'El documento elegido no está disponible en este proyecto.'];
            $fuente = ['fuente_tipo' => $tipo, 'fuente_id' => $fid, 'archivo' => null, 'nombre_archivo' => (string)$doc['nombre'], 'ext' => (string)$doc['ext']];
            if ($nombre === '') $nombre = (string)$doc['nombre'];
        }
        if ($nombre === '') $nombre = mb_substr((string)($fuente['nombre_archivo'] ?: 'Documento'), 0, 150);
        if ($fuente['ext'] === '') $fuente['ext'] = strtolower(pathinfo((string)$fuente['nombre_archivo'], PATHINFO_EXTENSION)) ?: 'pdf';
        if (!isset(self::TIPOS[$fuente['ext']])) return ['ok' => false, 'error' => 'El documento debe ser PDF o una imagen (png, jpg, webp, gif).'];

        $miniatura = self::guardarMiniatura((string)($post['miniatura'] ?? ''), $existente ? (string)$existente->miniatura : '');

        $datos = [
            'proyecto_id' => $pid, 'nombre' => $nombre, 'descripcion' => $descripcion,
            'fuente_tipo' => $fuente['fuente_tipo'], 'fuente_id' => $fuente['fuente_id'],
            'archivo' => $fuente['archivo'], 'nombre_archivo' => $fuente['nombre_archivo'], 'ext' => $fuente['ext'],
            'paginas' => $paginas, 'config_json' => json_encode($cfg, JSON_UNESCAPED_UNICODE),
            'anotaciones_json' => json_encode($an, JSON_UNESCAPED_UNICODE),
            'miniatura' => $miniatura,
            'creado_por' => $existente ? (string)$existente->creado_por : mb_substr((string)($_SESSION['nombre'] ?? ''), 0, 150),
            'usuario_id' => $existente ? $existente->usuario_id : ((int)($_SESSION['id'] ?? 0) ?: null),
        ];

        if ($existente) {
            self::guardarVersion($existente);
            foreach ($datos as $c => $v) $existente->$c = $v;
            $r = $existente->guardar();
            if (isset($r['error'])) return ['ok' => false, 'error' => 'No se pudo guardar el documento.'];
            return ['ok' => true, 'id' => (int)$existente->id];
        }
        $d = new AnotadorDocumento($datos);
        $r = $d->guardar();
        if (isset($r['error'])) return ['ok' => false, 'error' => 'No se pudo guardar el documento.'];
        return ['ok' => true, 'id' => (int)$r['id']];
    }

    /** Copia externa de un PDF o imagen (se sube solo al guardar) */
    private static function subirDocumento(Proyecto $p, array $f): array
    {
        if ((int)($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'No se pudo subir el documento.'];
        if (empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) return ['ok' => false, 'error' => 'El archivo no llegó al servidor.'];
        $nombre = basename((string)($f['name'] ?? 'documento'));
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!isset(self::TIPOS[$ext])) return ['ok' => false, 'error' => 'El documento debe ser PDF o imagen (png, jpg, webp, gif).'];
        if ((int)$f['size'] > self::MAX_ARCHIVO) return ['ok' => false, 'error' => 'El documento supera 80 MB.'];
        if (!Almacenamiento::cabe((int)$p->id, (int)$f['size'])) return ['ok' => false, 'error' => Almacenamiento::mensajeLleno((int)$p->id)];
        foreach (EscanerArchivos::revisar($f['tmp_name'], $nombre) as $motivo) return ['ok' => false, 'error' => 'Documento: ' . $motivo];
        if (!is_dir(self::CARPETA) && !@mkdir(self::CARPETA, 0775, true)) return ['ok' => false, 'error' => 'No se pudo crear la carpeta del Anotador.'];
        if ($ext !== 'pdf') {
            $guardado = Imagenes::guardarEn('anotador', $f['tmp_name'], 5000, 92);
            if (!$guardado) return ['ok' => false, 'error' => 'La imagen no es válida.'];
            return ['ok' => true, 'archivo' => $guardado, 'nombre' => mb_substr($nombre, 0, 255), 'ext' => strtolower(pathinfo($guardado, PATHINFO_EXTENSION)) ?: $ext];
        }
        $archivo = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($f['tmp_name'], self::CARPETA . '/' . $archivo)) return ['ok' => false, 'error' => 'No se pudo guardar el PDF.'];
        return ['ok' => true, 'archivo' => $archivo, 'nombre' => mb_substr($nombre, 0, 255), 'ext' => 'pdf'];
    }

    /** Guarda una imagen insertada (multipart o data URI) y devuelve nombre, URL y tamaño natural */
    public static function guardarImagen(Proyecto $p, array $files, array $post): ?array
    {
        $f = $files['imagen'] ?? null;
        $tmp = null;
        if ($f && (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name'])) {
            if ((int)$f['size'] > self::MAX_IMAGEN) return null;
            $tmp = $f['tmp_name'];
        } elseif (!empty($post['png']) && is_string($post['png'])) {
            $bin = MarcasLienzo::binarioDeDataUrl($post['png'], 'png|jpeg|jpg|webp');
            if ($bin === false || strlen($bin) > self::MAX_IMAGEN) return null;
            $tmp = tempnam(sys_get_temp_dir(), 'ant');
            file_put_contents($tmp, $bin);
        }
        if (!$tmp) return null;
        try {
            if (!Almacenamiento::cabe((int)$p->id, (int)filesize($tmp))) return null;
            $nombre = Imagenes::guardarEn('anotador', $tmp, 3000, 90);
            if (!$nombre) return null;
            $info = @getimagesize(self::CARPETA . '/' . $nombre);
            return ['archivo' => $nombre, 'url' => '/anotador/imagen?proyecto=' . (int)$p->id . '&archivo=' . $nombre, 'w' => $info[0] ?? 0, 'h' => $info[1] ?? 0];
        } finally {
            if (isset($post['png'])) @unlink($tmp);
        }
    }

    /** Guarda una firma: copia visible del proyecto + biblioteca personal del Redactor */
    public static function guardarFirma(Proyecto $p, string $nombre, string $dataUri, int $usuarioId): array|string
    {
        $bin = MarcasLienzo::binarioDeDataUrl($dataUri, 'png');
        if ($bin === false || strlen($bin) > 1_500_000) return 'La firma no es válida.';
        $info = @getimagesizefromstring($bin);
        if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 1600 || $info[1] > 800) return 'La firma debe ser un PNG de hasta 1600 × 800 px.';
        if (!Almacenamiento::cabe((int)$p->id, strlen($bin))) return Almacenamiento::mensajeLleno((int)$p->id);
        $tmp = tempnam(sys_get_temp_dir(), 'antf');
        file_put_contents($tmp, $bin);
        try {
            $archivo = Imagenes::guardarEn('anotador', $tmp, 1200, 90);
        } finally {
            @unlink($tmp);
        }
        if (!$archivo) return 'No se pudo guardar la firma.';
        $info = @getimagesize(self::CARPETA . '/' . $archivo);
        $biblioteca = RedactorFirmas::guardar($usuarioId, $nombre, $dataUri);
        $guardada = is_array($biblioteca) ? $biblioteca : null;
        return ['archivo' => $archivo, 'url' => '/anotador/imagen?proyecto=' . (int)$p->id . '&archivo=' . $archivo, 'w' => $info[0] ?? 0, 'h' => $info[1] ?? 0, 'biblioteca' => $guardada];
    }

    /** Miniatura de la página actual o de la primera (data URI JPEG) */
    private static function guardarMiniatura(string $dataUrl, string $anterior = ''): ?string
    {
        $bin = MarcasLienzo::binarioDeDataUrl($dataUrl, 'jpeg|jpg|png');
        if ($bin === false || strlen($bin) > 4 * 1024 * 1024) return $anterior ?: null;
        if (!is_dir(self::MINIATURAS) && !@mkdir(self::MINIATURAS, 0775, true)) return $anterior ?: null;
        $tmp = tempnam(sys_get_temp_dir(), 'antm');
        file_put_contents($tmp, $bin);
        try {
            $img = (new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()))
                ->read($tmp)->scaleDown(width: 640, height: 640);
            $nombre = bin2hex(random_bytes(16)) . '.jpg';
            $img->toJpeg(78)->save(self::MINIATURAS . '/' . $nombre);
            if ($anterior && $anterior !== $nombre) @unlink(self::MINIATURAS . '/' . basename($anterior));
            return $nombre;
        } catch (\Throwable $e) {
            return $anterior ?: null;
        } finally {
            @unlink($tmp);
        }
    }

    private static function guardarVersion(AnotadorDocumento $d): void
    {
        try {
            $v = new AnotadorVersion([
                'proyecto_id' => (int)$d->proyecto_id, 'documento_id' => (int)$d->id, 'nombre' => (string)$d->nombre,
                'config_json' => (string)$d->config_json, 'anotaciones_json' => (string)$d->anotaciones_json,
                'miniatura' => $d->miniatura, 'creado_por' => (string)$d->creado_por,
            ]);
            $v->guardar();
            $r = self::db()->query('SELECT id FROM anotador_versiones WHERE documento_id = ' . (int)$d->id . ' ORDER BY id DESC');
            $ids = [];
            while ($r && ($f = $r->fetch_assoc())) $ids[] = (int)$f['id'];
            foreach (array_slice($ids, self::MAX_VERSIONES) as $viejo) self::db()->query('DELETE FROM anotador_versiones WHERE id = ' . $viejo);
        } catch (\Throwable $e) {   // sin tabla de versiones no se bloquea el guardado
        }
    }

    public static function versiones(int $pid, int $id): array
    {
        try {
            $d = self::obtener($pid, $id);
            if (!$d) return [];
            return array_map(fn($v) => ['id' => (int)$v->id, 'nombre' => (string)$v->nombre, 'autor' => (string)$v->creado_por, 'creado' => (string)$v->creado, 'formas' => (int)($v->formas ?? 0)], AnotadorVersion::deDocumento($id));
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function restaurarVersion(int $pid, int $id, int $vid): ?array
    {
        $d = self::obtener($pid, $id);
        if (!$d || !self::puedeEditar($d->paraJs())) return null;
        $v = AnotadorVersion::find($vid);
        if (!$v || (int)$v->documento_id !== $id || (int)$v->proyecto_id !== $pid) return null;
        self::guardarVersion($d);
        $d->config_json = $v->config_json;
        $d->anotaciones_json = $v->anotaciones_json;
        $d->guardar();
        return ['ok' => true];
    }

    public static function eliminar(int $pid, int $id): bool
    {
        $d = self::obtener($pid, $id);
        if (!$d) return false;
        $archivos = $d->archivos();
        $min = (string)$d->miniatura;
        $ok = $d->eliminar();
        if (!$ok) return false;
        foreach ($archivos as $f) if (!self::archivoEnUso($pid, $f, $id)) @unlink(self::CARPETA . '/' . basename($f));
        if ($min && !self::miniaturaEnUso($pid, $min)) @unlink(self::MINIATURAS . '/' . basename($min));
        return true;
    }

    public static function duplicar(Proyecto $p, AnotadorDocumento $d): ?int
    {
        $cfg = $d->config();
        unset($cfg['finalizado'], $cfg['firmado_por'], $cfg['firmado_fecha'], $cfg['firma_hash']);
        $n = new AnotadorDocumento([
            'proyecto_id' => (int)$p->id, 'nombre' => mb_substr((string)$d->nombre . ' (copia)', 0, 150), 'descripcion' => (string)$d->descripcion,
            'fuente_tipo' => (string)$d->fuente_tipo, 'fuente_id' => $d->fuente_id, 'archivo' => $d->archivo,
            'nombre_archivo' => (string)$d->nombre_archivo, 'ext' => (string)$d->ext, 'paginas' => (int)$d->paginas,
            'config_json' => json_encode($cfg, JSON_UNESCAPED_UNICODE), 'anotaciones_json' => (string)$d->anotaciones_json,
            'miniatura' => $d->miniatura, 'creado_por' => mb_substr((string)($_SESSION['nombre'] ?? ''), 0, 150),
            'usuario_id' => (int)($_SESSION['id'] ?? 0) ?: null,
        ]);
        $r = $n->guardar();
        return isset($r['id']) ? (int)$r['id'] : null;
    }

    /** Borra imágenes/firmas/estampas y copias externas que ya no usa ningún documento ni versión (más de 24 h) */
    public static function limpiarHuerfanos(int $pid): void
    {
        if (!is_dir(self::CARPETA)) return;
        $limite = time() - 86400;
        foreach (glob(self::CARPETA . '/*') ?: [] as $ruta) {
            if (!is_file($ruta)) continue;
            $n = basename($ruta);
            if (!preg_match('/^[a-f0-9]{32}\.(?:png|jpg|jpeg|webp|gif|pdf)$/i', $n)) continue;
            if (@filemtime($ruta) > $limite) continue;
            $a = self::esc($n);
            try {
                $enDoc = self::filas("SELECT id FROM anotador_documentos WHERE proyecto_id = $pid AND (archivo = '$a' OR anotaciones_json LIKE '%$a%') LIMIT 1");
                $enVer = self::filas("SELECT id FROM anotador_versiones WHERE proyecto_id = $pid AND anotaciones_json LIKE '%$a%' LIMIT 1");
                if (!$enDoc && !$enVer) @unlink($ruta);
            } catch (\Throwable $e) {
            }
        }
    }

    /** ¿Algún otro documento del proyecto usa este archivo? */
    private static function archivoEnUso(int $pid, string $archivo, int $excepto): bool
    {
        $a = self::esc(basename($archivo));
        $r = self::filas("SELECT id FROM anotador_documentos WHERE proyecto_id = $pid AND id <> $excepto AND (archivo = '$a' OR anotaciones_json LIKE '%$a%') LIMIT 1");
        return !empty($r);
    }
    private static function miniaturaEnUso(int $pid, string $min): bool
    {
        $m = self::esc(basename($min));
        return !empty(self::filas("SELECT id FROM anotador_documentos WHERE proyecto_id = $pid AND miniatura = '$m' LIMIT 1"));
    }

    public static function bytes(int $pid): int
    {
        $t = 0;
        $vistos = [];
        foreach (self::listar($pid) as $d) {
            foreach ($d->archivos() as $f) $vistos[$f] = true;
            if ($d->miniatura) $t += (int)@filesize(self::MINIATURAS . '/' . basename((string)$d->miniatura));
        }
        try {
            foreach (self::filas("SELECT anotaciones_json FROM anotador_versiones WHERE proyecto_id = $pid") as $v) {
                if (preg_match_all('/[a-f0-9]{32}\.(?:png|jpg|jpeg|webp|gif|pdf)/i', (string)$v['anotaciones_json'], $m)) {
                    foreach ($m[0] as $f) $vistos[strtolower($f)] = true;
                }
            }
        } catch (\Throwable $e) {
        }
        foreach (array_keys($vistos) as $f) $t += (int)@filesize(self::CARPETA . '/' . basename($f));
        return $t;
    }

    /** Normaliza la configuración (escala por página, unidad y estado de firma) */
    public static function config($d): array
    {
        if (is_string($d)) {
            if (strlen($d) > 1_000_000) $d = '{}';
            $d = json_decode($d, true);
        }
        if (!is_array($d)) $d = [];
        $escalas = [];
        foreach ((array)($d['escalas'] ?? []) as $p => $v) {
            if (!is_numeric($p) || !is_numeric($v)) continue;
            $p = (int)$p;
            if ($p < 1 || $p > 10000 || count($escalas) > 300) continue;
            $v = (float)$v;
            if ($v > 0) $escalas[$p] = max(1e-9, min(1e6, $v));
        }
        $unidad = mb_substr(trim((string)($d['unidad'] ?? 'm')), 0, 12) ?: 'm';
        return ['escalas' => $escalas, 'unidad' => $unidad];
    }

    /**
     * Normaliza las anotaciones que llegan del navegador. Devuelve null si el JSON no es válido.
     * Coordenadas en píxeles del documento base; la escala real vive por página en config.escalas.
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
        $n = fn($v, $min, $max, $def = 0) => is_numeric($v) ? max($min, min($max, (float)$v)) : $def;
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
            foreach (array_slice((array)($s['notas'] ?? []), 0, 50) as $nota) {
                if (!is_array($nota)) continue;
                $txt = mb_substr(trim((string)($nota['texto'] ?? '')), 0, 1000);
                if ($txt === '') continue;
                $notas[] = ['texto' => $txt, 'autor' => mb_substr(trim((string)($nota['autor'] ?? '')), 0, 100), 'fecha' => mb_substr(trim((string)($nota['fecha'] ?? '')), 0, 30)];
            }
            $base['notas'] = $notas;
            $pts = fn($lista) => array_values(array_filter(array_map(function ($p) use ($n) {
                return is_array($p) && count($p) >= 2 ? [$n($p[0], -200000, 200000), $n($p[1], -200000, 200000)] : null;
            }, array_slice((array)$lista, 0, 800))));
            switch ($tipo) {
                case 'rect': case 'elipse': case 'nube': case 'resaltado': case 'area':
                    $x = $n($s['x'] ?? null, -200000, 200000); $y = $n($s['y'] ?? null, -200000, 200000);
                    $w = $n($s['w'] ?? null, -200000, 200000); $h = $n($s['h'] ?? null, -200000, 200000);
                    $base += ['x' => min($x, $x + $w), 'y' => min($y, $y + $h), 'w' => abs($w), 'h' => abs($h)];
                    if ($tipo === 'resaltado') $base['relleno'] = true;
                    break;
                case 'flecha': case 'linea': case 'medir':
                    $base += ['x1' => $n($s['x1'] ?? null, -200000, 200000), 'y1' => $n($s['y1'] ?? null, -200000, 200000), 'x2' => $n($s['x2'] ?? null, -200000, 200000), 'y2' => $n($s['y2'] ?? null, -200000, 200000)];
                    break;
                case 'mano': case 'resaltador':
                    $p = $pts($s['pts'] ?? []);
                    if (count($p) < 2) continue 2;
                    $base['pts'] = $p;
                    break;
                case 'texto':
                    $base += ['x' => $n($s['x'] ?? null, -200000, 200000), 'y' => $n($s['y'] ?? null, -200000, 200000), 'fuente' => $n($s['fuente'] ?? null, 6, 300, 16)];
                    if ($base['texto'] === '') continue 2;
                    break;
                case 'globo':
                    $numero++;
                    $base += ['x' => $n($s['x'] ?? null, -200000, 200000), 'y' => $n($s['y'] ?? null, -200000, 200000), 'ax' => $n($s['ax'] ?? null, -200000, 200000), 'ay' => $n($s['ay'] ?? null, -200000, 200000), 'numero' => max(1, min(9999, (int)($s['numero'] ?? $numero))), 'radio' => $n($s['radio'] ?? null, 8, 400, 28)];
                    $numero = max($numero, (int)$base['numero']);
                    break;
                case 'poligono': case 'volumen':
                    $p = $pts($s['pts'] ?? []);
                    if (count($p) < 3) continue 2;
                    $base['pts'] = $p;
                    $base['cerrado'] = ($s['cerrado'] ?? true) !== false;
                    $base['profundidad'] = $n($s['profundidad'] ?? null, 0, 10000, 0);
                    $base['etiqueta'] = ($s['etiqueta'] ?? '') === 'perimetro' ? 'perimetro' : 'area';
                    break;
                case 'imagen': case 'estampa': case 'firma':
                    $base += [
                        'x' => $n($s['x'] ?? null, -200000, 200000), 'y' => $n($s['y'] ?? null, -200000, 200000),
                        'w' => $n($s['w'] ?? null, 1, 200000, 200), 'h' => $n($s['h'] ?? null, 1, 200000, 200),
                        'rot' => $n($s['rot'] ?? null, -360, 360, 0), 'opacidad' => $n($s['opacidad'] ?? null, .05, 1, 1),
                    ];
                    $img = basename((string)($s['imagen'] ?? $s['archivo'] ?? ''));
                    if ($img !== '' && preg_match('/^[a-f0-9]{32}\.(?:png|jpg|jpeg|webp|gif)$/i', $img)) $base['imagen'] = strtolower($img);
                    if ($tipo === 'estampa') $base['estampa_id'] = max(0, (int)($s['estampa_id'] ?? 0));
                    if ($tipo === 'firma') {
                        $base['nombre'] = mb_substr(trim((string)($s['nombre'] ?? '')), 0, 150);
                        $base['cargo'] = mb_substr(trim((string)($s['cargo'] ?? '')), 0, 150);
                        $base['fecha'] = mb_substr(trim((string)($s['fecha'] ?? '')), 0, 30);
                        $base['bloqueada'] = !empty($s['bloqueada']);
                        $base['firma_id'] = max(0, (int)($s['firma_id'] ?? 0));
                    }
                    if (empty($base['imagen'])) continue 2;
                    break;
                case 'resaltado_texto': case 'subrayado': case 'tachado':
                    $rects = [];
                    foreach (array_slice((array)($s['rects'] ?? []), 0, 200) as $r) {
                        if (!is_array($r) || count($r) < 4) continue;
                        $w = abs($n($r[2], -200000, 200000)); $h = abs($n($r[3], -200000, 200000));
                        if ($w < 1 || $h < 1) continue;
                        $rects[] = [$n($r[0], -200000, 200000), $n($r[1], -200000, 200000), $w, $h];
                    }
                    if (!$rects) continue 2;
                    $base['rects'] = $rects;
                    $base['texto'] = mb_substr(trim((string)($s['texto'] ?? '')), 0, 2000);
                    $base['opacidad'] = $n($s['opacidad'] ?? null, .1, .7, .32);
                    break;
            }
            $out[] = $base;
        }
        $escalas = [];
        foreach ((array)($d['escalas'] ?? []) as $p => $v) {
            if (!is_numeric($p) || !is_numeric($v)) continue;
            $p = (int)$p;
            if ($p < 1 || $p > 10000 || count($escalas) > 300) continue;
            if ((float)$v > 0) $escalas[$p] = max(1e-9, min(1e6, (float)$v));
        }
        return [
            'version' => 2,
            'ancho' => max(1, (int)($d['ancho'] ?? 1)),
            'alto' => max(1, (int)($d['alto'] ?? 1)),
            'escalas' => $escalas,
            'unidad' => mb_substr(trim((string)($d['unidad'] ?? 'm')), 0, 12) ?: 'm',
            'shapes' => $out,
        ];
    }

    /** Finaliza la firma: exige al menos una firma colocada, sella el estado y deja el documento bloqueado */
    public static function finalizar(Proyecto $p, AnotadorDocumento $d, string $cargo = ''): array
    {
        if (!$d->id) return ['ok' => false, 'error' => 'Guarda el documento antes de firmarlo.'];
        if ($d->finalizado()) return ['ok' => false, 'error' => 'El documento ya está firmado y bloqueado.'];
        $an = $d->anotaciones();
        $firmas = array_values(array_filter((array)($an['shapes'] ?? []), fn($s) => ($s['tipo'] ?? '') === 'firma'));
        if (!$firmas) return ['ok' => false, 'error' => 'Coloca al menos una firma antes de finalizar.'];
        self::guardarVersion($d);
        $usuario = (int)($_SESSION['id'] ?? 0);
        $nombre = trim((string)($_SESSION['nombre'] ?? ''));
        $cfg = $d->config();
        $hash = hash('sha256', (string)$d->anotaciones_json . '|' . json_encode($cfg));
        $cfg['finalizado'] = true;
        $cfg['firmado_por'] = $nombre;
        $cfg['firmado_fecha'] = date('c');
        $cfg['firma_hash'] = $hash;
        $d->config_json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
        $d->guardar();
        try {
            $fpid = (int)$p->id; $fdid = (int)$d->id;
            foreach ($firmas as $f) {
                $n = self::esc(mb_substr((string)($f['nombre'] ?? $nombre), 0, 150));
                $c = self::esc(mb_substr((string)($f['cargo'] ?? $cargo), 0, 150));
                $pg = max(1, (int)($f['pagina'] ?? 1));
                self::db()->query("INSERT INTO anotador_firmas_aplicadas (proyecto_id, documento_id, usuario_id, nombre, cargo, pagina, hash) VALUES ($fpid, $fdid, $usuario, '$n', '$c', $pg, '$hash')");
            }
        } catch (\Throwable $e) {
            error_log('Anotador · registro de firma: ' . $e->getMessage());
        }
        return ['ok' => true, 'hash' => $hash, 'firmado' => $nombre, 'fecha' => $cfg['firmado_fecha']];
    }

    /** Reabre un documento firmado (solo administrador) */
    public static function reabrir(Proyecto $p, AnotadorDocumento $d): bool
    {
        if (!is_admin()) return false;
        $cfg = $d->config();
        $cfg['finalizado'] = false;
        $d->config_json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
        return !isset($d->guardar()['error']);
    }
}
