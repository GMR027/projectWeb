<?php
// Variables: $proyecto, $datos, $puedeEditar
require_once __DIR__ . '/../templates/herramientas-proyecto.php';
$modos = [
    'apagado' => ['fa-toggle-off', 'Apagado', 'Las relaciones se guardan y se ven, pero no se reprograma nada.'],
    'informativo' => ['fa-eye', 'Informativo', 'Muestra qué materiales bloquean actividades y cuánto se atrasarían. Nunca mueve fechas.'],
    'automatico' => ['fa-wand-magic-sparkles', 'Automático', 'Empuja las actividades hasta que sus materiales estén disponibles. Siempre con vista previa y opción de deshacer.'],
];
?>
<?php pestanas_herramienta('materiales', '/materiales/dependencias', $proyecto); ?>
<div class="page-header md-cab">
    <div>
        <h2><i class="fa-solid fa-diagram-project"></i> Dependencias de materiales</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · conecta qué material necesita de otros antes de poder usarse o instalarse. Un material puede requerir varios a la vez.</p>
    </div>
    <div class="md-cab-acciones">
        <a href="/materiales?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost"><i class="fa-solid fa-cubes"></i> Catálogo</a>
        <?php if ($puedeEditar): ?>
            <button type="button" class="btn btn-ghost" id="md-sugerir"><i class="fa-solid fa-wand-magic-sparkles"></i> Sugerir actividades</button>
            <button type="button" class="btn btn-primary" id="md-reprogramar" hidden><i class="fa-solid fa-calendar-check"></i> Revisar y reprogramar</button>
            <button type="button" class="btn btn-ghost" id="md-deshacer" hidden><i class="fa-solid fa-rotate-left"></i> Deshacer</button>
        <?php endif; ?>
    </div>
</div>

<div class="md-modos" role="radiogroup" aria-label="Modo de dependencias de materiales">
    <?php foreach ($modos as $k => [$ic, $nom, $desc]): ?>
        <button type="button" class="md-modo" data-modo="<?php echo $k; ?>" role="radio" aria-checked="false"<?php echo $puedeEditar ? '' : ' disabled'; ?>>
            <span class="md-modo-ic"><i class="fa-solid <?php echo $ic; ?>"></i></span>
            <strong><?php echo s($nom); ?></strong>
            <span><?php echo s($desc); ?></span>
        </button>
    <?php endforeach; ?>
</div>

<div id="md-aviso" class="md-aviso" hidden aria-live="polite"></div>
<div id="md-kpis" class="md-kpis" aria-live="polite"></div>

<div class="card md-barra">
    <div class="md-barra-filtros">
        <label class="md-busca"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="md-buscar" placeholder="Buscar material, referencia o especialidad…" aria-label="Buscar material" autocomplete="off"></label>
        <select id="md-esp" class="btn btn-ghost btn-sm" aria-label="Filtrar por especialidad"><option value="">Todas las especialidades</option></select>
        <select id="md-estado" class="btn btn-ghost btn-sm" aria-label="Filtrar por disponibilidad">
            <option value="">Todos los estados</option>
            <option value="bloqueado">Bloqueados</option>
            <option value="listo">Disponibles</option>
            <option value="sinrequisitos">Sin requisitos</option>
        </select>
        <label class="campo-check md-solo"><input type="checkbox" id="md-solo"> Solo con relaciones</label>
    </div>
    <div class="md-barra-vistas" role="group" aria-label="Vista">
        <button type="button" class="md-vista activa" data-vista="cadenas" title="Tablero de cadenas" aria-pressed="true"><i class="fa-solid fa-diagram-project"></i><span>Cadenas</span></button>
        <button type="button" class="md-vista" data-vista="lista" title="Lista de materiales" aria-pressed="false"><i class="fa-solid fa-list"></i><span>Lista</span></button>
    </div>
    <?php if ($puedeEditar): ?>
        <button type="button" class="btn btn-sm btn-primary" id="md-conectar"><i class="fa-solid fa-link"></i> Conectar materiales</button>
    <?php endif; ?>
</div>

<div class="md-layout">
    <div class="md-main">
        <div class="card md-tablero" id="md-tablero">
            <div class="md-tablero-cab">
                <span class="md-leyenda"><i class="md-dot listo"></i> Disponible</span>
                <span class="md-leyenda"><i class="md-dot justo"></i> Llega justo</span>
                <span class="md-leyenda"><i class="md-dot bloqueado"></i> Bloqueado o sin fecha</span>
                <span class="md-leyenda"><i class="md-dot sinreq"></i> Sin requisitos</span>
                <span class="md-ayuda"><i class="fa-regular fa-lightbulb"></i> Pulsa un material para ver y conectar sus requisitos.</span>
            </div>
            <div class="md-scroll" id="md-scroll">
                <div class="md-cols" id="md-cols"></div>
                <svg class="md-lineas" id="md-svg" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"></svg>
            </div>
            <div class="md-vacio" id="md-vacio" hidden>
                <i class="fa-solid fa-diagram-project"></i>
                <h3>Conecta tus materiales</h3>
                <p>Empieza con el material que debe estar primero (por ejemplo, <strong>soportería</strong> o <strong>muro</strong>) y conéctalo con el que lo necesita (<strong>cable</strong> o <strong>pintura</strong>).</p>
                <?php if ($puedeEditar): ?><button type="button" class="btn btn-primary" id="md-vacio-conectar"><i class="fa-solid fa-link"></i> Conectar el primero</button><?php endif; ?>
            </div>
        </div>
        <div class="card md-lista" id="md-lista" hidden>
            <div class="tabla-wrapper">
                <table class="tabla tabla-md">
                    <thead><tr><th>Material</th><th class="hide-m">Especialidad</th><th>Compra</th><th class="hide-m">Entrega</th><th class="num">Requiere</th><th class="num hide-m">Requerido por</th><th>Disponible</th><th class="hide-m">Se necesita</th><th>Estado</th></tr></thead>
                    <tbody id="md-lista-cuerpo"></tbody>
                </table>
            </div>
        </div>
    </div>
    <aside class="card md-panel" id="md-panel" aria-live="polite">
        <p class="text-muted">Elige un material para ver qué requiere, quién lo requiere y qué actividades lo usan.</p>
    </aside>
</div>

<?php if ($puedeEditar): ?>
<div class="modal-overlay" id="md-modal-conectar" aria-hidden="true">
    <div class="modal-card md-modal" role="dialog" aria-modal="true" aria-labelledby="md-mc-t">
        <div class="md-modal-cab">
            <div><h3 id="md-mc-t">Conectar materiales</h3><p class="text-muted text-sm">Elige qué materiales deben estar disponibles antes. Puedes marcar varios.</p></div>
            <button type="button" class="btn btn-ghost btn-sm" data-cerrar="md-modal-conectar" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="md-mc-suc" id="md-mc-suc"></div>
        <label class="md-busca md-mc-busca"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="md-mc-buscar" placeholder="Buscar materiales que debe requerir…" autocomplete="off"></label>
        <div class="md-mc-lista" id="md-mc-lista"></div>
        <div class="md-mc-sel" id="md-mc-sel"></div>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" data-cerrar="md-modal-conectar">Cancelar</button>
            <button type="button" class="btn btn-primary" id="md-mc-guardar"><i class="fa-solid fa-link"></i> Conectar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="md-modal-previa" aria-hidden="true">
    <div class="modal-card md-modal" role="dialog" aria-modal="true" aria-labelledby="md-mp-t">
        <div class="md-modal-cab">
            <div><h3 id="md-mp-t">Reprogramación por materiales</h3><p class="text-muted text-sm" id="md-mp-txt"></p></div>
            <button type="button" class="btn btn-ghost btn-sm" data-cerrar="md-modal-previa" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="md-mp-cuerpo" class="md-mp-cuerpo"></div>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" data-cerrar="md-modal-previa">Cancelar</button>
            <?php if ($puedeEditar): ?><button type="button" class="btn btn-primary" id="md-mp-aplicar" disabled><i class="fa-solid fa-check"></i> Aplicar</button><?php endif; ?>
        </div>
    </div>
</div>

<?php if ($puedeEditar): ?>
<div class="modal-overlay" id="md-modal-sugerencias" aria-hidden="true">
    <div class="modal-card md-modal md-modal-ancho" role="dialog" aria-modal="true" aria-labelledby="md-ms-t">
        <div class="md-modal-cab">
            <div><h3 id="md-ms-t"><i class="fa-solid fa-wand-magic-sparkles"></i> Sugerencias a partir de las cadenas</h3><p class="text-muted text-sm">Revisa y edita antes de aceptar. Nada se guarda hasta que pulses «Aplicar seleccionadas». Si una actividad existente recibe un prerrequisito nuevo, se moverá para respetarlo.</p></div>
            <button type="button" class="btn btn-ghost btn-sm" data-cerrar="md-modal-sugerencias" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="md-ms-tabs" role="tablist">
            <button type="button" class="md-ms-tab activa" data-tab="rel" role="tab"><i class="fa-solid fa-link"></i> Relaciones nuevas <span id="md-ms-nrel">0</span></button>
            <button type="button" class="md-ms-tab" data-tab="act" role="tab"><i class="fa-solid fa-list-check"></i> Actividades nuevas <span id="md-ms-nact">0</span></button>
        </div>
        <div id="md-ms-cuerpo" class="md-ms-cuerpo"><p class="text-muted">Calculando sugerencias…</p></div>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" data-cerrar="md-modal-sugerencias">Cancelar</button>
            <button type="button" class="btn btn-primary" id="md-ms-aplicar"><i class="fa-solid fa-check"></i> Aplicar seleccionadas</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
window.DEPMAT = <?php echo json_encode([
    'proyecto' => (int)$proyecto->id,
    'puedeEditar' => (bool)$puedeEditar,
    'datos' => $datos,
    'requisitos' => \Classes\DependenciasMateriales::REQUISITOS,
    'roles' => \Classes\DependenciasMateriales::ROLES,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
<script src="/vendor/dependencias-materiales.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/dependencias-materiales.js'); ?>"></script>
