<?php

namespace Controllers;

use MVC\Router;
use Model\Minuta;
use Model\Personal;
use Model\PersonalContratista;
use Model\BitacoraDepartamento;

/** Minutas de junta y catálogo de personal del contratista */
class MinutaController
{
    private static function esPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    private static function idPeticion(): ?int
    {
        return filter_var((self::esPost() ? $_POST : $_GET)['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    }

    /** Renglones tema/responsable/departamento enviados como arreglos paralelos: {$campo}[tema][], {$campo}[responsable][], … */
    private static function items(string $campo): array
    {
        $d = $_POST[$campo] ?? [];
        $out = [];
        foreach ((array)($d['tema'] ?? []) as $i => $tema) {
            $tema = mb_substr(trim((string)$tema), 0, 500);
            if ($tema === '') continue;
            $out[] = [
                'tema' => $tema,
                'responsable' => mb_substr(trim((string)($d['responsable'][$i] ?? '')), 0, 150),
                'departamento' => mb_substr(trim((string)($d['departamento'][$i] ?? '')), 0, 100),
            ];
        }
        return $out;
    }

    private static function departamentos(int $proyectoId): array
    {
        return \Classes\Catalogo::departamentos($proyectoId);
    }

    // ============================== MINUTAS ==============================

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $minutas = Minuta::deProyecto((int)$proyecto->id);
        $porDepto = [];
        foreach ($minutas as $m) $porDepto[$m->departamento !== '' ? $m->departamento : \Classes\Carpetas::SIN][] = $m;
        uksort($porDepto, fn($a, $b) => ($a === \Classes\Carpetas::SIN) <=> ($b === \Classes\Carpetas::SIN) ?: strcasecmp($a, $b));   // «Sin departamento» al final
        $router->render('minutas/index', ['titulo' => 'Minutas - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'minutas' => $minutas, 'porDepto' => $porDepto]);
    }

    /** Datos ya resueltos de una minuta (personas por nombre) para la vista y el PDF */
    public static function detalle(Minuta $m, int $proyectoId): array
    {
        $co = PersonalContratista::indexado($proyectoId);
        $pe = Personal::indexado($proyectoId);
        return [
            'contratista' => array_values(array_filter(array_map(fn($id) => $co[$id] ?? null, $m->getContratista()))),
            'personal' => array_values(array_filter(array_map(fn($id) => $pe[$id] ?? null, $m->getPersonal()))),
            'temas' => $m->getTemas(),
            'compromisos' => $m->getCompromisos(),
        ];
    }

    private static function deLaPeticion(): array
    {
        $proyecto = proyecto_de_peticion();
        $m = Minuta::find(self::idPeticion());
        if (!$m || (int)$m->proyecto_id !== (int)$proyecto->id) {
            header('Location: /minutas?proyecto=' . $proyecto->id);
            exit;
        }
        return [$proyecto, $m];
    }

    public static function ver(Router $router)
    {
        [$proyecto, $m] = self::deLaPeticion();
        $router->render('minutas/ver', ['titulo' => 'Minuta - ' . $m->nombre, 'proyecto' => $proyecto, 'minuta' => $m] + self::detalle($m, (int)$proyecto->id));
    }

    public static function pdf()
    {
        [$proyecto, $m] = self::deLaPeticion();
        if (!puede_descargar()) {
            http_response_code(403);
            exit('No autorizado');
        }
        $ruta = (new \Classes\Exportadores\MinutaPdfExporter($proyecto))->setMinuta($m)->generar('Minuta_' . slug_archivo($m->nombre));
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        @unlink($ruta);   // no se conserva en public/exportaciones
        exit;
    }

    public static function crear(Router $router)
    {
        self::formulario($router, new Minuta());
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $m = Minuta::find(self::idPeticion());
        if (!$m || (int)$m->proyecto_id !== (int)$proyecto->id) {
            header('Location: /minutas?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $m);
    }

    private static function formulario(Router $router, Minuta $m)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $contratista = PersonalContratista::indexado($pid);
        $personal = Personal::indexado($pid);
        $alertas = [];
        if (self::esPost()) {
            $m->proyecto_id = $pid;
            $m->fecha = trim($_POST['fecha'] ?? '');
            $m->nombre = trim($_POST['nombre'] ?? '');
            $m->importancia = $_POST['importancia'] ?? '';
            $m->departamento = mb_substr(trim((string)($_POST['departamento'] ?? '')), 0, 100);
            $m->setIds('contratista_json', array_filter(array_map('intval', (array)($_POST['contratista'] ?? [])), fn($i) => isset($contratista[$i])));
            $m->setIds('personal_json', array_filter(array_map('intval', (array)($_POST['personal'] ?? [])), fn($i) => isset($personal[$i])));
            $m->setItems('temas_json', self::items('temas'));
            $m->setItems('compromisos_json', self::items('compromisos'));
            $alertas = $m->validar();
            if (!$alertas) {
                $m->guardar();
                header('Location: /minutas?proyecto=' . $pid . '&ok=1');
                exit;
            }
        }
        $router->render('minutas/formulario', [
            'titulo' => $m->id ? 'Editar minuta' : 'Nueva minuta', 'proyecto' => $proyecto, 'minuta' => $m, 'alertas' => $alertas,
            'contratista' => $contratista, 'personal' => $personal, 'departamentos' => self::departamentos($pid),
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $m = Minuta::find(self::idPeticion());
        if ($m && (int)$m->proyecto_id === (int)$proyecto->id) $m->eliminar();
        header('Location: /minutas?proyecto=' . $proyecto->id);
        exit;
    }

    // ===================== PERSONAL DEL CONTRATISTA =====================

    /** A dónde volver tras editar el personal del contratista: el Directorio si se llegó desde ahí */
    private static function listaContratista(int $proyectoId, string $extra = ''): string
    {
        return (($_REQUEST['volver'] ?? '') === 'directorio' ? '/directorio?tab=contratista&' : '/minutas/contratista?') . 'proyecto=' . $proyectoId . $extra;
    }

    public static function contratista(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('minutas/contratista', ['titulo' => 'Personal del contratista - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'lista' => PersonalContratista::deProyecto((int)$proyecto->id)]);
    }

    public static function contratistaCrear(Router $router)
    {
        self::contratistaFormulario($router, new PersonalContratista());
    }

    public static function contratistaEditar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $p = PersonalContratista::find(self::idPeticion());
        if (!$p || (int)$p->proyecto_id !== (int)$proyecto->id) {
            header('Location: ' . self::listaContratista((int)$proyecto->id));
            exit;
        }
        self::contratistaFormulario($router, $p);
    }

    private static function contratistaFormulario(Router $router, PersonalContratista $p)
    {
        $proyecto = proyecto_de_peticion(true);
        $alertas = [];
        if (self::esPost()) {
            $p->proyecto_id = $proyecto->id;
            $p->nombre = trim($_POST['nombre'] ?? '');
            $p->puesto = trim($_POST['puesto'] ?? '');
            $p->empresa = trim($_POST['empresa'] ?? '');
            $alertas = $p->validar();
            if (!$alertas) {
                $p->guardar();
                header('Location: ' . self::listaContratista((int)$proyecto->id, '&ok=1'));
                exit;
            }
        }
        $router->render('minutas/contratista-formulario', ['titulo' => $p->id ? 'Editar personal del contratista' : 'Nuevo personal del contratista', 'proyecto' => $proyecto, 'persona' => $p, 'alertas' => $alertas]);
    }

    public static function contratistaEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $p = PersonalContratista::find(self::idPeticion());
        if ($p && (int)$p->proyecto_id === (int)$proyecto->id) $p->eliminar();   // las minutas ya guardadas ignoran el id que no existe
        header('Location: ' . self::listaContratista((int)$proyecto->id));
        exit;
    }
}
