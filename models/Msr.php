<?php

namespace Model;

/** Un MSR (reporte de status de materiales) de un proyecto; agrupa paquetes de submittals */
class Msr extends ActiveRecord
{
    protected static $tabla = 'msrs';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'descripcion', 'columnas_json', 'encabezado_id'];

    public $id;
    public $proyecto_id;
    public $nombre;
    public $descripcion;
    public $columnas_json;
    public $encabezado_id;
    // Conteos (solo lectura)
    public $n_submittals, $n_renglones;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->columnas_json = $args['columnas_json'] ?? null;
        $this->encabezado_id = !empty($args['encabezado_id']) ? (int)$args['encabezado_id'] : null;
        if (!self::tieneEncabezado()) self::$columnasDB = array_values(array_diff(self::$columnasDB, ['encabezado_id']));   // migración pendiente
    }

    /** ¿Existe la columna encabezado_id en esta BD? (database/migracion_msr_encabezado.sql) */
    public static function tieneEncabezado(): bool
    {
        static $r = null;
        try {
            return $r ??= (bool)self::$db->query("SHOW COLUMNS FROM msrs LIKE 'encabezado_id'")->num_rows;
        } catch (\Throwable $e) {
            return $r = false;
        }
    }

    /** Estampa de lienzo que sirve de encabezado de este MSR (null = el encabezado de siempre) */
    public function encabezado(): ?Estampa
    {
        if (!$this->encabezado_id || !Estampa::disponible()) return null;
        $e = Estampa::find((int)$this->encabezado_id);
        return $e && (int)$e->proyecto_id === (int)$this->proyecto_id ? $e : null;
    }

    /**
     * Columnas que puede mostrar el MSR: clave => [nombre genérico, nombre del diseño clásico, color, grupo, ocultar en móvil].
     * Las de texto libre que agrega el usuario son «txt_N» y se guardan en msr_items.extras_json.
     */
    public const COLUMNAS = [
        'imagen' => ['Imagen', 'ITEM PROLOG', '#1e3a5f', 'sub', false],
        'sub_numero' => ['Número de paquete', 'Subcontractor submittal number', '#1e3a5f', 'sub', true],
        'referencia' => ['Referencia', 'Spec Number', '#1e3a5f', 'sub', true],
        'descripcion' => ['Descripción', 'DESCRIPCIÓN', '#1e3a5f', 'sub', false],
        'fecha_envio_requerida' => ['Fecha requerida de envío', 'FECHA REQUERIDA DE ENVIO DE SUBMITTAL (PROG)', '#111111', 'sub', false],
        'no_submittal_contratista' => ['Texto', 'No. SUBMITTAL {contratista}', '#6b7280', 'sub', true],
        'fecha_envio_real' => ['Fecha real de envío', 'Real Submittal Date', '#6b7280', 'sub', false],
        'fecha_respuesta' => ['Fecha de respuesta', 'AnswerDate', '#6b7280', 'sub', false],
        'status' => ['Status', 'Status', '#6b7280', 'sub', false],
        'dias' => ['Días de entrega', 'Delivery Time Schedule (Days)', '#2563eb', 'ent', true],
        'fecha_compra_prog' => ['Compra (prog.)', 'FECHA COMPRA DE MATERIAL (PROG)', '#2563eb', 'ent', false],
        'fecha_instalacion_prog' => ['Instalación (prog.)', 'FECHA DE INSTALACION (PROG)', '#2563eb', 'ent', false],
        'fecha_compra_real' => ['Compra (real)', 'FECHA DE COMPRA (REAL)', '#1d4ed8', 'ent', false],
        'fecha_entrega_prog' => ['Entrega (prog.)', 'FECHA DE ENTREGA (PROG)', '#1d4ed8', 'ent', false],
        'fecha_entrega_real' => ['Entrega (real)', 'FECHA DE ENTREGA (REAL)', '#1d4ed8', 'ent', false],
        'proveedor' => ['Proveedor', 'DATOS DEL PROVEEDOR', '#2563eb', 'ent', true],
        'observaciones' => ['Observaciones', 'OBSERVACIONES', '#1e3a5f', 'ent', false],
    ];
    /** De dónde sale el dato de cada columna: 'dato' = vinculado a otra herramienta, 'calculo' = se calcula, 'texto' = lo escribe el usuario */
    public const ORIGEN = [
        'imagen' => ['dato', 'Materiales'], 'referencia' => ['dato', 'Materiales'], 'descripcion' => ['dato', 'Materiales'],
        'sub_numero' => ['dato', 'Submittals'], 'fecha_envio_requerida' => ['dato', 'Submittals'], 'fecha_envio_real' => ['dato', 'Submittals'],
        'fecha_respuesta' => ['dato', 'Submittals'], 'status' => ['dato', 'Submittals'],
        'dias' => ['calculo', 'Cálculo: compra → entrega'],
        'fecha_compra_prog' => ['dato', 'Materiales'], 'fecha_compra_real' => ['dato', 'Materiales'],
        'fecha_entrega_prog' => ['dato', 'Materiales'], 'fecha_entrega_real' => ['dato', 'Materiales'],
        'fecha_instalacion_prog' => ['dato', 'Fecha del renglón'],
        'no_submittal_contratista' => ['texto', 'Texto'], 'proveedor' => ['texto', 'Texto'], 'observaciones' => ['texto', 'Texto'],
    ];
    public const GRUPOS = ['sub' => 'STATUS DEL SUBMITTAL', 'ent' => 'STATUS DE ENTREGA DEL MATERIAL O EQUIPO'];
    public const CLASICO = ['imagen', 'sub_numero', 'referencia', 'descripcion', 'fecha_envio_requerida', 'no_submittal_contratista', 'fecha_envio_real', 'fecha_respuesta', 'status', 'dias', 'fecha_compra_prog', 'fecha_instalacion_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real', 'proveedor', 'observaciones'];

    public function esClasico(): bool
    {
        return !is_array(json_decode((string)$this->columnas_json, true));
    }

    /** Diseño guardado: [['k','n','c']] (clave, nombre propio, color) o null si usa el clásico */
    public function diseno(): ?array
    {
        $d = json_decode((string)$this->columnas_json, true);
        return is_array($d) ? $d : null;
    }

    /** Columnas a mostrar, en orden, con nombre y color resueltos: [['k','n','c','grupo','hide','texto']] */
    public function columnas(string $contratista = ''): array
    {
        $lista = $this->diseno();
        $clasico = $lista === null;
        if ($clasico) $lista = array_map(fn($k) => ['k' => $k, 'n' => '', 'c' => ''], self::CLASICO);
        $out = [];
        foreach ($lista as $c) {
            $k = (string)($c['k'] ?? '');
            $texto = (bool)preg_match('/^txt_\d{1,6}$/', $k);
            if (!$texto && !isset(self::COLUMNAS[$k])) continue;
            $def = self::COLUMNAS[$k] ?? ['Texto', 'Texto', '#6b7280', 'sub', false];
            $nombre = trim((string)($c['n'] ?? ''));
            if ($nombre === '') $nombre = $clasico ? str_replace('{contratista}', mb_strtoupper($contratista ?: 'CONTRATISTA'), $def[1]) : $def[0];
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($c['c'] ?? '')) ? $c['c'] : $def[2];
            $g = array_key_exists('g', $c) ? trim((string)$c['g']) : ($texto ? '' : self::GRUPOS[$def[3]]);
            $out[] = ['k' => $k, 'n' => $nombre, 'c' => $color, 'g' => $g, 'grupo' => $texto ? 'sub' : $def[3], 'hide' => $clasico && $def[4], 'texto' => $texto || $k === 'no_submittal_contratista' || $k === 'proveedor' || $k === 'observaciones'];
        }
        return $out;
    }

    /** Diseño actual siempre explícito [['k','n','c']] (si era el clásico, con los nombres clásicos) */
    public function disenoExplicito(string $contratista = ''): array
    {
        if (!$this->esClasico()) return $this->diseno();
        return array_map(fn($c) => ['k' => $c['k'], 'n' => $c['n'], 'c' => '', 'g' => $c['g']], $this->columnas($contratista));
    }

    /** Barra de grupos: columnas contiguas con el mismo grupo se unen. [['n','span','c']]; [] si ninguna columna tiene grupo */
    public static function grupos(array $columnas): array
    {
        if (!array_filter($columnas, fn($c) => $c['g'] !== '')) return [];
        $out = [];
        foreach ($columnas as $c) {
            $ult = count($out) - 1;
            if ($ult >= 0 && $out[$ult]['n'] === $c['g']) $out[$ult]['span']++;
            else $out[] = ['n' => $c['g'], 'span' => 1, 'c' => $c['c']];
        }
        return $out;
    }

    /** Valida y normaliza las columnas del formulario; devuelve el JSON (null = clásico) */
    public static function normalizarColumnas(string $modo, array $post): ?string
    {
        if ($modo !== 'personalizado') return null;
        $out = [];
        $vistos = [];
        foreach ($post as $c) {
            if (!is_array($c)) continue;
            $k = (string)($c['k'] ?? '');
            if ((!isset(self::COLUMNAS[$k]) && !preg_match('/^txt_\d{1,6}$/', $k)) || isset($vistos[$k])) continue;
            $vistos[$k] = true;
            $color = (string)($c['c'] ?? '');
            $fila = ['k' => $k, 'n' => mb_substr(trim((string)($c['n'] ?? '')), 0, 60), 'c' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : ''];
            if (array_key_exists('g', $c)) $fila['g'] = mb_substr(trim((string)$c['g']), 0, 60);
            $out[] = $fila;
        }
        return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre del MSR es obligatorio';
        if (mb_strlen($this->nombre) > 150) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 150)';
        if (mb_strlen($this->descripcion) > 500) self::$alertas['error'][] = 'La descripción es demasiado larga (máx. 500)';
        return self::$alertas;
    }

    /** MSR del proyecto con sus conteos */
    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL(
            "SELECT m.*,
                (SELECT COUNT(*) FROM submittals s WHERE s.msr_id = m.id) AS n_submittals,
                (SELECT COUNT(*) FROM msr_items i WHERE i.msr_id = m.id) AS n_renglones
             FROM msrs m WHERE m.proyecto_id = $proyectoId ORDER BY m.id ASC"
        );
    }

    /** Si el proyecto no tiene ningún MSR crea «MSR 1»; devuelve el primero */
    public static function asegurar(int $proyectoId): self
    {
        $res = self::consultarSQL("SELECT * FROM msrs WHERE proyecto_id = $proyectoId ORDER BY id ASC LIMIT 1");
        if ($res) return $res[0];
        $m = new self(['proyecto_id' => $proyectoId, 'nombre' => 'MSR 1']);
        $m->id = $m->guardar()['id'];
        return $m;
    }

    /** MSR por id, solo si es del proyecto */
    public static function deProyectoPorId(int $proyectoId, $id): ?self
    {
        $m = self::find(filter_var($id, FILTER_VALIDATE_INT));
        return $m && (int)$m->proyecto_id === $proyectoId ? $m : null;
    }

    /** Partidas propias: [['id','titulo','materiales'=>[ids]]] (materiales sin submittal en esa sección) */
    public function partidas(): array
    {
        $db = self::$db;
        $mid = (int)$this->id;
        $out = [];
        $res = $db->query("SELECT id, titulo, color FROM msr_partidas WHERE msr_id = $mid ORDER BY orden ASC, id ASC");
        while ($r = $res->fetch_assoc()) {
            $t = $db->escape_string($r['titulo']);
            $ids = [];
            $q = $db->query("SELECT material_id FROM msr_items WHERE msr_id = $mid AND submittal_id IS NULL AND seccion = '$t' ORDER BY orden ASC");
            while ($m = $q->fetch_row()) $ids[] = (int)$m[0];
            $out[] = ['id' => (int)$r['id'], 'titulo' => $r['titulo'], 'color' => $r['color'], 'materiales' => $ids];
        }
        return $out;
    }

    /** Valida las partidas del formulario; devuelve mensajes de error */
    public static function validarPartidas(array $partidas): array
    {
        $errores = [];
        $vistos = [];
        foreach ($partidas as $p) {
            $t = trim($p['titulo'] ?? '');
            if ($t === '') {
                if (!empty($p['materiales'])) $errores[] = 'Hay una partida con materiales pero sin título';
                continue;
            }
            if (mb_strlen($t) > 150) $errores[] = "El título «" . mb_substr($t, 0, 30) . "…» es demasiado largo (máx. 150)";
            if (isset($vistos[mb_strtolower($t)])) $errores[] = "La partida «$t» está repetida";
            $vistos[mb_strtolower($t)] = true;
        }
        return $errores;
    }

    /**
     * Aplica las partidas del formulario: crea/renombra/elimina partidas y sincroniza sus materiales
     * (renglones sin submittal). No toca los renglones que vienen de paquetes de submittal.
     */
    public function sincronizarPartidas(array $partidas): void
    {
        $db = self::$db;
        $mid = (int)$this->id;
        $pid = (int)$this->proyecto_id;
        $esc = fn($v) => $db->escape_string($v);
        $existentes = [];
        $res = $db->query("SELECT id, titulo FROM msr_partidas WHERE msr_id = $mid");
        while ($r = $res->fetch_assoc()) $existentes[(int)$r['id']] = $r['titulo'];

        $conservar = [];
        $orden = (int)$db->query("SELECT COALESCE(MAX(orden),0) FROM msr_items WHERE proyecto_id = $pid")->fetch_row()[0];
        $nPart = 0;
        foreach ($partidas as $p) {
            $titulo = trim($p['titulo'] ?? '');
            if ($titulo === '') continue;
            $t = $esc($titulo);
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($p['color'] ?? '')) ? $p['color'] : '';
            $id = (int)($p['id'] ?? 0);
            if ($id && isset($existentes[$id])) {
                if ($existentes[$id] !== $titulo) {
                    $viejo = $esc($existentes[$id]);
                    $db->query("UPDATE msr_items SET seccion = '$t' WHERE msr_id = $mid AND seccion = '$viejo'");
                }
                $db->query("UPDATE msr_partidas SET titulo = '$t', color = '$color', orden = $nPart WHERE id = $id");
            } else {
                $db->query("INSERT INTO msr_partidas (msr_id, titulo, color, orden) VALUES ($mid, '$t', '$color', $nPart)");
                $id = (int)$db->insert_id;
            }
            $conservar[] = $id;
            $nPart++;

            // Materiales del catálogo (solo de este proyecto)
            $ids = array_values(array_unique(array_filter(array_map('intval', $p['materiales'] ?? []))));
            if ($ids) {
                $r2 = $db->query("SELECT id FROM materiales WHERE proyecto_id = $pid AND id IN (" . implode(',', $ids) . ")");
                $ids = [];
                while ($m = $r2->fetch_row()) $ids[] = (int)$m[0];
            }
            $db->query("DELETE FROM msr_items WHERE msr_id = $mid AND submittal_id IS NULL AND seccion = '$t'" . ($ids ? ' AND material_id NOT IN (' . implode(',', $ids) . ')' : ''));
            foreach ($ids as $matId) {
                $ya = $db->query("SELECT 1 FROM msr_items WHERE msr_id = $mid AND submittal_id IS NULL AND material_id = $matId AND seccion = '$t' LIMIT 1");
                if ($ya && $ya->num_rows) continue;
                $db->query("INSERT INTO msr_items (proyecto_id, msr_id, submittal_id, material_id, seccion, orden) VALUES ($pid, $mid, NULL, $matId, '$t', " . (++$orden) . ")");
                MsrItem::heredarDeOtroMsr((int)$db->insert_id);
            }
        }
        // Partidas quitadas del formulario: se van con sus materiales sin submittal
        foreach ($existentes as $id => $titulo) {
            if (in_array($id, $conservar, true)) continue;
            $db->query("DELETE FROM msr_items WHERE msr_id = $mid AND submittal_id IS NULL AND seccion = '" . $esc($titulo) . "'");
            $db->query("DELETE FROM msr_partidas WHERE id = $id");
        }
        // Fechas de compra/entrega compartidas con el catálogo
        $db->query("UPDATE msr_items i JOIN materiales m ON m.id = i.material_id
            SET i.fecha_compra_prog = m.fecha_compra_prog, i.fecha_compra_real = m.fecha_compra_real,
                i.fecha_entrega_prog = m.fecha_entrega_prog, i.fecha_entrega_real = m.fecha_entrega_real
            WHERE i.msr_id = $mid AND i.submittal_id IS NULL");
    }
}
