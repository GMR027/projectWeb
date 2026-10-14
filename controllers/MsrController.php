<?php

namespace Controllers;

use MVC\Router;
use Model\MsrItem;
use Model\Proyecto;
use Model\Usuario;
use Classes\MsrImportador;

/** MSR · Material Status Report */
class MsrController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('msr/index', self::datos($proyecto) + [
            'titulo' => 'MSR - ' . $proyecto->nombre,
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    /** Datos del MSR (también los usan los exportadores) */
    public static function datos(Proyecto $proyecto): array
    {
        $items = MsrItem::deProyecto((int)$proyecto->id);
        $secciones = [];
        foreach ($items as $it) {
            $secciones[$it->seccion][] = $it;
        }
        $dueno = Usuario::find($proyecto->usuario_id);
        return [
            'proyecto' => $proyecto,
            'items' => $items,
            'secciones' => $secciones,
            'umbral' => max(1, (int)$proyecto->msr_umbral_critico),
            'logoEmpresa' => $dueno && $dueno->logo ? $dueno->logo : $proyecto->logo,
            'proveedores' => array_values(array_unique(array_filter(array_map(fn($i) => $i->proveedor, $items)))),
        ];
    }

    /** Cambia el status en ciclo P → A → RA */
    public static function status()
    {
        $item = self::itemEditable();
        $item->status = MsrItem::STATUS_CICLO[$item->status] ?? 'P';
        $item->guardar();
        self::json(['success' => true, 'status' => $item->status]);
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
        $item->guardar();
        $umbral = max(1, (int)(Proyecto::find($item->proyecto_id)->msr_umbral_critico ?? 60));
        self::json([
            'success' => true, 'dias' => $item->diasEntrega(), 'critico' => $item->esCritico($umbral),
            'atrasoCompra' => (bool)$item->compraAtrasada(), 'atrasoEntrega' => (bool)$item->entregaAtrasada(),
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
        header('Location: /msr?proyecto=' . $proyecto->id);
        exit;
    }

    // ---------- Importación ----------
    public static function importar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $vista = ['titulo' => 'Importar MSR - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'previa' => null];
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
            $r = MsrImportador::aplicar($_POST['token'] ?? '', $proyecto);
            $q = http_build_query(['proyecto' => $proyecto->id, 'importado' => $r['renglones'], 'mat' => $r['materiales'], 'sub' => $r['submittals'], 'omitidos' => $r['omitidos']]);
            header("Location: /msr?$q");
        } catch (\Throwable $e) {
            header('Location: /msr/importar?proyecto=' . $proyecto->id . '&error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function cancelarImportacion()
    {
        $proyecto = proyecto_de_peticion(true);
        MsrImportador::limpiar($_POST['token'] ?? '');
        header('Location: /msr/importar?proyecto=' . $proyecto->id);
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
