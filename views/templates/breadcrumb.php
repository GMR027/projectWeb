<?php
// Helper para generar breadcrumbs de navegación del proyecto
// $modo: clásico (iconos por sección), panel (sin barra; el menú lateral queda fijo) o pestañas (una pestaña por sección).
function breadcrumb_proyecto($proyecto, $pagina_actual = '', $modo = 'clasico') {
    if (!$proyecto) return;
    if (\Classes\TemaProyecto::normMenu($modo) === 'panel') return;   // el menú lateral hace de navegación
    require_once __DIR__ . '/herramientas-proyecto.php';
    $grupos = ['Navegación' => [
        ['url' => '/dashboard', 'icon' => 'fa-house', 'label' => 'Inicio', 'key' => 'dashboard', 'match' => '/dashboard', 'ayuda' => ayudas_herramientas()['/dashboard'][0], 'manual' => 'inicio'],
        ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos', 'key' => 'proyectos', 'match' => '/proyectos', 'ayuda' => ayudas_herramientas()['/proyectos'][0], 'manual' => 'inicio'],
    ]] + herramientas_proyecto($proyecto);

    // Herramienta de la página actual: gana la ruta más específica (/materiales/dependencias antes que /materiales).
    $rutaActual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $claveActiva = null;
    $largoActivo = -1;
    $grupoActivo = 0;
    $i = 0;
    foreach ($grupos as $enGrupo) {
        foreach ($enGrupo as $item) {
            $m = $item['match'];
            if (($rutaActual === $m || str_starts_with($rutaActual, $m . '/')) && strlen($m) > $largoActivo) {
                $largoActivo = strlen($m);
                $claveActiva = $item['key'];
                $grupoActivo = $i;
            }
        }
        $i++;
    }
    $esActiva = fn($item) => $claveActiva !== null && $item['key'] === $claveActiva;

    if (\Classes\TemaProyecto::normMenu($modo) === 'pestanas'): ?>
        <nav class="breadcrumb-nav breadcrumb-pestanas" aria-label="<?php echo s(t('Herramientas del proyecto')); ?>">
            <div class="bpest-tabs" role="tablist" aria-label="<?php echo s(t('Secciones de herramientas')); ?>">
                <?php $i = 0; foreach ($grupos as $nombre => $enGrupo): ?>
                    <button type="button" role="tab" class="bpest-tab<?php echo $i === $grupoActivo ? ' active' : ''; ?>"
                            id="bpest-tab-<?php echo $i; ?>" aria-selected="<?php echo $i === $grupoActivo ? 'true' : 'false'; ?>"
                            aria-controls="bpest-panel-<?php echo $i; ?>" tabindex="<?php echo $i === $grupoActivo ? '0' : '-1'; ?>"><?php echo s(t($nombre)); ?></button>
                <?php $i++; endforeach; ?>
            </div>
            <?php $i = 0; foreach ($grupos as $nombre => $enGrupo): ?>
                <div class="bpest-panel" id="bpest-panel-<?php echo $i; ?>" role="tabpanel" aria-labelledby="bpest-tab-<?php echo $i; ?>"<?php echo $i === $grupoActivo ? '' : ' hidden'; ?>>
                    <div class="breadcrumb-iconos">
                        <?php foreach ($enGrupo as $item): ?>
                            <a href="<?php echo s($item['url']); ?>"
                               class="breadcrumb-item <?php echo $esActiva($item) ? 'active' : ''; ?>"
                               aria-label="<?php echo s(t($item['label'])); ?>"<?php echo atributos_ayuda($item, true); ?><?php echo $esActiva($item) ? ' aria-current="page"' : ''; ?>>
                                <i class="fa-solid <?php echo s($item['icon']); ?>"></i>
                                <span class="breadcrumb-nombre"><?php echo s(t($item['label'])); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php $i++; endforeach; ?>
        </nav>
    <?php else: ?>
        <nav class="breadcrumb-nav" aria-label="<?php echo s(t('Herramientas del proyecto')); ?>">
            <div class="breadcrumb-container">
                <?php foreach ($grupos as $nombre => $enGrupo): ?>
                    <div class="breadcrumb-grupo">
                        <div class="breadcrumb-iconos">
                            <?php foreach ($enGrupo as $item): ?>
                                <a href="<?php echo s($item['url']); ?>"
                                   class="breadcrumb-item <?php echo $esActiva($item) ? 'active' : ''; ?>"
                                   aria-label="<?php echo s(t($item['label'])); ?>"<?php echo atributos_ayuda($item, true); ?><?php echo $esActiva($item) ? ' aria-current="page"' : ''; ?>>
                                    <i class="fa-solid <?php echo s($item['icon']); ?>"></i>
                                    <span class="breadcrumb-nombre"><?php echo s(t($item['label'])); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <span class="breadcrumb-categoria"><?php echo s(t($nombre)); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </nav>
    <?php endif;
}
