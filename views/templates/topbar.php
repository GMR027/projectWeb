<header class="topbar">
    <div class="topbar-left">
        <h1 class="page-title"><?php echo s(t($titulo ?? '')); ?></h1>
        <?php if (isset($proyecto)): ?>
            <div class="proyecto-periodo">
                <?php
                $inicio = new DateTime($proyecto->fecha_inicio);
                $fin = new DateTime($proyecto->fecha_fin);
                $diff = $inicio->diff($fin);
                $totalDias = $diff->days;
                $meses = $diff->m + ($diff->y * 12);
                $diasRestantes = $diff->d;
                ?>
                <span class="periodo-item">
                    <i class="fa-solid fa-calendar-day"></i>
                    <strong><?php echo $totalDias; ?></strong> días totales
                </span>
                <span class="periodo-separator">•</span>
                <span class="periodo-item">
                    <i class="fa-solid fa-calendar-week"></i>
                    <strong><?php echo $meses; ?></strong> meses<?php echo $diasRestantes > 0 ? " y <strong>$diasRestantes</strong> días" : ''; ?>
                </span>
                <span class="periodo-separator">•</span>
                <span class="periodo-item">
                    <i class="fa-solid fa-play"></i>
                    <?php echo $inicio->format('d/m/Y'); ?>
                </span>
                <span class="periodo-arrow">→</span>
                <span class="periodo-item">
                    <i class="fa-solid fa-flag-checkered"></i>
                    <?php echo $fin->format('d/m/Y'); ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
    <div class="topbar-right">
        <a href="/proyectos" class="btn btn-ghost btn-sm topbar-icono" title="<?php echo s(t('Proyectos')); ?>" aria-label="<?php echo s(t('Proyectos')); ?>">
            <i class="fa-solid fa-house"></i>
        </a>
        <button id="toggle-theme" class="btn btn-ghost btn-sm topbar-icono" title="Cambiar tema" aria-label="Cambiar tema">
            <i class="fa-solid fa-moon"></i>
        </button>
        <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
        <a href="/sistema" class="btn btn-ghost btn-sm topbar-icono" title="<?php echo s(t('Ajustes de app')); ?>" aria-label="<?php echo s(t('Ajustes de app')); ?>">
            <i class="fa-solid fa-gear"></i>
        </a>
        <?php endif; ?>
        <div class="user-info">
            <span class="user-name"><?php echo s($_SESSION['nombre'] ?? 'Invitado'); ?></span>
            <span class="user-role badge badge-<?php echo s($_SESSION['rol'] ?? 'visitante'); ?>">
                <?php echo s(t(ucfirst($_SESSION['rol'] ?? 'visitante'))); ?>
            </span>
        </div>
        <form method="POST" action="/idioma" class="idioma-sel" title="<?php echo s(t('Idioma')); ?>">
            <input type="hidden" name="volver" value="<?php echo s($_SERVER['REQUEST_URI'] ?? '/'); ?>">
            <?php foreach (IDIOMAS as $cod => $nom): ?><button type="submit" name="idioma" value="<?php echo $cod; ?>" class="<?php echo idioma_actual() === $cod ? 'activo' : ''; ?>" aria-label="<?php echo s($nom); ?>"><?php echo strtoupper($cod); ?></button><?php endforeach; ?>
        </form>
        <a href="/logout" class="btn btn-ghost btn-sm topbar-salir" title="<?php echo s(t('Salir')); ?>">
            <i class="fa-solid fa-right-from-bracket"></i> <span class="hide-m"><?php echo s(t('Salir')); ?></span>
        </a>
    </div>
</header>
