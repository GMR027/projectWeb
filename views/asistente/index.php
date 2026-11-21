<?php
/** Historial web del asistente (solo lectura) y borradores. */
$qsBase = array_filter(['proyecto' => $proyectoFiltro ?: null, 'q' => $q !== '' ? $q : null]);
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-robot"></i> Asistente digital</h2>
        <p class="text-muted">Historial de la app Android y borradores propuestos. La conversación se hace en el teléfono; aquí solo se consulta y se revisan los borradores.</p>
    </div>
    <div>
        <button class="btn btn-secondary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir</button>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alerta alerta-exito" role="status"><?php echo s($_GET['ok']); ?></div><?php endif; ?>
<?php if (!empty($_GET['error'])): ?><div class="alerta alerta-error" role="alert"><?php echo s($_GET['error']); ?></div><?php endif; ?>

<form method="GET" action="/asistente" class="card asis-filtros">
    <label>Obra
        <select name="proyecto">
            <option value="">Todas mis obras</option>
            <?php foreach ($proyectos as $p): ?>
                <option value="<?php echo (int)$p->id; ?>" <?php echo (int)$proyectoFiltro === (int)$p->id ? 'selected' : ''; ?>><?php echo s($p->nombre); ?> · <?php echo s($p->codigo); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Buscar
        <input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Texto de una conversación">
    </label>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
    <?php if ($proyectoFiltro || $q !== ''): ?><a class="btn btn-ghost" href="/asistente">Limpiar</a><?php endif; ?>
</form>

<div class="asis-layout">
    <aside class="card asis-conv" id="conversaciones">
        <div class="asis-cab"><h3><i class="fa-solid fa-comments"></i> Conversaciones</h3><span class="badge badge-cliente"><?php echo count($conversaciones); ?></span></div>
        <?php if (!$conversaciones): ?>
            <div class="asis-vacio"><i class="fa-regular fa-comment-dots"></i><p>Aún no hay conversaciones descargadas.</p><small>Abre el asistente en la app Android y sincroniza.</small></div>
        <?php else: ?>
            <div class="asis-lista">
                <?php foreach ($conversaciones as $c):
                    $activa = $conversacion && (int)$conversacion['id'] === (int)$c['id'];
                    $link = '/asistente?' . http_build_query(array_filter([
                        'conversacion' => (int)$c['id'],
                        'proyecto' => $proyectoFiltro ?: null,
                        'q' => $q !== '' ? $q : null,
                    ]));
                ?>
                <a class="asis-conv-item<?php echo $activa ? ' activa' : ''; ?>" href="<?php echo s($link); ?>">
                    <span class="asis-conv-ico"><i class="fa-solid fa-helmet-safety"></i></span>
                    <span class="asis-conv-txt">
                        <strong><?php echo s($c['titulo']); ?></strong>
                        <small><?php echo s($c['proyecto']); ?> · <?php echo (int)$c['mensajes']; ?> mensaje(s)</small>
                        <small><?php echo $admin ? s($c['usuario']) . ' · ' : ''; ?><?php echo s(date('d/m/Y H:i', strtotime((string)$c['actualizado']))); ?></small>
                    </span>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>

    <section class="card asis-chat">
        <?php if (!$conversacion): ?>
            <div class="asis-vacio asis-vacio-grande">
                <img src="/build/img/projectweb.svg" alt="" width="96" height="96">
                <h3>Sin conversación seleccionada</h3>
                <p>Elige una conversación de la lista o abre el asistente en la app para comenzar una.</p>
            </div>
        <?php else: ?>
            <div class="asis-chat-cab">
                <div>
                    <h3><?php echo s($conversacion['titulo']); ?></h3>
                    <p class="text-muted text-sm"><?php echo s($conversacion['proyecto']); ?> · <?php echo s($conversacion['codigo']); ?><?php echo $admin ? ' · ' . s($conversacion['usuario']) . ' (' . s($conversacion['email']) . ')' : ''; ?></p>
                </div>
                <div class="asis-chat-acciones">
                    <span class="text-muted text-sm"><?php echo s(date('d/m/Y H:i', strtotime((string)$conversacion['actualizado']))); ?></span>
                    <form method="POST" action="/asistente/eliminar" data-confirmar="¿Eliminar esta conversación del historial?">
                        <input type="hidden" name="id" value="<?php echo (int)$conversacion['id']; ?>">
                        <input type="hidden" name="proyecto" value="<?php echo (int)$proyectoFiltro; ?>">
                        <button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button>
                    </form>
                </div>
            </div>
            <div class="asis-mensajes">
                <?php foreach ($mensajes as $m): ?>
                    <div class="asis-msg asis-msg-<?php echo s($m['rol'] === 'user' ? 'user' : 'bot'); ?>">
                        <?php if ($m['rol'] !== 'user'): ?><img src="/build/img/projectweb.svg" alt="" class="asis-msg-avatar"><?php endif; ?>
                        <div class="asis-burbuja">
                            <div class="asis-msg-txt"><?php echo nl2br(s($m['contenido'])); ?></div>
                            <div class="asis-msg-pie">
                                <span><?php echo s(date('d/m/Y H:i', strtotime((string)$m['creado']))); ?></span>
                                <?php if ($m['rol'] !== 'user'): ?><span>· Asistente digital</span><?php endif; ?>
                                <?php if (!empty($m['datos']) && $admin): ?>
                                    <details><summary>Fuentes</summary><pre><?php echo s(json_encode($m['datos'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre></details>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="card asis-borradores">
    <div class="asis-cab"><h3><i class="fa-solid fa-pen-to-square"></i> Borradores del asistente</h3><span class="badge badge-visitante"><?php echo count($borradores); ?></span></div>
    <?php if (!$borradores): ?>
        <p class="text-muted">No hay borradores pendientes.</p>
    <?php else: ?>
        <div class="asis-borradores-lista">
            <?php foreach ($borradores as $b):
                $d = $b['datos'] ?: [];
                $resumen = '';
                if ($b['tipo'] === 'punch') $resumen = trim(($d['titulo'] ?? '') . ' · ' . ($d['severidad'] ?? '') . ' · ' . ($d['ubicacion'] ?? ''));
                elseif ($b['tipo'] === 'rfi') $resumen = trim(($d['asunto'] ?? '') . ' · ' . mb_substr((string)($d['pregunta'] ?? ''), 0, 160));
                elseif ($b['tipo'] === 'bitacora') $resumen = trim(($d['importancia'] ?? '') . ' · ' . mb_substr((string)($d['descripcion'] ?? ''), 0, 180));
                else $resumen = 'Borrador de ' . $b['tipo'] . ' (se completa en su formulario)';
                $aplicable = in_array($b['tipo'], \Classes\Asistente::TIPOS_APLICABLES, true);
            ?>
            <div class="asis-borrador">
                <span class="asis-borrador-ico"><i class="fa-solid <?php echo $b['tipo'] === 'punch' ? 'fa-list-check' : ($b['tipo'] === 'rfi' ? 'fa-circle-question' : 'fa-book'); ?>"></i></span>
                <div class="asis-borrador-txt">
                    <strong><?php echo s(ucfirst($b['tipo'])); ?> · <?php echo s($b['proyecto']); ?></strong>
                    <p><?php echo s($resumen); ?></p>
                    <small><?php echo s($b['usuario']); ?> · <?php echo s(date('d/m/Y H:i', strtotime((string)$b['creado']))); ?> · caduca <?php echo s(date('d/m/Y', strtotime((string)$b['expira_en']))); ?></small>
                </div>
                <div class="asis-borrador-acciones">
                    <?php if ($aplicable): ?>
                        <form method="POST" action="/asistente/borrador/aplicar" data-confirmar="¿Crear el registro real con este borrador?">
                            <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                            <input type="hidden" name="proyecto" value="<?php echo (int)$proyectoFiltro; ?>">
                            <input type="hidden" name="conversacion" value="<?php echo (int)($conversacion['id'] ?? 0); ?>">
                            <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-check"></i> Aplicar</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" action="/asistente/borrador/rechazar" data-confirmar="¿Descartar este borrador?">
                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                        <input type="hidden" name="proyecto" value="<?php echo (int)$proyectoFiltro; ?>">
                        <input type="hidden" name="conversacion" value="<?php echo (int)($conversacion['id'] ?? 0); ?>">
                        <button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-xmark"></i> Descartar</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
