<?php
/**
 * Cabecera compartida de un lienzo (vPlan y Editor).
 * Variables: $lz = [
 *   icono, titulo, subtitulo | subtitulo_html,
 *   chips  => [['icono', 'texto' (HTML ya escapado), 'suave' => bool]],
 *   volver => ['url', 'texto'],
 *   acciones => [['url'|'id', 'texto' (HTML), 'icono', 'clase', 'attrs', 'tipo' => link|button]],
 *   modos  => [['id', 'texto', 'icono', 'url', 'activo' => bool, 'attrs'], ...],
 *   modo_bar_extra => HTML opcional a la derecha de los modos,
 * ];
 * La cabecera NO decide permisos: el controlador arma $lz con lo que el usuario puede hacer.
 */
$lz = $lz ?? [];
$lzIcono = (string)($lz['icono'] ?? 'fa-sheet-plastic');
$lzTitulo = $lz['titulo_html'] ?? s((string)($lz['titulo'] ?? ''));
$lzSub = $lz['subtitulo_html'] ?? s((string)($lz['subtitulo'] ?? ''));
$lzChips = is_array($lz['chips'] ?? null) ? $lz['chips'] : [];
$lzVolver = $lz['volver'] ?? null;
$lzAcciones = is_array($lz['acciones'] ?? null) ? $lz['acciones'] : [];
$lzModos = is_array($lz['modos'] ?? null) ? $lz['modos'] : [];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid <?php echo s($lzIcono); ?>"></i> <?php echo $lzTitulo; ?></h2>
        <?php if ($lzSub !== ''): ?><p class="text-muted"><?php echo $lzSub; ?></p><?php endif; ?>
        <?php if ($lzChips): ?>
        <div class="herr-meta">
            <?php foreach ($lzChips as $ch): ?>
                <span class="herr-chip<?php echo !empty($ch['suave']) ? ' herr-chip-suave' : ''; ?>">
                    <?php if (!empty($ch['icono'])): ?><i class="fa-solid <?php echo s($ch['icono']); ?>"></i><?php endif; ?>
                    <?php echo $ch['texto'] ?? ''; ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="la-controles">
        <?php if ($lzVolver): ?>
            <a href="<?php echo s($lzVolver['url']); ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> <?php echo s($lzVolver['texto'] ?? 'Volver'); ?></a>
        <?php endif; ?>
        <?php foreach ($lzAcciones as $a): ?>
            <?php if (($a['tipo'] ?? 'link') === 'html') { echo $a['html'] ?? ''; continue; } ?>
            <?php $clase = $a['clase'] ?? 'btn btn-ghost'; $attrs = $a['attrs'] ?? ''; ?>
            <?php if (($a['tipo'] ?? 'link') === 'button'): ?>
                <button type="button" id="<?php echo s($a['id'] ?? ''); ?>" class="<?php echo s($clase); ?>"<?php echo $attrs; ?>>
                    <?php if (!empty($a['icono'])): ?><i class="fa-solid <?php echo s($a['icono']); ?>"></i> <?php endif; ?><?php echo $a['texto'] ?? ''; ?>
                </button>
            <?php else: ?>
                <a href="<?php echo s($a['url'] ?? '#'); ?>" class="<?php echo s($clase); ?>"<?php echo $attrs; ?>>
                    <?php if (!empty($a['icono'])): ?><i class="fa-solid <?php echo s($a['icono']); ?>"></i> <?php endif; ?><?php echo $a['texto'] ?? ''; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($lzModos): ?>
<nav class="lz-modos no-print" aria-label="<?php echo s(t('Modos de la lámina')); ?>" data-lz-modos>
    <?php foreach ($lzModos as $m): ?>
        <?php $activo = !empty($m['activo']); $clase = 'lz-modo' . ($activo ? ' activo' : ''); ?>
        <?php if (($m['tipo'] ?? 'link') === 'button'): ?>
            <button type="button" class="<?php echo $clase; ?>" data-lz-modo="<?php echo s($m['id']); ?>"<?php echo !empty($m['url']) ? ' data-lz-url="' . s($m['url']) . '"' : ''; ?> aria-pressed="<?php echo $activo ? 'true' : 'false'; ?>"<?php echo $m['attrs'] ?? ''; ?>>
                <?php if (!empty($m['icono'])): ?><i class="fa-solid <?php echo s($m['icono']); ?>"></i> <?php endif; ?><?php echo s($m['texto']); ?>
            </button>
        <?php else: ?>
            <a href="<?php echo s($m['url'] ?? '#'); ?>" class="<?php echo $clase; ?>" data-lz-modo="<?php echo s($m['id']); ?>"<?php echo !empty($m['attrs']) ? ' ' . $m['attrs'] : ''; ?>>
                <?php if (!empty($m['icono'])): ?><i class="fa-solid <?php echo s($m['icono']); ?>"></i> <?php endif; ?><?php echo s($m['texto']); ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!empty($lz['modo_bar_extra'])): ?>
        <span class="lz-sep" aria-hidden="true"></span>
        <div class="lz-der"><?php echo $lz['modo_bar_extra']; ?></div>
    <?php endif; ?>
</nav>
<?php endif; ?>
