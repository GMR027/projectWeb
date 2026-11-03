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
        <button type="button" id="sf-historial" class="btn btn-ghost" title="Ver y restaurar versiones anteriores de la tabla"><i class="fa-solid fa-clock-rotate-left"></i> Historial</button>
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
<?php if (!empty($vinculo) && !empty($actividadVinculo)): ?>
<div class="alerta alerta-info">
    <i class="fa-solid fa-link"></i>
    Esta tabla está <strong>vinculada a las subactividades</strong> de la actividad
    <strong><?php echo s(trim($actividadVinculo->codigo . ' ' . $actividadVinculo->nombre)); ?></strong>.
    Los cambios de Nombre, Inicio, Fin y Avance se guardan también en la actividad; las columnas adicionales se quedan en SheetFlow y vPlan.
    <?php if (puede_gestionar_proyecto($proyecto)): ?>
        <a href="/programa/actividades/editar?id=<?php echo (int)$actividadVinculo->id; ?>#subactividades">Abrir la actividad</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card rd-datos">
    <label>Nombre <input type="text" id="sf-nombre" maxlength="150" value="<?php echo s($hoja->nombre); ?>"></label>
    <label>Fecha <input type="date" id="sf-fecha" value="<?php echo s($hoja->fecha); ?>"></label>
    <label>Departamento <input type="text" id="sf-depto" maxlength="100" list="sf-deptos" value="<?php echo s($hoja->departamento); ?>" autocomplete="off"></label>
    <label>Descripción <input type="text" id="sf-desc" maxlength="300" value="<?php echo s($hoja->descripcion); ?>"></label>
    <datalist id="sf-deptos"><?php foreach ($departamentos as $dp): ?><option value="<?php echo s($dp); ?>"><?php endforeach; ?></datalist>
</div>

<div class="cinta-cab">
<div class="sf-pestanas" id="sf-pestanas" role="tablist" aria-label="Pestañas de herramientas">
    <button type="button" role="tab" class="activo" data-pest="inicio"><i class="fa-solid fa-house"></i> Inicio</button>
    <button type="button" role="tab" data-pest="insertar"><i class="fa-solid fa-plus"></i> Insertar</button>
    <button type="button" role="tab" data-pest="datos"><i class="fa-solid fa-table-list"></i> Datos</button>
    <button type="button" role="tab" data-pest="vista"><i class="fa-regular fa-eye"></i> Vista</button>
</div>
<button type="button" class="cinta-plegar" id="sf-plegar" aria-controls="sf-barra" aria-expanded="true" title="Ocultar la barra de herramientas"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>
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
            <span class="sf-etq">Combinar</span>
            <button type="button" class="sf-b sf-b-txt" data-acc="combinar" title="Combinar las celdas elegidas y centrar su contenido (como en LibreOffice)"><i class="fa-solid fa-object-group"></i> Combinar</button>
            <button type="button" class="sf-b sf-b-txt" data-acc="separar" title="Separar las celdas combinadas de la selección"><i class="fa-solid fa-object-ungroup"></i> Separar</button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Número</span>
            <select id="sf-nf" title="Formato de número o fecha de las celdas elegidas">
                <option value="">General</option>
                <option value="0">0</option>
                <option value="0.0">0.0</option>
                <option value="0.00">0.00</option>
                <option value="#,##0">#,##0</option>
                <option value="#,##0.00">#,##0.00</option>
                <option value="$#,##0.00">$#,##0.00</option>
                <option value="0%">0%</option>
                <option value="0.0%">0.0%</option>
                <option value="0.00%">0.00%</option>
                <option value="dd/mm/aaaa">dd/mm/aaaa</option>
                <option value="d/m/aaaa">d/m/aaaa</option>
                <option value="dd/mm/aa">dd/mm/aa</option>
            </select>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Pegar</span>
            <button type="button" class="sf-b sf-b-txt" data-peg="normal" title="Pegar con fórmulas y formatos (Ctrl + V)"><i class="fa-solid fa-paste"></i> Pegar</button>
            <button type="button" class="sf-b sf-b-txt" data-peg="valores" title="Pegar solo el resultado, sin fórmulas"><i class="fa-solid fa-1"></i><i class="fa-solid fa-0"></i> Valores</button>
            <button type="button" class="sf-b sf-b-txt" data-peg="formato" title="Pegar solo el formato (colores, letra, bordes, número)"><i class="fa-solid fa-fill-drip"></i> Formato</button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Condicional</span>
            <select id="sf-cond-tipo" title="Regla de formato condicional para el rango elegido">
                <option value="">Elegir regla…</option>
                <option value="mayor">Es mayor que</option>
                <option value="menor">Es menor que</option>
                <option value="igual">Es igual a</option>
                <option value="entre">Está entre</option>
                <option value="contiene">El texto contiene</option>
                <option value="duplicados">Valores duplicados</option>
                <option value="escala">Escala de color</option>
                <option value="barra">Barra de datos</option>
            </select>
            <input type="text" id="sf-cond-a" class="sf-cond-val" placeholder="Valor" title="Valor de la regla" hidden>
            <input type="text" id="sf-cond-b" class="sf-cond-val" placeholder="Y" title="Segundo valor (entre)" hidden>
            <input type="text" id="sf-cond-txt" class="sf-cond-val" placeholder="Texto" title="Texto que debe contener" hidden>
            <label class="sf-color" title="Color de la letra / de la barra"><i class="fa-solid fa-font"></i><input type="color" id="sf-cond-color" value="#dc2626"></label>
            <label class="sf-color" title="Color de fondo / extremo de la escala"><i class="fa-solid fa-fill-drip"></i><input type="color" id="sf-cond-fondo" value="#fee2e2"></label>
            <button type="button" class="sf-b sf-b-txt" id="sf-cond-aplicar" title="Aplicar la regla al rango elegido"><i class="fa-solid fa-wand-magic-sparkles"></i> Aplicar</button>
            <button type="button" class="sf-b" id="sf-cond-quitar" title="Quitar las reglas que tocan la selección"><i class="fa-solid fa-eraser"></i></button>
        </div>
        <div class="sf-grupo">
            <span class="sf-etq">Buscar</span>
            <button type="button" class="sf-b sf-b-txt" id="sf-buscar" title="Buscar y reemplazar (Ctrl + F)"><i class="fa-solid fa-magnifying-glass"></i> Buscar y reemplazar</button>
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
        <div class="sf-grupo">
            <span class="sf-etq">Gráfico</span>
            <select id="sf-graf-tipo" title="Tipo de gráfico"><option value="barra">Barras</option><option value="linea">Líneas</option></select>
            <input type="text" id="sf-graf-titulo" class="sf-graf-titulo" maxlength="80" placeholder="Título (opcional)" title="Título del gráfico">
            <button type="button" class="sf-b sf-b-txt" id="sf-graf-crear" title="Crea un gráfico con el rango elegido: primera columna = etiquetas, resto = series"><i class="fa-solid fa-chart-column"></i> Crear</button>
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
                    <option value="=SUMAPRODUCTO(A2:A10;B2:B10)">SUMAPRODUCTO</option><option value="=ABS(A2)">ABS</option><option value="=ENTERO(A2)">ENTERO</option>
                    <option value="=RESIDUO(A2;3)">RESIDUO</option><option value="=RAIZ(A2)">RAIZ</option><option value="=POTENCIA(A2;2)">POTENCIA</option>
                    <option value="=REDONDEAR.MAS(A2;2)">REDONDEAR.MAS</option><option value="=REDONDEAR.MENOS(A2;2)">REDONDEAR.MENOS</option>
                    <option value="=REDONDEAR.MULT(A2;5)">REDONDEAR.MULT</option><option value="=MULTIPLO.SUPERIOR(A2;5)">MULTIPLO.SUPERIOR</option>
                    <option value="=MULTIPLO.INFERIOR(A2;5)">MULTIPLO.INFERIOR</option>
                </optgroup>
                <optgroup label="Estadística">
                    <option value="=MEDIANA(A2:A10)">MEDIANA</option><option value="=MODA(A2:A10)">MODA</option><option value="=DESVEST(A2:A10)">DESVEST</option>
                    <option value="=DESVEST.P(A2:A10)">DESVEST.P</option><option value="=VAR(A2:A10)">VAR</option><option value="=VAR.P(A2:A10)">VAR.P</option>
                    <option value="=K.ESIMO.MAYOR(A2:A10;1)">K.ESIMO.MAYOR</option><option value="=K.ESIMO.MENOR(A2:A10;1)">K.ESIMO.MENOR</option>
                    <option value="=JERARQUIA(A2;A2:A10)">JERARQUIA</option><option value="=CONTARA(A2:A10)">CONTARA</option><option value="=CONTAR.BLANCO(A2:A10)">CONTAR.BLANCO</option>
                    <option value="=MAX.SI(A2:A10;B2:B10;&quot;&gt;2&quot;)">MAX.SI</option><option value="=MIN.SI(A2:A10;B2:B10;&quot;&gt;2&quot;)">MIN.SI</option>
                    <option value="=SUMAR.SI.CONJUNTO(A2:A10;B2:B10;&quot;&gt;0&quot;)">SUMAR.SI.CONJUNTO</option><option value="=CONTAR.SI.CONJUNTO(A2:A10;&quot;&gt;0&quot;;B2:B10;&quot;&gt;0&quot;)">CONTAR.SI.CONJUNTO</option>
                    <option value="=PROMEDIO.SI.CONJUNTO(A2:A10;B2:B10;&quot;&gt;0&quot;)">PROMEDIO.SI.CONJUNTO</option>
                </optgroup>
                <optgroup label="Texto">
                    <option value="=IZQUIERDA(A2;3)">IZQUIERDA</option><option value="=DERECHA(A2;3)">DERECHA</option><option value="=EXTRAE(A2;2;3)">EXTRAE</option>
                    <option value="=LARGO(A2)">LARGO</option><option value="=MAYUSC(A2)">MAYUSC</option><option value="=MINUSC(A2)">MINUSC</option>
                    <option value="=NOMPROPIO(A2)">NOMPROPIO</option><option value="=ESPACIOS(A2)">ESPACIOS</option><option value="=SUSTITUIR(A2;&quot;a&quot;;&quot;b&quot;)">SUSTITUIR</option>
                    <option value="=ENCONTRAR(&quot;a&quot;;A2)">ENCONTRAR</option><option value="=HALLAR(&quot;a&quot;;A2)">HALLAR</option><option value="=REPETIR(&quot;x&quot;;3)">REPETIR</option>
                    <option value="=CONCATENAR(A2;&quot; - &quot;;B2)">CONCATENAR</option><option value="=UNIRCADENAS(&quot;, &quot;;1;A2:B2)">UNIRCADENAS</option>
                    <option value="=TEXTO(A2;&quot;#,##0.00&quot;)">TEXTO</option><option value="=VALOR(A2)">VALOR</option>
                </optgroup>
                <optgroup label="Búsqueda y referencias">
                    <option value="=BUSCARV(A2;A2:B10;2;0)">BUSCARV</option><option value="=BUSCARH(A2;A2:B10;2;0)">BUSCARH</option>
                    <option value="=COINCIDIR(A2;A2:A10;0)">COINCIDIR</option><option value="=INDICE(A2:B10;1;2)">INDICE</option>
                    <option value="=BUSCARX(A2;A2:A10;B2:B10;&quot;No está&quot;)">BUSCARX</option><option value="=DESREF(A2;1;0)">DESREF</option>
                    <option value="=INDIRECTO(&quot;B2&quot;)">INDIRECTO</option><option value="=FILA(A2)">FILA</option><option value="=COLUMNA(A2)">COLUMNA</option>
                    <option value="=DIRECCION(2;1)">DIRECCION</option>
                </optgroup>
                <optgroup label="Fechas y finanzas">
                    <option value="=HOY()">HOY</option><option value="=FECHA(2026;1;31)">FECHA</option><option value="=AÑO(A2)">AÑO</option>
                    <option value="=MES(A2)">MES</option><option value="=DIA(A2)">DIA</option><option value="=DIAS(A2;B2)">DIAS</option>
                    <option value="=FECHA.MES(A2;1)">FECHA.MES</option><option value="=FIN.MES(A2;1)">FIN.MES</option><option value="=SIFECHA(A2;B2;&quot;d&quot;)">SIFECHA</option>
                    <option value="=NUM.DE.SEMANA(A2)">NUM.DE.SEMANA</option><option value="=DIAS.LAB(A2;5)">DIAS.LAB</option>
                    <option value="=PAGO(0.05;12;1000)">PAGO</option><option value="=PAGOINT(0.05;1;12;1000)">PAGOINT</option>
                    <option value="=PAGOPRIN(0.05;1;12;1000)">PAGOPRIN</option><option value="=VA(0.05;12;-100)">VA</option>
                    <option value="=VF(0.05;12;-100;1000)">VF</option><option value="=TASA(12;-100;1000)">TASA</option><option value="=NPER(0.05;-100;1000)">NPER</option>
                    <option value="=VNA(0.1;-100;60;60)">VNA</option><option value="=TIR(-100;60;60)">TIR</option>
                </optgroup>
                <optgroup label="Información">
                    <option value="=SI.ND(A2;&quot;Sin dato&quot;)">SI.ND</option><option value="=ES.ERROR(A2)">ES.ERROR</option><option value="=ES.NUMERO(A2)">ES.NUMERO</option>
                    <option value="=ES.TEXTO(A2)">ES.TEXTO</option><option value="=ES.BLANCO(A2)">ES.BLANCO</option><option value="=ES.PAR(A2)">ES.PAR</option>
                    <option value="=ES.IMPAR(A2)">ES.IMPAR</option><option value="=CAMBIAR(A2;1;&quot;uno&quot;;2;&quot;dos&quot;;&quot;otro&quot;)">CAMBIAR</option>
                    <option value="=ELEGIR(A2;&quot;uno&quot;;&quot;dos&quot;;&quot;tres&quot;)">ELEGIR</option>
                </optgroup>
                <optgroup label="Proyecto">
                    <option value="=AVANCE(&quot;1.2.3&quot;)">AVANCE (avance de la partida o actividad)</option>
                    <option value="=INICIO(&quot;1.2.3&quot;)">INICIO (fecha de inicio)</option>
                    <option value="=FIN(&quot;1.2.3&quot;)">FIN (fecha de fin)</option>
                    <option value="=DIAS.REST(&quot;1.2.3&quot;)">DIAS.REST (días que faltan)</option>
                    <option value="=PARTIDA(&quot;1.2.3&quot;)">PARTIDA (nombre)</option>
                    <option value="=TIPO(&quot;1.2.3&quot;)">TIPO (partida o actividad)</option>
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
            <span class="sf-etq">Inmovilizar</span>
            <button type="button" class="sf-b sf-b-txt" data-acc="congelar" title="Congela los renglones de arriba y las columnas de la izquierda de la celda elegida (como Excel)"><i class="fa-solid fa-thumbtack"></i> Hasta la celda</button>
            <button type="button" class="sf-b sf-b-txt" data-acc="descongelar" title="Quita la inmovilización"><i class="fa-solid fa-xmark"></i> Quitar</button>
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
    <input type="text" id="sf-fx" autocomplete="off" spellcheck="false" placeholder="Contenido de la celda; empieza con = para una fórmula, p. ej. =C2*D2. Con la fórmula abierta, haz clic en las celdas para insertar sus referencias.">
</div>

<div class="sf-busca" id="sf-busca" hidden>
    <input type="text" id="sf-busca-txt" placeholder="Buscar…" autocomplete="off" aria-label="Buscar">
    <input type="text" id="sf-busca-rep" placeholder="Reemplazar por…" autocomplete="off" aria-label="Reemplazar por">
    <label class="sf-chk"><input type="checkbox" id="sf-busca-formulas"> En fórmulas</label>
    <label class="sf-chk"><input type="checkbox" id="sf-busca-mayus"> Mayúsculas</label>
    <button type="button" id="sf-busca-ir" class="btn btn-sm btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
    <button type="button" id="sf-busca-rep1" class="btn btn-sm btn-ghost">Reemplazar</button>
    <button type="button" id="sf-busca-repall" class="btn btn-sm btn-ghost">Todo</button>
    <span id="sf-busca-info" class="text-sm text-muted" role="status"></span>
    <button type="button" id="sf-busca-cerrar" class="sf-panel-x" title="Cerrar (Esc)" aria-label="Cerrar la búsqueda"><i class="fa-solid fa-xmark"></i></button>
</div>

<div class="sf-versiones" id="sf-versiones" hidden>
    <div class="sf-versiones-cab">
        <strong><i class="fa-solid fa-clock-rotate-left"></i> Historial de versiones</strong>
        <button type="button" id="sf-versiones-cerrar" class="sf-panel-x" title="Cerrar" aria-label="Cerrar el historial"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <p class="text-muted text-sm">Se guarda una copia al editar (como mucho una por minuto). Restaurar vuelve a esa fecha y conserva la versión actual en el historial.</p>
    <div id="sf-versiones-lista"></div>
</div>

<div class="sf-filtro" id="sf-filtro" hidden></div>

<div class="sf-area" id="sf-area" tabindex="0" aria-label="Tabla editable">
    <div class="sf-scroll"><table class="sf-grid" id="sf-grid"></table><div class="sf-asa" id="sf-asa" hidden title="Arrastra para rellenar (series de números o fechas)"></div></div>
</div>
<div class="sf-filtro-estado" id="sf-filtro-estado" role="status" hidden></div>
<div class="sf-graficos" id="sf-graficos" hidden></div>
<div class="sf-estadistica" id="sf-estadistica" aria-live="polite"></div>
<p class="text-muted text-sm sf-ayuda">Clic para elegir · arrastra, Mayús+clic o Mayús+flechas para varias celdas · <strong>Ctrl/⌘+clic</strong> añade celdas sueltas (la barra de abajo muestra la <strong>suma</strong> de lo elegido) · en pantalla táctil, <strong>mantén pulsada una celda y arrastra</strong> para elegir el rango · Enter o doble clic para editar · Supr borra · Ctrl+C / Ctrl+V copian y pegan desde Excel (las fórmulas ajustan sus referencias; el asa de la esquina rellena series) · Ctrl+F busca y reemplaza · Fórmulas: empieza con <code>=</code> y <strong>haz clic en las celdas</strong> (o arrastra) para insertar sus referencias; hay funciones de matemáticas, estadística, texto, búsqueda, fechas, finanzas y del proyecto (<code>AVANCE("1.2.3")</code>, <code>FIN(…)</code>). <strong>Combinar</strong>/<strong>Separar</strong>, el formato condicional y los gráficos están en la cinta; el embudo de cada columna filtra. El encabezado es la fila 1.</p>

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
<script src="/vendor/gestos-lienzo.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/gestos-lienzo.js'); ?>"></script>
<script src="/vendor/hoja-inferior.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/hoja-inferior.js'); ?>"></script>
<script src="/vendor/sheetflow.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/sheetflow.js'); ?>"></script>
<script src="/vendor/tour.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tour.js'); ?>"></script>
<script>
// Guía rápida del editor de SheetFlow
window.Tour.registrar('sheetflow-editor', {
    auto: true,
    pasos: [
        { icono: 'fa-table-cells', titulo: 'La hoja', texto: 'Escribe y navega con las flechas como en Excel. Los cambios se <b>autoguardan</b> unos segundos después de editar. Arrastra para elegir varias celdas (o <b>Ctrl/⌘+clic</b> para celdas sueltas): abajo verás la <b>suma</b>. El <b>cuadradito de la esquina</b> rellena series y el <b>embudo</b> del encabezado filtra.' },
        { sel: '#sf-pestanas', icono: 'fa-table-columns', titulo: 'Cuatro pestañas', texto: '<b>Inicio</b> (formato, número, combinar, condicional), <b>Insertar</b> (renglones, columnas, imágenes, gráficos), <b>Datos</b> (tipos, totales, ordenar, fórmulas) y <b>Vista</b> (inmovilizar, zebra, zoom).' },
        { sel: '#sf-fx', icono: 'fa-square-root-variable', titulo: 'Fórmulas', texto: 'En la barra de abajo: empieza con <b>=</b> y usa SUMA, SI, SUMAR.SI… Las referencias son como en Excel (B3, C2:C10) y se ajustan solas al copiar o mover renglones. Con la fórmula abierta, haz <b>clic en las celdas</b> (o arrastra) para insertar sus referencias.' },
        { sel: '#sf-historial', icono: 'fa-clock-rotate-left', titulo: 'Historial y búsqueda', texto: '«Historial» guarda una copia al editar y permite volver atrás. Con <b>Ctrl + F</b> buscas y reemplazas en toda la tabla.' },
        { sel: '#sf-guardar', icono: 'fa-floppy-disk', titulo: 'Guardar y exportar', texto: 'Guarda la hoja (también con <b>Ctrl + S</b>). «Exportar» entrega el Excel, el CSV o el PDF con los formatos.' },
        { icono: 'fa-circle-check', titulo: 'Listo', texto: 'Recuerda: la tabla puede irse a una lámina del vPlan con «A vPlan», y las enlazadas se actualizan solas cuando cambia la hoja.' }
    ]
});
</script>
