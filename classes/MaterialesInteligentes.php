<?php

namespace Classes;

use Model\Proyecto;

/**
 * Materiales inteligentes: con la opción activa en el proyecto, la app de escritorio verifica cada material nuevo o modificado contra la normativa
 * y especificaciones cargadas (`Normativa`, cláusulas ya indexadas = la caché de documentación) y guarda el veredicto en `material_verificaciones`.
 * La web solo lee ese veredicto. La IA propone; aquí se valida: un «no aprobado» exige motivo y una cláusula real del proyecto citada.
 */
class MaterialesInteligentes
{
    use AccesoDb;

    public const ESTADOS = ['aprobado' => 'Aprobado', 'no_aprobado' => 'No aprobado', 'revisar' => 'Por revisar', 'sin_normativa' => 'Sin normativa aplicable'];
    private const POR_LOTE = 3;

    // ---------- opción por proyecto ----------

    public static function activo(int $pid): bool
    {
        return (bool)(self::filas("SELECT activo FROM materiales_inteligentes WHERE proyecto_id = $pid")[0]['activo'] ?? 0);
    }

    public static function fijarActivo(int $pid, bool $activo): bool
    {
        try {
            return (bool)self::db()->query("INSERT INTO materiales_inteligentes (proyecto_id, activo) VALUES ($pid, " . (int)$activo . ') ON DUPLICATE KEY UPDATE activo = ' . (int)$activo);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Documentos de normativa listos para consultar (con texto indexado) */
    public static function documentos(int $pid): int
    {
        return (int)(self::filas("SELECT COUNT(*) n FROM normativa_documentos WHERE proyecto_id = $pid AND estado = 'indexado' AND fragmentos > 0")[0]['n'] ?? 0);
    }

    // ---------- veredictos ----------

    /** Resume los datos que se verifican; si cambian, el veredicto queda desactualizado */
    public static function huella(array $m): string
    {
        $n = fn($k) => mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', (string)($m[$k] ?? ''))));
        return md5(implode('|', array_map($n, ['nombre_base', 'nombre', 'marca', 'modelo', 'valor_extra', 'referencia', 'especialidad', 'unidad', 'certificado_calidad'])));
    }

    /** @return array<int, array> material_id → verificación (con `info` decodificado) */
    public static function deProyecto(int $pid): array
    {
        $out = [];
        foreach (self::filas("SELECT * FROM material_verificaciones WHERE proyecto_id = $pid") as $v) $out[(int)$v['material_id']] = self::decodificar($v);
        return $out;
    }

    public static function deMaterial(int $mid): ?array
    {
        $v = self::filas("SELECT * FROM material_verificaciones WHERE material_id = $mid")[0] ?? null;
        return $v ? self::decodificar($v) : null;
    }

    private static function decodificar(array $v): array
    {
        $i = json_decode((string)$v['info'], true);
        $v['info'] = is_array($i) ? $i : ['motivos' => [], 'requisitos' => []];
        return $v;
    }

    /** ¿La verificación corresponde a los datos actuales del material? */
    public static function vigente(?array $v, \Model\Material $m): bool
    {
        return $v !== null && $v['huella'] === self::huella((array)$m);
    }

    /** Ids de materiales cuya verificación vigente es «no aprobado». @param int[] $ids */
    public static function noAprobados(int $pid, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return [];
        $out = [];
        $v = self::deProyecto($pid);
        foreach (\Model\Material::deProyecto($pid) as $m) {
            if (in_array((int)$m->id, $ids, true) && ($x = $v[(int)$m->id] ?? null) && $x['estado'] === 'no_aprobado' && self::vigente($x, $m)) $out[(int)$m->id] = ['nombre' => $m->nombre, 'resumen' => $x['resumen']];
        }
        return $out;
    }

    /** Materiales sin veredicto o con veredicto desactualizado */
    public static function pendientes(int $pid): array
    {
        $v = self::deProyecto($pid);
        return array_values(array_filter(\Model\Material::deProyecto($pid), fn($m) => !self::vigente($v[(int)$m->id] ?? null, $m)));
    }

    public static function conteos(int $pid): array
    {
        $c = array_fill_keys(array_keys(self::ESTADOS), 0);
        $v = self::deProyecto($pid);
        $pend = 0;
        foreach (\Model\Material::deProyecto($pid) as $m) {
            $x = $v[(int)$m->id] ?? null;
            if (self::vigente($x, $m)) $c[$x['estado']]++; else $pend++;
        }
        return $c + ['pendiente' => $pend];
    }

    // ---------- candidatos y lotes para la IA ----------

    /** Cláusulas con las que se contrasta un material: las aceptadas manualmente y las candidatas por sección CSI o palabras */
    private static function clausulas(int $pid, int $mid): array
    {
        $out = [];
        foreach (Normativa::deEntidad($pid, 'material', $mid) as $a) {
            if ($a['estado'] === 'aceptada' && $a['fragmento_id']) {
                $f = self::filas("SELECT f.id, f.documento_id, d.titulo doc, d.codigo, f.pagina, f.clausula, f.titulo, f.texto, f.idioma FROM normativa_fragmentos f JOIN normativa_documentos d ON d.id = f.documento_id WHERE f.id = " . (int)$a['fragmento_id'])[0] ?? null;
                if ($f) $out[(int)$f['id']] = $f;
            }
        }
        foreach (Normativa::candidatos($pid, 'material', $mid, 6) as $c) $out[(int)$c['id']] ??= $c;
        return array_slice(array_values($out), 0, 8);
    }

    private static function fichaMaterial(\Model\Material $m): string
    {
        $campos = ['Nombre' => $m->nombreBase(), 'Marca' => $m->marca, 'Modelo o tipo' => $m->modelo, 'Dimensiones/valor extra' => $m->valor_extra, 'Referencia (spec)' => $m->referencia, 'Especialidad' => $m->especialidad, 'Unidad' => $m->unidad, 'Certificado de calidad' => $m->certificado_calidad];
        $l = [];
        foreach ($campos as $k => $v) $l[] = "$k: " . (trim((string)$v) !== '' ? self::corto($v, 120) : '(no indicado)');
        return implode(' | ', $l);
    }

    /** @return list<array{titulo: string, prompt: string}> */
    public static function lotes(Proyecto $p, int $max = 24): array
    {
        $pid = (int)$p->id;
        $el = [];
        foreach (array_slice(self::pendientes($pid), 0, $max) as $m) {
            $c = self::clausulas($pid, (int)$m->id);
            if ($c) $el[] = [$m, $c];
        }
        $lotes = [];
        foreach (array_chunk($el, self::POR_LOTE) as $i => $grupo) {
            $txt = [];
            foreach ($grupo as [$m, $c]) {
                $txt[] = "### MATERIAL {$m->id}: " . self::fichaMaterial($m) . "\n" . implode("\n", array_map(fn($f) => "- CLÁUSULA {$f['id']} [{$f['doc']} · " . ($f['clausula'] !== '' ? $f['clausula'] . ' · ' : '') . "pág. {$f['pagina']} · " . strtoupper($f['idioma']) . '] ' . self::corto(trim($f['titulo'] . '. ' . $f['texto']), 650), $c));
            }
            $lotes[] = ['titulo' => 'Verificando materiales ' . ($i * self::POR_LOTE + 1) . ' a ' . ($i * self::POR_LOTE + count($grupo)) . ' de ' . count($el), 'prompt' =>
                "Eres revisor de calidad de una obra. Para cada MATERIAL decide si cumple las normas y especificaciones del proyecto, usando SOLO las cláusulas listadas (pueden estar en inglés o español; responde en español). Reglas:\n"
                . "- \"no_aprobado\": solo si una cláusula contradice claramente un dato del material (marca no permitida, dimensión o capacidad menor a la exigida, norma distinta…). Cita la cláusula y explica el motivo.\n"
                . "- \"aprobado\": los datos del material cumplen lo que exigen las cláusulas que le aplican.\n"
                . "- \"revisar\": faltan datos del material para decidir (p. ej. no indica dimensiones o marca) o las cláusulas no son concluyentes.\n"
                . "- No inventes requisitos que no estén en el texto; si una cláusula no aplica a ese material, ignórala.\n\n"
                . implode("\n\n", array_map(fn($t) => $t, $txt))
                . "\n\nDevuelve SOLO un JSON: {\"materiales\":[{\"material\":12,\"veredicto\":\"aprobado|no_aprobado|revisar\",\"resumen\":\"máx. 200 caracteres\",\"motivos\":[\"…\"],\"requisitos\":[{\"clausula\":345,\"requisito\":\"qué exige, máx. 200 caracteres\",\"cumple\":true}]}]}  (cumple: true, false o null si no se puede saber)"];
        }
        return $lotes;
    }

    // ---------- resultado ----------

    /**
     * Valida las respuestas de la IA. Un «no aprobado» sin motivo o sin cláusula real citada baja a «por revisar».
     * @return array<int, array{estado: string, resumen: string, motivos: list<string>, requisitos: list<array>}>
     */
    public static function normalizarIA(Proyecto $p, array $respuestas): array
    {
        $pid = (int)$p->id;
        $pend = [];
        foreach (self::pendientes($pid) as $m) $pend[(int)$m->id] = $m;
        $out = [];
        foreach ($respuestas as $r) {
            foreach ((array)($r['materiales'] ?? []) as $x) {
                if (!is_array($x)) continue;
                $mid = (int)($x['material'] ?? 0);
                if (!isset($pend[$mid]) || isset($out[$mid])) continue;
                $ver = (string)($x['veredicto'] ?? '');
                if (!in_array($ver, ['aprobado', 'no_aprobado', 'revisar'], true)) continue;
                $cl = [];
                foreach (self::clausulas($pid, $mid) as $f) $cl[(int)$f['id']] = $f;
                $req = [];
                foreach ((array)($x['requisitos'] ?? []) as $q) {
                    $cid = (int)($q['clausula'] ?? 0);
                    if (!is_array($q) || !isset($cl[$cid])) continue;
                    $c = $q['cumple'] ?? null;
                    $req[] = ['clausula_id' => $cid, 'documento' => $cl[$cid]['doc'], 'clausula' => $cl[$cid]['clausula'], 'pagina' => (int)$cl[$cid]['pagina'], 'requisito' => Informes::limpiarTexto($q['requisito'] ?? '', 250), 'cumple' => $c === true || $c === 'true' ? true : ($c === false || $c === 'false' ? false : null)];
                }
                $motivos = array_values(array_filter(array_map(fn($t) => Informes::limpiarTexto($t, 250), array_slice((array)($x['motivos'] ?? []), 0, 6))));
                $resumen = Informes::limpiarTexto($x['resumen'] ?? '', 300);
                if ($ver === 'no_aprobado' && (!$motivos || !$req)) {
                    $ver = 'revisar';
                    $resumen = 'La IA sugirió no aprobar, pero sin motivo o cláusula verificable: revísalo. ' . $resumen;
                }
                if ($resumen === '') $resumen = $motivos[0] ?? self::ESTADOS[$ver];
                $out[$mid] = ['estado' => $ver, 'resumen' => $resumen, 'motivos' => $motivos, 'requisitos' => $req];
            }
        }
        return $out;
    }

    /**
     * Guarda los veredictos. Los materiales pendientes sin ninguna cláusula candidata quedan «sin normativa aplicable» (regla del sistema);
     * los que tenían candidatas pero la IA no resolvió (lote fallido) siguen pendientes.
     * @return array{verificados: int, no_aprobados: list<array>, por_revisar: int, aprobados: int, sin_normativa: int}
     */
    public static function guardar(Proyecto $p, array $resultados, string $modelo): array
    {
        $pid = (int)$p->id;
        $res = ['verificados' => 0, 'aprobados' => 0, 'por_revisar' => 0, 'sin_normativa' => 0, 'no_aprobados' => []];
        foreach (self::pendientes($pid) as $m) {
            $mid = (int)$m->id;
            $r = $resultados[$mid] ?? null;
            if (!$r) {
                if (self::clausulas($pid, $mid)) continue;   // tenía cláusulas y la IA no respondió: se reintenta después
                $r = ['estado' => 'sin_normativa', 'resumen' => 'No se encontraron cláusulas aplicables en la normativa cargada.', 'motivos' => [], 'requisitos' => []];
            }
            $antes = self::deMaterial($mid);
            $info = ['motivos' => $r['motivos'], 'requisitos' => $r['requisitos']];
            self::db()->query("REPLACE INTO material_verificaciones (material_id, proyecto_id, estado, huella, resumen, info, modelo) VALUES ($mid, $pid, '{$r['estado']}', '" . self::huella((array)$m) . "', '" . self::esc(mb_substr($r['resumen'], 0, 500)) . "', '" . self::esc(json_encode($info, JSON_UNESCAPED_UNICODE)) . "', '" . self::esc(mb_substr($modelo, 0, 150)) . "')");
            Auditoria::registrar($pid, 'normativa', 'aplicado', 'verificar_material', 'Material ' . strtolower(self::ESTADOS[$r['estado']]) . ': ' . self::corto($r['resumen'], 200), $antes ? ['estado' => $antes['estado'], 'resumen' => $antes['resumen']] : null, ['estado' => $r['estado'], 'resumen' => $r['resumen']], null, $modelo, ['material', $mid, $m->nombre]);
            $res['verificados']++;
            if ($r['estado'] === 'no_aprobado') $res['no_aprobados'][] = ['id' => $mid, 'nombre' => $m->nombre, 'resumen' => $r['resumen']];
            else $res[['aprobado' => 'aprobados', 'revisar' => 'por_revisar', 'sin_normativa' => 'sin_normativa'][$r['estado']]]++;
        }
        if ($res['verificados']) Revisiones::registrarEjecucion($pid, 'inteligentes');
        return $res;
    }

    // ---------- presentación (web, solo lectura) ----------

    /** Insignia del veredicto; `$vigente` false = el material cambió después de verificarse */
    public static function badge(?array $v, bool $vigente, bool $activa): string
    {
        $h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        if (!$v || !$vigente) {
            if (!$activa && !$v) return '';
            $txt = $v ? 'Verificación desactualizada: el material cambió. La app de escritorio lo volverá a verificar.' : 'Pendiente de verificar con la app de escritorio.';
            return '<span class="badge badge-norma badge-norma-pendiente" title="' . $h($txt) . '"><i class="fa-regular fa-clock"></i> ' . ($v ? 'Desactualizada' : 'Pendiente') . '</span>';
        }
        $ico = ['aprobado' => 'fa-circle-check', 'no_aprobado' => 'fa-triangle-exclamation', 'revisar' => 'fa-circle-question', 'sin_normativa' => 'fa-minus'][$v['estado']];
        return '<span class="badge badge-norma badge-norma-' . $h($v['estado']) . '" title="' . $h($v['resumen']) . '"><i class="fa-solid ' . $ico . '"></i> ' . $h(self::ESTADOS[$v['estado']]) . '</span>';
    }
}
