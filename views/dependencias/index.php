<?php // Variables: $proyecto, $datos, $puedeEditar
$modos = [
    'apagado' => ['fa-toggle-off', 'Apagado', 'Las fechas se escriben a mano, como siempre. Las relaciones que captures se guardan pero no se calcula nada.'],
    'informativo' => ['fa-eye', 'Informativo', 'Calcula la ruta crítica, las holguras y los conflictos, y los dibuja. Nunca mueve fechas.'],
    'automatico' => ['fa-wand-magic-sparkles', 'Automático', 'Si una actividad se atrasa, sus sucesoras se recorren solas con tu calendario. Siempre con vista previa y con opción de deshacer.'],
];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-diagram-project"></i> <?php echo s(t('Dependencias de obra')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · qué actividad debe terminar (o empezar) antes de que otra pueda empezar (o terminar)</p>
    </div>
    <a href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← <?php echo s(t('Programa')); ?></a>
</div>

<div class="dep-modos" role="radiogroup" aria-label="Modo de dependencias">
    <?php foreach ($modos as $k => [$ic, $nom, $desc]): ?>
        <button type="button" class="dep-modo" data-modo="<?php echo $k; ?>" role="radio" aria-checked="false"<?php echo $puedeEditar ? '' : ' disabled'; ?>>
            <i class="fa-solid <?php echo $ic; ?>"></i><strong><?php echo s($nom); ?></strong><span><?php echo s($desc); ?></span>
        </button>
    <?php endforeach; ?>
</div>

<div id="dep-aviso" class="dep-aviso" hidden aria-live="polite"></div>
<div id="dep-resumen" class="dep-resumen"></div>

<div class="card dep-barra">
    <label>Partida <select id="dep-partida"><option value="">Todas</option></select></label>
    <label class="campo-check"><input type="checkbox" id="dep-solo"> Solo actividades con relaciones</label>
    <input type="search" id="dep-buscar" placeholder="Buscar actividad…" aria-label="Buscar actividad">
    <span class="dep-zoom" role="group" aria-label="Zoom del diagrama">
        <button type="button" class="btn btn-sm btn-ghost" id="dep-menos" title="Alejar (Ctrl + rueda)" aria-label="Alejar"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
        <span class="dep-zoom-pct" id="dep-zoom-pct" aria-live="polite">100%</span>
        <button type="button" class="btn btn-sm btn-ghost" id="dep-mas" title="Acercar (Ctrl + rueda)" aria-label="Acercar"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        <button type="button" class="btn btn-sm btn-ghost" id="dep-ajustar" title="Ajustar todo el programa al ancho disponible"><i class="fa-solid fa-expand"></i> Ajustar</button>
    </span>
    <?php if ($puedeEditar): ?>
        <button type="button" class="btn btn-sm btn-ghost" id="dep-enlazar" title="Clic en la predecesora y luego en la sucesora. También puedes arrastrar desde el círculo del extremo de una barra hasta otra barra."><i class="fa-solid fa-link"></i> Enlazar</button>
        <button type="button" class="btn btn-sm btn-primary" id="dep-reprogramar" hidden><i class="fa-solid fa-calendar-check"></i> Revisar y reprogramar</button>
        <button type="button" class="btn btn-sm btn-ghost" id="dep-deshacer" hidden><i class="fa-solid fa-rotate-left"></i> Deshacer la última reprogramación</button>
    <?php endif; ?>
</div>
<p class="text-muted text-sm dep-ayuda"><?php echo $puedeEditar ? 'Haz clic en una barra para ver y editar sus relaciones. Para crear una: botón «Enlazar» (predecesora → sucesora) o arrastra desde el círculo que aparece al final de una barra hasta otra barra.' : 'Haz clic en una barra para ver sus relaciones.'; ?></p>

<div class="dep-area">
    <div class="card dep-lienzo">
        <div class="dep-leyenda">
            <span><i class="dep-sw" style="background:#3b82f6"></i> Normal (color de su partida)</span><span><i class="dep-sw" style="background:#dc2626"></i> Ruta crítica</span><span><i class="dep-sw dep-sw-conf"></i> Conflicto</span><span><i class="dep-sw dep-sw-fan"></i> Fecha calculada</span><span>📌 Fecha fija</span>
        </div>
        <div class="dep-scroll" id="dep-scroll"><div class="dep-labels" id="dep-labels"></div><svg id="dep-svg" class="dep-svg" xmlns="http://www.w3.org/2000/svg"></svg></div>
    </div>
    <aside class="card dep-panel" id="dep-panel"><p class="text-muted">Elige una actividad para ver sus predecesoras y sucesoras.</p></aside>
</div>

<div class="modal-overlay" id="dep-modal" aria-hidden="true">
    <div class="modal-card dep-modal-card" role="dialog" aria-modal="true" aria-labelledby="dep-modal-t">
        <h3 id="dep-modal-t">Reprogramación</h3>
        <p class="text-muted text-sm" id="dep-modal-txt"></p>
        <div class="tabla-wrapper" style="max-height:44vh;overflow:auto"><table class="tabla"><thead><tr><th>Actividad</th><th>Inicio</th><th>Fin</th><th class="num">Días</th></tr></thead><tbody id="dep-modal-filas"></tbody></table></div>
        <div class="modal-acciones"><button type="button" class="btn btn-ghost" id="dep-modal-no">Cancelar</button><button type="button" class="btn btn-primary" id="dep-modal-si"><i class="fa-solid fa-check"></i> Aplicar</button></div>
    </div>
</div>

<script>window.DEP = <?php echo json_encode(['proyecto' => (int)$proyecto->id, 'puedeEditar' => (bool)$puedeEditar, 'datos' => $datos], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;</script>
<script src="/vendor/dependencias.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/dependencias.js'); ?>"></script>
