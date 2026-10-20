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
<p>Desde el listado también puedes <strong>Editar</strong> o <strong>Eliminar</strong> un proyecto (al eliminarlo se borra todo lo que contiene).</p>
<p class="manual-nota"><strong>Espacio de almacenamiento.</strong> Cada proyecto tiene un límite de espacio para sus archivos, fotos, planos, fichas y logos. Si se llena, el sistema avisa y no deja subir más hasta que borres algo. Lo ves en <em>Ajustes del proyecto</em>.</p>
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
<p>Es el corazón del sistema: el programa de obra organizado en <strong>partidas</strong> (capítulos o frentes, con su código WBS) y <strong>actividades</strong> dentro de cada partida. La página muestra, de arriba abajo, los datos del proyecto, los filtros, la tabla <em>Desglose WBS &amp; Partidas</em> y el <em>Diagrama de Gantt por Semanas</em>.</p>
[[fig:programa]]
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
<h4>Restricciones</h4>
<p>Una <strong>restricción</strong> es algo que impide o pone en riesgo una actividad (un RFI, un material pendiente, un permiso…).</p>
<ol>
<li>Desde una actividad pulsa el triángulo de <em>Agregar restricción</em> (o entra a la actividad completa).</li>
<li>Elige el <strong>tipo*</strong>, un código opcional (ej. RFI-04), la fecha y una <strong>descripción*</strong>.</li>
<li>Cuando se resuelva, pulsa <strong>Resolver</strong>. Las restricciones activas aparecen en el Gantt, en el Lookahead (columna de limitaciones), en vPlan y en las Acciones rápidas.</li>
</ol>
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

['id' => 'planificador', 'titulo' => 'Planificador', 'icono' => 'fa-diagram-next', 'roles' => $todos, 'herr' => 'planificador', 'cuerpo' => <<<'HTML'
<p>Te ayuda a decidir <strong>qué hacer según el material disponible</strong>. Cruza las actividades pendientes del programa con los materiales que les asignaste en el catálogo.</p>
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

['id' => 'vplan', 'titulo' => 'vPlan (láminas)', 'icono' => 'fa-map-location-dot', 'roles' => $todos, 'herr' => 'vplan', 'cuerpo' => <<<'HTML'
<p>El vPlan es una <strong>lámina libre</strong>: un plano sobre el que marcas visualmente el avance, acompañado de las tablas del programa (de una o varias partidas), el recovery y otras tablas. Cada elemento es un <strong>bloque</strong> que colocas donde quieras, en la hoja del tamaño que elijas.</p>
<h4>vPlans y áreas</h4>
<p>Puedes tener <strong>varios vPlan por proyecto</strong> (por ejemplo, uno por disciplina o por fase). Dentro de cada vPlan hay <strong>una lámina por área</strong>, cada una con su plano, tablas, título, simbología y distribución. En el gestor del vPlan usa el <strong>ojo</strong> para mostrar u ocultar láminas (lo oculto no se ve ni se exporta, pero se conserva), las flechas para ordenarlas, el campo <strong>Grupo</strong> para reunirlas por disciplina o nivel, y <strong>Duplicar</strong> para crear otra área con el mismo diseño y tablas (solo subes el plano nuevo). Dentro de una lámina, la lista <strong>Capas</strong> del modo diseño oculta o muestra cada bloque.</p>
<h4>Crear una lámina</h4>
<ol>
<li>En <strong>vPlan</strong> pulsa para crear una lámina. La <strong>partida inicial</strong> es opcional: solo arma el primer bloque de tareas.</li>
<li>Elige el <strong>tamaño de la hoja</strong> (tabloide, carta, A4, A3, A2, A1, Arch D u otra medida en mm, cm o pulgadas) y su orientación.</li>
<li>Escribe el <strong>título</strong> y el <strong>supervisor</strong>.</li>
<li>Sube el <strong>plano</strong> (imagen) y, si quieres, la <strong>simbología</strong> (imagen con la leyenda).</li>
<li>Si elegiste partida, marca qué actividades muestra. Si no marcas ninguna, muestra toda la partida.</li>
<li>Agrega las columnas de <strong>recovery</strong> que necesites (una o más) en el modo diseño, pestaña «Insertar».</li>
</ol>
<h4>Modo diseño: poner cada cosa donde quieras</h4>
<p>En la lámina pulsa <strong>Modo diseño</strong>. Cada elemento es un bloque que arrastras para moverlo y redimensionas con las esquinas o los bordes (flechas para afinar, Supr para quitarlo; «Rejilla» alinea a 0.5 %). Las herramientas están en el panel de la derecha, en cuatro pestañas: <strong>Propiedades</strong>, <strong>Capas</strong>, <strong>Insertar</strong> y <strong>Hoja</strong>. A la izquierda de la lámina, el panel de <strong>láminas</strong> (con miniaturas, agrupadas) te deja cambiar de área con un clic.</p>
<ul>
<li><strong>Título, supervisor, fecha y texto libre:</strong> se escriben a mano (o quedan automáticos), con tamaño de letra, alineación, negrita y color.</li>
<li><strong>Logos, plano, simbología y simbología del schedule:</strong> se ajustan al bloque sin deformarse. Puedes agregar más logos o textos desde «Insertar».</li>
<li><strong>Tareas (partidas):</strong> un bloque puede mostrar <strong>varias partidas</strong>, completas o solo algunas actividades, con su propio título y color. La numeración (SEQ) es <strong>continua 1..n</strong> o <strong>la de cada partida</strong>, con un subtítulo por partida. Puedes tener varios bloques de tareas en la misma lámina.</li>
<li><strong>Tablas adicionales:</strong> cada una es un bloque; en «Insertar» aparecen todas las tablas del proyecto de todos los tipos (también las hojas de SheetFlow), con buscador y la opción de insertar una copia independiente. Su letra se ajusta sola al espacio del bloque.</li>
<li><strong>Capas:</strong> lista de todos los objetos; arrástralos para cambiar el orden (arriba = al frente), ocúltalos con el ojo, bloquéalos con el candado para no moverlos por error y renómbralos con doble clic.</li>
<li><strong>Guías (como en Inkscape):</strong> arrastra desde las <strong>reglas</strong> (la de arriba da guías horizontales; la de la izquierda, verticales) y suelta dentro de la hoja. Los bloques se <strong>imantan</strong> a las guías, a los bordes y al centro de la hoja. Las guías también están en «Capas»: puedes ocultarlas, bloquearlas, escribir su posición en cm o quitarlas (o arrastrarlas fuera de la hoja). No se imprimen ni se exportan.</li>
<li><strong>Recovery:</strong> en «Insertar» agregas tantas columnas de recovery como necesites (Recovery, Recovery 02, 03…) y quitas la última; las fechas capturadas se conservan.</li>
<li><strong>Hoja:</strong> cambia el tamaño u orientación en cualquier momento; los bloques conservan su lugar en proporción. «Restablecer diseño estándar» vuelve a la disposición clásica.</li>
</ul>
<p class="manual-nota">Las restricciones se numeran en toda la lámina (en el orden en que se leen los bloques de tareas), así que un rombo con el mismo número es siempre la misma restricción.</p>
<h4>La lámina (vista de lectura)</h4>
<ul>
<li>El <strong>plano con sus marcas</strong> y las tablas del programa: SEQ, tarea, inicio, fin, recovery, % y restricciones, según el diseño de la lámina.</li>
<li>Las fechas de <strong>recovery</strong> se editan directamente en la tabla (con permiso de edición).</li>
<li>Puedes agregar <strong>tablas adicionales</strong> (punch list, restricciones, secundarias u otras) desde el catálogo de tablas; el catálogo se administra en <em>vPlan → Tablas</em>.</li>
<li><strong>Ampliar</strong> muestra la lámina a pantalla completa; Esc o el botón de cerrar la regresan.</li>
<li>Si el avance del programa cambió desde que dibujaste las marcas, aparece un aviso y el botón <strong>Actualizar plano</strong>.</li>
</ul>
<h4>Editar las marcas</h4>
<p>El botón <strong>Editar marcas</strong> abre el mismo <a href="#editor">editor de canvas</a> que usa la sección Editor: dibujas líneas, formas, texto, tablas, hipervínculos, imágenes, etc. sobre el plano. Pulsa <strong>Guardar</strong> (o Ctrl + S) para generar la imagen final de la lámina.</p>
<h4>Notas de la lámina</h4>
<p>En <strong>Acciones</strong> pulsa <strong>Crear nota</strong>: escribe una descripción y se guarda con la fecha del día. Puedes editar o borrar notas; la más reciente queda arriba.</p>
<p class="manual-nota">Para imprimir una lámina o llevarla a PDF usa <em>Exportar → vPlan</em>: una lámina por página, con el tamaño de hoja de cada lámina (si mezclas tamaños, el PDF las une en orden).</p>
HTML],

['id' => 'lastplanner', 'titulo' => 'Last Planner', 'icono' => 'fa-note-sticky', 'roles' => $todos, 'herr' => 'lastplanner', 'cuerpo' => <<<'HTML'
<p>Genera los <strong>post-its de planeación</strong> a partir del programa de obra: una tarjeta por actividad y día.</p>
<ul>
<li>Elige la <strong>partida</strong> (o <em>Mostrar todas</em>).</li>
<li>El rombo de cada tarjeta indica el estado: <strong>verde</strong> actividad finalizada, <strong>amarillo</strong> en proceso, <strong>rojo</strong> con restricción activa.</li>
<li>Los post-its se calculan solos: si cambian las fechas del programa, se ajustan.</li>
</ul>
<p>Para imprimirlos usa <em>Exportar → Last Planner</em>: tarjetas de 7×7 cm en hojas carta con marcas de corte; puedes elegir la partida y cuántas tarjetas van por fila.</p>
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
<h4>Personal</h4>
<p>Es el catálogo de personas del proyecto: <strong>nombre*</strong>, departamento y una imagen opcional. Se administra desde el botón <em>Personal</em> y alimenta los reportes diarios y las minutas.</p>
<p class="manual-nota">Las fotos de cada reporte aparecen solas en <a href="#evidencias">Evidencias</a>.</p>
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
<p>El repositorio de documentos del proyecto: planos, especificaciones, contratos, hojas de cálculo, etc.</p>
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
<li>Guarda.</li>
</ol>
<h4>Estatus</h4>
<p>En el listado, el estatus es un grupo de botones: <strong>En revisión</strong>, <strong>Resubmitar</strong>, <strong>Aprobado</strong> y <strong>A/AN</strong>. Al cambiarlo, el sistema actualiza los renglones del MSR (status equivalente y fecha de respuesta al aprobar).</p>
<p>Con <strong>Ver</strong> abres el paquete (imagen, material y especialidad de cada uno) y con <strong>Exportar PDF</strong> lo descargas. Los submittals pendientes aparecen en las Acciones rápidas.</p>
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
<p>Muestra fecha, nombre (con miniatura), categoría y tamaño. Por gráfico: <strong>Ver</strong>, <strong>Editar</strong>, <strong>Descargar</strong> (PNG, PDF o presentación .odp), <strong>Duplicar</strong> (conserva la categoría) y <strong>Eliminar</strong>.</p>
<h4>Categorías</h4>
<p>El botón <strong>Categorías</strong> abre un pequeño CRUD para clasificar tus gráficos: crear, renombrar y eliminar (no se puede eliminar una categoría que algún gráfico use; los nombres no se repiten). El selector <em>Categoría</em> aparece al crear un gráfico y en <em>Tamaño y fondo</em>.</p>
<h4>Crear un gráfico</h4>
<ol>
<li>Pulsa <strong>Nuevo gráfico</strong>. La fecha se pone sola.</li>
<li>Escribe el <strong>nombre*</strong> y elige la <strong>categoría</strong> (opcional).</li>
<li><strong>Tamaño:</strong> un formato común (pantalla, papel a 300 dpi, redes) u <em>Personalizado</em>, la orientación y el ancho × alto en píxeles.</li>
<li><strong>Fondo:</strong> una plantilla (liso, retícula, milimetrado, papel de ingeniería, texturas, fondos oscuros…) o <em>Imagen propia</em>, que puedes ajustar (modo, zoom, posición, repeticiones, opacidad).</li>
<li>Se abre el editor. Puedes cambiar tamaño y fondo después con <strong>Tamaño y fondo</strong>; el contenido se centra o se queda en la esquina, tú eliges.</li>
</ol>
<h4>La barra de herramientas</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Grupo</th><th>Herramientas</th></tr></thead>
<tbody>
<tr><td><strong>Selección</strong></td><td>Seleccionar y mover (Esc), y selección múltiple con recuadro. Con Mayús o Ctrl + clic sumas marcas.</td></tr>
<tr><td><strong>Líneas</strong></td><td>Continua, discontinua, trazo y punto, punteada, polilínea (clic por punto, doble clic o Enter para terminar), lápiz libre, flecha y flecha doble.</td></tr>
<tr><td><strong>Formas</strong></td><td>Rectángulo, redondeado, elipse, triángulo, rombo, pentágono, hexágono, estrella, cruz, equis, palomita, flecha de bloque, chevron, trapecio y zona translúcida, más 22 formas extra (octágono, corazón, rayo, gota, globo de texto, bandera, flechas de bloque en cuatro direcciones, estrellas, etc.). Las líneas recuerdan el último color, grosor y opacidad usados.</td></tr>
<tr><td><strong>Texto y marcadores</strong></td><td>Texto, tabla (arrastra para dibujarla; doble clic en una celda para escribir; Tab pasa a la siguiente), rombo de restricción numerado, círculo numerado, imagen, hipervínculo y vista 360.</td></tr>
<tr><td><strong>Medir</strong></td><td>Traza dos puntos; la primera vez indicas cuánto mide en la realidad y esa será la escala de las demás medidas. Eliges la unidad (mm, cm, m, pulgadas).</td></tr>
</tbody>
</table>
<h4>Panel derecho</h4>
<ul>
<li><strong>Propiedades:</strong> color de línea y de relleno, grosor, estilo de trazo, opacidad, tipo de letra, número, capa, comentario, etc. Cambian según la marca elegida.</li>
<li><strong>Capas:</strong> organiza el dibujo; puedes ocultar o bloquear capas.</li>
<li><strong>Láminas:</strong> agrega y navega entre láminas (Av Pág / Re Pág).</li>
<li><strong>Símbolos:</strong> inserta imágenes de la biblioteca de símbolos y cajetines (cuadros de datos).</li>
<li><strong>Alinear y distribuir:</strong> con varias marcas elegidas aparecen los botones para alinearlas y distribuirlas; también puedes <strong>agrupar</strong> (Ctrl + G).</li>
<li><strong>Comentarios:</strong> el campo Comentario de una marca crea una insignia amarilla en el editor; no sale en las imágenes exportadas.</li>
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
<h4>Crear un documento</h4>
<ol>
<li>Pulsa <strong>Nuevo documento</strong> y captura el <strong>nombre*</strong>, la <strong>fecha*</strong> y el <strong>departamento</strong> (elige uno de la lista o escribe uno nuevo). La lista se agrupa por departamento.</li>
<li>Elige el <strong>corrector</strong> (Español, English o ambos), la <strong>hoja</strong> (Carta, A4, Oficio, Tabloide) y su orientación.</li>
</ol>
<h4>Herramientas</h4>
<ul>
<li><strong>Texto:</strong> fuente, tamaño, negrita, cursiva, subrayado, tachado, sub/superíndice, color, resaltado, alineación, viñetas, numeración, sangría, interlineado y estilos (títulos, cita, código).</li>
<li><strong>Cortar, copiar y pegar</strong> (también pegado sin formato; lo pegado desde Word o LibreOffice se limpia).</li>
<li><strong>Tablas:</strong> inserta con filas y columnas; con el cursor dentro aparecen agregar/quitar fila o columna, color de celda y eliminar tabla. <kbd>Tab</kbd> pasa a la siguiente celda.</li>
<li><strong>Imágenes</strong> (desde tu equipo, pegadas o gráficos del Editor; clic en la imagen para cambiar su tamaño y alineación) y <strong>figuras</strong> (rectángulo, círculo, triángulo, rombo, estrella, flecha, línea).</li>
<li><strong>Hipervínculos:</strong> a una página web, a un correo o a <em>cualquier sección o documento de la plataforma</em> (programa, láminas, gráficos, otros documentos…). <kbd>Ctrl</kbd>+clic abre el enlace al editar.</li>
<li><strong>Página:</strong> márgenes en cm (normal, estrecho, amplio, encuadernado o personalizados), numeración de páginas («Página N de M»), encabezado con nombre · departamento · fecha y <strong>salto de página</strong>.</li>
<li><strong>Buscar y reemplazar</strong> (<kbd>Ctrl</kbd>+<kbd>F</kbd>).</li>
</ul>
<h4>Ortografía</h4>
<p>Las palabras mal escritas se subrayan en rojo. <strong>Clic derecho</strong> sobre una de ellas para ver sugerencias, <em>Ignorar</em> o <em>Agregar al diccionario</em> (la palabra queda para todos los documentos del proyecto). Los diccionarios de español (México) e inglés van incluidos y funcionan sin internet.</p>
<h4>Guardar y exportar</h4>
<p>Se guarda solo cada 30 segundos y con <kbd>Ctrl</kbd>+<kbd>S</kbd>. <strong>Exportar</strong> descarga Word (.docx), LibreOffice (.odt) o PDF con el nombre <code>Año_mes_día_Nombre</code>, y <strong>Imprimir</strong> abre el PDF listo para imprimir con la hoja, márgenes y numeración elegidos. La línea de corte de página en pantalla es aproximada; el PDF muestra la paginación exacta.</p>
HTML],

['id' => 'ensamblador-documentos', 'titulo' => 'Ensamblador de documentos', 'icono' => 'fa-object-group', 'roles' => $todos, 'herr' => 'ensamble', 'cuerpo' => <<<'HTML'
<p>Une en <strong>un solo PDF no editable</strong> documentos del <strong>Redactor</strong> y PDF que subiste al <strong>Archivo</strong>: por ejemplo una memoria de cálculo más sus planos en PDF.</p>
<h4>Crear un ensamble</h4>
<p><em>Documentos y diseño › Ensamblador de documentos › Nuevo ensamble</em>. Escribe el nombre, pulsa <strong>Agregar</strong> en cada documento y ordénalos con ▲ ▼. El PDF sale en ese orden.</p>
<h4>Que se actualice solo</h4>
<p>Con <strong>Guardar en Archivo</strong> el PDF queda en el Archivo del proyecto (puedes elegir carpeta). Si después cambias un documento original del Redactor o reemplazas un PDF fuente, ese archivo se <strong>regenera solo</strong> la próxima vez que se abra o descargue. Por ejemplo, si el título era verde y lo cambias a azul en el Redactor, el PDF del Archivo lo mostrará azul.</p>
<p class="manual-nota">Lo que descargas es un PDF común: ya no se actualiza ni conserva el vínculo. Si eliminas el ensamble, el PDF del Archivo se queda como está pero deja de actualizarse.</p>
HTML],

['id' => 'sheetflow', 'titulo' => 'SheetFlow (tablas)', 'icono' => 'fa-table-cells', 'roles' => $todos, 'herr' => 'sheetflow', 'cuerpo' => <<<'HTML'
<p>Crea <strong>tablas</strong> como en una hoja de cálculo (materiales, presupuestos, asistencias, registros) y úsalas en el <strong>vPlan</strong>, el <strong>Editor</strong> y el <strong>Redactor</strong> sin volver a capturarlas.</p>
<h4>Crear una tabla</h4>
<ol>
<li>Pulsa <strong>Nueva tabla</strong> y captura el <strong>nombre*</strong>, la <strong>fecha*</strong> y el <strong>departamento</strong>; la lista se agrupa por departamento.</li>
<li>Elige el punto de partida: <strong>en blanco</strong>, una <strong>plantilla</strong> (control de materiales, presupuesto, restricciones, asistencia semanal o el programa de obra) o <strong>importa</strong> un archivo <code>.xlsx</code>, <code>.ods</code> o <code>.csv</code> (primera hoja; la primera fila son los encabezados).</li>
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
<h4>Usar la tabla en otras herramientas (vínculos vivos)</h4>
<ul>
<li><strong>vPlan:</strong> en SheetFlow (<em>A vPlan</em>) o en <em>vPlan › Tablas adicionales › Crear desde SheetFlow</em> se crea una tabla vinculada (hasta 8 columnas y 200 renglones) que luego agregas a las láminas. Lo que cambies en SheetFlow se ve en la lámina, y lo que edites en la lámina se escribe en SheetFlow (no se pueden editar celdas con fórmula ni el renglón de totales desde la lámina).</li>
<li><strong>Redactor:</strong> <em>Insertar › Tabla › Origen › Tablas de SheetFlow</em>. La tabla se actualiza sola al abrir, ver y exportar el documento; para cambiarla, edítala en SheetFlow.</li>
<li><strong>Editor:</strong> panel <em>Símbolos › Tablas de SheetFlow</em>. Se actualiza al abrir el gráfico (guarda para conservar el cambio) y la puedes mover y cambiar de tamaño.</li>
</ul>
<p class="manual-nota">Si borras la tabla de SheetFlow, la de vPlan se queda con sus últimos datos y las del Redactor y el Editor con lo último que se guardó.</p>
HTML],

['id' => 'simbolos', 'titulo' => 'Símbolos', 'icono' => 'fa-shapes', 'roles' => $todos, 'herr' => 'graficos', 'cuerpo' => <<<'HTML'
<p>La <strong>biblioteca de símbolos</strong> del proyecto: imágenes reutilizables que insertas en el vPlan y en el Editor (cámaras, extintores, tableros, señales…).</p>
<ul>
<li><strong>Nuevo símbolo:</strong> escribe el <em>nombre*</em>, una categoría y sube la imagen (PNG con fondo transparente o SVG funcionan mejor). Pulsa <strong>Agregar símbolo</strong>.</li>
<li><strong>Importar símbolos:</strong> sube un <strong>.zip</strong> de biblioteca o varias imágenes de una vez. <strong>Exportar biblioteca</strong> descarga la tuya para reusarla en otro proyecto.</li>
<li>Puedes <strong>cambiar la imagen</strong> o eliminar un símbolo propio. Los <em>incluidos con la aplicación</em> sirven para todos los proyectos y no se editan.</li>
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
<tr><td><strong>Reportes de obra (PDF)</strong></td><td>Reporte diario (un día o un mes), Bitácora (por día, mes, departamento o importancia), Minutas (por importancia, día o mes), Submittals (uno, varios o todos), Materiales (por especialidad, estatus de compra o entrega) y Evidencias (por día, semana, mes o actividad). Carta vertical; los filtros se combinan y los vacíos no filtran.</td></tr>
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
