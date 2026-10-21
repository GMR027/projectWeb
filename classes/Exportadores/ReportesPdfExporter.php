<?php

namespace Classes\Exportadores;

use Controllers\MinutaController;
use Classes\Pdf;
use Model\Bitacora;
use Model\Evidencia;
use Model\Material;
use Model\Minuta;
use Model\Personal;
use Model\ReporteDiario;
use Model\Submittal;

/**
 * Reportes de obra en PDF (carta vertical): reporte diario, bitácora, minutas, submittals, materiales y evidencias.
 * Los filtros se combinan; los vacíos no filtran. Para fechas se aplica el primero que venga: día, semana o mes.
 */
class ReportesPdfExporter extends BaseExporter
{
    public const TIPOS = [
        'reporte' => 'Reporte diario', 'bitacora' => 'Bitácora', 'minutas' => 'Minutas',
        'submittals' => 'Submittals', 'materiales' => 'Materiales', 'evidencias' => 'Evidencias',
    ];

    private string $tipo = 'bitacora';
    private array $f = [];

    public function setOpciones(string $tipo, array $filtros): self
    {
        if (!isset(self::TIPOS[$tipo])) throw new \Exception('Tipo de reporte no soportado');
        $this->tipo = $tipo;
        $this->f = $filtros;
        return $this;
    }

    protected function prefijoNombre(): string
    {
        return self::TIPOS[$this->tipo];
    }

    /** [desde, hasta, texto] según día, semana o mes; null si no hay filtro de fecha */
    private function rango(): ?array
    {
        $dia = $this->fechaValida($this->f['fecha'] ?? '');
        if ($dia) return [$dia, $dia, fecha_larga_es($dia)];
        $sem = $this->fechaValida($this->f['semana'] ?? '');
        if ($sem) {
            $lunes = (new \DateTime($sem))->modify('monday this week');
            $dom = (clone $lunes)->modify('+6 days');
            return [$lunes->format('Y-m-d'), $dom->format('Y-m-d'), 'Semana del ' . fecha_corta_es($lunes->format('Y-m-d')) . ' al ' . fecha_corta_es($dom->format('Y-m-d'))];
        }
        $mes = (string)($this->f['mes'] ?? '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            return [$mes . '-01', date('Y-m-t', strtotime($mes . '-01')), ucfirst($meses[(int)substr($mes, 5)]) . ' ' . substr($mes, 0, 4)];
        }
        return null;
    }

    private function fechaValida(string $f): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $f);
        return $d && $d->format('Y-m-d') === $f ? $f : null;
    }

    private function enRango(?array $r, ?string $fecha): bool
    {
        return !$r || ($fecha >= $r[0] && $fecha <= $r[1]);
    }

    /** Foto reducida (máx. 900 px) como data URI para no desbordar la memoria de Dompdf */
    public static function foto(string $carpeta, ?string $archivo): ?string
    {
        if (!$archivo) return null;
        $ruta = __DIR__ . '/../../public/uploads/' . $carpeta . '/' . basename($archivo);
        if (!is_file($ruta)) return null;
        $datos = @file_get_contents($ruta);
        $im = $datos ? @imagecreatefromstring($datos) : false;
        if (!$im) return null;
        $w = imagesx($im);
        $h = imagesy($im);
        $k = min(1, 900 / max($w, $h));
        if ($k < 1) {
            $nuevo = imagescale($im, max(1, (int)round($w * $k)), max(1, (int)round($h * $k)));
            if ($nuevo) $im = $nuevo;
        }
        ob_start();
        imagejpeg($im, null, 78);
        return 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
    }

    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $pid = (int)$proyecto->id;
        $rango = $this->rango();
        $d = ['tipo' => $this->tipo, 'titulo' => self::TIPOS[$this->tipo], 'proyecto' => $proyecto, 'criterios' => [], 'rango' => $rango];
        if ($rango) $d['criterios'][] = $rango[2];

        switch ($this->tipo) {
            case 'reporte':   $this->datosReporte($d, $pid, $rango); break;
            case 'bitacora':  $this->datosBitacora($d, $pid, $rango); break;
            case 'minutas':   $this->datosMinutas($d, $pid, $rango); break;
            case 'submittals': $this->datosSubmittals($d, $pid); break;
            case 'materiales': $this->datosMateriales($d, $pid); break;
            case 'evidencias': $this->datosEvidencias($d, $pid, $rango); break;
        }
        if (!$d['registros']) throw new \Exception('No hay registros de ' . mb_strtolower(self::TIPOS[$this->tipo]) . ' con esos filtros');

        $dueno = \Model\Usuario::find($proyecto->usuario_id);
        $d['logoE'] = MsrPdfExporter::imagenBase64($dueno && $dueno->logo ? $dueno->logo : $proyecto->logo, 'build/img/logos');
        $d['logoC'] = MsrPdfExporter::imagenBase64($proyecto->contratista_logo, 'build/img/logos');
        $d['img'] = fn(?string $a, string $c) => MsrPdfExporter::imagenBase64($a, $c);
        extract($d);

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-reportes.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'letter', 'portrait', ['isHtml5ParserEnabled' => true]);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }

    // ------------------------------ datos por tipo ------------------------------

    /** [id => [Actividad, Partida]] */
    private function actividades(): array
    {
        $r = [];
        foreach ($this->proyecto->getPartidas() as $p) {
            foreach ($p->getActividades() as $a) $r[(int)$a->id] = [$a, $p];
        }
        return $r;
    }

    private function datosReporte(array &$d, int $pid, ?array $rango): void
    {
        if (!$rango) throw new \Exception('Elige el día o el mes del reporte diario');
        $acts = $this->actividades();
        $personal = Personal::indexado($pid);
        $reg = [];
        foreach (array_reverse(ReporteDiario::deProyecto($pid)) as $r) {
            if (!$this->enRango($rango, $r->fecha)) continue;
            $reg[] = [
                'reporte' => $r,
                'actividades' => array_values(array_filter(array_map(fn($id) => $acts[$id] ?? null, $r->getActividades()))),
                'personal' => array_values(array_filter(array_map(fn($id) => $personal[$id] ?? null, $r->getPersonal()))),
                'fotos' => array_values(array_filter(array_map(fn($f) => self::foto('reportes', $f), $r->getFotos()))),
            ];
        }
        $d['registros'] = $reg;
    }

    private function datosBitacora(array &$d, int $pid, ?array $rango): void
    {
        $dep = trim((string)($this->f['departamento'] ?? ''));
        $imp = (string)($this->f['importancia'] ?? '');
        if ($dep !== '') $d['criterios'][] = 'Departamento: ' . $dep;
        if (isset(Bitacora::IMPORTANCIAS[$imp])) $d['criterios'][] = 'Importancia: ' . Bitacora::IMPORTANCIAS[$imp];
        $reg = [];
        foreach (array_reverse(Bitacora::deProyecto($pid)) as $b) {
            if (!$this->enRango($rango, $b->fecha)) continue;
            if ($dep !== '' && $b->departamento !== $dep) continue;
            if (isset(Bitacora::IMPORTANCIAS[$imp]) && $b->importancia !== $imp) continue;
            $reg[] = $b;
        }
        $d['registros'] = $reg;
    }

    private function datosMinutas(array &$d, int $pid, ?array $rango): void
    {
        $imp = (string)($this->f['importancia'] ?? '');
        if (isset(Bitacora::IMPORTANCIAS[$imp])) $d['criterios'][] = 'Importancia: ' . Bitacora::IMPORTANCIAS[$imp];
        $reg = [];
        foreach (array_reverse(Minuta::deProyecto($pid)) as $m) {
            if (!$this->enRango($rango, $m->fecha)) continue;
            if (isset(Bitacora::IMPORTANCIAS[$imp]) && $m->importancia !== $imp) continue;
            $reg[] = ['minuta' => $m] + MinutaController::detalle($m, $pid);
        }
        $d['registros'] = $reg;
    }

    private function datosSubmittals(array &$d, int $pid): void
    {
        $ids = array_map('intval', (array)($this->f['submittal_ids'] ?? []));
        $reg = [];
        foreach (Submittal::deProyecto($pid) as $s) {
            if ($ids && !in_array((int)$s->id, $ids, true)) continue;
            $reg[] = ['submittal' => $s, 'materiales' => $s->materiales()];
        }
        $d['criterios'][] = $ids ? count($reg) . ' submittal(s) elegido(s)' : 'Todos los submittals';
        $d['registros'] = $reg;
    }

    private function datosMateriales(array &$d, int $pid): void
    {
        $esp = trim((string)($this->f['especialidad'] ?? ''));
        $compra = (string)($this->f['compra'] ?? '');
        $entrega = (string)($this->f['entrega'] ?? '');
        if ($esp !== '') $d['criterios'][] = 'Especialidad: ' . $esp;
        if (isset(Material::COMPRA[$compra])) $d['criterios'][] = 'Compra: ' . Material::COMPRA[$compra];
        if ($entrega === 'entregado') $d['criterios'][] = 'Entregados';
        if ($entrega === 'por_entregar') $d['criterios'][] = 'Por entregar';
        $reg = [];
        foreach (Material::consultarSQL("SELECT * FROM materiales WHERE proyecto_id = $pid ORDER BY especialidad ASC, nombre ASC") as $m) {
            if ($esp !== '' && $m->especialidad !== $esp) continue;
            if (isset(Material::COMPRA[$compra]) && $m->compra !== $compra) continue;
            if ($entrega === 'entregado' && !$m->fecha_entrega_real) continue;
            if ($entrega === 'por_entregar' && $m->fecha_entrega_real) continue;
            $reg[] = $m;
        }
        $d['registros'] = $reg;
    }

    private function datosEvidencias(array &$d, int $pid, ?array $rango): void
    {
        $acts = $this->actividades();
        $actId = (int)($this->f['actividad_id'] ?? 0);
        if ($actId) $d['criterios'][] = 'Actividad: ' . (isset($acts[$actId]) ? $acts[$actId][0]->nombre : '#' . $actId);
        $dias = [];   // fecha => [['foto', 'texto', 'actividad']]
        foreach (ReporteDiario::deProyecto($pid) as $r) {
            if (!$this->enRango($rango, $r->fecha)) continue;
            if ($actId && !in_array($actId, $r->getActividades(), true)) continue;
            $nombres = [];
            foreach ($r->getActividades() as $id) if (isset($acts[$id])) $nombres[] = $acts[$id][0]->nombre;
            foreach ($r->getFotos() as $f) {
                $dias[$r->fecha][] = ['archivo' => $f, 'carpeta' => 'reportes', 'texto' => trim($r->area . ($r->comentarios ? ' · ' . mb_substr($r->comentarios, 0, 140) : '')), 'actividad' => implode(', ', array_slice($nombres, 0, 3))];
            }
        }
        try {
            foreach (Evidencia::deProyecto($pid) as $e) {
                if (!$this->enRango($rango, $e->fecha)) continue;
                if ($actId && (int)$e->actividad_id !== $actId) continue;
                $dias[$e->fecha][] = ['archivo' => $e->foto, 'carpeta' => Evidencia::CARPETA, 'texto' => $e->descripcion, 'actividad' => $e->actividad_id && isset($acts[(int)$e->actividad_id]) ? $acts[(int)$e->actividad_id][0]->nombre : ''];
            }
        } catch (\Throwable $ex) {   // migración de evidencias pendiente
        }
        ksort($dias);
        $d['registros'] = $dias;
    }
}
