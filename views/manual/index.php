<?php // Variables: $secciones (ya filtradas por rol y herramientas), $rol
$etiquetas = ['admin' => 'Administrador', 'cliente' => 'Cliente', 'visitante' => 'Visitante'];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-book-open"></i> <?php echo s(t('Manual de usuario')); ?></h2>
        <p class="text-muted">Cómo funciona cada herramienta, sección por sección · estás viendo la guía para <strong><?php echo s($etiquetas[$rol] ?? 'Visitante'); ?></strong></p>
    </div>
</div>

<div class="manual-area">
    <aside class="card manual-indice" id="manual-contenido" aria-label="Índice del manual">
        <h3 class="card-titulo">Contenido</h3>
        <input type="search" id="manual-buscar" placeholder="Buscar en el manual…" aria-label="Buscar en el manual" autocomplete="off">
        <nav id="manual-toc">
            <?php foreach ($secciones as $i => $sec): ?>
                <a href="#<?php echo s($sec['id']); ?>" data-id="<?php echo s($sec['id']); ?>"><i class="fa-solid <?php echo s($sec['icono']); ?>"></i> <span><?php echo ($i + 1) . '. ' . s($sec['titulo']); ?></span></a>
            <?php endforeach; ?>
        </nav>
        <p class="text-muted text-sm" id="manual-sin-resultados" hidden>Ninguna sección coincide.</p>
    </aside>

    <div class="manual-contenido">
        <?php foreach ($secciones as $i => $sec): ?>
            <section class="card manual-seccion" id="<?php echo s($sec['id']); ?>" data-id="<?php echo s($sec['id']); ?>">
                <h3><i class="fa-solid <?php echo s($sec['icono']); ?>"></i> <?php echo ($i + 1) . '. ' . s($sec['titulo']); ?></h3>
                <div class="manual-cuerpo"><?php echo $sec['cuerpo']; // HTML fijo del manual, sin datos de usuario ?></div>
                <p class="manual-roles text-sm text-muted">Disponible para: <?php echo s(implode(' · ', array_map(fn($r) => $etiquetas[$r], $sec['roles']))); ?> <a href="#manual-contenido" class="manual-subir">↑ Contenido</a></p>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    // Buscador: oculta las secciones (y sus enlaces del índice) que no contienen el texto
    const q = document.getElementById('manual-buscar'), vacio = document.getElementById('manual-sin-resultados');
    const secciones = [...document.querySelectorAll('.manual-seccion')];
    const norm = t => t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const textos = new Map(secciones.map(s => [s.dataset.id, norm(s.textContent)]));
    q.addEventListener('input', () => {
        const t = norm(q.value.trim()); let n = 0;
        secciones.forEach(s => {
            const ok = !t || textos.get(s.dataset.id).includes(t);
            s.hidden = !ok; if (ok) n++;
            const a = document.querySelector('#manual-toc a[data-id="' + s.dataset.id + '"]'); if (a) a.hidden = !ok;
        });
        vacio.hidden = n > 0;
    });
})();
</script>
