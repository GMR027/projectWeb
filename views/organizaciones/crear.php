<?php
/** Alta de una organización (solo admin). Los clientes se agregan después, en su ficha. */
?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Cartera de empresas</span>
        <h2><i class="fa-solid fa-building-circle-arrow-right"></i> Nueva organización</h2>
        <p class="text-muted">Crea la empresa y su plan. En el siguiente paso le agregas sus clientes, visitantes y obras.</p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-hourglass-half"></i> 14 días de prueba</span>
            <span><i class="fa-solid fa-arrow-left"></i> <a href="/organizaciones" style="color:inherit">Volver a Organizaciones</a></span>
        </div>
    </div>
</div>

<div class="ctl-form-grid">
<div>
<div class="card">
    <form method="POST" action="/organizaciones/crear" class="formulario ctl-form" enctype="multipart/form-data">
        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-building"></i>
                <div><h4>Datos de la empresa</h4><p>Nombre comercial, razón social, RFC, contacto y logo.</p></div>
            </div>
            <?php include __DIR__ . '/_form.php'; ?>
        </div>
        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-layer-group"></i>
                <div><h4>Plan inicial</h4><p>La empresa empieza con 14 días de prueba; después puedes activarla y ajustar sus topes.</p></div>
            </div>
            <div class="ctl-campos">
                <div class="campo ctl-ancho">
                    <label for="plan_id">Plan</label>
                    <select id="plan_id" name="plan_id">
                        <?php foreach ($planes as $p): ?>
                            <option value="<?php echo s($p['clave']); ?>" <?php echo $org->planClave() === $p['clave'] ? 'selected' : ''; ?>>
                                <?php echo s($p['nombre']); ?> · <?php echo s(\Classes\Plan::precio($p['clave'])); ?> MXN/mes ·
                                <?php echo $p['obras'] === null ? 'obras ilimitadas' : (int)$p['obras'] . ' obras'; ?> ·
                                <?php echo $p['usuarios'] === null ? 'usuarios ilimitados' : (int)$p['usuarios'] . ' personas'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="ctl-form-pie">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Crear organización</button>
            <a href="/organizaciones" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
</div>
<aside class="ctl-aside">
    <div class="ctl-aside-card">
        <h4><i class="fa-solid fa-circle-info"></i> Después de crearla</h4>
        <ul>
            <li>Agrega al cliente propietario desde la pestaña «Clientes».</li>
            <li>El propietario administra su equipo y su facturación.</li>
            <li>Puedes cambiar el plan, los topes y el estado en su ficha.</li>
        </ul>
    </div>
    <div class="ctl-aside-card">
        <h4><i class="fa-solid fa-shield-halved"></i> Planes y topes</h4>
        <p>Cada plan define obras, personas que editan, visitantes y almacenamiento. Los topes se pueden afinar empresa por empresa sin borrar datos.</p>
    </div>
</aside>
</div>
