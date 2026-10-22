<?php

namespace Classes;

/**
 * Pruebas de calidad de la IA (Fase 5.3): una batería de casos FIJOS (mismo texto, misma respuesta esperada) que se corre con cada
 * modelo para compararlos y para detectar si un modelo o un cambio de prompt empeora. Los casos se parecen a lo que la plataforma
 * le pide de verdad a la IA (extraer, clasificar, redactar sin inventar cifras, razonar, contestar en JSON) pero son autocontenidos:
 * no dependen de ningún proyecto. El servidor califica sin IA con reglas deterministas (`puntuar`), así que la nota es reproducible.
 *
 * Cada caso: id, suite, nombre, prompt (pide JSON, como el resto de herramientas) y reglas. Regla = [tipo, campo, …argumentos]:
 *   igual · en · contiene · lista(min,max) · suma(campo,total) · alguno_igual(campo,valor) · numero(valor,tolerancia) · conjunto([…]) · orden([…])
 *   largo_min · largo_max · sin_numeros_extra([permitidos]) · espanol
 * La nota de un caso es la fracción de reglas cumplidas; el caso «aprueba» con todas. Si cambias un caso o una regla, sube VERSION:
 * las corridas de versiones distintas no son comparables y la pantalla lo avisa.
 */
class PruebasIA
{
    /** Súbela al cambiar casos o reglas */
    public const VERSION = 1;

    public const SUITES = [
        'extraccion' => 'Extraer datos de un texto',
        'clasificacion' => 'Clasificar y decidir',
        'redaccion' => 'Redactar sin inventar',
        'razonamiento' => 'Razonar con números y orden',
    ];

    private const FORMATO = "\n\nDevuelve SOLO un JSON con este formato: ";

    /** @return list<array{id:string,suite:string,nombre:string,prompt:string,reglas:list<array>}> */
    public static function casos(): array
    {
        $f = self::FORMATO;
        return [
            ['id' => 'ext-fecha', 'suite' => 'extraccion', 'nombre' => 'Fecha dicha en español',
                'prompt' => "Extrae la fecha del siguiente texto de la bitácora de obra: «El colado de la losa del nivel 3 se realizó el 12 de marzo de 2026 por la mañana.»$f{\"fecha\":\"AAAA-MM-DD\"}",
                'reglas' => [['igual', 'fecha', '2026-03-12']]],
            ['id' => 'ext-cantidades', 'suite' => 'extraccion', 'nombre' => 'Cantidades de un dictado',
                'prompt' => "Del siguiente dictado extrae cada material con su cantidad (número) y su unidad: «Se recibieron 48 cámaras IP, 6 gabinetes de pared y 350 metros de cable UTP categoría 6.»$f{\"materiales\":[{\"nombre\":\"texto\",\"cantidad\":0,\"unidad\":\"texto\"}]}",
                'reglas' => [['lista', 'materiales', 3, 3], ['suma', 'materiales', 'cantidad', 404], ['alguno_igual', 'materiales', 'cantidad', 350]]],
            ['id' => 'ext-responsable', 'suite' => 'extraccion', 'nombre' => 'Responsable y fecha límite de un acuerdo',
                'prompt' => "Del acuerdo de minuta «Ing. Laura Pérez entregará los planos as-built de la planta baja a más tardar el 5 de noviembre de 2026.» extrae quién y cuándo.$f{\"responsable\":\"texto\",\"fecha_limite\":\"AAAA-MM-DD\"}",
                'reglas' => [['contiene', 'responsable', 'laura'], ['igual', 'fecha_limite', '2026-11-05']]],

            ['id' => 'cla-severidad', 'suite' => 'clasificacion', 'nombre' => 'Severidad de un pendiente de punch list',
                'prompt' => "Clasifica la severidad (baja, media, alta o critica) de este pendiente de calidad: «Las varillas de la columna C-4 quedaron con menos recubrimiento del especificado y ya se coló el nivel superior encima.»$f{\"severidad\":\"baja|media|alta|critica\",\"motivo\":\"una frase\"}",
                'reglas' => [['en', 'severidad', ['alta', 'critica']], ['largo_max', 'motivo', 300]]],
            ['id' => 'cla-restriccion', 'suite' => 'clasificacion', 'nombre' => 'Tipo de restricción',
                'prompt' => "Clasifica la restricción en una categoría (material, mano_de_obra, permiso, diseno, clima u otro): «Falta la aprobación del municipio para cerrar media calle y subir la grúa.»$f{\"categoria\":\"material|mano_de_obra|permiso|diseno|clima|otro\"}",
                'reglas' => [['igual', 'categoria', 'permiso']]],
            ['id' => 'cla-contradice', 'suite' => 'clasificacion', 'nombre' => 'Detectar una contradicción plano vs. especificación',
                'prompt' => "Compara estas dos fuentes y di si se contradicen en el diámetro de la tubería.\nPlano IS-03: «Bajada pluvial en PVC sanitario de 4 pulgadas.»\nEspecificación 22 13 00: «Las bajadas pluviales serán de 6 pulgadas de diámetro mínimo.»$f{\"contradice\":true,\"dato_plano\":\"texto\",\"dato_especificacion\":\"texto\"}",
                'reglas' => [['igual', 'contradice', true], ['contiene', 'dato_plano', '4'], ['contiene', 'dato_especificacion', '6']]],
            ['id' => 'cla-no-contradice', 'suite' => 'clasificacion', 'nombre' => 'No ver contradicciones donde no las hay',
                'prompt' => "Compara estas dos fuentes y di si se contradicen en el espesor de la losa.\nPlano E-02: «Losa de concreto de 12 cm de espesor.»\nEspecificación 03 30 00: «La losa tendrá un espesor de 12 centímetros.»$f{\"contradice\":false,\"explicacion\":\"una frase\"}",
                'reglas' => [['igual', 'contradice', false]]],

            ['id' => 'red-resumen', 'suite' => 'redaccion', 'nombre' => 'Resumen sin cifras inventadas',
                'prompt' => "Escribe un resumen de dos frases para el cliente con SOLO estos datos: avance real 45 %, avance programado 52 %, 3 actividades atrasadas, 1 restricción activa (falta de cable UTP). No agregues ninguna otra cifra ni fecha.$f{\"resumen\":\"texto en español\"}",
                'reglas' => [['sin_numeros_extra', 'resumen', [45, 52, 3, 1, 2]], ['espanol', 'resumen'], ['contiene', 'resumen', 'cable']]],
            ['id' => 'red-rfi', 'suite' => 'redaccion', 'nombre' => 'Redactar un RFI',
                'prompt' => "Redacta una solicitud de información (RFI) formal y breve para el proyectista con este problema: en el plano A-05 el muro del eje 3 chocará con la bajada de aire acondicionado del plano M-02 a la altura de 2.40 m. Pide que confirmen cuál prevalece.$f{\"asunto\":\"máx. 100 caracteres\",\"pregunta\":\"2 a 4 frases\"}",
                'reglas' => [['largo_max', 'asunto', 100], ['largo_min', 'pregunta', 60], ['contiene', 'pregunta', 'a-05'], ['contiene', 'pregunta', 'm-02'], ['espanol', 'pregunta']]],
            ['id' => 'red-acciones', 'suite' => 'redaccion', 'nombre' => 'Lista de acciones con la longitud pedida',
                'prompt' => "El proyecto lleva 3 semanas de atraso por falta de cable UTP. Propón entre 3 y 5 acciones concretas para recuperarlo, cada una de máximo 150 caracteres.$f{\"acciones\":[\"texto\"]}",
                'reglas' => [['lista', 'acciones', 3, 5], ['cada_largo_max', 'acciones', 150], ['espanol', 'acciones']]],

            ['id' => 'raz-orden', 'suite' => 'razonamiento', 'nombre' => 'Ordenar las etapas de una instalación',
                'prompt' => "Ordena de primero a último estas etapas de una instalación de videovigilancia: pruebas y entrega, montaje de cámaras, canalización, configuración, tendido de cableado.$f{\"orden\":[\"etapa\"]}",
                'reglas' => [['lista', 'orden', 5, 5], ['orden', 'orden', ['canalizacion', 'cableado', 'montaje', 'configuracion', 'pruebas']]]],
            ['id' => 'raz-ruta', 'suite' => 'razonamiento', 'nombre' => 'Duración y ruta crítica de 4 actividades',
                'prompt' => "A dura 5 días. B depende de A y dura 3 días. C depende de A y dura 6 días. D depende de B y de C y dura 2 días. Todas empiezan en cuanto terminan sus predecesoras. ¿Cuántos días dura el conjunto y cuáles son las actividades críticas?$f{\"duracion_total\":0,\"criticas\":[\"A\"]}",
                'reglas' => [['numero', 'duracion_total', 13, 0], ['conjunto', 'criticas', ['a', 'c', 'd']]]],
            ['id' => 'raz-proyeccion', 'suite' => 'razonamiento', 'nombre' => 'Proyección lineal del avance',
                'prompt' => "Una obra de 20 semanas lleva 10 semanas con un avance real de 40 %. Si se mantiene el mismo ritmo semanal, ¿qué porcentaje de avance tendrá al terminar la semana 20?$f{\"avance_semana_20\":0,\"razon\":\"una frase\"}",
                'reglas' => [['numero', 'avance_semana_20', 80, 2]]],
        ];
    }

    public static function caso(string $id): ?array
    {
        foreach (self::casos() as $c) if ($c['id'] === $id) return $c;
        return null;
    }

    /** Casos de una suite (o todos) */
    public static function deSuite(string $suite): array
    {
        return array_values(array_filter(self::casos(), fn($c) => $suite === '' || $suite === 'todas' || $c['suite'] === $suite));
    }

    // ---------- comparación de corridas ----------

    /**
     * Corridas guardadas del proyecto (informes de la herramienta «pruebas_ia» hechos con un modelo) agrupadas por modelo.
     * 'modelos' = la corrida MÁS RECIENTE de cada modelo en la versión actual de los casos; 'historial' = todas, de la más nueva a la más vieja.
     */
    public static function comparar(int $pid): array
    {
        $db = $GLOBALS['db'];
        $r = $db->query("SELECT id, modelo, creado, contenido_json FROM informes_ia WHERE proyecto_id = $pid AND tipo = 'pruebas_ia' AND origen = 'ia' AND modelo <> '' ORDER BY id DESC LIMIT 200");
        $modelos = []; $historial = []; $otras = 0;
        foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $f) {
            $p = (json_decode($f['contenido_json'], true) ?: [])['pruebas'] ?? null;
            if (!is_array($p)) continue;
            $fila = ['id' => (int)$f['id'], 'modelo' => $f['modelo'], 'fecha' => $f['creado'], 'nota' => (int)$p['nota'], 'version' => (int)$p['version'], 'suite' => (string)($p['suite'] ?? 'todas'), 'casos' => (array)$p['casos'], 'ms_medio' => $p['ms_medio'] ?? null];
            $historial[] = $fila;
            if ($fila['version'] !== self::VERSION) { $otras++; continue; }
            $modelos[$f['modelo']] ??= $fila + ['corridas' => 0];
            $modelos[$f['modelo']]['corridas']++;
        }
        uasort($modelos, fn($a, $b) => $b['nota'] <=> $a['nota']);
        return ['modelos' => $modelos, 'historial' => $historial, 'otras_versiones' => $otras];
    }

    // ---------- calificación ----------

    private static function norm($v): string
    {
        $t = mb_strtolower(trim(is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE)));
        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    private static function numero($v): ?float
    {
        if (is_int($v) || is_float($v)) return (float)$v;
        if (is_string($v) && preg_match('/-?\d+(?:[.,]\d+)?/', $v, $m)) return (float)str_replace(',', '.', $m[0]);
        return null;
    }

    private static function texto($v): string
    {
        return is_array($v) ? implode(' ', array_map(fn($x) => is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE), $v)) : (is_scalar($v) ? (string)$v : '');
    }

    /** Evalúa una regla; devuelve null si pasa o el motivo del fallo */
    private static function regla(array $r, array $resp): ?string
    {
        [$tipo, $campo] = $r;
        $v = $resp[$campo] ?? null;
        if ($v === null || $v === '' || $v === []) return "falta «{$campo}»";
        switch ($tipo) {
            case 'igual':
                $esp = $r[2];
                if (is_bool($esp)) { $b = filter_var(is_string($v) ? self::norm($v) : $v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE); return $b === $esp ? null : "«{$campo}» debía ser " . ($esp ? 'true' : 'false'); }
                return self::norm($v) === self::norm($esp) ? null : "«{$campo}» = " . self::texto($v) . ", se esperaba $esp";
            case 'en':
                return in_array(self::norm($v), array_map([self::class, 'norm'], $r[2]), true) ? null : "«{$campo}» = " . self::texto($v) . ", se esperaba " . implode(' o ', $r[2]);
            case 'contiene':
                return str_contains(self::norm(self::texto($v)), self::norm($r[2])) ? null : "«{$campo}» no menciona «{$r[2]}»";
            case 'lista':
                if (!is_array($v) || !array_is_list($v)) return "«{$campo}» debía ser una lista";
                return count($v) >= $r[2] && count($v) <= $r[3] ? null : "«{$campo}» trae " . count($v) . " elementos (se pedían de {$r[2]} a {$r[3]})";
            case 'suma':
                if (!is_array($v)) return "«{$campo}» debía ser una lista";
                $s = 0.0; foreach ($v as $x) $s += (float)(self::numero(is_array($x) ? ($x[$r[2]] ?? null) : null) ?? 0);
                return abs($s - $r[3]) < 0.5 ? null : "la suma de «{$r[2]}» da " . $s . ", se esperaba {$r[3]}";
            case 'alguno_igual':
                foreach ((array)$v as $x) if (is_array($x) && self::numero($x[$r[2]] ?? null) == (float)$r[3]) return null;
                return "ningún elemento tiene {$r[2]} = {$r[3]}";
            case 'numero':
                $n = self::numero($v);
                return $n !== null && abs($n - $r[2]) <= $r[3] ? null : "«{$campo}» = " . self::texto($v) . ", se esperaba {$r[2]}" . ($r[3] ? " (±{$r[3]})" : '');
            case 'conjunto':
                $a = array_unique(array_map(fn($x) => self::norm($x), (array)$v)); $b = array_map([self::class, 'norm'], $r[2]);
                sort($a); sort($b);
                return $a === $b ? null : "«{$campo}» = " . self::texto($v) . ", se esperaba " . implode(', ', $r[2]);
            case 'orden':
                $pos = -1;
                foreach ($r[2] as $clave) {
                    $hallado = -1;
                    foreach (array_values((array)$v) as $i => $x) if (str_contains(self::norm($x), $clave)) { $hallado = $i; break; }
                    if ($hallado < 0 || $hallado <= $pos) return "el orden de «{$campo}» no es el correcto (falla en «{$clave}»)";
                    $pos = $hallado;
                }
                return null;
            case 'largo_max':
                return mb_strlen(self::texto($v)) <= $r[2] ? null : "«{$campo}» mide " . mb_strlen(self::texto($v)) . " (máx. {$r[2]})";
            case 'largo_min':
                return mb_strlen(self::texto($v)) >= $r[2] ? null : "«{$campo}» es demasiado corto";
            case 'cada_largo_max':
                foreach ((array)$v as $x) if (mb_strlen(self::texto($x)) > $r[2]) return "un elemento de «{$campo}» pasa de {$r[2]} caracteres";
                return null;
            case 'sin_numeros_extra':
                preg_match_all('/\d+(?:[.,]\d+)?/', self::texto($v), $m);
                foreach ($m[0] as $n) if (!in_array((float)str_replace(',', '.', $n), array_map('floatval', $r[2]), true)) return "«{$campo}» inventa la cifra $n";
                return null;
            case 'espanol':
                $t = ' ' . preg_replace('/[^\p{L}\s]/u', ' ', self::norm(self::texto($v))) . ' ';
                $es = preg_match_all('/\s(de|la|el|que|en|y|los|las|con|por|para|una|un|se|del|al|es|falta|hay)\s/u', $t);
                $en = preg_match_all('/\s(the|and|of|to|is|with|for|that|in|are|this|a)\s/u', $t);
                return $es >= 1 && $es > $en ? null : "«{$campo}» no está en español";
        }
        return 'regla desconocida';
    }

    /**
     * Califica la respuesta del modelo (ya decodificada) a un caso.
     * @return array{nota:float,aprobado:bool,fallas:list<string>}
     */
    public static function puntuar(array $caso, ?array $respuesta): array
    {
        $reglas = $caso['reglas'];
        if (!$respuesta) return ['nota' => 0.0, 'aprobado' => false, 'fallas' => ['sin respuesta válida (no devolvió el JSON pedido)']];
        $fallas = [];
        foreach ($reglas as $r) if (($m = self::regla($r, $respuesta)) !== null) $fallas[] = $m;
        $nota = round((count($reglas) - count($fallas)) / count($reglas), 3);
        return ['nota' => $nota, 'aprobado' => !$fallas, 'fallas' => $fallas];
    }
}
