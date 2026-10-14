<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Restriccion;
use Model\TipoRestriccion;
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

        $router->render('lookahead/index', $datos + [
            'titulo' => '4WLA - ' . $proyecto->nombre,
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
        $semanas = self::armarSemanas($lunes, $calendario);
        $tipos = TipoRestriccion::all('id ASC');
        $restricciones = self::restriccionesActivas((int)$proyecto->id);

        $filas = [];
        foreach ($proyecto->getPartidas() as $partida) {
            $acts = [];
            foreach ($partida->getActividades() as $act) {
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

        return ['semanas' => $semanas, 'tipos' => $tipos, 'filas' => $filas];
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

    /** 5 bloques de 7 días: actual + 4 semanas */
    private static function armarSemanas(\DateTime $lunes, Calendario $calendario): array
    {
        $etiquetas = ['ACTUAL', '1ª SEMANA', '2ª SEMANA', '3ª SEMANA', '4ª SEMANA'];
        $semanas = [];
        $cursor = clone $lunes;
        for ($i = 0; $i < 5; $i++) {
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
                'etiqueta' => $etiquetas[$i],
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
