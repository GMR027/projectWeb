<?php

namespace Classes\Herramientas;

use Model\Proyecto;

/**
 * Contrato de una herramienta de IA que produce un informe. Toda cifra, lista y fecha la calcula el servidor en `preparar()`;
 * la IA solo redacta (lotes con respuesta JSON). `componer()` también funciona sin respuestas de la IA (texto de plantilla),
 * así que la herramienta sirve con cualquier modelo y sin modelo.
 */
interface Herramienta
{
    public static function id(): string;

    /** Nombre, grupo del menú, descripción, qué exige al modelo y parámetros que pide */
    public static function ficha(): array;

    /** @return array{datos: array, lotes: list<array{titulo: string, prompt: string}>} */
    public static function preparar(Proyecto $p, array $params): array;

    /** Contenido del informe a partir de los datos calculados y las respuestas (decodificadas) de la IA, que pueden faltar */
    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array;

    public static function titulo(Proyecto $p, array $params): string;
}
