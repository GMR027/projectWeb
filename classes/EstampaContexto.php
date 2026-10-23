<?php

namespace Classes;

use Model\Proyecto;
use Model\VplanLamina;

/**
 * Datos del proyecto que alimentan las estampas y los cajetines. Todo se calcula bajo demanda y se guarda en caché
 * durante la petición. Opciones ($opts): corte (Y-m-d), actividad (id por defecto), autor, escala, lamina_n, lamina_total.
 */
class EstampaContexto
{
    public Proyecto $proyecto;
    public ?VplanLamina $lamina;
    public array $opts;
    private array $cache = [];

    public function __construct(Proyecto $proyecto, ?VplanLamina $lamina = null, array $opts = [])
    {
        $this->proyecto = $proyecto;
        $this->lamina = $lamina;
        $this->opts = $opts;
    }

    private function db(): \mysqli
    {
        return \Model\MsrItem::getDB();
    }

    private function filas(string $sql): array
    {
        try {
            $r = $this->db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];   // tabla que aún no existe en esta BD
        }
    }

    private function memo(string $k, callable $f)
    {
        return array_key_exists($k, $this->cache) ? $this->cache[$k] : ($this->cache[$k] = $f());
    }

    /** Fecha de corte: la elegida o la de hoy */
    public function corte(): string
    {
        $c = (string)($this->opts['corte'] ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $c) ? $c : date('Y-m-d');
    }

    public function calendario(): Calendario
    {
        return $this->memo('cal', fn() => new Calendario((int)$this->proyecto->id));
    }

    /** Todas las actividades del proyecto (con su partida), en el orden del programa: id => fila */
    public function actividades(): array
    {
        return $this->memo('acts', function () {
            $pid = (int)$this->proyecto->id;
            $out = [];
            foreach ($this->filas("SELECT a.*, p.nombre partida_nombre, p.codigo partida_codigo, p.color_bg partida_color, p.orden partida_orden
                FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid ORDER BY p.orden, p.id, a.orden, a.id") as $f) {
                $out[(int)$f['id']] = $f;
            }
            return $out;
        });
    }

    /** Responsables por actividad: id => [nombres] */
    public function responsables(): array
    {
        return $this->memo('resp', function () {
            $pid = (int)$this->proyecto->id;
            $out = [];
            foreach ($this->filas("SELECT ar.actividad_id, ar.personal_id, pe.nombre FROM actividad_responsables ar
                JOIN personal pe ON pe.id = ar.personal_id WHERE pe.proyecto_id = $pid ORDER BY pe.nombre") as $f) {
                $out[(int)$f['actividad_id']][(int)$f['personal_id']] = $f['nombre'];
            }
            return $out;
        });
    }

    /** Ruta crítica y holgura por actividad: id => ['critica' => bool, 'holgura' => ?int] */
    public function critica(): array
    {
        return $this->memo('crit', function () {
            try {
                $out = [];
                foreach (Dependencias::datos($this->proyecto)['actividades'] ?? [] as $a) {
                    $out[(int)$a['id']] = ['critica' => !empty($a['critica']), 'holgura' => $a['holgura']];
                }
                return $out;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /** Restricciones activas por actividad: id => [descripciones] */
    public function restricciones(): array
    {
        return $this->memo('restr', function () {
            $pid = (int)$this->proyecto->id;
            $out = [];
            foreach ($this->filas("SELECT r.actividad_id, r.codigo, r.descripcion, r.fecha FROM restricciones r JOIN actividades a ON a.id = r.actividad_id
                JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND r.estado = 'activa' ORDER BY r.id") as $f) {
                $out[(int)$f['actividad_id']][] = ['texto' => trim((string)$f['descripcion']) ?: (trim((string)$f['codigo']) ?: 'Restricción'), 'fecha' => $f['fecha']];
            }
            return $out;
        });
    }

    /** Días hábiles después de $desde hasta $hasta (0 si ya pasó) */
    public function diasHabiles(string $desde, string $hasta): int
    {
        if ($hasta <= $desde) return 0;
        $cal = $this->calendario();
        $n = 0;
        $d = new \DateTime($desde);
        for ($i = 0; $i < 800; $i++) {
            $d->modify('+1 day');
            $k = $d->format('Y-m-d');
            if ($k > $hasta) break;
            if ($cal->esHabil($k)) $n++;
        }
        return $n;
    }

    /** Primer día hábil igual o posterior a $f (tope de 14 días) */
    public function diaHabil(string $f): string
    {
        $cal = $this->calendario();
        $d = new \DateTime($f);
        for ($i = 0; $i < 14 && !$cal->esHabil($d->format('Y-m-d')); $i++) $d->modify('+1 day');
        return $d->format('Y-m-d');
    }

    /** Avance real/programado/desvío/SPI del resumen de la Curva S (null si aún no hay datos) */
    private function curva(): array
    {
        return $this->memo('curva', function () {
            try {
                $c = CurvaS::calcular($this->proyecto);
                if (!empty($c['resumen'])) return $c['resumen'];
            } catch (\Throwable $e) {
            }
            return [];
        });
    }

    private function numero(string $sql): int
    {
        $f = $this->filas($sql);
        return $f ? (int)array_values($f[0])[0] : 0;
    }

    private function lamina(string $que): ?int
    {
        if (isset($this->opts['lamina_' . $que])) return (int)$this->opts['lamina_' . $que];
        if (!$this->lamina) return null;
        return $this->memo('lam', function () {
            $vis = VplanLamina::visiblesDeProyecto((int)$this->proyecto->id, $this->lamina->paquete_id ? (int)$this->lamina->paquete_id : null);
            $pos = 1;
            foreach ($vis as $i => $v) if ((int)$v->id === (int)$this->lamina->id) { $pos = $i + 1; break; }
            return ['n' => $pos, 'total' => max(1, count($vis))];
        })[$que];
    }

    /** Fila de una actividad con todo lo que las estampas muestran de ella ('' si no existe) */
    public function actividad(int $id): ?array
    {
        $a = $this->actividades()[$id] ?? null;
        if (!$a) return null;
        $corte = $this->corte();
        $cr = $this->critica()[$id] ?? null;
        $rs = $this->restricciones()[$id] ?? [];
        return [
            'id' => $id, 'codigo' => (string)$a['codigo'], 'nombre' => (string)$a['nombre'], 'partida' => (string)$a['partida_nombre'], 'partida_codigo' => (string)$a['partida_codigo'],
            'color' => (string)$a['partida_color'], 'inicio' => (string)$a['fecha_inicio'], 'fin' => (string)$a['fecha_fin'], 'avance' => (int)$a['progreso'],
            'status' => (string)$a['status'], 'prioridad' => (string)$a['prioridad'], 'duracion' => (int)$a['duracion_dias'],
            'responsable' => implode(', ', $this->responsables()[$id] ?? []),
            'critica' => $cr === null ? null : $cr['critica'], 'holgura' => $cr['holgura'] ?? null,
            'restriccion' => $rs ? $rs[0]['texto'] . (count($rs) > 1 ? ' (+' . (count($rs) - 1) . ')' : '') : '',
            'restricciones' => count($rs),
            'dias' => $this->diasHabiles($corte, (string)$a['fecha_fin']),
            'hecha' => $a['status'] === 'completada' || (int)$a['progreso'] >= 100,
        ];
    }

    /**
     * Actividades que caen en la fecha de la etiqueta (la de corte o una fija). $e: partidas[], responsable, solo_pendientes, max.
     * @return array{fecha: string, filas: array, hay_mas: int, habil: bool}
     */
    /** Configuración de la etiqueta «Actividades del día» de la estampa que se está dibujando (para que {fecha_dia} use su misma fecha) */
    public array $cfgDia = [];

    public function actividadesDelDia(array $e): array
    {
        $fecha = (($e['fecha_modo'] ?? 'corte') === 'fija' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['fecha'] ?? ''))) ? $e['fecha'] : $this->corte();
        $habil = $this->calendario()->esHabil($fecha);
        if (!$habil) $fecha = $this->diaHabil($fecha);   // fin de semana o festivo: se muestra el siguiente día hábil
        $partidas = array_map('intval', (array)($e['partidas'] ?? []));
        if (isset($this->opts['partida'])) $partidas = (int)$this->opts['partida'] > 0 ? [(int)$this->opts['partida']] : [];   // Last Planner: la partida de la tarjeta
        $resp = (int)($e['responsable'] ?? 0);
        $filas = [];
        foreach ($this->actividades() as $id => $a) {
            if ($a['fecha_inicio'] > $fecha || $a['fecha_fin'] < $fecha) continue;
            if ($partidas && !in_array((int)$a['partida_id'], $partidas, true)) continue;
            if ($resp && !isset($this->responsables()[$id][$resp])) continue;
            $info = $this->actividad($id);
            if (!empty($e['solo_pendientes']) && $info['hecha']) continue;
            $info['dias'] = $this->diasHabiles($fecha, (string)$a['fecha_fin']);
            $filas[] = $info;
        }
        $max = max(1, min(24, (int)($this->opts['max'] ?? $e['max'] ?? 8)));
        $desde = max(0, (int)($this->opts['offset'] ?? 0));
        return ['fecha' => $fecha, 'todas' => $filas, 'filas' => array_slice($filas, $desde, $max), 'hay_mas' => max(0, count($filas) - $desde - $max), 'habil' => $habil];
    }

    /** Nombre de una partida del proyecto ('' si no existe) */
    public function partidaNombre(int $id): string
    {
        $f = $this->filas("SELECT nombre FROM partidas WHERE id = $id AND proyecto_id = " . (int)$this->proyecto->id);
        return (string)($f[0]['nombre'] ?? '');
    }

    /** Logo como URL (pantalla) o data URI (PDF): 'compania' = el de la empresa del dueño, 'proyecto' = el del proyecto, 'subida' = imagen propia de la estampa ($archivo) */
    public function logo(string $fuente, bool $pdf, string $archivo = ''): ?string
    {
        $carpeta = 'logos';
        if ($fuente === 'subida') {
            $arch = preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $archivo) ? $archivo : null;
            $carpeta = 'estampas';
        } else {
            $arch = match ($fuente) {
                'proyecto' => $this->proyecto->logo ?: null,
                'contratista' => $this->proyecto->contratista_logo ?: null,
                default => $this->proyecto->logoEmpresa() ?: $this->proyecto->logo,
            };
        }
        if (!$arch) return null;
        if ($pdf) return \Classes\Exportadores\MsrPdfExporter::imagenBase64($arch, 'build/img/' . $carpeta);
        return is_file(__DIR__ . '/../public/build/img/' . $carpeta . '/' . basename($arch)) ? '/build/img/' . $carpeta . '/' . rawurlencode(basename($arch)) : null;
    }

    /** Valor sin formato de un campo del catálogo (null si no aplica). $actId: actividad para los campos «act_*» */
    public function campo(string $k, int $actId = 0)
    {
        $p = $this->proyecto;
        $corte = $this->corte();
        if (str_starts_with($k, 'act_')) {
            $a = $this->actividad($actId ?: (int)($this->opts['actividad'] ?? 0));
            if (!$a) return null;
            return match ($k) {
                'act_codigo' => $a['codigo'], 'act_nombre' => $a['nombre'], 'act_partida' => $a['partida'], 'act_inicio' => $a['inicio'], 'act_fin' => $a['fin'],
                'act_avance' => $a['avance'], 'act_status' => Estampas::STATUS[$a['status']] ?? $a['status'], 'act_responsable' => $a['responsable'],
                'act_prioridad' => ucfirst($a['prioridad']), 'act_critica' => $a['critica'] === null ? null : ($a['critica'] ? 'Sí' : 'No'),
                'act_holgura' => $a['holgura'], 'act_dias' => $a['dias'], 'act_restriccion' => $a['restriccion'],
                default => null,
            };
        }
        return match ($k) {
            'proyecto' => (string)$p->nombre, 'codigo' => (string)$p->codigo, 'cliente' => (string)$p->empresa, 'contratista' => (string)$p->contratista,
            'ubicacion' => (string)$p->ubicacion, 'trabajo' => (string)$p->numero_trabajo,
            'titulo' => $this->lamina ? (string)$this->lamina->titulo : (string)($this->opts['titulo'] ?? ''),
            'lamina' => $this->lamina('n'), 'total' => $this->lamina('total'), 'escala' => (string)($this->opts['escala'] ?? ''), 'revision' => (string)($this->opts['revision'] ?? ''),
            'fecha' => date('Y-m-d'), 'corte' => $corte, 'autor' => (string)($this->opts['autor'] ?? ($_SESSION['nombre'] ?? '')),
            'inicio' => (string)$p->fecha_inicio, 'fin' => (string)$p->fecha_fin,
            'duracion' => max(0, (int)floor((strtotime($p->fecha_fin) - strtotime($p->fecha_inicio)) / 86400) + 1),
            'dias_transcurridos' => max(0, (int)floor((strtotime($corte) - strtotime($p->fecha_inicio)) / 86400)),
            'dias_restantes' => max(0, (int)floor((strtotime($p->fecha_fin) - strtotime($corte)) / 86400)),
            'semana' => max(1, (int)floor((strtotime($corte) - strtotime($p->fecha_inicio)) / 604800) + 1),
            'avance_real' => $this->curva()['real'] ?? null, 'avance_prog' => $this->curva()['plan'] ?? null,
            'desvio' => $this->curva()['desvio'] ?? null, 'spi' => $this->curva()['spi'] ?? null,
            'actividades_total' => count($this->actividades()),
            'actividades_hechas' => count(array_filter($this->actividades(), fn($a) => $a['status'] === 'completada' || (int)$a['progreso'] >= 100)),
            'actividades_atrasadas' => count(array_filter($this->actividades(), fn($a) => $a['fecha_fin'] < $corte && $a['status'] !== 'completada' && (int)$a['progreso'] < 100)),
            'restr_activas' => array_sum(array_map('count', $this->restricciones())),
            'restr_vencidas' => count(array_filter(array_merge(...array_values($this->restricciones()) ?: [[]]), fn($r) => $r['fecha'] && $r['fecha'] < $corte)),
            'oc_abiertas' => $this->numero("SELECT COUNT(*) FROM ordenes_cambio WHERE proyecto_id = " . (int)$p->id . " AND estado IN ('borrador','solicitada','aprobada','ejecutada')"),
            'retiros_pendientes' => count($this->retiros()), 'retiros_vencidos' => count(array_filter($this->retiros(), fn($r) => $r['vencido'])),
            'personal_ultimo' => $this->ultimoReporte()['personal'] ?? null, 'reporte_ultimo' => $this->ultimoReporte()['fecha'] ?? null,
            'fecha_dia' => $this->actividadesDelDia($this->cfgDia)['fecha'],
            default => null,
        };
    }

    private function retiros(): array
    {
        return $this->memo('ret', function () {
            try {
                return \Classes\Cambios::retirosPendientes((int)$this->proyecto->id);
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    private function ultimoReporte(): array
    {
        return $this->memo('rep', function () {
            $f = $this->filas("SELECT fecha, personal_json FROM reportes_diarios WHERE proyecto_id = " . (int)$this->proyecto->id . " ORDER BY fecha DESC, id DESC LIMIT 1");
            if (!$f) return [];
            $pers = json_decode((string)$f[0]['personal_json'], true);
            return ['fecha' => $f[0]['fecha'], 'personal' => is_array($pers) ? count($pers) : 0];
        });
    }
}
