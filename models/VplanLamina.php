<?php

namespace Model;

class VplanLamina extends ActiveRecord
{
    protected static $tabla = 'vplan_laminas';
    protected static $columnasDB = ['id', 'proyecto_id', 'paquete_id', 'partida_id', 'titulo', 'supervisor', 'ancho_mm', 'alto_mm', 'preset', 'recovery_cols', 'recovery_n', 'restr_auto', 'plano', 'simbologia', 'simbologia_grafico_id', 'marcas_json', 'marcas_png', 'marcas_estados_hash', 'guias_json', 'orden', 'visible', 'grupo'];

    public $id, $proyecto_id, $paquete_id, $partida_id, $titulo, $supervisor, $ancho_mm, $alto_mm, $preset, $recovery_cols, $recovery_n, $restr_auto, $plano, $simbologia, $simbologia_grafico_id, $marcas_json, $marcas_png, $marcas_estados_hash, $guias_json, $orden, $visible, $grupo;
    // Unión (solo lectura)
    public $partida_nombre;

    /** Máximo de columnas de Recovery en una lámina */
    public const MAX_RECOVERY = 6;

    public function __construct($args = [])
    {
        foreach (static::$columnasDB as $c) {
            $this->$c = $args[$c] ?? null;
        }
        $this->titulo = $args['titulo'] ?? '';
        $this->supervisor = $args['supervisor'] ?? '';
        $this->orden = (int)($args['orden'] ?? 0);
        $this->visible = (int)($args['visible'] ?? 1);
        $this->grupo = $args['grupo'] ?? '';
        $this->ancho_mm = (int)($args['ancho_mm'] ?? 432);
        $this->alto_mm = (int)($args['alto_mm'] ?? 279);
        $this->preset = $args['preset'] ?? 'tabloide';
        $this->recovery_cols = (int)($args['recovery_cols'] ?? 0);
        $this->recovery_n = max(0, min(self::MAX_RECOVERY, (int)($args['recovery_n'] ?? 0)));
        $this->restr_auto = (int)($args['restr_auto'] ?? 0);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->titulo)) self::$alertas['error'][] = 'El título de la lámina es obligatorio';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL(
            "SELECT l.*, p.nombre AS partida_nombre FROM vplan_laminas l
             LEFT JOIN partidas p ON p.id = l.partida_id
             LEFT JOIN vplan_paquetes q ON q.id = l.paquete_id
             WHERE l.proyecto_id = $proyectoId ORDER BY COALESCE(q.orden, 9999), q.id, l.orden, l.id"
        );
    }

    /** Láminas visibles del proyecto en el orden de los vPlan (lo que sale en el PDF de «todas») */
    public static function visiblesDeProyecto(int $proyectoId, ?int $paqueteId = null): array
    {
        return array_values(array_filter(self::deProyecto($proyectoId), fn($l) => (int)$l->visible === 1 && (!$paqueteId || (int)$l->paquete_id === $paqueteId)));
    }

    public static function siguienteOrden(int $paqueteId): int
    {
        return (int)self::$db->query("SELECT COALESCE(MAX(orden),0)+1 FROM vplan_laminas WHERE paquete_id = $paqueteId")->fetch_row()[0];
    }

    /** Intercambia el lugar con la lámina vecina del mismo vPlan (dir -1 sube, +1 baja) */
    public function mover(int $dir): void
    {
        $ids = array_map(fn($l) => (int)$l->id, VplanPaquete::find($this->paquete_id)?->laminas() ?? []);
        $i = array_search((int)$this->id, $ids, true);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) return;
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $orden => $id) self::$db->query("UPDATE vplan_laminas SET orden = " . ($orden + 1) . " WHERE id = $id");
    }

    /**
     * Copia el diseño de la lámina como una lámina nueva del mismo vPlan: tamaño, bloques, tablas (copias
     * independientes con sus renglones), simbología y supervisor. NO copia el plano ni las marcas: se sube el del área nueva.
     */
    public function duplicar(): self
    {
        $origen = $this->bloques();
        $copia = new self([
            'proyecto_id' => $this->proyecto_id, 'paquete_id' => $this->paquete_id, 'partida_id' => $this->partida_id,
            'titulo' => mb_substr((string)$this->titulo, 0, 180) . ' (copia)', 'supervisor' => $this->supervisor,
            'ancho_mm' => $this->ancho_mm, 'alto_mm' => $this->alto_mm, 'preset' => $this->preset, 'recovery_cols' => $this->recovery_cols, 'recovery_n' => $this->recovery_n, 'guias_json' => $this->guias_json,
            'restr_auto' => $this->restr_auto, 'simbologia_grafico_id' => $this->simbologia_grafico_id, 'grupo' => $this->grupo, 'visible' => 1,
            'orden' => self::siguienteOrden((int)$this->paquete_id),
        ]);
        if ($this->simbologia && \Classes\Almacenamiento::cabe((int)$this->proyecto_id, (int)@filesize(\Classes\Imagenes::CARPETA_VPLAN . '/' . basename($this->simbologia)))) {
            $copia->simbologia = \Classes\Imagenes::copiarVplan($this->simbologia);
        }
        $copia->id = $copia->guardar()['id'];
        $porId = [];
        foreach ($this->tablas() as $t) $porId[(int)$t->id] = $t;
        $nuevas = [];
        foreach ($origen as $b) {
            $nb = new VplanBloque(['lamina_id' => $copia->id, 'tipo' => $b->tipo, 'x' => $b->x, 'y' => $b->y, 'w' => $b->w, 'h' => $b->h, 'z' => $b->z]);
            $cfg = $b->config();
            if ($b->tipo === 'tabla') {
                $t = $porId[(int)($cfg['tabla_id'] ?? 0)] ?? null;
                if (!$t) continue;
                $nt = $t->duplicar();
                $nt->nombre_interno = mb_substr($t->etiqueta(), 0, 120) . ' · ' . mb_substr((string)$copia->titulo, 0, 60);
                $nt->guardar();
                self::$db->query("INSERT INTO vplan_lamina_tablas (lamina_id, tabla_id, orden) VALUES (" . (int)$copia->id . ", " . (int)$nt->id . ", " . (int)$b->z . ")");
                $cfg['tabla_id'] = (int)$nt->id;
            }
            $nb->setConfig($cfg);
            $nb->guardar();
        }
        return $copia;
    }

    /** Tablas adicionales de la lámina, en orden */
    public function tablas(): array
    {
        return VplanTabla::consultarSQL(
            "SELECT t.* FROM vplan_tablas t JOIN vplan_lamina_tablas lt ON lt.tabla_id = t.id
             WHERE lt.lamina_id = " . (int)$this->id . " ORDER BY lt.orden, t.id"
        );
    }

    public function agregarTabla(int $tablaId): void
    {
        $id = (int)$this->id;
        $orden = (int)self::$db->query("SELECT COALESCE(MAX(orden),0)+1 FROM vplan_lamina_tablas WHERE lamina_id = $id")->fetch_row()[0];
        self::$db->query("INSERT IGNORE INTO vplan_lamina_tablas (lamina_id, tabla_id, orden) VALUES ($id, " . (int)$tablaId . ", $orden)");
        $this->bloquesCache = null;
        $this->bloques();   // crea el bloque de la tabla nueva
    }

    public function quitarTabla(int $tablaId): void
    {
        self::$db->query("DELETE FROM vplan_lamina_tablas WHERE lamina_id = " . (int)$this->id . " AND tabla_id = " . (int)$tablaId);
        $this->bloquesCache = null;
        $this->bloques();   // retira su bloque
    }

    private ?array $bloquesCache = null;

    public function olvidarBloques(): void
    {
        $this->bloquesCache = null;
    }

    /**
     * Bloques de la lámina. Una lámina anterior a los bloques se convierte aquí, la primera vez que se pide, a un
     * diseño idéntico al que tenía. Además cada tabla adicional de la lámina tiene su bloque (se crea o se retira).
     * @return \Model\VplanBloque[]
     */
    /**
     * Renueva las imágenes vinculadas (bloques «imagen» con `ref`) cuyo origen cambió: copia propia nueva del archivo actual.
     * Un símbolo SVG no se puede copiar en el servidor (se queda como está hasta que se vuelva a insertar).
     */
    public function sincronizarImagenes(array $bloques): void
    {
        foreach ($bloques as $b) {
            if ($b->tipo !== 'imagen') continue;
            $c = $b->config();
            if (empty($c['ref'])) continue;
            $o = \Classes\ImagenVinculada::actual((string)$c['ref'], (int)$this->proyecto_id);
            if (!$o || $o['token'] === ($c['refv'] ?? '')) continue;
            if (!\Classes\Almacenamiento::cabe((int)$this->proyecto_id, (int)@filesize($o['ruta']))) continue;
            $nuevo = preg_match('/\.svg$/i', $o['ruta']) ? null : \Classes\Imagenes::guardarGrande($o['ruta'], 1600);
            if (!$nuevo) continue;
            $viejo = $c['archivo'] ?? '';
            $c['archivo'] = $nuevo;
            $c['refv'] = $o['token'];
            $b->setConfig($c);
            $b->guardar();
            if ($viejo !== '') {   // se borra solo si ningún otro bloque lo usa
                $enUso = self::$db->query("SELECT COUNT(*) FROM vplan_bloques WHERE config_json LIKE '%" . self::$db->escape_string($viejo) . "%'")->fetch_row()[0] ?? 0;
                if (!(int)$enUso) \Classes\Imagenes::eliminarVplan($viejo);
            }
        }
    }

    public function bloques(): array
    {
        if (!$this->id) return [];
        if ($this->bloquesCache !== null) return $this->bloquesCache;
        $id = (int)$this->id;
        $lock = "vplan_bloques_$id";
        self::$db->query("SELECT GET_LOCK('$lock', 10)")->free();   // dos peticiones a la vez no deben crear el diseño dos veces
        try {
            $bloques = VplanBloque::deLamina($id);
            if (!$bloques) {
                $seq = $this->seleccion() ? 'partida' : 'continuo';
                foreach (\Classes\VplanDiseno::porDefecto($this, \Classes\Vplan::fuentesAntiguas($this), $seq) as $b) {
                    $b->lamina_id = $id;
                    $b->id = $b->guardar()['id'];
                    $bloques[] = $b;
                }
            }
            $miembros = array_map(fn($t) => (int)$t->id, $this->tablas());
            $conBloque = [];
            foreach ($bloques as $k => $b) {
                if ($b->tipo !== 'tabla') continue;
                $tid = (int)($b->config()['tabla_id'] ?? 0);
                if (!in_array($tid, $miembros, true) || isset($conBloque[$tid])) {   // la tabla ya no está en la lámina (o está repetida)
                    $b->eliminar();
                    unset($bloques[$k]);
                } else {
                    $conBloque[$tid] = true;
                }
            }
            $bloques = array_values($bloques);
            foreach ($this->tablas() as $t) {
                if (isset($conBloque[(int)$t->id])) continue;
                $nuevo = \Classes\VplanDiseno::posicionNueva($this, $t, $bloques);
                $nuevo->id = $nuevo->guardar()['id'];
                $bloques[] = $nuevo;
            }
        } finally {
            self::$db->query("SELECT RELEASE_LOCK('$lock')")->free();
        }
        return $this->bloquesCache = $bloques;
    }

    /** Intercambia el orden con la tabla vecina (dir -1 sube, +1 baja) */
    public function moverTabla(int $tablaId, int $dir): void
    {
        $ids = array_map(fn($t) => (int)$t->id, $this->tablas());
        $i = array_search($tablaId, $ids, true);
        $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) return;
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $orden => $tid) {
            self::$db->query("UPDATE vplan_lamina_tablas SET orden = $orden WHERE lamina_id = " . (int)$this->id . " AND tabla_id = $tid");
        }
    }

    /** Ids de las actividades elegidas para esta lámina (vacío = toda la partida) */
    public function seleccion(): array
    {
        if (!$this->id) return [];
        $res = self::$db->query("SELECT actividad_id FROM vplan_lamina_actividades WHERE lamina_id = " . (int)$this->id);
        $out = [];
        while ($r = $res->fetch_row()) $out[] = (int)$r[0];
        return $out;
    }

    /** Reemplaza la selección. Los ids deben ser de la partida de la lámina; vacío = toda la partida */
    public function guardarSeleccion(array $ids): void
    {
        $id = (int)$this->id;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        self::$db->query("DELETE FROM vplan_lamina_actividades WHERE lamina_id = $id");
        foreach ($ids as $aid) {
            self::$db->query("INSERT IGNORE INTO vplan_lamina_actividades (lamina_id, actividad_id) VALUES ($id, $aid)");
        }
    }

    /** [actividad_id => [n => ['inicio' => fecha|null, 'fin' => fecha|null]]] */
    public function recovery(): array
    {
        $res = self::$db->query("SELECT actividad_id, n, inicio, fin FROM vplan_recovery_n WHERE lamina_id = " . (int)$this->id);
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(int)$r['actividad_id']][(int)$r['n']] = ['inicio' => $r['inicio'], 'fin' => $r['fin']];
        return $out;
    }

    /** $campo: 'inicio' | 'fin'; $n: 1..MAX_RECOVERY */
    public function guardarRecovery(int $actividadId, int $n, string $campo, ?string $valor): void
    {
        if (!in_array($campo, ['inicio', 'fin'], true) || $n < 1 || $n > self::MAX_RECOVERY) return;
        $id = (int)$this->id;
        $aid = (int)$actividadId;
        $v = $valor === null ? 'NULL' : "'" . self::$db->escape_string($valor) . "'";
        self::$db->query("INSERT INTO vplan_recovery_n (lamina_id, actividad_id, n, $campo) VALUES ($id, $aid, $n, $v) ON DUPLICATE KEY UPDATE $campo = $v");
    }

    /** [actividad_id => [col_id => texto]] de las columnas de texto de los bloques de tareas */
    public function textosColumnas(): array
    {
        $out = [];
        try {
            $res = self::$db->query("SELECT actividad_id, col_id, texto FROM vplan_col_texto WHERE lamina_id = " . (int)$this->id);
            while ($res && $r = $res->fetch_assoc()) $out[(int)$r['actividad_id']][$r['col_id']] = $r['texto'];
        } catch (\Throwable $e) { /* migración pendiente */ }
        return $out;
    }

    public function guardarTextoColumna(int $actividadId, string $colId, string $texto): void
    {
        $id = (int)$this->id;
        $aid = (int)$actividadId;
        $c = self::$db->escape_string(substr($colId, 0, 12));
        $t = self::$db->escape_string(mb_substr($texto, 0, 500));
        self::$db->query("INSERT INTO vplan_col_texto (lamina_id, actividad_id, col_id, texto) VALUES ($id, $aid, '$c', '$t') ON DUPLICATE KEY UPDATE texto = '$t'");
    }

    /** Guías del modo diseño: [{id, eje: 'v'|'h', pos: % de la hoja, visible, bloqueada, nombre}] */
    public function guias(): array
    {
        $g = json_decode((string)$this->guias_json, true);
        return is_array($g) ? array_values($g) : [];
    }

    /** Imagen de la simbología del schedule elegida (siempre la versión actual del gráfico); null = tabla estándar */
    public function imagenSimbologiaSchedule(): ?string
    {
        if (!$this->simbologia_grafico_id) return null;
        $g = Grafico::find((int)$this->simbologia_grafico_id);
        return $g && $g->tipo === 'simbologia' && (int)$g->proyecto_id === (int)$this->proyecto_id ? $g->imagenSimbologia() : null;
    }
}
