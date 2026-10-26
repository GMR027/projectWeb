<?php
// Secciones del Manual solo para el administrador (guía detallada con ilustraciones [[fig:nombre]], ver _ilustraciones.php).
// 'despues' = id de la sección base tras la que se inserta.
$admin = ['admin'];

return [

['id' => 'novedades', 'despues' => 'primeros-pasos', 'titulo' => 'Novedades de la plataforma', 'icono' => 'fa-wand-magic-sparkles', 'roles' => ['admin', 'cliente', 'visitante'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Un repaso de lo más reciente que ha ganado ProjectWeb, con el enlace a la sección del manual donde se explica cada cosa. Lo verás siempre al día: cuando se agrega una herramienta, se documenta aquí.</p>
<h4>Octubre 2026</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Novedad</th><th>Qué cambia para ti</th></tr></thead>
<tbody>
<tr><td><strong>Tablas que se adaptan</strong><br><small><a href="#sheetflow">SheetFlow</a> · <a href="#vplan">vPlan</a> · <a href="#editor">Editor</a></small></td><td>El texto de las tablas se ajusta al tamaño: <strong>crece o se reduce</strong> con el bloque del vPlan, con las asas del Editor y con el ancho de la pantalla. Se acabaron las letras diminutas en una tabla grande.</td></tr>
<tr><td><strong>Todo en tiempo real</strong><br><small><a href="#sheetflow">SheetFlow</a></small></td><td>Si cambias una tabla en SheetFlow o desde una lámina, el vPlan, la vista de la tabla y el Editor <strong>se actualizan solos</strong>, sin recargar a mano.</td></tr>
<tr><td><strong>Tabla inteligente</strong><br><small><a href="#sheetflow">SheetFlow</a></small></td><td>Constructor guiado con columnas de tipo <em>lista</em>, <em>casilla</em> y <em>semáforo</em>, y color de encabezado por columna. Funcionan también al editar desde el vPlan.</td></tr>
<tr><td><strong>Punch list conectado</strong><br><small><a href="#punch">Punch list</a></small></td><td>Inserta tus pendientes como tabla en el <strong>vPlan, el Redactor y el Editor</strong>, eligiendo columnas, fotos antes/después y filtros. Se actualiza con el módulo.</td></tr>
<tr><td><strong>Estampas inteligentes y cajetines</strong><br><small><a href="#simbolos">Símbolos</a> · <a href="#lastplanner">Last Planner</a></small></td><td>Bloques cuyas etiquetas se llenan solas con datos del proyecto (avance, fechas, semáforos, actividades del día), con editor visual. Hay plantilla de <strong>post-it de Last Planner</strong> y <strong>encabezados dinámicos</strong> para el MSR y el Lookahead.</td></tr>
<tr><td><strong>vPlan de láminas libres</strong><br><small><a href="#vplan">vPlan</a></small></td><td>Cada lámina es una hoja de cualquier tamaño donde colocas bloques (plano, tablas, recovery, simbología, texto) con <strong>capas, guías y rejilla</strong>. Varios vPlan por proyecto, navegador de láminas y PDF por lámina.</td></tr>
<tr><td><strong>Lookahead configurable</strong><br><small><a href="#lookahead">Lookahead</a></small></td><td>Nombre de la herramienta, columna PTA, título del banner y encabezado con logos a tu medida, de 1 a 8 semanas visibles.</td></tr>
<tr><td><strong>Editor más preciso</strong><br><small><a href="#editor">Editor</a></small></td><td>Líneas con largo y separación del trazo, <strong>nodos editables</strong> y símbolos o simbologías que se actualizan solos al cambiar su origen.</td></tr>
<tr><td><strong>Programa más ágil</strong><br><small><a href="#programa">Programa de obra</a></small></td><td>Seleccionar varias partidas o actividades para eliminarlas en lote, avance editable en línea y avance inicial al crear actividades. Las restricciones se pueden editar.</td></tr>
</tbody>
</table>
<h4>Meses anteriores</h4>
<ul>
<li><strong><a href="#ensamblador-documentos">Unir documentos (PDF)</a></strong> (antes «Ensamblador de documentos»): un PDF que une documentos del Redactor y PDF del Archivo y se actualiza solo cuando cambian.</li>
<li><strong><a href="#traductor">Traductor</a></strong> español ↔ inglés de textos y documentos.</li>
<li><strong><a href="#sheetflow">SheetFlow</a></strong>: fórmulas, formato por celda, bordes, imágenes, importación de Excel/CSV y archivo propio <code>.sheetflow</code>.</li>
<li><strong><a href="#cambios">Órdenes de cambio</a> y <a href="#lineas-base">líneas base</a></strong>: cada desviación con su impacto y su causa; comparación con barra «fantasma».</li>
<li><strong><a href="#datos-obra">Datos de obra</a></strong> y <strong><a href="#tipo-obra">Tipo de obra y Mapa de avance</a></strong>.</li>
<li><strong><a href="#dependencias">Dependencias y ruta crítica</a></strong>, <strong>Curva S</strong>, <strong><a href="#msr">MSR configurable</a></strong> (columnas, nombre y color a tu gusto), <strong><a href="#view360">View360</a></strong> y <strong>carpetas por departamento</strong>.</li>
</ul>
<p class="manual-nota">¿Te falta algo? Escríbenos desde <em>Soporte</em> (si eres cliente) o desde el formulario de contacto de la página principal.</p>
HTML],

['id' => 'novedades-admin', 'despues' => 'novedades', 'titulo' => 'Novedades para el administrador', 'icono' => 'fa-user-shield', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Además de lo que ve cualquier usuario (sección anterior), estas son las novedades que solo ve el administrador.</p>
<table class="tabla manual-tabla">
<thead><tr><th>Novedad</th><th>Qué cambia para ti</th></tr></thead>
<tbody>
<tr><td><strong>App de escritorio 0.3.4</strong><br><small><a href="#escritorio">App de escritorio</a></small></td><td>Engranaje de Ajustes en la propia app, campana de actualizaciones (sin pedir token), reconocimiento de texto (OCR) en documentos escaneados y arreglo del inicio de sesión. Las llaves de la IA siguen cifradas en tu equipo.</td></tr>
<tr><td><strong>Centro de IA</strong><br><small><a href="#centro-ia">Centro de IA</a></small></td><td>Un lugar para revisar, aplicar o rechazar las propuestas de todas las herramientas de IA, con auditoría de cada decisión. <strong>Nada cambia en tus datos sin que lo aceptes.</strong></td></tr>
<tr><td><strong>Programador, Inconsistencias y Materiales</strong><br><small><a href="#programador-ia">Programador</a> · <a href="#inconsistencias">Inconsistencias</a> · <a href="#analisis-materiales">Materiales</a></small></td><td>La IA arma actividades, detecta fechas que no cuadran, datos duplicados o incompletos, y cruza materiales contra el cronograma. Tú decides qué se aplica.</td></tr>
<tr><td><strong>Ensamblador con IA</strong><br><small><a href="#ensamblador">Ensamblador</a></small></td><td>Convierte PDF, PPTX o DOCX en partidas, materiales y submittals. Distinto de «Unir documentos (PDF)», que solo junta PDF.</td></tr>
<tr><td><strong>Ordenar y renumerar</strong><br><small><a href="#ordenar">Ordenar y renumerar</a></small></td><td>Reordena el programa por fechas y renumera la EDT sin IA, con vista previa.</td></tr>
<tr><td><strong>Revisiones</strong><br><small><a href="#revisiones">Revisiones</a></small></td><td>Semáforo ✅ ⚠ ❌ por proyecto y estado de uso de cada herramienta para saber qué falta por capturar.</td></tr>
<tr><td><strong>Seguridad y operación</strong><br><small><a href="#sistema">Ajustes del sistema</a> · <a href="#seguridad">Seguridad</a></small></td><td>Subidas de imagen validadas por su tipo real, IP del visitante detrás del proxy, copia opcional de cada respaldo automático fuera del volumen de datos y PHP desactivado en las carpetas de archivos subidos.</td></tr>
</tbody>
</table>
HTML],

['id' => 'admin-guia', 'despues' => 'primeros-pasos', 'titulo' => 'Guía del administrador', 'icono' => 'fa-user-shield', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Esta guía resume lo que hace el administrador y en qué orden. Cada tema tiene su propia sección más abajo, con ilustraciones; los números de los globos naranja de cada imagen se explican en el texto.</p>
[[fig:pantalla]]
<ol>
<li><strong>Menú lateral.</strong> Arriba están los accesos generales del administrador: <em>Inicio</em>, <em>Portafolio de Proyectos</em>, <em>Usuarios y Accesos</em>, <em>Bandeja de entrada</em> y <em>Ajustes</em>. Al abrir un proyecto aparece <em>Proyecto actual</em> con todas sus herramientas.</li>
<li><strong>Encabezado del proyecto:</strong> nombre, días, duración y fechas.</li>
<li><strong>Barra de herramientas</strong> para saltar entre herramientas del proyecto.</li>
<li><strong>Contenido</strong> de la herramienta abierta.</li>
<li><strong>Manual de usuario</strong>, siempre al pie del menú lateral. Escribe en su buscador para ir directo a un tema.</li>
</ol>
<h4>Orden recomendado para un proyecto nuevo</h4>
[[fig:flujo]]
<ol>
<li><strong>Crea el proyecto</strong> (Portafolio → + Nuevo Proyecto) y asigna a quién lo verá.</li>
<li><strong>Configura el calendario:</strong> jornada semanal y festivos, en <em>Ajustes</em> del proyecto. Sin esto las fechas de fin no serán confiables.</li>
<li><strong>Arma el programa:</strong> partidas y actividades, a mano o con el <em>Ensamblador</em> a partir de un documento.</li>
<li><strong>Define dependencias</strong> para obtener la ruta crítica.</li>
<li><strong>Da seguimiento cada semana:</strong> avance, lookahead, reportes, bitácora, materiales.</li>
<li><strong>Exporta</strong> PDF, XML de MS Project o el paquete que necesites.</li>
</ol>
<h4>Rutina sugerida</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Cuándo</th><th>Qué revisar</th></tr></thead>
<tbody>
<tr><td><strong>Cada día</strong></td><td>Bandeja de entrada, Acciones rápidas del proyecto, reporte diario y avisos del banner de Datos de obra.</td></tr>
<tr><td><strong>Cada semana</strong></td><td>Avance de actividades, Lookahead, MSR (materiales), Propuestas del Centro de IA y estado de <em>Revisiones</em>.</td></tr>
<tr><td><strong>Cada mes</strong></td><td>Respaldos y espacio del servidor (Ajustes), usuarios con acceso, línea base nueva si hubo un cambio mayor.</td></tr>
</tbody>
</table>
<p class="manual-nota"><strong>Regla de oro de la IA.</strong> Ninguna herramienta de IA cambia tus datos por sí sola: propone, tú aceptas, y todo queda en el historial con opción de revertir.</p>
<p class="manual-nota"><strong>Espacio de los proyectos que creas.</strong> Cada proyecto tiene un tope de espacio para sus archivos, fotos, planos y fichas. En los proyectos que te pertenecen (creados por ti) puedes ampliarlo o reducirlo cuando quieras en <em>Ajustes del proyecto → Datos → Almacenamiento</em>: escribe los MB y pulsa <em>Ampliar o reducir</em>. Con <strong>0</strong> el proyecto vuelve al límite heredado. Los proyectos de un cliente usan el espacio que tú le fijaste en <em>Usuarios y Accesos</em>.</p>
HTML],

['id' => 'escritorio', 'despues' => 'sistema', 'titulo' => 'App de escritorio', 'icono' => 'fa-desktop', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>La <strong>app de escritorio</strong> (Project Web Desktop) es la forma de usar las herramientas de IA: muestra tu ProjectWeb y añade un menú de <em>Herramientas IA</em>. El modelo se ejecuta en <strong>tu equipo</strong> y la app envía el resultado al servidor, que lo valida. Solo el administrador la usa.</p>
[[fig:escritorio]]
<h4>Instalar y actualizar</h4>
<ol>
<li>Entra a <strong>Ajustes</strong> del sistema y baja a <em>App de escritorio</em>. Ahí descargas el instalador (<code>.deb</code> para Ubuntu/Debian o <code>.AppImage</code>).</li>
<li>Instala el <code>.deb</code> con doble clic o con <code>sudo apt install ./projectweb-desktop-linux-amd64.deb</code>.</li>
<li>Al abrir la app indica la dirección de tu servidor e inicia sesión con tu cuenta de administrador.</li>
<li>La app consulta sola si hay una versión nueva (pestaña <em>Servidor y actualización</em> de sus Ajustes). Cuando exista una, descárgala e instálala encima de la anterior.</li>
</ol>
<h4>Recorrido de la pantalla</h4>
<ul>
<li><strong>① Menú de Herramientas IA</strong>, agrupado: <em>Resultados y control</em> (Análisis inteligente), <em>Planeación</em> (Programador), <em>Ensamblador</em> (documentos y ordenar por fechas), <em>Revisión de datos</em> (Calidad de datos) y <em>Project Web</em> (atajos a páginas que no son IA, plegado). Junto a cada herramienta ves una marca de uso: <strong>al día</strong>, <strong>desactualizada</strong> o <strong>sin ejecutar</strong>.</li>
<li><strong>② Panel</strong> de la herramienta elegida.</li>
<li><strong>③ Ajustes</strong> (abajo del menú): Configuración de IA, Voz, Apariencia, Almacenamiento, Servidor y actualización y Desinstalar.</li>
<li><strong>④ Chip de IA</strong> del encabezado: muestra la conexión activa; al pulsarlo abre tus <em>conexiones guardadas</em>.</li>
<li>Arriba eliges el <strong>proyecto</strong> sobre el que trabajan las herramientas.</li>
</ul>
[[fig:ia-manual-uso]]
<h4>Vigilancia continua</h4>
<p>En <em>Análisis inteligente</em> hay un interruptor de <strong>Vigilancia continua</strong>: con él activo, la app revisa el proyecto en segundo plano y te avisa con una notificación (por ejemplo, un material por vencer o una inconsistencia nueva). Se guarda al cambiarlo.</p>
<h4>Modo sin conexión</h4>
<p>En <em>Ajustes → Modo sin conexión</em> (por equipo) puedes guardar páginas y encolar cambios de estado de renglones de órdenes de cambio; se suben solos al volver la red. Si alguien más cambió el mismo renglón, la app te pregunta si aplicas el tuyo o descartas.</p>
<p class="manual-nota">Las <strong>claves de API</strong> se guardan cifradas en tu equipo y nunca en el servidor.</p>
HTML],

['id' => 'ia-conexion', 'despues' => 'escritorio', 'titulo' => 'Configurar la IA (conexiones)', 'icono' => 'fa-plug', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>En la app de escritorio, <strong>Ajustes → Configuración de IA</strong> decide con qué «cerebro» trabajan las herramientas. Tienes tres formas de conectarlo y puedes guardar varias.</p>
[[fig:ia-conexion]]
<h4>Tipos de conexión ①</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Pestaña</th><th>Qué es</th><th>Cuándo usarla</th></tr></thead>
<tbody>
<tr><td><strong>IA local</strong></td><td>Un modelo que corre en tu computadora (por ejemplo con Ollama). No sale información de tu equipo.</td><td>Revisiones frecuentes y datos sensibles. La app muestra tu hardware y los modelos que aguantará tu equipo.</td></tr>
<tr><td><strong>Suscripción</strong></td><td>Un asistente por línea de comandos (CLI) de una suscripción que ya pagas.</td><td>Planeación e informes, que piden más capacidad.</td></tr>
<tr><td><strong>API</strong></td><td>Un servicio en la nube con tu clave de API.</td><td>Cuando necesitas el modelo más potente y aceptas el costo por uso.</td></tr>
<tr><td><strong>Conexiones guardadas</strong></td><td>Tu lista de conexiones ya configuradas.</td><td>Cambiar rápido de una a otra.</td></tr>
</tbody>
</table>
<h4>Configurar una conexión ②</h4>
<ol>
<li>Elige la pestaña, selecciona el <strong>modelo</strong> y, si aplica, escribe la clave o confirma que el CLI está instalado.</li>
<li>Pulsa <strong>Probar conexión</strong>; debe responder correctamente.</li>
<li>Pulsa <strong>Guardar</strong>. Queda disponible en el chip de IA del encabezado.</li>
</ol>
<h4>Qué conexión usa cada tarea ③</h4>
<p>Puedes asignar un modelo distinto según el trabajo: uno ligero (puede ser local) para <em>revisiones, verificaciones y chat</em>, y otro potente para <em>planeación e informes</em>. Si lo dejas en «principal» se usa la conexión principal. En cada herramienta todavía puedes cambiarla al momento de ejecutar.</p>
<p class="manual-nota"><strong>Consejo.</strong> Si un análisis tarda demasiado, prueba con un modelo más ligero o con menos actividades por paquete; las herramientas avisan cuando la capacidad de tu equipo no alcanza.</p>
HTML],

['id' => 'centro-ia', 'despues' => 'exportar', 'titulo' => 'Centro de IA', 'icono' => 'fa-robot', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Es el lugar donde se reúne todo lo que hacen las herramientas de IA en un proyecto. Está en el menú del proyecto como <strong>Centro de IA</strong> y tiene ocho pestañas (la última, «Calidad de la IA», compara modelos).</p>
[[fig:ciclo-ia]]
[[fig:centro-ia]]
<h4>Pestañas ①</h4>
<ul>
<li><strong>Resumen.</strong> La pantalla del día (no es un correo): qué cambió la IA, qué espera tu aprobación, qué herramienta está sin ejecutar o desactualizada y qué detectó la vigilancia. Aquí también activas la <em>vigilancia en el servidor</em> (con tarea programada, <code>bin/vigilancia_programada.php</code>) para que revise el proyecto aunque la app de escritorio esté cerrada.</li>
<li><strong>Propuestas.</strong> Bandeja de lo que la IA encontró y sigue pendiente de tu decisión, con herramienta, severidad (alta, media, baja) y quién lo detectó. Aquí aceptas o rechazas.</li>
<li><strong>Informes.</strong> Informes generados (semanal, resumen ejecutivo, etc.). Puedes <em>Generar un informe</em> con IA o <em>sin IA</em> (con texto de plantilla), revisarlo, aprobarlo y descargarlo en PDF.</li>
<li><strong>Grabaciones.</strong> Graba una reunión o sube un audio; se transcribe y de ahí salen minutas, compromisos y tareas.</li>
<li><strong>Auditoría de la IA.</strong> Registro de auditoría de todo lo que la IA aplicó, revirtió o rechazó, con valores antes/después. Filtra por herramienta, evento, usuario y fechas, y exporta a Excel o PDF. (Los cambios hechos a mano están en <em>Historial de cambios</em>, de la sección Administración.)</li>
<li><strong>Biblioteca.</strong> Análisis guardados (★) para consultarlos o compararlos con uno nuevo.</li>
<li><strong>Uso de la IA.</strong> Medidor: cuántas llamadas hizo cada herramienta al modelo, con cuál modelo y cuánto tardó (por herramienta, modelo y día). Lo informa la app de escritorio.</li>
</ul>
<h4>Aprobar por lotes, niveles de riesgo y revertir por tanda</h4>
<ul>
<li><strong>Niveles de riesgo.</strong> Al pie de <em>Propuestas</em>, elige para cada tipo de cambio si <em>pide permiso</em> (mover fechas, relaciones, avance… se acepta una por una y con confirmación) o es <em>simple</em> (renombrar, vincular normativa… se acepta con un clic).</li>
<li><strong>Aprobación por lotes.</strong> En <em>Propuestas</em>, el botón <em>Aceptar todas las de bajo riesgo</em> aplica solo las simples que ves con los filtros; filtra por <em>Partida</em> para aceptar solo las de una. Las que piden permiso nunca entran.</li>
<li><strong>Revertir por tanda.</strong> En <em>Auditoría de la IA</em>, <em>Revertir lo que hizo la IA</em>: elige desde qué hora (hoy, última hora, 24 h o una fecha), revisa la lista y revierte lo marcado. Cada reversión queda en la auditoría.</li>
</ul>
<h4>Decidir una propuesta ②③</h4>
<ol>
<li>Abre la propuesta para ver el <em>por qué</em> y el <em>antes / después</em>.</li>
<li>Pulsa <strong>✔ Aceptar</strong> para aplicarla. El sistema guarda el valor anterior.</li>
<li>Pulsa <strong>✖ Rechazar</strong> si no procede; queda registrado y no vuelve a molestarte.</li>
<li>Si te arrepientes, en <em>Auditoría de la IA</em> usa <strong>Revertir</strong> sobre esa entrada.</li>
</ol>
<p class="manual-nota">En la app de escritorio, <em>Análisis inteligente</em> muestra un resumen gráfico de propuestas pendientes por herramienta y severidad, junto con el historial.</p>
HTML],

['id' => 'ia-nuevas', 'despues' => 'centro-ia', 'titulo' => 'IA: predicción, RFI, contradicciones y calidad', 'icono' => 'fa-brain', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Cuatro herramientas más en el menú de la app de escritorio (se ejecutan como los demás informes de IA). Todas calculan las cifras en el servidor; la IA solo redacta o juzga, y todas funcionan sin IA con una versión más simple.</p>
<ul>
<li><strong>Predicción de atrasos</strong> (Planeación): da un puntaje de 0 a 100 a cada actividad pendiente según avance contra lo esperado, inicio vencido, predecesoras atrasadas, restricciones, material sin submittal aprobado, RFI abiertos, punch grave y permisos; estima los días de atraso probables y la fecha de fin de la obra con el SPI. Cada puntaje muestra sus razones. Es una estimación para saber dónde mirar primero.</li>
<li><strong>Redactar un RFI</strong> (Campo): describes el problema (también dictado) y eliges la actividad; redacta asunto, antecedentes y pregunta, y el sistema agrega fechas, sucesoras, materiales, normativa relacionada y una fecha límite sugerida. Al <em>aprobar</em> el informe se crea el RFI abierto.</li>
<li><strong>Contradicciones entre documentos</strong> (Documentos): compara las oraciones con medidas de los documentos de <em>Normativa y especificaciones</em> (especificaciones, normas y planos en PDF o Word) y del catálogo de materiales; el sistema encuentra los datos que se dicen distinto (con conversión pulgadas↔mm, español↔inglés) y la IA decide cuáles son contradicciones reales. Las confirmadas pueden volverse RFI.</li>
<li><strong>Pruebas de calidad de la IA</strong> (Resultados y control): corre casos fijos con el modelo elegido y da una nota por caso; en <em>Centro de IA → Calidad de la IA</em> se comparan los modelos y se ve si alguno empeora. Corre la prueba con cada modelo que quieras comparar.</li>
</ul>
HTML],

['id' => 'programador-ia', 'despues' => 'centro-ia', 'titulo' => 'Programador de actividades (IA)', 'icono' => 'fa-wand-magic-sparkles', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Revisa tu programa y propone cómo <strong>acortarlo</strong>: qué actividades pueden empezar antes, cuáles pueden hacerse en paralelo y qué las frena. Se ejecuta desde la app de escritorio (<em>Herramientas IA → Planeación → Programador de actividades</em>) y los resultados se consultan también en la web, en <strong>Programador de actividades</strong> del menú del proyecto.</p>
[[fig:programador]]
<h4>Cómo funciona ①②③</h4>
<ol>
<li><strong>Sin IA.</strong> El sistema lee las actividades, descarta las ya iniciadas, terminadas e hitos, las clasifica (administrativas u operativas) y las empaca por partida.</li>
<li><strong>La IA analiza cada paquete</strong> y devuelve solo lo que propone cambiar: predecesoras, paralelismos y obstáculos.</li>
<li><strong>Integración y revisión.</strong> Otra llamada relaciona paquetes entre sí y una última revisa el conjunto: inconsistencias, duraciones dudosas, riesgos y datos faltantes.</li>
</ol>
<p class="manual-nota">Las <strong>fechas las calcula el sistema</strong> con el calendario y la ruta crítica, no la IA. Lo que ya tiene avance no se mueve.</p>
<h4>Usar la propuesta ④</h4>
<ul>
<li>En la página <strong>Programador</strong> verás cada propuesta con las partidas que difieren, el impacto por partida, la <em>Curva S</em> actual contra la propuesta, las <em>mejoras sugeridas</em> y los <em>obstáculos detectados</em> (materiales sin comprar, submittals, restricciones).</li>
<li>Puedes <strong>guardar</strong> propuestas (si no, se conservan las últimas 10), <strong>compararlas</strong> entre sí y <strong>exportarlas</strong>.</li>
<li>La propuesta es una <em>simulación</em>: no modifica tus actividades. Decide tú qué llevar al programa real.</li>
</ul>
HTML],

['id' => 'inconsistencias', 'despues' => 'programador-ia', 'titulo' => 'Inconsistencias de proyecto (IA)', 'icono' => 'fa-magnifying-glass-chart', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Forma, junto con <strong>Análisis de materiales</strong>, la herramienta <strong>Revisión de datos</strong> del menú (dos pestañas). Busca errores y contradicciones en los datos del proyecto, por ejemplo una actividad que termina antes de empezar, un avance que no coincide con el estatus o una dependencia incumplida. Está en el menú del proyecto como <strong>Inconsistencias de proyecto</strong> y en la app de escritorio dentro de <em>Calidad de datos</em>.</p>
[[fig:inconsistencias]]
<h4>Dos fuentes de hallazgos</h4>
<ol>
<li><strong>Reglas del sistema (sin IA) ①.</strong> El botón <em>Revisar ahora (sin IA)</em> corre al instante decenas de verificaciones: fechas, estatus contra avance, hitos, días no hábiles, dependencias, partidas desactualizadas, materiales, restricciones, submittals, reportes y duplicados.</li>
<li><strong>Análisis con IA.</strong> Desde la app de escritorio, la IA lee la bitácora y los reportes de cada partida y compara nombres entre sí para encontrar lo que las reglas no ven.</li>
</ol>
<h4>Atender un hallazgo ②③</h4>
<ol>
<li>Filtra por estado, severidad, categoría o quién lo detectó.</li>
<li>Cada hallazgo trae una <strong>solución propuesta</strong> de una lista cerrada: nota, ajustar fechas, sincronizar estatus, ajustar progreso, recalcular partida, marcar comprado, resolver restricción o quitar dependencia.</li>
<li><strong>Aceptar</strong> aplica la solución (se guarda el valor anterior y se puede revertir). <strong>Rechazar</strong> lo descarta. <em>Ver actividad →</em> te lleva al dato para decidir.</li>
</ol>
<p>Cada análisis se guarda como una <strong>corrida</strong>. Si no la guardas con nombre, solo se conservan las últimas 5.</p>
HTML],

['id' => 'analisis-materiales', 'despues' => 'inconsistencias', 'titulo' => 'Análisis de materiales y materiales inteligentes', 'icono' => 'fa-boxes-stacked', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Cruza el programa con el catálogo de materiales para avisarte antes de que la falta de material detenga una actividad. Es la segunda pestaña de <strong>Revisión de datos</strong> en el menú del proyecto.</p>
[[fig:materiales-ia]]
<h4>Cómo leerlo ①②③</h4>
<ul>
<li>El resumen superior cuenta los materiales por estado: <strong>compra vencida</strong>, <strong>sin fecha</strong>, <strong>tarde</strong>, con <strong>submittal</strong> pendiente, <strong>a tiempo</strong> o <strong>en sitio</strong>.</li>
<li>La lista muestra cada material, cuándo se necesita (inicio de la actividad) y su estado. A la derecha ves la <strong>acción sugerida</strong>, como <em>Marcar comprado</em> o dejar una nota.</li>
<li>Aplica lo mismo que en inconsistencias: aceptas o rechazas; lo aceptado se puede revertir. Hay un enlace directo al <strong>MSR</strong> para ir a corregir la compra.</li>
</ul>
<h4>Materiales inteligentes</h4>
<p>Dentro de <em>Calidad de datos</em> en la app de escritorio, esta pestaña <strong>verifica cada material contra la normativa</strong> que cargaste (por ejemplo, que la resistencia o la marca cumplan la especificación) y deja un veredicto sugerido. Tú confirmas el resultado final en <em>Revisiones</em>.</p>
HTML],

['id' => 'ensamblador', 'despues' => 'analisis-materiales', 'titulo' => 'Ensamblador de documentos', 'icono' => 'fa-puzzle-piece', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Convierte documentos en datos del proyecto: sube un <strong>PDF, PPTX, DOCX, ODT o TXT</strong> (hasta 50 MB) y la IA propone <strong>partidas, actividades, materiales, submittals, dependencias, fechas de contrato, festivos, restricciones, contactos, responsables, cantidades, compromisos de minuta y documentos de normativa</strong>. Está en la app de escritorio: <em>Herramientas IA → Ensamblador → Ensamblador de documentos</em>.</p>
[[fig:ensamblador]]
<h4>Paso a paso</h4>
<ol>
<li><strong>Sube el archivo ①.</strong> El sistema lo revisa, lo guarda en el <em>Archivo</em> del proyecto y extrae su texto por páginas. Si ya lo habías subido, te avisa del duplicado.</li>
<li><strong>Dile qué buscar ②.</strong> Usa los botones de selección rápida (partidas nuevas, modificar existentes, materiales, submittal nuevo o agregar a uno existente, dependencias, contrato, responsables, cantidades, minuta, normativa, o <em>✨ Lo que encuentres</em>) o escribe tu instrucción en el chat. Si escribes una pregunta, te la contesta con los fragmentos más relevantes del documento y no crea propuesta.</li>
<li><strong>Revisa la propuesta ③.</strong> Cada cambio trae una casilla. La coincidencia de nombres con lo que ya existe se indica: <em>alta</em> (marcada), <em>dudosa</em> (sin marcar, con aviso). Un material que ya existe solo se <strong>completa</strong> (campos vacíos), nunca se sobrescribe. Desmarca lo que no quieras.</li>
<li><strong>Aplica ④.</strong> Se aplica en orden (partidas, materiales, submittals, festivos, dependencias, restricciones, fechas de contrato, normativa, contactos, responsables, cantidades y compromisos). Cada ítem va en su propia transacción: si uno falla, los demás siguen. Después puedes <strong>Deshacer</strong>.</li>
</ol>
<h4>Detalles que conviene saber</h4>
<ul>
<li>Las <strong>partidas nuevas</strong> toman el siguiente código (<code>N.0</code>) y sus fechas se calculan a continuación del programa con el calendario; si el documento no trae duración se usa 1 día y se avisa.</li>
<li>Las <strong>dependencias</strong> que formarían un ciclo se rechazan solas; no se reprograma nada hasta que lo hagas desde el modo de dependencias.</li>
<li>Las <strong>cantidades con importe</strong> de <em>todas</em> las actividades hacen que la Curva S pese el avance por importe en lugar de por duración.</li>
<li><strong>Segunda pasada de revisión</strong> (casilla activa por defecto): la IA vuelve a revisar cada fragmento para quitar lo no respaldado. Tarda el doble; desactívala si tienes prisa.</li>
<li>No lee PDF escaneados (sin OCR) ni <code>.ppt</code> binario (guárdalo como <code>.pptx</code>).</li>
<li>Lo guardado se consulta en <strong>Datos de obra</strong>; tras aplicar partidas se ofrece <em>Ordenar y renumerar</em>.</li>
</ul>
HTML],

['id' => 'ordenar', 'despues' => 'ensamblador', 'titulo' => 'Ordenar y renumerar el programa', 'icono' => 'fa-arrow-down-1-9', 'roles' => $admin, 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Reordena partidas y actividades y, si quieres, vuelve a numerar los códigos (<code>1.0, 2.0…</code> y <code>1.1, 1.2…</code>) sin tocar a mano cada una. Está en el botón <strong>Ordenar y renumerar</strong> de la página del Programa. No usa IA.</p>
[[fig:ordenar]]
<ol>
<li><strong>Mueve ①:</strong> arrastra con el asa ☰ o usa las flechas ▲ ▼. También puedes pasar una actividad a otra partida.</li>
<li><strong>Revisa la vista previa ②:</strong> con la casilla <em>Renumerar</em> activa, a la derecha de cada fila ves el código nuevo.</li>
<li><strong>Guarda ③.</strong> Todo se aplica en una transacción y las fechas de las partidas se recalculan. Con <em>Deshacer ese orden</em> regresas al orden anterior (mientras no se hayan agregado o quitado partidas o actividades).</li>
<li>El icono <strong>ⓘ</strong> de cada fila muestra fechas, avance, dependencias, materiales, evidencias y cuántas veces aparece ese código escrito como texto en bitácora, láminas y minutas.</li>
</ol>
<p class="manual-nota">Lo ligado por identificador (dependencias, materiales, evidencias, láminas) muestra el código nuevo solo. Los códigos escritos como <em>texto libre</em> en bitácora, notas y minutas <strong>no cambian</strong>, porque son registros históricos.</p>
<h4>Ordenar por fechas (app de escritorio, con IA)</h4>
<p>En <em>Herramientas IA → Ensamblador → Ordenar y renumerar por fechas</em> el sistema calcula el orden por fecha de inicio y la IA solo <strong>explica</strong> cada cambio y advierte de problemas (actividades sin fecha, predecesoras que empiezan después de su sucesora). Cada grupo trae las casillas <em>Ordenar</em> y <em>Renumerar</em>; si el programa cambió desde que se calculó, la aplicación se rechaza y debes recalcular. Se deshace igual que el orden manual.</p>
HTML],

['id' => 'cambios', 'despues' => 'ordenar', 'titulo' => 'Órdenes de cambio y resumen diario', 'icono' => 'fa-file-circle-exclamation', 'roles' => ['admin', 'cliente'], 'herr' => 'cambios', 'cuerpo' => <<<'HTML'
<p>Registra los cambios de alcance que afectan costo o tiempo y justifica con ellos las desviaciones del programa. Está en el menú del proyecto como <strong>Órdenes de cambio</strong>.</p>
[[fig:cambios]]
<h4>Crear una orden</h4>
<ol>
<li>Pulsa <strong>+ Nueva orden</strong> y llena: fecha*, título*, descripción o justificación, solicitante, <strong>impacto en costo</strong> y <strong>en días</strong>, y la actividad principal afectada.</li>
<li>El <strong>estado ①</strong> avanza así: Borrador → Solicitada → Aprobada (o Rechazada) → Ejecutada → Cerrada. Una orden solicitada, aprobada, ejecutada o cerrada que apunta a una actividad <em>justifica</em> su desviación en la comparación de líneas base.</li>
</ol>
<h4>Renglones ②</h4>
<p>Cada orden se desglosa en renglones con <em>tipo</em> (instalación temporal, instalación, reubicación, demolición, adición, modificación), descripción, ubicaciones, cantidad, fecha prevista, actividad y material. Cada renglón avanza de <em>pendiente</em> a <em>ejecutado</em> y luego a <em>retirado</em> (si es temporal) o <em>verificado</em>.</p>
<h4>Retiros pendientes ③</h4>
<p>Una <strong>instalación temporal</strong> siempre exige retiro: mientras esté <em>ejecutada</em> sin retirarse aparece como <strong>retiro pendiente</strong>, marcado como <em>vencido</em> o <em>sin programar</em>, en la pantalla y en el resumen diario.</p>
<h4>Materiales contra cronograma</h4>
<p>El botón <strong>Cruce de materiales</strong> compara cuándo se necesita cada material (inicio de la actividad o fecha prevista del renglón) con su compra, entrega y submittal, y marca: compra vencida, sin fecha, tarde, submittal, a tiempo o en sitio.</p>
<h4>Resumen ejecutivo de hoy</h4>
<p>El botón <strong>Resumen ejecutivo de hoy</strong> (solo administrador) genera un informe con semáforo <em>crítico / atención / en orden</em>. Todas las cifras las calcula el sistema; la IA, si la hay, solo redacta el análisis y las sugerencias. Sin IA se genera igual con texto de plantilla. No modifica datos.</p>
<p>Antes de generarlo eliges dos cosas: el <strong>tipo de informe</strong> — <em>Simple</em> (una página, solo lo esencial) o <em>Detallado</em> (todo) — y el <strong>nivel de detalle</strong> — <em>Básico, Medio, Alto o Máximo</em>, que decide cuántas actividades muestra cada lista (5, 10, 20 ó 40). Los mismos selectores están en <strong>Centro de IA → Informes → Resumen ejecutivo diario</strong> y en la app de escritorio.</p>
<p>Además del avance, la línea base y los cambios del día, el informe trae seis bloques que <strong>siempre aparecen</strong>, con su «Ninguno» cuando no hay nada (así sabes que se revisó):</p>
<ol>
<li><strong>Actividades en riesgo alto</strong>: las que el sistema puntúa de 0 a 100 y pasan de 60 en los próximos 14 días, con sus razones (no inició, avance por debajo, material sin submittal…).</li>
<li><strong>En ruta crítica</strong>: las que, si se atrasan, atrasan la obra entera (holgura cero, aún sin terminar). Necesita las dependencias activadas.</li>
<li><strong>Restricciones activas</strong>: lo que todavía frena cada actividad (permiso, aprobación, información), con tipo y días de antigüedad.</li>
<li><strong>Contradicciones entre documentos</strong>: lo que dicen distinto plano y especificación. No se analiza todos los días: se toma el último informe <em>Contradicciones</em> de hasta 7 días.</li>
<li><strong>Punch list abierto</strong>: los pendientes de calidad, marcando los críticos y los vencidos.</li>
<li><strong>Causas de atraso</strong>: por qué se atrasó cada actividad (material, submittal, restricción, predecesora, clima, bitácora), agrupadas por causa.</li>
</ol>
<p>Todo lo que pide acción se suma solo a <strong>Pendientes de hoy</strong>: levantar una restricción, resolver un punch crítico vencido, escalar una contradicción severa o revisar una actividad de riesgo alto.</p>
HTML],

['id' => 'lineas-base', 'despues' => 'cambios', 'titulo' => 'Líneas base y comparación', 'icono' => 'fa-timeline', 'roles' => ['admin', 'cliente'], 'herr' => 'cambios', 'cuerpo' => <<<'HTML'
<p>Una <strong>línea base</strong> es una foto fija del programa (actividades, fechas, ruta crítica y materiales) en un momento dado, que luego no cambia. Sirve para saber cuánto se ha movido el proyecto. Está en <strong>Líneas base</strong> (desde Órdenes de cambio).</p>
[[fig:lineas-base]]
<ol>
<li><strong>Captura</strong> una línea base cuando el programa quede aprobado (contrato, reprogramación mayor). Dale un nombre y notas. Sobrevive aunque después borres actividades o materiales.</li>
<li>Pulsa <strong>Comparar con hoy ①</strong>. Cada actividad se marca como <em>igual</em>, <em>movida</em>, <em>nueva</em> o <em>eliminada</em>, con la diferencia en <strong>días hábiles</strong>.</li>
<li>La <strong>barra gris ②</strong> es lo planeado y la de color lo actual. La causa de cada desviación aparece como <strong>③</strong>: <em>orden de cambio</em>, <em>material</em> o <em>sin justificar</em>.</li>
<li>Revisa también <em>Cambios en la ruta crítica</em>, <em>Materiales cuyas fechas cambiaron</em> y, si cargaste fechas de contrato, <em>Contra el contrato</em>.</li>
</ol>
<p class="manual-nota">La ruta crítica solo se compara si las dependencias de obra del proyecto están encendidas.</p>
HTML],

['id' => 'datos-obra', 'despues' => 'lineas-base', 'titulo' => 'Datos de obra', 'icono' => 'fa-file-contract', 'roles' => ['admin', 'cliente', 'visitante'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Concentra lo que el <em>Ensamblador</em> extrajo de contratos, minutas y especificaciones y lo pone a la vista. Está en el menú del proyecto como <strong>Datos de obra</strong>.</p>
[[fig:datos-obra]]
<ul>
<li><strong>Alertas ①.</strong> Un banner marca como <em>crítico</em> si el programa termina después de la fecha del contrato, si el contrato ya venció sin terminar o si faltan 7 días o menos; como <em>aviso</em> si faltan 30 o menos. Las primeras alertas también salen en la parte superior del Programa.</li>
<li><strong>Fechas contractuales ②.</strong> Inicio, fin e hitos con su penalización y el texto de origen, comparados con el programa.</li>
<li><strong>Responsables por actividad ③.</strong> Una persona responsable por actividad.</li>
<li><strong>Cantidades de obra ④.</strong> Clave, concepto, cantidad, unidad e importe, con total. Si <em>todas</em> las actividades con duración tienen importe, la Curva S pesa el avance por importe.</li>
<li><strong>Directorio de contactos.</strong> Cargo, empresa, teléfono y correo (solo lo ve quien gestiona el proyecto).</li>
</ul>
HTML],

['id' => 'revisiones', 'despues' => 'datos-obra', 'titulo' => 'Revisiones', 'icono' => 'fa-clipboard-check', 'roles' => ['admin', 'cliente', 'visitante'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Lleva el control de qué materiales, actividades y <strong>RFI</strong> ya revisaste y qué herramientas de IA están al día. Está en el menú del proyecto como <strong>Revisiones</strong>; un resumen corto aparece también en Inicio.</p>
[[fig:revisiones]]
<h4>Veredictos ①</h4>
<table class="tabla manual-tabla">
<thead><tr><th>Marca</th><th>Significa</th></tr></thead>
<tbody>
<tr><td>✅</td><td>Correcto.</td></tr>
<tr><td>⚠</td><td>Requiere revisión.</td></tr>
<tr><td>❌</td><td>No cumple.</td></tr>
<tr><td>⚪</td><td>Sin evaluar.</td></tr>
<tr><td>⟳</td><td>Cambió después de revisarse: el dato se modificó y conviene revisarlo otra vez.</td></tr>
</tbody>
</table>
<p>Pulsa el chip de un material o actividad (en el catálogo y en la tabla del programa; el administrador también lo ve en Normativa) y elige el veredicto; queda guardado quién y cuándo, con una nota opcional. Pueden marcar el administrador y los clientes con acceso; el visitante solo ve. Todo cambio queda en la pestaña <em>Historial</em>.</p>
<h4>Herramientas de IA ②</h4>
<p>Cada herramienta muestra si está <strong>al día</strong>, <strong>desactualizada</strong> (los materiales o actividades cambiaron después de su último análisis) o <strong>sin ejecutar</strong>. Esa misma marca aparece junto a la herramienta en el menú de la app de escritorio.</p>
<p class="manual-nota">La IA propone y tú decides: el veredicto de Revisiones es independiente de la verificación automática de Materiales inteligentes.</p>
HTML],

['id' => 'punch', 'despues' => 'revisiones', 'titulo' => 'Punch list', 'icono' => 'fa-clipboard-check', 'roles' => ['admin', 'cliente', 'visitante'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Lista de pendientes de calidad y cierre: detalles por corregir antes de entregar. Está en el menú del proyecto como <strong>Punch list</strong>.</p>
[[fig:punch]]
<ol>
<li><strong>Registra ①</strong> cada pendiente con ubicación, categoría, severidad, responsable, contratista, fecha límite, actividad relacionada, descripción y una <strong>foto «antes»</strong>.</li>
<li>Para recorrer un área, usa <strong>Inspección</strong>: una lista de verificación por ubicación que registra todos los hallazgos de una vez.</li>
<li>Filtra por estado, ubicación o contratista y descarga el <strong>PDF</strong> (general o por contratista) para repartirlo.</li>
<li><strong>Úsalo en otros documentos:</strong> el Punch list se puede insertar como tabla en <strong>vPlan</strong> (<em>Tablas adicionales › Nueva tabla › Punch list</em>), en el <strong>Redactor</strong> (<em>Insertar tabla › Punch list</em>) y en el <strong>Editor</strong> (pestaña Símbolos › <em>Punch list</em>). En los tres eliges <strong>qué ver</strong> (columnas, fotos antes/después, estado, severidad, contratista) con el botón «Qué ver», y la tabla se actualiza sola con los pendientes. En vPlan además puedes cambiar estado, severidad, fecha y responsable desde la lámina. En el Editor las fotos (hasta 20 por tabla) se colocan en sus celdas.</li>
<li><strong>Cierra ③:</strong> abre el pendiente, sube la <strong>foto «después»</strong> y cambia su estado.</li>
</ol>
HTML],

['id' => 'seguridad', 'despues' => 'punch', 'titulo' => 'Seguridad y permisos', 'icono' => 'fa-helmet-safety', 'roles' => ['admin', 'cliente', 'visitante'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Controla los permisos de trabajo de alto riesgo y los incidentes. Está en el menú del proyecto como <strong>Seguridad y permisos</strong>.</p>
[[fig:seguridad]]
<ul>
<li><strong>Permisos exigidos ①.</strong> La pantalla lista los permisos que piden las actividades de los <strong>próximos 14 días</strong> (altura, trabajo en caliente, espacios confinados…).</li>
<li><strong>Solicitar un permiso ②.</strong> Elige el tipo, la actividad, área, responsable, contratista, desde y hasta, descripción del trabajo y riesgos; el sistema propone las <em>medidas del tipo elegido</em> y puedes añadir otras, una por línea. Se descarga en <strong>PDF</strong>. Quien gestiona el proyecto puede verificarlo o rechazarlo.</li>
<li><strong>Incidentes ③.</strong> Registra fecha, severidad, qué pasó y acciones tomadas; ciérralo cuando se resuelva.</li>
</ul>
<h4>Permisos y clima</h4>
<p>Si el pronóstico del proyecto marca lluvia o viento fuerte en los próximos 7 días y hay permisos de trabajo vigentes (aprobados o por aprobar) de altura, izaje, excavación, eléctrico o en caliente, aparece la tabla <em>Permisos en días de lluvia o viento</em> para que decidas si reprogramas o suspendes. La propuesta de «día no hábil por clima» también menciona cuántos permisos caen ese día.</p>
HTML],

['id' => 'normativa', 'despues' => 'seguridad', 'titulo' => 'Normativa y especificaciones', 'icono' => 'fa-scale-balanced', 'roles' => ['admin'], 'herr' => null, 'cuerpo' => <<<'HTML'
<p>Biblioteca de normas y especificaciones del proyecto, con búsqueda y vínculos a las actividades y materiales a los que aplican. Está en el menú del proyecto como <strong>Normativa y especificaciones</strong>. <strong>Este módulo es solo del administrador</strong>: el cliente y el visitante no lo ven en el menú ni en la búsqueda, y no pueden abrir sus páginas.</p>
[[fig:normativa]]
<ol>
<li><strong>Carga ①</strong> uno o varios PDF o Word. El título, código y tipo se detectan del contenido.</li>
<li><strong>Busca ②</strong> en todos los documentos; la búsqueda cruza español e inglés. Dentro de un documento también puedes buscar y ver cada cláusula.</li>
<li><strong>Vincula ③.</strong> El sistema (o la IA, desde la app de escritorio) <em>propone</em> a qué actividades y materiales aplica cada cláusula. Tú marcas <em>Aplica</em> o <em>No aplica</em>, o <em>Desvincula</em>. Nada queda vinculado sin tu confirmación. Cada propuesta muestra el <strong>documento, la sección, la cláusula y la página</strong> (con enlace al PDF en esa página) y el requisito resumido.</li>
<li><strong>Por material y actividad ④</strong> (botón junto a <em>Vínculos</em>): una lista por elemento con toda la normativa que le aplica o se le propone. Filtra por material/actividad, estado, «solo sin normativa aplicada» o texto; abre la ficha del elemento, acepta todas sus propuestas de una vez o pide <em>Sugerir de los documentos</em>.</li>
<li>Descarga el <strong>PDF</strong> del documento o de una cláusula. El informe de «Aplicar normativa y especificaciones» (app de escritorio) trae la cita completa (documento, sección, cláusula, página) y una lista por elemento.</li>
</ol>
HTML],

['id' => 'tipo-obra', 'despues' => 'normativa', 'titulo' => 'Tipo de obra y Mapa de avance', 'icono' => 'fa-layer-group', 'roles' => ['admin', 'cliente', 'visitante'], 'herr' => null, 'cuerpo' => <<<'HTML'
<h4>Tipo de obra</h4>
<p>Identifica qué clase de obra es tu proyecto y muestra qué aporta: <em>secuencia típica</em>, <em>submittals habituales</em>, <em>materiales críticos y plazo típico</em> y el <em>contexto</em> que se entrega a la IA para que sus propuestas sean más acertadas. Elige el tipo y pulsa <strong>Guardar</strong>, o usa <em>Identificar</em> para que el sistema lo deduzca del programa. En <em>Registro de tipos de obra</em> están todos los disponibles: videovigilancia, control de acceso, cableado, edificación, vivienda unifamiliar, edificio multifamiliar o comercial, nave industrial, remodelación y urbanización. En un proyecto sin partidas puedes crear una <strong>base de partidas y actividades</strong> con duraciones típicas.</p>
<h4>Mapa de avance</h4>
<p>Traduce el <strong>color de las láminas</strong> del vPlan (lo que pintas sobre los planos) a <strong>porcentaje de avance por actividad</strong>. En la tabla <em>Cómo se traduce el color</em> defines el porcentaje de cada color; la sección <em>Avance por actividad según los planos</em> compara el avance real contra el pintado y <strong>propone cambios</strong> cuando la diferencia supera el umbral que fijes. Tú aceptas o no cada uno.</p>
HTML],

];
