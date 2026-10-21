<?php

namespace Classes;

/**
 * Guía «Por dónde empezar» de un proyecto nuevo (modal al crearlo y sección en Ajustes del proyecto). El estado de cada paso
 * se calcula con los datos reales del proyecto, sin banderas guardadas; lo que se captura aquí se guarda en las mismas tablas
 * que usa cada herramienta.
 */
class PorDondeEmpezar
{
    use AccesoDb;

    public const MODOS = ['guiado' => 'Paso a paso', 'lista' => 'Lista de pasos'];

    private static function n(string $sql): int
    {
        return (int)(self::filas($sql)[0]['n'] ?? 0);
    }

    /** Pasos en el orden recomendado. Los exclusivos del administrador solo se incluyen si $admin */
    public static function pasos(int $pid, bool $admin): array
    {
        $cuentas = [
            'calendario' => self::n("SELECT COUNT(*) n FROM calendario_festivos WHERE proyecto_id = $pid"),
            'programa' => self::n("SELECT COUNT(*) n FROM partidas WHERE proyecto_id = $pid"),
            'equipo' => self::n("SELECT COUNT(*) n FROM personal WHERE proyecto_id = $pid"),
            'materiales' => self::n("SELECT COUNT(*) n FROM materiales WHERE proyecto_id = $pid"),
            'documentos' => self::n("SELECT COUNT(*) n FROM archivos WHERE proyecto_id = $pid"),
            'contrato' => self::n("SELECT COUNT(*) n FROM proyecto_fechas_contrato WHERE proyecto_id = $pid"),
        ];
        $pasos = [
            ['id' => 'calendario', 'icono' => 'fa-calendar-days', 'titulo' => 'Calendario de trabajo',
                'porque' => 'Todas las fechas del programa se calculan con la jornada semanal y los días festivos. Ya trae lunes a viernes completos y sábado medio día; agrega los festivos que apliquen.',
                'unidad' => 'festivo(s)', 'herramienta' => ['Ajustes del proyecto', "/ajustes?proyecto=$pid#jornada"]],
            ['id' => 'programa', 'icono' => 'fa-list-check', 'titulo' => 'Programa de obra',
                'porque' => 'El programa (partidas y actividades) es la base de todo lo demás. Importa el que ya tengas en MS Project o crea la primera partida.',
                'unidad' => 'partida(s)', 'herramienta' => ['Importar XML / POD', "/programa/importar?proyecto=$pid"]],
            ['id' => 'equipo', 'icono' => 'fa-users', 'titulo' => 'Equipo del proyecto',
                'porque' => 'Registra a las personas para asignarlas como responsables y usarlas en reportes.',
                'unidad' => 'persona(s)', 'herramienta' => ['Personal', "/reportes/personal?proyecto=$pid"]],
            ['id' => 'materiales', 'icono' => 'fa-boxes-stacked', 'titulo' => 'Materiales',
                'porque' => 'Captura los materiales clave (o importa un MSR) para dar seguimiento a compras y entregas.',
                'unidad' => 'material(es)', 'herramienta' => ['Importar MSR', "/msr/importar?proyecto=$pid"]],
            ['id' => 'documentos', 'icono' => 'fa-folder-open', 'titulo' => 'Documentos del proyecto',
                'porque' => 'Sube contrato, planos y especificaciones al Archivo para tenerlos a la mano y ligarlos después.',
                'unidad' => 'archivo(s)', 'herramienta' => ['Archivo', "/archivos?proyecto=$pid"]],
            ['id' => 'contrato', 'icono' => 'fa-file-signature', 'titulo' => 'Fechas del contrato',
                'porque' => 'Con las fechas contractuales el sistema avisa si el programa termina después del contrato.',
                'unidad' => 'fecha(s)', 'herramienta' => ['Datos de obra', "/datos-obra?proyecto=$pid"]],
        ];
        foreach ($pasos as &$p) {
            $p['cuenta'] = $cuentas[$p['id']];
            $p['hecho'] = $p['cuenta'] > 0;
        }
        unset($p);
        if ($admin) {
            $pasos[] = ['id' => 'ia', 'icono' => 'fa-wand-magic-sparkles', 'titulo' => 'Ensamblador de documentos (IA)', 'solo_admin' => true,
                'porque' => 'Desde la aplicación de escritorio puedes subir contratos y especificaciones y dejar que la IA proponga partidas, materiales y fechas. Nada cambia sin que lo aceptes.',
                'unidad' => '', 'cuenta' => 0, 'hecho' => false, 'herramienta' => null];
        }
        return $pasos;
    }

    /** Avance: [pasos hechos, total] sin contar los informativos */
    public static function avance(array $pasos): array
    {
        $reales = array_filter($pasos, fn($p) => empty($p['solo_admin']));
        return [count(array_filter($reales, fn($p) => $p['hecho'])), count($reales)];
    }
}
