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
    use AccesoDb;

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
        $r = ['Archivos' => 0, 'Fotos de reportes diarios' => 0, 'Personal (fotos)' => 0, 'vPlan (planos e imágenes)' => 0, 'Editor (gráficos)' => 0, 'Redactor (documentos)' => 0, 'SheetFlow (tablas)' => 0, 'Símbolos' => 0, 'Evidencias' => 0, 'Materiales (fotos y fichas)' => 0, 'Logos y carátula' => 0];

        foreach (Archivo::deProyecto($proyectoId) as $a) $r['Archivos'] += self::tam((string)$a->ruta()) ?: 0;

        foreach (ReporteDiario::deProyecto($proyectoId) as $rd) {
            foreach ($rd->getFotos() as $f) $r['Fotos de reportes diarios'] += self::tam(self::RAIZ . '/public/uploads/reportes/' . basename($f));
        }
        foreach (Personal::deProyecto($proyectoId) as $pe) {
            if ($pe->imagen) $r['Personal (fotos)'] += self::tam(self::RAIZ . '/public/uploads/personal/' . basename($pe->imagen));
        }
        foreach (VplanLamina::deProyecto($proyectoId) as $l) {
            $nombres = array_filter([$l->plano, $l->simbologia, $l->marcas_png]);
            $nombres = array_merge($nombres, \Model\Grafico::imagenesDe($l->marcas_json));
            foreach (array_unique($nombres) as $n) $r['vPlan (planos e imágenes)'] += self::tam(self::RAIZ . '/public/uploads/vplan/' . basename($n));
        }
        try {   // si la tabla del Editor aún no existe (migración pendiente), no debe bloquear las demás subidas
            foreach (\Model\Grafico::deProyecto($proyectoId, null) as $g) $r['Editor (gráficos)'] += $g->bytes();
        } catch (\Throwable $e) {
        }
        try {
            foreach (\Model\RedactorDocumento::deProyecto($proyectoId) as $doc) $r['Redactor (documentos)'] += $doc->bytes();
        } catch (\Throwable $e) {
        }
        try {
            $r['SheetFlow (tablas)'] += \Model\SheetflowHoja::bytesDe($proyectoId);
        } catch (\Throwable $e) {
        }
        try {
            foreach (\Model\Simbolo::deProyecto($proyectoId) as $sim) $r['Símbolos'] += $sim->bytes();
        } catch (\Throwable $e) {
        }
        try {
            foreach (\Model\Evidencia::deProyecto($proyectoId) as $ev) $r['Evidencias'] += $ev->bytes();
        } catch (\Throwable $e) {
        }
        foreach (Material::deProyecto($proyectoId) as $m) {
            if ($m->imagen) $r['Materiales (fotos y fichas)'] += self::tam(self::RAIZ . '/public/uploads/materiales/' . basename($m->imagen));
            if ($m->ficha_tecnica) $r['Materiales (fotos y fichas)'] += self::tam(self::RAIZ . "/storage/fichas/$proyectoId/" . basename($m->ficha_tecnica));
        }
        $r['Grabaciones de reuniones'] = Grabaciones::bytes($proyectoId);
        $r['Normativa y especificaciones'] = Normativa::bytes($proyectoId);
        $r['Punch list (fotos)'] = Punch::bytes($proyectoId);
        if ($p) {
            foreach ([['logo', 'logos'], ['contratista_logo', 'logos'], ['caratula', 'caratulas']] as [$c, $dir]) {
                if ($p->$c) $r['Logos y carátula'] += self::tam(self::RAIZ . "/public/build/img/$dir/" . basename($p->$c));
            }
        }
        return $r;
    }

    /** Límite en bytes del proyecto: el propio que le fijó el admin; si no, el del cliente dueño; si no, el predeterminado */
    public static function limite(int $proyectoId): int
    {
        $p = Proyecto::find($proyectoId);
        if ($p && (int)($p->limite_almacenamiento_mb ?? 0) > 0) {
            return (int)$p->limite_almacenamiento_mb * 1048576;
        }
        $u = $p && $p->usuario_id ? \Model\Usuario::find($p->usuario_id) : null;
        return $u && $u->rol === 'cliente' && (int)$u->limite_almacenamiento_mb > 0 ? (int)$u->limite_almacenamiento_mb * 1048576 : self::LIMITE;
    }

    /** Límite en bytes sin el ajuste propio del proyecto (el heredado del cliente o el predeterminado) */
    public static function limiteHeredado(int $proyectoId): int
    {
        $p = Proyecto::find($proyectoId);
        $u = $p && $p->usuario_id ? \Model\Usuario::find($p->usuario_id) : null;
        return $u && $u->rol === 'cliente' && (int)$u->limite_almacenamiento_mb > 0 ? (int)$u->limite_almacenamiento_mb * 1048576 : self::LIMITE;
    }

    /** MB asignados a un proyecto por el admin (0 si no tiene límite propio) */
    public static function limiteMb(int $proyectoId): int
    {
        $p = Proyecto::find($proyectoId);
        return $p ? (int)($p->limite_almacenamiento_mb ?? 0) : 0;
    }

    /** ¿Existe la columna del límite propio del proyecto? (migración aplicada) */
    public static function hayColumnaProyecto(): bool
    {
        static $existe = null;
        if ($existe !== null) return $existe;
        $existe = in_array('limite_almacenamiento_mb', self::columnasProyecto(), true);
        return $existe;
    }

    /** Columnas de la tabla proyectos (con caché) */
    private static function columnasProyecto(): array
    {
        static $cols = null;
        if ($cols !== null) return $cols;
        $cols = [];
        try {
            $r = self::db()->query("SHOW COLUMNS FROM proyectos");
            if ($r) while ($f = $r->fetch_assoc()) $cols[] = $f['Field'];
        } catch (\Throwable $e) {
            $cols = [];
        }
        return $cols;
    }

    /**
     * Fija el espacio propio del proyecto (MB). Con $mb = 0 se vuelve al límite
     * heredado del cliente dueño (o al predeterminado de 300 MB).
     */
    public static function fijarLimiteProyecto(int $proyectoId, int $mb): bool
    {
        if (!self::hayColumnaProyecto()) return false;
        $valor = $mb > 0 ? (int)$mb : 'NULL';
        return (bool)HistorialCambios::escribir('proyectos', 'modificado', (int)$proyectoId,
            fn() => self::db()->query("UPDATE proyectos SET limite_almacenamiento_mb = $valor WHERE id = " . (int)$proyectoId));
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
