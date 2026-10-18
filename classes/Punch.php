<?php

namespace Classes;

use Model\Proyecto;

/**
 * Punch list: pendientes de calidad con responsable, fecha límite y fotos antes/después; inspecciones con listas de verificación por sistema que convierten cada
 * falla en un pendiente; y un análisis de causas repetidas (por categoría, contratista y ubicación). Los pendientes los captura una persona; la IA solo ayuda a
 * estructurar lo dictado y a redactar el análisis.
 */
class Punch
{
    public const ESTADOS = ['abierto' => 'Abierto', 'en_proceso' => 'En proceso', 'corregido' => 'Corregido (por verificar)', 'cerrado' => 'Cerrado', 'rechazado' => 'Rechazado (no procede)'];
    public const SEVERIDADES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'];
    public const CARPETA = 'punch';
    public const PLANTILLAS = [
        'cctv' => ['nombre' => 'Videovigilancia (CCTV)', 'items' => [['Enfoque y ángulo de cada cámara', 'Cámaras'], ['Cámara fija y sellada (sin juego ni humedad)', 'Cámaras'], ['Etiquetado de cámara y cable en ambos extremos', 'Etiquetado'], ['Cableado ordenado, sujeto y sin tensión', 'Cableado'], ['Conectores y terminaciones sin daños', 'Cableado'], ['Alimentación PoE/fuente estable', 'Energía'], ['Grabación y reproducción verificadas', 'Configuración'], ['Cobertura según proyecto (sin puntos ciegos)', 'Cámaras'], ['Registro en el sistema de gestión de video', 'Configuración']]],
        'acceso' => ['nombre' => 'Control de acceso', 'items' => [['Lector instalado, nivelado y fijo', 'Lectores'], ['Cerradura/electroimán alinea y cierra', 'Cerraduras'], ['Contacto magnético sin falsos', 'Sensores'], ['Botón de salida y apertura de emergencia funcionan', 'Seguridad'], ['Controlador identificado y con respaldo de energía', 'Energía'], ['Prueba de tarjeta/credencial por puerta', 'Configuración'], ['Etiquetado de puerta y cableado', 'Etiquetado']]],
        'cableado' => ['nombre' => 'Cableado estructurado (voz y datos)', 'items' => [['Etiquetado de salidas y patch panel', 'Etiquetado'], ['Certificación del enlace (reporte de pruebas)', 'Pruebas'], ['Radio de curvatura y holgura de cable', 'Cableado'], ['Faceplates y jacks completos y alineados', 'Salidas'], ['Orden del rack y cable management', 'Rack'], ['Sellado cortafuego de pasos', 'Cortafuego']]],
        'electrico' => ['nombre' => 'Instalación eléctrica', 'items' => [['Tablero identificado con directorio de circuitos', 'Tableros'], ['Conexiones apretadas y sin calentamiento', 'Conexiones'], ['Continuidad de tierra física', 'Tierras'], ['Canalizaciones fijas, con registros accesibles', 'Canalización'], ['Luminarias y contactos probados', 'Equipos'], ['Cableado con color y calibre correctos', 'Cableado']]],
        'acabados' => ['nombre' => 'Obra civil y acabados', 'items' => [['Muros sin grietas ni desplomes', 'Muros'], ['Pintura uniforme sin escurrimientos', 'Pintura'], ['Pisos nivelados y sin piezas rotas', 'Pisos'], ['Plafón nivelado y sin manchas', 'Plafón'], ['Puertas y cancelería alineadas, cierran bien', 'Carpintería'], ['Limpieza final de la zona', 'Limpieza']]],
        'general' => ['nombre' => 'Inspección general', 'items' => [['Trabajo terminado según planos y especificación', 'Cumplimiento'], ['Calidad de los materiales instalados', 'Materiales'], ['Limpieza y orden del área', 'Limpieza'], ['Seguridad: sin riesgos visibles', 'Seguridad'], ['Documentación y pruebas entregadas', 'Documentos']]],
    ];

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function filas(string $sql): array
    {
        try {
            $r = self::db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function esc($v): string
    {
        return self::db()->real_escape_string((string)$v);
    }

    public static function bytes(int $pid): int
    {
        $t = 0;
        foreach (self::filas("SELECT foto_antes, foto_despues FROM punch_items WHERE proyecto_id = $pid") as $f) foreach (['foto_antes', 'foto_despues'] as $c) if ($f[$c]) $t += (int)@filesize(dirname(__DIR__) . '/public/uploads/' . self::CARPETA . '/' . basename($f[$c]));
        return $t;
    }

    public static function listar(int $pid, array $f = []): array
    {
        $w = ["i.proyecto_id = $pid"];
        if (isset(self::ESTADOS[$f['estado'] ?? ''])) $w[] = "i.estado = '{$f['estado']}'";
        elseif (($f['estado'] ?? '') === 'pendientes') $w[] = "i.estado IN ('abierto','en_proceso','corregido')";
        if (isset(self::SEVERIDADES[$f['severidad'] ?? ''])) $w[] = "i.severidad = '{$f['severidad']}'";
        if (($f['contratista'] ?? '') !== '') $w[] = "i.contratista = '" . self::esc($f['contratista']) . "'";
        if (($f['categoria'] ?? '') !== '') $w[] = "i.categoria = '" . self::esc($f['categoria']) . "'";
        if (($f['q'] ?? '') !== '') $w[] = "(i.titulo LIKE '%" . self::esc(str_replace(['%', '_'], ['\\%', '\\_'], $f['q'])) . "%' OR i.ubicacion LIKE '%" . self::esc(str_replace(['%', '_'], ['\\%', '\\_'], $f['q'])) . "%')";
        $hoy = date('Y-m-d');
        $r = self::filas("SELECT i.*, a.codigo act_codigo FROM punch_items i LEFT JOIN actividades a ON a.id = i.actividad_id WHERE " . implode(' AND ', $w) . " ORDER BY FIELD(i.estado,'abierto','en_proceso','corregido','cerrado','rechazado'), FIELD(i.severidad,'critica','alta','media','baja'), i.fecha_limite IS NULL, i.fecha_limite LIMIT 400");
        foreach ($r as &$x) $x['vencido'] = in_array($x['estado'], ['abierto', 'en_proceso'], true) && $x['fecha_limite'] && $x['fecha_limite'] < $hoy;
        return $r;
    }

    public static function obtener(int $pid, int $id): ?array
    {
        return self::filas("SELECT i.*, a.codigo act_codigo, a.nombre act_nombre FROM punch_items i LEFT JOIN actividades a ON a.id = i.actividad_id WHERE i.id = $id AND i.proyecto_id = $pid")[0] ?? null;
    }

    /** @return array{0: bool, 1: string, 2: int} */
    public static function guardar(int $pid, array $d, ?array $existente = null, string $origen = 'manual'): array
    {
        $titulo = trim(mb_substr((string)($d['titulo'] ?? ''), 0, 200));
        if ($titulo === '') return [false, 'El título es obligatorio.', 0];
        $lim = \DateTime::createFromFormat('Y-m-d', (string)($d['fecha_limite'] ?? ''));
        $aid = (int)($d['actividad_id'] ?? 0);
        if ($aid && !self::filas("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $aid AND pa.proyecto_id = $pid")) $aid = 0;
        $sev = isset(self::SEVERIDADES[$d['severidad'] ?? '']) ? $d['severidad'] : 'media';
        $campos = "titulo = '" . self::esc($titulo) . "', descripcion = '" . self::esc(mb_substr((string)($d['descripcion'] ?? ''), 0, 3000)) . "', actividad_id = " . ($aid ?: 'NULL') . ", ubicacion = '" . self::esc(mb_substr((string)($d['ubicacion'] ?? ''), 0, 200)) . "', categoria = '" . self::esc(mb_substr((string)($d['categoria'] ?? ''), 0, 60))
            . "', severidad = '$sev', responsable = '" . self::esc(mb_substr((string)($d['responsable'] ?? ''), 0, 150)) . "', contratista = '" . self::esc(mb_substr((string)($d['contratista'] ?? ''), 0, 150)) . "', fecha_limite = " . ($lim && $lim->format('Y-m-d') === $d['fecha_limite'] ? "'{$d['fecha_limite']}'" : 'NULL');
        $db = self::db();
        if ($existente) { $db->query("UPDATE punch_items SET $campos WHERE id = " . (int)$existente['id'] . " AND proyecto_id = $pid"); return [true, 'Pendiente actualizado.', (int)$existente['id']]; }
        $db->query("INSERT INTO punch_items SET proyecto_id = $pid, $campos, origen = '" . (in_array($origen, ['manual', 'inspeccion', 'ia'], true) ? $origen : 'manual') . "', creado_por = '" . self::esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "'");
        return [true, 'Pendiente registrado.', (int)$db->insert_id];
    }

    public static function ponerFoto(int $pid, int $id, string $campo, string $nombre): void
    {
        if (!in_array($campo, ['foto_antes', 'foto_despues'], true)) return;
        $x = self::obtener($pid, $id);
        if (!$x) return;
        if ($x[$campo]) Imagenes::eliminarEn(self::CARPETA, $x[$campo]);
        self::db()->query("UPDATE punch_items SET $campo = '" . self::esc($nombre) . "' WHERE id = $id AND proyecto_id = $pid");
    }

    public static function estado(Proyecto $p, int $id, string $estado): array
    {
        $pid = (int)$p->id;
        $x = self::obtener($pid, $id);
        if (!$x || !isset(self::ESTADOS[$estado])) return [false, 'Pendiente no encontrado'];
        self::db()->query("UPDATE punch_items SET estado = '$estado', cerrado_en = " . ($estado === 'cerrado' ? 'NOW()' : 'NULL') . " WHERE id = $id AND proyecto_id = $pid");
        Auditoria::registrar($pid, 'punch', $estado === 'rechazado' ? 'rechazado' : 'aplicado', 'punch_estado', 'Pendiente «' . mb_substr($x['titulo'], 0, 80) . '»: ' . self::ESTADOS[$x['estado']] . ' → ' . self::ESTADOS[$estado], ['estado' => $x['estado']], ['estado' => $estado], $id, '', ['punch', $id, $x['titulo']]);
        return [true, 'Estado actualizado.'];
    }

    public static function eliminar(int $pid, int $id): void
    {
        $x = self::obtener($pid, $id);
        if (!$x) return;
        foreach (['foto_antes', 'foto_despues'] as $c) if ($x[$c]) Imagenes::eliminarEn(self::CARPETA, $x[$c]);
        self::db()->query("DELETE FROM punch_items WHERE id = $id AND proyecto_id = $pid");
    }

    /** Crea un pendiente por cada falla de una inspección. @param array $resultados lista de ['texto','categoria','estado'=>ok|falla|na,'nota'] */
    public static function desdeInspeccion(int $pid, string $plantilla, string $ubicacion, string $contratista, string $responsable, array $resultados): int
    {
        $n = 0;
        foreach ($resultados as $r) {
            if (($r['estado'] ?? '') !== 'falla') continue;
            [$ok] = self::guardar($pid, ['titulo' => mb_substr((string)$r['texto'], 0, 200), 'descripcion' => trim((string)($r['nota'] ?? '')) . ' (Inspección: ' . (self::PLANTILLAS[$plantilla]['nombre'] ?? $plantilla) . ')', 'ubicacion' => $ubicacion, 'categoria' => $r['categoria'] ?? '', 'severidad' => 'media', 'contratista' => $contratista, 'responsable' => $responsable, 'fecha_limite' => date('Y-m-d', strtotime('+7 days'))], null, 'inspeccion');
            if ($ok) $n++;
        }
        return $n;
    }

    public static function contratistas(int $pid): array
    {
        return array_column(self::filas("SELECT DISTINCT contratista FROM punch_items WHERE proyecto_id = $pid AND contratista <> '' ORDER BY contratista"), 'contratista');
    }

    /** Estadísticas y causas repetidas */
    public static function analisis(int $pid): array
    {
        $hoy = date('Y-m-d');
        $pend = "proyecto_id = $pid AND estado IN ('abierto','en_proceso','corregido')";
        $g = fn(string $col) => self::filas("SELECT COALESCE(NULLIF($col, ''), '(sin dato)') clave, COUNT(*) n, SUM(estado IN ('abierto','en_proceso','corregido')) pendientes, SUM(severidad IN ('alta','critica') AND estado IN ('abierto','en_proceso','corregido')) graves FROM punch_items WHERE proyecto_id = $pid AND estado <> 'rechazado' GROUP BY clave HAVING n >= 1 ORDER BY pendientes DESC, n DESC LIMIT 8");
        $t = self::filas("SELECT COUNT(*) total, SUM(estado IN ('abierto','en_proceso','corregido')) pendientes, SUM(estado = 'cerrado') cerrados, SUM(estado IN ('abierto','en_proceso') AND fecha_limite < '$hoy') vencidos, AVG(CASE WHEN estado IN ('abierto','en_proceso','corregido') THEN DATEDIFF('$hoy', DATE(creado)) END) edad FROM punch_items WHERE proyecto_id = $pid AND estado <> 'rechazado'")[0] ?? [];
        return ['total' => (int)($t['total'] ?? 0), 'pendientes' => (int)($t['pendientes'] ?? 0), 'cerrados' => (int)($t['cerrados'] ?? 0), 'vencidos' => (int)($t['vencidos'] ?? 0), 'edad' => $t['edad'] !== null ? round((float)$t['edad'], 1) : null,
            'categorias' => $g('categoria'), 'contratistas' => $g('contratista'), 'ubicaciones' => $g('ubicacion')];
    }

    public static function pdf(Proyecto $p, string $contratista = ''): string
    {
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $items = array_values(array_filter(self::listar((int)$p->id, ['estado' => 'pendientes', 'contratista' => $contratista]), fn($x) => true));
        $fmt = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
        $filas = implode('', array_map(fn($x, $i) => '<tr><td>' . ($i + 1) . '</td><td><b>' . $e($x['titulo']) . '</b><br>' . $e(mb_substr((string)$x['descripcion'], 0, 220)) . '</td><td>' . $e($x['ubicacion']) . '</td><td>' . $e($x['categoria']) . '</td><td>' . $e(self::SEVERIDADES[$x['severidad']]) . '</td><td>' . $e($x['responsable'] ?: $x['contratista']) . '</td><td>' . $fmt($x['fecha_limite']) . ($x['vencido'] ? ' ⚠' : '') . '</td><td>' . $e(self::ESTADOS[$x['estado']]) . '</td></tr>', $items, array_keys($items)));
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:9px}h1{font-size:15px;margin:0}h2{font-size:10px;color:#555;margin:2px 0 10px;font-weight:normal}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:3px 5px;vertical-align:top;text-align:left}th{background:#e8ecf1}</style></head><body><h1>Punch list' . ($contratista !== '' ? ' · ' . $e($contratista) : '') . '</h1><h2>' . $e($p->nombre) . ' · ' . count($items) . ' pendiente(s) · generado el ' . date('d/m/Y H:i') . '</h2>'
            . '<table><thead><tr><th>#</th><th>Pendiente</th><th>Ubicación</th><th>Categoría</th><th>Severidad</th><th>Responsable</th><th>Fecha límite</th><th>Estado</th></tr></thead><tbody>' . $filas . '</tbody></table></body></html>';
        $o = new \Dompdf\Options();
        $o->set('defaultFont', 'DejaVu Sans');
        $d = new \Dompdf\Dompdf($o);
        $d->loadHtml($html);
        $d->setPaper('letter', 'landscape');
        $d->render();
        return $d->output();
    }
}
