<?php

namespace Classes;

use Model\Archivo;
use Model\Material;
use Model\Personal;
use Model\Proyecto;
use Model\ReporteDiario;
use Model\VplanLamina;

/** Espacio en disco que ocupa la información subida a un proyecto (límite fijo por proyecto) */
class Almacenamiento
{
    public const LIMITE = 300 * 1024 * 1024;   // 300 MB por defecto (proyectos de admin o sin dueño)
    private const RAIZ = __DIR__ . '/..';

    private static function tam(string $ruta): int
    {
        return is_file($ruta) ? (int)@filesize($ruta) : 0;
    }

    /** ['Categoría' => bytes, ...] de lo subido al proyecto */
    public static function desglose(int $proyectoId): array
    {
        $p = Proyecto::find($proyectoId);
        $r = ['Archivos' => 0, 'Fotos de reportes diarios' => 0, 'Personal (fotos)' => 0, 'vPlan (planos e imágenes)' => 0, 'Materiales (fotos y fichas)' => 0, 'Logos y carátula' => 0];

        foreach (Archivo::deProyecto($proyectoId) as $a) $r['Archivos'] += self::tam((string)$a->ruta()) ?: 0;

        foreach (ReporteDiario::deProyecto($proyectoId) as $rd) {
            foreach ($rd->getFotos() as $f) $r['Fotos de reportes diarios'] += self::tam(self::RAIZ . '/public/uploads/reportes/' . basename($f));
        }
        foreach (Personal::deProyecto($proyectoId) as $pe) {
            if ($pe->imagen) $r['Personal (fotos)'] += self::tam(self::RAIZ . '/public/uploads/personal/' . basename($pe->imagen));
        }
        foreach (VplanLamina::deProyecto($proyectoId) as $l) {
            $nombres = array_filter([$l->plano, $l->simbologia, $l->marcas_png]);
            $nombres = array_merge($nombres, \Controllers\VplanController::imagenesDeMarcas($l->marcas_json));
            foreach (array_unique($nombres) as $n) $r['vPlan (planos e imágenes)'] += self::tam(self::RAIZ . '/public/uploads/vplan/' . basename($n));
        }
        foreach (Material::deProyecto($proyectoId) as $m) {
            if ($m->imagen) $r['Materiales (fotos y fichas)'] += self::tam(self::RAIZ . '/public/uploads/materiales/' . basename($m->imagen));
            if ($m->ficha_tecnica) $r['Materiales (fotos y fichas)'] += self::tam(self::RAIZ . "/storage/fichas/$proyectoId/" . basename($m->ficha_tecnica));
        }
        if ($p) {
            foreach ([['logo', 'logos'], ['contratista_logo', 'logos'], ['caratula', 'caratulas']] as [$c, $dir]) {
                if ($p->$c) $r['Logos y carátula'] += self::tam(self::RAIZ . "/public/build/img/$dir/" . basename($p->$c));
            }
        }
        return $r;
    }

    /** Límite en bytes del proyecto: el que el admin fijó al cliente dueño; si no, el predeterminado */
    public static function limite(int $proyectoId): int
    {
        $p = Proyecto::find($proyectoId);
        $u = $p && $p->usuario_id ? \Model\Usuario::find($p->usuario_id) : null;
        return $u && $u->rol === 'cliente' && (int)$u->limite_almacenamiento_mb > 0 ? (int)$u->limite_almacenamiento_mb * 1048576 : self::LIMITE;
    }

    public static function usado(int $proyectoId): int
    {
        return array_sum(self::desglose($proyectoId));
    }

    public static function disponible(int $proyectoId): int
    {
        return max(0, self::limite($proyectoId) - self::usado($proyectoId));
    }

    /** ¿Caben $bytes más sin pasar del límite? */
    public static function cabe(int $proyectoId, int $bytes): bool
    {
        return $bytes <= self::disponible($proyectoId);
    }

    public static function mensajeLleno(int $proyectoId): string
    {
        return 'El proyecto alcanzó su límite de almacenamiento (' . self::formato(self::limite($proyectoId)) . '). Quedan ' . self::formato(self::disponible($proyectoId)) . ' libres; elimina archivos para continuar.';
    }

    public static function formato(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        return number_format($bytes / 1024, 0) . ' KB';
    }
}
