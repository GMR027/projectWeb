<?php
/** Aviso cuando la migración de planes y organizaciones no está aplicada (solo admin). */
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-building"></i> Organizaciones</h2>
        <p class="text-muted">Falta preparar la base de datos para planes y organizaciones.</p>
    </div>
</div>

<div class="card">
    <h3 class="card-titulo"><i class="fa-solid fa-database"></i> Aplica la migración</h3>
    <p>La tabla <code class="mono">organizaciones</code> todavía no existe en esta instalación, por eso la sección no puede abrirse. Es un paso único y no borra datos:</p>
    <pre class="mono" style="overflow-x:auto; padding:1.2rem; background:var(--bg-soft, rgba(148,163,184,.12)); border-radius:.8rem">mysql -u root project &lt; database/migracion_planes_organizaciones.sql
php bin/migrar_organizaciones.php</pre>
    <p class="text-muted text-sm">El primer comando crea las tablas y columnas; el segundo arma una organización por cada cliente existente y reasigna sus obras y equipo. Se puede volver a ejecutar sin duplicar nada.</p>
    <p class="text-sm">Cuando termine, recarga esta página.</p>
</div>
