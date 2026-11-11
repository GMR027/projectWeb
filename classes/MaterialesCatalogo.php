<?php

namespace Classes;

use Model\Material;

/** Utilidades compartidas por la vista y los exportadores del catálogo de materiales */
class MaterialesCatalogo
{
    /** Agrupa por especialidad (los vacíos al final como «Sin especialidad») y ordena por nombre */
    public static function secciones(array $materiales): array
    {
        $out = [];
        foreach ($materiales as $m) {
            $esp = trim((string)$m->especialidad) !== '' ? trim((string)$m->especialidad) : 'Sin especialidad';
            $out[$esp][] = $m;
        }
        uksort($out, function ($a, $b) {
            if ($a === 'Sin especialidad') return 1;
            if ($b === 'Sin especialidad') return -1;
            return strcasecmp($a, $b);
        });
        foreach ($out as &$lista) usort($lista, fn($a, $b) => strcasecmp((string)$a->nombre, (string)$b->nombre));
        return $out;
    }

    /** Fecha dd/mm/aaaa o «—» */
    public static function fecha(?string $f): string
    {
        return $f ? date('d/m/Y', strtotime($f)) : '—';
    }

    public static function compra(?string $c): string
    {
        return Material::COMPRA[$c ?? ''] ?? 'Pendiente';
    }

    /** Resumen de filtros para la cabecera del archivo */
    public static function textoFiltros(array $f): string
    {
        $partes = [];
        if (($f['q'] ?? '') !== '') $partes[] = 'Búsqueda: ' . $f['q'];
        if (($f['especialidad'] ?? '') !== '') $partes[] = 'Especialidad: ' . ($f['especialidad'] === '__sin' ? 'Sin especialidad' : $f['especialidad']);
        if (($f['entrega'] ?? '') !== '') $partes[] = 'Entrega: ' . ($f['entrega'] === 'entregado' ? 'Entregado' : 'Pendiente');
        if (($f['compra'] ?? '') !== '') $partes[] = 'Compra: ' . self::compra((string)$f['compra']);
        return $partes ? implode(' · ', $partes) : 'Todo el catálogo';
    }
}
