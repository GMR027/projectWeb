<?php
/** Cerrar partida con compuerta de punch. Variables: $proyecto, $partida, $bloqueos, $puedeExcepcion */
?>
<div class="page-header">
    <div><h2><i class="fa-solid fa-lock"></i> Cerrar partida</h2><p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s($partida->codigo . ' ' . $partida->nombre); ?></p></div>
    <a href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <?php if ($bloqueos): ?>
        <div class="alerta alerta-error" role="alert">
            <strong>No se puede cerrar todavía.</strong> Hay <b><?php echo (int)$bloqueos; ?></b> pendiente(s) de severidad alta o crítica en el punch de esta partida.
            <a href="/punch?proyecto=<?php echo (int)$proyecto->id; ?>&estado=pendientes">Ver el punch list</a>.
        </div>
        <?php if ($puedeExcepcion): ?>
            <p>Como administrador o dueño del proyecto puedes cerrarla con una excepción firmada. El motivo quedará en la auditoría y en la partida.</p>
            <form method="POST" action="/programa/partidas/cerrar" data-confirm="Cerrar con excepción|Se cerrará la partida aunque tenga pendientes graves de punch. Quedará firmada con tu nombre y motivo.|Cerrar igual">
                <input type="hidden" name="id" value="<?php echo (int)$partida->id; ?>">
                <label>Motivo de la excepción (mínimo 10 caracteres)
                    <textarea name="motivo" rows="3" required minlength="10" maxlength="500" style="width:100%"></textarea>
                </label>
                <div style="margin-top:.8rem"><button class="btn btn-danger" type="submit"><i class="fa-solid fa-lock"></i> Cerrar con excepción</button> <a class="btn btn-ghost" href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>">Cancelar</a></div>
            </form>
        <?php else: ?>
            <p class="text-muted">Solo el dueño del proyecto o el administrador pueden autorizar una excepción.</p>
            <a class="btn btn-ghost" href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>">Volver</a>
        <?php endif; ?>
    <?php else: ?>
        <div class="alerta alerta-exito"><i class="fa-solid fa-circle-check"></i> La partida no tiene pendientes graves de punch. Se puede cerrar.</div>
        <form method="POST" action="/programa/partidas/cerrar" data-confirm="Cerrar partida|La partida quedará marcada como cerrada en el programa.|Cerrar">
            <input type="hidden" name="id" value="<?php echo (int)$partida->id; ?>">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-lock"></i> Cerrar partida</button>
            <a class="btn btn-ghost" href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>">Cancelar</a>
        </form>
    <?php endif; ?>
</div>
