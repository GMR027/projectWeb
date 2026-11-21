<?php /** Sesiones y dispositivos. Variables: $sesiones, $total. */ ?>
<div class="auth-card sesiones-card">
    <div class="auth-header">
        <div class="logo-grande"><i class="fa-solid fa-shield-halved"></i></div>
        <h1><?php echo s(t('Sesiones y dispositivos')); ?></h1>
        <p class="auth-subtitle">
            <?php echo $total === 1
                ? s(t('Tienes 1 dispositivo con la sesión abierta.'))
                : s(t('Tienes {n} dispositivos con la sesión abierta.', ['n' => $total])); ?>
        </p>
    </div>

    <?php if (!empty($_GET['ok'])): ?><div class="alerta alerta-exito" role="status"><?php echo s($_GET['ok']); ?></div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="alerta alerta-error" role="alert"><?php echo s($_GET['error']); ?></div><?php endif; ?>

    <?php if (!$sesiones): ?>
        <div class="alerta alerta-aviso" role="status">
            <i class="fa-solid fa-circle-info"></i> <?php echo s(t('No hay sesiones registradas. Si acabas de entrar, recarga la página.')); ?>
        </div>
    <?php else: ?>
        <div class="sesiones-lista" id="sesiones">
            <?php foreach ($sesiones as $s):
                $ultimo = !empty($s['ultimo_acceso']) ? date('d/m/Y H:i', strtotime((string)$s['ultimo_acceso'])) : '—';
                $creado = !empty($s['creado']) ? date('d/m/Y H:i', strtotime((string)$s['creado'])) : '—';
            ?>
            <div class="sesion-item<?php echo $s['actual'] ? ' es-actual' : ''; ?>">
                <div class="sesion-ico" aria-hidden="true"><i class="fa-solid <?php echo s($s['icono']); ?>"></i></div>
                <div class="sesion-datos">
                    <strong><?php echo s($s['dispositivo']); ?></strong>
                    <span class="sesion-tipo"><?php echo s($s['tipo_texto']); ?><?php echo $s['navegador'] !== '' ? ' · ' . s($s['navegador']) : ''; ?><?php echo $s['sistema'] !== '' ? ' · ' . s($s['sistema']) : ''; ?></span>
                    <span class="sesion-meta"><i class="fa-solid fa-location-dot"></i> <?php echo s($s['ip']); ?> <span class="sesion-sep">·</span> <i class="fa-regular fa-clock"></i> <?php echo s($ultimo); ?></span>
                    <span class="sesion-meta sesion-meta-suave"><?php echo s(t('Inició')); ?>: <?php echo s($creado); ?><?php if (!empty($s['app_version'])): ?> · v<?php echo s($s['app_version']); ?><?php endif; ?></span>
                </div>
                <div class="sesion-acciones">
                    <?php if ($s['actual']): ?>
                        <span class="badge badge-cliente"><i class="fa-solid fa-circle-check"></i> <?php echo s(t('Esta sesión')); ?></span>
                    <?php else: ?>
                        <form method="POST" action="/cuenta/sesiones/cerrar" data-confirmar="<?php echo s(t('¿Cerrar esta sesión? El dispositivo tendrá que iniciar sesión de nuevo.')); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" aria-label="<?php echo s(t('Cerrar sesión en este dispositivo')); ?>">
                                <i class="fa-solid fa-xmark"></i> <?php echo s(t('Cerrar')); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total > 1): ?>
        <form method="POST" action="/cuenta/sesiones/cerrar-otras" class="sesiones-cerrar-todas" data-confirmar="<?php echo s(t('¿Cerrar todas las demás sesiones? Solo quedará este dispositivo.')); ?>">
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-power-off"></i> <?php echo s(t('Cerrar todas las demás')); ?></button>
        </form>
        <?php endif; ?>
    <?php endif; ?>

    <p class="text-center mt-1">
        <a href="/dashboard"><?php echo s(t('Volver al inicio')); ?></a>
        <span class="sesion-sep">·</span>
        <a href="/cuenta/password"><?php echo s(t('Cambiar contraseña')); ?></a>
    </p>
</div>
