<?php

namespace Model;

use Classes\Imagenes;

/** Documento del Redactor: texto con formato (HTML saneado) más nombre, fecha, departamento y ajustes de página */
class RedactorDocumento extends ActiveRecord
{
    protected static $tabla = 'redactor_documentos';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'fecha', 'departamento', 'idioma', 'papel', 'orientacion', 'margen_sup', 'margen_inf', 'margen_izq', 'margen_der', 'numerar', 'encabezado', 'rastrear', 'marca_agua', 'encabezado_texto', 'pie_texto', 'contenido', 'comentarios', 'creado_por'];

    public $id, $proyecto_id, $nombre, $fecha, $departamento, $idioma, $papel, $orientacion;
    public $margen_sup, $margen_inf, $margen_izq, $margen_der, $numerar, $encabezado, $rastrear, $marca_agua, $encabezado_texto, $pie_texto, $contenido, $comentarios, $creado_por;
    public $creado, $actualizado;   // los pone la BD

    /** Papeles [clave => [etiqueta, ancho cm, alto cm]] (vertical) */
    public const PAPELES = [
        'carta' => ['Carta 21.6 × 27.9 cm', 21.59, 27.94],
        'a4' => ['A4 21 × 29.7 cm', 21.0, 29.7],
        'oficio' => ['Oficio 21.6 × 33 cm', 21.59, 33.02],
        'tabloide' => ['Tabloide 27.9 × 43.2 cm', 27.94, 43.18],
    ];
    public const IDIOMAS = ['es' => 'Español', 'en' => 'English', 'es+en' => 'Español + English'];
    public const MARGEN_MIN = 0.0;
    public const MARGEN_MAX = 8.0;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = trim((string)($args['nombre'] ?? ''));
        $this->fecha = (string)($args['fecha'] ?? date('Y-m-d'));
        $this->departamento = trim((string)($args['departamento'] ?? ''));
        $this->idioma = isset(self::IDIOMAS[$args['idioma'] ?? '']) ? $args['idioma'] : 'es';
        $this->papel = isset(self::PAPELES[$args['papel'] ?? '']) ? $args['papel'] : 'carta';
        $this->orientacion = ($args['orientacion'] ?? '') === 'horizontal' ? 'horizontal' : 'vertical';
        foreach (['sup', 'inf', 'izq', 'der'] as $l) $this->{'margen_' . $l} = self::margen($args['margen_' . $l] ?? 2.54);
        $this->numerar = !empty($args['numerar']) || !array_key_exists('numerar', $args) ? 1 : 0;
        $this->encabezado = !empty($args['encabezado']) ? 1 : 0;
        $this->rastrear = !empty($args['rastrear']) ? 1 : 0;
        $this->marca_agua = mb_substr(trim(preg_replace('/[<>"\x00-\x1f]/u', '', (string)($args['marca_agua'] ?? ''))), 0, 40);
        $this->encabezado_texto = mb_substr(trim(preg_replace('/[<>"\x00-\x1f]/u', '', (string)($args['encabezado_texto'] ?? ''))), 0, 200);
        $this->pie_texto = mb_substr(trim(preg_replace('/[<>"\x00-\x1f]/u', '', (string)($args['pie_texto'] ?? ''))), 0, 200);
        $this->contenido = $args['contenido'] ?? '';
        $this->comentarios = $args['comentarios'] ?? null;
        $this->creado_por = $args['creado_por'] ?? null;
    }

    /** Margen en cm siempre dentro de rango (acepta coma decimal) */
    public static function margen($v): float
    {
        $n = is_numeric(str_replace(',', '.', (string)$v)) ? (float)str_replace(',', '.', (string)$v) : 2.54;
        return round(max(self::MARGEN_MIN, min(self::MARGEN_MAX, $n)), 2);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre es obligatorio';
        if (mb_strlen($this->nombre) > 150) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 150)';
        if (mb_strlen($this->departamento) > 100) self::$alertas['error'][] = 'El departamento es demasiado largo (máx. 100)';
        $f = \DateTime::createFromFormat('Y-m-d', $this->fecha);
        if (!$f || $f->format('Y-m-d') !== $this->fecha) self::$alertas['error'][] = 'La fecha no es válida';
        return self::$alertas;
    }

    /** Medidas útiles de la hoja en cm: [ancho, alto] según papel y orientación */
    public function medidas(): array
    {
        [, $w, $h] = self::PAPELES[$this->papel];
        return $this->orientacion === 'horizontal' ? [$h, $w] : [$w, $h];
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM redactor_documentos WHERE proyecto_id = $proyectoId ORDER BY departamento ASC, fecha DESC, id DESC");
    }

    /** Departamentos sugeridos: los de la bitácora del proyecto y los ya usados por documentos */
    public static function departamentos(int $proyectoId): array
    {
        return \Classes\Catalogo::departamentos($proyectoId);
    }

    /** Palabras agregadas por el usuario al diccionario del proyecto */
    public static function palabras(int $proyectoId): array
    {
        $out = [];
        try {
            $r = self::$db->query("SELECT palabra FROM redactor_palabras WHERE proyecto_id = $proyectoId ORDER BY palabra");
            while ($r && ($f = $r->fetch_assoc())) $out[] = $f['palabra'];
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public static function agregarPalabra(int $proyectoId, string $palabra): bool
    {
        $palabra = trim($palabra);
        if ($palabra === '' || mb_strlen($palabra) > 60 || !preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\'’-]*$/u', $palabra)) return false;
        return self::ejecutar("INSERT IGNORE INTO redactor_palabras (proyecto_id, palabra) VALUES ($proyectoId, '" . self::escapar($palabra) . "')");
    }

    /** Imágenes subidas que el contenido usa (nombres de archivo en uploads/vplan) */
    public static function imagenesDe(?string $html): array
    {
        preg_match_all('#/uploads/vplan/([a-f0-9]{32}\.(?:png|jpg))#', (string)$html, $m);
        return array_values(array_unique($m[1]));
    }

    /** Borra del disco las imágenes que el contenido usa y ningún otro documento del proyecto conserva */
    public function eliminarArchivos(): void
    {
        $otros = self::imagenesDeProyecto((int)$this->proyecto_id, (int)$this->id);
        foreach (array_diff(self::imagenesDe($this->contenido), $otros) as $n) Imagenes::eliminarVplan($n);
    }

    private static function imagenesDeProyecto(int $proyectoId, int $excluir): array
    {
        $out = [];
        $r = self::$db->query("SELECT contenido FROM redactor_documentos WHERE proyecto_id = $proyectoId AND id <> $excluir");
        while ($r && ($f = $r->fetch_assoc())) $out = array_merge($out, self::imagenesDe($f['contenido']));
        return $out;
    }

    /** Peso aproximado: el texto más las imágenes que usa */
    public function bytes(): int
    {
        $t = strlen((string)$this->contenido) + strlen((string)$this->comentarios);
        foreach (self::imagenesDe($this->contenido) as $n) {
            $ruta = Imagenes::CARPETA_VPLAN . '/' . $n;
            if (is_file($ruta)) $t += (int)@filesize($ruta);
        }
        return $t;
    }

    // ---------- Historial de versiones ----------
    public const MAX_VERSIONES = 40;

    /** Guarda una copia del contenido; conserva solo las últimas MAX_VERSIONES del documento */
    public static function guardarVersion(int $documentoId, string $contenido, string $usuario, string $etiqueta = ''): void
    {
        try {
            self::ejecutar("INSERT INTO redactor_versiones (documento_id, etiqueta, usuario, contenido) VALUES ($documentoId, '" . self::escapar(mb_substr($etiqueta, 0, 80)) . "', '" . self::escapar(mb_substr($usuario, 0, 100)) . "', '" . self::escapar($contenido) . "')");
            self::ejecutar("DELETE FROM redactor_versiones WHERE documento_id = $documentoId AND id NOT IN (SELECT id FROM (SELECT id FROM redactor_versiones WHERE documento_id = $documentoId ORDER BY id DESC LIMIT " . self::MAX_VERSIONES . ") t)");
        } catch (\Throwable $e) {   // migración del historial pendiente
            error_log('Redactor versiones: ' . $e->getMessage());
        }
    }

    /** Fecha (Y-m-d H:i:s) de la última versión guardada, o null */
    public static function ultimaVersion(int $documentoId): ?string
    {
        try {
            $r = self::$db->query("SELECT creado FROM redactor_versiones WHERE documento_id = $documentoId ORDER BY id DESC LIMIT 1");
            $f = $r ? $r->fetch_assoc() : null;
            return $f['creado'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function versiones(int $documentoId): array
    {
        $out = [];
        try {
            $r = self::$db->query("SELECT id, etiqueta, usuario, creado, CHAR_LENGTH(contenido) AS tam FROM redactor_versiones WHERE documento_id = $documentoId ORDER BY id DESC");
            while ($r && ($f = $r->fetch_assoc())) $out[] = $f;
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public static function version(int $documentoId, int $versionId): ?array
    {
        try {
            $r = self::$db->query("SELECT id, etiqueta, usuario, creado, contenido FROM redactor_versiones WHERE documento_id = $documentoId AND id = $versionId");
            return $r ? ($r->fetch_assoc() ?: null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Partidas del proyecto con sus actividades, para insertarlas como tabla en un documento */
    /** Tablas adicionales del proyecto para insertar en el documento: columnas + filas de texto */
    public static function tablaExtra(int $proyectoId, string $tipo): ?array
    {
        if (preg_match('/^sf:(\d+)$/', $tipo, $m)) {   // tabla de SheetFlow del mismo proyecto
            $h = SheetflowHoja::find((int)$m[1]);
            if (!$h || (int)$h->proyecto_id !== $proyectoId) return null;
            $t = \Classes\Sheetflow::matriz($h);
            return ['columnas' => $t['columnas'], 'filas' => $t['totales'] ? array_merge($t['filas'], [$t['totales']]) : $t['filas'], 'html' => \Classes\Sheetflow::htmlRedactor($h)];
        }
        $fecha = fn($f) => $f ? date('d/m/Y', strtotime($f)) : '';
        $q = match ($tipo) {
            'materiales' => ['cols' => ['Referencia', 'Material', 'Especialidad', 'Cantidad', 'Unidad', 'Compra', 'Entrega prog.'],
                'sql' => "SELECT referencia, nombre, especialidad, cantidad, unidad, compra, fecha_entrega_prog FROM materiales WHERE proyecto_id = $proyectoId ORDER BY especialidad, nombre",
                'fila' => fn($r) => [$r['referencia'], $r['nombre'], $r['especialidad'], $r['cantidad'] === null ? '' : rtrim(rtrim($r['cantidad'], '0'), '.'), $r['unidad'], $r['compra'], $fecha($r['fecha_entrega_prog'])]],
            'restricciones' => ['cols' => ['Código', 'Tipo', 'Actividad', 'Descripción', 'Fecha', 'Estado'],
                'sql' => "SELECT r.codigo, t.nombre AS tipo, a.nombre AS actividad, r.descripcion, r.fecha, r.estado FROM restricciones r
                    JOIN actividades a ON a.id = r.actividad_id JOIN partidas p ON p.id = a.partida_id JOIN tipos_restriccion t ON t.id = r.tipo_id
                    WHERE p.proyecto_id = $proyectoId ORDER BY r.estado, r.fecha, r.id",
                'fila' => fn($r) => [$r['codigo'], $r['tipo'], $r['actividad'], $r['descripcion'], $fecha($r['fecha']), $r['estado']]],
            'festivos' => ['cols' => ['Fecha', 'Motivo', 'Tipo'],
                'sql' => "SELECT fecha, motivo, tipo FROM calendario_festivos WHERE proyecto_id = $proyectoId ORDER BY fecha",
                'fila' => fn($r) => [$fecha($r['fecha']), $r['motivo'], $r['tipo']]],
            'punchlist' => ['cols' => ['Pendiente', 'Ubicación', 'Categoría', 'Severidad', 'Responsable', 'Fecha límite', 'Estado'],
                'sql' => "SELECT titulo, ubicacion, categoria, severidad, responsable, fecha_limite, estado FROM punch_items WHERE proyecto_id = $proyectoId ORDER BY FIELD(estado,'abierto','en_proceso','corregido','cerrado','rechazado'), fecha_limite, id",
                'fila' => fn($r) => [$r['titulo'], $r['ubicacion'], $r['categoria'], $r['severidad'], $r['responsable'], $fecha($r['fecha_limite']), str_replace('_', ' ', $r['estado'])]],
            'submittals' => ['cols' => ['Número', 'Fecha', 'Especialidad', 'Para', 'Estatus', 'Respuesta'],
                'sql' => "SELECT numero_submittal, fecha, especialidad, para, estatus, fecha_respuesta FROM submittals WHERE proyecto_id = $proyectoId ORDER BY fecha, id",
                'fila' => fn($r) => [$r['numero_submittal'], $fecha($r['fecha']), $r['especialidad'], $r['para'], str_replace('_', ' ', $r['estatus']), $fecha($r['fecha_respuesta'])]],
            'ordenes' => ['cols' => ['Número', 'Título', 'Fecha', 'Solicitante', 'Estado', 'Costo', 'Días'],
                'sql' => "SELECT numero, titulo, fecha, solicitante, estado, impacto_costo, impacto_dias FROM ordenes_cambio WHERE proyecto_id = $proyectoId ORDER BY fecha, id",
                'fila' => fn($r) => [$r['numero'], $r['titulo'], $fecha($r['fecha']), $r['solicitante'], $r['estado'], number_format((float)$r['impacto_costo'], 2), $r['impacto_dias']]],
            'bitacora' => ['cols' => ['Fecha', 'Departamento', 'Descripción', 'Importancia'],
                'sql' => "SELECT fecha, departamento, descripcion, importancia FROM bitacora WHERE proyecto_id = $proyectoId ORDER BY fecha, id",
                'fila' => fn($r) => [$fecha($r['fecha']), $r['departamento'], $r['descripcion'], $r['importancia']]],
            default => null,
        };
        if (!$q) return null;
        $filas = [];
        $r = self::$db->query($q['sql']);
        while ($r && ($f = $r->fetch_assoc())) $filas[] = array_map(fn($v) => (string)$v, $q['fila']($f));
        return ['columnas' => $q['cols'], 'filas' => $filas];
    }

    public static function programa(int $proyectoId): array
    {
        $out = [];
        $r = self::$db->query("SELECT p.id AS pid, p.codigo AS pcodigo, p.nombre AS pnombre, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.duracion_dias, a.progreso, a.status
            FROM partidas p LEFT JOIN actividades a ON a.partida_id = p.id WHERE p.proyecto_id = $proyectoId ORDER BY p.orden, p.id, a.orden, a.id");
        while ($r && ($f = $r->fetch_assoc())) $out[] = $f;
        return $out;
    }
}
