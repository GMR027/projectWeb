<?php

namespace Controllers;

use Model\Proyecto;
use Classes\MaterialesInteligentes;

/** Materiales inteligentes: API para la app de escritorio (solo admin). La web solo muestra el resultado en materiales y submittals */
class MaterialesInteligentesController
{
    private static function estadoDe(Proyecto $p): array
    {
        $pid = (int)$p->id;
        return ['activo' => MaterialesInteligentes::activo($pid), 'documentos' => MaterialesInteligentes::documentos($pid), 'conteos' => MaterialesInteligentes::conteos($pid)];
    }

    public static function estado()
    {
        $p = proyecto_api();
        json_respuesta(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre] + self::estadoDe($p));
    }

    /** Activa o desactiva la opción (solo desde la app de escritorio) */
    public static function activar()
    {
        $p = proyecto_api();
        if (!MaterialesInteligentes::fijarActivo((int)$p->id, !empty($_POST['activo']))) json_respuesta(['success' => false, 'error' => 'No se pudo guardar (¿falta la migración materiales_inteligentes?)'], 500);
        json_respuesta(['success' => true] + self::estadoDe($p));
    }

    /** Lotes de prompt para el modelo; sin la opción activa no se envía nada */
    public static function contexto()
    {
        $p = proyecto_api();
        $e = self::estadoDe($p);
        if (!$e['activo']) json_respuesta(['success' => true, 'csrf' => csrf_token(), 'lotes' => [], 'motivo' => 'inactivo'] + $e);
        if (!$e['documentos']) json_respuesta(['success' => true, 'csrf' => csrf_token(), 'lotes' => [], 'motivo' => 'sin_documentos'] + $e);
        json_respuesta(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'lotes' => MaterialesInteligentes::lotes($p)] + $e);
    }

    /** Recibe las respuestas de la IA ({modelo, respuestas:[...]}), las valida y guarda el veredicto en cada material */
    public static function resultado()
    {
        $p = proyecto_api();
        if (!MaterialesInteligentes::activo((int)$p->id)) json_respuesta(['success' => false, 'error' => 'Materiales inteligentes no está activo en este proyecto'], 409);
        $cuerpo = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($cuerpo) || !is_array($cuerpo['respuestas'] ?? null)) json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        $r = MaterialesInteligentes::guardar($p, MaterialesInteligentes::normalizarIA($p, $cuerpo['respuestas']), (string)($cuerpo['modelo'] ?? ''));
        json_respuesta(['success' => true] + $r + self::estadoDe($p));
    }
}
