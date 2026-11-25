<?php $esContacto = $tipo === 'contacto'; ?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Centro de mensajes</span>
        <h2><i class="fa-solid <?php echo $esContacto ? 'fa-envelope' : 'fa-headset'; ?>"></i> <?php echo $esContacto ? 'Mensaje de contacto' : 'Mensaje de soporte'; ?></h2>
        <p class="text-muted">Recibido el <?php echo s(substr($creado, 0, 16)); ?></p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-tag"></i> <?php echo $esContacto ? 'Contacto' : 'Soporte'; ?></span>
            <span><i class="fa-solid fa-arrow-left"></i> <a href="/bandeja?tipo=<?php echo s($tipo); ?>" style="color:inherit">Volver a la bandeja</a></span>
        </div>
    </div>
</div>

<div class="card ctl-lectura">
    <div class="ctl-lectura-cab">
        <div>
            <h3><?php echo $esContacto ? s($m->nombre) : s($m->tema); ?></h3>
            <p class="text-muted text-sm"><?php echo $esContacto ? 'Formulario de contacto' : 'Ticket de soporte'; ?> · <?php echo s(substr($creado, 0, 16)); ?></p>
        </div>
        <span class="badge <?php echo $esContacto ? 'badge-admin' : 'badge-cliente'; ?>"><?php echo $esContacto ? 'Contacto' : 'Soporte'; ?></span>
    </div>

    <?php if ($esContacto): ?>
        <dl class="msg-meta ctl-lectura-meta">
            <div><dt>Nombre</dt><dd><?php echo s($m->nombre); ?></dd></div>
            <div><dt>Empresa</dt><dd><?php echo s($m->empresa); ?></dd></div>
            <?php if ($m->puesto): ?><div><dt>Puesto</dt><dd><?php echo s($m->puesto); ?></dd></div><?php endif; ?>
            <div><dt>Correo</dt><dd><a href="mailto:<?php echo s($m->correo); ?>"><?php echo s($m->correo); ?></a></dd></div>
            <div><dt>Teléfono</dt><dd><a href="tel:<?php echo s(preg_replace('/[^0-9+]/', '', $m->telefono)); ?>"><?php echo s($m->telefono); ?></a></dd></div>
            <div><dt>Plan de interés</dt><dd><?php echo s($m->plan ?: '—'); ?></dd></div>
            <div><dt>Proyectos simultáneos</dt><dd><?php echo s($m->proyectos_aprox ?: '—'); ?></dd></div>
            <div><dt>Prefiere contacto por</dt><dd><?php echo s(\Model\Contacto::PREFERENCIAS[$m->preferencia] ?? $m->preferencia); ?></dd></div>
            <div><dt>Cómo nos conoció</dt><dd><?php echo s($m->origen ?: '—'); ?></dd></div>
        </dl>
        <h4 class="card-titulo">Mensaje</h4>
        <div class="msg-cuerpo ctl-lectura-cuerpo"><?php echo s($m->mensaje); ?></div>
    <?php else: ?>
        <dl class="msg-meta ctl-lectura-meta">
            <div><dt>Usuario</dt><dd><?php echo s($remitente->nombre ?? 'Usuario eliminado'); ?></dd></div>
            <?php if ($remitente): ?><div><dt>Correo</dt><dd><a href="mailto:<?php echo s($remitente->email); ?>"><?php echo s($remitente->email); ?></a></dd></div><?php endif; ?>
            <div><dt>Tema</dt><dd><?php echo s($m->tema); ?></dd></div>
        </dl>
        <h4 class="card-titulo">Información</h4>
        <div class="msg-cuerpo ctl-lectura-cuerpo"><?php echo s($m->informacion); ?></div>
    <?php endif; ?>

    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:2rem">
        <?php $correo = $esContacto ? $m->correo : ($remitente->email ?? ''); if ($correo): ?>
            <a class="btn btn-primary" href="mailto:<?php echo s($correo); ?>?subject=<?php echo rawurlencode('Re: ' . ($esContacto ? 'Tu mensaje a ProjectWeb' : $m->tema)); ?>"><i class="fa-solid fa-reply"></i> Responder por correo</a>
        <?php endif; ?>
        <form method="POST" action="/bandeja/accion">
            <input type="hidden" name="tipo" value="<?php echo s($tipo); ?>"><input type="hidden" name="id" value="<?php echo s($m->id); ?>"><input type="hidden" name="accion" value="no_leido"><input type="hidden" name="volver" value="<?php echo s($tipo); ?>">
            <button class="btn btn-ghost" type="submit"><i class="fa-regular fa-envelope"></i> Marcar como no leído</button>
        </form>
        <form method="POST" action="/bandeja/accion" data-confirm="¿Eliminar mensaje?|Esta acción no se puede deshacer.">
            <input type="hidden" name="tipo" value="<?php echo s($tipo); ?>"><input type="hidden" name="id" value="<?php echo s($m->id); ?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="volver" value="<?php echo s($tipo); ?>">
            <button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button>
        </form>
    </div>
</div>
