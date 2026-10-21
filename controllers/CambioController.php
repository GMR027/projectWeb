<?php

namespace Controllers;

use MVC\Router;
use Classes\Cambios;
use Classes\CruceMateriales;
use Classes\Dependencias;
use Model\CambioPartida;
use Model\Material;
use Model\OrdenCambio;

/** Órdenes de cambio con seguimiento de lo que se instala, retira, reubica, demuele o agrega */
class CambioController
{
    private static function fuente(): array
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    }

    private static function ordenDe($proyecto, string $campo = 'id'): ?OrdenCambio
    {
        $o = OrdenCambio::find(filter_var(self::fuente()[$campo] ?? null, FILTER_VALIDATE_INT));
        return $o && (int)$o->proyecto_id === (int)$proyecto->id ? $o : null;
    }

    private static function partidaDe($proyecto): ?CambioPartida
    {
        $c = CambioPartida::find(filter_var(self::fuente()['id'] ?? null, FILTER_VALIDATE_INT));
        return $c && (int)$c->proyecto_id === (int)$proyecto->id ? $c : null;
    }

    private static function fecha($v): ?string
    {
        $v = trim((string)$v);
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }

    private static function numero($v): ?string
    {
        $v = str_replace([',', '$', ' '], '', (string)$v);
        return is_numeric($v) ? (string)$v : null;
    }

    /** Id de una actividad del proyecto (o null) */
    private static function actividadValida($proyecto, $id): ?int
    {
        $id = (int)$id;
        return $id && isset(Dependencias::actividades((int)$proyecto->id)[$id]) ? $id : null;
    }

    private static function materialValido($proyecto, $id): ?int
    {
        $m = Material::find((int)$id);
        return $m && (int)$m->proyecto_id === (int)$proyecto->id ? (int)$m->id : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $filtro = $_GET['estado'] ?? '';
        $ordenes = array_values(array_filter(OrdenCambio::deProyecto($pid), fn($o) => !isset(OrdenCambio::ESTADOS[$filtro]) || $o->estado === $filtro));
        $conteos = [];
        if (Cambios::disponible()) {
            foreach ($GLOBALS['db']->query("SELECT orden_id, COUNT(*) n, SUM(estado IN ('ejecutado','retirado','verificado')) hechas FROM cambio_partidas WHERE proyecto_id = $pid AND estado <> 'cancelado' GROUP BY orden_id") as $f) $conteos[(int)$f['orden_id']] = $f;
        }
        $router->render('cambios/index', [
            'titulo' => 'Órdenes de cambio - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'ordenes' => $ordenes, 'filtro' => $filtro, 'conteos' => $conteos,
            'resumen' => Cambios::resumen($pid), 'retiros' => Cambios::retirosPendientes($pid), 'disponible' => Cambios::disponible(),
        ]);
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $orden = self::ordenDe($proyecto);
        if (!$orden) {
            header('Location: /cambios?proyecto=' . $proyecto->id);
            exit;
        }
        $pid = (int)$proyecto->id;
        $router->render('cambios/ver', [
            'titulo' => 'Orden ' . $orden->numero . ' - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'orden' => $orden, 'partidas' => $orden->partidas(),
            'actividades' => Dependencias::actividades($pid), 'materiales' => Material::deProyecto($pid),
            'editar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        self::formulario($router, $proyecto, new OrdenCambio(['numero' => OrdenCambio::siguienteNumero((int)$proyecto->id), 'solicitante' => usuario_actual()['nombre'] ?? '']));
    }

    public static function editar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $orden = self::ordenDe($proyecto);
        if (!$orden) {
            header('Location: /cambios?proyecto=' . $proyecto->id);
            exit;
        }
        self::formulario($router, $proyecto, $orden);
    }

    private static function formulario(Router $router, $proyecto, OrdenCambio $orden)
    {
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nueva = !$orden->id;
            $orden->numero = mb_substr(trim($_POST['numero'] ?? ''), 0, 30);
            $orden->titulo = mb_substr(trim($_POST['titulo'] ?? ''), 0, 200);
            $orden->descripcion = trim($_POST['descripcion'] ?? '');
            $orden->solicitante = mb_substr(trim($_POST['solicitante'] ?? ''), 0, 150);
            $orden->estado = $_POST['estado'] ?? 'borrador';
            $orden->fecha = trim($_POST['fecha'] ?? '');
            $orden->impacto_costo = self::numero($_POST['impacto_costo'] ?? '') ?? 0;
            $orden->impacto_dias = (int)($_POST['impacto_dias'] ?? 0);
            $orden->actividad_id = self::actividadValida($proyecto, $_POST['actividad_id'] ?? 0);
            $orden->proyecto_id = $proyecto->id;
            if ($nueva) $orden->creado_por = usuario_actual()['nombre'] ?? '';
            $alertas = $orden->validar();
            if (!$alertas) {
                $r = $orden->guardar();
                if (is_array($r)) $orden->id = $r['id'];
                header('Location: /cambios/ver?proyecto=' . $proyecto->id . '&id=' . $orden->id);
                exit;
            }
        }
        $router->render('cambios/formulario', [
            'titulo' => $orden->id ? 'Editar orden de cambio' : 'Nueva orden de cambio', 'proyecto' => $proyecto, 'orden' => $orden, 'alertas' => $alertas,
            'actividades' => Dependencias::actividades((int)$proyecto->id),
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($o = self::ordenDe($proyecto)) $o->eliminar();
        header('Location: /cambios?proyecto=' . $proyecto->id);
        exit;
    }

    /** Cambia el estado de la orden (JSON) */
    public static function estadoOrden()
    {
        $proyecto = proyecto_de_peticion(true);
        $o = self::ordenDe($proyecto);
        $nuevo = $_POST['estado'] ?? '';
        if (!$o || !isset(OrdenCambio::ESTADOS[$nuevo])) json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        $o->estado = $nuevo;
        $o->guardar();
        json_respuesta(['success' => true, 'estado' => $nuevo]);
    }

    /** Crea o edita un renglón de la orden */
    public static function guardarPartida()
    {
        $proyecto = proyecto_de_peticion(true);
        $orden = self::ordenDe($proyecto, 'orden_id');
        if (!$orden) {
            header('Location: /cambios?proyecto=' . $proyecto->id);
            exit;
        }
        $c = !empty($_POST['id']) ? self::partidaDe($proyecto) : new CambioPartida();
        if (!$c || ($c->orden_id && (int)$c->orden_id !== (int)$orden->id)) {
            header('Location: /cambios/ver?proyecto=' . $proyecto->id . '&id=' . $orden->id);
            exit;
        }
        $tipo = $_POST['tipo'] ?? 'modificacion';
        $c->orden_id = $orden->id;
        $c->proyecto_id = $proyecto->id;
        $c->tipo = $tipo;
        $c->descripcion = mb_substr(trim($_POST['descripcion'] ?? ''), 0, 300);
        $c->ubicacion_original = mb_substr(trim($_POST['ubicacion_original'] ?? ''), 0, 200);
        $c->ubicacion_nueva = mb_substr(trim($_POST['ubicacion_nueva'] ?? ''), 0, 200);
        $c->cantidad = self::numero($_POST['cantidad'] ?? '');
        $c->unidad = mb_substr(trim($_POST['unidad'] ?? ''), 0, 30);
        $c->actividad_id = self::actividadValida($proyecto, $_POST['actividad_id'] ?? 0);
        $c->material_id = self::materialValido($proyecto, $_POST['material_id'] ?? 0);
        $c->requiere_retiro = $tipo === 'instalacion_temporal' || !empty($_POST['requiere_retiro']) ? 1 : 0;
        $c->fecha_prevista = self::fecha($_POST['fecha_prevista'] ?? '');
        $c->fecha_retiro_prevista = $c->requiere_retiro ? self::fecha($_POST['fecha_retiro_prevista'] ?? '') : null;
        $c->notas = mb_substr(trim($_POST['notas'] ?? ''), 0, 500);
        if (!in_array($c->estado, CambioPartida::estadosDe($c->tipo, (bool)$c->requiere_retiro), true)) $c->estado = 'pendiente';   // p. ej. «retirado» deja de valer si ya no requiere retiro
        $alertas = $c->validar();
        if (!$alertas) {
            $c->guardar();
        } else {
            $_SESSION['cambios_msg'] = ['ok' => false, 'texto' => implode(' ', $alertas['error'])];
        }
        header('Location: /cambios/ver?proyecto=' . $proyecto->id . '&id=' . $orden->id);
        exit;
    }

    public static function eliminarPartida()
    {
        $proyecto = proyecto_de_peticion(true);
        $c = self::partidaDe($proyecto);
        $oid = $c ? (int)$c->orden_id : 0;
        if ($c) $c->eliminar();
        header('Location: /cambios/ver?proyecto=' . $proyecto->id . '&id=' . $oid);
        exit;
    }

    /**
     * Cambia el estado de un renglón (JSON). Si el cliente manda `base` (la marca de la última vez que lo vio, p. ej. al subir una cola sin conexión)
     * y el renglón cambió desde entonces, responde 409 con el estado actual para que el usuario decida; `forzar=1` lo aplica de todos modos.
     */
    public static function estadoPartida()
    {
        $proyecto = proyecto_de_peticion(true);
        $c = self::partidaDe($proyecto);
        $nuevo = $_POST['estado'] ?? '';
        if (!$c || !isset(CambioPartida::ESTADOS[$nuevo])) json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        $base = trim((string)($_POST['base'] ?? ''));
        if ($base !== '' && empty($_POST['forzar']) && $c->actualizado !== $base && $c->estado !== $nuevo) {
            json_respuesta(['success' => false, 'conflicto' => true, 'actual' => ['estado' => $c->estado, 'actualizado' => $c->actualizado], 'error' => 'El renglón cambió mientras trabajabas sin conexión'], 409);
        }
        if ($c->estado !== $nuevo && !$c->cambiarEstado($nuevo)) json_respuesta(['success' => false, 'error' => 'Ese estado no corresponde a este renglón'], 422);
        $c = CambioPartida::find($c->id);
        json_respuesta(['success' => true, 'estado' => $c->estado, 'actualizado' => $c->actualizado, 'fecha_ejecucion' => $c->fecha_ejecucion, 'fecha_retiro_real' => $c->fecha_retiro_real]);
    }

    public static function cruce(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('cambios/cruce', ['titulo' => 'Materiales contra cronograma - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'cruce' => CruceMateriales::cruzar($proyecto)]);
    }
}
