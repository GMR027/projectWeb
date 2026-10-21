<?php

namespace Classes;

/** Crear, editar y borrar notas (vPlan y View360): misma validación y mismas respuestas JSON; la fecha la pone el servidor al crear */
class NotasApi
{
    /**
     * $dueno = lámina o gráfico ya autorizado; $nota = clase del modelo de la nota; $fk = columna que la liga al dueño.
     * $total = cuántas notas tiene ya el dueño; $max = tope por dueño; $unidad = «lámina» o «gráfico» para el mensaje.
     */
    public static function guardar(object $dueno, string $nota, string $fk, callable $total, int $max, string $unidad): void
    {
        $texto = trim((string)($_POST['descripcion'] ?? ''));
        if ($texto === '') json_respuesta(['success' => false, 'error' => 'La descripción es obligatoria'], 422);
        if (mb_strlen($texto) > $nota::MAX_LARGO) json_respuesta(['success' => false, 'error' => 'Máximo ' . $nota::MAX_LARGO . ' caracteres'], 422);

        if (!empty($_POST['id'])) {
            $n = self::deDueno($dueno, $nota, $fk, $_POST['id']);
            if (!$n) json_respuesta(['success' => false, 'error' => 'Nota no encontrada'], 404);
            $n->descripcion = $texto;   // la fecha original se conserva
            $n->guardar();
        } else {
            if ($total() >= $max) json_respuesta(['success' => false, 'error' => "Máximo $max notas por $unidad"], 422);
            $n = new $nota([$fk => $dueno->id, 'descripcion' => $texto]);
            $n->id = $n->guardar()['id'];
        }
        json_respuesta(['success' => true, 'nota' => $n->aArray()]);
    }

    public static function eliminar(object $dueno, string $nota, string $fk): void
    {
        $n = self::deDueno($dueno, $nota, $fk, $_POST['id'] ?? null);
        if (!$n) json_respuesta(['success' => false, 'error' => 'Nota no encontrada'], 404);
        $n->eliminar();
        json_respuesta(['success' => true]);
    }

    private static function deDueno(object $dueno, string $nota, string $fk, $id): ?object
    {
        $n = $nota::find(filter_var($id, FILTER_VALIDATE_INT));
        return $n && (int)$n->$fk === (int)$dueno->id ? $n : null;
    }
}
