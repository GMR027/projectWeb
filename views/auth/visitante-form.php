<?php
$edita = !empty($usuario->id);
$tipo = $tipo ?? 'visitante';
$esColab = $tipo === 'colaborador';
$nombreTipo = $esColab ? 'colaborador' : 'visitante';
$tituloForm = $edita ? 'Editar ' . $nombreTipo : ($esColab ? 'Nuevo colaborador' : 'Nuevo visitante');
?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Equipo de la cuenta</span>
        <h2><i class="fa-solid <?php echo $esColab ? 'fa-user-pen' : 'fa-eye'; ?>"></i> <?php echo s($tituloForm); ?></h2>
        <p class="text-muted"><?php echo $esColab ? 'Puede crear y editar en las herramientas que marques, pero nunca borrar.' : 'Solo puede consultar y descargar las obras que le asignes.'; ?></p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-users"></i> <?php echo (int)($usados ?? 0); ?> <?php echo $nombreTipo; ?>s</span>
            <span><i class="fa-solid fa-arrow-left"></i> <a href="/usuarios" style="color:inherit">Volver a Mi equipo</a></span>
        </div>
    </div>
</div>

<div class="ctl-form-grid">
<div>
<div class="card">
    <?php if (!empty($lleno)): ?>
        <div class="ctl-empty">
            <i class="fa-solid fa-user-lock"></i>
            <strong>Límite de <?php echo $esColab ? 'colaboradores' : 'visitantes'; ?> alcanzado</strong>
            <p>Ya tienes el máximo de <?php echo ($ilimitado ?? false) ? 'sin límite' : (int)($limite ?? 0); ?> <?php echo $esColab ? 'colaboradores' : 'visitantes'; ?> permitido. Elimina uno para poder crear otro.</p>
            <a href="/usuarios" class="btn btn-primary">Volver a Mi equipo</a>
        </div>
    <?php else: ?>
    <form method="POST" class="formulario ctl-form">
        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-id-card"></i>
                <div><h4>Datos de la persona</h4><p>Nombre, correo y tipo de acceso dentro de tu equipo.</p></div>
            </div>
            <div class="ctl-campos">
                <div class="campo">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo s($usuario->nombre ?? ''); ?>" required>
                </div>
                <div class="campo">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo s($usuario->email ?? ''); ?>" required>
                </div>
                <div class="campo ctl-ancho">
                    <label for="tipo">Tipo de cuenta</label>
                    <select id="tipo" name="tipo">
                        <option value="visitante"<?php echo !$esColab ? ' selected' : ''; ?>>Visitante · solo lectura</option>
                        <option value="colaborador"<?php echo $esColab ? ' selected' : ''; ?>>Colaborador · puede editar</option>
                    </select>
                    <small class="text-muted"><?php echo $esColab ? 'Colaboradores' : 'Visitantes'; ?>: <?php echo (int)($usados ?? 0); ?> <?php echo (($ilimitado ?? false) ? '(sin límite)' : 'de ' . (int)($limite ?? 0)); ?> en este tipo. El plan de tu organización fija los topes.</small>
                </div>
            </div>
        </div>

        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-door-open"></i>
                <div><h4>Obras y herramientas</h4><p>Lo que no marques desaparece de su menú y no puede entrar por enlace.</p></div>
            </div>
            <div class="campo campo-accesos">
                <label>Proyectos a los que entra</label>
                <small class="text-muted">Aparecen los proyectos que creaste y los que el administrador te asignó.</small>
                <?php
                $items = array_map(fn($p) => ['id' => (int)$p->id, 'titulo' => $p->nombre, 'detalle' => $p->codigo], $proyectos);
                $nombreCampo = 'proyectos';
                $marcados = $asignados ?? [];
                include __DIR__ . '/../templates/lista-accesos.php';
                ?>
            </div>
            <?php $marcadas = $herramientasMarcadas ?? null; include __DIR__ . '/../templates/herramientas-visitante.php'; ?>
        </div>

        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-key"></i>
                <div><h4>Contraseña</h4><p><?php echo $edita ? 'Déjala vacía para conservar la actual.' : 'La persona deberá cambiarla al entrar por primera vez.'; ?></p></div>
            </div>
            <div class="ctl-campos">
                <div class="campo">
                    <label for="password"><?php echo $edita ? 'Nuevo password' : 'Password'; ?></label>
                    <input type="password" id="password" name="password" <?php echo $edita ? '' : 'required'; ?> autocomplete="new-password">
                </div>
                <div class="campo">
                    <label for="password2">Repetir password</label>
                    <input type="password" id="password2" name="password2" <?php echo $edita ? '' : 'required'; ?> autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="ctl-form-pie">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo $edita ? 'Guardar cambios' : ($esColab ? 'Crear colaborador' : 'Crear visitante'); ?></button>
            <a href="/usuarios" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>
</div>
</div>
<aside class="ctl-aside">
    <div class="ctl-aside-card">
        <h4><i class="fa-solid <?php echo $esColab ? 'fa-user-pen' : 'fa-eye'; ?>"></i> <?php echo $esColab ? 'Colaborador' : 'Visitante'; ?></h4>
        <p><?php echo $esColab ? 'Edita en las herramientas marcadas y participa en la obra, pero el borrado siempre queda para ti.' : 'Consulta y descarga. No puede cambiar fechas, avances ni archivos.'; ?></p>
    </div>
    <div class="ctl-aside-card">
        <h4><i class="fa-solid fa-circle-info"></i> Cómo funciona</h4>
        <ul>
            <li>Marca las obras a las que puede entrar.</li>
            <li>En «Herramientas» decide qué ve y, si es colaborador, qué edita.</li>
            <li>Puedes cambiarlo después desde Editar.</li>
        </ul>
    </div>
</aside>
</div>
