<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Restriccion;
use Model\TipoRestriccion;
use Model\LookaheadConfig;
use Classes\Calendario;

/** 4WLA: semana actual + 4 semanas de lookahead, mismos datos que el programa en formato de planeación */
class LookaheadController
{
    private const MESES = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
    private const DIAS = [1 => 'L', 2 => 'M', 3 => 'M', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];

    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_ver_proyecto($proyecto);

        $lunes = self::lunesDe($_GET['fecha'] ?? '');
        $datos = self::datos($proyecto, $lunes);
        $config = LookaheadConfig::deProyecto((int)$proyecto->id);
        // Campos personalizados de las actividades: aquí solo filtran (la agrupación ya es por partida)
        $pid = (int)$proyecto->id;
        $cp = \Classes\CamposPersonalizados::aplicar($pid, 'actividad', [], fn($x) => 0);
        if ($cp['filtros']) {
            $vals = \Classes\CamposPersonalizados::valores($pid, 'actividad');
            foreach ($datos['filas'] as $i => $bloque) {
                $datos['filas'][$i]['actividades'] = array_values(array_filter($bloque['actividades'], fn($f) => \Classes\CamposPersonalizados::coincide($vals[(int)$f['actividad']->id] ?? [], $cp['filtros'], $cp['campos'])));
            }
            $datos['filas'] = array_values(array_filter($datos['filas'], fn($b) => $b['actividades']));
        }

        $router->render('lookahead/index', $datos + [
            'titulo' => $config->nombreHerramienta() . ' - ' . $proyecto->nombre,
            'config' => $config, 'cp' => $cp, 'diseno' => \Classes\ListaDiseno::para($pid, 'lookahead'),
            'encabezados' => LookaheadConfig::disponible() ? LookaheadConfig::encabezadosDisponibles((int)$proyecto->id) : [],
            'proyecto' => $proyecto,
            'lunes' => $lunes,
            'anterior' => (clone $lunes)->modify('-7 days')->format('Y-m-d'),
            'siguiente' => (clone $lunes)->modify('+7 days')->format('Y-m-d'),
        ]);
    }

    /** Lunes de la semana de la fecha dada (Y-m-d); hoy si es inválida */
    public static function lunesDe(string $fecha): \DateTime
    {
        $ref = \DateTime::createFromFormat('Y-m-d', $fecha) ?: new \DateTime('today');
        $ref->setTime(0, 0);
        return (int)$ref->format('N') === 1 ? $ref : $ref->modify('last monday');
    }

    /** Datos del 4WLA (también los usa el exportador PDF) */
    public static function datos(Proyecto $proyecto, \DateTime $lunes): array
    {
        $calendario = new Calendario((int)$proyecto->id);
        $config = LookaheadConfig::deProyecto((int)$proyecto->id);
        $semanas = self::armarSemanas($lunes, $calendario, (int)$config->semanas);
        $tipos = TipoRestriccion::all('id ASC');
        $restricciones = self::restriccionesActivas((int)$proyecto->id);

        $filas = [];
        foreach ($proyecto->getPartidasConActividades() as $bloque) {
            $partida = $bloque['partida'];
            $acts = [];
            foreach ($bloque['actividades'] as $act) {
                $atrasada = $act->fecha_fin < $lunes->format('Y-m-d') && (int)$act->progreso < 100;
                $porTipo = $restricciones[$act->id] ?? [];
                $celdas = [];
                foreach ($semanas as $sem) {
                    foreach ($sem['dias'] as $d) {
                        $celdas[$d['fecha']] = $d['fecha'] >= $act->fecha_inicio && $d['fecha'] <= $act->fecha_fin && $d['habil'];
                    }
                }
                $acts[] = [
                    'actividad' => $act,
                    'celdas' => $celdas,
                    'tipos' => $porTipo,
                    'pta_ok' => (int)$act->pta === 1,
                    'con_restriccion' => !empty($porTipo),
                    'atrasada' => $atrasada,
                ];
            }
            if ($acts) {
                $filas[] = ['partida' => $partida, 'actividades' => $acts];
            }
        }

        return ['semanas' => $semanas, 'tipos' => $tipos, 'filas' => $filas, 'config' => $config];
    }

    /** Alterna PTA (OK / !) de una actividad. Solo quien gestiona el proyecto. */
    public static function togglePta()
    {
        header('Content-Type: application/json');
        $act = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth()
            ? \Model\Actividad::find(filter_var($_POST['actividad_id'] ?? null, FILTER_VALIDATE_INT))
            : null;
        $partida = $act ? \Model\Partida::find($act->partida_id) : null;
        if (!$partida || !puede_gestionar_proyecto(Proyecto::find($partida->proyecto_id))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        $act->pta = (int)$act->pta === 1 ? 0 : 1;
        $act->guardar();
        echo json_encode(['success' => true, 'pta' => (int)$act->pta]);
        exit;
    }

    /** Responde JSON y termina; solo quien gestiona el proyecto puede cambiar ajustes */
    private static function gestorOFalla(): Proyecto
    {
        header('Content-Type: application/json');
        $proyecto = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? Proyecto::find(filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }
        return $proyecto;
    }

    /** Guarda los ajustes enviados (solo los campos presentes): semanas, nombre, pta_nombre, titulo, encabezado_id */
    public static function guardarConfig()
    {
        $proyecto = self::gestorOFalla();
        if (!LookaheadConfig::disponible()) {
            echo json_encode(['success' => false, 'error' => 'Falta aplicar la migración database/migracion_lookahead_config.sql']);
            exit;
        }
        $c = LookaheadConfig::deProyecto((int)$proyecto->id);
        $c->proyecto_id = $proyecto->id;
        foreach (['nombre', 'pta_nombre', 'titulo'] as $campo) {
            if (isset($_POST[$campo])) $c->$campo = trim((string)$_POST[$campo]);
        }
        if (isset($_POST['semanas'])) $c->semanas = max(LookaheadConfig::MIN_SEMANAS, min(LookaheadConfig::MAX_SEMANAS, (int)$_POST['semanas']));
        if (isset($_POST['encabezado_id'])) {
            $c->encabezado_id = (int)$_POST['encabezado_id'] ?: null;
            if ($c->encabezado_id && !$c->encabezado()) $c->encabezado_id = null;
        }
        $c->guardar();
        echo json_encode(['success' => true]);
        exit;
    }

    /** Crea (sin id), renombra/recolorea (con id) o elimina (accion=eliminar) una limitación. Son comunes a todos los proyectos: solo el administrador. */
    public static function guardarLimitacion()
    {
        self::gestorOFalla();
        if (!is_admin()) {   // los tipos son de todos los proyectos: solo el administrador los cambia
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Solo el administrador puede cambiar las limitaciones']);
            exit;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $nombre = mb_substr(trim((string)($_POST['nombre'] ?? '')), 0, 50);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#FF5A1F';
        $t = $id ? TipoRestriccion::find($id) : null;

        if (($_POST['accion'] ?? '') === 'eliminar') {
            if (!$t) { echo json_encode(['success' => false, 'error' => 'No existe']); exit; }
            $n = \Model\Restriccion::consultarSQL('SELECT * FROM restricciones WHERE tipo_id = ' . (int)$t->id);
            if ($n) {
                echo json_encode(['success' => false, 'error' => 'La limitación «' . $t->nombre . '» tiene ' . count($n) . ' restricción(es) registrada(s). Resuélvelas o cámbialas de tipo antes de quitarla.']);
                exit;
            }
            $t->eliminar();
            echo json_encode(['success' => true]);
            exit;
        }
        if ($nombre === '') { echo json_encode(['success' => false, 'error' => 'Escribe un nombre']); exit; }
        foreach (TipoRestriccion::all('id ASC') as $o) {
            if (mb_strtolower($o->nombre) === mb_strtolower($nombre) && (!$t || (int)$o->id !== (int)$t->id)) {
                echo json_encode(['success' => false, 'error' => 'Ya existe una limitación con ese nombre']);
                exit;
            }
        }
        $t ??= new TipoRestriccion();
        $t->nombre = $nombre;
        $t->color = $color;
        $t->guardar();
        echo json_encode(['success' => true]);
        exit;
    }

    /** Bloques de 7 días: la semana actual + las $siguientes que pida la configuración (1-8) */
    private static function armarSemanas(\DateTime $lunes, Calendario $calendario, int $siguientes = 4): array
    {
        $semanas = [];
        $cursor = clone $lunes;
        for ($i = 0; $i <= $siguientes; $i++) {
            $dias = [];
            $meses = [];
            for ($d = 0; $d < 7; $d++) {
                $fecha = $cursor->format('Y-m-d');
                $n = (int)$cursor->format('n');
                $meses[$n] = self::MESES[$n];
                $dias[] = [
                    'fecha' => $fecha,
                    'numero' => (int)$cursor->format('j'),
                    'letra' => self::DIAS[(int)$cursor->format('N')],
                    'habil' => $calendario->esHabil($fecha),
                    'hoy' => $fecha === date('Y-m-d'),
                ];
                $cursor->modify('+1 day');
            }
            $semanas[] = [
                'etiqueta' => $i === 0 ? 'ACTUAL' : $i . 'ª SEMANA',
                'iso' => (int)(new \DateTime($dias[0]['fecha']))->format('W'),
                'mes' => implode(' / ', $meses),
                'dias' => $dias,
            ];
        }
        return $semanas;
    }

    /** @return array<int, array<int,int>> actividad_id => [tipo_id => cantidad] */
    public static function restriccionesActivas(int $proyectoId): array
    {
        $rows = Restriccion::consultarSQL(
            "SELECT r.* FROM restricciones r
             JOIN actividades a ON a.id = r.actividad_id
             JOIN partidas p ON p.id = a.partida_id
             WHERE p.proyecto_id = $proyectoId AND r.estado = 'activa'"
        );
        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$r->actividad_id][$r->tipo_id] = ($mapa[$r->actividad_id][$r->tipo_id] ?? 0) + 1;
        }
        return $mapa;
    }
}
