<header class="topbar">
    <div class="topbar-left">
        <h1 class="page-title"><?php echo s($titulo ?? ''); ?></h1>
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
        <button id="toggle-theme" class="btn btn-ghost btn-sm" title="Cambiar tema">
            <i class="fa-solid fa-moon"></i>
        </button>
        <div class="user-info">
            <span class="user-name"><?php echo s($_SESSION['nombre'] ?? 'Invitado'); ?></span>
            <span class="user-role badge badge-<?php echo s($_SESSION['rol'] ?? 'visitante'); ?>">
                <?php echo s(ucfirst($_SESSION['rol'] ?? 'visitante')); ?>
            </span>
        </div>
        <a href="/logout" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-right-from-bracket"></i> Salir
        </a>
    </div>
</header>
