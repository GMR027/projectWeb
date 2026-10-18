<?php

namespace Controllers;

use Model\Proyecto;
use Classes\MaterialesInteligentes;

/** Materiales inteligentes: API para la app de escritorio (solo admin). La web solo muestra el resultado en materiales y submittals */
class MaterialesInteligentesController
{
    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function proyectoApi(): Proyecto
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $p = is_admin() ? Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    private static function estadoDe(Proyecto $p): array
    {
        $pid = (int)$p->id;
        return ['activo' => MaterialesInteligentes::activo($pid), 'documentos' => MaterialesInteligentes::documentos($pid), 'conteos' => MaterialesInteligentes::conteos($pid)];
    }

    public static function estado()
    {
        $p = self::proyectoApi();
        self::json(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre] + self::estadoDe($p));
    }

    /** Activa o desactiva la opción (solo desde la app de escritorio) */
    public static function activar()
    {
        $p = self::proyectoApi();
        if (!MaterialesInteligentes::fijarActivo((int)$p->id, !empty($_POST['activo']))) self::json(['success' => false, 'error' => 'No se pudo guardar (¿falta la migración materiales_inteligentes?)'], 500);
        self::json(['success' => true] + self::estadoDe($p));
    }

    /** Lotes de prompt para el modelo; sin la opción activa no se envía nada */
    public static function contexto()
    {
        $p = self::proyectoApi();
        $e = self::estadoDe($p);
        if (!$e['activo']) self::json(['success' => true, 'csrf' => csrf_token(), 'lotes' => [], 'motivo' => 'inactivo'] + $e);
        if (!$e['documentos']) self::json(['success' => true, 'csrf' => csrf_token(), 'lotes' => [], 'motivo' => 'sin_documentos'] + $e);
        self::json(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'lotes' => MaterialesInteligentes::lotes($p)] + $e);
    }

    /** Recibe las respuestas de la IA ({modelo, respuestas:[...]}), las valida y guarda el veredicto en cada material */
    public static function resultado()
    {
        $p = self::proyectoApi();
        if (!MaterialesInteligentes::activo((int)$p->id)) self::json(['success' => false, 'error' => 'Materiales inteligentes no está activo en este proyecto'], 409);
        $cuerpo = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($cuerpo) || !is_array($cuerpo['respuestas'] ?? null)) self::json(['success' => false, 'error' => 'Datos no válidos'], 422);
        $r = MaterialesInteligentes::guardar($p, MaterialesInteligentes::normalizarIA($p, $cuerpo['respuestas']), (string)($cuerpo['modelo'] ?? ''));
        self::json(['success' => true] + $r + self::estadoDe($p));
    }
}
