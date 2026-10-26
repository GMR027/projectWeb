<?php

namespace Controllers;

use MVC\Router;
use Model\Material;
use Model\SheetflowHoja;
use Model\Submittal;
use Model\Msr;
use Model\Grafico;
use Model\GraficoCategoria;
use Classes\Catalogo;
use Classes\Imagenes;
use Classes\Sheetflow;

/** Paquetes de submittals (empaquetador de materiales) */
class SubmittalController
{
    /** Departamento de SheetFlow donde viven las tablas de materiales generadas desde un submittal */
    private const DEPTO_TABLA_MATERIALES = 'Materiales';

    /** Nombre de la tabla de materiales de un paquete */
    private static function nombreTablaMateriales(Submittal $submittal): string
    {
        $numero = trim((string)$submittal->numero_submittal) ?: ('#' . (int)$submittal->id);
        return mb_substr('Materiales · Submittal ' . $numero, 0, 150);
    }

    /** Marca que guarda la tabla en su descripción: así se reconoce aunque le cambien el nombre */
    private static function marcaTablaMateriales(Submittal $submittal): string
    {
        $numero = trim((string)$submittal->numero_submittal) ?: ('#' . (int)$submittal->id);
        return mb_substr('Tabla de materiales del submittal ' . $numero . ' (generada automáticamente)', 0, 300);
    }

    /** Tabla de materiales ya generada para este paquete (por la marca de la descripción o, si se editó, por su nombre) */
    private static function tablaMaterialesDe($proyecto, Submittal $submittal): ?SheetflowHoja
    {
        $marca = self::marcaTablaMateriales($submittal);
        $nombre = mb_strtolower(self::nombreTablaMateriales($submittal));
        $porNombre = null;
        foreach (SheetflowHoja::deProyecto((int)$proyecto->id) as $h) {
            if (trim((string)$h->descripcion) === $marca) return $h;   // la marca manda aunque se haya renombrado el departamento
            if ($porNombre === null && $h->departamento === self::DEPTO_TABLA_MATERIALES
                && mb_strtolower(trim((string)$h->nombre)) === $nombre) $porNombre = $h;
        }
        return $porNombre;
    }

    /** Copia la miniatura del material a la carpeta de imágenes de SheetFlow (nombre estable por contenido, sin duplicar) */
    private static function imagenTablaMateriales(?string $imagen): ?string
    {
        $ruta = Imagenes::ruta($imagen);
        if (!$ruta) return null;
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg'], true)) {
            $nuevo = Imagenes::guardarGrande($ruta, 600);
            return $nuevo ? '/uploads/vplan/' . $nuevo : null;
        }
        $nombre = md5_file($ruta) . '.' . $ext;
        $destino = Imagenes::CARPETA_VPLAN . '/' . $nombre;
        if (!is_file($destino)) {
            if (!is_dir(Imagenes::CARPETA_VPLAN)) mkdir(Imagenes::CARPETA_VPLAN, 0775, true);
            if (!@copy($ruta, $destino)) return null;
        }
        return '/uploads/vplan/' . $nombre;
    }

    /** Contenido base de la tabla: imagen, nombre, marca, modelo o tipo, referencia y especialidad de cada material del paquete */
    private static function datosTablaMateriales(Submittal $submittal): array
    {
        $cols = [
            ['n' => 'Imagen del material', 'w' => 90],
            ['n' => 'Nombre del material', 'w' => 240],
            ['n' => 'Marca', 'w' => 120],
            ['n' => 'Modelo o tipo', 'w' => 140],
            ['n' => 'Referencia', 'w' => 130],
            ['n' => 'Especialidad', 'w' => 150],
        ];
        $rows = [];
        $fmt = [];
        foreach ($submittal->materiales() as $i => $m) {
            $rows[] = ['', (string)$m->nombreBase(), (string)$m->marca, (string)$m->modelo, (string)$m->referencia, (string)$m->especialidad];
            if ($img = self::imagenTablaMateriales($m->imagen)) $fmt["$i,0"] = ['im' => $img];
        }
        if (!$rows) $rows[] = array_fill(0, count($cols), '');
        return ['cols' => $cols, 'rows' => $rows, 'fmt' => $fmt];
    }

    /**
     * Botón «Generar tabla de materiales» del submittal.
     * accion=estado dice si ya existe (y su URL), accion=crear la crea y accion=reescribir la regenera con los materiales actuales.
     */
    public static function tablaMateriales()
    {
        $proyecto = proyecto_de_peticion(true);
        $submittal = self::submittalDe($proyecto);
        if (!$submittal) json_respuesta(['success' => false, 'error' => 'El submittal no existe'], 404);

        $existente = self::tablaMaterialesDe($proyecto, $submittal);
        $url = fn(SheetflowHoja $h) => '/sheetflow/abrir?proyecto=' . (int)$proyecto->id . '&id=' . (int)$h->id;
        $accion = (string)($_POST['accion'] ?? 'estado');

        if ($accion === 'estado') {
            json_respuesta(['success' => true, 'existe' => (bool)$existente, 'id' => $existente ? (int)$existente->id : null,
                'url' => $existente ? $url($existente) : null]);
        }
        if (!in_array($accion, ['crear', 'reescribir'], true)) json_respuesta(['success' => false, 'error' => 'Acción no válida'], 422);
        if ($existente && $accion === 'crear') json_respuesta(['success' => false, 'existe' => true, 'error' => 'La tabla de materiales ya existe'], 409);

        $datos = json_encode(Sheetflow::normalizar(self::datosTablaMateriales($submittal)), JSON_UNESCAPED_UNICODE);
        if ($existente) {
            $existente->fecha = date('Y-m-d');
            if ($existente->departamento === '') $existente->departamento = self::DEPTO_TABLA_MATERIALES;
            $existente->descripcion = self::marcaTablaMateriales($submittal);
            $existente->datos = $datos;
            $existente->guardar();
            $hoja = $existente;
        } else {
            $departamento = Catalogo::agregarDepartamento((int)$proyecto->id, self::DEPTO_TABLA_MATERIALES) ?: self::DEPTO_TABLA_MATERIALES;
            $hoja = new SheetflowHoja([
                'proyecto_id' => (int)$proyecto->id, 'nombre' => self::nombreTablaMateriales($submittal),
                'fecha' => date('Y-m-d'), 'departamento' => $departamento,
                'descripcion' => self::marcaTablaMateriales($submittal), 'datos' => $datos,
                'creado_por' => $_SESSION['id'] ?? null,
            ]);
            $hoja->id = $hoja->guardar()['id'];
        }
        json_respuesta(['success' => true, 'id' => (int)$hoja->id, 'url' => $url($hoja)]);
    }

    private static function submittalDe($proyecto): ?Submittal
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $s = Submittal::find(filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT));
        return $s && (int)$s->proyecto_id === (int)$proyecto->id ? $s : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $cp = \Classes\CamposPersonalizados::aplicar($pid, 'submittal', Submittal::deProyecto($pid), fn($p) => (int)$p->id);
        $router->render('submittals/index', [
            'titulo' => 'Submittals - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'paquetes' => $cp['items'], 'cp' => $cp, 'diseno' => \Classes\ListaDiseno::para($pid, 'submittals'),
            'msrNombres' => array_column(array_map(fn($m) => ['id' => $m->id, 'n' => $m->nombre], Msr::deProyecto((int)$proyecto->id)), 'n', 'id'),
            'conteos' => Submittal::conteos((int)$proyecto->id),
        ]);
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $submittal = self::submittalDe($proyecto);
        if (!$submittal) {
            header('Location: /submittals?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('submittals/ver', [
            'titulo' => 'Submittal ' . $submittal->numero_submittal, 'proyecto' => $proyecto,
            'submittal' => $submittal, 'materiales' => $submittal->materiales(), 'laminas' => $submittal->laminas(),
            'verif' => is_admin() ? \Classes\MaterialesInteligentes::deProyecto((int)$proyecto->id) : [],
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $msrId = Msr::deProyectoPorId((int)$proyecto->id, $_GET['msr'] ?? null)?->id ?? Msr::asegurar((int)$proyecto->id)->id;
        $submittal = new Submittal(['numero_trabajo' => $proyecto->numero_trabajo ?? '', 'msr_id' => $msrId]);
        self::formulario($router, $proyecto, $submittal, []);
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $submittal = self::submittalDe($proyecto);
        if (!$submittal) {
            header('Location: /submittals?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $proyecto, $submittal, $submittal->idsMateriales(), $submittal->idsLaminas());
    }

    private static function formulario(Router $router, $proyecto, Submittal $submittal, array $marcados, array $laminasMarcadas = [])
    {
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach (['fecha', 'numero_trabajo', 'departamento', 'para', 'numero_submittal', 'especialidad'] as $c) {
                $submittal->$c = trim($_POST[$c] ?? '');
            }
            $submittal->especialidad = \Model\Especialidad::resolver((int)$proyecto->id, $submittal->especialidad);
            $submittal->proyecto_id = $proyecto->id;
            $submittal->msr_id = Msr::deProyectoPorId((int)$proyecto->id, $_POST['msr_id'] ?? null)?->id;
            $marcados = array_map('intval', $_POST['materiales'] ?? []);
            $laminasMarcadas = array_map('intval', $_POST['laminas'] ?? []);
            $alertas = $submittal->validar();
            // Materiales inteligentes: un material no aprobado por la normativa solo se envía si se confirma expresamente
            $malos = ($alertas || !is_admin()) ? [] : \Classes\MaterialesInteligentes::noAprobados((int)$proyecto->id, $marcados);
            $nuevosMalos = array_diff_key($malos, array_flip(array_map('intval', $submittal->id ? $submittal->idsMateriales() : [])));
            if ($nuevosMalos && empty($_POST['confirmar_no_aprobados'])) {
                $alertas['error'][] = 'Materiales NO aprobados por las normativas y especificaciones: ' . implode('; ', array_map(fn($m) => $m['nombre'] . ' (' . $m['resumen'] . ')', $nuevosMalos)) . '. Quítalos del paquete o marca la casilla de confirmación para enviarlos de todas formas.';
                $pedirConfirmacion = true;
            }
            if (!$alertas) {
                $r = $submittal->guardar();
                if (is_array($r)) $submittal->id = $r['id'];
                $submittal->sincronizarMateriales($marcados);
                $submittal->sincronizarLaminas($laminasMarcadas);
                header('Location: /submittals/ver?proyecto=' . $proyecto->id . '&id=' . $submittal->id);
                exit;
            }
        }
        $router->render('submittals/formulario', [
            'titulo' => $submittal->id ? 'Editar submittal' : 'Nuevo submittal', 'proyecto' => $proyecto,
            'submittal' => $submittal, 'marcados' => $marcados, 'alertas' => $alertas,
            'laminasMarcadas' => $laminasMarcadas,
            'laminasDisponibles' => Grafico::laminasParaSeleccion((int)$proyecto->id),
            'categoriasLamina' => GraficoCategoria::deProyecto((int)$proyecto->id),
            'materiales' => Material::deProyecto((int)$proyecto->id), 'msrs' => Msr::deProyecto((int)$proyecto->id),
            'noAprobados' => is_admin() ? \Classes\MaterialesInteligentes::noAprobados((int)$proyecto->id, array_map(fn($m) => (int)$m->id, Material::deProyecto((int)$proyecto->id))) : [],
            'pedirConfirmacion' => $pedirConfirmacion ?? false,
        ]);
    }

    /** Cicla el estatus: enviado → rechazado → aprobado (o fija el indicado) */
    public static function estatus()
    {
        $proyecto = proyecto_de_peticion(true);
        $s = self::submittalDe($proyecto);
        $nuevo = $_POST['estatus'] ?? '';
        if (!$s || !isset(Submittal::ESTATUS[$nuevo])) {
            header('Content-Type: application/json');
            http_response_code(422);
            echo json_encode(['success' => false]);
            exit;
        }
        $anterior = $s->estatus;
        $s->estatus = $nuevo;
        $s->guardar();
        $s->sincronizarMsr($anterior);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'estatus' => $nuevo]);
        exit;
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($s = self::submittalDe($proyecto)) $s->eliminar();
        header('Location: /submittals?proyecto=' . $proyecto->id);
        exit;
    }
}
