<?php
/**
 * Barra de personalización de una lista: botón «Diseñar», filtros por campos personalizados y agrupación.
 * Variables: $proyecto, $herramienta (clave de ListaDiseno), $cp (resultado de CamposPersonalizados::aplicar), $puedeDisenar
 */
use Classes\CamposPersonalizados as CP;
use Classes\ListaDiseno;

$pid = (int)$proyecto->id;
$camposCp = $cp['campos'];
$ruta = ListaDiseno::HERRAMIENTAS[$herramienta][2];
$conservar = array_diff_key($_GET, ['cp' => 1, 'agrupar' => 1, 'proyecto' => 1]);   // el resto de filtros de la lista se mantiene
?>
<?php if ($camposCp || $puedeDisenar): ?>
<form method="GET" action="<?php echo s($ruta); ?>" class="card inc-filtros cp-barra">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <?php foreach ($conservar as $k => $v): if (is_string($v)): ?><input type="hidden" name="<?php echo s($k); ?>" value="<?php echo s($v); ?>"><?php endif; endforeach; ?>
    <?php foreach ($camposCp as $c): $v = $cp['filtros'][$c['id']] ?? ''; ?>
        <label><?php echo s($c['nombre']); ?>
            <?php if ($c['tipo'] === 'lista'): ?>
                <select name="cp[<?php echo (int)$c['id']; ?>]" onchange="this.form.submit()"><option value=""><?php echo s(t('Todos')); ?></option>
                    <?php foreach ($c['opciones'] as $o): ?><option<?php echo $v === $o ? ' selected' : ''; ?>><?php echo s($o); ?></option><?php endforeach; ?>
                    <option value="__vacio"<?php echo $v === '__vacio' ? ' selected' : ''; ?>><?php echo s(t('(vacío)')); ?></option></select>
            <?php else: ?>
                <input type="<?php echo $c['tipo'] === 'fecha' ? 'date' : 'search'; ?>" name="cp[<?php echo (int)$c['id']; ?>]" value="<?php echo s($v); ?>" placeholder="Filtrar">
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <?php if ($camposCp): ?>
        <?php if (empty($sinAgrupar)): ?><label><?php echo s(t('Agrupar por')); ?>
            <select name="agrupar" onchange="this.form.submit()"><option value="0"><?php echo s(t('Sin agrupar')); ?></option>
                <?php foreach ($camposCp as $c): ?><option value="<?php echo (int)$c['id']; ?>"<?php echo $cp['agrupar'] === $c['id'] ? ' selected' : ''; ?>><?php echo s($c['nombre']); ?></option><?php endforeach; ?>
            </select></label><?php endif; ?>
        <button class="btn btn-ghost" type="submit"><?php echo s(t('Filtrar')); ?></button>
        <?php if ($cp['filtros'] || $cp['agrupar']): ?><a class="btn btn-ghost" href="<?php echo s($ruta); ?>?<?php echo s(http_build_query(['proyecto' => $pid] + array_filter($conservar, 'is_string'))); ?>"><?php echo s(t('Quitar filtros')); ?></a><?php endif; ?>
    <?php endif; ?>
    <?php if ($puedeDisenar): ?>
        <span class="cp-barra-der">
            <a class="btn btn-ghost" href="/lista/diseno?proyecto=<?php echo $pid; ?>&herramienta=<?php echo s($herramienta); ?>"><i class="fa-solid fa-table-columns"></i> <?php echo s(t('Diseñar')); ?></a>
            <a class="btn btn-ghost" href="/campos?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-tags"></i> <?php echo s(t('Campos')); ?></a>
        </span>
    <?php endif; ?>
</form>
<?php endif; ?>
