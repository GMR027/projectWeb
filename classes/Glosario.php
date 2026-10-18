<?php

namespace Classes;

/** Glosario técnico español ↔ inglés de obra, instalaciones eléctricas, voz y datos y seguridad electrónica. Sirve para buscar en documentos en el otro idioma */
class Glosario
{
    /** raíz/forma en español => equivalentes en inglés */
    private const PARES = [
        'cable' => ['cable', 'wire', 'cabling'], 'cableado' => ['cabling', 'wiring', 'cable'], 'canalizacion' => ['conduit', 'raceway', 'pathway'], 'tuberia' => ['pipe', 'piping', 'conduit'], 'conduit' => ['conduit', 'raceway'],
        'charola' => ['tray', 'cable tray'], 'ducto' => ['duct', 'conduit'], 'registro' => ['junction', 'box', 'pull'], 'caja' => ['box', 'enclosure'], 'gabinete' => ['cabinet', 'enclosure', 'rack'], 'rack' => ['rack', 'cabinet'],
        'camara' => ['camera', 'cctv'], 'videovigilancia' => ['video surveillance', 'cctv', 'camera'], 'cctv' => ['cctv', 'video surveillance'], 'lector' => ['reader', 'card reader'], 'control de acceso' => ['access control'],
        'acceso' => ['access'], 'cerradura' => ['lock', 'strike'], 'sensor' => ['sensor', 'detector'], 'detector' => ['detector'], 'alarma' => ['alarm'], 'incendio' => ['fire', 'fire alarm'], 'sirena' => ['horn', 'strobe', 'siren'],
        'fuente' => ['power supply', 'supply'], 'alimentacion' => ['power', 'supply'], 'energia' => ['power', 'electrical'], 'tierra' => ['ground', 'grounding'], 'aterrizaje' => ['grounding', 'bonding'], 'pararrayos' => ['lightning'],
        'tablero' => ['panel', 'panelboard', 'switchboard'], 'interruptor' => ['switch', 'breaker'], 'contacto' => ['outlet', 'receptacle', 'contact'], 'luminaria' => ['luminaire', 'light fixture', 'lighting'], 'lampara' => ['lamp', 'light'],
        'transformador' => ['transformer'], 'ups' => ['ups', 'uninterruptible'], 'planta' => ['generator', 'plant'], 'generador' => ['generator'], 'bateria' => ['battery'],
        'conector' => ['connector', 'jack'], 'jack' => ['jack', 'connector'], 'patch' => ['patch'], 'parcheo' => ['patch', 'patching'], 'fibra' => ['fiber', 'fibre', 'optical'], 'optica' => ['optical', 'fiber'], 'switch' => ['switch'],
        'red' => ['network', 'lan'], 'datos' => ['data'], 'voz' => ['voice', 'telephone'], 'telefonia' => ['telephone', 'voice'], 'comunicaciones' => ['communications', 'telecommunications'], 'telecomunicaciones' => ['telecommunications'],
        'concreto' => ['concrete'], 'cemento' => ['cement'], 'acero' => ['steel'], 'varilla' => ['rebar', 'reinforcing bar'], 'refuerzo' => ['reinforcement', 'reinforcing'], 'cimbra' => ['formwork'], 'colado' => ['pour', 'placing'], 'cimentacion' => ['foundation'],
        'muro' => ['wall'], 'losa' => ['slab'], 'columna' => ['column'], 'trabe' => ['beam', 'girder'], 'viga' => ['beam'], 'techo' => ['roof', 'ceiling'], 'plafon' => ['ceiling'], 'piso' => ['floor', 'flooring'], 'puerta' => ['door'], 'ventana' => ['window'],
        'canceleria' => ['glazing', 'curtain wall', 'aluminum'], 'cristal' => ['glass', 'glazing'], 'vidrio' => ['glass'], 'pintura' => ['paint', 'painting', 'coating'], 'impermeabilizacion' => ['waterproofing'], 'aislamiento' => ['insulation'],
        'soldadura' => ['welding', 'weld'], 'anclaje' => ['anchor', 'anchorage'], 'tornillo' => ['screw', 'bolt'], 'perno' => ['bolt'], 'soporte' => ['support', 'bracket', 'hanger'], 'abrazadera' => ['clamp', 'strap'], 'sellado' => ['sealing', 'firestop'],
        'firestop' => ['firestop'], 'cortafuego' => ['firestop', 'fire stopping'], 'ventilacion' => ['ventilation', 'hvac'], 'aire' => ['air'], 'acondicionado' => ['air conditioning', 'hvac'], 'extraccion' => ['exhaust'],
        'agua' => ['water'], 'drenaje' => ['drain', 'drainage'], 'sanitario' => ['sanitary', 'plumbing'], 'hidraulico' => ['hydraulic', 'plumbing'], 'valvula' => ['valve'], 'bomba' => ['pump'], 'cisterna' => ['cistern', 'tank'],
        'seguridad' => ['safety', 'security'], 'proteccion' => ['protection'], 'casco' => ['helmet', 'hard hat'], 'altura' => ['height', 'fall'], 'prueba' => ['test', 'testing'], 'pruebas' => ['testing', 'tests'], 'certificacion' => ['certification', 'certified'],
        'garantia' => ['warranty'], 'instalacion' => ['installation', 'install'], 'montaje' => ['mounting', 'installation'], 'mantenimiento' => ['maintenance'], 'inspeccion' => ['inspection'], 'norma' => ['standard', 'code'], 'especificacion' => ['specification'],
        'resistencia' => ['strength', 'resistance'], 'tension' => ['voltage', 'tension'], 'voltaje' => ['voltage'], 'corriente' => ['current'], 'calibre' => ['gauge', 'awg'], 'aislante' => ['insulating', 'insulation'], 'galvanizado' => ['galvanized'],
        'inoxidable' => ['stainless'], 'aluminio' => ['aluminum', 'aluminium'], 'cobre' => ['copper'], 'plastico' => ['plastic', 'pvc'], 'metalico' => ['metallic', 'steel'], 'intemperie' => ['outdoor', 'weatherproof'], 'interior' => ['indoor', 'interior'], 'exterior' => ['outdoor', 'exterior'],
    ];

    private static ?array $inverso = null;

    private static function quitarAcentos(string $t): string
    {
        return strtr(mb_strtolower($t), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /** Palabras (3+ letras, sin acentos) de un texto */
    public static function palabras(string $t): array
    {
        return array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', self::quitarAcentos($t)), fn($w) => strlen($w) >= 3 && !ctype_digit($w))));
    }

    /** Formas posibles en singular de una palabra: «cables» → cable, «canalizaciones» → canalizacion, «cámaras» → camara */
    public static function variantes(string $w): array
    {
        $v = [$w];
        if (str_ends_with($w, 'es') && strlen($w) > 4) $v[] = substr($w, 0, -2);
        if (str_ends_with($w, 's') && strlen($w) > 3) $v[] = substr($w, 0, -1);
        return array_values(array_unique($v));
    }

    /** Las palabras originales más sus equivalentes en el otro idioma (para buscar en documentos en español o inglés) */
    public static function expandir(array $palabras): array
    {
        self::$inverso ??= (function () {
            $inv = [];
            foreach (self::PARES as $es => $ens) foreach ($ens as $en) foreach (self::palabras($en) as $p) $inv[$p][] = $es;
            return $inv;
        })();
        $out = $palabras;
        foreach ($palabras as $w) {
            foreach (self::variantes($w) as $k) {
                foreach (self::PARES[$k] ?? [] as $en) array_push($out, ...self::palabras($en));
                foreach (self::$inverso[$k] ?? [] as $es) array_push($out, ...self::palabras($es));
            }
        }
        return array_values(array_unique($out));
    }

    /** Idioma dominante de un texto: 'es', 'en' o null si no alcanza para decidir */
    public static function idioma(string $texto): ?string
    {
        $es = ['de', 'la', 'el', 'los', 'las', 'que', 'para', 'con', 'por', 'una', 'del', 'se', 'en', 'y', 'deberan', 'debera', 'sera', 'seran', 'cuando', 'sobre', 'este', 'esta', 'segun'];
        $en = ['the', 'of', 'and', 'to', 'in', 'shall', 'be', 'for', 'with', 'by', 'is', 'are', 'that', 'this', 'as', 'or', 'from', 'on', 'all', 'such', 'will', 'which'];
        $n = 0; $a = 0; $b = 0;
        foreach (preg_split('/[^a-z]+/', self::quitarAcentos($texto)) as $w) {
            if ($w === '') continue;
            $n++;
            if (in_array($w, $es, true)) $a++;
            if (in_array($w, $en, true)) $b++;
        }
        if ($n < 12 || ($a + $b) < 3) return null;
        return $a >= $b ? 'es' : 'en';
    }
}
