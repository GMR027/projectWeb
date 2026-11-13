<?php

namespace Classes;

use Model\Actividad;
use Model\ActividadMaterial;
use Model\Partida;
use Model\Proyecto;
use Model\Subactividad;
use Model\SubactividadHoja;

/**
 * Reglas de negocio de las subactividades, en un solo sitio para que el programa,
 * la línea de tiempo, las dependencias, SheetFlow/vPlan y la importación no
 * recalculen cada uno a su manera:
 *
 *  - el avance de la actividad es el promedio simple de sus subactividades;
 *  - las fechas de la actividad son el envolvente (mínimo inicio / máximo fin);
 *  - al mover el inicio de la actividad se desplazan todas sus subactividades;
 *  - después de un cambio se refrescan partida, materiales, vPlan y dependencias.
 */
class Subactividades
{
    /**
     * Recalcula la actividad a partir de sus subactividades.
     *
     * Opciones:
     *  - envolvente (bool, true): ajustar fechas de la actividad al mínimo/máximo.
     *  - propagar (bool, true): propagar dependencias en modo automático.
     *  - hoja (bool, true): reflejar el cambio en la tabla vinculada de SheetFlow.
     *
     * @return array{progreso:?int, fechas_cambiadas:bool, movidas:int, errores:string[]}
     */
    public static function recalcular(Actividad $actividad, array $opciones = []): array
    {
        $envolvente = $opciones['envolvente'] ?? true;
        $propagar = $opciones['propagar'] ?? true;
        $hoja = $opciones['hoja'] ?? true;

        $subs = Subactividad::deActividad((int)$actividad->id);
        if (!$subs) {
            return ['progreso' => null, 'fechas_cambiadas' => false, 'movidas' => 0, 'errores' => []];
        }

        $progreso = Subactividad::promedio(array_map(fn($s) => (int)$s->progreso, $subs));
        $actividad->progreso = $progreso;
        $actividad->sincronizarStatus();

        $cambioFechas = false;
        if ($envolvente && !$actividad->esHito()) {
            $inicios = array_filter(array_map(fn($s) => (string)($s->fecha_inicio ?? ''), $subs));
            $fines = array_filter(array_map(fn($s) => (string)($s->fecha_fin ?? ''), $subs));
            if ($inicios && $fines) {
                $inicio = min($inicios);
                $fin = max($fines);
                if ((string)$actividad->fecha_inicio !== $inicio) {
                    $actividad->fecha_inicio = $inicio;
                    $cambioFechas = true;
                }
                if ((string)$actividad->fecha_fin !== $fin) {
                    $actividad->fecha_fin = $fin;
                    $cambioFechas = true;
                }
                if ($cambioFechas) {
                    $cal = new Calendario($actividad->proyectoId(), (int)($actividad->calendario_id ?? 0));
                    $actividad->duracion_dias = max(1, (int)ceil($cal->calcularDuracion($inicio, $fin)));
                }
            }
        }

        $guardado = $actividad->guardar();
        if (!$guardado || (is_array($guardado) && empty($guardado['resultado']))) {
            return ['progreso' => $progreso, 'fechas_cambiadas' => $cambioFechas, 'movidas' => 0,
                'errores' => ['No se pudo guardar la actividad con su nuevo avance.']];
        }

        return self::efectos($actividad, $subs, $cambioFechas, $propagar, $hoja) + [
            'progreso' => $progreso,
            'fechas_cambiadas' => $cambioFechas,
        ];
    }

    /**
     * Desplaza el inicio de la actividad y todas sus subactividades el mismo número
     * de días naturales (conserva duraciones y huecos). El fin se deriva del
     * envolvente, así que no se toca aquí.
     *
     * @return array{progreso:?int, fechas_cambiadas:bool, movidas:int, errores:string[]}
     */
    public static function moverInicio(Actividad $actividad, string $nuevoInicio, array $opciones = []): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevoInicio)) {
            return ['progreso' => null, 'fechas_cambiadas' => false, 'movidas' => 0, 'errores' => ['Fecha de inicio inválida']];
        }
        $subs = Subactividad::deActividad((int)$actividad->id);
        if (!$subs) {
            $actividad->fecha_inicio = $nuevoInicio;
            $actividad->guardar();
            return ['progreso' => null, 'fechas_cambiadas' => true, 'movidas' => 0, 'errores' => []];
        }

        $cal = new Calendario($actividad->proyectoId(), (int)($actividad->calendario_id ?? 0));
        $nuevoInicio = self::habilMasCercano($cal, $nuevoInicio);
        // La referencia es la subactividad más temprana: así el nuevo envolvente empieza exactamente en la fecha pedida.
        $viejo = (string)min(array_map(fn($s) => (string)$s->fecha_inicio, $subs));
        $delta = self::diasEntre($viejo, $nuevoInicio);
        $movidas = 0;
        $errores = [];

        foreach ($subs as $sub) {
            $sub->fecha_inicio = self::moverFecha((string)$sub->fecha_inicio, $delta);
            $sub->fecha_fin = self::moverFecha((string)$sub->fecha_fin, $delta);
            if ($sub->esHito()) $sub->fecha_fin = $sub->fecha_inicio;
            if (!$sub->validar()) {
                $r = $sub->guardar();
                if (!$r || (is_array($r) && empty($r['resultado']))) {
                    $errores[] = 'No se pudo mover la subactividad «' . $sub->nombre . '».';
                } else {
                    $movidas++;
                }
            } else {
                $errores[] = 'La subactividad «' . $sub->nombre . '» quedaría con fechas inválidas.';
            }
        }

        // El envolvente deja la actividad en el nuevo inicio y su fin correspondiente.
        $res = self::recalcular($actividad, $opciones);
        $res['movidas'] = $movidas;
        $res['errores'] = array_merge($errores, $res['errores'] ?? []);
        return $res;
    }

    /**
     * Guarda una subactividad y recalcula su actividad.
     *
     * @return array{ok:bool, id:?int, errores:string[]}
     */
    public static function guardar(Subactividad $sub, ?Actividad $actividad = null, array $opciones = []): array
    {
        $alertas = $sub->validar();
        if ($alertas) {
            return ['ok' => false, 'id' => $sub->id ? (int)$sub->id : null, 'errores' => $alertas['error'] ?? []];
        }
        if ($sub->orden < 1 && $sub->actividad_id) {
            $sub->orden = count(Subactividad::deActividad((int)$sub->actividad_id)) + 1;
        }
        $r = $sub->guardar();
        if (!$r || (is_array($r) && empty($r['resultado']))) {
            return ['ok' => false, 'id' => $sub->id ? (int)$sub->id : null, 'errores' => ['No se pudo guardar la subactividad.']];
        }
        $id = is_array($r) ? ($r['id'] ?? $sub->id) : $sub->id;
        $sub->id = $id;

        $actividad ??= $sub->getActividad();
        if ($actividad) self::recalcular($actividad, $opciones);
        return ['ok' => true, 'id' => $id ? (int)$id : null, 'errores' => []];
    }

    /** Borra una subactividad y recalcula su actividad */
    public static function eliminar(Subactividad $sub, array $opciones = []): bool
    {
        $actividad = $sub->getActividad();
        $sub->eliminar();
        if ($actividad) self::recalcular($actividad, $opciones);
        return true;
    }

    /** Envolvente actual de las subactividades: ['inicio'=>?string,'fin'=>?string] */
    public static function envolvente(array $subs): array
    {
        $inicios = array_filter(array_map(fn($s) => (string)($s->fecha_inicio ?? ''), $subs));
        $fines = array_filter(array_map(fn($s) => (string)($s->fecha_fin ?? ''), $subs));
        return [
            'inicio' => $inicios ? min($inicios) : null,
            'fin' => $fines ? max($fines) : null,
        ];
    }

    /** Refresca partida, materiales, vPlan y dependencias tras un cambio de la actividad */
    private static function efectos(Actividad $actividad, array $subs, bool $cambioFechas, bool $propagar, bool $hoja): array
    {
        $errores = [];
        $movidas = 0;
        if ($cambioFechas) {
            if ($partida = Partida::find((int)$actividad->partida_id)) $partida->recalcularFechas();
            try {
                ActividadMaterial::sincronizarInstalacion($actividad->proyectoId());
            } catch (\Throwable $e) {
                error_log('Subactividades · materiales: ' . $e->getMessage());
            }
            if ($propagar) {
                try {
                    $proyecto = Proyecto::find($actividad->proyectoId());
                    if ($proyecto) $movidas = Dependencias::propagar($proyecto);
                } catch (\Throwable $e) {
                    error_log('Subactividades · dependencias: ' . $e->getMessage());
                }
            }
        }
        if ($hoja) {
            try {
                $error = SubactividadHoja::reflejar($actividad);
                if ($error !== null) $errores[] = $error;
            } catch (\Throwable $e) {
                error_log('Subactividades · SheetFlow: ' . $e->getMessage());
                $errores[] = 'No se pudo actualizar la tabla vinculada de SheetFlow.';
            }
        }
        return ['movidas' => $movidas, 'errores' => $errores];
    }

    private static function diasEntre(string $desde, string $hasta): int
    {
        $a = strtotime($desde);
        $b = strtotime($hasta);
        if ($a === false || $b === false) return 0;
        return (int)round(($b - $a) / 86400);
    }

    private static function moverFecha(string $fecha, int $dias): string
    {
        if ($fecha === '') return $fecha;
        $t = strtotime(($dias >= 0 ? '+' : '') . $dias . ' day', strtotime($fecha));
        return $t === false ? $fecha : date('Y-m-d', $t);
    }

    private static function habilMasCercano(Calendario $cal, string $fecha): string
    {
        if ($cal->esHabil($fecha)) return $fecha;
        $adelante = new \DateTime($fecha);
        $atras = new \DateTime($fecha);
        for ($i = 0; $i < 400; $i++) {
            $adelante->modify('+1 day');
            if ($cal->esHabil($adelante->format('Y-m-d'))) return $adelante->format('Y-m-d');
            $atras->modify('-1 day');
            if ($cal->esHabil($atras->format('Y-m-d'))) return $atras->format('Y-m-d');
        }
        return $fecha;
    }
}
