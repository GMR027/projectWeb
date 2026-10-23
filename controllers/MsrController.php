<?php

namespace Controllers;

use MVC\Router;
use Model\Msr;
use Model\MsrItem;
use Model\Material;
use Model\Submittal;
use Model\Proyecto;
use Model\Usuario;
use Classes\MsrImportador;

/** MSR · Material Status Report */
class MsrController
{
    /** MSR de la petición (GET/POST `msr` o `id`), solo si es del proyecto */
    private static function msrDe(Proyecto $proyecto): ?Msr
    {
        $f = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        return Msr::deProyectoPorId((int)$proyecto->id, $f['id'] ?? $f['msr'] ?? null);
    }

    /** Listado de MSR del proyecto */
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('msr/listado', [
            'titulo' => 'MSR - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'msrs' => Msr::deProyecto((int)$proyecto->id), 'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    /** Un MSR (el reporte) */
    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $msr = self::msrDe($proyecto);
        if (!$msr) {
            header('Location: /msr?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('msr/index', self::datos($proyecto, $msr) + [
            'titulo' => $msr->nombre . ' - ' . $proyecto->nombre, 'msr' => $msr,
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        self::formulario($router, $proyecto, new Msr());
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $msr = self::msrDe($proyecto);
        if (!$msr) {
            header('Location: /msr?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $proyecto, $msr);
    }

    private static function formulario(Router $router, Proyecto $proyecto, Msr $msr)
    {
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $msr->nombre = trim($_POST['nombre'] ?? '');
            $msr->descripcion = trim($_POST['descripcion'] ?? '');
            $enc = (int)($_POST['encabezado_id'] ?? 0);
            $msr->encabezado_id = $enc > 0 && ($e = \Model\Estampa::find($enc)) && (int)$e->proyecto_id === (int)$proyecto->id ? $enc : null;
            $msr->proyecto_id = $proyecto->id;
            $partidasPost = array_values(array_filter($_POST['partidas'] ?? [], 'is_array'));
            $msr->columnas_json = Msr::normalizarColumnas((string)($_POST['modo'] ?? ''), array_values(array_filter($_POST['columnas'] ?? [], 'is_array')));
            $alertas = $msr->validar();
            foreach (Msr::validarPartidas($partidasPost) as $e) $alertas['error'][] = $e;
            if (!$alertas) {
                $r = $msr->guardar();
                if (is_array($r)) $msr->id = $r['id'];
                $msr->sincronizarPartidas($partidasPost);
                header('Location: /msr/ver?proyecto=' . $proyecto->id . '&id=' . $msr->id);
                exit;
            }
        }
        $router->render('msr/formulario', [
            'titulo' => $msr->id ? 'Editar MSR' : 'Nuevo MSR', 'proyecto' => $proyecto, 'msr' => $msr, 'alertas' => $alertas,
            'partidas' => isset($partidasPost)
                ? array_map(fn($p) => ['id' => (int)($p['id'] ?? 0), 'titulo' => trim($p['titulo'] ?? ''), 'color' => (string)($p['color'] ?? ''), 'materiales' => array_map('intval', $p['materiales'] ?? [])], $partidasPost)
                : ($msr->id ? $msr->partidas() : []),
            'columnasForm' => self::columnasParaFormulario($msr, $proyecto),
            'encabezados' => Msr::tieneEncabezado() ? \Model\Estampa::deProyecto((int)$proyecto->id) : [],
            'catalogo' => array_map(fn($x) => ['id' => (int)$x->id, 'nombre' => $x->nombre, 'ref' => $x->referencia, 'esp' => $x->especialidad, 'img' => $x->imagen], \Model\Material::deProyecto((int)$proyecto->id)),
        ]);
    }

    /** Todas las columnas disponibles para el editor: las del MSR primero (en su orden) y el resto sin marcar */
    private static function columnasParaFormulario(Msr $msr, Proyecto $proyecto): array
    {
        $ctr = (string)$proyecto->contratista;
        $activas = $msr->columnas($ctr);
        // Un MSR sin diseño propio parte de las columnas de siempre, con sus nombres ya escritos para que el usuario los edite
        $propias = $msr->disenoExplicito($ctr);
        $out = [];
        $usadas = [];
        foreach ($propias as $i => $c) {
            $r = $activas[$i] ?? null;
            if (!$r) continue;
            $out[] = ['k' => $c['k'], 'n' => $c['n'], 'c' => $c['c'] ?? '', 'g' => $r['g'], 'generico' => Msr::COLUMNAS[$c['k']][0] ?? 'Texto', 'color' => $r['c'], 'activa' => true, 'texto' => str_starts_with($c['k'], 'txt_'), 'origen' => Msr::ORIGEN[$c['k']] ?? ['texto', 'Texto']];
            $usadas[$c['k']] = true;
        }
        foreach (Msr::COLUMNAS as $k => $d) {
            if (isset($usadas[$k])) continue;
            $out[] = ['k' => $k, 'n' => '', 'c' => '', 'g' => Msr::GRUPOS[$d[3]], 'generico' => $d[0], 'color' => $d[2], 'activa' => false, 'texto' => false, 'origen' => Msr::ORIGEN[$k] ?? ['texto', 'Texto']];
        }
        return $out;
    }

    /** Elimina el MSR y sus renglones; sus paquetes de submittal quedan «Sin MSR» */
    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($msr = self::msrDe($proyecto)) $msr->eliminar();
        header('Location: /msr?proyecto=' . $proyecto->id);
        exit;
    }

    /** Datos del MSR (también los usan los exportadores); sin $msr, todos los renglones del proyecto */
    public static function datos(Proyecto $proyecto, ?Msr $msr = null): array
    {
        $items = MsrItem::deProyecto((int)$proyecto->id, $msr ? (int)$msr->id : null);
        $secciones = [];
        foreach ($items as $it) {
            $secciones[$it->seccion][] = $it;
        }
        // Partidas propias aún vacías también se muestran
        $coloresSec = [];
        if ($msr) foreach ($msr->partidas() as $p) {
            if (!isset($secciones[$p['titulo']])) $secciones[$p['titulo']] = [];
            if ($p['color'] !== '') $coloresSec[$p['titulo']] = $p['color'];
        }
        $dueno = Usuario::find($proyecto->usuario_id);
        return [
            'proyecto' => $proyecto,
            'msr' => $msr,
            'items' => $items,
            'secciones' => $secciones,
            'coloresSec' => $coloresSec,
            'grupos' => Msr::grupos($msr ? $msr->columnas((string)$proyecto->contratista) : (new Msr())->columnas((string)$proyecto->contratista)),
            'columnas' => $msr ? $msr->columnas((string)$proyecto->contratista) : (new Msr())->columnas((string)$proyecto->contratista),
            'umbral' => max(1, (int)$proyecto->msr_umbral_critico),
            'logoEmpresa' => $dueno && $dueno->logo ? $dueno->logo : $proyecto->logo,
            'proveedores' => array_values(array_unique(array_filter(array_map(fn($i) => $i->proveedor, $items)))),
        ];
    }

    /** Cambia el status en ciclo P → A → RA; el submittal y todos sus renglones comparten el status */
    public static function status()
    {
        $item = self::itemEditable();
        $nuevo = MsrItem::STATUS_CICLO[$item->status] ?? 'P';
        $sub = $item->submittal_id ? Submittal::find($item->submittal_id) : null;
        $respuesta = null;
        if ($sub) {
            $anterior = $sub->estatus;
            $sub->estatus = array_search($nuevo, Submittal::STATUS_MSR, true) ?: 'enviado';
            $sub->guardar();
            $sub->sincronizarMsr($anterior);
            $respuesta = $sub->fecha_respuesta;
        } else {
            $item->status = $nuevo;
            $item->guardar();
        }
        json_respuesta(['success' => true, 'status' => $nuevo, 'compartido' => (bool)$sub, 'fecha_respuesta' => $respuesta]);
    }

    /** Guarda un campo editable en línea */
    public static function campo()
    {
        $item = self::itemEditable();
        $campo = $_POST['campo'] ?? '';
        if (preg_match('/^txt_\d{1,6}$/', $campo)) {   // columna de texto libre del usuario
            $item->fijarExtra($campo, mb_substr(trim((string)($_POST['valor'] ?? '')), 0, 2000));
            $item->guardar();
            json_respuesta(['success' => true, 'dias' => $item->diasEntrega(), 'critico' => false, 'atrasoCompra' => false, 'atrasoEntrega' => false, 'compartido' => null]);
        }
        $tipo = MsrItem::EDITABLES[$campo] ?? null;
        if (!$tipo) json_respuesta(['success' => false, 'error' => 'Campo no editable'], 422);
        $valor = trim((string)($_POST['valor'] ?? ''));
        if ($tipo === 'fecha') {
            $d = $valor === '' ? null : \DateTime::createFromFormat('Y-m-d', $valor);
            if ($valor !== '' && (!$d || $d->format('Y-m-d') !== $valor)) json_respuesta(['success' => false, 'error' => 'Fecha inválida'], 422);
            $item->$campo = $valor === '' ? null : $valor;
        } else {
            $item->$campo = mb_substr($valor, 0, $campo === 'observaciones' ? 2000 : 200);
        }
        if ($campo === 'fecha_envio_real' && $valor === '') json_respuesta(['success' => false, 'error' => 'La fecha del submittal no puede quedar vacía'], 422);
        $item->guardar();

        // Fechas compartidas: el material (compra/entrega) o el submittal (envío/respuesta) las comparten con todos sus renglones
        $compartido = null;
        $fecha = $item->$campo;
        if (in_array($campo, ['fecha_compra_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real'], true)) {
            if ($mat = Material::find($item->material_id)) {
                $mat->$campo = $fecha;
                if ($campo === 'fecha_compra_real') $mat->compra = $fecha ? 'comprado' : 'pendiente';
                $mat->guardar();
                $mat->sincronizarMsr();
                $compartido = ['attr' => 'mat', 'id' => (int)$mat->id];
            }
        } elseif (in_array($campo, ['fecha_envio_real', 'fecha_respuesta'], true)) {
            if ($item->submittal_id && ($sub = Submittal::find($item->submittal_id))) {
                if ($campo === 'fecha_envio_real') $sub->fecha = $fecha; else $sub->fecha_respuesta = $fecha;
                $sub->guardar();
                $sub->sincronizarFechas();
                $compartido = ['attr' => 'sub', 'id' => (int)$sub->id];
            }
        }
        $item = MsrItem::find($item->id);
        $umbral = max(1, (int)(Proyecto::find($item->proyecto_id)->msr_umbral_critico ?? 60));
        json_respuesta([
            'success' => true, 'dias' => $item->diasEntrega(), 'critico' => $item->esCritico($umbral),
            'atrasoCompra' => (bool)$item->compraAtrasada(), 'atrasoEntrega' => (bool)$item->entregaAtrasada(), 'compartido' => $compartido,
        ]);
    }

    /** Guarda el nuevo orden de las columnas (arrastradas en el encabezado); si el MSR era clásico pasa a diseño propio */
    public static function orden()
    {
        $proyecto = proyecto_de_peticion(true);
        $msr = self::msrDe($proyecto);
        if (!$msr) json_respuesta(['success' => false, 'error' => 'MSR no encontrado'], 404);
        $claves = array_values(array_filter(explode(',', (string)($_POST['orden'] ?? ''))));
        $porClave = [];
        foreach ($msr->disenoExplicito((string)$proyecto->contratista) as $c) $porClave[$c['k']] = $c;
        $nuevo = [];
        foreach ($claves as $k) if (isset($porClave[$k])) { $nuevo[] = $porClave[$k]; unset($porClave[$k]); }
        foreach ($porClave as $c) $nuevo[] = $c;
        $msr->columnas_json = Msr::normalizarColumnas('personalizado', $nuevo);
        $msr->guardar();
        json_respuesta(['success' => true]);
    }

    /** Cambia el título y el color de una columna (doble clic en su encabezado) */
    public static function columna()
    {
        $proyecto = proyecto_de_peticion(true);
        $msr = self::msrDe($proyecto);
        if (!$msr) json_respuesta(['success' => false, 'error' => 'MSR no encontrado'], 404);
        $k = (string)($_POST['k'] ?? '');
        $diseno = $msr->disenoExplicito((string)$proyecto->contratista);
        $hallada = false;
        foreach ($diseno as &$c) {
            if ($c['k'] !== $k) continue;
            $hallada = true;
            $c['n'] = mb_substr(trim((string)($_POST['n'] ?? '')), 0, 60);
            $c['c'] = (string)($_POST['c'] ?? '');
            if (isset($_POST['g'])) $c['g'] = (string)$_POST['g'];
        }
        unset($c);
        if (!$hallada) json_respuesta(['success' => false, 'error' => 'Columna no encontrada'], 404);
        // Al asignarla a un grupo, la columna se coloca junto a las demás de ese grupo
        $grupo = trim((string)($_POST['g'] ?? ''));
        if ($grupo !== '' && isset($_POST['g'])) {
            $actual = $msr->columnas((string)$proyecto->contratista);
            $gDe = array_column($actual, 'g', 'k');
            $gDe[$k] = $grupo;
            $propia = null;
            $resto = [];
            foreach ($diseno as $c) { if ($c['k'] === $k) $propia = $c; else $resto[] = $c; }
            $ultimo = -1;
            foreach ($resto as $i => $c) if (($c['g'] ?? $gDe[$c['k']] ?? '') === $grupo) $ultimo = $i;
            if ($propia && $ultimo >= 0) {
                array_splice($resto, $ultimo + 1, 0, [$propia]);
                $diseno = $resto;
            }
        }
        $msr->columnas_json = Msr::normalizarColumnas('personalizado', $diseno);
        $msr->guardar();
        $col = array_values(array_filter($msr->columnas((string)$proyecto->contratista), fn($x) => $x['k'] === $k))[0] ?? null;
        json_respuesta(['success' => true, 'n' => $col['n'] ?? '', 'c' => $col['c'] ?? '', 'g' => $col['g'] ?? '']);
    }

    /** Sube o baja un material dentro de su partida: intercambia su posición con la del renglón vecino */
    public static function mover()
    {
        $proyecto = proyecto_de_peticion(true);
        $msr = self::msrDe($proyecto);
        if (!$msr) json_respuesta(['success' => false, 'error' => 'MSR no encontrado'], 404);
        $a = MsrItem::find((int)($_POST['item'] ?? 0));
        $b = MsrItem::find((int)($_POST['con'] ?? 0));
        if (!$a || !$b || (int)$a->msr_id !== (int)$msr->id || (int)$b->msr_id !== (int)$msr->id || $a->seccion !== $b->seccion) {
            json_respuesta(['success' => false, 'error' => 'Los renglones deben ser de la misma partida'], 422);
        }
        $db = MsrItem::getDB();
        $ids = [];
        $res = $db->query('SELECT id FROM msr_items WHERE msr_id = ' . (int)$msr->id . ' ORDER BY orden ASC, id ASC');
        while ($r = $res->fetch_row()) $ids[] = (int)$r[0];
        $i = array_search((int)$a->id, $ids, true);
        $j = array_search((int)$b->id, $ids, true);
        if ($i === false || $j === false) json_respuesta(['success' => false, 'error' => 'Renglón no encontrado'], 404);
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $n => $id) $db->query('UPDATE msr_items SET orden = ' . ($n + 1) . ' WHERE id = ' . $id);
        json_respuesta(['success' => true]);
    }

    /** Cambia el título y el color de una partida (doble clic en su barra) */
    public static function partida()
    {
        $proyecto = proyecto_de_peticion(true);
        $msr = self::msrDe($proyecto);
        if (!$msr) json_respuesta(['success' => false, 'error' => 'MSR no encontrado'], 404);
        $db = MsrItem::getDB();
        $mid = (int)$msr->id;
        $viejo = (string)($_POST['viejo'] ?? '');
        $titulo = mb_substr(trim((string)($_POST['titulo'] ?? '')), 0, 200);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '';
        if ($titulo === '') json_respuesta(['success' => false, 'error' => 'El título no puede quedar vacío'], 422);
        $v = $db->escape_string($viejo);
        $t = $db->escape_string($titulo);
        if ($titulo !== $viejo) {
            $dup = $db->query("SELECT 1 FROM msr_items WHERE msr_id = $mid AND seccion = '$t' LIMIT 1");
            $dupP = $db->query("SELECT 1 FROM msr_partidas WHERE msr_id = $mid AND titulo = '$t' LIMIT 1");
            if (($dup && $dup->num_rows) || ($dupP && $dupP->num_rows)) json_respuesta(['success' => false, 'error' => 'Ya existe una partida con ese título'], 422);
            $db->query("UPDATE msr_items SET seccion = '$t' WHERE msr_id = $mid AND seccion = '$v'");
        }
        $existe = $db->query("SELECT id FROM msr_partidas WHERE msr_id = $mid AND titulo = '$v' LIMIT 1");
        if ($existe && ($f = $existe->fetch_row())) {
            $db->query("UPDATE msr_partidas SET titulo = '$t', color = '$color' WHERE id = " . (int)$f[0]);
        } else {
            $orden = (int)$db->query("SELECT COALESCE(MAX(orden),-1)+1 FROM msr_partidas WHERE msr_id = $mid")->fetch_row()[0];
            $db->query("INSERT INTO msr_partidas (msr_id, titulo, color, orden) VALUES ($mid, '$t', '$color', $orden)");
        }
        json_respuesta(['success' => true, 'titulo' => $titulo, 'color' => $color]);
    }

    /** Umbral (días) a partir del cual la entrega se marca como crítica */
    public static function umbral()
    {
        $proyecto = proyecto_de_peticion(true);
        $v = filter_var($_POST['umbral'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
        if ($v) {
            $proyecto->msr_umbral_critico = $v;
            $proyecto->guardar();
        }
        $msr = self::msrDe($proyecto);
        header('Location: ' . ($msr ? '/msr/ver?proyecto=' . $proyecto->id . '&id=' . $msr->id : '/msr?proyecto=' . $proyecto->id));
        exit;
    }

    // ---------- Importación ----------
    public static function importar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $msr = self::msrDe($proyecto) ?? Msr::asegurar((int)$proyecto->id);
        $vista = ['titulo' => 'Importar MSR - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'previa' => null, 'msr' => $msr];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $f = $_FILES['archivo'] ?? null;
            $ok = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name'])
                && strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'xlsx' && $f['size'] <= 30 * 1024 * 1024;
            if (!$ok) {
                Proyecto::setAlerta('error', 'Sube un archivo .xlsx de máximo 30 MB');
            } else {
                try {
                    @set_time_limit(180);
                    $vista['previa'] = MsrImportador::analizar($f['tmp_name'], (int)$proyecto->id);
                    $vista['existentes'] = MsrImportador::partidasDe((int)$msr->id);
                    $vista['nuevas'] = $vista['existentes'] ? MsrImportador::partidasNuevas($vista['previa']['filas'], (int)$msr->id) : [];
                } catch (\Throwable $e) {
                    Proyecto::setAlerta('error', 'No se pudo leer el archivo: ' . $e->getMessage());
                }
            }
        }
        $vista['alertas'] = Proyecto::getAlertas();
        $router->render('msr/importar', $vista);
    }

    public static function confirmarImportacion()
    {
        $proyecto = proyecto_de_peticion(true);
        try {
            @set_time_limit(180);
            $msr = self::msrDe($proyecto) ?? Msr::asegurar((int)$proyecto->id);
            $r = MsrImportador::aplicar($_POST['token'] ?? '', $proyecto, (int)$msr->id, self::unionDePost());
            $q = http_build_query(['proyecto' => $proyecto->id, 'id' => $msr->id, 'importado' => $r['renglones'], 'mat' => $r['materiales'], 'sub' => $r['submittals'], 'omitidos' => $r['omitidos']]);
            header("Location: /msr/ver?$q");
        } catch (\Throwable $e) {
            header('Location: /msr/importar?proyecto=' . $proyecto->id . '&msr=' . (int)($_POST['msr'] ?? 0) . '&error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    /** ['Partida del Excel' => 'Partida existente'] elegido en la vista previa; vacío = crear partida nueva */
    private static function unionDePost(): array
    {
        $u = [];
        foreach ((array)($_POST['union'] ?? []) as $x) {
            if (is_array($x) && !empty($x['de']) && !empty($x['a'])) $u[(string)$x['de']] = (string)$x['a'];
        }
        return $u;
    }

    public static function cancelarImportacion()
    {
        $proyecto = proyecto_de_peticion(true);
        MsrImportador::limpiar($_POST['token'] ?? '');
        header('Location: /msr/importar?proyecto=' . $proyecto->id . '&msr=' . (int)($_POST['msr'] ?? 0));
        exit;
    }

    // ---------- util ----------
    private static function itemEditable(): MsrItem
    {
        $item = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth()
            ? MsrItem::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $item ? Proyecto::find($item->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        }
        return $item;
    }

}
