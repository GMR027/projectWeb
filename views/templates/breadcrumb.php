<?php
// Helper para generar breadcrumbs de navegación del proyecto
function breadcrumb_proyecto($proyecto, $pagina_actual = '') {
    if (!$proyecto) return;
    require_once __DIR__ . '/herramientas-proyecto.php';
    $grupos = ['Navegación' => [['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos', 'key' => 'proyectos', 'ayuda' => ayudas_herramientas()['/proyectos'][0], 'manual' => 'inicio']]] + herramientas_proyecto($proyecto);
    ?>
    <nav class="breadcrumb-nav" aria-label="<?php echo s(t('Herramientas del proyecto')); ?>">
        <div class="breadcrumb-container">
            <?php foreach ($grupos as $nombre => $enGrupo): ?>
                <div class="breadcrumb-grupo">
                    <div class="breadcrumb-iconos">
                        <?php foreach ($enGrupo as $item): ?>
                            <a href="<?php echo s($item['url']); ?>"
                               class="breadcrumb-item <?php echo $pagina_actual === $item['key'] ? 'active' : ''; ?>"
                               aria-label="<?php echo s(t($item['label'])); ?>"<?php echo atributos_ayuda($item, true); ?>>
                                <i class="fa-solid <?php echo s($item['icon']); ?>"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <span class="breadcrumb-categoria"><?php echo s(t($nombre)); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
}
