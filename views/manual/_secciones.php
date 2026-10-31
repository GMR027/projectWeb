<?php
// Contenido del Manual de usuario. Cada sección: id, titulo, icono, roles (quién la ve), herr (clave de herramienta para visitantes) y cuerpo (HTML fijo).
$todos = ['admin', 'cliente', 'visitante'];
$gestion = ['admin', 'cliente'];
$admin = ['admin'];

return [

['id' => 'primeros-pasos', 'titulo' => 'Primeros pasos', 'icono' => 'fa-rocket', 'roles' => $todos, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>ProjectWeb es una plataforma para llevar el <strong>programa de obra</strong> de uno o varios proyectos: planear actividades, darles seguimiento semana a semana, documentar lo que pasa en obra (reportes, bitácora, minutas, fotos), controlar materiales y submittals, y generar los documentos para imprimir o compartir.</p>
<h4>Iniciar sesión</h4>
<ol>
<li>Entra a la página de inicio de sesión y escribe tu <strong>correo</strong> y <strong>contraseña</strong>.</li>
<li>Si olvidaste la contraseña, escribe desde el formulario de <em>Contacto</em> de la página principal para que te la restablezcan.</li>
<li>Para salir usa el botón <strong>Salir</strong> de la esquina superior derecha.</li>
</ol>
<p>La página principal también ofrece una <strong>demostración</strong> de solo lectura, con un proyecto de ejemplo, para conocer la herramienta sin crear nada.</p>
<h4>Los tres roles</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Rol</th><th>Qué puede hacer</th></tr></thead>
<tbody>
<tr><td><strong>Administrador</strong></td><td>Todo: crea proyectos y usuarios, ve todos los proyectos, edita cualquier dato, descarga documentos, responde la bandeja de entrada y administra el sistema (respaldos y almacenamiento).</td></tr>
<tr><td><strong>Cliente</strong></td><td>Ve y <em>gestiona sus propios proyectos</em> (los que él creó), y ve los que el administrador le asigne. Puede descargar documentos, escribir a soporte y crear hasta 2 visitantes. Cuántos proyectos puede crear y cuánto espacio tiene por proyecto lo fija el administrador.</td></tr>
<tr><td><strong>Visitante</strong></td><td>Solo <em>lectura</em>: ve los proyectos que le asignaron, pero no edita ni descarga. Además, quien lo creó puede limitarle las herramientas que ve.</td></tr>
</tbody>
</table>
<h4>Cómo está organizada la pantalla</h4>
<ul>
<li><strong>Menú lateral izquierdo.</strong> Arriba están los accesos generales (Inicio, Proyectos, etc.). Cuando abres un proyecto aparece la sección <em>Proyecto actual</em> con todas sus herramientas, y debajo las <em>Acciones rápidas</em>. Hasta abajo está este Manual.</li>
<li><strong>Encabezado del proyecto.</strong> Muestra el nombre, los días totales, la duración en meses y días, y las fechas de inicio y fin.</li>
<li><strong>Barra de herramientas del proyecto.</strong> Una fila de botones debajo del encabezado para saltar entre herramientas sin usar el menú lateral.</li>
<li><strong>Idioma.</strong> Los botones <strong>ES / EN</strong> de la parte superior cambian la interfaz entre español e inglés.</li>
</ul>
<h4>Convenciones que verás en todas partes</h4>
<ul>
<li>Un asterisco <strong>*</strong> junto a un campo significa que es obligatorio.</li>
<li>Los botones de color llamativo son la acción principal (Guardar, Crear); los de contorno son secundarios; los rojos eliminan y <strong>siempre piden confirmación</strong>.</li>
<li>Las fechas se muestran como <code>dd/mm/aaaa</code>.</li>
<li>Si no ves una herramienta del menú, es porque tu rol no la usa o porque no te la activaron.</li>
</ul>
HTML],

['id' => 'inicio', 'titulo' => 'Inicio y proyectos', 'icono' => 'fa-house', 'roles' => $todos, 'herr' => null, 'cuerpo' => <<<'HTML'
<h4>Inicio</h4>
<p>Es tu panel de control. Muestra un saludo, los <strong>proyectos recientes</strong> con su avance y botones para entrar a cada uno: <em>Ver Proyecto</em> (abre su Programa) y <em>Ajustes</em>. El enlace <em>Ver todos</em> lleva al listado completo.</p>
<h4>Portafolio de proyectos</h4>
<p>El administrador lo ve como <strong>Portafolio de Proyectos</strong>, el cliente como <strong>Mis Proyectos</strong> y el visitante como <strong>Proyectos Asignados</strong>. Cada tarjeta muestra la carátula, el progreso y el periodo del proyecto.</p>
<h4>Crear un proyecto <small>(administrador y cliente con cupo)</small></h4>
<ol>
<li>Pulsa <strong>+ Nuevo Proyecto</strong>. Los clientes solo ven el botón si todavía tienen cupo; el límite lo define el administrador.</li>
<li>Llena los datos: <strong>código*</strong> y <strong>nombre*</strong> (obligatorios), descripción, ubicación, empresa, contratista, número de trabajo (Job No.), logos, color identificador y las <strong>fechas de inicio y fin*</strong>.</li>
<li>Puedes subir el <strong>logo del contratista</strong>, el <strong>logo del proyecto</strong> y una <strong>carátula</strong> (imagen grande que se ve en el portafolio).</li>
<li>El administrador además elige al <em>propietario</em> y marca los clientes y visitantes que podrán ver el proyecto.</li>
<li>Pulsa <strong>Crear Proyecto</strong>. Se crea con la jornada de trabajo predeterminada: lunes a viernes completos, sábado medio día y domingo no laborable (la puedes cambiar en los Ajustes del proyecto).</li>
</ol>
<h4>Salud del proyecto (semáforo)</h4>
<p>Cada tarjeta del Inicio, y la ficha del proyecto (<em>Ver proyecto</em>), muestra un <strong>semáforo</strong> que junta datos de varias herramientas: actividades atrasadas, restricciones abiertas, submittals con envío vencido o rechazados, pendientes graves del punch, órdenes de cambio por aprobar y permisos de trabajo. Verde = todo en orden, amarillo = requiere atención, rojo = hay puntos urgentes. En el Inicio solo ves lo que necesita atención; en la ficha del proyecto ves todos los indicadores con su explicación. A la ficha llegas desde el menú del proyecto (<em>Proyecto → Salud del proyecto</em>) o con el botón del mismo nombre en <em>Ajustes del proyecto</em>; al Inicio, con <em>Inicio</em> en la parte de arriba del menú lateral. Pulsa un indicador para ir a la herramienta donde se resuelve. No guarda nada: se calcula cada vez que abres la página.</p>
<h4>Buscar en el proyecto</h4>
<p>Dentro de un proyecto, la caja <em>Buscar en el proyecto</em> de la barra superior busca en el programa, materiales, submittals, minutas, bitácora, reportes, archivo, punch, órdenes de cambio, permisos, documentos del Redactor y normativa (solo administrador). Todas las palabras deben aparecer; no importan acentos ni mayúsculas. Los visitantes solo ven resultados de las herramientas que tienen permitidas.</p>
<p>Desde el listado también puedes <strong>Editar</strong> o <strong>Eliminar</strong> un proyecto (al eliminarlo se borra todo lo que contiene).</p>
<p class="manual-nota"><strong>Espacio de almacenamiento.</strong> Cada proyecto tiene un límite de espacio para sus archivos, fotos, planos, fichas y logos. Si se llena, el sistema avisa y no deja subir más hasta que borres algo. Lo ves en <em>Ajustes del proyecto</em>. El administrador puede ampliar o reducir el espacio de los proyectos que creó: en <em>Ajustes del proyecto → Datos → Almacenamiento</em> escribe los MB que quieras asignarle (con 0 vuelve al límite heredado). Los clientes no pueden cambiarlo.</p>
<p class="manual-nota"><strong>Por dónde empezar.</strong> Al crear un proyecto eliges cómo arrancar: <em>paso a paso</em> (una ventana con un paso a la vez), <em>lista de pasos</em> (todos a la vista) o solo crear el proyecto. Los pasos son: calendario y festivos, primera partida o importación del programa, equipo, materiales, documentos y fechas del contrato; cada uno tiene su captura rápida y un enlace a la herramienta completa. Lo que captures se guarda en esa herramienta. Puedes volver cuando quieras en <em>Ajustes del proyecto → Por dónde empezar</em>. Nada es obligatorio.</p>
<p class="manual-nota"><strong>Apariencia del proyecto.</strong> En <em>Ajustes del proyecto → General → Apariencia del proyecto</em> eliges si el proyecto se abre <em>siempre claro</em>, <em>siempre oscuro</em> o «como cada usuario» (lo que cada quien tenga en su navegador), y su <strong>color de acento</strong> entre 24 tonos. Se guarda en el proyecto: se mantiene al cambiar de navegador o de equipo y lo ven todos los que abran ese proyecto; el mismo color pinta la carátula en el portafolio. También eliges si la <strong>barra superior de herramientas</strong> escribe el nombre debajo de cada icono (<em>Mostrar nombres de herramientas: Sí / No</em>); con «No» queda solo el icono y el nombre aparece al pasar el puntero. En <em>Apariencia de menú superior</em> escoges entre <strong>Modo clásico</strong> (la barra de iconos agrupada por sección), <strong>Modo panel</strong> (oculta la barra y deja fijo el menú lateral con todas las herramientas) y <strong>Modo pestaña</strong> (una pestaña por sección con sus iconos). No afecta a lo que se exporta (PDF, Excel, XML).</p>
<p class="manual-nota"><strong>Campana de notificaciones.</strong> En la barra superior, la campana reúne tres cosas: las <em>novedades de la plataforma</em> (lo último que cambió en ProjectWeb), las <em>actividades de hoy</em> del proyecto que estés viendo y lo que está pendiente en <em>Acciones rápidas</em>. Al abrirla, las novedades quedan marcadas como vistas. Cada quien ve solo lo que le toca: el cliente no recibe avisos de herramientas del administrador. El historial completo de novedades está en <em>Ajustes del proyecto → Novedades</em> y también en <a href="/novedades">Novedades de la plataforma</a>.</p>
<p class="manual-nota"><strong>Respaldo de proyecto.</strong> En <em>Ajustes del proyecto</em>, debajo de Almacenamiento, puedes pulsar <em>Respaldar ahora</em> o dejar activo el respaldo automático de las 23:30 (se conserva uno diario, uno semanal y uno mensual, para que elijas a qué momento volver; más los últimos 5 manuales). <em>Restaurar desde el respaldo anterior</em> devuelve el proyecto a como estaba en el último respaldo (por ejemplo, para recuperar los cambios de ayer); antes se guarda una copia del estado actual por si quieres deshacer. Solo lo usan el administrador y el cliente dueño del proyecto.</p>
HTML],

['id' => 'usuarios', 'titulo' => 'Usuarios y accesos', 'icono' => 'fa-users-gear', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Solo el administrador gestiona las cuentas. El listado permite <strong>buscar</strong>, <strong>editar</strong> y <strong>eliminar</strong> usuarios, y muestra cuántos proyectos tiene cada uno y su espacio por proyecto.</p>
[[fig:usuarios]]
<h4>Crear un usuario</h4>
<ol>
<li>Pulsa <strong>+ Nuevo Usuario</strong>.</li>
<li>Escribe nombre completo y correo, y elige el <strong>rol</strong> (administrador, cliente o visitante).</li>
<li>Para un <strong>cliente</strong> define: <em>límite de proyectos</em> (0 = no puede crear proyectos; los que tú le asignes no cuentan), <em>espacio por proyecto en MB</em> (1024 MB = 1 GB) y, si quieres, el logo de su empresa.</li>
<li>Marca los <strong>proyectos con acceso</strong>: cliente y visitante verán únicamente esos (el cliente además ve los que él mismo cree). El administrador ve todo.</li>
<li>Escribe y repite la contraseña. Al editar, deja los campos de contraseña vacíos para conservar la actual.</li>
</ol>
<h4>Herramientas de un visitante</h4>
<p>Al crear o editar un visitante puedes marcar <strong>qué herramientas puede ver</strong> (Programa, vPlan, Lookahead, etc.). Si dejas todas marcadas no tiene restricción; si desmarcas alguna, desaparece de su menú. El visitante nunca puede editar ni descargar.</p>
HTML],

['id' => 'visitantes', 'titulo' => 'Mis visitantes', 'icono' => 'fa-user-group', 'roles' => ['cliente'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Como cliente puedes dar acceso de <strong>solo lectura</strong> a otras personas (por ejemplo, tu cliente final o un supervisor) creando hasta <strong>2 visitantes</strong>.</p>
<ol>
<li>Entra a <strong>Mis Visitantes</strong> y pulsa <strong>+ Nuevo visitante</strong>.</li>
<li>Escribe nombre, correo y contraseña (repítela).</li>
<li>Marca los <strong>proyectos que puede ver</strong>. Solo aparecen tus proyectos.</li>
<li>Puedes limitar las <strong>herramientas</strong> que verá. Si lo dejas completo, ve todas.</li>
</ol>
<p>Un visitante siempre tiene rol de visitante: no puede editar ni descargar, aunque tú sí. Para crear otro cuando ya tienes el máximo, elimina uno.</p>
HTML],

['id' => 'bandeja', 'titulo' => 'Bandeja de entrada', 'icono' => 'fa-inbox', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Reúne en un solo lugar los <strong>mensajes del formulario de contacto</strong> de la página principal y los <strong>mensajes de soporte</strong> que escriben tus clientes. El menú lateral muestra el número de mensajes sin leer.</p>
[[fig:bandeja]]
<ul>
<li><strong>Filtros:</strong> Todos, Contacto o Soporte.</li>
<li><strong>Abrir</strong> un mensaje lo marca como leído; puedes volver a marcarlo como <em>no leído</em>.</li>
<li><strong>Responder por correo</strong> abre tu programa de correo con el asunto ya escrito.</li>
<li>Los mensajes de <strong>soporte</strong> se responden dentro del sistema: escribe un <em>título</em> y una <em>descripción</em> y, si hace falta, adjunta hasta <strong>5 fotos</strong> (jpg, png, webp o gif, de hasta 8 MB cada una). El cliente ve la respuesta en su pantalla de Soporte. Puedes editar tus respuestas y ves el historial de <em>Respuestas enviadas</em>.</li>
<li><strong>Eliminar</strong> borra el mensaje.</li>
</ul>
<p class="manual-nota">El formulario de contacto de la página principal tiene protecciones contra correo basura y limita a 5 mensajes por hora por dirección.</p>
HTML],

['id' => 'soporte', 'titulo' => 'Soporte', 'icono' => 'fa-headset', 'roles' => ['cliente'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Si tienes una duda o necesitas algo, escríbela aquí: el administrador la recibe en su bandeja de entrada.</p>
<ol>
<li>Entra a <strong>Soporte</strong>.</li>
<li>En <em>Nuevo mensaje</em> confirma tu nombre, escribe el <strong>tema</strong> y la <strong>información</strong> (lo que necesitas o el problema que ves) y pulsa <strong>Enviar</strong>.</li>
<li>En <strong>Mis mensajes</strong> ves el estado de cada uno y, cuando el administrador responde, aparece su <strong>respuesta</strong>. Un contador en el menú te avisa de respuestas nuevas.</li>
<li>Puedes <strong>editar</strong> o <strong>eliminar</strong> tus propios mensajes.</li>
</ol>
HTML],

['id' => 'sistema', 'titulo' => 'Ajustes del sistema', 'icono' => 'fa-gear', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>El <strong>Ajustes</strong> del menú general (distinto de los Ajustes de cada proyecto) administra el servidor completo.</p>
[[fig:respaldos]]
<h4>Almacenamiento del servidor</h4>
<p>Muestra el espacio usado y, cada día, guarda una foto del uso para dibujar la gráfica <em>Evolución del espacio usado</em> (aparece cuando hay al menos dos días registrados). Sirve para estimar cuándo se llenará un volumen. Recuerda que los volúmenes no reservan espacio: comparten el disco con otras aplicaciones.</p>
<h4>Respaldos</h4>
<ul>
<li>Cada respaldo es un <strong>.zip</strong> con la base de datos completa (incluye usuarios y contraseñas) y todo el volumen de archivos. Puedes ponerle un <em>nombre</em> opcional.</li>
<li><strong>Respaldo diario automático:</strong> activa la programación y elige cuántos respaldos conservar (de 1 a 30); los más viejos se borran solos.</li>
<li>Puedes <strong>descargar</strong> un respaldo o <strong>restaurar</strong> desde un .zip de tu equipo. Restaurar sustituye los datos actuales por los del respaldo: úsalo con cuidado.</li>
</ul>
<h4>Idioma</h4>
<p>Cambia entre Español e English para la interfaz.</p>
HTML],

['id' => 'programa', 'titulo' => 'Programa de obra', 'icono' => 'fa-list-check', 'roles' => $todos, 'herr' => 'programa', 'cuerpo' => <<<'HTML'
<h4>Actividades en riesgo</h4>
<p>Junto al nombre de una actividad pendiente que <strong>inicia en los próximos 30 días</strong> (o ya debió iniciar) puede aparecer la marca <strong>⚠ En riesgo</strong>. Significa que alguno de estos puntos está sin resolver: un <em>material vinculado sin submittal aprobado</em> (ni en un submittal aprobado ni con status A/AN en el MSR), un <em>pendiente grave del punch</em> (severidad alta o crítica, abierto) o un <em>permiso de trabajo que falta</em>. Pasa el cursor para ver el motivo y pulsa la marca para ir a donde se resuelve. Las actividades marcadas «sin material» no se evalúan por material. Con el filtro <em>Riesgo → Solo en riesgo</em> ves únicamente esas actividades. También aparece en el Lookahead y en el Last Planner.</p>
<p>Es el corazón del sistema: el programa de obra organizado en <strong>partidas</strong> (capítulos o frentes, con su código WBS) y <strong>actividades</strong> dentro de cada partida. La página muestra, de arriba abajo, los datos del proyecto, los filtros, la tabla <em>Desglose WBS &amp; Partidas</em> y el <em>Diagrama de Gantt por Semanas</em>.</p>
[[fig:programa]]
<h4>Tipo de obra y base de partidas</h4>
<p>En <em>Materiales y cambios → Tipo de obra</em> puedes elegir el tipo de obra del proyecto. Además de dar contexto a las herramientas de IA, el sistema ofrece una <strong>base de partidas y actividades</strong> para arrancar más rápido. Las plantillas de construcción incluyen: <strong>vivienda unifamiliar</strong>, <strong>edificio multifamiliar o comercial</strong>, <strong>nave industrial</strong>, <strong>remodelación y adecuación</strong>, <strong>urbanización y obra civil</strong> y la <strong>edificación</strong> general. La base solo se crea si el proyecto todavía no tiene partidas; después puedes renombrar, mover o eliminar lo que necesites.</p>
<h4>Crear una partida</h4>
<ol>
<li>Pulsa <strong>+ Nueva Partida</strong>.</li>
<li>Escribe el <strong>código WBS*</strong> (por ejemplo 4.0) y el <strong>nombre*</strong>. Opcionalmente descripción, un color de fondo (se usa en el Gantt) y el orden.</li>
</ol>
<h4>Crear una actividad</h4>
<ol>
<li>Pulsa <strong>+ Nueva Actividad</strong> (o el botón <em>+</em> de la fila de una partida, que ya elige esa partida).</li>
<li>Completa: <strong>partida*</strong>, <strong>código*</strong>, <strong>nombre*</strong>, descripción, <strong>fecha de inicio*</strong>, <strong>duración en días hábiles*</strong>, <strong>progreso (%)*</strong>, prioridad y orden.</li>
<li>La <strong>fecha de fin se calcula sola</strong> usando el calendario del proyecto: salta los días no laborables y los festivos.</li>
<li>Marca <strong>Es un hito</strong> si es un punto de control: dura 0 días y empieza y termina el mismo día.</li>
<li><strong>Icono de la actividad</strong> (opcional): sube una imagen pequeña. Se muestra en forma circular sobre la barra de la actividad en la <a href="#lineatiempo">Línea de tiempo activa</a> (con la casilla «Icono de actividad») y en el programa si activas la columna <strong>Icono</strong> en <em>Diseñar programa</em>. Al editar puedes reemplazarla o quitarla.</li>
<li>En <em>Materiales</em> puedes indicar qué materiales del catálogo necesita la actividad, o marcar <strong>Actividad sin material</strong> si no requiere (así deja de aparecer en las Acciones rápidas).</li>
</ol>
<h4>Cosas que el sistema hace por ti</h4>
<ul>
<li><strong>Estatus automático:</strong> 0 % = No iniciada, 100 % = Completada, cualquier valor intermedio = En proceso.</li>
<li><strong>Fechas de la partida:</strong> se recalculan solas con las de sus actividades cada vez que creas, editas o eliminas una.</li>
</ul>
<h4>Agregar y reordenar actividades</h4>
<ul>
<li><strong>Alta rápida:</strong> al final de cada partida hay una fila para escribir solo el nombre y pulsar Enter. El código sigue al de la partida (2.1, 2.2…) y, si no pones fecha, empieza el día hábil siguiente a la última actividad.</li>
<li><strong>Subir y bajar:</strong> las flechas de cada actividad la mueven dentro de su partida y la partida <strong>renumera</strong> sus actividades automáticamente. El cambio queda en el historial de «Ordenar y renumerar» y se puede deshacer.</li>
<li><strong>Eliminar una partida</strong> elimina también todas sus actividades (con sus restricciones, dependencias y materiales vinculados).</li>
</ul>
<h4>Cambiar las fechas rápidamente</h4>
<p>En la tabla, el icono de <em>Cambiar fechas</em> de una actividad abre una ventana con las fechas de inicio y fin. Si mueves el inicio, el fin se corre con la misma duración; después puedes ajustar el fin.</p>
<h4>Copiar y pegar fechas</h4>
<p>Haz clic sobre cualquier fecha visible (en el programa, en tablas, en el vPlan o en el Gantt) y pulsa <strong>Ctrl + C</strong>; se copia el día completo como <span class="mono">AAAA-MM-DD</span>, aunque solo hayas seleccionado una parte del texto. Después entra al campo de fecha de destino y pulsa <strong>Ctrl + V</strong>. Acepta fechas escritas como <span class="mono">AAAA-MM-DD</span>, <span class="mono">DD/MM/AAAA</span>, <span class="mono">20 oct 2026</span> o <span class="mono">20 de octubre de 2026</span>. Al pegar en una fecha editable se guarda igual que si la escribieras; el campo se marca en verde un instante para confirmar.</p>
<h4>Restricciones</h4>
<p>Una <strong>restricción</strong> es algo que impide o pone en riesgo una actividad (un RFI, un material pendiente, un permiso…).</p>
<ol>
<li>Desde una actividad pulsa el triángulo de <em>Agregar restricción</em> (o entra a la actividad completa).</li>
<li>Elige el <strong>tipo*</strong>, un código opcional (ej. RFI-04), la fecha y una <strong>descripción*</strong>.</li>
<li>Cuando se resuelva, pulsa <strong>Resolver</strong>. Las restricciones activas aparecen en el Gantt, en el Lookahead (columna de limitaciones), en vPlan y en las Acciones rápidas.</li>
</ol>
<h4>Subactividades</h4>
<p>Una actividad se puede dividir en <strong>subactividades</strong> con su propio nombre, fechas y avance (0–100 %). Se agregan, editan y eliminan en la página de la actividad completa, debajo de «Restricciones de esta Actividad».</p>
<ul>
<li>Mientras la actividad tenga subactividades, su <strong>Progreso (%)</strong> es el <strong>promedio</strong> de ellas y el campo queda bloqueado; en la tabla del programa se muestra con un candado.</li>
<li>Si eliminas la última subactividad, se conserva el último promedio como avance manual de la actividad.</li>
<li>Las subactividades <strong>no</strong> aparecen en el programa de obra, dependencias, línea de tiempo ni Lookahead: solo alimentan el avance de su actividad.</li>
</ul>
<p>Con el botón <strong>Generar tabla en SheetFlow</strong> se crea una tabla vinculada con una fila por subactividad y las columnas <em>Nombre, Inicio, Fin y Avance (%)</em>. La tabla se abre en <a href="#sheetflow">SheetFlow</a> y desde ese momento se edita en los dos sentidos: lo que cambies en la tabla se guarda en la subactividad (y en el avance de la actividad) y lo que cambies en la actividad se refleja en la tabla. Las columnas adicionales que agregues se quedan solo en SheetFlow y en vPlan; no aparecen en la actividad. Si eliminas un renglón de la tabla, se elimina su subactividad (el editor lo pide confirmar). Si la tabla tiene fórmulas en columnas adicionales, agrega o elimina renglones en SheetFlow para que las referencias se ajusten. <strong>Desvincular</strong> conserva la tabla como una tabla normal y no borra las subactividades.</p>
<h4>Filtros</h4>
<p>Arriba de la tabla puedes filtrar por <strong>partida</strong>, <strong>estatus</strong>, <strong>prioridad</strong> y <strong>restricción</strong> (con o sin). <em>Limpiar</em> quita los filtros.</p>
<h4>Importar desde otro programa</h4>
<ol>
<li>Pulsa <strong>Importar XML / POD</strong>.</li>
<li>Sube un archivo <strong>.xml</strong> (MS Project, formato MSPDI) o un <strong>.pod</strong> exportado en XML.</li>
<li>Pulsa <strong>Analizar archivo</strong>: verás una <em>vista previa</em> con código, nombre, inicio y avance, y los avisos que encontró.</li>
<li>Si es lo que esperabas, <strong>Confirmar importación</strong>; si no, <em>Cancelar</em>.</li>
</ol>
<p class="manual-nota">Solo se importan nombres, fechas, avance, hitos, prioridad, notas y niveles. <strong>No</strong> se traen predecesoras, recursos ni calendarios.</p>
<h4>Diagrama de Gantt por semanas</h4>
<p>Debajo de la tabla, cada fila tiene su barra distribuida por semana: color según el estatus y relleno según el avance; un punto marca las actividades con restricción. Si activaste las <strong>Dependencias de obra</strong> en modo Informativo o Automático, aparece la casilla <em>Dependencias</em> en la leyenda para mostrar u ocultar las flechas entre actividades (rojas en la ruta crítica, naranjas con conflicto). El Gantt no se muestra en pantallas de celular.</p>
HTML],

['id' => 'dependencias', 'titulo' => 'Dependencias de obra', 'icono' => 'fa-diagram-project', 'roles' => $todos, 'herr' => 'dependencias', 'cuerpo' => <<<'HTML'
<p>Aquí defines <strong>qué actividad debe terminar (o empezar) antes de que otra pueda empezar (o terminar)</strong>. Con eso el sistema calcula la <em>ruta crítica</em>, las holguras y los conflictos, y los dibuja en un diagrama de barras.</p>
<h4>1. Elige el modo</h4>
<ul>
<li><strong>Apagado:</strong> las fechas se escriben a mano, como siempre. Las relaciones se guardan pero no se calcula nada.</li>
<li><strong>Informativo:</strong> calcula la ruta crítica, las holguras y los conflictos y los dibuja. <em>Nunca mueve fechas.</em></li>
<li><strong>Automático:</strong> si una actividad se atrasa, sus sucesoras se recorren solas con tu calendario. Siempre con vista previa y con opción de deshacer.</li>
</ul>
<p>Los chips de arriba resumen: relaciones, actividades en ruta crítica, con conflicto, fin actual y fin calculado.</p>
<h4>2. Crea relaciones</h4>
<p>Hay dos maneras:</p>
<ul>
<li><strong>Botón Enlazar:</strong> pulsa <em>Enlazar</em>, haz clic en la actividad <strong>predecesora</strong> y luego en la <strong>sucesora</strong>. Esc cancela.</li>
<li><strong>Arrastrando:</strong> pasa el cursor por una barra, aparece un círculo en su extremo; arrástralo hasta otra barra.</li>
</ul>
<p>También puedes agregarlas desde el panel derecho con el selector <em>Agregar</em> en predecesoras o sucesoras.</p>
<h4>3. Tipos de relación y retraso</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Tipo</th><th>Significa</th></tr></thead>
<tbody>
<tr><td><strong>FS</strong> Fin → inicio</td><td>La sucesora empieza cuando termina la predecesora (el caso más común).</td></tr>
<tr><td><strong>SS</strong> Inicio → inicio</td><td>Las dos empiezan juntas.</td></tr>
<tr><td><strong>FF</strong> Fin → fin</td><td>Las dos terminan juntas.</td></tr>
<tr><td><strong>SF</strong> Inicio → fin</td><td>La sucesora termina cuando empieza la predecesora.</td></tr>
</tbody>
</table>
<p>El <strong>retraso</strong> son días hábiles de espera (un número negativo es un adelanto). Se cambia en el panel derecho.</p>
<h4>4. El panel de la actividad</h4>
<p>Haz clic en una barra para ver sus datos, su holgura y sus relaciones. Ahí cambias tipo y retraso, quitas una relación con la ✕ o marcas <strong>📌 Fecha fija</strong>: una actividad con fecha fija no se mueve en modo automático; solo se avisa si choca con sus predecesoras.</p>
<h4>5. Leer el diagrama</h4>
<ul>
<li><strong>Rojo:</strong> ruta crítica. <strong>Naranja con ⚠:</strong> conflicto (empieza antes de lo que exigen sus predecesoras). <strong>Borde punteado:</strong> la fecha calculada.</li>
<li>La línea naranja vertical es <em>hoy</em>.</li>
<li>Si hay un <strong>ciclo</strong> (A depende de B y B de A) se avisa con las actividades involucradas; quita una relación para poder calcular.</li>
</ul>
<h4>6. Moverte por el diagrama</h4>
<ul>
<li><strong>Zoom:</strong> botones lupa − y +, el botón <em>Ajustar</em> (encaja todo el programa) o <strong>Ctrl + rueda del mouse</strong>.</li>
<li><strong>Desplazarte:</strong> rueda del mouse (vertical), o mantén presionada la <strong>rueda (botón central)</strong> y arrastra para moverte en horizontal y vertical a la vez.</li>
<li><strong>Filtros:</strong> por partida, <em>Solo actividades con relaciones</em> y un buscador de actividades.</li>
</ul>
<h4>7. Reprogramar (modo automático)</h4>
<ol>
<li>Si hay actividades que deben moverse, aparece el botón <strong>Revisar y reprogramar</strong>.</li>
<li>Verás una tabla con cada actividad, su inicio y fin actuales y los nuevos, y cuántos días se mueve.</li>
<li><strong>Aplicar</strong> las reprograma; las de fecha fija no se tocan. <strong>Deshacer la última reprogramación</strong> las regresa a como estaban.</li>
</ol>
HTML],

['id' => 'lineatiempo', 'titulo' => 'Línea de tiempo activa', 'icono' => 'fa-chart-gantt', 'roles' => $todos, 'herr' => 'lineatiempo', 'cuerpo' => <<<'HTML'
<p>Es el <strong>Gantt diario de todos los meses del proyecto, hecho para mover fechas con el mouse</strong>. A diferencia del Gantt del Programa (que es de solo lectura y por semanas), aquí ves los días y <em>arrastras las barras</em> para reprogramar; los cambios se guardan al instante, sin recargar.</p>
<h4>1. Qué muestra</h4>
<ul>
<li><strong>Todos los meses con actividades</strong> en una sola barra de tiempo: la banda superior trae el nombre de cada mes. Las actividades que empezaron antes o terminan después de lo que se ve se recortan al borde con una flechita ◀ ▶ que indica que continúan.</li>
<li>Las actividades se agrupan por <strong>partida, con el color de la partida</strong> en cada barra; en el centro de cada barra está el <strong>número de la actividad y, entre paréntesis, los días que dura</strong> (por ejemplo <code>1.4 (5 d)</code>).</li>
<li>Por defecto se ven las <strong>no iniciadas y en proceso</strong> (lo que se está ejecutando). En <em>Estado</em> puedes ver todas o solo las completadas.</li>
<li>Con <em>Solo con restricción</em> te quedas únicamente con las actividades (y partidas) que tienen una restricción activa; la marca⚠ queda <strong>fuera de la barra, a la derecha y en rojo</strong> y <strong>al acercar el puntero se abre una ventana flotante</strong> (con retardo, igual que las burbujas de ayuda de arriba) con el detalle de cada restricción y un botón <strong>Resolver</strong> que la marca como resuelta sin recargar.</li>
<li>Cada barra lleva, <strong>fuera de ella y a la derecha</strong> (justo antes de la restricción), el estado en un circulito <strong>relleno con la marca en blanco</strong>: <strong style="color:#ef4444">rojo con ✕</strong> si está <em>no iniciada</em>, <strong style="color:#d97706">ámbar con reloj</strong> si está <em>en proceso</em> y <strong style="color:#22c55e">verde con paloma</strong> si está <em>completada</em> (los tres están explicados en la leyenda de arriba).</li>
<li>Con la casilla <strong>Icono de actividad</strong> cada actividad muestra, en forma <strong>circular y sobre su barra</strong>, la imagen que se le cargó al crearla o editarla (el disco mide 48 px y las filas se hacen más altas para no tapar la barra).</li>
<li>Los <strong>días no hábiles</strong> (según la jornada del proyecto y sus festivos) aparecen sombreados y la línea naranja es <em>hoy</em>.</li>
</ul>
<h4>2. Cómo se mueven las fechas</h4>
<ul>
<li><strong>Arrastra el cuerpo de la barra</strong> para cambiar inicio y fin juntos (la actividad conserva su largo).</li>
<li><strong>Arrastra la flecha ◀ del extremo izquierdo</strong> para cambiar solo el inicio, o <strong>la flecha ▶ del extremo derecho</strong> para cambiar solo el fin. Mientras arrastras ves las fechas en una etiqueta junto al cursor.</li>
<li>Al soltar, la fecha se <strong>ajusta al día hábil más cercano</strong> y se guarda sola. Si el movimiento no se puede guardar, la barra regresa a su lugar y se avisa.</li>
<li>Un <strong>hito</strong> (◆) no tiene duración: solo se mueve completo.</li>
</ul>
<p class="manual-nota">Las fechas se guardan con las mismas reglas del Programa: la duración se recalcula en días hábiles con el calendario del proyecto, se actualizan las fechas de la partida y, si las <a href="#dependencias">Dependencias de obra</a> están en modo automático, las actividades sucesoras se recorren solas (lo avisa un mensaje).</p>
<h4>3. Cómo moverte en el tiempo</h4>
<ul>
<li><strong>La rueda del ratón sobre el diagrama</strong> amplía o reduce los días visibles (se ancla en el día donde está el cursor; en el celular usa dos dedos).</li>
<li><strong>Con la rueda presionada (botón central) puedes arrastrar el diagrama</strong> a los lados o hacia arriba y abajo, para ver otros meses u otras actividades sin cambiar el zoom.</li>
<li>Los botones <strong>‹</strong> y <strong>›</strong> saltan al mes anterior o siguiente, y <strong>Hoy</strong> regresa a la fecha de hoy: todo en la misma ventana y sin recargar.</li>
<li>Los filtros quedan en la dirección de la página: puedes recargar, marcar la página como favorita o compartirla con la misma partida.</li>
<li>El cliente y los visitantes ven la línea de tiempo <strong>en solo lectura</strong>.</li>
</ul>
<h4>4. Exportar la vista</h4>
<p>Con <strong>Exportar</strong> (arriba, junto al botón <em>Programa</em>) descargas <strong>exactamente lo que estás viendo, con los filtros elegidos</strong> (partida, estado, «solo con restricción» e icono de actividad):</p>
<ul>
<li><strong>PDF (una sola hoja):</strong> el papel mide el tamaño que haga falta para caber todo en una hoja (el ancho según los días del rango y el alto según las actividades), así que nunca sale partido en varias páginas.</li>
<li><strong>LibreOffice Calc (.ods):</strong> la misma línea de tiempo como tabla editable: a la izquierda el código, la actividad, el estado, el % de avance, fechas, duración y restricción; a la derecha, una columna por día con la barra de cada actividad (parte completada en color aclarado) y los días no hábiles sombreados.</li>
</ul>
<p class="manual-nota">Los dos formatos se generan al instante con los datos actuales del programa: si moviste fechas o cambiaste avances, vuelve a exportar y tendrás lo último. El nombre del archivo sigue la <a href="#exportar">nomenclatura de exportación</a> que hayas configurado. Lo mismo puedes hacer desde <em>Exportar</em> (Centro de Exportación), donde además combinas los estados por casillas (no iniciadas, en proceso, completadas) y puedes marcar <strong>varios meses</strong> a la vez: cada mes sale como archivo propio dentro de un ZIP.</p>
HTML],

['id' => 'planificador', 'titulo' => 'Planificador', 'icono' => 'fa-diagram-next', 'roles' => $todos, 'herr' => 'planificador', 'cuerpo' => <<<'HTML'
<p>Está en la segunda pestaña de <strong>Lookahead</strong> (<em>Planeación › Lookahead › Planificador</em>). Te ayuda a decidir <strong>qué hacer según el material disponible</strong>. Cruza las actividades pendientes del programa con los materiales que les asignaste en el catálogo.</p>
<ul>
<li><strong>Ventana:</strong> elige el periodo que quieres revisar.</li>
<li><strong>Filtros:</strong> por partida y <em>Solo actividades con materiales vinculados</em>.</li>
<li>El apartado <strong>«Tengo estos materiales comprados: ¿qué puedo adelantar?»</strong> lista las actividades que ya podrían ejecutarse porque su material está comprado. Si no aparece nada, es porque ningún material comprado está vinculado todavía a actividades pendientes.</li>
</ul>
<p class="manual-nota">Para que funcione, vincula materiales a las actividades (en la actividad, sección <em>Materiales</em>) y registra las fechas de compra y entrega en el <a href="#materiales">Catálogo de materiales</a>.</p>
HTML],

['id' => 'lookahead', 'titulo' => 'Lookahead', 'icono' => 'fa-binoculars', 'roles' => $todos, 'herr' => 'lookahead', 'cuerpo' => <<<'HTML'
<p>El <strong>Lookahead</strong> es la mirada a las próximas semanas (por defecto la actual y las cuatro siguientes): qué actividades tocan, en qué días y qué limitaciones tienen.</p>
<ul>
<li>Con las flechas <strong>◀ ▶</strong> cambias de semana y con <strong>Hoy</strong> regresas a la actual.</li>
<li>La tabla lista cada actividad con su <strong>ID (EDT)</strong>, nombre, semana y <strong>% completo</strong>, y marca con <strong>X</strong> los días en que se trabaja.</li>
<li>Con <strong>−</strong> y <strong>+</strong> (junto al selector de mes) quitas o agregas semanas visibles, de 1 a 8 después de la actual. Esos botones no salen en el PDF.</li>
<li>En <strong>Ajustes</strong> cambias el nombre de la herramienta, el de la columna PTA, el título del banner (con {semanas}, {proyecto}, {codigo}) y eliges una <strong>plantilla de encabezado</strong> (cajetín con logos hecho en Estampas inteligentes). El administrador también agrega, renombra o quita ahí las <strong>limitaciones</strong>; son las mismas del programa de obra y Last Planner, así que el cambio vale en todos los proyectos. Una limitación con restricciones registradas no se puede quitar hasta resolverlas.</li>
<li>La columna <strong>PTA</strong> (o el nombre que le pongas) es un botón: haz clic para alternar entre <strong>OK</strong> y <strong>!</strong> y así marcar el estado de esa actividad.</li>
<li>El bloque <strong>Limitaciones</strong> muestra las restricciones activas de cada actividad, una columna por tipo de restricción.</li>
</ul>
<p>Los datos salen del programa de obra: si cambias fechas o avances, el Lookahead se ajusta solo. Para imprimirlo o editarlo usa <em>Exportar</em> → <strong>Lookahead</strong> (PDF tabloide, o .xlsx / .ods editable).</p>
HTML],

['id' => 'vplan', 'titulo' => 'vPlan (láminas)', 'icono' => 'fa-sheet-plastic', 'roles' => $todos, 'herr' => 'vplan', 'cuerpo' => <<<'HTML'
<p>El vPlan es una <strong>lámina libre</strong>: un plano sobre el que marcas visualmente el avance, acompañado de las tablas del programa (de una o varias partidas), el recovery y otras tablas. Cada elemento es un <strong>bloque</strong> que colocas donde quieras, en la hoja del tamaño que elijas.</p>
<h4>vPlans y áreas</h4>
<p>Puedes tener <strong>varios vPlan por proyecto</strong> (por ejemplo, uno por disciplina o por fase). Dentro de cada vPlan hay <strong>una lámina por área</strong>, cada una con su plano, tablas, título, simbología y distribución. En el gestor del vPlan usa el <strong>ojo</strong> para mostrar u ocultar láminas (lo oculto no se ve ni se exporta, pero se conserva), las flechas para ordenarlas, el campo <strong>Grupo</strong> para reunirlas por disciplina o nivel, y <strong>Duplicar</strong> para crear otra área con el mismo diseño y tablas (solo subes el plano nuevo). Dentro de una lámina, la lista <strong>Capas</strong> del modo diseño oculta o muestra cada bloque.</p>
<p>Es la primera vez que usas estas herramientas: el botón <strong>Guía rápida</strong> (o <em>abrir con <code>?tour=1</code></em>) recorre la pantalla paso a paso; se muestra sola la primera vez y se puede repetir cuando quieras. En el gestor del vPlan hay dos vistas: <strong>Galería</strong> (tarjetas con miniatura que se reordenan arrastrando) y <strong>Lista</strong> (detallada, con orden y grupos).</p>
<h4>Crear una lámina</h4>
<p>Al crearla se abre un <strong>asistente de tres pasos</strong> (qué lleva · tamaño de hoja · datos y plano) y, al terminar, la lámina entra directa a <strong>Acomodar bloques</strong> con la guía encima: ahí colocas el plano y las tablas.</p>
<ol>
<li>En <strong>vPlan</strong> pulsa para crear una lámina. La <strong>partida inicial</strong> es opcional: solo arma el primer bloque de tareas.</li>
<li>Elige el <strong>tamaño de la hoja</strong> con las tarjetas de proporción (tabloide ★ es la más usada, carta, A4, A3, A2, A1, Arch D u otra medida en mm, cm o pulgadas) y su orientación.</li>
<li>Escribe el <strong>título</strong> y el <strong>supervisor</strong>.</li>
<li>Sube el <strong>plano</strong> (imagen) y, si quieres, la <strong>simbología</strong> (imagen con la leyenda).</li>
<li>Si elegiste partida, marca qué actividades muestra. Si no marcas ninguna, muestra toda la partida.</li>
<li>Agrega las columnas de <strong>recovery</strong> que necesites (una o más) en el modo diseño, pestaña «Hoja».</li>
</ol>
<h4>Modo diseño: poner cada cosa donde quieras</h4>
<p>En la lámina pulsa <strong>Modo diseño</strong>. Cada elemento es un bloque que arrastras para moverlo y redimensionas con las esquinas o los bordes (flechas para afinar, Supr para quitarlo; «Rejilla» alinea a 0.5 %). Las herramientas están en el panel de la derecha, en cuatro pestañas: <strong>Propiedades</strong>, <strong>Capas</strong>, <strong>Insertar</strong> y <strong>Hoja</strong>; con la flecha de la cabecera ocultas el panel para ver más hoja, y con <strong>↶ ↷</strong> deshaces o rehaces (igual que con Ctrl+Z y Ctrl+Y) y con <strong>?</strong> ves todos los atajos. A la izquierda de la lámina, el panel de <strong>láminas</strong> (con miniaturas, agrupadas) te deja cambiar de área con un clic.</p>
<ul>
<li><strong>Título, supervisor, fecha y texto libre:</strong> se escriben a mano (o quedan automáticos), con tamaño de letra, alineación, negrita y color.</li>
<li><strong>Logos, plano, simbología y simbología del schedule:</strong> se ajustan al bloque sin deformarse. Puedes agregar más logos o textos desde «Insertar».</li>
<li><strong>Tareas (partidas):</strong> un bloque puede mostrar <strong>varias partidas</strong>, completas o solo algunas actividades, con su propio título y color. La numeración (SEQ) es <strong>continua 1..n</strong> o <strong>la de cada partida</strong>, con un subtítulo por partida. Puedes tener varios bloques de tareas en la misma lámina.</li>
<li><strong>Tablas adicionales:</strong> cada una es un bloque; en «Insertar» aparecen todas las tablas del proyecto de todos los tipos, con buscador y la opción de insertar una copia independiente. Su letra se ajusta sola al espacio del bloque: se reduce si falta lugar y <strong>crece</strong> si sobra, para que nunca quede un texto diminuto. Las tablas vinculadas a SheetFlow se <strong>actualizan en tiempo real</strong> cuando alguien cambia sus datos. En «Insertar» hay dos apartados: <strong>Tablas adicionales</strong> (las creadas en vPlan; llevan la etiqueta «SheetFlow» si están enlazadas a una hoja) y <strong>Tablas SheetFlow</strong> (las hojas de SheetFlow que todavía no están en el vPlan: al insertarlas se crea la tabla y quedan enlazadas).</li>
<li><strong>Insertar:</strong> el buscador de arriba filtra <em>todo</em> a la vez (bloques, símbolos, cajetines, estampas y tablas) y los chips (Bloques, Símbolos, Cajetines, Estampas, Tablas, Todo) muestran una categoría a la vez; lo último que insertaste queda en «Recientes». Un clic pone el bloque en el centro de la hoja y <strong>arrastrarlo hasta la hoja</strong> lo deja exactamente donde lo sueltes (se pega a las guías como los demás bloques).</li>
<li><strong>Colores:</strong> donde hay un selector de color aparece también el botón <strong>Paleta</strong> con los colores básicos, la maestra Pantone y tus colores guardados.</li>
<li><strong>Capas:</strong> lista de todos los objetos; arrástralos para cambiar el orden (arriba = al frente), ocúltalos con el ojo, bloquéalos con el candado para no moverlos por error y renómbralos con doble clic.</li>
<li><strong>Guías (como en Inkscape):</strong> arrastra desde las <strong>reglas</strong> (la de arriba da guías horizontales; la de la izquierda, verticales) y suelta dentro de la hoja. Los bloques se <strong>imantan</strong> a las guías, a los bordes y al centro de la hoja. Las guías también están en «Capas»: puedes ocultarlas, bloquearlas, escribir su posición en cm o quitarlas (o arrastrarlas fuera de la hoja). No se imprimen ni se exportan.</li>
<li><strong>Recovery:</strong> en «Hoja» agregas tantas columnas de recovery como necesites (Recovery, Recovery 02, 03…) y quitas la última; las fechas capturadas se conservan.</li>
<li><strong>Hoja:</strong> cambia el tamaño u orientación en cualquier momento con las tarjetas de tamaños (tabloide, carta, oficio, A4…A1, Arch D o una medida tuya); los bloques conservan su lugar en proporción. «Restablecer diseño estándar» vuelve a la disposición clásica.</li>
</ul>
<p class="manual-nota">Las restricciones se numeran en toda la lámina (en el orden en que se leen los bloques de tareas), así que un rombo con el mismo número es siempre la misma restricción.</p>
<h4>La lámina (vista de lectura)</h4>
<ul>
<li>El <strong>plano con sus marcas</strong> y las tablas del programa: SEQ, tarea, inicio, fin, recovery, % y restricciones, según el diseño de la lámina.</li>
<li>Las fechas de <strong>recovery</strong> se editan directamente en la tabla (con permiso de edición).</li>
<li>Puedes agregar <strong>tablas adicionales</strong> (punch list, restricciones, secundarias u otras) desde el catálogo de tablas; el catálogo se administra en <em>vPlan → Tablas</em>.</li>
<li><strong>Tabla en base a documento:</strong> en <em>vPlan › Tablas adicionales</em>, el botón «Tabla en base a documento» (a la izquierda de «+ Nueva tabla») lee un Excel, CSV, Word o PDF con texto y arma la tabla sola, detectando las columnas. Antes de crearla ves una vista previa que puedes corregir (quitar renglones o columnas, editar celdas). Las imágenes y los PDF escaneados no se pueden leer en la web sin OCR: el administrador los procesa con IA desde la app de escritorio (<em>Herramientas IA › Tabla desde documento</em>), que además puede corregir la lectura de cualquier archivo.</li>
<li><strong>Ampliar</strong> muestra la lámina a pantalla completa; Esc o el botón de cerrar la regresan.</li>
<li><strong>Zoom de la lámina:</strong> el control junto a «Ampliar» cambia el tamaño en pantalla. <strong>100 %</strong> es el tamaño real de la hoja (1 mm = 1 mm), que es exactamente como se ve en el PDF; <strong>Ajustar</strong> la mete en el ancho disponible y los botones <strong>−</strong> y <strong>+</strong> la acercan o la alejan (también con Ctrl + rueda del mouse sobre la lámina). La letra de las tablas nunca baja de lo que se puede leer: si la tabla tiene mucha información, la lámina se muestra más grande para que el texto se lea igual que en el PDF.</li>
<li>Si el avance del programa cambió desde que dibujaste las marcas, aparece un aviso y el botón <strong>Actualizar plano</strong>.</li>
</ul>
<h4>Editar las marcas</h4>
<p>El botón <strong>Dibujar sobre el plano</strong> (antes <em>Editar marcas</em>) abre el mismo <a href="#editor">editor de canvas</a> que usa la sección Editor: dibujas líneas, formas, texto, <strong>cajas de texto</strong> (el texto baja a la siguiente línea dentro del ancho de la caja, sin desbordarse), tablas, hipervínculos, imágenes, etc. sobre el plano. Pulsa <strong>Guardar</strong> (o Ctrl + S) para generar la imagen final de la lámina. El panel de la izquierda (<em>Propiedades</em>, <em>Alinear</em>, <em>Capas</em>, <em>Láminas</em>, <em>Materiales</em> y <em>Símbolos</em>) se puede ocultar con la flecha de su cabecera para dibujar con más espacio.</p>
<h4>Notas de la lámina</h4>
<p>En <strong>Acciones</strong> pulsa <strong>Crear nota</strong>: escribe una descripción y se guarda con la fecha del día. Puedes editar o borrar notas; la más reciente queda arriba.</p>
<p class="manual-nota">Las fechas visibles en la lámina se copian con un clic + <strong>Ctrl + C</strong> y se pegan en cualquier fecha editable con <strong>Ctrl + V</strong>, igual que en el programa.</p>
<p class="manual-nota">Para imprimir una lámina o llevarla a PDF usa <em>Exportar → vPlan</em>: una lámina por página, con el tamaño de hoja de cada lámina (si mezclas tamaños, el PDF las une en orden).</p>
HTML],

['id' => 'materiales', 'titulo' => 'Materiales en el plano', 'icono' => 'fa-cubes', 'roles' => $todos, 'herr' => 'vplan', 'cuerpo' => <<<'HTML'
<p>En el editor de marcas de vPlan, la pestaña <strong>Materiales</strong> sirve para marcar en el plano <em>dónde</em> se instala cada material y <em>cuándo</em>. Sustituye a la liga simple de una actividad por una relación múltiple.</p>
<h4>Dibujar y asignar</h4>
<ol>
<li>Abre la lámina y pulsa <strong>Dibujar sobre el plano</strong>. Elige la pestaña <strong>Materiales</strong>.</li>
<li>Dibuja una <strong>línea de materiales</strong> o una <strong>forma de materiales</strong>. La marca nace preparada para recibir actividades.</li>
<li>Con la marca seleccionada, en <strong>Propiedades → Materiales de esta marca</strong> pulsa <strong>Agregar actividad</strong>. También puedes hacerlo desde el panel <strong>Materiales → Asignar a la marca seleccionada</strong>, sin cambiar de pestaña. Busca por partida, código o nombre y marca una o varias actividades; si una actividad tiene subactividades, despliégalas y marca las que correspondan. Se asignan actividades: los materiales y sus fotos salen del catálogo de cada una.</li>
<li>Repite con todas las líneas y formas. Una misma actividad puede estar en varias marcas; la tabla la muestra una sola vez y cuenta sus ubicaciones.</li>
<li>Elige el <strong>color</strong> de cada línea o forma con el selector y la paleta. Las marcas de material conservan tu color; solo cambian al estado del programa si activas «Colorear la marca según el estado».</li>
</ol>
<h4>Fotos de los materiales</h4>
<p>Si las actividades tienen materiales con foto en el catálogo, sobre la marca aparece un <strong>botón redondo</strong> con la primera foto. Al pulsarlo se muestra la galería de fotos; en <strong>Propiedades</strong> puedes activar o desactivar la galería, cambiar su <strong>tamaño en px</strong> con el deslizador (se ve al instante) y elegir si aparece <strong>en el centro de la forma</strong> o <strong>repartida por su borde</strong>. En las líneas siempre se coloca arriba y centrada. El botón solo se ve mientras editas; las fotos sí salen en el PNG y en el PDF.</p>
<h4>Tabla de instalación</h4>
<ol>
<li>En el panel <strong>Materiales</strong> pulsa <strong>Crear tabla de materiales</strong>. Se crea en SheetFlow con una fila por actividad o subactividad y tres columnas: <strong>Material</strong>, <strong>Fecha de material</strong> (la fecha fin del programa) y <strong>Status</strong>.</li>
<li>La tabla se agrega como bloque a la lámina; en <strong>Modo diseño</strong> la mueves y redimensionas como cualquier otra.</li>
<li>Cuando agregues o quites marcas, pulsa <strong>Sincronizar</strong>: añade las filas nuevas y no toca las columnas extra. Si cambió el programa y quieres actualizar nombre, fecha y status, usa <strong>Traer fechas y estado del programa</strong>.</li>
<li>En <strong>SheetFlow</strong> puedes agregar todas las columnas que quieras (observaciones, fecha real, proveedor…). Aparecen en el panel del editor y en la tabla de la lámina.</li>
<li>En el panel, haz clic en una fila para seleccionar en el plano todas sus líneas y formas. Los renglones cuya actividad ya no está en el plano se marcan como «sin ubicación» para no perder lo capturado.</li>
</ol>
<p class="manual-nota">La tabla de materiales es una tabla de SheetFlow normal: también puedes abrirla, editarla y exportarla desde SheetFlow.</p>
HTML],

['id' => 'lastplanner', 'titulo' => 'Last Planner', 'icono' => 'fa-note-sticky', 'roles' => $todos, 'herr' => 'lastplanner', 'cuerpo' => <<<'HTML'
<p>Genera los <strong>post-its de planeación</strong> a partir del programa de obra: una tarjeta por actividad y día.</p>
<ul>
<li>Elige la <strong>partida</strong> (o <em>Mostrar todas</em>).</li>
<li>El rombo de cada tarjeta indica el estado: <strong>verde</strong> actividad finalizada, <strong>amarillo</strong> en proceso, <strong>rojo</strong> con restricción activa.</li>
<li>El triángulo <strong>naranja</strong> ▲ marca una actividad <em>en riesgo</em> (ver «Actividades en riesgo» en Programa de obra); pasa el cursor para ver el motivo. Solo se muestra en pantalla, no en el PDF.</li>
<li>Los post-its se calculan solos: si cambian las fechas del programa, se ajustan.</li>
<li>Con <strong>Filtro</strong> puedes ver solo los post-its con restricción, y con <strong>Período</strong> solo los de un rango de fechas o de un mes. Los mismos filtros se aplican al exportar.</li>
<li>En <strong>Plantilla del post-it</strong> puedes elegir el diseño clásico o una estampa propia. En <strong>Plantillas de obra</strong> hay diseños listos de 7×7 cm: <em>Cuadrilla</em>, <em>Restricciones</em>, <em>Calidad</em> y <em>Seguridad</em>. Al pulsar <strong>Cargar y usar</strong> se copia la plantilla al proyecto y queda seleccionada; después puedes ajustarla en el editor de estampas.</li>
</ul>
<p>Para imprimirlos usa <em>Exportar → Last Planner</em>: tarjetas de 7×7 cm en hojas carta con marcas de corte; puedes elegir la partida, el filtro, el período y cuántas tarjetas van por fila.</p>
HTML],

['id' => 'reportes', 'titulo' => 'Reporte diario', 'icono' => 'fa-clipboard-list', 'roles' => $todos, 'herr' => 'reportes', 'cuerpo' => <<<'HTML'
<p>El registro del día en obra: qué se trabajó, quién estuvo y fotos.</p>
<h4>Crear un reporte</h4>
<ol>
<li>Pulsa <strong>+ Nuevo reporte</strong>.</li>
<li>Elige la <strong>fecha*</strong>, escribe el área y el número de trabajo.</li>
<li>Marca las <strong>actividades del programa*</strong> trabajadas (una o varias).</li>
<li>Marca el <strong>personal</strong> que participó. Si falta alguien, agrégalo en <em>Personal</em>.</li>
<li>Escribe los <strong>comentarios</strong> y sube <strong>fotos</strong> (hasta 20 por guardado; jpg, png, webp o gif de hasta 15 MB cada una).</li>
<li>Pulsa <strong>Guardar</strong>.</li>
</ol>
<p>En el listado puedes <strong>Ver</strong> el reporte (se abre en otra pestaña), <strong>Editar</strong> o <strong>Eliminar</strong>. Al editar, las fotos pueden quitarse con <em>Quitar</em>.</p>
<p>Haz clic en cualquier foto del reporte para verla en grande en una ventana modal; ciérrala con el botón <strong>X</strong> o la tecla <strong>Esc</strong>.</p>
<h4>Personal</h4>
<p>Es el catálogo de personas del proyecto: <strong>nombre*</strong>, departamento y una imagen opcional. Se administra desde el botón <em>Personal</em> y alimenta los reportes diarios y las minutas.</p>
<p class="manual-nota">Las fotos de cada reporte aparecen solas en <a href="#evidencias">Evidencias</a>.</p>
<h4>Actualizar el avance desde el reporte</h4>
<p>Al <strong>Ver</strong> un reporte, junto a cada actividad aparece su <em>Avance real</em>. Corrige los porcentajes y pulsa <strong>Actualizar avance de las actividades</strong>: solo cambian las que modificaste y el valor queda anotado con la <em>fecha del reporte</em> (así la Curva S real refleja el día en que ocurrió, no el día en que lo capturaste).</p>
HTML],

['id' => 'bitacora', 'titulo' => 'Bitácora de obra', 'icono' => 'fa-book', 'roles' => $todos, 'herr' => 'bitacora', 'cuerpo' => <<<'HTML'
<p>El diario de eventos relevantes del proyecto, clasificado por <strong>importancia</strong> y <strong>departamento</strong>.</p>
<ol>
<li>Pulsa <strong>+ Nuevo registro</strong>.</li>
<li>Elige la <strong>fecha*</strong> y el <strong>departamento</strong>.</li>
<li>Define el <strong>nivel de importancia*</strong>: Baja, Media, Alta o Crítica.</li>
<li>Escribe la <strong>descripción*</strong> y guarda.</li>
</ol>
<ul>
<li>El listado se puede filtrar; <em>Limpiar</em> quita los filtros.</li>
<li>Los registros de importancia <strong>Alta y Crítica</strong> aparecen en las Acciones rápidas del menú lateral.</li>
<li><strong>Departamentos:</strong> en el botón <em>Departamentos</em> creas, renombras o eliminas los departamentos. Al renombrar uno, se actualizan también los registros que lo usan.</li>
</ul>
HTML],

['id' => 'minutas', 'titulo' => 'Minutas', 'icono' => 'fa-handshake', 'roles' => $todos, 'herr' => 'minutas', 'cuerpo' => <<<'HTML'
<p>Las minutas de reunión: quiénes asistieron, qué temas se vieron y qué compromisos quedaron. Se ordenan en <strong>carpetas</strong> (por departamento): renómbralas con el lápiz y mueve cada minuta con «Mover a…». Lo mismo aplica al Redactor y a SheetFlow.</p>
<ol>
<li>Pulsa <strong>+ Nueva minuta</strong>.</li>
<li>Escribe la <strong>fecha*</strong>, la <strong>importancia*</strong> y el <strong>nombre de la minuta*</strong>.</li>
<li>Marca a los asistentes: <strong>personal del contratista</strong> y <strong>personal del proyecto</strong>. Si falta alguien, agrégalo con los botones <em>Personal del contratista</em> / <em>Personal del proyecto</em> (el contratista lleva nombre, puesto y empresa).</li>
<li>Agrega los <strong>temas</strong> vistos con <strong>+ Agregar</strong>: cada tema lleva su responsable y departamento (los departamentos salen de la bitácora).</li>
<li>Agrega los <strong>compromisos</strong>.</li>
<li>Guarda.</li>
</ol>
<p>En el listado puedes <strong>Ver</strong> la minuta, descargarla en <strong>PDF</strong>, editarla o eliminarla. Las últimas minutas aparecen también en las Acciones rápidas.</p>
HTML],

['id' => 'evidencias', 'titulo' => 'Evidencias', 'icono' => 'fa-camera-retro', 'roles' => $todos, 'herr' => 'evidencias', 'cuerpo' => <<<'HTML'
<p>Una <strong>línea de tiempo con fotos</strong> del avance. Reúne automáticamente las fotos de los reportes diarios y las que subas directamente a una actividad.</p>
<h4>Subir una evidencia</h4>
<ol>
<li>En <strong>Nueva evidencia</strong> elige la <strong>actividad*</strong>.</li>
<li>Selecciona las <strong>fotos*</strong> (hasta 10, de 15 MB cada una).</li>
<li>Opcionalmente escribe una descripción (se aplica a todas las fotos) y pulsa <strong>Subir</strong>.</li>
</ol>
<ul>
<li>Las fotos se agrupan por día; el resumen indica cuántas fotos hay en cuántos días.</li>
<li>Puedes <strong>filtrar</strong> por partida o por actividad y moverte con <em>Más recientes</em> / <em>Más antiguas</em>.</li>
<li>Haz clic en una foto para verla en grande.</li>
</ul>
HTML],

['id' => 'archivos', 'titulo' => 'Archivo', 'icono' => 'fa-folder-open', 'roles' => $todos, 'herr' => 'archivos', 'cuerpo' => <<<'HTML'
<p>El repositorio de documentos del proyecto: planos, especificaciones, contratos, hojas de cálculo, etc. Arriba tiene dos pestañas: <strong>Archivo</strong> (la lista) y <strong>Visor</strong> (abre los archivos sin salir de la ventana).</p>
<h4>Subir un archivo</h4>
<ol>
<li>Pulsa <strong>+ Subir archivo</strong>.</li>
<li>Elige la <strong>fecha*</strong>, la <strong>categoría</strong>, el <strong>nombre del archivo*</strong> y, si aplica, el <strong>número de revisión</strong>.</li>
<li>Selecciona el archivo (máximo <strong>100 MB</strong>). Se aceptan PDF, Word, Excel, PowerPoint, XML, LibreOffice, imágenes, DWG/DXF, ZIP y similares.</li>
<li>Guarda.</li>
</ol>
<ul>
<li><strong>Descargar</strong> está disponible para administradores y clientes; los visitantes solo ven el listado.</li>
<li><strong>Categorías:</strong> el botón <em>Categorías</em> permite crear, renombrar y eliminar categorías. Una categoría en uso no se puede eliminar.</li>
</ul>
<p class="manual-nota">Si al subir un archivo grande el servidor lo rechaza, el administrador debe aumentar los límites de subida de PHP (<code>upload_max_filesize</code> y <code>post_max_size</code>).</p>
HTML],

['id' => 'visor', 'titulo' => 'Visor', 'icono' => 'fa-eye', 'roles' => $todos, 'herr' => 'visor', 'cuerpo' => <<<'HTML'
<p>El <strong>Visor</strong> es la segunda pestaña de <strong>Archivo</strong> (<em>Documentos y diseño › Archivo › Visor</em>). Muestra los archivos del <strong>Archivo</strong> del proyecto <strong>en la misma ventana</strong>: eliges un archivo de la lista de la izquierda y se abre en el panel derecho, sin abrir pestañas nuevas ni descargarlo.</p>
<ul>
<li><strong>Buscar y filtrar:</strong> por nombre, por categoría o por carpeta (como el Archivero).</li>
<li><strong>Navegar:</strong> usa los botones <i class="fa-solid fa-chevron-left"></i> / <i class="fa-solid fa-chevron-right"></i> o las flechas del teclado para pasar al archivo anterior o siguiente.</li>
<li>Se pueden ver <strong>PDF</strong>, <strong>imágenes</strong>, <strong>texto y CSV</strong> y, si el servidor tiene LibreOffice, <strong>Word, Excel y PowerPoint</strong> (se convierten a PDF).</li>
<li>Los archivos que no se pueden mostrar (por ejemplo un .zip o un plano .dwg) solo se pueden descargar.</li>
<li>Ver archivos requiere permiso de descarga: <strong>administrador</strong> o <strong>cliente</strong>. Los visitantes solo ven el listado.</li>
</ul>
HTML],

['id' => 'curvas', 'titulo' => 'Curvas de productividad', 'icono' => 'fa-chart-line', 'roles' => $todos, 'herr' => 'curvas', 'cuerpo' => <<<'HTML'
<p>Miden el <strong>avance real contra lo planeado</strong> de un trabajo concreto (por ejemplo, metros de cableado o de tubería).</p>
<h4>Crear una curva</h4>
<ol>
<li>Pulsa <strong>+ Nueva curva</strong>.</li>
<li>Escribe el <strong>nombre*</strong>, el <strong>material a medir</strong> (opcional, del catálogo), la <strong>cantidad*</strong> total y la unidad.</li>
<li>Define <strong>fecha de inicio*</strong> y <strong>fin*</strong>. Por defecto se usan las del programa de obra; las semanas se recalculan solas si cambian.</li>
<li>Elige el <strong>tipo de curva</strong> (cómo se reparte lo planeado en el tiempo). El planeado siempre suma la cantidad total.</li>
</ol>
<p>Al abrir una curva ves la tabla por semana con <em>Planned</em>, <em>Actual</em> y sus acumulados, junto a la gráfica. Si tienes permiso de edición, captura ahí el avance real de cada semana en la columna de <em>Actual</em>.</p>
<h4>Curva S del programa</h4>
<p>Compara el avance acumulado <strong>programado</strong> contra el <strong>real</strong>, semana por semana, usando la duración de cada actividad como su peso. Puedes capturar el real a mano y pulsar <strong>Guardar capturas a mano</strong>. Necesita actividades con duración en el programa.</p>
<h4>Gráfica maestra</h4>
<p>Junta todas las curvas en una sola gráfica de avance acumulado (%): línea continua = planeado, discontinua = real. Haz clic en la leyenda para ocultar o mostrar una curva.</p>
<p class="manual-nota">Para imprimir una curva usa <em>Exportar → Curvas de productividad</em> (PDF carta horizontal o .xlsx / .ods con tabla y gráfica).</p>
HTML],

['id' => 'msr', 'titulo' => 'MSR · Materiales', 'icono' => 'fa-truck-ramp-box', 'roles' => $todos, 'herr' => 'msr', 'cuerpo' => <<<'HTML'
<p>El <strong>MSR (Material Status Report)</strong> es el reporte que sigue cada material desde el envío del submittal hasta su entrega e instalación.</p>
<h4>Mis MSR</h4>
<p>El listado muestra tus reportes con su número de submittals y renglones. Crea uno con <strong>+ Nuevo MSR</strong> (nombre, descripción y las partidas que cubre).</p>
<h4>La tabla del MSR</h4>
<p>Cada renglón es un material. Las columnas incluyen: ítem, número de submittal del subcontratista, spec, descripción, fecha requerida de envío, número de submittal, fecha real y de respuesta, <strong>status</strong>, tiempo de entrega, fechas programadas de compra, instalación y entrega, fechas reales, datos del proveedor y observaciones.</p>
<ul>
<li>El <strong>status</strong> es un botón: cada clic lo cambia en el orden <strong>P → A → AN → RA</strong>, que equivalen a los estatus de submittal: <strong>P</strong> en revisión, <strong>A</strong> aprobado, <strong>AN</strong> aprobado A/AN y <strong>RA</strong> resubmitar.</li>
<li><strong>Crítico desde (días):</strong> escribe un número y pulsa <em>Aplicar</em>: los materiales cuyo <em>tiempo de entrega</em> es de ese número de días o más se resaltan como material crítico. También puedes filtrar la tabla por <em>Entrega crítica</em> y por atrasos.</li>
<li>Los renglones se generan solos desde los <a href="#submittals">Submittals</a> y se alimentan de las fechas del <a href="#materiales">Catálogo de materiales</a>.</li>
</ul>
<h4>Importar desde Excel</h4>
<ol>
<li>Pulsa <strong>Importar Excel</strong> y sube un archivo <strong>.xlsx</strong>.</li>
<li>Revisa la <em>vista previa</em> (sección, submittal, spec, descripción, compra, entrega, proveedor y foto) y los avisos.</li>
<li><strong>Confirmar importación</strong>.</li>
</ol>
<h4>Columnas a tu medida</h4>
<p>Al crear o editar un MSR eliges <strong>qué columnas</strong> lleva, su <strong>nombre</strong> y su <strong>orden</strong>, y puedes agregar columnas de texto propias. En la tabla arrastra un encabezado para moverlo; con doble clic cambias su título, color y grupo. Cada partida del MSR puede tener su propio color de título.</p>
<p class="manual-nota">Para imprimirlo usa <em>Exportar → MSR</em>: PDF en A4 horizontal con fotos, o .xlsx / .ods editables.</p>
HTML],

['id' => 'submittals', 'titulo' => 'Submittals', 'icono' => 'fa-boxes-packing', 'roles' => $gestion, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Un <strong>submittal</strong> es el paquete de materiales que se envía a aprobación. Aquí armas cada paquete tomando materiales del catálogo.</p>
<ol>
<li>Pulsa <strong>+ Nuevo paquete</strong>.</li>
<li>Escribe la <strong>fecha*</strong> y el <strong>número de submittal*</strong>; opcionalmente el número de trabajo, departamento, a quién va («Para»), especialidad y el MSR al que pertenece.</li>
<li>Marca los <strong>materiales del paquete</strong>. Cada material marcado genera su renglón en el MSR. Si no está, agrégalo antes al catálogo.</li>
<li>Opcionalmente elige <strong>láminas del Editor</strong> (las vistas de <a href="#editor">/graficos</a>) para adjuntarlas al paquete. Puedes filtrar la lista por categoría y seleccionar varias con Ctrl/Cmd + clic. Salen en el PDF debajo de la lista de materiales.</li>
<li>Guarda.</li>
</ol>
<h4>Estatus</h4>
<p>En el listado, el estatus es un grupo de botones: <strong>En revisión</strong>, <strong>Resubmitar</strong>, <strong>Aprobado</strong> y <strong>A/AN</strong>. Al cambiarlo, el sistema actualiza los renglones del MSR (status equivalente y fecha de respuesta al aprobar).</p>
<p>Con <strong>Ver</strong> abres el paquete (imagen, material y especialidad de cada uno) y con <strong>Exportar PDF</strong> lo descargas. Los submittals pendientes aparecen en las Acciones rápidas.</p>
<p>En la vista del paquete, <strong>Generar tabla de materiales</strong> crea en <a href="#sheetflow">SheetFlow</a> una tabla con la imagen, el nombre, la marca, el modelo o tipo, la referencia y la especialidad de cada material, dentro del departamento <em>Materiales</em> (si no existe, se crea solo). La tabla se puede editar como cualquier otra de SheetFlow. Si vuelves a pulsar el botón y la tabla ya existe, el sistema te pregunta si quieres <strong>reescribirla</strong> con los materiales actuales o <strong>ir a verla</strong>; al reescribir se pierden los cambios hechos en la tabla.</p>
HTML],

['id' => 'materiales', 'titulo' => 'Catálogo de materiales', 'icono' => 'fa-cubes', 'roles' => $gestion, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>La lista maestra de materiales del proyecto. Se usa en submittals, MSR, Planificador y curvas.</p>
<h4>Dar de alta un material</h4>
<ol>
<li>Pulsa <strong>+ Nuevo material</strong>.</li>
<li>Escribe el <strong>nombre base*</strong>, la marca, el modelo o tipo y un valor extra (dimensiones, capacidad…). El <strong>nombre completo</strong> se arma solo uniendo esas partes; las que dejes vacías se omiten.</li>
<li>Indica la <strong>referencia (Spec Number)</strong>, la <strong>especialidad</strong>, certificado de calidad, link web, cantidad y unidad.</li>
<li>Sube una <strong>imagen</strong> (se guarda en miniatura) y la <strong>ficha técnica</strong> en PDF.</li>
<li>En <strong>Logística y entrega</strong> captura: fecha de compra programada y real, fecha de entrega programada y real. Estas fechas se reflejan solas en el MSR.</li>
</ol>
<ul>
<li>En el listado, los botones de <strong>Compra</strong> y <strong>Entregado</strong> registran esos eventos con un clic.</li>
<li>Puedes importar materiales desde el MSR.</li>
</ul>
<h4>Especialidades</h4>
<p>Con el botón <strong>Especialidades</strong> administras las categorías técnicas del proyecto (eléctrica, hidráulica, etc.). Son propias de cada proyecto y no las ven otros. Muestra cuántos materiales y submittals usa cada una.</p>
HTML],

['id' => 'dependencias-materiales', 'titulo' => 'Dependencias de materiales', 'icono' => 'fa-diagram-project', 'roles' => $gestion, 'herr' => 'materiales', 'cuerpo' => <<<'HTML'
<p>Sirve para responder una pregunta de obra: <strong>¿este material puede usarse ya?</strong> Conectas un material con los que necesita y el sistema calcula cuándo está realmente disponible. Un material puede requerir <strong>varios</strong> a la vez: por ejemplo, una cámara bullet puede necesitar caja, base y cámara.</p>
<h4>Conectar materiales</h4>
<ol>
<li>Entra a <strong>Materiales → Dependencias de materiales</strong> (o a la pestaña «Dependencias» del Catálogo).</li>
<li>Pulsa <strong>Conectar materiales</strong> y haz clic primero en el material que debe estar disponible antes y luego en el que lo necesita. También puedes abrir un material y usar <strong>Agregar prerrequisito</strong>.</li>
<li>Marca todos los materiales que hagan falta y elige para cada uno si debe estar <strong>entregado en obra</strong> o <strong>instalado</strong>, con los días hábiles de retraso que quieras sumar.</li>
<li>Guarda. La cadena se dibuja en el tablero y el panel muestra «Requiere» y «Requerido por».</li>
</ol>
<h4>Qué calcula</h4>
<ul>
<li>La <strong>disponibilidad</strong> de un material es la más tardía entre su propia entrega y la de todos sus prerrequisitos. Si falta una fecha, el material queda <strong>bloqueado</strong> y no se inventa nada.</li>
<li>Una actividad puede empezar cuando <strong>todos</strong> sus materiales están disponibles. Si alguno no tiene fecha, la actividad se marca como bloqueada.</li>
<li>Los materiales de una actividad pueden ser <strong>Instala</strong> (la actividad los coloca) o <strong>Consume</strong> (solo los usa). Se elige en el formulario de la actividad.</li>
</ul>
<h4>Programar y sugerir</h4>
<ul>
<li><strong>Revisar y reprogramar</strong>: muestra qué actividades se moverían y por qué. Solo aplica en modo <strong>Automático</strong> y siempre se puede <strong>Deshacer</strong>.</li>
<li><strong>Sugerir actividades</strong>: propone relaciones entre actividades que ya existen y actividades nuevas para los materiales de las cadenas que todavía no tienen ninguna. Puedes editar nombre, partida, duración y fecha antes de aceptar. Si una actividad existente recibe un prerrequisito nuevo, se mueve para respetarlo. Nada se guarda sin tu confirmación.</li>
<li>El <a href="#programador-ia">Programador de actividades</a> recibe estas cadenas como referencia: usa la disponibilidad efectiva como piso de fecha y las reporta como obstáculos.</li>
</ul>
<p class="manual-nota">Los modos son: <strong>Apagado</strong> (se guardan y se ven las relaciones, pero no se mueve nada), <strong>Informativo</strong> (avisa de bloqueos y atrasos, sin mover fechas) y <strong>Automático</strong> (reprograma con vista previa y deshacer).</p>
HTML],

['id' => 'directorio', 'titulo' => 'Directorio', 'icono' => 'fa-address-book', 'roles' => $gestion, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Un solo lugar (en <strong>Administración</strong>) para capturar lo que varias herramientas comparten. Lo que captures aquí aparece en las listas de las demás.</p>
<ul>
<li><strong>Personal del proyecto</strong> y <strong>Personal del contratista</strong> (cliente o contratista): se eligen en Reporte diario y Minutas. Cada persona del proyecto tiene tipo (directivo, administrativo u operativo), cargo, empresa, teléfono, correo, dirección, RFC y notas generales; el RFC se valida (ej. XAXX010101000). Se editan con los mismos formularios de siempre y vuelves al Directorio al guardar.</li>
<li><strong>Departamentos</strong>: carpeta en Minutas, Redactor y SheetFlow; filtro en Bitácora, Submittals, Evidencias y Personal. Muestra dónde se usa cada uno. <em>Renombrar</em> un departamento lo cambia en todas las herramientas; si escribes el nombre de otro que ya existe, se unen. No se puede eliminar uno en uso.</li>
<li><strong>Categorías</strong>: las de <em>Archivo</em>, las del <em>Punch list</em> (incluye las de las plantillas de inspección) y las de <em>Evidencias</em>. Funcionan igual que los departamentos.</li>
<li><strong>Especialidades</strong>: las de Materiales y Submittals.</li>
</ul>
<p>En <strong>Evidencias</strong> puedes asignar departamento y categoría a cada foto subida y filtrar por ellos (las fotos de los reportes diarios no los llevan).</p>
HTML],

['id' => 'recursos', 'titulo' => 'Recursos y cuadrillas', 'icono' => 'fa-people-group', 'roles' => $todos, 'herr' => 'recursos', 'cuerpo' => <<<'HTML'
<p>Reparte <strong>personas y cuadrillas</strong> en las actividades y ve, semana por semana, cuánto trabajo cae sobre cada una. Si alguien queda por encima de su jornada, la herramienta avisa.</p>
<h4>Cómo se usa</h4>
<ol>
<li>En <strong>Personas y cuadrillas</strong> da de alta cada recurso: una <em>persona</em> (puedes tomarla del Directorio) o una <em>cuadrilla</em> con su número de integrantes y las horas por día.</li>
<li>En <strong>Asignaciones</strong> elige la actividad, el recurso y su <strong>dedicación (%)</strong>: qué parte de su jornada dedica a esa actividad mientras dure. Si lo asignas de nuevo, se actualiza el porcentaje.</li>
<li>En <strong>Carga semanal</strong> ves una tabla de calor: verde (hasta 85 %), amarillo (85–100 %) y rojo (más de 100 %, sobrecarga). Pasa el cursor sobre una celda para ver las actividades y las horas. Los botones de arriba mueven la ventana de 4 en 4 semanas.</li>
</ol>
<p>La carga de una semana es la suma de las dedicaciones de las actividades que caen en ella, ponderadas por sus <strong>días hábiles</strong> (con el calendario y los festivos del proyecto). Las actividades al 100 % de avance ya no cargan al recurso. Una sobrecarga en las próximas 4 semanas aparece en la <a href="#inicio">Salud del proyecto</a>.</p>
<p class="manual-nota">Solo el administrador y el cliente dueño del proyecto crean recursos y asignaciones; los visitantes solo consultan.</p>
HTML],

['id' => 'rfi', 'titulo' => 'RFI (solicitudes de información)', 'icono' => 'fa-circle-question', 'roles' => $todos, 'herr' => 'rfi', 'cuerpo' => <<<'HTML'
<p>Un <strong>RFI</strong> es una pregunta formal que la obra no puede resolver sola (un detalle de plano, una especificación, una decisión del cliente). Aquí llevas su registro y sabes qué trabajo está detenido por no tener respuesta.</p>
<ol>
<li>Pulsa <strong>+ Nuevo RFI</strong>. El número (RFI-001, RFI-002…) se sugiere solo.</li>
<li>Escribe el <strong>asunto*</strong>, la pregunta, el <strong>responsable</strong> de contestar, la <strong>fecha límite</strong> y la prioridad.</li>
<li>Elige la <strong>actividad que bloquea</strong>, si hay una. Mientras el RFI esté abierto, esa actividad aparece <em>en riesgo</em> en el Programa, el Lookahead y el Last Planner.</li>
<li>Cuando llegue la respuesta, cambia el estado a <strong>Respondido</strong> (se anota la fecha) y después a <strong>Cerrado</strong>.</li>
<li>Si el RFI necesita planos o esquemas, adjunta <strong>láminas del Editor</strong> (<a href="#editor">/graficos</a>): elige una o varias con Ctrl/Cmd + clic y filtra por categoría. El botón <strong>PDF</strong> de cada RFI (o la tarjeta <em>RFI</em> del <a href="#exportar">Centro de Exportación</a>) entrega el RFI con sus láminas debajo.</li>
</ol>
<p>Los RFI abiertos con la fecha límite pasada salen como <strong>vencidos</strong>, cuentan en la <a href="#inicio">Salud del proyecto</a> y su fecha límite viaja al calendario del celular (<a href="#compartir">Compartir</a>). El administrador marca cada RFI con el chip de Revisiones (✅ correcto, ⚠ por revisar, ❌ no cumple); si el RFI cambia después de revisarse, el chip avisa.</p>
HTML],

['id' => 'costos', 'titulo' => 'Costos y presupuesto', 'icono' => 'fa-sack-dollar', 'roles' => $gestion, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Compara <strong>lo presupuestado, lo avanzado y lo gastado</strong>. Solo lo ven el administrador y el cliente dueño del proyecto (los visitantes no).</p>
<h4>Qué capturas</h4>
<ul>
<li><strong>Presupuesto por partida:</strong> en la pestaña <em>Resumen</em>, un monto por partida y <em>Guardar presupuesto</em>.</li>
<li><strong>Costo real:</strong> cada gasto (factura, nómina, renta…) con fecha, monto y, si quieres, su partida y la orden de cambio que lo originó.</li>
<li><strong>Estimaciones:</strong> lo que cobras al cliente. Las de estado <em>borrador</em> no cuentan hasta aprobarse.</li>
</ul>
<h4>Qué calcula</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Sigla</th><th>Significa</th></tr></thead>
<tbody>
<tr><td><strong>Presupuesto vigente</strong></td><td>El presupuesto de la partida más el costo de las órdenes de cambio aprobadas, ejecutadas o cerradas de sus actividades.</td></tr>
<tr><td><strong>PV</strong></td><td>Valor programado: presupuesto × avance que el programa dice que debería llevarse hoy.</td></tr>
<tr><td><strong>EV</strong></td><td>Valor ganado: presupuesto × avance real de las actividades.</td></tr>
<tr><td><strong>AC</strong></td><td>Costo real capturado.</td></tr>
<tr><td><strong>CPI</strong></td><td>EV ÷ AC. Menor a 1 = se gasta más de lo que se avanza.</td></tr>
<tr><td><strong>SPI</strong></td><td>EV ÷ PV. Menor a 1 = atraso.</td></tr>
<tr><td><strong>EAC</strong></td><td>Costo final estimado = presupuesto ÷ CPI.</td></tr>
</tbody>
</table>
<p>El avance de cada partida se pesa por la duración de sus actividades, igual que la <a href="#curvas">Curva S</a>, y la <strong>curva de costos</strong> de la pestaña Resumen sale de esa misma Curva S (PV y EV por semana contra el costo real y lo estimado acumulados). Si el CPI baja de 1 se enciende un aviso en la <a href="#inicio">Salud del proyecto</a>.</p>
HTML],

['id' => 'compartir', 'titulo' => 'Compartir en solo lectura', 'icono' => 'fa-share-nodes', 'roles' => $gestion, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Dos formas de enseñar el proyecto a alguien <strong>sin crearle usuario</strong>. En ambas cualquiera que tenga el enlace lo ve, así que compártelo solo con quien debe verlo; puedes <strong>revocarlo</strong> cuando quieras.</p>
<h4>Enlace para el cliente</h4>
<ol>
<li>En <em>Proyecto → Compartir en solo lectura</em> escribe para quién es (una nota tuya), elige cuánto dura (7 a 90 días) y qué secciones ve: <strong>avance</strong> (con Curva S, avance por partida y las próximas tres semanas), <strong>fotos</strong> y <strong>reportes diarios</strong>.</li>
<li>Pulsa <em>Crear enlace</em> y <em>Copiar</em>. Mándalo por correo o mensaje.</li>
</ol>
<p>La página no muestra costos, nombres del personal ni documentos. La tabla indica cuántas veces se abrió y cuándo fue la última. Al vencer o revocarse, el enlace deja de funcionar.</p>
<h4>Calendario en el celular (.ics)</h4>
<p>Crea una <em>suscripción</em> y úsala en <em>Agregar calendario desde URL</em> (Google Calendar), <em>Añadir calendario con suscripción</em> (iPhone) u Outlook; en el celular también puedes pulsar <em>Suscribir</em>. Trae las actividades pendientes de la semana pasada a las próximas 4 semanas, <strong>todos los hitos</strong> y la fecha límite de los <a href="#rfi">RFI</a> abiertos, y se actualiza solo (cada pocas horas, según tu calendario). No caduca; se corta revocándola. También hay un atajo en el <a href="#calendario">Calendario</a>.</p>
HTML],

['id' => 'calendario', 'titulo' => 'Calendario', 'icono' => 'fa-calendar-days', 'roles' => $todos, 'herr' => 'calendario', 'cuerpo' => <<<'HTML'
<p>Vista mensual de las actividades del programa.</p>
<ul>
<li>Usa <strong>Anterior</strong>, <strong>Hoy</strong> y <strong>Siguiente</strong> para cambiar de mes.</li>
<li>Cada día muestra las actividades que caen en él, con su porcentaje y el color de su estatus.</li>
<li>Debajo, <em>Actividades del Mes</em> lista código, nombre, partida, progreso, estatus y cuántos días caen en el mes. Desde ahí puedes <strong>Editar</strong> una actividad sin salir del calendario.</li>
<li>El botón <strong>Crear Actividad</strong> abre el formulario en una ventana.</li>
</ul>
<p>El calendario respeta la <strong>jornada semanal y los festivos</strong> de los Ajustes del proyecto.</p>
HTML],

['id' => 'editor', 'titulo' => 'Editor (gráficos)', 'icono' => 'fa-pen-ruler', 'roles' => $todos, 'herr' => 'graficos', 'cuerpo' => <<<'HTML'
<p>Un lienzo libre para hacer <strong>diagramas, croquis, esquemas y láminas</strong> con las mismas herramientas de dibujo del vPlan. Cada gráfico puede tener varias <strong>láminas</strong>, como una presentación.</p>
<h4>El listado</h4>
<p>Tiene dos vistas: <strong>Galería</strong> (tarjetas con la portada y las acciones en el menú <em>⋮</em>: descargar, duplicar, guardar como simbología, eliminar) y <strong>Lista</strong> (tabla detallada). La vista se recuerda para la próxima vez.</p>
<h4>Categorías</h4>
<p>El botón <strong>Categorías</strong> abre un pequeño CRUD para clasificar tus gráficos: crear, renombrar y eliminar (no se puede eliminar una categoría que algún gráfico use; los nombres no se repiten). El selector <em>Categoría</em> aparece al crear un gráfico y en <em>Tamaño y fondo</em>.</p>
<h4>Crear un gráfico</h4>
<ol>
<li>Pulsa <strong>Nuevo gráfico</strong>. La fecha se pone sola.</li>
<li>Escribe el <strong>nombre*</strong> y elige la <strong>categoría</strong> (opcional).</li>
<li><strong>Tamaño:</strong> elige la tarjeta con la proporción del papel (pantalla, papel a 300 dpi, redes) u <em>Personalizado</em>, la orientación y el ancho × alto en píxeles.</li>
<li><strong>Fondo:</strong> una plantilla (liso, retícula, milimetrado, papel de ingeniería, texturas, fondos oscuros…) o <em>Imagen propia</em>, que puedes ajustar (modo, zoom, posición, repeticiones, opacidad).</li>
<li>Se abre el editor. Puedes cambiar tamaño y fondo después con <strong>Tamaño y fondo</strong>; el contenido se centra o se queda en la esquina, tú eliges.</li>
</ol>
<h4>La barra de herramientas</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Grupo</th><th>Herramientas</th></tr></thead>
<tbody>
<tr><td><strong>Selección</strong></td><td>Seleccionar y mover (Esc), y selección múltiple con recuadro. Con Mayús o Ctrl + clic sumas marcas.</td></tr>
<tr><td><strong>Líneas</strong></td><td>Continua, discontinua, trazo y punto, punteada, polilínea (clic por punto, doble clic o Enter para terminar), lápiz libre, flecha y flecha doble.</td></tr>
<tr><td><strong>Formas</strong></td><td>Rectángulo, redondeado, elipse, triángulo, rombo, <strong>polígono</strong> (clic en cada vértice; doble clic, Enter o clic en el primer punto para cerrar), pentágono, hexágono, estrella, cruz, equis, palomita, flecha de bloque, chevron, trapecio y zona translúcida, más 22 formas extra (octágono, corazón, rayo, gota, globo de texto, bandera, flechas de bloque en cuatro direcciones, estrellas, etc.). Las líneas recuerdan el último color, grosor y opacidad usados.</td></tr>
<tr><td><strong>Texto y marcadores</strong></td><td>Texto, <strong>caja de texto</strong> (haz clic y escribe: el texto baja a la siguiente línea dentro del ancho de la caja y no se desborda a la derecha; el ancho se cambia con las asas laterales o en Propiedades, donde también puedes convertir un texto normal en caja), tabla (arrastra para dibujarla; doble clic en una celda para escribir; Tab pasa a la siguiente; al escalarla con las asas la letra crece o se reduce junto con ella y las filas se reajustan), rombo de restricción numerado, círculo numerado, imagen, hipervínculo y vista 360.</td></tr>
<tr><td><strong>Medir</strong></td><td>Traza dos puntos; la primera vez indicas cuánto mide en la realidad y esa será la escala de las demás medidas. Eliges la unidad (mm, cm, m, pulgadas).</td></tr>
</tbody>
</table>
<h4>Panel derecho</h4>
<ul>
<li><strong>Propiedades:</strong> color de línea y de relleno, grosor, estilo de trazo, opacidad, tipo de letra, número, capa, comentario, etc. Cambian según la marca elegida.</li>
<li><strong>Capas:</strong> organiza el dibujo; puedes ocultar o bloquear capas.</li>
<li><strong>Láminas:</strong> agrega y navega entre láminas (Av Pág / Re Pág).</li>
<li><strong>Símbolos:</strong> inserta imágenes de la biblioteca de símbolos y cajetines (cuadros de datos).</li>
<li><strong>Alinear:</strong> con varias marcas elegidas aparecen los botones para alinearlas y distribuirlas; también puedes <strong>agrupar</strong> (Ctrl + G) y desagrupar.</li>
<li><strong>Comentarios:</strong> el campo Comentario de una marca crea una insignia amarilla en el editor; no sale en las imágenes exportadas.</li>
</ul>
<h4>Tablas vinculadas y símbolos que se actualizan solos</h4>
<ul>
<li>Las <strong>tablas de SheetFlow</strong> y del <strong>Punch list</strong> que insertes siguen conectadas a su origen: el gráfico las vuelve a leer al abrirse, al regresar a la ventana y cada 15 segundos, conservando posición, giro, opacidad y el tamaño que les diste.</li>
<li>Los <strong>símbolos y simbologías</strong> colocados se actualizan solos cuando editas su origen en la biblioteca.</li>
<li><strong>Líneas y polígonos:</strong> además de grosor y color, puedes ajustar el <strong>largo y la separación del trazo</strong> (discontinuas y punteadas) y <strong>editar los nodos</strong> de una línea, una polilínea o un polígono: moverlos, agregar uno nuevo sobre el segmento o quitarlo.</li>
</ul>
<h4>Hipervínculos</h4>
<ol>
<li>Elige la herramienta <strong>Hipervínculo</strong> y dibuja un área sobre el plano.</li>
<li>En Propiedades elige el tipo: <strong>enlace externo</strong> (Google Maps, una web, un correo) o <strong>sección del proyecto</strong> (Programa, una lámina de vPlan, un submittal, etc.).</li>
<li>Elige un <strong>icono</strong> (enlace, ubicación, documento, foto, video, bandera, información, estrella, número o sin icono), su color y tamaño (con deslizador o escribiendo el valor), y una etiqueta.</li>
<li>El área es invisible al exportar; el icono se ve en las páginas de lectura y <strong>no sale en el PDF</strong>. Si marcas <em>Incluir el icono al exportar</em>, sí sale en PNG, JPG y WebP. En el PDF solo viajan los enlaces externos (como área clicable).</li>
</ol>
<h4>Vista 360</h4>
<ol>
<li>Elige la herramienta <strong>Vista 360</strong> (junto al hipervínculo) y selecciona una <strong>foto 360</strong>: debe ser equirectangular (proporción 2:1), JPG, PNG o WebP, de al menos 1024 px de ancho y hasta 40 MB.</li>
<li>Dibuja el área sobre el plano. En Propiedades puedes ponerle etiqueta, icono y cambiar la foto con <em>Cambiar foto 360</em>.</li>
<li>La vista se abre desde la sección <a href="#view360">View360</a>.</li>
</ol>
<h4>Guardar y exportar</h4>
<ul>
<li><strong>Guardar:</strong> botón Guardar o Ctrl + S. También hay <em>autoguardado</em> (casilla en la barra). Si otra sesión modificó la lámina, el sistema te avisa antes de reemplazarla.</li>
<li><strong>Exportar:</strong> a PNG, JPG, WebP, PDF u ODP, con el tamaño y calidad que elijas (el contenido se ajusta sin deformarse) y fondo transparente opcional en PNG/WebP. Con varias láminas, puedes exportar todas.</li>
</ul>
<h4>Atajos de teclado</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Tecla</th><th>Acción</th></tr></thead>
<tbody>
<tr><td>Ctrl + Z / Ctrl + Y</td><td>Deshacer / rehacer</td></tr>
<tr><td>Ctrl + C / V / D</td><td>Copiar / pegar / duplicar</td></tr>
<tr><td>Ctrl + A</td><td>Seleccionar todo</td></tr>
<tr><td>Ctrl + G / Ctrl + Mayús + G</td><td>Agrupar / desagrupar</td></tr>
<tr><td>Ctrl + S</td><td>Guardar</td></tr>
<tr><td>Supr o Retroceso</td><td>Borrar lo seleccionado</td></tr>
<tr><td>Flechas (Mayús = 10 px)</td><td>Mover con precisión</td></tr>
<tr><td>F8</td><td>Activar o desactivar el modo ortogonal</td></tr>
<tr><td>Esc</td><td>Volver a Seleccionar</td></tr>
<tr><td>Rueda del mouse</td><td>Zoom sobre el lienzo</td></tr>
<tr><td>Ctrl + B / I / U</td><td>Negrita / cursiva / subrayado en texto</td></tr>
</tbody>
</table>
HTML],

['id' => 'redactor', 'titulo' => 'Redactor (documentos)', 'icono' => 'fa-file-pen', 'roles' => $todos, 'herr' => 'redactor', 'cuerpo' => <<<'HTML'
<p>Escribe documentos como en Word o LibreOffice sin salir de la plataforma: informes, memorias, actas, oficios.</p>
<p>Primera vez: el botón <strong>Guía rápida</strong> (lista y editor) recorre la herramienta paso a paso; se muestra solo la primera vez y se repite cuando quieras. Al pasar el cursor por los términos con línea punteada (departamento, plantilla…) aparece su definición del glosario.</p>
<h4>Crear un documento</h4>
<ol>
<li>Pulsa <strong>Nuevo documento</strong> y captura el <strong>nombre*</strong>, la <strong>fecha*</strong> y el <strong>departamento</strong> (elige uno de la lista o escribe uno nuevo). La lista se agrupa por departamento.</li>
<li>Elige el <strong>corrector</strong> (Español, English o ambos), la <strong>hoja</strong> (Carta, A4, Oficio, Tabloide) y su orientación.</li>
<li>Si quieres, elige una <strong>plantilla</strong>. Además de oficio, minuta, informe de avance, memoria, RFI, orden de cambio, acta, incidente y lista de verificación, hay documentos de obra: <strong>bitácora de obra</strong>, <strong>reporte semanal</strong>, <strong>estimación de obra</strong>, <strong>control de submittals</strong>, <strong>reporte de no conformidad</strong> y <strong>procedimiento constructivo</strong>.</li>
</ol>
<h4>Herramientas</h4>
<ul>
<li><strong>Texto:</strong> fuente, tamaño, negrita, cursiva, subrayado, tachado, sub/superíndice, color, resaltado, alineación, viñetas, numeración, sangría, interlineado y estilos (títulos, cita, código).</li>
<li><strong>Cortar, copiar y pegar</strong> (también pegado sin formato; lo pegado desde Word o LibreOffice se limpia).</li>
<li><strong>Tablas:</strong> inserta con filas y columnas; con el cursor dentro aparecen agregar/quitar fila o columna, color de celda y eliminar tabla. <kbd>Tab</kbd> pasa a la siguiente celda.</li>
<li><strong>Imágenes</strong> (desde tu equipo, pegadas o gráficos del Editor; clic en la imagen para cambiar su tamaño y alineación) y <strong>figuras</strong> (rectángulo, círculo, triángulo, rombo, estrella, flecha, línea).</li>
<li><strong>Cuadro de texto</strong> (pestaña <em>Insertar</em>): una caja con ancho propio, fondo y borde, a la izquierda, a la derecha o centrada. El texto baja solo a la siguiente línea dentro de su ancho (no se desborda) y la caja crece en alto conforme escribes. Sale igual en PDF, Word y LibreOffice.</li>
<li><strong>Hipervínculos:</strong> a una página web, a un correo o a <em>cualquier sección o documento de la plataforma</em> (programa, láminas, gráficos, otros documentos…). <kbd>Ctrl</kbd>+clic abre el enlace al editar.</li>
<li><strong>Página:</strong> márgenes en cm (normal, estrecho, amplio, encuadernado o personalizados), numeración de páginas («Página N de M»), encabezado con nombre · departamento · fecha y <strong>salto de página</strong>.</li>
<li><strong>Buscar y reemplazar</strong> (<kbd>Ctrl</kbd>+<kbd>F</kbd>).</li>
</ul>
<h4>Ortografía</h4>
<p>Las palabras mal escritas se subrayan en rojo. <strong>Clic derecho</strong> sobre una de ellas para ver sugerencias, <em>Ignorar</em> o <em>Agregar al diccionario</em> (la palabra queda para todos los documentos del proyecto). Los diccionarios de español (México) e inglés van incluidos y funcionan sin internet.</p>
<h4>Guardar y exportar</h4>
<p>Se guarda solo cada 30 segundos y con <kbd>Ctrl</kbd>+<kbd>S</kbd>. <strong>Exportar</strong> descarga Word (.docx), LibreOffice (.odt) o PDF con el nombre <code>Año_mes_día_Nombre</code>, y <strong>Imprimir</strong> abre el PDF listo para imprimir con la hoja, márgenes y numeración elegidos. La línea de corte de página en pantalla es aproximada; el PDF muestra la paginación exacta.</p>
HTML],

['id' => 'ensamblador-documentos', 'titulo' => 'Unir documentos (PDF)', 'icono' => 'fa-object-group', 'roles' => $todos, 'herr' => 'ensamble', 'cuerpo' => <<<'HTML'
<p>Une en <strong>un solo PDF no editable</strong> documentos del <strong>Redactor</strong> y PDF que subiste al <strong>Archivo</strong>: por ejemplo una memoria de cálculo más sus planos en PDF.</p>
<h4>Crear un ensamble</h4>
<p><em>Documentos y diseño › Unir documentos (PDF) › Nuevo ensamble</em>. Escribe el nombre, pulsa <strong>Agregar</strong> en cada documento y ordénalos con ▲ ▼. El PDF sale en ese orden.</p>
<h4>Que se actualice solo</h4>
<p>Con <strong>Guardar en Archivo</strong> el PDF queda en el Archivo del proyecto (puedes elegir carpeta). Si después cambias un documento original del Redactor o reemplazas un PDF fuente, ese archivo se <strong>regenera solo</strong> la próxima vez que se abra o descargue. Por ejemplo, si el título era verde y lo cambias a azul en el Redactor, el PDF del Archivo lo mostrará azul.</p>
<p class="manual-nota">Lo que descargas es un PDF común: ya no se actualiza ni conserva el vínculo. Si eliminas el ensamble, el PDF del Archivo se queda como está pero deja de actualizarse.</p>
HTML],

['id' => 'sheetflow', 'titulo' => 'SheetFlow (tablas)', 'icono' => 'fa-table-cells', 'roles' => $todos, 'herr' => 'sheetflow', 'cuerpo' => <<<'HTML'
<p>Crea <strong>tablas</strong> como en una hoja de cálculo (materiales, presupuestos, asistencias, registros) y úsalas en el <strong>vPlan</strong>, el <strong>Editor</strong> y el <strong>Redactor</strong> sin volver a capturarlas.</p>
<p>Primera vez: el botón <strong>Guía rápida</strong> (lista y editor) recorre la herramienta paso a paso, y los términos con línea punteada (SheetFlow, fórmulas, departamento…) muestran su definición al pasar el cursor.</p>
<h4>Crear una tabla</h4>
<ol>
<li>Pulsa <strong>Nueva tabla</strong> y captura el <strong>nombre*</strong>, la <strong>fecha*</strong> y el <strong>departamento</strong>; la lista se agrupa por departamento.</li>
<li>Elige el punto de partida: <strong>en blanco</strong>, una <strong>plantilla</strong> (control de materiales, presupuesto, restricciones, asistencia semanal, el programa de obra, estimación de obra, control de submittals, bitácora de obra, control de almacén, inspección de calidad o control de equipo) o <strong>importa</strong> un archivo <code>.xlsx</code>, <code>.ods</code> o <code>.csv</code> (primera hoja; la primera fila son los encabezados).</li>
</ol>
<h4>El editor</h4>
<ul>
<li><strong>Celdas:</strong> clic para elegir, arrastra o <kbd>Mayús</kbd>+flechas para varias; <kbd>Enter</kbd> o doble clic para editar; <kbd>Supr</kbd> borra. <kbd>Ctrl</kbd>+<kbd>C</kbd> / <kbd>V</kbd> copian y pegan, también desde Excel o LibreOffice. <kbd>Ctrl</kbd>+<kbd>Z</kbd> / <kbd>Y</kbd> deshacen y rehacen.</li>
<li><strong>Renglones y columnas:</strong> inserta arriba/abajo o a los lados, elimina, y ordena por la columna elegida. Hasta 30 columnas y 1 000 renglones.</li>
<li><strong>Tipo de columna:</strong> texto, número, moneda, porcentaje o fecha (da formato y alineación). Cada columna numérica puede tener un <strong>total</strong> al pie (suma, promedio, mínimo, máximo) y cualquier columna puede <strong>contar</strong>.</li>
<li><strong>Fórmulas:</strong> empieza con <code>=</code>. Operadores <code>+ − * / ^</code>, referencias como <code>C2</code> o <code>C2:C10</code> (el encabezado es la fila 1) y funciones <code>SUMA, PROMEDIO, MIN, MAX, CONTAR, REDONDEAR, SI</code>. Al insertar o eliminar renglones y columnas las referencias se ajustan solas.</li>
<li><strong>Formato de celdas</strong> (pestaña <em>Inicio</em>): tamaño de letra, negrita, cursiva, subrayado, color de letra y de relleno, alineación y <strong>bordes</strong> (todos, exterior, arriba, abajo, izquierda, derecha o ninguno, con color, grosor y estilo: continuo, guiones, puntos o doble). El ancho de cada columna se cambia arrastrando el borde de su letra.</li>
<li><strong>Imagen en una celda:</strong> pestaña <em>Insertar › Insertar en celda…</em> (logotipos, fotos). La imagen se ve también en el PDF, Excel y las tablas del Redactor.</li>
<li><strong>Funciones condicionales:</strong> <code>SI, Y, O, NO, SI.ERROR, SUMAR.SI, CONTAR.SI, PROMEDIO.SI</code>; en <em>Datos › Insertar función</em> hay un ejemplo de cada una. Por ejemplo <code>=SI(E2&gt;=D2;"Completo";"Pendiente")</code> o <code>=SUMAR.SI(B2:B9;"Estructura";E2:E9)</code>.</li>
<li><strong>Estilo de la tabla</strong> (pestaña <em>Vista</em>): color del encabezado y de su texto, renglones alternos, líneas de cuadrícula y zoom.</li>
<li>Se guarda solo unos segundos después de cada cambio y con <kbd>Ctrl</kbd>+<kbd>S</kbd>. <strong>Exportar</strong> descarga Excel (.xlsx), CSV o PDF, o abre el PDF para imprimir. También puedes descargar el archivo propio <strong>.sheetflow</strong> (con su icono rosa en la sección Archivo) y volver a importarlo con <em>Nueva tabla</em>.</li>
</ul>
<h4>Tabla inteligente y tipos de captura</h4>
<ul>
<li>En <em>Nueva tabla › Tabla inteligente</em> un constructor guiado te deja nombrar la tabla, elegir el <strong>tipo y el color de encabezado de cada columna</strong>, reordenarlas y definir cuántos renglones vacíos trae.</li>
<li>Además de texto, número, moneda, porcentaje y fecha, una columna puede ser <strong>lista</strong> (menú desplegable con hasta 20 opciones que defines en <em>Datos › Columna</em>), <strong>casilla</strong> (✔ o vacío) o <strong>semáforo</strong> (Verde, Amarillo, Rojo con su color).</li>
<li>Esos controles funcionan también cuando la tabla se edita desde una lámina del vPlan: la lista solo acepta sus opciones, la casilla se marca con un clic y el semáforo se pinta del color elegido.</li>
</ul>
<h4>Que se adapte a cualquier tamaño</h4>
<ul>
<li><strong>vPlan:</strong> la letra de la tabla se calcula para llenar el bloque. Si el bloque es pequeño, la letra se reduce hasta que todo quepa; si lo agrandas, <strong>crece</strong> (hasta 48 pt) en lugar de dejar un texto diminuto en un cuadro enorme. Lo mismo ocurre en el PDF de la lámina.</li>
<li><strong>Editor:</strong> al escalar una tabla con las asas de la esquina, la <strong>letra se escala con ella</strong> y los renglones se reajustan para que el texto siempre quepa. Si solo estiras un lado, la letra cambia a la mitad del camino.</li>
<li><strong>Vista de la tabla:</strong> el tamaño de la letra sigue el ancho disponible de la pantalla, así que se lee bien en un monitor grande y no se desborda en el celular.</li>
</ul>
<h4>Siempre al día: cambios en tiempo real</h4>
<ul>
<li>Cuando alguien modifica una tabla vinculada (en SheetFlow o desde una lámina del vPlan), las demás vistas <strong>se actualizan solas</strong> sin que tengas que recargar: la lámina del vPlan y la vista de la tabla consultan los cambios cada pocos segundos y se refrescan (conservan tu posición en la página). Si estás escribiendo en un campo o tienes un diálogo abierto, esperan a que termines para no interrumpirte.</li>
<li>El <strong>Editor</strong> vuelve a leer las tablas vinculadas al regresar a su ventana y cada 15 segundos; respeta el tamaño y la letra que les diste, y deja el gráfico marcado como «sin guardar» para que conserves el cambio con <kbd>Ctrl</kbd>+<kbd>S</kbd>.</li>
<li>El <strong>Redactor</strong> las actualiza al abrir, ver y exportar el documento (no se recarga solo para no hacerte perder lo que estás escribiendo).</li>
</ul>
<h4>Usar la tabla en otras herramientas (vínculos vivos)</h4>
<ul>
<li><strong>vPlan:</strong> en SheetFlow (<em>A vPlan</em>) o en <em>vPlan › Tablas adicionales › Crear desde SheetFlow</em> se crea una tabla vinculada (hasta 8 columnas y 200 renglones) que luego agregas a las láminas. Lo que cambies en SheetFlow se ve en la lámina, y lo que edites en la lámina se escribe en SheetFlow (no se pueden editar celdas con fórmula ni el renglón de totales desde la lámina).</li>
<li><strong>Redactor:</strong> <em>Insertar › Tabla › Origen › Tablas de SheetFlow</em>. La tabla se actualiza sola al abrir, ver y exportar el documento; para cambiarla, edítala en SheetFlow.</li>
<li><strong>Editor:</strong> panel <em>Símbolos › Tablas de SheetFlow</em>. Se actualiza al abrir el gráfico (guarda para conservar el cambio) y la puedes mover y cambiar de tamaño.</li>
</ul>
<p class="manual-nota">Si borras la tabla de SheetFlow, la de vPlan se queda con sus últimos datos y las del Redactor y el Editor con lo último que se guardó.</p>
HTML],

['id' => 'simbolos', 'titulo' => 'Símbolos', 'icono' => 'fa-shapes', 'roles' => $todos, 'herr' => 'graficos', 'cuerpo' => <<<'HTML'
<p>Está en la segunda pestaña del <strong>Editor</strong> (<em>Documentos y diseño › Editor › Símbolos, estampas y cajetines</em>). La <strong>biblioteca de símbolos</strong> del proyecto: imágenes reutilizables que insertas en el vPlan y en el Editor (cámaras, extintores, tableros, señales…).</p>
<ul>
<li><strong>Nuevo símbolo:</strong> escribe el <em>nombre*</em>, una categoría y sube la imagen (PNG con fondo transparente o SVG funcionan mejor). Pulsa <strong>Agregar símbolo</strong>.</li>
<li><strong>Importar símbolos:</strong> sube un <strong>.zip</strong> de biblioteca o varias imágenes de una vez. <strong>Exportar biblioteca</strong> descarga la tuya para reusarla en otro proyecto.</li>
<li>Puedes <strong>cambiar la imagen</strong> o eliminar un símbolo propio. Los <em>incluidos con la aplicación</em> sirven para todos los proyectos y no se editan.</li>
<li>El botón <strong>Guía rápida</strong> de la cabecera recorre la biblioteca paso a paso (pestañas, importación, estampas); el número azul de cada tarjeta dice en cuántas láminas se usa el símbolo.</li>
</ul>
<h4>Cajetines</h4>
<p>Un <strong>cajetín</strong> es el cuadro de datos de un plano (proyecto, título, fecha, revisión…) que insertas de un clic. Cada <strong>renglón</strong> tiene un <em>título</em> (a la izquierda) y un <em>dato</em> (a la derecha). El dato puede ser un texto fijo o un <strong>campo</strong> que se llena solo: elígelo con el botón <em>＋ Campo…</em> (nombre del proyecto, fecha, avance, SPI, restricciones activas…). Puedes añadir, quitar y mover renglones, empezar con una <em>plantilla</em> y ver abajo la <strong>vista previa con datos reales</strong>.</p>
<h4>Estampas inteligentes</h4>
<p>Una <strong>estampa</strong> es un bloque que colocas en una lámina del vPlan y que <strong>se actualiza solo</strong>. Está hecha de <em>etiquetas</em>, y cada etiqueta junta un texto antes (<em>prefijo</em>), un <em>dato</em> del proyecto (la fecha, el avance, una actividad, las restricciones…) y un texto después (<em>sufijo</em>). Por ejemplo: «Avance: » + <em>avance de la actividad</em> + « %».</p>
<ul>
<li><strong>Nueva estampa</strong> o <strong>empieza con un ejemplo</strong>: cajetín de plano, sello de estatus de actividad, etiqueta de restricción, pie de reporte con corte y SPI, semáforo de avance y <strong>Last Planner · Post-its del día</strong>.</li>
<li><strong>Tipos de etiqueta:</strong> <em>Campo inteligente</em>, <em>Texto libre</em> (admite campos como <code>{proyecto}</code> o <code>{corte:largo}</code>), <em>Semáforo</em> (verde, amarillo o rojo según dos umbrales que tú eliges) y <em>Actividades del día</em>.</li>
<li><strong>Actividades del día (post-its):</strong> una tarjeta por cada actividad que cae en la fecha de hoy (o en una fecha fija). Eliges las partidas o el responsable, cuántas tarjetas, cuántas columnas, el color y qué datos muestra cada una. Cambia sola cada día; en fin de semana o festivo muestra el siguiente día hábil.</li>
<li>La <strong>vista previa</strong> a la derecha usa los datos reales del proyecto y te deja probar con otra actividad o fecha de corte.</li>
<li><strong>En el vPlan:</strong> abre una lámina → <em>Modo diseño</em> → pestaña <em>Insertar</em> → <em>Estampas inteligentes</em>. En <em>Propiedades</em> eliges la estampa, la fecha y la actividad de las etiquetas «de la actividad». También sale en el PDF de la lámina.</li>
</ul>
HTML],

['id' => 'view360', 'titulo' => 'View360', 'icono' => 'fa-street-view', 'roles' => $todos, 'herr' => 'view360', 'cuerpo' => <<<'HTML'
<p>Crea <strong>gráficos interactivos</strong>: un plano con zonas que abren <strong>vistas 360</strong> del proyecto.</p>
<h4>Crear un gráfico interactivo</h4>
<ol>
<li>Pulsa <strong>Crear gráfico interactivo</strong>.</li>
<li>Escribe la <strong>fecha*</strong>, el <strong>nombre del gráfico*</strong> y la <strong>versión</strong> (por ejemplo v1.0).</li>
<li>Elige el <strong>fondo*</strong>: un <strong>PDF</strong> (se usa la primera página) o una <strong>imagen</strong>, de hasta 30 MB. Se carga como fondo del lienzo.</li>
<li>Se abre el <a href="#editor">editor</a> con el fondo puesto. Ahí dibujas lo que necesites y, con la herramienta <strong>Vista 360</strong>, marcas las zonas que abrirán una foto 360. Guarda al terminar.</li>
</ol>
<h4>El listado</h4>
<p>Cada fila tiene fecha, nombre y versión, y los botones <strong>Ver</strong> (abre el gráfico sin herramientas de edición), <strong>Editor</strong> (reabre el lienzo), <strong>Editar</strong> (cambia fecha, nombre y versión) y <strong>Eliminar</strong> (borra también el lienzo y sus archivos).</p>
<h4>La vista de lectura</h4>
<ul>
<li>El plano se muestra a todo el ancho con sus <strong>hipervínculos activos</strong>: las zonas se iluminan al pasar el cursor, con su icono y etiqueta.</li>
<li>Al hacer clic en una <strong>vista 360</strong>, aparece una tarjeta en el <strong>panel derecho</strong> con un visor vivo: <strong>arrastra</strong> para mirar alrededor (en celular, con dos dedos). Para acercar usa los botones + / − o <strong>Ctrl + rueda</strong>.</li>
<li>El botón <strong>Ver</strong> de la tarjeta abre el visor 360 a <strong>pantalla completa</strong> (se cierra con Esc o con el botón Cerrar). <strong>Cerrar</strong> quita la tarjeta del panel.</li>
<li>Puedes tener varias tarjetas abiertas a la vez; si abres la misma otra vez, te lleva a su tarjeta.</li>
</ul>
<h4>Acciones → Crear nota</h4>
<p>Debajo del gráfico, la sección <strong>Acciones</strong> permite <strong>Crear nota</strong>: escribe una descripción y se guarda con la fecha del día. Puedes editarla o borrarla (con confirmación); la más reciente queda arriba. Los visitantes pueden leer las notas, no escribirlas.</p>
<p class="manual-nota">El visor 360 necesita un navegador con WebGL (cualquier navegador moderno). Para convertir un PDF a imagen, el servidor debe tener instalado <code>pdftoppm</code> o Ghostscript.</p>
HTML],

['id' => 'comparador', 'titulo' => 'Comparador de documentos', 'icono' => 'fa-code-compare', 'roles' => $todos, 'herr' => 'comparador', 'cuerpo' => <<<'HTML'
<p>Pone <strong>dos imágenes o PDF</strong> uno sobre otro para ver qué cambió entre dos revisiones de un plano o documento.</p>
<h4>Elegir los documentos</h4>
<ul>
<li><strong>Documento A</strong> es la base (el fondo) y <strong>Documento B</strong> es el que se superpone y se mueve.</li>
<li>Cada uno puede ser un archivo <strong>del proyecto</strong> (sección <a href="#archivos">Archivo</a>) o uno <strong>externo</strong> de tu equipo; los externos se quedan en tu navegador y no se suben.</li>
<li>En un PDF elige la <strong>página</strong> que quieres comparar.</li>
</ul>
<h4>Modos de comparación</h4>
<ul>
<li><strong>Deslizador:</strong> A a un lado y B al otro de una línea que arrastras (vertical u horizontal).</li>
<li><strong>Fundido:</strong> mezcla A y B con una barra de porcentaje.</li>
<li><strong>Diferencias:</strong> lo igual se ve negro y lo distinto se ilumina.</li>
</ul>
<h4>Emparejar B con A</h4>
<p>Con <strong>Mover B</strong> arrastras el documento B (flechas del teclado para afinar), <strong>Ajustar B a A</strong> lo escala al tamaño de A, <strong>Restablecer B</strong> quita traslado, escala y giro, y el botón de flechas intercambia A y B. Rueda del ratón = zoom; arrastrar = desplazar.</p>
<h4>Descargar PDF</h4>
<p>Al final puedes exportar la comparación a PDF eligiendo tamaño de hoja, orientación, qué incluir (A y B como se ven, solo A o solo B) y el área (vista actual o documento A completo).</p>
HTML],

['id' => 'traductor', 'titulo' => 'Traductor', 'icono' => 'fa-language', 'roles' => $todos, 'herr' => 'traductor', 'cuerpo' => <<<'HTML'
<p>Traduce de <strong>español a inglés</strong> o de <strong>inglés a español</strong> un texto que escribas o un documento completo.</p>
<h4>Texto</h4>
<ol>
<li>Elige el idioma de origen y el de destino (el botón de flechas los intercambia; si ya hay traducción, la pasa al cuadro de origen).</li>
<li>Escribe o pega el texto (hasta 20,000 caracteres) y pulsa <strong>Traducir</strong>.</li>
<li>Con <strong>Copiar</strong> llevas el resultado al portapapeles. Se conservan los saltos de línea y las listas.</li>
<li>Con <strong>Exportar</strong> descargas ese mismo texto como documento: Word (.docx), PDF o texto (.txt), según el formato elegido al lado del botón. El archivo se llama <code>traduccion-EN-fecha</code> o <code>traduccion-ES-fecha</code>.</li>
</ol>
<h4>Documento</h4>
<p>En la pestaña <strong>Documento</strong> sube un archivo de hasta 20 MB y pulsa <strong>Traducir y descargar</strong>. El resultado se descarga con el mismo nombre más <code>_EN</code> o <code>_ES</code>; no se guarda en el servidor.</p>
<ul>
<li><strong>Word</strong> (.docx, .doc), <strong>Writer</strong> (.odt) y RTF: se traduce cada párrafo, también en tablas, encabezados y pies. Se conserva el estilo del párrafo; el formato interno (una palabra en negritas) puede perderse.</li>
<li><strong>Excel</strong> (.xlsx, .xls), <strong>Calc</strong> (.ods) y CSV: se traducen las celdas de texto; fórmulas, números y fechas no cambian.</li>
<li><strong>PowerPoint</strong> (.pptx, .ppt) e <strong>Impress</strong> (.odp): se traduce el texto de cada diapositiva y sus notas.</li>
<li><strong>PDF</strong>: se entrega un PDF nuevo con el texto traducido (sin el diseño original). Un PDF escaneado, sin texto seleccionable, no se puede traducir.</li>
<li><strong>TXT</strong> y Markdown: se traducen línea por línea.</li>
</ul>
<p class="manual-nota">La traducción es automática: revísala antes de usarla en contratos, especificaciones o documentos oficiales. Los formatos .doc, .xls, .ppt y .rtf se entregan convertidos a .docx, .xlsx y .pptx. Traducir documentos requiere permiso de descarga.</p>
HTML],

['id' => 'ajustes', 'titulo' => 'Ajustes del proyecto', 'icono' => 'fa-gear', 'roles' => $gestion, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Configuración propia de cada proyecto. Se abre desde <em>Ajustes</em> en el menú del proyecto.</p>
<h4>Jornada semanal</h4>
<p>Define cuánto se trabaja cada día de la semana con un factor de <strong>0 a 1</strong> (1 = día completo, 0.5 = medio día, 0 = no laborable). El valor inicial es lunes a viernes = 1, sábado = 0.5 y domingo = 0. Con esto el sistema calcula las fechas de fin de las actividades. Pulsa <strong>Guardar Jornada</strong>. Solo el administrador puede modificarla.</p>
<h4>Festivos y paros</h4>
<p>Registra <strong>festivos</strong>, <strong>paros técnicos</strong> y <strong>suspensiones por clima</strong>: agrega la fecha y el tipo. Una actividad que cruza una de esas fechas la salta al calcular su fin.</p>
<h4>Almacenamiento</h4>
<p>Muestra cuánto del espacio del proyecto está usado y en qué (archivos, fotos, planos, fichas, logos). No cuenta lo que se genera al exportar.</p>
<h4>Idioma</h4>
<p>Cambia la interfaz entre Español e English.</p>
HTML],

['id' => 'exportar', 'titulo' => 'Exportar', 'icono' => 'fa-file-export', 'roles' => $gestion, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>El <strong>Centro de Exportación</strong> genera los documentos del proyecto. Los archivos se nombran solos como <code>AAAA_MM_DD_NombreUsuario.ext</code>.</p>
<table class="tabla manual-tabla">
<thead><tr><th>Documento</th><th>Qué genera</th></tr></thead>
<tbody>
<tr><td><strong>Programa de obra</strong></td><td>Eliges el formato: <em>PDF ejecutivo</em> (Gantt + tabla en tabloide 11×17" horizontal, paginado según la duración), <em>Microsoft Project (.xml)</em> en formato MSPDI (ábrelo en Project y guárdalo como .mpp; no es un .mpp binario) o <em>ProjectLibre (.pod)</em>.</td></tr>
<tr><td><strong>4WLA</strong></td><td>Lookahead de 4 semanas: semana actual + 4 con X por día, PTA y limitaciones. Tabloide horizontal; en .xlsx / .ods queda editable. Eliges el formato y la <em>semana de referencia</em>.</td></tr>
<tr><td><strong>vPlan</strong></td><td>Plano con la tabla del programa, recovery y tablas adicionales; una lámina por página, al tamaño de hoja de cada lámina. Eliges la lámina.</td></tr>
<tr><td><strong>Last Planner</strong></td><td>Post-its de 7×7 cm por día y partida, en hojas carta con marcas de corte. Eliges la partida y las tarjetas por fila.</td></tr>
<tr><td><strong>Curvas de productividad</strong></td><td>Una curva con su tabla y gráfica, o la gráfica maestra con todas. PDF carta horizontal; .xlsx / .ods con tabla y gráfica.</td></tr>
<tr><td><strong>MSR</strong></td><td>Status de submittals y entrega de materiales con fotos. PDF en A4 horizontal; .xlsx / .ods editables.</td></tr>
<tr><td><strong>Línea de tiempo activa</strong></td><td>El Gantt diario agrupado por partida con casillas de <em>estado</em> (no iniciadas, en proceso, completadas —cualquier combinación—), «solo con restricción», icono de actividad y partida; y casillas de <em>meses</em>: sin marcar ninguno sale el rango completo del proyecto y con varios marcados un archivo por mes en un ZIP. PDF en una sola hoja del tamaño que haga falta, o .ods editable.</td></tr>
<tr><td><strong>Reportes de obra (PDF)</strong></td><td>Reporte diario (un día o un mes), Bitácora (por día, mes, departamento o importancia), Minutas (por importancia, día o mes), Submittals (uno, varios o todos), RFI (uno o varios, con sus láminas del Editor), Materiales (por especialidad, estatus de compra o entrega) y Evidencias (por día, semana, mes o actividad). Carta vertical; los filtros se combinan y los vacíos no filtran.</td></tr>
</tbody>
</table>
<p class="manual-nota">Solo administradores y clientes pueden exportar; los visitantes no.</p>
HTML],

['id' => 'acciones', 'titulo' => 'Acciones rápidas', 'icono' => 'fa-bolt', 'roles' => $todos, 'herr' => 'acciones', 'cuerpo' => <<<'HTML'
<p>En la parte baja del menú lateral, cuando estás dentro de un proyecto, las <strong>Acciones rápidas</strong> te muestran de un vistazo lo que requiere atención. Cada sección se despliega y muestra un contador con la cantidad pendiente; al pulsar un elemento vas directo a él.</p>
<ul>
<li><strong>Actividades de hoy</strong> y <strong>de la semana</strong>, con su avance.</li>
<li><strong>Restricciones activas</strong>.</li>
<li><strong>Bitácora · alta y crítica</strong>: los registros importantes.</li>
<li><strong>Últimas minutas</strong>.</li>
<li><strong>Submittals por aprobar</strong>.</li>
<li><strong>Materiales pendientes</strong>, <strong>actividades sin materiales</strong> y <strong>materiales sin asignar</strong> (los tres solo para quien gestiona el proyecto).</li>
</ul>
<p>El enlace <strong>Ver todas en otra pestaña</strong> abre la lista completa con todos los pendientes, y en cada sección <em>Ver todas (n) →</em> abre esa sección completa. Una sección sin pendientes indica «Nada pendiente».</p>
HTML],

['id' => 'historial', 'titulo' => 'Historial de cambios', 'icono' => 'fa-clock-rotate-left', 'roles' => $todos, 'herr' => 'historial', 'cuerpo' => <<<'HTML'
<p>En <strong>Historial de cambios</strong> (sección <em>Administración</em> de la barra del proyecto) queda registrado todo lo que se modifica en el proyecto: quién lo hizo, cuándo, qué cambió exactamente y en qué herramienta. El registro <strong>no se puede editar ni borrar</strong>.</p>
<p class="manual-nota"><strong>Solo cambios hechos a mano.</strong> Aquí aparece lo que el usuario cambia con las herramientas. Lo que calcula el sistema por sí solo —las fechas que se recalculan en las partidas, las actualizaciones automáticas al abrir una lámina o un documento, las tareas programadas y el bot de Telegram— no se registra ni ensucia la lista.</p>
<h4>Las tres pestañas</h4>
<ul>
<li><strong>Cambios pendientes</strong> (la que se abre): todo lo nuevo, esperando revisión.</li>
<li><strong>Cambios aceptados</strong>: lo que el administrador o el cliente ya validaron, con quién y cuándo lo aceptaron. Aquí también quedan los cambios que hace la IA, porque la IA nunca toca los datos sin que alguien la acepte.</li>
<li><strong>Cambios revertidos</strong>: los que se cancelaron, junto con el registro de lo que se revirtió.</li>
</ul>
<h4>Cada renglón muestra</h4>
<ul>
<li><strong>Fecha</strong> en que se hizo el cambio.</li>
<li><strong>Descripción del cambio</strong>: qué se creó, qué se modificó (con su valor anterior → nuevo) o qué se eliminó, quién lo hizo y a qué elemento afectó. Debajo, <em>Antes / después</em> despliega los valores completos.</li>
<li><strong>Herramienta</strong> donde ocurrió (Programa, Materiales, Archivo…) y el tipo de evento.</li>
<li><strong>Acciones</strong>: <em>Ver detalle</em> abre la herramienta justo en ese elemento; <em>Aceptar</em> lo pasa a «Cambios aceptados»; <em>Cancelar</em> devuelve los datos a como estaban antes de ese cambio.</li>
</ul>
<p class="manual-nota">Aceptar o cancelar solo lo hacen el administrador y el cliente dueño del proyecto; el visitante solo consulta.</p>
<h4>Filtros y descarga</h4>
<ul>
<li>Filtra por herramienta, evento, quién lo hizo, rango de fechas y texto libre.</li>
<li><strong>Aceptar todo</strong> (en la pestaña de pendientes) mueve de una vez todo lo que coincide con el filtro.</li>
<li><strong>Excel</strong> y <strong>PDF</strong> descargan la lista con esos mismos filtros, para administradores y clientes.</li>
</ul>
<h4>Cuándo no se puede cancelar</h4>
<p>Si el registro no conserva los valores antiguos, el botón lo explica y el cambio queda únicamente como registro. Al cancelar un borrado se recupera la fila, pero lo que colgaba de ella con borrado en cascada (fotos, relaciones, partidas hijas) no vuelve.</p>
<h4>Visibilidad para los visitantes</h4>
<p>El dueño del proyecto decide si un visitante ve esta herramienta: en <em>Mis visitantes</em>, marca <em>Historial de cambios</em> dentro de «Herramientas que puede ver». Si no está marcada, la entrada desaparece de su menú y tampoco puede entrar por enlace directo.</p>
HTML],

['id' => 'integraciones', 'titulo' => 'Integraciones: API, Telegram y clima', 'icono' => 'fa-plug', 'roles' => ['admin', 'cliente'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>En <strong>Integraciones</strong> (menú Proyecto) conectas ProjectWeb con otras herramientas (Power BI, Excel, Zapier, Slack, un sistema propio…). Para recibir avisos y reportar avance desde el celular, mira la sección <a href="#telegram">Telegram</a>.</p>
<h4>API REST</h4>
<ul>
<li>Crea un <strong>token</strong> con un nombre que diga para qué es. Se muestra <em>una sola vez</em>: cópialo. Actúa como tú: ve y cambia lo mismo que tú en la web. Hazlo <strong>de solo lectura</strong> si la otra herramienta solo consulta, y ligado a un <strong>proyecto</strong> si no debe ver los demás. Puedes ponerle vigencia y revocarlo cuando quieras.</li>
<li>Se usa con la cabecera <code>Authorization: Bearer pw_…</code> en <code>/api/v1/…</code>. Puedes leer proyectos, partidas, actividades, restricciones, bitácora, punch, RFI, submittals, reportes y la Curva S; y escribir (con un token de escritura) el avance de una actividad, registros de bitácora, pendientes del punch y RFI.</li>
<li>La descripción completa (OpenAPI 3) está en <code>/api/v1/openapi</code> y se puede importar en Postman o Swagger. Límite: 120 peticiones por minuto por token.</li>
</ul>
HTML],

['id' => 'telegram', 'titulo' => 'Telegram: avisos, avance y fotos desde el celular', 'icono' => 'fa-paper-plane', 'roles' => ['admin', 'cliente'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Con el bot de Telegram trabajas desde el celular sin abrir la plataforma: recibes avisos de la obra, reportas avance, mandas fotos y anotas en la bitácora. Es gratis y no necesitas saber nada técnico.</p>
<h4>Conectar tu Telegram (1 minuto)</h4>
<ol>
<li>Entra a <strong>Integraciones</strong> (menú Proyecto) y pulsa <strong>Conectar mi Telegram</strong>.</li>
<li>Se abre Telegram con el bot de la plataforma: pulsa <strong>Iniciar</strong>. Listo, el bot te saluda con tu nombre.</li>
</ol>
<p>Si no hay botón porque Telegram no está activado en tu servidor, pídele al administrador que lo active una vez (se explica en la misma pantalla). Al abrir el chat verás el <strong>menú de comandos</strong> con lo que el bot sabe hacer.</p>
<h4>Recibir avisos</h4>
<ul>
<li><strong>En tu chat:</strong> en <em>Avisos de un proyecto</em> pulsa «Recibir avisos de este proyecto en mi chat».</li>
<li><strong>En el grupo de la obra:</strong> pulsa «Agregar a un grupo de la obra», elige el grupo en Telegram y confirma. Todos los del grupo verán los avisos.</li>
<li>Con <strong>Qué avisos recibe</strong> eliges cuáles llegan: nuevo RFI, cambio de estado, nuevo pendiente del punch list, nueva restricción, cambio de submittal, bitácora importante, alerta de clima y <strong>resumen de la obra cada mañana</strong>. Con <strong>Probar</strong> mandas un mensaje de prueba.</li>
<li><strong>Desde el aviso mismo puedes actuar</strong> (solo en tu chat, no en el grupo): en un pendiente del punch, los botones <em>Corregido</em> o <em>En proceso</em>; en un RFI, <em>Responder</em> (escribes la respuesta y pasa a Respondido); en un submittal, <em>Aprobado</em>, <em>A/AN</em> o <em>Resubmitar</em>. Todos llevan también <em>Ver en la web</em>. Quien no sea administrador ni dueño del proyecto ve los botones pero no puede usarlos.</li>
</ul>
<h4>Reportar avance</h4>
<ol>
<li>En el chat con el bot escribe <code>/avance</code>. Te muestra las actividades de hoy y las atrasadas como botones (o busca una con <code>/avance muro</code>).</li>
<li>Toca la actividad y luego un porcentaje (0, 25, 50, 75, 90, 100) o <strong>escribe el número</strong> (por ejemplo <code>65</code>).</li>
</ol>
<p>El avance cambia igual que en el programa: 0 % queda sin iniciar, 100 % completada y lo demás en proceso. Con <code>/hoy</code> ves qué toca hoy.</p>
<h4>Mandar fotos</h4>
<p>Manda la foto al bot. Si ya elegiste una actividad con <code>/avance</code>, se guarda en ella; si no, el bot te pregunta a cuál pertenece con botones. La foto queda como <strong>evidencia</strong> (sección Evidencias) y lo que escribas como pie de foto será su descripción. Puedes mandar varias a la vez.</p>
<h4>Consultas: salud y PDF</h4>
<ul>
<li><code>/salud</code> — semáforo del proyecto: actividades atrasadas, restricciones, submittals con problema, punch, RFI, recursos, costos, seguridad y actividades en riesgo, solo los que no están en verde.</li>
<li><code>/pdf</code> — manda al chat el <strong>PDF del programa</strong> (Gantt) o del <strong>punch list</strong> para descargarlo o compartirlo desde el celular.</li>
<li><code>/clima</code> — pronóstico de hoy y mañana. <code>/proyecto</code> cambia de proyecto si tienes varios.</li>
</ul>
<h4>Notas</h4>
<ul>
<li><code>/nota</code> seguido del texto lo anota en la bitácora; puedes poner la importancia al inicio: <code>/nota alta: falta concreto</code>.</li>
</ul>
<p><strong>Quién puede qué:</strong> ver y consultar, cualquier persona con acceso al proyecto (menos visitantes). Reportar avance, mandar fotos y notas, y usar los botones de acción, solo el administrador o el dueño del proyecto, igual que en la web. Si cambias de teléfono, pulsa <em>Desconectar</em> y vuelve a conectar.</p>
<h4>Clima automático</h4>
<p>En Integraciones, <strong>Clima automático</strong> → <em>Activar</em>. El proyecto necesita su <em>ubicación</em> (ciudad o «lat,lon») en Ajustes del proyecto. Cada tarde (después de las 6 pm) se anota en la bitácora el clima del día (temperaturas, lluvia y viento) y, si mañana habrá lluvia o viento fuerte, el bot avisa con las actividades de exterior que caen ese día para reprogramarlas. <em>Probar ahora</em> lo ejecuta en el momento. Los datos vienen de Open-Meteo (gratis); solo se envía el nombre del lugar.</p>
<h4>Resumen de la obra cada mañana</h4>
<p>En Integraciones, <strong>Resumen de la obra</strong> → <em>Activar</em>. Después de las 6 am (o la hora configurada en el servidor) el bot manda a tus chats el resumen del día: clima, actividades de hoy, atrasadas, restricciones urgentes, punch grave, RFI y el semáforo. Si no hay nada relevante, no se manda nada; cada proyecto lo manda una vez al día. Para elegir quién lo recibe, marca la casilla <em>«Resumen de la obra cada mañana»</em> en los avisos de cada destino. <em>Probar ahora</em> lo manda en el momento.</p>
HTML],

['id' => 'personalizacion', 'titulo' => 'Personalizar el proyecto', 'icono' => 'fa-sliders', 'roles' => $todos, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Todo esto se ajusta a tu forma de trabajar. Lo de cada <em>proyecto</em> lo cambia quien lo gestiona; el <em>inicio</em> es de cada persona.</p>
<h4>Diseñar las listas (columnas, nombres y orden)</h4>
<p>En <strong>Submittals</strong>, <strong>Bitácora</strong>, <strong>Reporte diario</strong>, <strong>Punch list</strong> y <strong>Lookahead</strong> el botón <strong>Diseñar</strong> abre la lista de columnas: marca las que quieres ver, ponles el nombre que prefieras y, donde se puede, ordénalas con ☰ o las flechas. <em>Restablecer</em> devuelve el diseño de siempre. En el Punch cada pendiente es una tarjeta, así que el diseño elige qué datos lleva; en el Lookahead solo se muestran, ocultan y renombran las columnas fijas (el orden va ligado a las semanas).</p>
<h4>Campos personalizados</h4>
<p>En <strong>Campos personalizados</strong> (menú Proyecto) creas tus propios datos —«Nivel», «Frente», «Torre»— para actividades, punch, submittals, bitácora o reportes. Pueden ser texto, número, fecha o una lista de opciones. Salen como una columna más (se editan ahí mismo), se pueden <strong>filtrar</strong> y, en las listas, <strong>agrupar</strong>. En el programa y en el Lookahead filtran; la agrupación del programa ya son las partidas. También salen en el PDF del programa si su columna está en el diseño.</p>
<h4>Plantillas de proyecto</h4>
<p>Desde <strong>Plantillas de proyecto</strong> guardas la estructura de un proyecto terminado: partidas, actividades, dependencias, jornada, diseños, campos, catálogos y materiales (sin avance, reportes, fotos ni costos). Al crear un proyecto nuevo eliges la plantilla y las fechas se cuentan desde su inicio. Una plantilla también se puede aplicar a un proyecto vacío. El administrador puede compartirlas con todos.</p>
<p>También puedes crear una plantilla sin abrir un proyecto: en <strong>Empezar desde un tipo de obra</strong> eliges un proceso (videovigilancia, control de acceso, cableado, edificación, vivienda, nave industrial, remodelación o urbanización) y ajustas nombres y duraciones antes de guardarla. En <strong>Leer un proceso constructivo</strong> subes el pliego, la memoria o el procedimiento en PDF, Word, PowerPoint, OpenDocument o texto: el sistema detecta el tipo de obra y propone la plantilla. Esa lectura es local, sin IA ni internet; un PDF escaneado sin texto no se puede leer. Para procesos que no están en el catálogo, el administrador puede usar la app de escritorio: en el <strong>Ensamblador</strong> elige <em>Plantilla de proyecto</em>, revisa y aplica la propuesta y pulsa <em>Guardar como plantilla</em>.</p>
<h4>Marca del cliente</h4>
<p>En <strong>Marca del cliente</strong> subes el logo y eliges el nombre y dos colores. Al activarla, los PDF de reportes (programa, Lookahead, Last Planner, curvas, MSR, submittals, minutas, reportes diarios, punch, informes y permisos) llevan una cabecera con el logo y los colores en títulos y encabezados de tabla. Las exportaciones XML/POD y las láminas del vPlan no cambian.</p>
<h4>Mi inicio</h4>
<p>En el inicio, <strong>Personalizar inicio</strong> te deja elegir qué bloques ves (resumen, actividades de hoy, atrasadas, proyectos recientes, novedades, accesos rápidos) y su orden. Solo afecta tu cuenta.</p>
HTML],

['id' => 'ayuda', 'titulo' => 'Consejos y solución de problemas', 'icono' => 'fa-life-ring', 'roles' => $todos, 'herr' => null, 'cuerpo' => <<<'HTML'
<h4>Buenas prácticas</h4>
<ul>
<li>Empieza siempre por el <strong>programa de obra</strong> (partidas y actividades): casi todas las demás herramientas se alimentan de él.</li>
<li>Configura primero la <strong>jornada y los festivos</strong> para que las fechas de fin salgan bien.</li>
<li>Vincula los <strong>materiales</strong> a las actividades para aprovechar el Planificador, el MSR y las Acciones rápidas.</li>
<li>Guarda a menudo en el editor (Ctrl + S) y usa Ctrl + Z si te equivocas.</li>
</ul>
<h4>Si algo no funciona</h4>
<ul>
<li><strong>No veo una herramienta:</strong> tu rol puede no usarla o quien administra tu cuenta no la activó. Pídeselo.</li>
<li><strong>No me deja subir un archivo:</strong> revisa el límite de tamaño de esa sección y el espacio libre del proyecto (Ajustes del proyecto).</li>
<li><strong>La sesión caducó:</strong> recarga la página e inicia sesión de nuevo.</li>
<li><strong>Un visor 360 queda en blanco:</strong> abre la página en un navegador actualizado con WebGL activado.</li>
<li><strong>Cualquier otra duda:</strong> los clientes pueden escribir desde <em>Soporte</em>, y los demás, a quien administra su cuenta.</li>
</ul>
HTML],

];
