<?php

namespace Classes;

use Model\Proyecto;
use Model\SheetflowHoja;
use Model\Subactividad;
use Model\VplanLamina;
use Model\VplanTabla;

/**
 * Instalación de materiales en una lámina de vPlan.
 *
 * Cada línea/forma de las marcas guarda en `material.items` las actividades y
 * subactividades que representa. La lámina se vincula a UNA tabla de SheetFlow
 * (`vplan_laminas.materiales_tabla_id`) con una fila por actividad/subactividad
 * única y tres columnas base: Material, Fecha de material y Status.
 *
 * Las columnas extra se agregan en SheetFlow y nunca las toca la sincronización.
 */
class MaterialesLamina
{
    public const DEPARTAMENTO = 'Materiales';
    public const MAX_IMAGENES = 12;
    /** Valores de la columna Status (tipo lista de SheetFlow) */
    public const STATUS = ['No iniciada', 'En proceso', 'Completada'];

    // ---------- actividades para el buscador ----------

    /**
     * Partidas + actividades + subactividades del proyecto.
     * [partidas => [{id, codigo, nombre, color}], actividades => [{id, codigo, nombre, partida_id, …}]]
     */
    public static function arbol(Proyecto $proyecto): array
    {
        $subs = Subactividad::deProyecto((int)$proyecto->id);
        $partidas = [];
        $actividades = [];
        foreach ($proyecto->getPartidasConActividades() as $bloque) {
            $p = $bloque['partida'];
            $partidas[] = ['id' => (int)$p->id, 'codigo' => (string)$p->codigo, 'nombre' => (string)$p->nombre, 'color' => (string)$p->color_bg];
            foreach ($bloque['actividades'] as $a) {
                $propias = $subs[(int)$a->id] ?? [];
                $actividades[] = [
                    'id' => (int)$a->id,
                    'codigo' => (string)$a->codigo,
                    'nombre' => (string)$a->nombre,
                    'partida_id' => (int)$p->id,
                    'partida' => (string)$p->nombre,
                    'estado' => Vplan::estado($a),
                    'progreso' => (int)$a->progreso,
                    'fecha_inicio' => (string)($a->fecha_inicio ?? ''),
                    'fecha_fin' => (string)($a->fecha_fin ?? ''),
                    'subactividades' => array_map(fn($s) => [
                        'id' => (int)$s->id,
                        'nombre' => (string)$s->nombre,
                        'fecha_inicio' => (string)($s->fecha_inicio ?? ''),
                        'fecha_fin' => (string)($s->fecha_fin ?? ''),
                        'status' => (int)$s->status,
                    ], $propias),
                ];
            }
        }
        return ['partidas' => $partidas, 'actividades' => $actividades];
    }

    /** Configuración que recibe el editor de marcas (solo lectura del lado del servidor) */
    public static function config(Proyecto $proyecto, VplanLamina $lamina): array
    {
        $arbol = self::arbol($proyecto);
        $tabla = self::tablaDe($proyecto, $lamina);
        return [
            'tabla' => $tabla ? ['id' => (int)$tabla->id, 'titulo' => (string)$tabla->titulo, 'hoja' => (int)$tabla->sheetflow_id, 'version' => (string)($tabla->hoja()?->actualizado ?? '')] : null,
            'partidas' => $arbol['partidas'],
            'actividades' => $arbol['actividades'],
            'status' => self::STATUS,
            'api' => [
                'crear' => '/api/vplan/materiales/crear',
                'sincronizar' => '/api/vplan/materiales/sincronizar',
                'datos' => '/api/vplan/materiales/datos',
                'imagenes' => '/api/vplan/materiales/imagenes',
                'desvincular' => '/api/vplan/materiales/desvincular',
                'vincular' => '/api/vplan/materiales/vincular',
                'sheetflow' => '/api/sheetflow/datos',
            ],
        ];
    }

    // ---------- claves ----------

    /** Claves únicas de las marcas de una lámina, en el orden en que aparecen: ['a12','s45',…] */
    public static function clavesDeMarcas(?string $json): array
    {
        $d = json_decode((string)$json, true);
        $out = [];
        foreach ((array)($d['shapes'] ?? []) as $sh) {
            if (!is_array($sh)) continue;
            $items = (array)($sh['material']['items'] ?? []);
            if (!$items && !empty($sh['actividad_id'])) $items = [['tipo' => 'actividad', 'id' => $sh['actividad_id']]];   // liga antigua de vPlan
            foreach ($items as $it) {
                $clave = self::clave($it['tipo'] ?? '', $it['id'] ?? 0);
                if ($clave) $out[$clave] = true;
            }
        }
        return array_keys($out);
    }

    private static function clave(string $tipo, $id): ?string
    {
        $id = (int)$id;
        if ($id <= 0) return null;
        return $tipo === 'subactividad' ? 's' . $id : ($tipo === 'actividad' ? 'a' . $id : null);
    }

    /** Color de cada clave según la primera línea/forma que la usa: ['a12' => '#e11d48', …] */
    public static function coloresDeMarcas(?string $json): array
    {
        $d = json_decode((string)$json, true);
        $out = [];
        $lineas = ['line', 'arrow', 'darrow', 'measure'];
        foreach ((array)($d['shapes'] ?? []) as $sh) {
            if (!is_array($sh)) continue;
            $color = in_array($sh['type'] ?? '', $lineas, true) ? ($sh['stroke'] ?? '') : (($sh['fill'] ?? '') ?: ($sh['stroke'] ?? ''));
            if (!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) continue;
            $items = (array)($sh['material']['items'] ?? []);
            if (!$items && !empty($sh['actividad_id'])) $items = [['tipo' => 'actividad', 'id' => $sh['actividad_id']]];
            foreach ($items as $it) {
                $k = self::clave($it['tipo'] ?? '', $it['id'] ?? 0);
                if ($k && !isset($out[$k])) $out[$k] = strtolower($color);
            }
        }
        return $out;
    }

    /** Deja solo las claves que existen en el proyecto (y conserva el orden) */
    public static function clavesValidas(array $claves, array $arbol): array
    {
        $ok = [];
        foreach ($arbol['actividades'] as $a) {
            $ok['a' . (int)$a['id']] = true;
            foreach ($a['subactividades'] as $s) $ok['s' . (int)$s['id']] = true;
        }
        $out = [];
        foreach ($claves as $c) if (isset($ok[$c])) $out[$c] = true;
        return array_keys($out);
    }

    /** Datos de una clave: nombre, fecha fin y status; null si ya no existe */
    public static function resolver(string $clave, array $arbol): ?array
    {
        $tipo = $clave[0] ?? '';
        $id = (int)substr($clave, 1);
        if ($id <= 0 || !in_array($tipo, ['a', 's'], true)) return null;
        foreach ($arbol['actividades'] as $a) {
            if ($tipo === 'a' && (int)$a['id'] === $id) {
                return [
                    'clave' => $clave, 'tipo' => 'actividad', 'id' => $id, 'actividad_id' => $id,
                    'nombre' => self::nombre($a['codigo'], $a['nombre']),
                    'fecha' => (string)$a['fecha_fin'], 'status' => self::statusDeAvance((int)$a['progreso']),
                    'estado' => (string)$a['estado'],
                ];
            }
            if ($tipo === 's') {
                foreach ($a['subactividades'] as $s) {
                    if ((int)$s['id'] !== $id) continue;
                    return [
                        'clave' => $clave, 'tipo' => 'subactividad', 'id' => $id, 'actividad_id' => (int)$a['id'],
                        'nombre' => self::nombre($a['codigo'], $s['nombre']),
                        'fecha' => (string)$s['fecha_fin'], 'status' => self::statusDeAvance((int)$s['status']),
                        'estado' => (string)$a['estado'],
                    ];
                }
            }
        }
        return null;
    }

    private static function nombre(string $codigo, string $nombre): string
    {
        $codigo = trim($codigo);
        return mb_substr($codigo !== '' ? $codigo . ' · ' . $nombre : $nombre, 0, 200);
    }

    private static function statusDeAvance(int $pct): string
    {
        if ($pct <= 0) return self::STATUS[0];
        return $pct >= 100 ? self::STATUS[2] : self::STATUS[1];
    }

    // ---------- tabla de SheetFlow ----------

    public static function tablaDe(Proyecto $proyecto, VplanLamina $lamina): ?VplanTabla
    {
        $id = (int)($lamina->materiales_tabla_id ?? 0);
        if ($id <= 0) return null;
        $t = VplanTabla::find($id);
        if (!$t || (int)$t->proyecto_id !== (int)$proyecto->id || $t->tipo !== 'materiales') return null;
        return $t;
    }

    /** Columnas base de la tabla (las tres primeras son fijas) */
    private static function columnasBase(): array
    {
        return [
            ['n' => 'Material', 't' => 'texto', 'a' => 'izquierda', 'w' => 300, 'rol' => 'material', 'fija' => 1],
            ['n' => 'Fecha de material', 't' => 'fecha', 'a' => 'centro', 'w' => 115, 'rol' => 'fecha_material', 'fija' => 1],
            ['n' => 'Status', 't' => 'lista', 'a' => 'centro', 'w' => 120, 'rol' => 'status_material', 'fija' => 1, 'op' => self::STATUS],
        ];
    }

    /** Crea (o devuelve) la tabla de la lámina y la agrega al diseño como bloque */
    public static function crear(Proyecto $proyecto, VplanLamina $lamina, ?int $usuarioId = null): array
    {
        $existente = self::tablaDe($proyecto, $lamina);
        if ($existente && $existente->hoja()) {
            $ids = array_map(fn($t) => (int)$t->id, $lamina->tablas());
            if (!in_array((int)$existente->id, $ids, true)) $lamina->agregarTabla((int)$existente->id);   // bloque retirado del diseño
            return [$existente->hoja(), $existente];
        }

        $arbol = self::arbol($proyecto);
        $claves = self::clavesValidas(self::clavesDeMarcas($lamina->marcas_json), $arbol);
        $filas = [];
        $ids = [];
        foreach ($claves as $clave) {
            $f = self::resolver($clave, $arbol);
            if (!$f) continue;
            $filas[] = [$f['nombre'], (string)$f['fecha'], $f['status']];
            $ids[] = $clave;
        }
        $datos = Sheetflow::normalizar([
            'cols' => self::columnasBase(),
            'rows' => $filas,
            'meta' => ['materiales' => ['ids' => $ids]],
        ]);

        $etiqueta = trim((string)$lamina->titulo);
        if ($etiqueta === '') $etiqueta = 'Lámina #' . (int)$lamina->id;
        $departamento = \Classes\Catalogo::agregarDepartamento((int)$proyecto->id, self::DEPARTAMENTO) ?: self::DEPARTAMENTO;
        $h = new SheetflowHoja([
            'proyecto_id' => (int)$proyecto->id,
            'nombre' => mb_substr('Instalación de materiales · ' . $etiqueta, 0, 150),
            'fecha' => date('Y-m-d'),
            'departamento' => $departamento,
            'descripcion' => mb_substr('Tabla vinculada a las marcas de la lámina «' . $etiqueta . '»: una fila por actividad o subactividad, con su fecha y status. Las columnas que agregues se conservan.', 0, 300),
            'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'creado_por' => $usuarioId,
        ]);
        $h->id = $h->guardar()['id'];

        $t = new VplanTabla([
            'proyecto_id' => (int)$proyecto->id,
            'titulo' => 'INSTALACIÓN DE MATERIALES',
            'nombre_interno' => mb_substr('Materiales · ' . $etiqueta, 0, 150),
            'tipo' => 'materiales',
            'sheetflow_id' => (int)$h->id,
            'columnas_json' => json_encode(['Material', 'Fecha de material', 'Status'], JSON_UNESCAPED_UNICODE),
        ]);
        $t->id = $t->guardar()['id'];

        $lamina->materiales_tabla_id = (int)$t->id;
        $lamina->guardar();
        $lamina->agregarTabla((int)$t->id);

        return [$h, $t];
    }

    /**
     * Agrega a la tabla las actividades/subactividades nuevas de las marcas.
     * Nunca toca las columnas extra; con $refrescarBase actualiza Material/Fecha/Status.
     */
    public static function sincronizar(Proyecto $proyecto, VplanLamina $lamina, bool $refrescarBase = false): array
    {
        $t = self::tablaDe($proyecto, $lamina);
        if (!$t || !($h = $t->hoja())) [$h, $t] = self::crear($proyecto, $lamina);
        $arbol = self::arbol($proyecto);
        $claves = self::clavesValidas(self::clavesDeMarcas($lamina->marcas_json), $arbol);
        $datos = self::fusionar($h->contenido(), $claves, $arbol, $refrescarBase);
        Sheetflow::guardarDatos($h, $datos);
        $h = SheetflowHoja::find((int)$h->id);
        return [$h, $t];
    }

    /** Mezcla las claves actuales con los renglones de la hoja conservando las columnas extra */
    public static function fusionar(array $datos, array $claves, array $arbol, bool $refrescarBase = false): array
    {
        $datos = Sheetflow::limpiar($datos);
        $ids = array_values((array)($datos['meta']['materiales']['ids'] ?? []));
        $actuales = array_fill_keys($claves, true);
        $vistos = [];
        $filas = [];
        $nuevosIds = [];
        foreach ($datos['rows'] as $i => $fila) {
            $clave = (string)($ids[$i] ?? '');
            if ($clave !== '' && isset($actuales[$clave])) {
                if ($refrescarBase && ($f = self::resolver($clave, $arbol))) {
                    $fila[0] = $f['nombre'];
                    $fila[1] = (string)$f['fecha'];
                    $fila[2] = $f['status'];
                }
                $vistos[$clave] = true;
            }
            $filas[] = array_values((array)$fila);
            $nuevosIds[] = $clave !== '' && preg_match('/^[as]\d+$/', $clave) ? $clave : null;
        }
        $nc = count($datos['cols']);
        foreach ($claves as $clave) {
            if (isset($vistos[$clave]) || in_array($clave, $nuevosIds, true)) continue;
            $f = self::resolver($clave, $arbol);
            if (!$f) continue;
            $fila = array_fill(0, max(3, $nc), '');
            $fila[0] = $f['nombre'];
            $fila[1] = (string)$f['fecha'];
            $fila[2] = $f['status'];
            $filas[] = $fila;
            $nuevosIds[] = $clave;
        }
        $datos['rows'] = $filas;
        $datos['meta']['materiales'] = ['ids' => $nuevosIds];
        return Sheetflow::normalizar($datos);
    }

    /** Datos de la tabla para el panel del editor: tabla, hoja y matriz (con meta) */
    public static function datos(Proyecto $proyecto, VplanLamina $lamina): array
    {
        $t = self::tablaDe($proyecto, $lamina);
        $h = $t?->hoja();
        return [
            'tabla' => $t ? ['id' => (int)$t->id, 'titulo' => (string)$t->titulo, 'hoja' => (int)$t->sheetflow_id] : null,
            'hoja' => $h ? ['id' => (int)$h->id, 'nombre' => (string)$h->nombre, 'version' => (string)$h->actualizado] : null,
            'matriz' => $h ? Sheetflow::matriz($h) : null,
        ];
    }

    // ---------- imágenes de materiales ----------

    /**
     * Imágenes del catálogo de materiales de cada actividad:
     * [actividad_id => [{id, nombre, src, marca, modelo}, …]]
     */
    public static function imagenes(Proyecto $proyecto, array $actividadIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $actividadIds), fn($i) => $i > 0)));
        if (!$ids) return [];
        $lista = implode(',', $ids);
        $out = [];
        try {
            // Consulta directa: la fila trae actividad_id, que no es una columna del modelo Material
            $r = SheetflowHoja::conexion()->query(
                "SELECT am.actividad_id, m.id, m.nombre, m.imagen, m.marca, m.modelo
                 FROM actividad_materiales am
                 JOIN materiales m ON m.id = am.material_id
                 WHERE am.actividad_id IN ($lista) AND m.proyecto_id = " . (int)$proyecto->id . "
                   AND m.imagen IS NOT NULL AND m.imagen <> ''
                 ORDER BY am.actividad_id, m.nombre, m.id"
            );
            while ($r && ($m = $r->fetch_assoc())) {
                $aid = (int)$m['actividad_id'];
                if (count($out[$aid] ?? []) >= self::MAX_IMAGENES) continue;
                $arch = basename((string)$m['imagen']);
                if (!preg_match('/^[A-Za-z0-9._-]+$/', $arch)) continue;
                $out[$aid][] = [
                    'id' => (int)$m['id'],
                    'nombre' => (string)$m['nombre'],
                    'src' => '/uploads/materiales/' . $arch,
                    'marca' => (string)$m['marca'],
                    'modelo' => (string)$m['modelo'],
                ];
            }
        } catch (\Throwable $e) {   // catálogo de materiales no disponible
        }
        return $out;
    }
}
