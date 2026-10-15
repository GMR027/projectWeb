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
            $msr->proyecto_id = $proyecto->id;
            $partidasPost = array_values(array_filter($_POST['partidas'] ?? [], 'is_array'));
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
                ? array_map(fn($p) => ['id' => (int)($p['id'] ?? 0), 'titulo' => trim($p['titulo'] ?? ''), 'materiales' => array_map('intval', $p['materiales'] ?? [])], $partidasPost)
                : ($msr->id ? $msr->partidas() : []),
            'catalogo' => array_map(fn($x) => ['id' => (int)$x->id, 'nombre' => $x->nombre, 'ref' => $x->referencia, 'esp' => $x->especialidad, 'img' => $x->imagen], \Model\Material::deProyecto((int)$proyecto->id)),
        ]);
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
        if ($msr) foreach ($msr->partidas() as $p) {
            if (!isset($secciones[$p['titulo']])) $secciones[$p['titulo']] = [];
        }
        $dueno = Usuario::find($proyecto->usuario_id);
        return [
            'proyecto' => $proyecto,
            'msr' => $msr,
            'items' => $items,
            'secciones' => $secciones,
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
        self::json(['success' => true, 'status' => $nuevo, 'compartido' => (bool)$sub, 'fecha_respuesta' => $respuesta]);
    }

    /** Guarda un campo editable en línea */
    public static function campo()
    {
        $item = self::itemEditable();
        $campo = $_POST['campo'] ?? '';
        $tipo = MsrItem::EDITABLES[$campo] ?? null;
        if (!$tipo) self::json(['success' => false, 'error' => 'Campo no editable'], 422);
        $valor = trim((string)($_POST['valor'] ?? ''));
        if ($tipo === 'fecha') {
            $d = $valor === '' ? null : \DateTime::createFromFormat('Y-m-d', $valor);
            if ($valor !== '' && (!$d || $d->format('Y-m-d') !== $valor)) self::json(['success' => false, 'error' => 'Fecha inválida'], 422);
            $item->$campo = $valor === '' ? null : $valor;
        } else {
            $item->$campo = mb_substr($valor, 0, $campo === 'observaciones' ? 2000 : 200);
        }
        if ($campo === 'fecha_envio_real' && $valor === '') self::json(['success' => false, 'error' => 'La fecha del submittal no puede quedar vacía'], 422);
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
        self::json([
            'success' => true, 'dias' => $item->diasEntrega(), 'critico' => $item->esCritico($umbral),
            'atrasoCompra' => (bool)$item->compraAtrasada(), 'atrasoEntrega' => (bool)$item->entregaAtrasada(), 'compartido' => $compartido,
        ]);
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
            $r = MsrImportador::aplicar($_POST['token'] ?? '', $proyecto, (int)$msr->id);
            $q = http_build_query(['proyecto' => $proyecto->id, 'id' => $msr->id, 'importado' => $r['renglones'], 'mat' => $r['materiales'], 'sub' => $r['submittals'], 'omitidos' => $r['omitidos']]);
            header("Location: /msr/ver?$q");
        } catch (\Throwable $e) {
            header('Location: /msr/importar?proyecto=' . $proyecto->id . '&msr=' . (int)($_POST['msr'] ?? 0) . '&error=' . urlencode($e->getMessage()));
        }
        exit;
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
            self::json(['success' => false, 'error' => 'No autorizado'], 403);
        }
        return $item;
    }

    private static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
