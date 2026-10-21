<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;

/**
 * Plantillas por tipo de obra. Una vez elegida la del proyecto aportan: (1) contexto del oficio para los prompts de la IA (los modelos pequeños adivinan menos),
 * (2) una regla de secuencia que avisa cuando una etapa arranca antes que la anterior (canalización → cableado → montaje → …) y propone la dependencia,
 * (3) submittals y materiales críticos típicos, la lista de inspección del punch list y los permisos de seguridad habituales, y (4) una base de partidas y actividades
 * para empezar un proyecto nuevo.
 */
class Plantillas
{
    use AccesoDb;

    public const CATALOGO = [
        'videovigilancia' => [
            'nombre' => 'Videovigilancia (CCTV)', 'punch' => 'cctv', 'permisos' => ['altura', 'electrico'],
            'descripcion' => 'Cámaras, canalización, cableado, gabinetes, servidores y software de gestión de video.',
            'contexto' => 'Proyecto de videovigilancia (CCTV). Secuencia típica: ingeniería y submittals → compra de equipo → canalización y soportes → tendido de cableado (UTP/fibra) → montaje de cámaras y gabinetes → alimentación (PoE/fuentes) → configuración (enfoque, red, grabación, licencias) → pruebas, capacitación y entrega. Lo que más retrasa: entrega de cámaras, servidores y licencias (6 a 10 semanas) y la aprobación de submittals. Errores comunes: montar cámaras antes de terminar la canalización, no considerar altura/andamios, olvidar el etiquetado y la prueba de grabación.',
            'etapas' => [['Canalización', ['canalizacion', 'tuberia', 'conduit', 'charola', 'ducto', 'registro', 'soporteria']], ['Cableado', ['cableado', 'tendido', 'jalado', 'cable utp', 'fibra', 'cable de']], ['Montaje', ['montaje', 'instalacion de camara', 'instalacion de gabinete', 'fijacion', 'poste', 'base', 'instalacion de lector']], ['Alimentación', ['energiz', 'alimentacion', 'fuente', 'ups', 'poe']], ['Configuración', ['configuracion', 'enfoque', 'alta de', 'licencia', 'programacion', 'servidor', 'puesta en operacion', 'parametriz']], ['Pruebas y entrega', ['prueba', 'capacitacion', 'entrega', 'puesta en marcha', 'aceptacion', 'protocolo']]],
            'submittals' => ['Fichas técnicas de cámaras y soportes', 'Servidor/NVR y cálculo de almacenamiento', 'Licencias del software de gestión de video', 'Planos de ubicación y cobertura', 'Memoria de ancho de banda', 'Fichas de cable, canalización y gabinetes'],
            'criticos' => [['camara', 60], ['servidor', 60], ['licencia', 30], ['switch', 45]],
            'partidas' => [['1.0', 'Ingeniería y submittals', [['Levantamiento de sitio', 3], ['Ingeniería de detalle y planos', 8], ['Elaboración y envío de submittals', 5], ['Compra de equipos', 5]]], ['2.0', 'Canalizaciones y soportes', [['Trazo y marcado', 2], ['Instalación de canalización y soportes', 12], ['Registros y charolas', 5]]], ['3.0', 'Cableado', [['Tendido de cable UTP/fibra', 10], ['Etiquetado y terminaciones', 4]]],
                ['4.0', 'Instalación de equipos', [['Montaje de gabinetes y servidores', 4], ['Montaje de cámaras', 8], ['Alimentación y energizado', 3]]], ['5.0', 'Configuración y entrega', [['Configuración de red y grabación', 5], ['Enfoque y alta de licencias', 3], ['Pruebas de funcionamiento', 3], ['Capacitación y entrega', 2]]]],
        ],
        'control_acceso' => [
            'nombre' => 'Control de acceso', 'punch' => 'acceso', 'permisos' => ['altura', 'electrico'],
            'descripcion' => 'Lectores, controladores, cerraduras/electroimanes, contactos, botones y software de acceso.',
            'contexto' => 'Proyecto de control de acceso. Secuencia típica: ingeniería y submittals → compra → canalización → cableado a puertas → montaje de controladores, lectores y cerraduras → alimentación y respaldo → configuración y alta de credenciales → pruebas por puerta y entrega. Lo que más retrasa: entrega de controladores/lectores, coordinación con herrería y carpintería de las puertas, y energía/UPS. Errores comunes: instalar cerraduras antes de que la puerta esté lista, no probar apertura de emergencia, cableado sin etiquetar.',
            'etapas' => [['Canalización', ['canalizacion', 'tuberia', 'conduit', 'ducto', 'registro']], ['Cableado', ['cableado', 'tendido', 'jalado', 'cable']], ['Montaje', ['montaje', 'instalacion de lector', 'instalacion de controlador', 'cerradura', 'electroiman', 'contacto magnetico', 'boton']], ['Alimentación', ['energiz', 'alimentacion', 'fuente', 'ups', 'respaldo']], ['Configuración', ['configuracion', 'alta de', 'credencial', 'programacion', 'software', 'puesta en operacion']], ['Pruebas y entrega', ['prueba', 'capacitacion', 'entrega', 'aceptacion', 'protocolo']]],
            'submittals' => ['Fichas de lectores, controladores y cerraduras', 'Diagrama de puertas y de conexión', 'Licencias del software de acceso', 'Memoria de alimentación y respaldo', 'Planos de ubicación'], 'criticos' => [['controlador', 60], ['lector', 45], ['cerradura', 45], ['licencia', 30]],
            'partidas' => [['1.0', 'Ingeniería y submittals', [['Levantamiento y diagrama de puertas', 4], ['Submittals', 5], ['Compra de equipos', 5]]], ['2.0', 'Canalización y cableado', [['Canalización a puertas', 8], ['Tendido de cableado', 6], ['Etiquetado', 2]]], ['3.0', 'Instalación', [['Montaje de controladores', 3], ['Montaje de lectores y cerraduras', 8], ['Alimentación y respaldo', 3]]], ['4.0', 'Configuración y entrega', [['Configuración y alta de credenciales', 4], ['Pruebas por puerta', 3], ['Capacitación y entrega', 2]]]],
        ],
        'cableado' => [
            'nombre' => 'Cableado estructurado (voz y datos)', 'punch' => 'cableado', 'permisos' => ['altura'],
            'descripcion' => 'Salidas, cableado horizontal y vertical, racks, patch panels, certificación.',
            'contexto' => 'Proyecto de cableado estructurado. Secuencia típica: ingeniería y submittals → canalización y charolas → tendido de cable horizontal y troncal → montaje de racks y patch panels → terminaciones y etiquetado → certificación del enlace → entrega con memoria. Lo que más retrasa: disponibilidad de ductos/plafón terminado, material (cable, jacks) y la certificación. Errores comunes: tender sin etiquetar, exceder el radio de curvatura, no sellar cortafuegos, no entregar reporte de certificación.',
            'etapas' => [['Canalización', ['canalizacion', 'charola', 'tuberia', 'ducto']], ['Cableado', ['cableado', 'tendido', 'jalado', 'cable utp', 'fibra']], ['Racks y patch', ['rack', 'patch', 'gabinete', 'montaje']], ['Terminaciones', ['terminacion', 'etiquetado', 'jack', 'faceplate', 'conectorizacion']], ['Certificación', ['certificacion', 'prueba', 'fluke', 'reporte']], ['Entrega', ['entrega', 'capacitacion', 'memoria', 'planos as-built']]],
            'submittals' => ['Fichas de cable, jacks, patch panels y racks', 'Plano de salidas y rutas', 'Procedimiento de certificación', 'Garantía del fabricante'], 'criticos' => [['cable', 30], ['rack', 45], ['fibra', 45]],
            'partidas' => [['1.0', 'Ingeniería', [['Levantamiento y planos', 4], ['Submittals', 5], ['Compra de material', 4]]], ['2.0', 'Canalización', [['Charolas y ductos', 10], ['Sellado de pasos', 3]]], ['3.0', 'Cableado', [['Tendido horizontal', 10], ['Tendido troncal/fibra', 5]]], ['4.0', 'Racks y terminaciones', [['Montaje de racks y patch panels', 4], ['Terminaciones y etiquetado', 6]]], ['5.0', 'Certificación y entrega', [['Certificación de enlaces', 4], ['Memoria y entrega', 2]]]],
        ],
        'edificacion' => [
            'nombre' => 'Edificación (obra civil)', 'punch' => 'acabados', 'permisos' => ['altura', 'excavacion', 'caliente', 'izaje'],
            'descripcion' => 'Cimentación, estructura, albañilería, instalaciones y acabados.',
            'contexto' => 'Proyecto de edificación. Secuencia típica: trabajos preliminares y trazo → excavación y cimentación → estructura (cimbra, acero, colado) → albañilería y muros → instalaciones (eléctrica, hidrosanitaria, HVAC) → acabados (aplanados, pisos, plafones, pintura, carpintería) → pruebas y entrega. Lo que más retrasa: lluvias en obra negra, suministro de acero y concreto, curado de colados, coordinación de instalaciones y aprobaciones de submittals. Errores comunes: cerrar muros antes de instalaciones, no respetar curado, acabados sobre humedad.',
            'etapas' => [['Preliminares y cimentación', ['trazo', 'excavacion', 'cimentacion', 'zapata', 'relleno', 'compactacion']], ['Estructura', ['cimbra', 'acero', 'armado', 'colado', 'losa', 'columna', 'trabe', 'estructura']], ['Albañilería', ['muro', 'block', 'albanileria', 'castillo', 'dala']], ['Instalaciones', ['instalacion electrica', 'hidraulica', 'sanitaria', 'hvac', 'ductos', 'tuberia', 'canalizacion']], ['Acabados', ['aplanado', 'piso', 'plafon', 'pintura', 'carpinteria', 'canceleria', 'azulejo', 'impermeabiliz']], ['Pruebas y entrega', ['prueba', 'limpieza', 'entrega', 'punch', 'recepcion']]],
            'submittals' => ['Diseño de mezcla de concreto', 'Acero de refuerzo (certificados)', 'Cancelería y vidrio', 'Equipos HVAC y eléctricos', 'Acabados (muestras)'], 'criticos' => [['acero', 30], ['concreto', 10], ['canceleria', 60], ['hvac', 70], ['elevador', 120]],
            'partidas' => [['1.0', 'Preliminares y cimentación', [['Trazo y nivelación', 3], ['Excavación', 8], ['Cimentación', 15]]], ['2.0', 'Estructura', [['Cimbra y armado', 20], ['Colado', 10], ['Descimbra y curado', 7]]], ['3.0', 'Albañilería', [['Muros', 20], ['Castillos y dalas', 8]]], ['4.0', 'Instalaciones', [['Instalación eléctrica', 20], ['Instalación hidrosanitaria', 15], ['HVAC', 15]]], ['5.0', 'Acabados', [['Aplanados', 15], ['Pisos y plafones', 15], ['Pintura y carpintería', 12]]], ['6.0', 'Pruebas y entrega', [['Pruebas de instalaciones', 5], ['Limpieza y punch list', 8], ['Entrega', 2]]]],
        ],
    ];

    public static function actual(int $pid): ?array
    {
        $f = self::filas("SELECT plantilla, base_aplicada FROM proyecto_plantilla WHERE proyecto_id = $pid")[0] ?? null;
        return $f && isset(self::CATALOGO[$f['plantilla']]) ? ['clave' => $f['plantilla'], 'base_aplicada' => (int)$f['base_aplicada']] + self::CATALOGO[$f['plantilla']] : null;
    }

    public static function elegir(int $pid, string $clave, string $origen = 'manual', int $confianza = 0, string $indicios = ''): bool
    {
        if ($clave === '') { self::db()->query("DELETE FROM proyecto_plantilla WHERE proyecto_id = $pid"); return true; }
        if (!isset(self::CATALOGO[$clave])) return false;
        self::db()->query("INSERT INTO proyecto_plantilla (proyecto_id, plantilla) VALUES ($pid, '$clave') ON DUPLICATE KEY UPDATE plantilla = '$clave'");
        try {   // el registro es opcional: si la migración está pendiente no se rompe la elección
            self::db()->query("INSERT INTO proyecto_plantilla_historial (proyecto_id, plantilla, origen, confianza, indicios, usuario) VALUES ($pid, '$clave', '" . ($origen === 'identificado' ? 'identificado' : 'manual') . "', " . max(0, min(100, $confianza)) . ", '" . self::db()->real_escape_string(mb_substr($indicios, 0, 500)) . "', '" . self::db()->real_escape_string(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "')");
        } catch (\Throwable $e) {
        }
        return true;
    }

    /** Registro de tipos de obra elegidos o identificados en el proyecto, del más reciente al más antiguo */
    public static function historial(int $pid, int $limite = 20): array
    {
        return self::filas("SELECT plantilla, origen, confianza, indicios, usuario, creado FROM proyecto_plantilla_historial WHERE proyecto_id = $pid ORDER BY id DESC LIMIT " . max(1, min(100, $limite)));
    }

    /** Palabras (sin acentos) que delatan cada tipo de obra en nombres de proyecto, partidas, actividades y materiales */
    private const PISTAS = [
        'videovigilancia' => ['cctv', 'videovigilancia', 'video vigilancia', 'camara', 'nvr', 'dvr', 'ptz', 'monitoreo', 'grabacion', 'analitica de video', 'vms'],
        'control_acceso' => ['control de acceso', 'acceso', 'lector', 'controlador', 'cerradura', 'electroiman', 'torniquete', 'biometric', 'tarjeta', 'proximidad', 'boton de salida', 'contacto magnetico'],
        'cableado' => ['cableado estructurado', 'voz y datos', 'patch', 'rack', 'utp', 'cat6', 'cat 6', 'fibra optica', 'certificacion', 'jack', 'salida de datos', 'idf', 'mdf'],
        'edificacion' => ['cimentacion', 'cimbra', 'colado', 'losa', 'zapata', 'albanileria', 'muro', 'excavacion', 'acabados', 'estructura', 'trabe', 'firme'],
    ];

    /**
     * Identifica el tipo de obra comparando lo ya capturado en el proyecto con las pistas de cada tipo. No guarda nada: devuelve el ranking para que el usuario confirme.
     * @return array{ranking: array, total: int, texto: bool} ranking: [clave => ['puntos'=>n, 'porcentaje'=>%, 'indicios'=>[palabras]]] de mayor a menor
     */
    public static function identificar(Proyecto $p): array
    {
        $pid = (int)$p->id;
        $partes = [(string)$p->nombre, (string)($p->descripcion ?? '')];
        foreach (self::filas("SELECT nombre FROM partidas WHERE proyecto_id = $pid") as $f) $partes[] = $f['nombre'];
        foreach (self::filas("SELECT a.nombre FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid LIMIT 800") as $f) $partes[] = $f['nombre'];
        foreach (self::filas("SELECT nombre FROM materiales WHERE proyecto_id = $pid LIMIT 800") as $f) $partes[] = $f['nombre'];
        $texto = ' ' . Voz::sinAcentos(mb_strtolower(implode(' | ', $partes))) . ' ';
        $res = [];
        foreach (self::PISTAS as $clave => $pistas) {
            $pts = 0;
            $ind = [];
            foreach ($pistas as $w) {
                $n = substr_count($texto, $w);
                if ($n > 0) { $pts += min($n, 10) * (str_contains($w, ' ') ? 2 : 1); $ind[] = $w . ($n > 1 ? " ×$n" : ''); }
            }
            $res[$clave] = ['puntos' => $pts, 'indicios' => $ind];
        }
        $total = array_sum(array_column($res, 'puntos'));
        foreach ($res as $k => $r) $res[$k]['porcentaje'] = $total > 0 ? (int)round($r['puntos'] * 100 / $total) : 0;
        uasort($res, fn($a, $b) => $b['puntos'] <=> $a['puntos']);
        return ['ranking' => $res, 'total' => $total, 'texto' => trim($texto) !== ''];
    }

    /** Bloque de contexto para los prompts de las herramientas de IA (vacío si el proyecto no eligió plantilla) */
    public static function contexto(Proyecto $p): string
    {
        $t = self::actual((int)$p->id);
        return $t ? "\n\n## CONTEXTO DEL TIPO DE OBRA ({$t['nombre']})\n{$t['contexto']}\nPlazos típicos de materiales críticos: " . implode(', ', array_map(fn($c) => "{$c[0]} ~{$c[1]} días", $t['criticos'])) . '.' : '';
    }

    /** Etapa (índice) de una actividad según su nombre, o null */
    private static function etapa(array $t, string $nombre): ?int
    {
        $n = Voz::sinAcentos($nombre);
        foreach ($t['etapas'] as $i => [, $palabras]) foreach ($palabras as $w) if (str_contains($n, $w)) return $i;
        return null;
    }

    /**
     * Regla de secuencia: dentro de cada partida, una actividad de una etapa no debería empezar antes de que empiece la etapa anterior.
     * Propone la dependencia (fin → inicio) con la última actividad de la etapa previa. @return array hallazgos para Inconsistencias
     */
    public static function reglaSecuencia(Proyecto $p, array $acts, array $rels): array
    {
        $t = self::actual((int)$p->id);
        if (!$t) return [];
        $porPartida = [];
        $global = [];
        foreach ($acts as $id => $a) {
            if ((int)$a['hito'] || (int)$a['progreso'] >= 100) continue;
            $e = self::etapa($t, $a['nombre']);
            if ($e === null) continue;
            $porPartida[$a['partida_id']][$e][] = $a + ['id' => $id];
            $global[$e][] = $a + ['id' => $id];
        }
        // Proyectos de una sola zona (pocas partidas, una por sistema): si la partida no trae la etapa previa se compara con la etapa previa de todo el proyecto
        $unaZona = count(array_unique(array_column($acts, 'partida_id'))) <= 12;
        $existe = [];
        foreach ($rels as $r) $existe[$r['pred'] . '>' . $r['suc']] = true;
        $out = [];
        $vistos = [];
        foreach ($porPartida as $etapas) {
            ksort($etapas);
            foreach ($etapas as $k => $lista) {
                $previas = $etapas[$k - 1] ?? ($unaZona ? ($global[$k - 1] ?? null) : null);
                if (!$previas || $k === 0) continue;
                $iniPrev = min(array_column($previas, 'fecha_inicio'));
                usort($previas, fn($x, $y) => strcmp($y['fecha_fin'], $x['fecha_fin']));
                $ultima = $previas[0];
                foreach ($lista as $a) {
                    if ($a['fecha_inicio'] >= $iniPrev || isset($existe[$ultima['id'] . '>' . $a['id']]) || isset($vistos[$a['id']]) || (int)$a['fecha_fija'] || (int)$ultima['id'] === (int)$a['id']) continue;
                    $vistos[$a['id']] = true;
                    $out[] = ['huella' => sha1("seq:{$p->id}:{$ultima['id']}:{$a['id']}"), 'categoria' => 'dependencias', 'severidad' => 'media', 'origen' => 'sistema',
                        'titulo' => 'Secuencia fuera de orden: ' . $a['codigo'] . ' antes que ' . $ultima['codigo'], 'descripcion' => "«{$a['nombre']}» ({$t['etapas'][$k][0]}) inicia el {$a['fecha_inicio']}, antes de que empiece la etapa «{$t['etapas'][$k - 1][0]}» ({$iniPrev}). En obras de {$t['nombre']} esa etapa suele ir primero.",
                        'actividades' => [(int)$a['id'], (int)$ultima['id']], 'solucion' => ['texto' => "Hacer que «{$a['codigo']}» empiece cuando termine «{$ultima['codigo']}» (relación fin → inicio).", 'accion' => 'agregar_dependencia', 'params' => ['pred' => (int)$ultima['id'], 'suc' => (int)$a['id'], 'tipo' => 'FS', 'retraso' => 0]]];
                    if (count($out) >= 12) return $out;
                }
            }
        }
        return $out;
    }

    /** Crea las partidas y actividades base de la plantilla (solo si el proyecto aún no tiene partidas). @return array{0: bool, 1: string} */
    public static function aplicarBase(Proyecto $p): array
    {
        $pid = (int)$p->id;
        $t = self::actual($pid);
        if (!$t) return [false, 'Elige primero un tipo de obra.'];
        if ((int)(self::filas("SELECT COUNT(*) n FROM partidas WHERE proyecto_id = $pid")[0]['n'] ?? 0) > 0) return [false, 'El proyecto ya tiene partidas: la base solo se puede crear en un proyecto vacío.'];
        $cal = new Calendario($pid);
        $mot = new RutaCritica($cal);
        $cursor = $mot->sig($p->fecha_inicio ?: date('Y-m-d'));
        $db = self::db();
        $colores = ['#2563eb', '#16a34a', '#f59e0b', '#9333ea', '#dc2626', '#0891b2'];
        $nAct = 0;
        $db->begin_transaction();
        try {
            foreach ($t['partidas'] as $i => [$codigo, $nombre, $acts]) {
                $pa = new Partida(['proyecto_id' => $pid, 'codigo' => $codigo, 'nombre' => $nombre, 'color_bg' => $colores[$i % count($colores)], 'orden' => $i + 1]);
                $r = $pa->guardar();
                $paId = is_array($r) ? (int)$r['id'] : (int)$pa->id;
                $pa->id = $paId;
                foreach ($acts as $j => [$an, $dur]) {
                    $fin = $cal->calcularFechaFin($cursor, $dur);
                    $a = new Actividad(['partida_id' => $paId, 'codigo' => preg_replace('/\.0$/', '', $codigo) . '.' . ($j + 1), 'nombre' => $an, 'fecha_inicio' => $cursor, 'duracion_dias' => $dur, 'fecha_fin' => $fin, 'orden' => $j + 1]);
                    $a->guardar();
                    $cursor = $mot->sig(date('Y-m-d', strtotime($fin . ' +1 day')));
                    $nAct++;
                }
                $pa->recalcularFechas();
            }
            $db->query("UPDATE proyecto_plantilla SET base_aplicada = 1 WHERE proyecto_id = $pid");
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo crear la base: ' . $e->getMessage()];
        }
        Auditoria::registrar($pid, 'plantillas', 'aplicado', 'aplicar_plantilla', "Base de «{$t['nombre']}»: " . count($t['partidas']) . " partidas y $nAct actividades", null, ['plantilla' => $t['clave']], null, '', ['proyecto', $pid, $t['nombre']]);
        return [true, 'Base creada: ' . count($t['partidas']) . " partidas y $nAct actividades (a partir del inicio del proyecto). Ajusta las duraciones y fechas a tu caso."];
    }
}
