<?php

namespace Classes;

use Model\Material;
use Model\Proyecto;

/**
 * Análisis de materiales: revisa el catálogo de materiales, los submittals pendientes, la información incompleta y la coherencia
 * entre los materiales y el MSR (Reportes de materiales); propone renombrar los materiales a la estructura de nombre completo
 * (nombre base + marca + modelo/tipo + valor extra). Reglas del sistema + revisión de la IA; cada hallazgo trae una solución que
 * solo se aplica cuando el admin la acepta, y se puede revertir. Los análisis son guardables (historial).
 */
class AnalisisMateriales
{
    public const CATEGORIAS = ['nombre' => 'Nombre del material', 'msr' => 'Material vs. MSR', 'submittals' => 'Submittals', 'incompleto' => 'Información incompleta', 'fechas' => 'Fechas',
        'duplicados' => 'Duplicados', 'vinculos' => 'Vínculos', 'otro' => 'Otro'];
    public const SEVERIDADES = ['baja' => 1, 'media' => 2, 'alta' => 3, 'critica' => 4];
    public const ACCIONES = ['nota' => [], 'renombrar_material' => ['material_id', 'nombre_base'], 'sincronizar_msr_material' => ['material_id'], 'sincronizar_msr_submittal' => ['submittal_id'], 'sincronizar_instalacion' => []];
    public const LOTE = 25;
    public const CORRIDAS_SIN_GUARDAR = 5;
    private const DIAS_SUBMITTAL = 14;   // un submittal enviado sin respuesta pasado este tiempo se señala
    private const FECHAS_MSR = ['fecha_compra_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real'];

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function corto(?string $t, int $n): string
    {
        $t = trim((string)preg_replace('/\s+/u', ' ', (string)$t));
        return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
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

    private static function hallazgo(string $regla, string $cat, string $sev, string $titulo, string $desc, array $mats, array $subs, string $texto, string $accion = 'nota', array $params = []): array
    {
        return ['huella' => sha1('s:' . $regla), 'categoria' => $cat, 'severidad' => $sev, 'origen' => 'sistema', 'titulo' => self::corto($titulo, 250), 'descripcion' => $desc,
            'materiales' => array_values(array_unique($mats)), 'submittals' => array_values(array_unique($subs)), 'solucion' => ['texto' => $texto, 'accion' => $accion, 'params' => $params]];
    }

    private static function partes(array $m): array
    {
        return ['nombre_base' => (string)($m['nombre_base'] ?? $m['nombre']), 'marca' => (string)$m['marca'], 'modelo' => (string)$m['modelo'], 'valor_extra' => (string)$m['valor_extra']];
    }

    /** Palabras de un texto sin acentos ni mayúsculas, para comparar nombres */
    private static function palabras(string $t): array
    {
        $t = mb_strtolower(strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n']));
        // las palabras de relleno («tipo», «modelo», «de»…) se pierden o se ganan al estructurar el nombre y no cuentan como información
        $vacias = ['tipo', 'modelo', 'mod', 'marca', 'serie', 'series', 'de', 'del', 'la', 'el', 'los', 'las', 'con', 'para', 'y', 'e', 'en', 'a', 'por', 'sku', 'ref', 'no', 'num'];
        return array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', $t), fn($w) => $w !== '' && !in_array($w, $vacias, true))));
    }

    /** ¿El nombre propuesto conserva la información del actual? (reordenar y corregir typos sí; inventar o perder datos no) */
    private static function conservaInformacion(string $actual, string $nuevo): bool
    {
        $a = self::palabras($actual);
        $n = self::palabras($nuevo);
        if (!$a || !$n) return false;
        $parecida = function (string $w, array $en) {
            foreach ($en as $x) if ($x === $w || (strlen($w) > 4 && strlen($x) > 4 && levenshtein($w, $x) <= 2)) return true;
            return false;
        };
        $perdidas = count(array_filter($a, fn($w) => !$parecida($w, $n)));
        $nuevas = count(array_filter($n, fn($w) => !$parecida($w, $a)));
        return $perdidas <= max(1, (int)floor(count($a) * 0.2)) && $nuevas <= max(2, (int)floor(count($n) * 0.25));
    }

    // ---------- fase 1: reglas del sistema ----------

    public static function detectarSistema(Proyecto $p, ?string $hoy = null): array
    {
        $pid = (int)$p->id;
        $hoy ??= date('Y-m-d');
        $out = [];
        $mats = [];
        foreach (self::filas("SELECT * FROM materiales WHERE proyecto_id = $pid ORDER BY especialidad, nombre") as $m) $mats[(int)$m['id']] = $m;
        if (!$mats) return [];
        $items = [];
        foreach (self::filas("SELECT i.*, s.estatus AS sub_estatus FROM msr_items i LEFT JOIN submittals s ON s.id = i.submittal_id WHERE i.proyecto_id = $pid") as $i) $items[(int)$i['material_id']][] = $i;
        $subs = [];
        foreach (self::filas("SELECT * FROM submittals WHERE proyecto_id = $pid") as $s) $subs[(int)$s['id']] = $s;
        $subDe = [];   // material_id => submittals que lo incluyen
        foreach (self::filas("SELECT sm.material_id, sm.submittal_id FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE s.proyecto_id = $pid") as $f) $subDe[(int)$f['material_id']][] = (int)$f['submittal_id'];
        $uso = [];
        foreach (self::filas("SELECT am.material_id, MIN(a.fecha_inicio) AS inicio, COUNT(*) AS n FROM actividad_materiales am JOIN actividades a ON a.id = am.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid GROUP BY am.material_id") as $f) $uso[(int)$f['material_id']] = $f;
        $nom = fn($m) => self::corto($m['nombre'], 70);

        $sinEstructura = [];
        $vistos = [];
        $faltan = [];
        $malInstalacion = [];
        foreach ($mats as $id => $m) {
            $partes = self::partes($m);
            $compuesto = Material::componerNombre(...array_values($partes));
            // nombre completo desactualizado respecto a sus partes
            if ($compuesto !== '' && $compuesto !== $m['nombre']) {
                $out[] = self::hallazgo("nombre_desactualizado:$id:" . md5($m['nombre'] . $compuesto), 'nombre', 'media', 'Nombre completo no coincide con sus partes: ' . $nom($m),
                    "El nombre guardado es «{$m['nombre']}» pero sus partes (nombre base, marca, modelo, valor extra) forman «{$compuesto}».", [$id], [], "Armar el nombre completo con sus partes: «{$compuesto}».", 'renombrar_material', ['material_id' => $id] + $partes);
            }
            if (trim((string)$m['marca']) === '' && trim((string)$m['modelo']) === '') $sinEstructura[] = $id;
            // duplicados
            $k = mb_strtolower(trim($m['nombre'])) . '|' . mb_strtolower(trim((string)$m['referencia']));
            $vistos[$k][] = $id;

            // información incompleta: se agrupa por tipo de dato faltante (un hallazgo por tipo, con todos sus materiales)
            $enSub = !empty($subDe[$id]) || array_filter($items[$id] ?? [], fn($i) => $i['submittal_id']);
            if (trim((string)$m['especialidad']) === '') $faltan['especialidad'][] = $id;
            if ($m['cantidad'] === null || (float)$m['cantidad'] <= 0) $faltan['cantidad'][] = $id;
            if (trim((string)$m['unidad']) === '') $faltan['unidad'][] = $id;
            if (empty($m['ficha_tecnica']) && ($enSub || $m['compra'] === 'comprado')) $faltan['ficha técnica (PDF)'][] = $id;
            if ($m['compra'] === 'pendiente' && (!$m['fecha_compra_prog'] || !$m['fecha_entrega_prog'])) $faltan['fechas programadas de compra/entrega'][] = $id;
            if (($items[$id] ?? []) && array_filter($items[$id], fn($i) => trim((string)$i['proveedor']) === '')) $faltan['proveedor en el MSR'][] = $id;
            if (empty($uso[$id])) $faltan['una actividad que lo use'][] = $id;
            if (!$enSub) $faltan['submittal'][] = $id;

            // coherencia con el MSR
            $mi = $items[$id] ?? [];
            if (!$mi) {
                $out[] = self::hallazgo("sin_msr:$id", 'msr', 'media', 'Material fuera del MSR: ' . $nom($m), 'Este material no aparece en ningún renglón de los Reportes de materiales (MSR).', [$id], [], 'Agregarlo al MSR desde la pantalla del MSR (no se cambia nada automáticamente).');
            } else {
                $desfase = array_filter($mi, function ($i) use ($m) {
                    foreach (self::FECHAS_MSR as $c) if (($i[$c] ?: null) !== ($m[$c] ?: null)) return true;
                    return false;
                });
                if ($desfase) {
                    $out[] = self::hallazgo("msr_fechas:$id:" . md5(json_encode(array_map(fn($i) => [$i['id']] + array_intersect_key($i, array_flip(self::FECHAS_MSR)), $desfase)) . json_encode(array_intersect_key($m, array_flip(self::FECHAS_MSR)))), 'msr', 'media', 'Fechas del MSR distintas al material: ' . $nom($m),
                        count($desfase) . ' renglón(es) del MSR tienen fechas de compra/entrega distintas a las del material (el catálogo manda).', [$id], [], 'Copiar las fechas del material a sus renglones del MSR.', 'sincronizar_msr_material', ['material_id' => $id]);
                }
                if (!empty($uso[$id]['inicio']) && array_filter($mi, fn($i) => ($i['fecha_instalacion_prog'] ?: null) !== $uso[$id]['inicio'])) $malInstalacion[] = $id;
            }
            // entrega programada después de que se necesita
            if (!empty($uso[$id]['inicio']) && $m['fecha_entrega_prog'] && !$m['fecha_entrega_real'] && $m['fecha_entrega_prog'] > $uso[$id]['inicio']) {
                $out[] = self::hallazgo("entrega_tarde:$id:{$m['fecha_entrega_prog']}:{$uso[$id]['inicio']}", 'fechas', 'alta', 'Llega después de que se necesita: ' . $nom($m), "Entrega programada el {$m['fecha_entrega_prog']} y la primera actividad que lo usa inicia el {$uso[$id]['inicio']}.", [$id], [],
                    'Adelantar la compra/entrega o reprogramar la actividad (no se cambia nada automáticamente).');
            }
            // comprado sin submittal aprobado
            if ($m['compra'] === 'comprado' && $enSub) {
                $ests = array_unique(array_filter(array_map(fn($sid) => $subs[$sid]['estatus'] ?? null, $subDe[$id] ?? [])) + array_filter(array_map(fn($i) => $i['sub_estatus'], $mi)));
                if ($ests && !array_intersect($ests, ['aprobado', 'aprobado_an'])) {
                    $out[] = self::hallazgo("comprado_sin_aprobar:$id:" . implode(',', $ests), 'submittals', 'alta', 'Comprado sin submittal aprobado: ' . $nom($m), 'El material está como comprado pero su submittal está «' . implode('/', $ests) . '».', [$id], array_values($subDe[$id] ?? []),
                        'Confirmar con el cliente la aprobación o corregir el estado de compra (no se cambia nada automáticamente).');
                }
            }
        }
        if ($malInstalacion) {
            sort($malInstalacion);
            $out[] = self::hallazgo('msr_instalacion:' . md5(implode(',', $malInstalacion)), 'msr', 'baja', count($malInstalacion) . ' material(es) con la instalación del MSR desfasada', 'La fecha de instalación de su renglón del MSR no es el inicio de la primera actividad que usa el material: '
                . implode('; ', array_map(fn($i) => self::corto($mats[$i]['nombre'], 35), array_slice($malInstalacion, 0, 4))) . (count($malInstalacion) > 4 ? '…' : '.'), array_slice($malInstalacion, 0, 80), [], 'Recalcular las fechas de instalación del MSR con el programa de obra.', 'sincronizar_instalacion');
        }
        $sevFalta = ['ficha técnica (PDF)' => 'media', 'submittal' => 'media', 'proveedor en el MSR' => 'media', 'fechas programadas de compra/entrega' => 'media'];
        foreach ($faltan as $campo => $ids) {
            sort($ids);
            $out[] = self::hallazgo("incompleto:$campo:" . md5(implode(',', $ids)), 'incompleto', $sevFalta[$campo] ?? 'baja', count($ids) . ' material(es) sin ' . $campo, 'Falta ' . $campo . ' en: ' . implode('; ', array_map(fn($i) => self::corto($mats[$i]['nombre'], 35), array_slice($ids, 0, 5))) . (count($ids) > 5 ? '… y ' . (count($ids) - 5) . ' más.' : '.'),
                array_slice($ids, 0, 80), [], 'Completar este dato en el catálogo / MSR (no se cambia nada automáticamente).');
        }
        if ($sinEstructura) {
            $out[] = self::hallazgo('nombres_sin_estructura', 'nombre', 'baja', count($sinEstructura) . ' material(es) sin marca ni modelo separados', 'Estos materiales tienen todo el texto en el nombre base y no usan la estructura nombre + marca + modelo/tipo + valor extra: '
                . implode('; ', array_map(fn($i) => self::corto($mats[$i]['nombre'], 40), array_slice($sinEstructura, 0, 4))) . (count($sinEstructura) > 4 ? '…' : '') . '.', array_slice($sinEstructura, 0, 40), [],
                'Usa el análisis con IA en la app de escritorio: propone el nombre estructurado de cada material y tú aceptas o rechazas cada uno.');
        }
        foreach ($vistos as $ids) if (count($ids) > 1) {
            sort($ids);
            $out[] = self::hallazgo('dup_material:' . implode(',', $ids), 'duplicados', 'media', 'Material repetido: ' . self::corto($mats[$ids[0]]['nombre'], 60), 'Hay ' . count($ids) . ' materiales con el mismo nombre y referencia.', $ids, [], 'Revisar si es un duplicado y unificarlos (no se cambia nada automáticamente).');
        }

        // submittals
        $conMateriales = [];
        foreach ($subDe as $mid => $lista) foreach ($lista as $sid) $conMateriales[$sid][] = $mid;
        foreach ($subs as $sid => $s) {
            $n = $s['numero_submittal'] ?: ('#' . $sid);
            $mids = $conMateriales[$sid] ?? [];
            if ($s['estatus'] === 'enviado' && $s['fecha']) {
                $dias = (int)floor((strtotime($hoy) - strtotime($s['fecha'])) / 86400);
                if ($dias >= self::DIAS_SUBMITTAL) {
                    $out[] = self::hallazgo("sub_pendiente:$sid:" . intdiv($dias, 15), 'submittals', $dias >= 45 ? 'alta' : ($dias >= 30 ? 'media' : 'baja'), "Submittal $n sin respuesta hace $dias días", "Se envió el {$s['fecha']} y sigue en revisión; afecta " . count($mids) . ' material(es).', array_slice($mids, 0, 30), [$sid],
                        'Dar seguimiento con el cliente; sin su aprobación no se pueden comprar los materiales (no se cambia nada automáticamente).');
                }
            } elseif ($s['estatus'] === 'rechazado') {
                $out[] = self::hallazgo("sub_rechazado:$sid", 'submittals', 'alta', "Submittal $n rechazado (hay que reenviarlo)", 'Fue rechazado y sus ' . count($mids) . ' material(es) no pueden comprarse hasta resubmitar.', array_slice($mids, 0, 30), [$sid],
                    'Preparar y reenviar el submittal (no se cambia nada automáticamente).');
            }
            if (!$mids) {
                $out[] = self::hallazgo("sub_vacio:$sid", 'submittals', 'baja', "Submittal $n sin materiales", 'El paquete no tiene materiales asociados.', [], [$sid], 'Asociarle sus materiales o eliminarlo si sobra (no se cambia nada automáticamente).');
            }
            // estatus del MSR distinto al del submittal
            $esperado = \Model\Submittal::STATUS_MSR[$s['estatus']] ?? null;
            $malos = self::filas("SELECT COUNT(*) n FROM msr_items WHERE submittal_id = $sid AND status <> '" . self::db()->real_escape_string((string)$esperado) . "'")[0]['n'] ?? 0;
            if ($esperado && $malos > 0) {
                $out[] = self::hallazgo("msr_status:$sid:$esperado:$malos", 'msr', 'media', "Estatus del MSR distinto al submittal $n", "$malos renglón(es) del MSR no tienen el estatus «{$esperado}» que corresponde al submittal ({$s['estatus']}).", array_slice($mids, 0, 30), [$sid],
                    "Poner el estatus «{$esperado}» en todos los renglones de este submittal.", 'sincronizar_msr_submittal', ['submittal_id' => $sid]);
            }
        }
        // renglones con envío requerido vencido
        foreach (self::filas("SELECT i.id, i.material_id, i.fecha_envio_requerida AS req, i.submittal_id FROM msr_items i WHERE i.proyecto_id = $pid AND i.fecha_envio_requerida IS NOT NULL AND i.fecha_envio_requerida < '$hoy' AND i.fecha_envio_real IS NULL AND i.status = 'P'") as $f) {
            $out[] = self::hallazgo("envio_vencido:{$f['id']}:{$f['req']}", 'submittals', 'media', 'Envío de submittal vencido: ' . ($mats[(int)$f['material_id']] ? self::corto($mats[(int)$f['material_id']]['nombre'], 60) : 'material'), "Debía enviarse el {$f['req']} y no tiene fecha de envío real.", [(int)$f['material_id']], $f['submittal_id'] ? [(int)$f['submittal_id']] : [],
                'Enviar el submittal o reprogramar la fecha requerida (no se cambia nada automáticamente).');
        }
        usort($out, fn($x, $y) => self::SEVERIDADES[$y['severidad']] <=> self::SEVERIDADES[$x['severidad']]);
        return $out;
    }

    // ---------- fase 2: IA ----------

    private static function instrucciones(): string
    {
        return <<<'TXT'
Eres un especialista en control de materiales de obra (MSR, submittals y catálogo). Revisa CADA material de la lista, uno por uno, y haz dos cosas:

1) PROPONER EL NOMBRE ESTRUCTURADO. Cada material debe tener el nombre completo = nombre_base + marca + modelo/tipo + valor_extra:
   - "nombre_base": qué ES el material, genérico y en español, sin marca ni modelo (ej. «Cámara multisensor», «Cable de seguridad riser CMR», «Soporte para cámara»). Primera letra mayúscula, sin typos.
   - "marca": fabricante (ej. AVIGILON, Honeywell, Lenel). Vacío si no aparece.
   - "modelo": modelo, serie o tipo exacto (ej. 24C-H5A-3MH, LNL-X4420). Vacío si no aparece.
   - "valor_extra": medida, capacidad, calibre, resolución o característica distintiva (ej. 8.0 MP, 2-18 AWG). Vacío si no aplica.
   NO inventes ni pierdas información: todo lo que dice el nombre actual debe quedar repartido en esas 4 partes (puedes corregir typos evidentes, como «AVIGILONN»→«AVIGILON»). Si el material YA cumple la estructura, no lo reportes. Una propuesta por material, con accion "renombrar_material" y params {material_id, nombre_base, marca, modelo, valor_extra}.

2) DETECTAR INCONSISTENCIAS Y DATOS INCOMPLETOS entre el material, su submittal y su renglón del MSR que NO sean cálculos simples (esos ya los detectó el sistema): materiales parecidos con nombres distintos, mismo modelo con marca distinta, especialidad que no corresponde al tipo de material, proveedor que no corresponde a la marca, cantidad/unidad absurdas, submittal que agrupa materiales que no tienen relación, etc. Usa accion "nota" para esto.

Reglas estrictas:
- Usa SOLO ids de la lista (en "materiales" y "submittals"). Todo hallazgo debe nombrar al menos un material. Máximo 30 hallazgos, los más importantes primero (los renombrados cuentan).
- "titulo" breve (máx. 100 caracteres; para renombrar: «Renombrar: <nombre actual corto>»); "descripcion" cita el dato que lo sustenta (máx. 300 caracteres; para renombrar: «Nuevo: <nombre completo propuesto>»).
- "categoria": nombre, msr, submittals, incompleto, fechas, duplicados, vinculos u otro. "severidad": baja, media, alta o critica (los renombrados son «baja»).
- "solucion": {"texto": qué propones (máx. 200 caracteres), "accion": "renombrar_material" o "nota", "params": {...}}.

Formato (JSON estricto):
{"hallazgos":[{"categoria":"nombre","severidad":"baja","titulo":"","descripcion":"","materiales":[0],"submittals":[],"solucion":{"texto":"","accion":"renombrar_material","params":{"material_id":0,"nombre_base":"","marca":"","modelo":"","valor_extra":""}}}]}
TXT;
    }

    /** Un lote de la IA por cada 25 materiales: una línea por material con lo que sabe el catálogo, el submittal y el MSR */
    public static function lotes(Proyecto $p, array $sistema): array
    {
        $pid = (int)$p->id;
        $mats = self::filas("SELECT * FROM materiales WHERE proyecto_id = $pid ORDER BY especialidad, nombre");
        $subs = [];
        foreach (self::filas("SELECT * FROM submittals WHERE proyecto_id = $pid") as $s) $subs[(int)$s['id']] = $s;
        $subDe = [];
        foreach (self::filas("SELECT sm.material_id, sm.submittal_id FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE s.proyecto_id = $pid") as $f) $subDe[(int)$f['material_id']][] = (int)$f['submittal_id'];
        $items = [];
        foreach (self::filas("SELECT * FROM msr_items WHERE proyecto_id = $pid ORDER BY id") as $i) $items[(int)$i['material_id']][] = $i;
        $uso = [];
        foreach (self::filas("SELECT am.material_id, COUNT(*) n, MIN(a.fecha_inicio) ini FROM actividad_materiales am JOIN actividades a ON a.id = am.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid GROUP BY am.material_id") as $f) $uso[(int)$f['material_id']] = $f;
        $ya = [];
        foreach ($sistema as $h) foreach ($h['materiales'] as $mid) $ya[$mid][] = $h['titulo'];

        $lotes = [];
        $trozos = array_chunk($mats, self::LOTE);
        foreach ($trozos as $k => $trozo) {
            $lineas = [];
            $subIds = [];
            foreach ($trozo as $m) {
                $id = (int)$m['id'];
                $mi = $items[$id][0] ?? null;
                foreach ($subDe[$id] ?? [] as $sid) $subIds[$sid] = true;
                $lineas[] = $id . '|ref:' . self::corto($m['referencia'], 12) . '|nombre:' . self::corto($m['nombre'], 110) . '|base:' . (($m['nombre_base'] ?? $m['nombre']) === $m['nombre'] ? '=' : self::corto((string)$m['nombre_base'], 60)) . '|marca:' . self::corto($m['marca'], 25) . '|modelo:' . self::corto($m['modelo'], 30)
                    . '|extra:' . self::corto($m['valor_extra'], 25) . '|esp:' . self::corto($m['especialidad'], 20) . '|cant:' . ($m['cantidad'] !== null ? rtrim(rtrim($m['cantidad'], '0'), '.') : '') . ' ' . self::corto($m['unidad'], 8)
                    . '|compra:' . $m['compra'] . '|entrega:' . ($m['fecha_entrega_real'] ? 'real ' . $m['fecha_entrega_real'] : ($m['fecha_entrega_prog'] ?: '-')) . '|sub:' . (implode(',', $subDe[$id] ?? []) ?: '-')
                    . '|msr:' . ($mi ? (self::corto($mi['proveedor'], 20) ?: 'sin proveedor') . ' st' . $mi['status'] : 'no está') . '|act:' . (int)($uso[$id]['n'] ?? 0) . ($ya[$id] ?? [] ? '|ya:' . self::corto(implode('; ', array_slice($ya[$id], 0, 2)), 90) : '');
            }
            $sl = [];
            foreach (array_keys($subIds) as $sid) {
                $s = $subs[$sid] ?? null;
                if ($s) $sl[] = $sid . '|' . ($s['numero_submittal'] ?: '-') . '|' . $s['estatus'] . '|' . $s['fecha'] . '|' . self::corto($s['departamento'], 25);
            }
            $desde = $k * self::LOTE + 1;
            $hasta = $desde + count($trozo) - 1;
            $lotes[] = ['titulo' => "Materiales {$desde} a {$hasta} de " . count($mats), 'ids' => array_map(fn($m) => (int)$m['id'], $trozo), 'prompt' => self::instrucciones() . Plantillas::contexto($p)
                . "\n\n## MATERIALES A REVISAR (id|ref|nombre actual|base|marca|modelo|extra|especialidad|cantidad|compra|entrega|submittals|MSR|actividades que lo usan|ya detectado)\nHoy: " . date('Y-m-d') . "\n" . implode("\n", $lineas)
                . "\n\n## SUBMITTALS DE ESTOS MATERIALES (id|número|estatus|fecha de envío|departamento)\n" . ($sl ? implode("\n", $sl) : '(ninguno)') . "\n\nResponde SOLO con el JSON."];
        }
        return $lotes;
    }

    public static function normalizarIA(Proyecto $p, array $respuestas): array
    {
        $pid = (int)$p->id;
        $mats = [];
        foreach (self::filas("SELECT * FROM materiales WHERE proyecto_id = $pid") as $m) $mats[(int)$m['id']] = $m;
        $subs = array_column(self::filas("SELECT id FROM submittals WHERE proyecto_id = $pid"), 'id', 'id');
        $out = [];
        foreach ($respuestas as $r) {
            foreach ((array)($r['hallazgos'] ?? []) as $h) {
                if (!is_array($h)) continue;
                $titulo = self::corto($h['titulo'] ?? '', 250);
                $desc = self::corto($h['descripcion'] ?? '', 500);
                $mids = array_values(array_unique(array_filter(array_map('intval', (array)($h['materiales'] ?? [])), fn($i) => isset($mats[$i]))));
                $sids = array_values(array_unique(array_filter(array_map('intval', (array)($h['submittals'] ?? [])), fn($i) => isset($subs[$i]))));
                if ($titulo === '' || $desc === '' || !$mids) continue;
                $cat = isset(self::CATEGORIAS[$h['categoria'] ?? '']) ? $h['categoria'] : 'otro';
                $sev = isset(self::SEVERIDADES[$h['severidad'] ?? '']) ? $h['severidad'] : 'media';
                $s = is_array($h['solucion'] ?? null) ? $h['solucion'] : [];
                $accion = ($s['accion'] ?? '') === 'renombrar_material' ? 'renombrar_material' : 'nota';   // la IA solo puede renombrar o dejar notas
                $texto = self::corto($s['texto'] ?? '', 300) ?: 'Revisar y corregir a mano.';
                $params = [];
                if ($accion === 'renombrar_material') {
                    $mid = (int)($s['params']['material_id'] ?? ($mids[0] ?? 0));
                    $q = is_array($s['params'] ?? null) ? $s['params'] : [];
                    $nuevo = ['nombre_base' => self::corto($q['nombre_base'] ?? '', 200), 'marca' => self::corto($q['marca'] ?? '', 100), 'modelo' => self::corto($q['modelo'] ?? '', 100), 'valor_extra' => self::corto($q['valor_extra'] ?? '', 150)];
                    $compuesto = Material::componerNombre(...array_values($nuevo));
                    if (!isset($mats[$mid]) || $nuevo['nombre_base'] === '' || mb_strlen($compuesto) > 255 || !self::conservaInformacion($mats[$mid]['nombre'], $compuesto)) {
                        $accion = 'nota';
                        $texto .= ' (la propuesta de nombre no conserva la información del actual: se revisa a mano)';
                    } elseif ($compuesto === $mats[$mid]['nombre'] && self::partes($mats[$mid]) === $nuevo) {
                        continue;   // ya cumplía
                    } else {
                        $params = ['material_id' => $mid] + $nuevo;
                        $mids = [$mid];
                        $desc = 'Nuevo nombre completo: «' . $compuesto . '». Actual: «' . self::corto($mats[$mid]['nombre'], 120) . '».';
                    }
                }
                $out[] = ['huella' => sha1('i:' . $cat . '|' . implode(',', $mids) . '|' . $accion . '|' . json_encode($params) . ($accion === 'nota' ? '|' . mb_strtolower(preg_replace('/\W+/u', '', $titulo)) : '')), 'categoria' => $accion === 'renombrar_material' ? 'nombre' : $cat, 'severidad' => $sev,
                    'origen' => 'ia', 'titulo' => $titulo, 'descripcion' => $desc, 'materiales' => $mids, 'submittals' => $sids, 'solucion' => ['texto' => $texto, 'accion' => $accion, 'params' => $params]];
            }
        }
        return $out;
    }

    /** Parámetros de una acción saneados, o null si no son válidos / no son del proyecto */
    private static function paramsValidos(int $pid, string $accion, array $p): ?array
    {
        $en = fn(string $sql) => (bool)(self::db()->query($sql)?->num_rows);
        switch ($accion) {
            case 'nota':
            case 'sincronizar_instalacion':
                return [];
            case 'renombrar_material':
                $id = (int)($p['material_id'] ?? 0);
                $n = ['nombre_base' => self::corto($p['nombre_base'] ?? '', 200), 'marca' => self::corto($p['marca'] ?? '', 100), 'modelo' => self::corto($p['modelo'] ?? '', 100), 'valor_extra' => self::corto($p['valor_extra'] ?? '', 150)];
                if ($n['nombre_base'] === '' || mb_strlen(Material::componerNombre(...array_values($n))) > 255 || !$en("SELECT 1 FROM materiales WHERE id = $id AND proyecto_id = $pid")) return null;
                return ['material_id' => $id] + $n;
            case 'sincronizar_msr_material':
                $id = (int)($p['material_id'] ?? 0);
                return $en("SELECT 1 FROM materiales WHERE id = $id AND proyecto_id = $pid") ? ['material_id' => $id] : null;
            case 'sincronizar_msr_submittal':
                $id = (int)($p['submittal_id'] ?? 0);
                return $en("SELECT 1 FROM submittals WHERE id = $id AND proyecto_id = $pid") ? ['submittal_id' => $id] : null;
        }
        return null;
    }

    // ---------- persistencia ----------

    public static function guardar(Proyecto $p, array $hallazgos, string $modelo = '', array $conteo = []): array
    {
        $db = self::db();
        $pid = (int)$p->id;
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $sist = count(array_filter($hallazgos, fn($h) => $h['origen'] === 'sistema'));
        $db->query("INSERT INTO analisis_materiales (proyecto_id, modelo, del_sistema, de_la_ia) VALUES ($pid, '" . $esc(self::corto($modelo, 150)) . "', " . (int)($conteo['del_sistema'] ?? $sist) . ', ' . (int)($conteo['de_la_ia'] ?? count($hallazgos) - $sist) . ')');
        $corrida = (int)$db->insert_id;
        \Classes\Revisiones::registrarEjecucion($pid, 'materiales');
        $resueltas = [];
        foreach (self::filas("SELECT huella FROM analisis_materiales_hallazgos WHERE proyecto_id = $pid AND estado <> 'pendiente'") as $f) $resueltas[$f['huella']] = true;
        $nuevos = $ya = 0;
        $vistos = [];
        foreach ($hallazgos as $h) {
            if (isset($vistos[$h['huella']])) continue;
            $vistos[$h['huella']] = true;
            if (isset($resueltas[$h['huella']])) { $ya++; continue; }
            $h['solucion']['esperado'] = self::esperado($pid, $h['solucion']['accion'], $h['solucion']['params']);
            $db->query("INSERT INTO analisis_materiales_hallazgos (proyecto_id, corrida_id, huella, categoria, severidad, origen, titulo, descripcion, materiales_json, submittals_json, solucion_json, modelo) VALUES ($pid, $corrida, '{$esc($h['huella'])}', '{$esc($h['categoria'])}', '{$esc($h['severidad'])}', '{$esc($h['origen'])}', '{$esc($h['titulo'])}', '{$esc($h['descripcion'])}', '"
                . $esc(json_encode($h['materiales'])) . "', '" . $esc(json_encode($h['submittals'])) . "', '" . $esc(json_encode($h['solucion'], JSON_UNESCAPED_UNICODE)) . "', '" . $esc(self::corto($h['origen'] === 'ia' ? $modelo : '', 150)) . "')");
            $nuevos++;
        }
        $db->query("DELETE FROM analisis_materiales WHERE proyecto_id = $pid AND guardada = 0 AND id NOT IN (SELECT id FROM (SELECT id FROM analisis_materiales WHERE proyecto_id = $pid AND guardada = 0 ORDER BY id DESC LIMIT " . self::CORRIDAS_SIN_GUARDAR . ') t)');
        return ['corrida' => $corrida, 'nuevos' => $nuevos, 'ya_resueltos' => $ya];
    }

    /** Estado de lo que la solución toca al detectar: si cambia antes de aceptar, la solución ya no se aplica */
    private static function esperado(int $pid, string $accion, array $params): ?array
    {
        if ($accion !== 'renombrar_material') return null;
        $m = self::filas('SELECT nombre, nombre_base, marca, modelo, valor_extra FROM materiales WHERE id = ' . (int)$params['material_id'] . " AND proyecto_id = $pid")[0] ?? null;
        return $m ?: null;
    }

    /**
     * Guarda el registro de cada lote de la IA ({titulo, ids, estado, error, respuesta}): materiales cubiertos, hallazgos validados y
     * respuesta cruda, para consultarlo después aunque el lote no haya generado hallazgos o haya fallado.
     */
    public static function guardarLotes(Proyecto $p, int $corrida, array $lotes): int
    {
        $db = self::db();
        $pid = (int)$p->id;
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $nombres = [];
        foreach (self::filas("SELECT id, nombre FROM materiales WHERE proyecto_id = $pid") as $m) $nombres[(int)$m['id']] = $m['nombre'];
        $n = 0;
        foreach (array_values($lotes) as $orden => $l) {
            if (!is_array($l)) continue;
            $ok = ($l['estado'] ?? 'ok') !== 'error';
            $ids = array_values(array_filter(array_map('intval', (array)($l['ids'] ?? [])), fn($i) => isset($nombres[$i])));
            $mats = array_map(fn($i) => ['id' => $i, 'nombre' => $nombres[$i]], $ids);
            $hallazgos = [];
            if ($ok && is_array($l['respuesta'] ?? null)) {
                foreach (self::normalizarIA($p, [$l['respuesta']]) as $h) $hallazgos[] = ['titulo' => $h['titulo'], 'severidad' => $h['severidad'], 'categoria' => $h['categoria'], 'accion' => $h['solucion']['accion'], 'materiales' => $h['materiales']];
            }
            $resp = $ok && isset($l['respuesta']) ? mb_substr(json_encode($l['respuesta'], JSON_UNESCAPED_UNICODE), 0, 60000) : '';
            $db->query("INSERT INTO analisis_materiales_lotes (proyecto_id, corrida_id, orden, titulo, estado, error, materiales_json, hallazgos_json, respuesta_json) VALUES ($pid, $corrida, " . ($orden + 1) . ", '" . $esc(self::corto($l['titulo'] ?? 'Lote ' . ($orden + 1), 150)) . "', '" . ($ok ? 'ok' : 'error') . "', '" . $esc(self::corto($l['error'] ?? '', 500)) . "', '"
                . $esc(json_encode($mats, JSON_UNESCAPED_UNICODE)) . "', '" . $esc(json_encode($hallazgos, JSON_UNESCAPED_UNICODE)) . "', '" . $esc($resp) . "')");
            $n++;
        }
        return $n;
    }

    /** Registro por lote de un análisis (sin la respuesta cruda) */
    public static function lotesDeCorrida(int $pid, int $corrida): array
    {
        $out = [];
        foreach (self::filas("SELECT id, orden, titulo, estado, error, materiales_json, hallazgos_json FROM analisis_materiales_lotes WHERE proyecto_id = $pid AND corrida_id = $corrida ORDER BY orden") as $f) {
            $f['materiales'] = json_decode((string)$f['materiales_json'], true) ?: [];
            $f['hallazgos'] = json_decode((string)$f['hallazgos_json'], true) ?: [];
            unset($f['materiales_json'], $f['hallazgos_json']);
            $out[] = $f;
        }
        return $out;
    }

    public static function corridas(int $pid): array
    {
        return self::filas("SELECT c.id, c.nombre, c.guardada, c.modelo, c.del_sistema, c.de_la_ia, c.creado,
                   SUM(i.estado = 'pendiente') AS pendientes, SUM(i.estado = 'aceptada') AS aceptadas, SUM(i.estado = 'rechazada') AS rechazadas, COUNT(i.id) AS total
            FROM analisis_materiales c LEFT JOIN analisis_materiales_hallazgos i ON i.corrida_id = c.id WHERE c.proyecto_id = $pid GROUP BY c.id ORDER BY c.id DESC");
    }

    public static function marcarCorrida(int $pid, int $id, bool $guardar, string $nombre = ''): void
    {
        $db = self::db();
        $n = $db->real_escape_string(self::corto($nombre, 150));
        $db->query('UPDATE analisis_materiales SET guardada = ' . ($guardar ? 1 : 0) . ", nombre = '" . ($guardar ? $n : '') . "' WHERE id = $id AND proyecto_id = $pid");
    }

    public static function eliminarCorrida(int $pid, int $id): void
    {
        self::db()->query("DELETE FROM analisis_materiales WHERE id = $id AND proyecto_id = $pid");
    }

    public static function listar(int $pid, array $f = []): array
    {
        $w = ["proyecto_id = $pid"];
        if (!empty($f['corrida'])) $w[] = 'corrida_id = ' . (int)$f['corrida'];
        if (in_array($f['estado'] ?? '', ['pendiente', 'aceptada', 'rechazada'], true)) $w[] = "estado = '{$f['estado']}'";
        if (isset(self::SEVERIDADES[$f['severidad'] ?? ''])) $w[] = "severidad = '{$f['severidad']}'";
        if (isset(self::CATEGORIAS[$f['categoria'] ?? ''])) $w[] = "categoria = '{$f['categoria']}'";
        if (in_array($f['origen'] ?? '', ['sistema', 'ia'], true)) $w[] = "origen = '{$f['origen']}'";
        $filas = self::filas('SELECT * FROM analisis_materiales_hallazgos WHERE ' . implode(' AND ', $w) . " ORDER BY FIELD(estado,'pendiente','aceptada','rechazada'), FIELD(severidad,'critica','alta','media','baja'), categoria, id");
        $mats = array_column(self::filas("SELECT id, nombre FROM materiales WHERE proyecto_id = $pid"), 'nombre', 'id');
        $subs = array_column(self::filas("SELECT id, COALESCE(NULLIF(numero_submittal,''), CONCAT('#', id)) n FROM submittals WHERE proyecto_id = $pid"), 'n', 'id');
        foreach ($filas as &$x) {
            $x['id'] = (int)$x['id'];
            $x['materiales'] = array_values(array_filter(array_map(fn($i) => isset($mats[$i]) ? ['id' => $i, 'nombre' => $mats[$i]] : null, (array)json_decode((string)$x['materiales_json'], true))));
            $x['submittals'] = array_values(array_filter(array_map(fn($i) => isset($subs[$i]) ? ['id' => $i, 'numero' => $subs[$i]] : null, (array)json_decode((string)$x['submittals_json'], true))));
            $x['solucion'] = json_decode($x['solucion_json'], true) ?: ['texto' => '', 'accion' => 'nota', 'params' => []];
            $x['revertible'] = $x['estado'] === 'aceptada' && $x['antes_json'] !== null;
        }
        return $filas;
    }

    public static function conteos(int $pid, int $corrida = 0): array
    {
        $c = ['pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0, 'criticas' => 0];
        foreach (self::filas("SELECT estado, severidad, COUNT(*) n FROM analisis_materiales_hallazgos WHERE proyecto_id = $pid" . ($corrida ? " AND corrida_id = $corrida" : '') . ' GROUP BY estado, severidad') as $f) {
            $c[$f['estado']] += (int)$f['n'];
            if ($f['estado'] === 'pendiente' && in_array($f['severidad'], ['alta', 'critica'], true)) $c['criticas'] += (int)$f['n'];
        }
        return $c;
    }

    private static function obtener(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM analisis_materiales_hallazgos WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    private static function cerrar(int $pid, int $id, string $estado, ?array $antes): void
    {
        $db = self::db();
        $a = $antes === null ? 'NULL' : "'" . $db->real_escape_string(json_encode($antes, JSON_UNESCAPED_UNICODE)) . "'";
        $quien = $db->real_escape_string(usuario_actual()['nombre'] ?? '');
        $db->query("UPDATE analisis_materiales_hallazgos SET estado = '$estado', antes_json = $a, resuelto_por = '$quien', resuelto_en = NOW() WHERE id = $id AND proyecto_id = $pid");
    }

    public static function rechazar(int $pid, int $id): array
    {
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] !== 'pendiente') return [false, 'El hallazgo ya no está pendiente'];
        self::cerrar($pid, $id, 'rechazada', null);
        $sol = json_decode($h['solucion_json'], true) ?: [];
        Auditoria::registrar($pid, 'materiales', 'rechazado', (string)($sol['accion'] ?? 'nota'), 'Rechazado: ' . $h['titulo'], null, (array)($sol['params'] ?? []), $id);
        return [true, 'Rechazado: no se volverá a mostrar'];
    }

    public static function reabrir(Proyecto $p, int $id): array
    {
        $pid = (int)$p->id;
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] === 'pendiente') return [false, 'No hay nada que reabrir'];
        $revierte = $h['estado'] === 'aceptada' && $h['antes_json'] !== null;
        if ($revierte) {
            [$ok, $msg] = self::restaurar($pid, json_decode($h['antes_json'], true) ?: []);
            if (!$ok) return [false, $msg];
        }
        self::db()->query("UPDATE analisis_materiales_hallazgos SET estado = 'pendiente', antes_json = NULL, resuelto_por = '', resuelto_en = NULL WHERE id = $id AND proyecto_id = $pid");
        $sol = json_decode($h['solucion_json'], true) ?: [];
        $revirtio = $h['estado'] === 'aceptada' && $h['antes_json'] !== null;
        Auditoria::registrar($pid, 'materiales', $revirtio ? 'revertido' : 'reabierto', (string)($sol['accion'] ?? 'nota'), ($revirtio ? 'Cambio revertido: ' : 'Vuelve a pendiente: ') . $h['titulo'], $revirtio ? json_decode($h['antes_json'], true) : null, null, $id, '', Auditoria::entidad($pid, (string)($sol['accion'] ?? 'nota'), (array)($sol['params'] ?? [])));
        return [true, $revierte ? 'Cambio revertido; vuelve a estar pendiente' : 'Vuelve a estar pendiente'];
    }

    public static function aceptar(Proyecto $p, int $id): array
    {
        $pid = (int)$p->id;
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] !== 'pendiente') return [false, 'El hallazgo ya no está pendiente'];
        $s = json_decode($h['solucion_json'], true) ?: [];
        $accion = $s['accion'] ?? 'nota';
        $params = self::paramsValidos($pid, $accion, (array)($s['params'] ?? []));
        if ($params === null || !isset(self::ACCIONES[$accion])) return [false, 'La solución ya no es aplicable (los datos cambiaron). Rechaza o resuelve a mano.'];
        if (!empty($s['esperado']) && self::esperado($pid, $accion, $params) !== $s['esperado']) return [false, 'El material cambió desde que se hizo este análisis, así que la solución ya no aplica. Vuelve a analizar o resuélvelo a mano.'];
        $db = self::db();
        $db->begin_transaction();
        try {
            [$ok, $msg, $antes] = self::ejecutar($pid, $accion, $params);
            if (!$ok) { $db->rollback(); return [false, $msg]; }
            self::cerrar($pid, $id, 'aceptada', $antes);
            Auditoria::registrar($pid, 'materiales', 'aplicado', $accion, $msg, $antes, $params, $id, $h['origen'] === 'ia' ? (string)$h['modelo'] : '');
            $db->query('UPDATE analisis_materiales SET guardada = 1, nombre = IF(nombre = \'\', CONCAT(\'Análisis \', DATE_FORMAT(creado, \'%d/%m/%Y\')), nombre) WHERE id = ' . (int)$h['corrida_id']);   // con cambios aplicados no debe rotar
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo aplicar: ' . $e->getMessage()];
        }
        return [true, $msg];
    }

    /** @return array{0: bool, 1: string, 2: ?array} */
    private static function ejecutar(int $pid, string $accion, array $x): array
    {
        $db = self::db();
        switch ($accion) {
            case 'nota':
                return [true, 'Marcado como atendido (no se cambió ningún dato)', null];
            case 'renombrar_material':
                $m = Material::find($x['material_id']);
                if (!$m || (int)$m->proyecto_id !== $pid) return [false, 'Material no encontrado', null];
                $antes = ['accion' => $accion, 'material_id' => (int)$m->id, 'nombre' => $m->nombre, 'nombre_base' => $m->nombre_base, 'marca' => $m->marca, 'modelo' => $m->modelo, 'valor_extra' => $m->valor_extra];
                $m->nombre_base = $x['nombre_base'];
                $m->marca = $x['marca'];
                $m->modelo = $x['modelo'];
                $m->valor_extra = $x['valor_extra'];
                $m->armarNombre();
                $m->guardar();
                return [true, 'Material renombrado: «' . $m->nombre . '»', $antes];
            case 'sincronizar_msr_material':
                $m = Material::find($x['material_id']);
                if (!$m || (int)$m->proyecto_id !== $pid) return [false, 'Material no encontrado', null];
                $antes = ['accion' => $accion, 'renglones' => self::filas('SELECT id, fecha_compra_prog, fecha_compra_real, fecha_entrega_prog, fecha_entrega_real FROM msr_items WHERE material_id = ' . (int)$m->id)];
                $m->sincronizarMsr();
                return [true, 'Fechas del MSR actualizadas con las del material', $antes];
            case 'sincronizar_msr_submittal':
                $s = self::filas('SELECT estatus FROM submittals WHERE id = ' . (int)$x['submittal_id'] . " AND proyecto_id = $pid")[0] ?? null;
                if (!$s) return [false, 'Submittal no encontrado', null];
                $st = \Model\Submittal::STATUS_MSR[$s['estatus']] ?? 'P';
                $antes = ['accion' => $accion, 'renglones' => self::filas('SELECT id, status FROM msr_items WHERE submittal_id = ' . (int)$x['submittal_id'])];
                $db->query("UPDATE msr_items SET status = '$st' WHERE submittal_id = " . (int)$x['submittal_id']);
                return [true, "Estatus del MSR puesto en «{$st}»", $antes];
            case 'sincronizar_instalacion':
                $antes = ['accion' => $accion, 'renglones' => self::filas("SELECT id, fecha_instalacion_prog FROM msr_items WHERE proyecto_id = $pid")];
                \Model\ActividadMaterial::sincronizarInstalacion($pid);
                return [true, 'Fechas de instalación del MSR recalculadas', $antes];
        }
        return [false, 'Acción no permitida', null];
    }

    private static function restaurar(int $pid, array $a): array
    {
        $db = self::db();
        $f = fn($v) => $v === null || $v === '' ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'";
        switch ($a['accion'] ?? '') {
            case 'renombrar_material':
                $db->query("UPDATE materiales SET nombre = {$f($a['nombre'])}, nombre_base = {$f($a['nombre_base'])}, marca = '" . $db->real_escape_string((string)$a['marca']) . "', modelo = '" . $db->real_escape_string((string)$a['modelo']) . "', valor_extra = '" . $db->real_escape_string((string)$a['valor_extra']) . "' WHERE id = " . (int)$a['material_id'] . " AND proyecto_id = $pid");
                return [true, ''];
            case 'sincronizar_msr_material':
                foreach ($a['renglones'] as $r) $db->query("UPDATE msr_items SET fecha_compra_prog = {$f($r['fecha_compra_prog'])}, fecha_compra_real = {$f($r['fecha_compra_real'])}, fecha_entrega_prog = {$f($r['fecha_entrega_prog'])}, fecha_entrega_real = {$f($r['fecha_entrega_real'])} WHERE id = " . (int)$r['id'] . " AND proyecto_id = $pid");
                return [true, ''];
            case 'sincronizar_msr_submittal':
                foreach ($a['renglones'] as $r) $db->query("UPDATE msr_items SET status = '" . $db->real_escape_string($r['status']) . "' WHERE id = " . (int)$r['id'] . " AND proyecto_id = $pid");
                return [true, ''];
            case 'sincronizar_instalacion':
                foreach ($a['renglones'] as $r) $db->query("UPDATE msr_items SET fecha_instalacion_prog = {$f($r['fecha_instalacion_prog'])} WHERE id = " . (int)$r['id'] . " AND proyecto_id = $pid");
                return [true, ''];
        }
        return [false, 'No se puede revertir esta acción'];
    }
}
