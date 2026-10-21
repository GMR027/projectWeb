<?php

namespace Controllers;

use Classes\EstampaContexto;
use Classes\Estampas;
use Model\Estampa;
use Model\VplanLamina;

/** Estampas inteligentes (pestaña de Símbolos): guardar, duplicar, eliminar, copiar plantillas y vistas previas con datos reales */
class EstampaController
{
    private static function propia(int $proyectoId): ?Estampa
    {
        $e = Estampa::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        return $e && (int)$e->proyecto_id === $proyectoId ? $e : null;
    }

    private static function sinTabla(): void
    {
        if (!Estampa::disponible()) json_respuesta(['success' => false, 'error' => 'Falta aplicar database/migracion_estampas.sql en esta base de datos'], 500);
    }

    public static function guardar()
    {
        $proyecto = proyecto_de_peticion(true);
        self::sinTabla();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $e = $id ? self::propia((int)$proyecto->id) : null;
        if ($id && !$e) json_respuesta(['success' => false, 'error' => 'Estampa no encontrada'], 404);
        $cfg = json_decode((string)($_POST['config'] ?? ''), true);
        if (!is_array($cfg)) json_respuesta(['success' => false, 'error' => 'Configuración no válida'], 422);
        $nuevo = new Estampa(['id' => $e->id ?? null, 'proyecto_id' => $proyecto->id, 'nombre' => $_POST['nombre'] ?? '',
            'config_json' => json_encode(Estampas::limpiarConfig($cfg, (int)$proyecto->id), JSON_UNESCAPED_UNICODE)]);
        if ($al = $nuevo->validar()) json_respuesta(['success' => false, 'error' => $al['error'][0]], 422);
        $r = $nuevo->guardar();
        json_respuesta(['success' => true, 'id' => (int)($nuevo->id ?: ($r['id'] ?? 0))]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        self::sinTabla();
        if ($e = self::propia((int)$proyecto->id)) $e->eliminar();
        json_respuesta(['success' => true]);
    }

    public static function duplicar()
    {
        $proyecto = proyecto_de_peticion(true);
        self::sinTabla();
        $e = self::propia((int)$proyecto->id);
        if (!$e) json_respuesta(['success' => false, 'error' => 'Estampa no encontrada'], 404);
        $copia = new Estampa(['proyecto_id' => $proyecto->id, 'nombre' => mb_substr($e->nombre, 0, 90) . ' (copia)', 'config_json' => $e->config_json]);
        $r = $copia->guardar();
        json_respuesta(['success' => true, 'id' => (int)($r['id'] ?? 0)]);
    }

    /** Copia una de las plantillas de ejemplo a este proyecto */
    public static function plantilla()
    {
        $proyecto = proyecto_de_peticion(true);
        self::sinTabla();
        $p = Estampas::plantillas()[(string)($_POST['clave'] ?? '')] ?? null;
        if (!$p) json_respuesta(['success' => false, 'error' => 'Plantilla no encontrada'], 404);
        $e = new Estampa(['proyecto_id' => $proyecto->id, 'nombre' => $p['nombre'], 'config_json' => json_encode(Estampas::limpiarConfig($p['config'], (int)$proyecto->id), JSON_UNESCAPED_UNICODE)]);
        $r = $e->guardar();
        json_respuesta(['success' => true, 'id' => (int)($r['id'] ?? 0)]);
    }

    /** Contexto de una vista previa o de un bloque: lámina opcional, fecha de corte y actividad de ejemplo */
    private static function contexto($proyecto, ?array $extra = null): array
    {
        $lamina = VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT));
        if ($lamina && (int)$lamina->proyecto_id !== (int)$proyecto->id) $lamina = null;
        $corte = (string)($_POST['corte'] ?? '');
        $opts = ['corte' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte) ? $corte : '', 'actividad' => (int)($_POST['actividad'] ?? 0)];
        if (!$lamina) $opts += ['lamina_n' => 1, 'lamina_total' => 1];
        return [$lamina, $opts + ($extra ?? [])];
    }

    /** HTML de una estampa guardada (id) o aún sin guardar (config) con datos reales. modo: cq (hoja), px (vista previa) */
    public static function vista()
    {
        $proyecto = proyecto_de_peticion();
        [$lamina, $opts] = self::contexto($proyecto);
        $modo = ($_POST['modo'] ?? '') === 'px' ? 'px' : 'cq';
        if (!empty($_POST['config'])) {
            $cfg = json_decode((string)$_POST['config'], true);
            if (!is_array($cfg)) json_respuesta(['success' => false, 'error' => 'Configuración no válida'], 422);
            $cfg = Estampas::limpiarConfig($cfg, (int)$proyecto->id);
            $ctx = new EstampaContexto($proyecto, $lamina, $opts + ['revision' => $cfg['datos']['revision'], 'escala' => $cfg['datos']['escala']]);
            json_respuesta(['success' => true, 'html' => Estampas::render($cfg, $ctx, Estampas::unidad($modo, 1.3))]);
        }
        $e = Estampa::disponible() ? self::propia((int)$proyecto->id) : null;
        if (!$e) json_respuesta(['success' => false, 'error' => 'Estampa no encontrada'], 404);
        json_respuesta(['success' => true, 'html' => Estampas::renderGuardada($e, $proyecto, $lamina, $opts, $modo, 1.3)]);
    }

    /** Resuelve textos con {campos}: para la vista previa de cajetines y para el Editor (solo_nuevas deja las variables antiguas) */
    public static function textos()
    {
        $proyecto = proyecto_de_peticion();
        [$lamina, $opts] = self::contexto($proyecto);
        $ctx = new EstampaContexto($proyecto, $lamina, $opts);
        $solo = !empty($_POST['solo_nuevas']);
        $out = [];
        foreach (array_slice((array)($_POST['textos'] ?? []), 0, 60) as $t) $out[] = Estampas::texto(mb_substr((string)$t, 0, 300), $ctx, $solo);
        json_respuesta(['success' => true, 'textos' => $out]);
    }
}
