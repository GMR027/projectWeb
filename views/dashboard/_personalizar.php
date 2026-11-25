<?php
/** Ventana «Personalizar mi inicio». Variables: $inicio, $personalizando */
$iconos = [
    'resumen' => 'fa-chart-pie',
    'atencion' => 'fa-triangle-exclamation',
    'proyectos' => 'fa-building',
    'herramientas' => 'fa-grip',
    'novedades' => 'fa-bullhorn',
    'atajos' => 'fa-bolt',
];
?>
<dialog class="inicio-dialogo inicio-dialogo-personalizar" id="inicio-personalizar" aria-labelledby="inicio-pers-titulo"<?php echo $personalizando ? ' open data-inicio-abrir="1"' : ''; ?>>
    <div class="ventana-cab">
        <span class="ventana-icono"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
        <div class="ventana-cab-txt">
            <span class="ventana-eyebrow"><?php echo s(t('Inicio a tu medida')); ?></span>
            <h2 id="inicio-pers-titulo"><?php echo s(t('Personalizar mi inicio')); ?></h2>
            <p><?php echo s(t('Elige qué ves, en qué orden y cuánto ocupa. Solo afecta tu cuenta.')); ?></p>
        </div>
        <button type="button" class="ventana-x" data-inicio-cerrar aria-label="<?php echo s(t('Cerrar')); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <form method="POST" action="/inicio/guardar" id="inicio-form">
        <div class="ventana-cuerpo inicio-pers-cuerpo">
            <div class="inicio-pers-lista">
                <h3><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i> <?php echo s(t('Bloques del inicio')); ?></h3>
                <ul id="inicio-bloques" class="inicio-bloques">
                    <?php $i = 0; foreach ($inicio['bloques'] as $b): [$tituloBloque, $descBloque] = \Classes\InicioUsuario::BLOQUES[$b['k']]; ?>
                        <li class="inicio-bloque-item" draggable="true" data-bloque="<?php echo s($b['k']); ?>">
                            <span class="inicio-asa" title="<?php echo s(t('Arrastra para ordenar')); ?>" aria-hidden="true">⠿</span>
                            <span class="inicio-bloque-ico"><i class="fa-solid <?php echo s($iconos[$b['k']] ?? 'fa-square'); ?>" aria-hidden="true"></i></span>
                            <div class="inicio-bloque-txt">
                                <strong><?php echo s($tituloBloque); ?></strong>
                                <small><?php echo s($descBloque); ?></small>
                            </div>
                            <label class="inicio-switch" title="<?php echo s(t('Mostrar este bloque')); ?>">
                                <input type="checkbox" data-f="on" name="bloques[<?php echo $i; ?>][on]" value="1"<?php echo $b['on'] ? ' checked' : ''; ?>>
                                <span class="inicio-switch-caja" aria-hidden="true"><span class="inicio-switch-punto"></span></span>
                                <span class="sr-only"><?php echo s(t('Mostrar')); ?> <?php echo s($tituloBloque); ?></span>
                            </label>
                            <div class="inicio-anchos" role="group" aria-label="<?php echo s(t('Ancho de') . ' ' . $tituloBloque); ?>">
                                <?php foreach (\Classes\InicioUsuario::ANCHOS as $ancho => $etiqueta): ?>
                                    <label>
                                        <input type="radio" data-f="ancho" name="bloques[<?php echo $i; ?>][ancho]" value="<?php echo (int)$ancho; ?>"<?php echo (int)$b['ancho'] === (int)$ancho ? ' checked' : ''; ?>>
                                        <span><?php echo s($etiqueta); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" data-f="k" name="bloques[<?php echo $i; ?>][k]" value="<?php echo s($b['k']); ?>">
                            <div class="inicio-mover">
                                <button type="button" class="btn btn-xs btn-ghost" data-mover="-1" title="<?php echo s(t('Subir')); ?>" aria-label="<?php echo s(t('Subir')); ?>">↑</button>
                                <button type="button" class="btn btn-xs btn-ghost" data-mover="1" title="<?php echo s(t('Bajar')); ?>" aria-label="<?php echo s(t('Bajar')); ?>">↓</button>
                            </div>
                        </li>
                    <?php $i++; endforeach; ?>
                </ul>
                <div class="inicio-pers-opciones">
                    <label for="proyectos_n"><?php echo s(t('Proyectos que se muestran en «Proyectos recientes»')); ?></label>
                    <select id="proyectos_n" name="proyectos_n">
                        <?php foreach (\Classes\InicioUsuario::CANTIDADES as $n): ?>
                            <option value="<?php echo $n; ?>"<?php echo $inicio['proyectos_n'] === $n ? ' selected' : ''; ?>><?php echo $n; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <aside class="inicio-previa" aria-label="<?php echo s(t('Vista previa')); ?>">
                <h3><i class="fa-solid fa-eye" aria-hidden="true"></i> <?php echo s(t('Vista previa')); ?></h3>
                <div class="inicio-previa-marco">
                    <div class="inicio-previa-rejilla" id="inicio-previa">
                        <?php foreach ($inicio['bloques'] as $b): ?>
                            <span class="inicio-previa-bloque inicio-previa-w-<?php echo (int)$b['ancho']; ?>" data-previa="<?php echo s($b['k']); ?>"<?php echo $b['on'] ? '' : ' hidden'; ?>>
                                <i class="fa-solid <?php echo s($iconos[$b['k']] ?? 'fa-square'); ?>" aria-hidden="true"></i>
                                <b><?php echo s(\Classes\InicioUsuario::BLOQUES[$b['k']][0]); ?></b>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="text-muted"><?php echo s(t('Arrastra para ordenar. Los cambios se guardan en tu cuenta, no en la de los demás.')); ?></p>
            </aside>
        </div>
        <div class="ventana-pie">
            <?php if (!empty($inicio['propia'])): ?>
                <button type="submit" name="restablecer" value="1" class="btn btn-ghost" formnovalidate><?php echo s(t('Volver al inicio de siempre')); ?></button>
            <?php endif; ?>
            <span class="inicio-pie-espacio"></span>
            <button type="button" class="btn btn-ghost" data-inicio-cerrar><?php echo s(t('Cancelar')); ?></button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <?php echo s(t('Guardar cambios')); ?></button>
        </div>
    </form>
</dialog>
