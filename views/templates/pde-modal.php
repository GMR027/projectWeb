<?php
// Ventana «Por dónde empezar» al crear un proyecto (?inicio=guiado|lista&proyecto=ID). Modo «ninguno» no muestra nada.
$pdeModo = (string)($_GET['inicio'] ?? '');
$pdeProyecto = isset(\Classes\PorDondeEmpezar::MODOS[$pdeModo]) ? \Model\Proyecto::find(filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
if ($pdeProyecto && is_auth() && puede_gestionar_proyecto($pdeProyecto)): ?>
<div class="modal-overlay abierto" id="pde-modal" aria-hidden="false">
    <div class="modal-card pde-modal-card" role="dialog" aria-modal="true" aria-labelledby="pde-titulo">
        <div class="pde-modal-titulo">
            <h3 id="pde-titulo"><i class="fa-solid fa-flag-checkered"></i> Por dónde empezar · <?php echo s($pdeProyecto->nombre); ?></h3>
            <button type="button" class="btn btn-ghost" id="pde-cerrar" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="text-muted text-sm">Tu proyecto ya está creado. Estos son los pasos recomendados; puedes cerrar y volver en <a href="/ajustes?proyecto=<?php echo (int)$pdeProyecto->id; ?>#por-donde-empezar">Ajustes del proyecto</a>.</p>
        <?php $pdeEnModal = true; include __DIR__ . '/por-donde-empezar.php'; ?>
    </div>
</div>
<?php endif; ?>
