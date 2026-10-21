<?php

namespace Classes;

use Model\Archivo;
use Model\Ensamble;
use Model\RedactorDocumento;

/** Une en un solo PDF documentos del Redactor y PDF del Archivo, siempre con el contenido actual de cada fuente */
class EnsamblePdf
{
    /** Fuentes disponibles del proyecto: documentos del Redactor y archivos PDF */
    public static function disponibles(int $proyectoId): array
    {
        $redactor = [];
        foreach (RedactorDocumento::consultarSQL('SELECT id, proyecto_id, nombre, fecha, departamento FROM redactor_documentos WHERE proyecto_id = ' . $proyectoId . ' ORDER BY nombre') as $d) {
            $redactor[] = ['t' => 'redactor', 'id' => (int)$d->id, 'nombre' => (string)$d->nombre, 'detalle' => (string)$d->departamento];
        }
        $pdfs = [];
        foreach (Archivo::deProyecto($proyectoId) as $a) {
            if ($a->extension() !== 'pdf' || Ensamble::delArchivo((int)$a->id)) continue;   // un ensamble no se usa como fuente de otro
            $pdfs[] = ['t' => 'archivo', 'id' => (int)$a->id, 'nombre' => (string)($a->nombre ?: $a->archivo_original), 'detalle' => $a->numero_revision !== '' ? 'Rev. ' . $a->numero_revision : ''];
        }
        return ['redactor' => $redactor, 'archivo' => $pdfs];
    }

    /** Carga una fuente del proyecto (null si ya no existe o es de otro proyecto) */
    private static function fuente(array $f, int $proyectoId)
    {
        $o = $f['t'] === 'redactor' ? RedactorDocumento::find($f['id']) : Archivo::find($f['id']);
        if (!$o || (int)$o->proyecto_id !== $proyectoId) return null;
        if ($f['t'] === 'archivo' && ($o->extension() !== 'pdf' || !self::rutaFuente($o))) return null;
        return $o;
    }

    /** Ruta del PDF de una fuente de Archivo sin disparar la actualización de ensambles */
    private static function rutaFuente(Archivo $a): ?string
    {
        $r = Archivo::carpeta((int)$a->proyecto_id) . '/' . basename((string)$a->archivo_guardado);
        return is_file($r) ? $r : null;
    }

    /** Nombre legible de cada fuente y si falta, en el orden de la receta */
    public static function resumen(Ensamble $e): array
    {
        $out = [];
        foreach ($e->listaFuentes() as $f) {
            $o = self::fuente($f, (int)$e->proyecto_id);
            $out[] = ['t' => $f['t'], 'id' => $f['id'], 'nombre' => $o ? (string)($o->nombre ?: ($o->archivo_original ?? '')) : '', 'falta' => !$o];
        }
        return $out;
    }

    /** Huella del estado actual de las fuentes: cambia cuando se edita un documento o se reemplaza un PDF */
    public static function firma(Ensamble $e): string
    {
        $partes = [];
        $h = \Model\SheetflowHoja::consultarSQL('SELECT id, actualizado FROM sheetflow_hojas WHERE proyecto_id = ' . (int)$e->proyecto_id . ' ORDER BY actualizado DESC LIMIT 1');
        $sf = $h ? (string)$h[0]->actualizado : '';
        foreach ($e->listaFuentes() as $f) {
            $o = self::fuente($f, (int)$e->proyecto_id);
            if (!$o) $partes[] = $f['t'] . $f['id'] . ':falta';
            elseif ($f['t'] === 'redactor') $partes[] = 'r' . $o->id . ':' . $o->actualizado . ':' . md5((string)$o->contenido) . ':' . $sf;
            else $partes[] = 'a' . $o->id . ':' . $o->archivo_guardado . ':' . $o->tamano;
        }
        return sha1(implode('|', $partes));
    }

    /** Genera el PDF unido. Devuelve [ruta temporal, avisos]; quien llama borra el archivo. */
    public static function generar(Ensamble $e): array
    {
        @set_time_limit(180);
        $pid = (int)$e->proyecto_id;
        $temporales = [];
        $avisos = [];
        try {
            foreach ($e->listaFuentes() as $f) {
                $o = self::fuente($f, $pid);
                if (!$o) { $avisos[] = 'Un documento ya no existe y se omitió'; continue; }
                if ($f['t'] === 'archivo') { $temporales[] = [self::rutaFuente($o), false]; continue; }
                $c = clone $o;
                $c->contenido = Sheetflow::refrescarHtml((string)$o->contenido, $pid);
                $tmp = tempnam(sys_get_temp_dir(), 'ens') . '.pdf';
                file_put_contents($tmp, Redactor::pdf($c, ['cambios' => false, 'comentarios' => false, 'firmas' => true]));
                $temporales[] = [$tmp, true];
            }
            if (!$temporales) throw new \RuntimeException('No hay documentos que unir');
            $salida = tempnam(sys_get_temp_dir(), 'ens') . '.pdf';
            if (!self::unir(array_column($temporales, 0), $salida)) { @unlink($salida); throw new \RuntimeException('No se pudo unir los PDF'); }
            return [$salida, $avisos];
        } finally {
            foreach ($temporales as [$ruta, $borrar]) if ($borrar) @unlink($ruta);
        }
    }

    private static function unir(array $rutas, string $salida): bool
    {
        return ShellTools::unirPdf($rutas, $salida);
    }

    public static function nombreArchivo(Ensamble $e): string
    {
        return date('Y_m_d') . '_' . slug_archivo($e->nombre) . '.pdf';
    }

    /**
     * Guarda (o vuelve a guardar) el PDF en el Archivo del proyecto y lo deja ligado al ensamble.
     * Devuelve null si todo salió bien o el mensaje de error.
     */
    public static function guardarEnArchivo(Ensamble $e, ?int $carpetaId = null): ?string
    {
        $pid = (int)$e->proyecto_id;
        try {
            [$tmp, ] = self::generar($e);
        } catch (\Throwable $x) {
            error_log('Ensamblador de documentos: ' . $x->getMessage());
            return 'No se pudo generar el PDF';
        }
        $a = $e->archivo_id ? Archivo::find($e->archivo_id) : null;
        $nuevo = !$a;
        $tam = (int)filesize($tmp);
        $anterior = $a ? (int)$a->tamano : 0;
        if ($tam > $anterior && !Almacenamiento::cabe($pid, $tam - $anterior)) { @unlink($tmp); return Almacenamiento::mensajeLleno($pid); }
        $dir = Archivo::carpeta($pid);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        if ($nuevo) {
            $a = new Archivo(['proyecto_id' => $pid, 'fecha' => date('Y-m-d'), 'nombre' => $e->nombre, 'carpeta_id' => $carpetaId ?: null]);
            $a->archivo_guardado = bin2hex(random_bytes(12)) . '.pdf';
        }
        if (!@rename($tmp, $dir . '/' . $a->archivo_guardado) && !(@copy($tmp, $dir . '/' . $a->archivo_guardado) && @unlink($tmp))) { @unlink($tmp); return 'No se pudo guardar el archivo'; }
        $a->archivo_original = self::nombreArchivo($e);
        $a->tamano = $tam;
        $a->fecha = date('Y-m-d');
        $a->nombre = $e->nombre;
        $r = $a->guardar();
        if ($nuevo) {
            $e->archivo_id = (int)($r['id'] ?? 0) ?: null;
        }
        $e->firma = self::firma($e);
        $e->guardar();
        return null;
    }

    /** Si el archivo es un ensamble vivo y alguna fuente cambió, regenera su PDF (lo llama Archivo::ruta) */
    public static function refrescar(Archivo $a): void
    {
        static $en = [];
        if (isset($en[$a->id])) return;
        $e = Ensamble::delArchivo((int)$a->id);
        if (!$e || $e->firma === self::firma($e)) return;
        $en[$a->id] = true;
        try {
            [$tmp, ] = self::generar($e);
            $dest = Archivo::carpeta((int)$a->proyecto_id) . '/' . basename((string)$a->archivo_guardado);
            if (@rename($tmp, $dest) || (@copy($tmp, $dest) && @unlink($tmp))) {
                $a->tamano = (int)filesize($dest);
                $a->archivo_original = self::nombreArchivo($e);
                $a->guardar();
                $e->firma = self::firma($e);
                $e->guardar();
            } else @unlink($tmp);
        } catch (\Throwable $x) {
            error_log('Ensamblador de documentos (actualizar): ' . $x->getMessage());   // se conserva la versión anterior
        } finally {
            unset($en[$a->id]);
        }
    }
}
