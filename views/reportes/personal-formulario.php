<?php $esNuevo = !$persona->id; $id = s($proyecto->id); $deDirectorio = ($_REQUEST['volver'] ?? '') === 'directorio'; ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo personal' : 'Editar personal'; ?></h2>
    <a href="<?php echo $deDirectorio ? "/directorio?tab=personal&proyecto=$id" : "/reportes/personal?proyecto=$id"; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/reportes/personal/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if ($deDirectorio): ?><input type="hidden" name="volver" value="directorio"><?php endif; ?>
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($persona->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($persona->nombre); ?>" required></div>
            <div class="campo">
                <label for="departamento">Departamento</label>
                <input type="text" id="departamento" name="departamento" maxlength="100" list="lista-deptos" value="<?php echo s($persona->departamento); ?>">
                <datalist id="lista-deptos"><?php foreach ($departamentos as $d): ?><option value="<?php echo s($d); ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="campo">
                <label for="tipo">Tipo de personal</label>
                <select id="tipo" name="tipo"><option value="">— Sin definir —</option>
                    <?php foreach (\Model\Personal::TIPOS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $contacto['tipo'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="campo"><label for="cargo">Cargo / puesto</label><input type="text" id="cargo" name="cargo" maxlength="100" value="<?php echo s($contacto['cargo']); ?>"></div>
            <div class="campo"><label for="empresa">Empresa</label><input type="text" id="empresa" name="empresa" maxlength="150" value="<?php echo s($contacto['empresa']); ?>"></div>
            <div class="campo"><label for="telefono">Teléfono</label><input type="tel" id="telefono" name="telefono" maxlength="40" value="<?php echo s($contacto['telefono']); ?>"></div>
            <div class="campo"><label for="correo">Correo</label><input type="email" id="correo" name="correo" maxlength="150" value="<?php echo s($contacto['correo']); ?>"></div>
            <div class="campo"><label for="rfc">RFC</label><input type="text" id="rfc" name="rfc" maxlength="13" value="<?php echo s($contacto['rfc']); ?>" style="text-transform:uppercase" autocomplete="off"></div>
            <div class="campo campo-full"><label for="direccion">Dirección</label><input type="text" id="direccion" name="direccion" maxlength="250" value="<?php echo s($contacto['direccion']); ?>"></div>
            <div class="campo campo-full"><label for="notas">Notas generales</label><textarea id="notas" name="notas" rows="4" maxlength="5000"><?php echo s($contacto['notas']); ?></textarea></div>
            <div class="campo campo-full">
                <label for="imagen">Imagen</label>
                <input type="file" id="imagen" name="imagen" accept="image/*">
                <?php if ($persona->imagen): ?>
                    <img class="rd-avatar" src="/uploads/personal/<?php echo s($persona->imagen); ?>" alt="" style="margin-top:.5rem">
                    <label class="text-sm"><input type="checkbox" name="quitar_imagen" value="1" style="width:auto"> Quitar imagen</label>
                <?php endif; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
