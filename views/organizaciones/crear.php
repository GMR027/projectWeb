<?php
/** Alta de una organización (solo admin). Los clientes se agregan después, en su ficha. */
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-building-circle-arrow-right"></i> Nueva organización</h2>
        <p class="text-muted">Crea la empresa y su plan. En el siguiente paso le agregas sus clientes, visitantes y obras.</p>
    </div>
    <a href="/organizaciones" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <form method="POST" action="/organizaciones/crear" class="formulario" enctype="multipart/form-data">
        <?php include __DIR__ . '/_form.php'; ?>
        <div style="display:flex; gap:.6rem; margin-top:1.4rem">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Crear organización</button>
            <a href="/organizaciones" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
