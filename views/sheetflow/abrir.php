<?php // Variables: $proyecto, $hoja, $departamentos, $usos, $config
use Classes\Sheetflow;
$id = (int)$proyecto->id;
$q = 'proyecto=' . $id . '&id=' . (int)$hoja->id;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table-cells"></i> <span id="sf-titulo"><?php echo s($hoja->nombre); ?></span></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · SheetFlow</p>
    </div>
    <div class="la-controles">
        <span id="sf-estado" class="text-sm text-muted" role="status"></span>
        <button type="button" id="sf-guardar" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        <button type="button" class="btn btn-ghost" data-tour="sheetflow-editor" title="Recorrer el editor paso a paso (se muestra solo la primera vez)"><i class="fa-solid fa-circle-play"></i> Guía rápida</button>
        <?php if (puede_descargar()): ?>
            <details class="gr-descargar">
                <summary class="btn btn-ghost"><i class="fa-solid fa-file-export"></i> Exportar</summary>
                <div class="gr-descargar-menu">
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=xlsx" data-guardar-antes><i class="fa-regular fa-file-excel"></i> Excel (.xlsx)</a>
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=csv" data-guardar-antes><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=sheetflow" data-guardar-antes><i class="fa-solid fa-table-cells"></i> SheetFlow (.sheetflow)</a>
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=pdf" data-guardar-antes><i class="fa-regular fa-file-pdf"></i> PDF</a>
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=pdf&inline=1" target="_blank" rel="noopener" data-guardar-antes><i class="fa-solid fa-print"></i> Imprimir</a>
                </div>
            </details>
        <?php endif; ?>
        <a href="/sheetflow/ver?<?php echo $q; ?>" class="btn btn-ghost"><i class="fa-regular fa-eye"></i> Ver</a>
        <a href="/sheetflow?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Tablas</a>
    </div>
</div>
<?php $avisoTexto = 'Las tablas solo se pueden editar en una tablet o en un equipo de escritorio.'; $avisoLectura = true; include __DIR__ . '/../templates/aviso-escritorio.php'; ?>

<div class="card rd-datos">
    <label>Nombre <input type="text" id="sf-nombre" maxlength="150" value="<?php echo s($hoja->nombre); ?>"></label>
    <label>Fecha <input type="date" id="sf-fecha" value="<?php echo s($hoja->fecha); ?>"></label>
    <label>Departamento <input type="text" id="sf-depto" maxlength="100" list="sf-deptos" value="<?php echo s($hoja->departamento); ?>" autocomplete="off"></label>
    <label>Descripción <input type="text" id="sf-desc" maxlength="300" value="<?php echo s($hoja->descripcion); ?>"></label>
    <datalist id="sf-deptos"><?php foreach ($departamentos as $dp): ?><option value="<?php echo s($dp); ?>"><?php endforeach; ?></datalist>
</div>

<div class="sf-pestanas" id="sf-pestanas" role="tablist" aria-label="Pestañas de herramientas">
    <button type="button" role="tab" class="activo" data-pest="inicio"><i class="fa-solid fa-house"></i> Inicio</button>
    <button type="button" role="tab" data-pest="insertar"><i class="fa-solid fa-plus"></i> Insertar</button>
    <button type="button" role="tab" data-pest="datos"><i class="fa-solid fa-table-list"></i> Datos</button>
    <button type="button" role="tab" data-pest="vista"><i class="fa-regular fa-eye"></i> Vista</button>
</div>
<div class="sf-barra" id="sf-barra" role="toolbar" aria-label="Herramientas de la tabla">
    <div class="sf-panel" data-panel="inicio">
        <div class="sf-grupo" title="Deshacer / rehacer">
            <button type="button" class="sf-b" id="sf-deshacer" title="Deshacer (Ctrl+Z)"><i class="fa-solid fa-rotate-left"></i></button>
            <button type="button" class="sf-b" id="sf-rehacer" title="Rehacer (Ctrl+Y)"><i class="fa-solid fa-rotate-right"></i></button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Fuente</span>
            <button type="button" class="sf-b" id="sf-fs-menos" title="Reducir tamaño de letra"><i class="fa-solid fa-minus"></i></button>
            <input type="number" id="sf-fs" min="6" max="72" step="1" value="13" title="Tamaño de letra (px) de las celdas elegidas" aria-label="Tamaño de letra">
            <button type="button" class="sf-b" id="sf-fs-mas" title="Aumentar tamaño de letra"><i class="fa-solid fa-plus"></i></button>
            <button type="button" class="sf-b" data-fmt="b" title="Negrita (Ctrl+B)"><i class="fa-solid fa-bold"></i></button>
            <button type="button" class="sf-b" data-fmt="i" title="Cursiva (Ctrl+I)"><i class="fa-solid fa-italic"></i></button>
            <button type="button" class="sf-b" data-fmt="u" title="Subrayado (Ctrl+U)"><i class="fa-solid fa-underline"></i></button>
            <label class="sf-color" title="Color de la letra"><i class="fa-solid fa-font"></i><input type="color" id="sf-c-letra" value="#111111"></label>
            <label class="sf-color" title="Color de relleno de la celda"><i class="fa-solid fa-fill-drip"></i><input type="color" id="sf-c-fondo" value="#fff7c2"></label>
            <button type="button" class="sf-b" id="sf-sin-relleno" title="Quitar relleno"><i class="fa-solid fa-ban"></i></button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Alinear</span>
            <button type="button" class="sf-b" data-alin="izquierda" title="Alinear a la izquierda"><i class="fa-solid fa-align-left"></i></button>
            <button type="button" class="sf-b" data-alin="centro" title="Centrar"><i class="fa-solid fa-align-center"></i></button>
            <button type="button" class="sf-b" data-alin="derecha" title="Alinear a la derecha"><i class="fa-solid fa-align-right"></i></button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Bordes</span>
            <select id="sf-b-lado" title="Dónde poner el borde en las celdas elegidas">
                <option value="todos">Todos</option><option value="exterior">Exterior</option><option value="arriba">Arriba</option><option value="abajo">Abajo</option>
                <option value="izquierda">Izquierda</option><option value="derecha">Derecha</option><option value="ninguno">Sin bordes</option>
            </select>
            <select id="sf-b-estilo" title="Estilo de línea"><option value="solid">Continuo</option><option value="dashed">Guiones</option><option value="dotted">Puntos</option><option value="double">Doble</option></select>
            <select id="sf-b-grosor" title="Grosor (px)"><option value="1">1 px</option><option value="2">2 px</option><option value="3">3 px</option><option value="4">4 px</option><option value="6">6 px</option></select>
            <label class="sf-color" title="Color del borde"><i class="fa-regular fa-square"></i><input type="color" id="sf-b-color" value="#1f3a5f"></label>
            <button type="button" class="sf-b sf-b-txt" id="sf-b-aplicar" title="Aplicar el borde"><i class="fa-solid fa-border-all"></i> Aplicar</button>
            <button type="button" class="sf-b" id="sf-limpiar-fmt" title="Quitar todo el formato de las celdas elegidas (conserva el contenido)"><i class="fa-solid fa-eraser"></i></button>
        </div>
    </div>
    <div class="sf-panel" data-panel="insertar" hidden>
        <div class="sf-grupo">
            <span class="sf-etq">Renglones</span>
            <button type="button" class="sf-b sf-b-txt" data-acc="fila-arriba" title="Insertar renglón arriba"><i class="fa-solid fa-arrow-up"></i> Arriba</button>
            <button type="button" class="sf-b sf-b-txt" data-acc="fila-abajo" title="Insertar renglón abajo"><i class="fa-solid fa-arrow-down"></i> Abajo</button>
            <button type="button" class="sf-b sf-b-txt" data-acc="fila-borrar" title="Eliminar los renglones elegidos"><i class="fa-solid fa-trash-can"></i> Eliminar</button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Columnas</span>
            <button type="button" class="sf-b sf-b-txt" data-acc="col-izq" title="Insertar columna a la izquierda"><i class="fa-solid fa-arrow-left"></i> Izquierda</button>
            <button type="button" class="sf-b sf-b-txt" data-acc="col-der" title="Insertar columna a la derecha"><i class="fa-solid fa-arrow-right"></i> Derecha</button>
            <button type="button" class="sf-b sf-b-txt" data-acc="col-borrar" title="Eliminar las columnas elegidas"><i class="fa-solid fa-trash-can"></i> Eliminar</button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Imagen</span>
            <button type="button" class="sf-b sf-b-txt" id="sf-img-poner" title="Pone una imagen en la celda elegida"><i class="fa-regular fa-image"></i> Insertar en celda…</button>
            <?php if (!empty($config['simbolos'])): ?>
            <select id="sf-img-simbolo" class="sf-b" title="Pone un símbolo de la biblioteca en la celda elegida"><option value="">Símbolo…</option><?php $cat = null; foreach ($config['simbolos'] as $sm): if ($sm['cat'] !== $cat): if ($cat !== null) echo '</optgroup>'; $cat = $sm['cat']; echo '<optgroup label="' . s($cat) . '">'; endif; ?><option value="<?php echo s($sm['src']); ?>"><?php echo s($sm['nombre']); ?></option><?php endforeach; if ($cat !== null) echo '</optgroup>'; ?></select>
            <?php endif; ?>
            <button type="button" class="sf-b sf-b-txt" id="sf-img-quitar" title="Quita la imagen de las celdas elegidas"><i class="fa-solid fa-xmark"></i> Quitar</button>
            <input type="file" id="sf-img-archivo" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
        </div>
    </div>
    <div class="sf-panel" data-panel="datos" hidden>
        <div class="sf-grupo">
            <span class="sf-etq">Columna</span>
            <select id="sf-tipo" title="Tipo de dato de la columna">
                <?php foreach (Sheetflow::TIPOS as $k => $n): ?><option value="<?php echo s($k); ?>"><?php echo s($n); ?></option><?php endforeach; ?>
            </select>
            <input type="text" id="sf-opciones" class="sf-b" hidden placeholder="Opciones: una, dos, tres" title="Opciones de la lista desplegable, separadas por comas (máx. 20)">
            <select id="sf-total" title="Total al pie de la columna (solo columnas numéricas; «Cuenta» en cualquiera)">
                <?php foreach (Sheetflow::TOTALES as $k => $n): ?><option value="<?php echo s($k); ?>"><?php echo s($k === '' ? 'Sin total' : 'Total: ' . mb_strtolower($n)); ?></option><?php endforeach; ?>
            </select>
            <button type="button" class="sf-b sf-b-txt" id="sf-ancho-auto" title="Vuelve al ancho automático de las columnas elegidas"><i class="fa-solid fa-arrows-left-right"></i> Ancho auto</button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Ordenar</span>
            <button type="button" class="sf-b sf-b-txt" data-acc="orden-asc" title="Ordenar A→Z / menor a mayor"><i class="fa-solid fa-arrow-down-a-z"></i> A→Z</button>
            <button type="button" class="sf-b sf-b-txt" data-acc="orden-desc" title="Ordenar Z→A / mayor a menor"><i class="fa-solid fa-arrow-down-z-a"></i> Z→A</button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Fórmulas</span>
            <select id="sf-fn" title="Inserta una función en la celda elegida">
                <option value="">Insertar función…</option>
                <optgroup label="Condicionales">
                    <option value="=SI(A2>0;&quot;Sí&quot;;&quot;No&quot;)">SI (prueba; si es verdad; si no)</option>
                    <option value="=Y(A2>0;B2>0)">Y (todas se cumplen)</option>
                    <option value="=O(A2>0;B2>0)">O (alguna se cumple)</option>
                    <option value="=NO(A2>0)">NO (invierte)</option>
                    <option value="=SI.ERROR(A2/B2;0)">SI.ERROR (valor si hay error)</option>
                    <option value="=SUMAR.SI(A2:A10;&quot;&gt;5&quot;;B2:B10)">SUMAR.SI (suma si cumple)</option>
                    <option value="=CONTAR.SI(A2:A10;&quot;&gt;5&quot;)">CONTAR.SI (cuenta si cumple)</option>
                    <option value="=PROMEDIO.SI(A2:A10;&quot;&gt;5&quot;;B2:B10)">PROMEDIO.SI (promedia si cumple)</option>
                </optgroup>
                <optgroup label="Matemáticas">
                    <option value="=SUMA(A2:A10)">SUMA</option><option value="=PROMEDIO(A2:A10)">PROMEDIO</option><option value="=MIN(A2:A10)">MIN</option>
                    <option value="=MAX(A2:A10)">MAX</option><option value="=CONTAR(A2:A10)">CONTAR</option><option value="=REDONDEAR(A2;2)">REDONDEAR</option>
                </optgroup>
            </select>
        </div>
    </div>
    <div class="sf-panel" data-panel="vista" hidden>
        <div class="sf-grupo">
            <span class="sf-etq">Encabezado</span>
            <label class="sf-color" title="Color del encabezado"><i class="fa-solid fa-fill-drip"></i><input type="color" id="sf-c-enc" value="#1f3a5f"></label>
            <label class="sf-color" title="Color del texto del encabezado"><i class="fa-solid fa-font"></i><input type="color" id="sf-c-txt" value="#ffffff"></label>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Tabla</span>
            <label class="sf-chk"><input type="checkbox" id="sf-zebra"> Renglones alternos</label>
            <label class="sf-chk"><input type="checkbox" id="sf-bordes"> Líneas de cuadrícula</label>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Zoom</span>
            <input type="range" id="sf-zoom" min="50" max="200" step="10" value="100" aria-label="Zoom" style="width:14rem"><span id="sf-zoom-v" class="mono">100%</span>
        </div>
    </div>
</div>

<div class="sf-formula">
    <span class="sf-ref" id="sf-ref">A2</span>
    <span class="sf-fx">fx</span>
    <input type="text" id="sf-fx" autocomplete="off" spellcheck="false" placeholder="Contenido de la celda; empieza con = para una fórmula, p. ej. =SUMA(C2:C10) o =C2*D2">
</div>

<div class="sf-area" id="sf-area" tabindex="0" aria-label="Tabla editable">
    <div class="sf-scroll"><table class="sf-grid" id="sf-grid"></table></div>
</div>
<div class="sf-estadistica" id="sf-estadistica" aria-live="polite"></div>
<p class="text-muted text-sm sf-ayuda">Clic para elegir · arrastra o Mayús+flechas para varias celdas · Enter o doble clic para editar · Supr borra · Ctrl+C / Ctrl+V copian y pegan desde Excel · Fórmulas: <code>+ − * / ^ &amp;</code>, <code>SUMA PROMEDIO MIN MAX CONTAR REDONDEAR</code> y condicionales <code>SI Y O NO SI.ERROR SUMAR.SI CONTAR.SI PROMEDIO.SI</code>. Arrastra el borde de una letra de columna para cambiar su ancho. El encabezado es la fila 1.</p>

<div class="card sf-usos">
    <h3><i class="fa-solid fa-share-nodes"></i> Usar esta tabla</h3>
    <ul class="sf-lista-usos">
        <li><strong>vPlan:</strong>
            <form method="POST" action="/sheetflow/a-vplan" style="display:inline" data-sf-guardar><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$hoja->id; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-solid fa-table-list"></i> Crear tabla en vPlan</button></form>
            <a class="btn btn-sm btn-ghost" href="/vplan/crear?proyecto=<?php echo (int)$proyecto->id; ?>&amp;sheetflow=<?php echo (int)$hoja->id; ?>"><i class="fa-solid fa-file-circle-plus"></i> Crear lámina vPlan con esta tabla</a>
            <?php foreach ($usos as $u): ?>
                <form method="POST" action="/sheetflow/a-vplan" style="display:inline" data-sf-guardar><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$hoja->id; ?>"><input type="hidden" name="tabla" value="<?php echo (int)$u['id']; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-solid fa-rotate"></i> Vinculada: «<?php echo s($u['titulo']); ?>» (<?php echo (int)$u['laminas']; ?> lámina<?php echo (int)$u['laminas'] === 1 ? '' : 's'; ?>)</button></form>
            <?php endforeach; ?></li>
        <li><strong>Editor:</strong> panel <em>Símbolos</em> de un gráfico → «Tablas de SheetFlow».</li>
        <li><strong>Redactor:</strong> <em>Insertar › Tabla › Origen › SheetFlow</em>.<?php foreach ($redactor as $d): ?> <a class="btn btn-sm btn-ghost" href="/redactor/abrir?proyecto=<?php echo (int)$proyecto->id; ?>&amp;id=<?php echo (int)$d['id']; ?>"><i class="fa-solid fa-file-lines"></i> Usada en «<?php echo s($d['nombre']); ?>»</a><?php endforeach; ?></li>
    </ul>
</div>
<script>window.SF_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="/vendor/sheetflow.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/sheetflow.js'); ?>"></script>
<script src="/vendor/tour.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tour.js'); ?>"></script>
<script>
// Guía rápida del editor de SheetFlow
window.Tour.registrar('sheetflow-editor', {
    auto: true,
    pasos: [
        { icono: 'fa-table-cells', titulo: 'La hoja', texto: 'Escribe y navega con las flechas como en Excel. Los cambios se <b>autoguardan</b> unos segundos después de editar.' },
        { sel: '#sf-pestanas', icono: 'fa-table-columns', titulo: 'Cuatro pestañas', texto: '<b>Inicio</b> (formato de celda), <b>Insertar</b> (renglones, columnas, imágenes), <b>Datos</b> (ordenar, totales, importar) y <b>Vista</b> (zebra, bordes, zoom).' },
        { sel: '#sf-fx', icono: 'fa-square-root-variable', titulo: 'Fórmulas', texto: 'En la barra de abajo: empieza con <b>=</b> y usa SUMA, SI, SUMAR.SI… Las referencias son como en Excel (B3, C2:C10) y se reescriben solas al mover renglones.' },
        { sel: '#sf-guardar', icono: 'fa-floppy-disk', titulo: 'Guardar y exportar', texto: 'Guarda la hoja (también con <b>Ctrl + S</b>). «Exportar» entrega el Excel, el CSV o el PDF con los formatos.' },
        { icono: 'fa-circle-check', titulo: 'Listo', texto: 'Recuerda: la tabla puede irse a una lámina del vPlan con «A vPlan», y las enlazadas se actualizan solas cuando cambia la hoja.' }
    ]
});
</script>
