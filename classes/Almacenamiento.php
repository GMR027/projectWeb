<?php

namespace Classes;

use Model\Archivo;
use Model\Material;
use Model\Organizacion;
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
        $r = ['Archivos' => 0, 'Fotos de reportes diarios' => 0, 'Personal (fotos)' => 0, 'vPlan (planos e imágenes)' => 0, 'Editor (gráficos)' => 0, 'Redactor (documentos)' => 0, 'SheetFlow (tablas)' => 0, 'Símbolos' => 0, 'Evidencias' => 0, 'Materiales (fotos y fichas)' => 0, 'Anotador (planos y firmas)' => 0, 'Logos y carátula' => 0];

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
        $r['Comparador de documentos'] = Comparador::bytes($proyectoId);
        try {
            $r['Anotador (planos y firmas)'] = \Classes\Anotador::bytes($proyectoId);
        } catch (\Throwable $e) {
        }
        if ($p) {
            foreach ([['logo', 'logos'], ['contratista_logo', 'logos'], ['caratula', 'caratulas']] as [$c, $dir]) {
                if ($p->$c) $r['Logos y carátula'] += self::tam(self::RAIZ . "/public/build/img/$dir/" . basename($p->$c));
            }
        }
        return $r;
    }

    /** Límite en bytes del proyecto: el propio que le fijó el admin; si no, el de su organización; si no, el del cliente dueño; si no, el predeterminado */
    public static function limite(int $proyectoId): int
    {
        $p = Proyecto::find($proyectoId);
        if ($p && (int)($p->limite_almacenamiento_mb ?? 0) > 0) {
            return (int)$p->limite_almacenamiento_mb * 1048576;
        }
        return self::limiteHeredado($proyectoId);
    }

    /** Límite en bytes sin el ajuste propio del proyecto (el de su organización o el heredado del cliente) */
    public static function limiteHeredado(int $proyectoId): int
    {
        $p = Proyecto::find($proyectoId);
        if (!$p) return self::LIMITE;
        $org = self::organizacionDeProyecto($p);
        if ($org) {
            $lim = $org->limites();
            $mb = (int)($lim['almacenamiento_por_obra_mb'] ?? 0);
            return $mb > 0 ? $mb * 1048576 : self::LIMITE;
        }
        $u = $p->usuario_id ? \Model\Usuario::find((int)$p->usuario_id) : null;
        return $u && $u->rol === 'cliente' && (int)$u->limite_almacenamiento_mb > 0 ? (int)$u->limite_almacenamiento_mb * 1048576 : self::LIMITE;
    }

    /** Organización dueña del proyecto (por su columna o por el usuario dueño) */
    public static function organizacionDeProyecto(Proyecto $p): ?Organizacion
    {
        try {
            if (!empty($p->organizacion_id)) {
                $org = Organizacion::find((int)$p->organizacion_id);
                if ($org) return $org;
            }
            return $p->usuario_id ? Organizacion::deUsuario((int)$p->usuario_id) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Límite total de la organización en bytes; null = sin tope total (legacy) */
    public static function limiteCuenta(?int $orgId): ?int
    {
        if (!$orgId) return null;
        try {
            $org = Organizacion::find($orgId);
            if (!$org) return null;
            $gb = $org->limites()['almacenamiento_gb'];
            return $gb === null ? null : (int)$gb * 1073741824;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Espacio usado por TODAS las obras de la organización (incluidas las archivadas) */
    public static function usadoCuenta(int $orgId): int
    {
        static $cache = [];
        if (array_key_exists($orgId, $cache)) return $cache[$orgId];
        $total = 0;
        try {
            foreach (Proyecto::whereAll('organizacion_id', (string)$orgId, 'id ASC') as $p) {
                $total += self::usado((int)$p->id);
            }
        } catch (\Throwable $e) {
        }
        return $cache[$orgId] = $total;
    }

    /** Recalcula y guarda el contador de la organización (para el panel admin) */
    public static function recalcularCuenta(int $orgId): int
    {
        $total = 0;
        foreach (Proyecto::whereAll('organizacion_id', (string)$orgId, 'id ASC') as $p) {
            $total += self::usado((int)$p->id);
        }
        try {
            self::db()->query('UPDATE organizaciones SET almacenamiento_bytes = ' . (int)$total . ' WHERE id = ' . (int)$orgId);
        } catch (\Throwable $e) {
        }
        return $total;
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

    /** ¿Caben $bytes más sin pasar del límite del proyecto ni del total de la organización? */
    public static function cabe(int $proyectoId, int $bytes): bool
    {
        if ($bytes > self::disponible($proyectoId)) return false;
        $p = Proyecto::find($proyectoId);
        $org = $p ? self::organizacionDeProyecto($p) : null;
        if ($org) {
            $total = self::limiteCuenta((int)$org->id);
            if ($total !== null && $bytes > max(0, $total - self::usadoCuenta((int)$org->id))) return false;
        }
        return true;
    }

    public static function mensajeLleno(int $proyectoId): string
    {
        $p = Proyecto::find($proyectoId);
        $org = $p ? self::organizacionDeProyecto($p) : null;
        if ($org) {
            $total = self::limiteCuenta((int)$org->id);
            $usadoCuenta = self::usadoCuenta((int)$org->id);
            if ($total !== null && $usadoCuenta >= $total) {
                return 'La organización alcanzó su límite de almacenamiento (' . self::formato($total) . '). Quedan ' . self::formato(max(0, $total - $usadoCuenta)) . ' libres; elimina archivos o amplía el plan para continuar.';
            }
        }
        return 'El proyecto alcanzó su límite de almacenamiento (' . self::formato(self::limite($proyectoId)) . '). Quedan ' . self::formato(self::disponible($proyectoId)) . ' libres; elimina archivos para continuar.';
    }

    public static function formato(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        return number_format($bytes / 1024, 0) . ' KB';
    }
}
