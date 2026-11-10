<?php
/**
 * Curso del administrador: módulos, lecciones, ejercicios y guiones de enseñanza.
 * Cada lección: id, titulo, cuerpo (HTML fijo, admite [[fig:nombre]]), ejercicio, ensenar y video (opcional).
 * El curso se renderiza en views/manual/curso.php y solo lo ve el administrador.
 */
return [

[
    'id' => 'm0',
    'titulo' => 'Módulo 0 · Cómo usar este curso',
    'icono' => 'fa-graduation-cap',
    'objetivo' => 'Entender el método: primero aprender, luego practicar en una obra real y al final enseñarlo a un cliente.',
    'duracion' => '15 min',
    'lecciones' => [
        [
            'id' => 'm0-l1',
            'titulo' => 'Aprender, practicar y enseñar',
            'cuerpo' => <<<'HTML'
<p>Este curso no está pensado para leerlo de corrido. Está pensado para <strong>hacerlo</strong>: en cada lección vas a encontrar pasos, un ejercicio corto y una forma de explicárselo a un cliente.</p>
<h4>El método de 4 pasos</h4>
<ol>
<li><strong>Ver:</strong> lee la lección y ubica la herramienta en el menú.</li>
<li><strong>Hacer:</strong> repite el ejercicio en el proyecto demo o en una obra de prueba.</li>
<li><strong>Repetir:</strong> hazlo una segunda vez sin mirar los pasos. Ahí se aprende.</li>
<li><strong>Enseñar:</strong> explícaselo a otra persona en 5 minutos. Si puedes enseñarlo, ya lo dominas.</li>
</ol>
<p>Al final de cada módulo hay una lección de cierre con el guion para capacitar al cliente. No avances al siguiente módulo hasta poder hacer el ejercicio sin ayuda.</p>
HTML,
            'ejercicio' => 'Abre el proyecto demo y localiza cinco herramientas del menú. No cambies nada: solo ubícalas.',
            'ensenar' => 'Explica al cliente que la plataforma se aprende usándola con su propia obra, no con un manual teórico.',
        ],
        [
            'id' => 'm0-l2',
            'titulo' => 'Los roles y el mapa de la plataforma',
            'cuerpo' => <<<'HTML'
<p>Antes de tocar una herramienta, entiende quién puede hacer qué:</p>
<table class="tabla manual-tabla">
<thead><tr><th>Rol</th><th>Qué puede hacer</th></tr></thead>
<tbody>
<tr><td><strong>Administrador</strong></td><td>Todo: organizaciones, planes, usuarios, proyectos, configuración y todas las herramientas.</td></tr>
<tr><td><strong>Propietario</strong></td><td>Dueño de la organización: plan, facturación, equipo y todas las obras de su empresa.</td></tr>
<tr><td><strong>Gestor</strong></td><td>Crea y edita obras de la organización; no factura ni administra equipo.</td></tr>
<tr><td><strong>Colaborador</strong></td><td>Edita solo en las herramientas que le marquen; nunca borra.</td></tr>
<tr><td><strong>Visitante</strong></td><td>Solo lectura de los proyectos asignados.</td></tr>
</tbody>
</table>
<p>El administrador ve el menú completo. El cliente ve su organización y sus obras. El visitante ve solo lo que le asignaron.</p>
<p>En la parte de abajo de este curso tienes el <strong>mapa de todas las herramientas</strong>, agrupadas por sección, con lo que hace cada una y el módulo donde se enseña.</p>
HTML,
            'ejercicio' => 'Entra a /usuarios y /organizaciones. Identifica un propietario, un gestor, un colaborador y un visitante.',
            'ensenar' => 'Dibuja los cuatro roles en una hoja y explica quién captura, quién decide y quién solo consulta.',
        ],
        [
            'id' => 'm0-l3',
            'titulo' => 'Cómo está armado el curso',
            'cuerpo' => <<<'HTML'
<p>El curso sigue el orden real de una obra:</p>
<ol>
<li><strong>Preparar</strong> la organización, el plan, los usuarios y el proyecto.</li>
<li><strong>Planear</strong>: programa, dependencias, lookahead, vPlan y curvas.</li>
<li><strong>Ejecutar</strong>: reporte diario, bitácora, restricciones, calidad y seguridad.</li>
<li><strong>Abastecer y controlar</strong>: materiales, costos, cambios y líneas base.</li>
<li><strong>Documentar</strong>: archivo, redactor, planos, comparador y anotador.</li>
<li><strong>Administrar</strong>: contratos, equipo, cierre, marca e integraciones.</li>
<li><strong>Usar la IA</strong> con criterio y auditoría.</li>
<li><strong>Capacitar</strong> al cliente y cerrar el piloto.</li>
</ol>
<p>Marca cada lección al terminarla. El avance se guarda en tu navegador; no se comparte con nadie.</p>
HTML,
            'ejercicio' => 'Recorre el índice del curso y elige el módulo que más necesitas hoy. Empieza por ahí si ya conoces lo básico.',
            'ensenar' => 'Muestra al cliente el índice y dile que el curso es la ruta para no perderse entre tantas herramientas.',
        ],
    ],
],

[
    'id' => 'm1',
    'titulo' => 'Módulo 1 · Preparar la plataforma y el proyecto',
    'icono' => 'fa-rocket',
    'objetivo' => 'Dejar lista la organización, el plan, los usuarios, el proyecto y el calendario antes de capturar una sola actividad.',
    'duracion' => '60 min',
    'lecciones' => [
        [
            'id' => 'm1-l1',
            'titulo' => 'Organización, plan, límites y almacenamiento',
            'cuerpo' => <<<'HTML'
<p>La <strong>organización</strong> es la empresa que contrata. Ahí viven el plan, el trial, los usuarios, el almacenamiento y la facturación.</p>
<h4>Alta de una empresa</h4>
<ol>
<li>Entra a <strong>Organizaciones y planes</strong> (solo admin) y pulsa <em>Nueva organización</em>.</li>
<li>Captura nombre, razón social, RFC, correo de facturación y plan. La organización se crea vacía.</li>
<li>Abre su ficha y en <em>Clientes</em> pulsa <strong>Agregar cliente propietario</strong>: se crea el usuario dueño, que administrará su equipo y sus obras.</li>
<li>En <em>Visitantes y colaboradores</em> agrega al equipo de la empresa. El visitante solo ve las obras y herramientas asignadas; el colaborador edita en las herramientas marcadas y nunca borra.</li>
</ol>
<p>Un cliente pertenece a una sola empresa y una empresa tiene un solo propietario (para cambiarlo se usa <em>Transferir propiedad</em>). El tope de clientes, visitantes y almacenamiento sale del plan.</p>
<h4>Qué revisar</h4>
<ol>
<li>Confirma el plan: Obra, Pro, Constructora, Corporativo o Legacy.</li>
<li>Revisa el estado: prueba, activo, suspendido o cancelado.</li>
<li>Revisa las fechas: inicio, fin, trial y gracia.</li>
<li>Mira el uso: obras activas, asientos, visitantes y almacenamiento total en la ficha.</li>
<li>Si hay un pago, regístralo en <em>Pagos</em> con monto, periodo, método, referencia y UUID del CFDI.</li>
<li>Para dar de baja una empresa sin perder nada, usa <strong>Archivar</strong> (solo si no tiene obras activas). El borrado definitivo solo se permite si está vacía.</li>
</ol>
<p>El plan define cuántas obras activas, cuántos usuarios de edición y cuánto almacenamiento total tiene la organización. Las obras archivadas liberan cupo, pero siguen ocupando disco.</p>
HTML,
            'ejercicio' => 'Abre /organizaciones, revisa el plan de una organización y localiza su uso de obras y almacenamiento.',
            'ensenar' => 'Explica al cliente que el plan no le quita herramientas: le da capacidad y gobierno. Todas las herramientas de obra están en todos los planes.',
            'video' => 'Grabar 2 min: abrir Organizaciones, mostrar plan, estado, uso y el formulario de pago.',
        ],
        [
            'id' => 'm1-l2',
            'titulo' => 'Usuarios, roles, equipo y accesos',
            'cuerpo' => <<<'HTML'
<p>Un usuario mal creado genera problemas de permisos toda la obra. Crea primero al propietario y luego a su equipo.</p>
<ol>
<li>En <strong>Usuarios y Accesos</strong>, pulsa <em>Nuevo Usuario</em>.</li>
<li>Elige el rol: administrador, cliente (propietario o gestor) o visitante (colaborador o visitante).</li>
<li>Si es propietario, define el plan y los datos de la organización.</li>
<li>Si es gestor, elige una organización con plan Pro o superior.</li>
<li>Si es visitante, elige si será colaborador (edita herramientas marcadas) o visitante (solo lectura).</li>
<li>Marca los proyectos a los que tendrá acceso.</li>
<li>Guarda. La contraseña es temporal: se le pedirá cambiarla al entrar.</li>
</ol>
<p>El propietario crea a su equipo en <strong>Mi equipo</strong>. Los visitantes son ilimitados; los asientos de edición dependen del plan.</p>
HTML,
            'ejercicio' => 'Crea un propietario, un gestor y un visitante en una organización de prueba. Entra con cada uno y compara el menú.',
            'ensenar' => 'Regla de oro para el cliente: el propietario decide, el gestor produce y el visitante consulta. Si todos son administradores, nadie controla nada.',
        ],
        [
            'id' => 'm1-l3',
            'titulo' => 'Crear el proyecto desde cero',
            'cuerpo' => <<<'HTML'
<p>El proyecto es el contenedor de todo: programa, campo, materiales, documentos y costos.</p>
<ol>
<li>Entra a <strong>Proyectos</strong> y pulsa <strong>+ Nuevo Proyecto</strong>.</li>
<li>Captura <strong>código</strong> y <strong>nombre</strong> (obligatorios), descripción, ubicación, empresa y contratista.</li>
<li>Define <strong>fecha de inicio</strong> y <strong>fecha de fin</strong>.</li>
<li>El admin elige <strong>propietario</strong> y <strong>organización dueña</strong>.</li>
<li>Marca los usuarios con acceso al proyecto.</li>
<li>Sube logo del contratista, logo del proyecto y carátula si los tienes.</li>
<li>Elige cómo empezar: guiado, lista completa o solo crear.</li>
<li>Guarda. Se crea con la jornada L-V completa, sábado medio día y domingo no laborable.</li>
</ol>
[[fig:pantalla]]
HTML,
            'ejercicio' => 'Crea un proyecto de prueba con fechas reales, un propietario y dos usuarios con acceso.',
            'ensenar' => 'Haz que el cliente cree su propio proyecto contigo al lado. No lo crees tú solo: quien lo crea lo siente suyo.',
        ],
        [
            'id' => 'm1-l4',
            'titulo' => 'Calendario, jornada y festivos',
            'cuerpo' => <<<'HTML'
<p>Si el calendario está mal, todas las fechas del programa estarán mal. Es la primera configuración técnica.</p>
<ol>
<li>Abre <strong>Ajustes del proyecto → Calendario</strong>.</li>
<li>Define el factor de cada día: 1 = día completo, 0.5 = medio día, 0 = no laborable.</li>
<li>Agrega festivos, paros técnicos y días de clima.</li>
<li>Revisa el calendario resultante: debe coincidir con la obra real.</li>
</ol>
<p>Las actividades y las dependencias usan este calendario para calcular fechas de fin. Un festivo que no cargaste se convierte en un atraso inventado.</p>
[[fig:datos-obra]]
HTML,
            'ejercicio' => 'Configura una jornada con sábado medio día y agrega un festivo. Crea una actividad que lo cruce y verifica que se salte.',
            'ensenar' => 'Pregunta al cliente: “¿Trabajan sábado? ¿Qué días no se trabaja en esta obra?”. Con eso armas el calendario en 2 minutos.',
        ],
        [
            'id' => 'm1-l5',
            'titulo' => 'Datos de obra, archivo y plantillas',
            'cuerpo' => <<<'HTML'
<p>Antes de planear, deja la información base ordenada:</p>
<ul>
<li><strong>Datos de obra:</strong> fechas contractuales, responsables, cantidades y contactos.</li>
<li><strong>Archivo:</strong> planos, especificaciones, contratos y permisos, con categoría y revisión.</li>
<li><strong>Plantillas de proyecto:</strong> guarda un proyecto como plantilla para reutilizar partidas, actividades, dependencias y calendario.</li>
<li><strong>Tipo de obra:</strong> plantillas de partidas y actividades por proceso (videovigilancia, edificación, urbanización…).</li>
</ul>
<p>Una plantilla bien hecha ahorra días de captura y hace que todas las obras de la empresa se estructuren igual.</p>
HTML,
            'ejercicio' => 'Sube un plano al Archivo, crea una categoría y guarda el proyecto como plantilla.',
            'ensenar' => 'Enseña al cliente que el Archivo no es una carpeta cualquiera: es la fuente de verdad de planos y contratos, con revisión.',
        ],
    ],
],

[
    'id' => 'm2',
    'titulo' => 'Módulo 2 · Planeación',
    'icono' => 'fa-chart-gantt',
    'objetivo' => 'Construir el programa, calcular la ruta crítica y preparar las juntas semanales con lookahead, Last Planner y vPlan.',
    'duracion' => '120 min',
    'lecciones' => [
        [
            'id' => 'm2-l1',
            'titulo' => 'Programa: partidas, actividades e hitos',
            'cuerpo' => <<<'HTML'
<p>El programa es el corazón de la plataforma. Todo lo demás se alimenta de él.</p>
<ol>
<li>Abre <strong>Programa</strong> y crea las partidas por disciplina o frente.</li>
<li>Dentro de cada partida, agrega actividades con nombre, duración, fecha de inicio y avance inicial.</li>
<li>Usa <strong>hitos</strong> para fechas clave (inicio de obra, entrega, permiso).</li>
<li>Edita el avance en línea y revisa el Gantt por semanas.</li>
<li>Agrega restricciones a las actividades que pueden frenarse.</li>
<li>Si necesitas columnas propias, entra a <strong>Diseño del programa</strong> y configura nombres, orden e indicador.</li>
</ol>
<p>El avance de la partida se pondera por duración; los hitos no cuentan. Así el Programa, el Inicio y el Gantt muestran la misma cifra.</p>
[[fig:programa]]
HTML,
            'ejercicio' => 'Crea una partida con cinco actividades y un hito. Cambia el avance de una actividad y observa el avance de la partida.',
            'ensenar' => 'Empieza con una sola disciplina. Si el cliente ve 200 actividades el primer día, se asusta. Con 15 entiende el valor.',
            'video' => 'Grabar 3 min: crear partida, cinco actividades, un hito y mostrar el Gantt actualizado.',
        ],
        [
            'id' => 'm2-l2',
            'titulo' => 'Dependencias y ruta crítica',
            'cuerpo' => <<<'HTML'
<p>Las dependencias convierten una lista de actividades en un programa que se mueve solo.</p>
<ol>
<li>Abre <strong>Dependencias de obra</strong>.</li>
<li>Relaciona predecesora y sucesora con el tipo correcto: FS (fin-inicio), SS (inicio-inicio), FF (fin-fin) o SF (inicio-fin).</li>
<li>Agrega retraso en días hábiles si aplica.</li>
<li>Revisa la <strong>ruta crítica</strong>, las holguras y los conflictos.</li>
<li>Prueba el modo automático con vista previa y opción de deshacer.</li>
</ol>
<p>La ruta crítica es la cadena que no puede atrasarse sin mover la fecha final. Ahí es donde el cliente debe poner su atención cada semana.</p>
HTML,
            'ejercicio' => 'Encadena tres actividades FS y una SS con retraso. Identifica la ruta crítica y mueve una fecha para ver el conflicto.',
            'ensenar' => 'Explica la ruta crítica como “el camino sin holgura”. Si se atrasa una actividad de ahí, se atrasa la obra.',
        ],
        [
            'id' => 'm2-l3',
            'titulo' => 'Línea de tiempo activa',
            'cuerpo' => <<<'HTML'
<p>La <strong>Línea de tiempo activa</strong> es el Gantt diario para reprogramar con el mouse.</p>
<ol>
<li>Abre <strong>Línea de tiempo activa</strong>.</li>
<li>Agrupa por partida y usa los filtros de estado, restricción y partida.</li>
<li>Arrastra una barra para mover la actividad o sus extremos para cambiar solo inicio o fin.</li>
<li>Usa la rueda del ratón para zoom y el botón central para paneo.</li>
<li>Activa el <strong>icono de actividad</strong> si quieres ver el disco de estado sobre cada barra.</li>
<li>Exporta a PDF u ODS con los filtros actuales.</li>
</ol>
<p>Los cambios se guardan al instante, respetan días hábiles y recalculan duración, partida, materiales y dependencias.</p>
HTML,
            'ejercicio' => 'Mueve una actividad dos días y verifica que la duración en días hábiles se recalcule.',
            'ensenar' => 'Enseña la línea de tiempo como el “tablero de ajedrez” de la obra: mover una barra es mover el plan, no solo un dibujo.',
        ],
        [
            'id' => 'm2-l4',
            'titulo' => 'Lookahead y Last Planner',
            'cuerpo' => <<<'HTML'
<p>El lookahead mira las próximas semanas; Last Planner convierte la semana en compromisos.</p>
<ol>
<li>Abre <strong>Lookahead</strong> y revisa la semana actual más las siguientes.</li>
<li>Marca la columna PTA y las limitaciones o restricciones.</li>
<li>Detecta qué actividades pueden arrancar y cuáles no.</li>
<li>Pasa a <strong>Last Planner</strong> y arma las tarjetas por día y por partida.</li>
<li>Imprime los post-its o proyecta la vista para la junta.</li>
<li>Al cierre de semana, marca el cumplimiento (PPC).</li>
</ol>
<p>El lookahead no es un reporte para el cliente: es la herramienta de la junta interna para liberar lo que frena la obra.</p>
HTML,
            'ejercicio' => 'Prepara el lookahead de la semana y marca tres actividades que no pueden arrancar por restricción.',
            'ensenar' => 'Haz que el residente explique por qué una actividad no puede arrancar. Ahí entiende para qué sirve el lookahead.',
        ],
        [
            'id' => 'm2-l5',
            'titulo' => 'vPlan y láminas para juntas',
            'cuerpo' => <<<'HTML'
<p>vPlan convierte el programa en una lámina visual para la junta de obra.</p>
<ol>
<li>Abre <strong>vPlan</strong> y crea una lámina del tamaño que necesites.</li>
<li>Inserta bloques: plano, tabla del programa, recovery, restricciones, punch list, texto o imágenes.</li>
<li>Entra al <strong>modo diseño</strong> para mover, alinear, usar capas y guías.</li>
<li>Marca zonas del plano y asígnalas a actividades o partidas.</li>
<li>Guarda y exporta la lámina a PDF para imprimir o proyectar.</li>
</ol>
<p>Una buena lámina reemplaza tres reportes y una discusión de media hora.</p>
HTML,
            'ejercicio' => 'Crea una lámina con un plano, una tabla del programa y una tabla de restricciones.',
            'ensenar' => 'Pregunta al cliente qué imprime hoy para la junta. Esa hoja es la primera lámina que debes reconstruir en vPlan.',
        ],
        [
            'id' => 'm2-l6',
            'titulo' => 'Línea de balance, riesgos y recursos',
            'cuerpo' => <<<'HTML'
<p>Cuando la obra se repite por piso, tramo o casa, la línea de balance es más útil que el Gantt.</p>
<ul>
<li><strong>Línea de balance:</strong> define frentes y oficios, calcula el takt, detecta cruces y genera actividades por piso o tramo.</li>
<li><strong>Riesgos:</strong> registra probabilidad, impacto, responsable, plan de acción y fecha. Un riesgo alto marca la actividad “En riesgo”.</li>
<li><strong>Recursos y cuadrillas:</strong> asigna personas y cuadrillas, revisa carga por semana y alertas de sobrecarga.</li>
</ul>
<p>Estas tres herramientas evitan que el programa sea solo fechas: lo conectan con la gente y con lo que puede salir mal.</p>
HTML,
            'ejercicio' => 'Crea dos frentes, calcula el takt y registra un riesgo alto con responsable y fecha.',
            'ensenar' => 'Usa la línea de balance con clientes de vivienda o torres; con obra civil singular, quédate en Gantt y lookahead.',
        ],
        [
            'id' => 'm2-l7',
            'titulo' => 'Curvas, línea base y comparación',
            'cuerpo' => <<<'HTML'
<p>Para saber si la obra va bien, necesitas comparar el plan contra la realidad.</p>
<ol>
<li>Abre <strong>Curvas de productividad</strong> y revisa planeado vs. real por frente.</li>
<li>Revisa la <strong>Curva S</strong> del proyecto: avance acumulado programado y real.</li>
<li>Guarda una <strong>línea base</strong> antes de un cambio importante.</li>
<li>Compara la línea base con el programa actual: actividades movidas, nuevas, eliminadas y ruta crítica.</li>
<li>Registra la causa del atraso: cambio, material o sin justificar.</li>
</ol>
<p>Sin línea base no hay forma de demostrar un atraso. Es la memoria del programa.</p>
[[fig:lineas-base]]
HTML,
            'ejercicio' => 'Guarda una línea base, mueve una actividad y compara. Identifica qué cambió y por qué.',
            'ensenar' => 'Explica al cliente que la línea base es la “foto del plan aprobado”. Sirve para reclamar o justificar cambios, no para castigar.',
        ],
    ],
],

[
    'id' => 'm3',
    'titulo' => 'Módulo 3 · Campo y seguimiento',
    'icono' => 'fa-clipboard-list',
    'objetivo' => 'Registrar lo que pasa en obra, resolver restricciones y documentar calidad y seguridad sin llenar formatos a mano.',
    'duracion' => '120 min',
    'lecciones' => [
        [
            'id' => 'm3-l1',
            'titulo' => 'Parte diario y reporte diario',
            'cuerpo' => <<<'HTML'
<p>El <strong>Parte diario</strong> es la captura de campo: una sola pantalla para el día.</p>
<ol>
<li>Abre <strong>Reporte diario</strong> desde el celular o la computadora.</li>
<li>Registra personal y horas, equipo, materiales, avance de actividades y tiempos muertos.</li>
<li>Agrega fotos y notas; usa la firma si la necesitas.</li>
<li>Si no hay señal, captura en modo sin conexión y sincroniza al volver.</li>
<li>Cierra el día: el sistema actualiza avance, horas de equipo y bitácora.</li>
<li>Genera el PDF para el cliente.</li>
</ol>
<p>El reporte diario es la evidencia que evita discusiones al final del mes.</p>
HTML,
            'ejercicio' => 'Captura un día completo con personal, dos actividades, una foto y un tiempo muerto. Genera el PDF.',
            'ensenar' => 'Haz que el residente capture el primer reporte contigo. Si lo hace una vez, lo repite; si lo haces tú, no lo aprende.',
            'video' => 'Grabar 3 min: captura móvil del parte diario, foto, cierre y PDF.',
        ],
        [
            'id' => 'm3-l2',
            'titulo' => 'Bitácora, minutas y evidencias',
            'cuerpo' => <<<'HTML'
<p>La memoria de la obra se construye con tres piezas:</p>
<ul>
<li><strong>Bitácora:</strong> eventos, acuerdos y compromisos por departamento, con nivel de importancia.</li>
<li><strong>Minutas:</strong> temas, responsables, compromisos y asistentes; PDF listo para enviar.</li>
<li><strong>Evidencias:</strong> fotos de avance ligadas a cada actividad, agrupadas por día.</li>
</ul>
<p>La bitácora no es un diario personal: es el registro que protege a la empresa cuando alguien reclama.</p>
HTML,
            'ejercicio' => 'Registra una junta con tres acuerdos, genera la minuta y sube una evidencia a una actividad.',
            'ensenar' => 'Enseña al cliente a registrar solo lo que tiene consecuencia: acuerdos, cambios, retrasos y riesgos.',
        ],
        [
            'id' => 'm3-l3',
            'titulo' => 'Restricciones, acciones rápidas y punch list',
            'cuerpo' => <<<'HTML'
<p>Lo que frena la obra debe tener nombre, responsable y fecha.</p>
<ol>
<li>Abre <strong>Acciones rápidas</strong> y revisa restricciones, bitácora crítica, minutas y materiales pendientes.</li>
<li>Registra una restricción ligada a la actividad que bloquea.</li>
<li>Asigna responsable y fecha compromiso.</li>
<li>Resuelve la restricción y deja la evidencia.</li>
<li>En <strong>Punch list</strong>, registra pendientes con foto antes y después, ubicación y contratista.</li>
<li>Genera el PDF por contratista para el cierre.</li>
</ol>
[[fig:punch]]
HTML,
            'ejercicio' => 'Crea una restricción, resuélvela y registra un pendiente de punch con foto antes/después.',
            'ensenar' => 'La pregunta que cambia la junta: “¿quién libera esto y para cuándo?”. Si no hay nombre y fecha, no es restricción.',
        ],
        [
            'id' => 'm3-l4',
            'titulo' => 'Calidad, ITP y no conformidades',
            'cuerpo' => <<<'HTML'
<p>La calidad se controla antes de que el trabajo se tape, no después.</p>
<ol>
<li>Abre <strong>Calidad e inspecciones</strong> y crea un plan ITP por actividad o partida.</li>
<li>Define puntos de inspección, criterios y responsables.</li>
<li>Registra resultados y liberaciones firmadas.</li>
<li>Si algo falla, abre una <strong>no conformidad</strong> con evidencia y responsable.</li>
<li>Revisa la compuerta: un ITP pendiente topa el avance en 99 %; una NC abierta lo congela.</li>
</ol>
<p>La compuerta evita que se declare 100 % una actividad que no está liberada.</p>
HTML,
            'ejercicio' => 'Crea un ITP con tres puntos, libera dos y deja uno pendiente. Intenta subir el avance a 100 %.',
            'ensenar' => 'Explica que la compuerta no es un castigo: es la garantía de que el 100 % significa algo.',
        ],
        [
            'id' => 'm3-l5',
            'titulo' => 'Seguridad, RFI y clima',
            'cuerpo' => <<<'HTML'
<p>La seguridad y las dudas técnicas también son datos de obra.</p>
<ul>
<li><strong>Seguridad y permisos:</strong> permisos de trabajo, pláticas, inspecciones y matriz 3×3; puede materializarse como restricción.</li>
<li><strong>RFI:</strong> solicitud de información con responsable, fecha límite y actividad bloqueada mientras esté abierta.</li>
<li><strong>Clima automático:</strong> el pronóstico se registra en la bitácora y avisa si afecta la jornada.</li>
</ul>
<p>Un RFI sin fecha es una bomba de tiempo: la actividad se detiene y nadie sabe por qué.</p>
[[fig:seguridad]]
HTML,
            'ejercicio' => 'Abre un RFI con fecha límite y registra una plática de seguridad con dos asistentes.',
            'ensenar' => 'Muestra al cliente que un RFI abierto bloquea la actividad en el programa. Así entiende por qué hay que contestarlo.',
        ],
        [
            'id' => 'm3-l6',
            'titulo' => 'Salud del proyecto, mapa de avance y búsqueda',
            'cuerpo' => <<<'HTML'
<p>El <strong>semáforo de salud</strong> junta atrasos, restricciones, submittals, punch, cambios y permisos en una sola vista.</p>
<ol>
<li>Abre <strong>Salud del proyecto</strong> desde el menú del proyecto.</li>
<li>Revisa cada indicador y pulsa el que esté en rojo para ir a la herramienta donde se resuelve.</li>
<li>Usa <strong>Mapa de avance</strong> para colorear zonas del plano según avance.</li>
<li>Usa el <strong>Buscador global</strong> para encontrar una actividad, material, minuta o documento.</li>
</ol>
<p>La salud no guarda nada: se calcula cada vez. Es el tablero que el dueño debe mirar antes de la junta.</p>
HTML,
            'ejercicio' => 'Abre Salud del proyecto y localiza un indicador en rojo. Ve a la herramienta y resuélvelo.',
            'ensenar' => 'Enseña al dueño a mirar el semáforo, no a pedir el PDF. El PDF llega después; el semáforo es el ahora.',
        ],
    ],
],

[
    'id' => 'm4',
    'titulo' => 'Módulo 4 · Materiales, costos y cambios',
    'icono' => 'fa-truck-ramp-box',
    'objetivo' => 'Conectar materiales, compras, submittals y costos con el programa para saber qué falta y cuánto cuesta.',
    'duracion' => '120 min',
    'lecciones' => [
        [
            'id' => 'm4-l1',
            'titulo' => 'Catálogo, MSR y submittals',
            'cuerpo' => <<<'HTML'
<p>El material que no está aprobado o no ha llegado es la causa más común de atraso.</p>
<ol>
<li>En <strong>Catálogo de materiales</strong>, crea los materiales con foto, ficha técnica y especialidad.</li>
<li>En <strong>MSR</strong>, arma el reporte de materiales con las columnas que necesites.</li>
<li>En <strong>Submittals</strong>, agrupa materiales para aprobación y controla envío, revisión y respuesta.</li>
<li>Marca compra, entrega y submittal en cada material.</li>
<li>Revisa qué material llega tarde o qué compra ya venció.</li>
</ol>
<p>El MSR no es una lista: es el semáforo de abastecimiento de la obra.</p>
HTML,
            'ejercicio' => 'Crea tres materiales, un MSR y un submittal con dos materiales. Marca uno rechazado.',
            'ensenar' => 'Pregunta al cliente cuáles son los cinco materiales que más le atrasan. Con esos empieza el catálogo.',
        ],
        [
            'id' => 'm4-l2',
            'titulo' => 'Dependencias de materiales y planificador',
            'cuerpo' => <<<'HTML'
<p>Un material puede necesitar otro material entregado o instalado antes de usarse.</p>
<ol>
<li>Abre <strong>Dependencias de materiales</strong>.</li>
<li>Conecta qué material requiere a otro, con requisito de entrega o instalación y retraso.</li>
<li>Revisa la disponibilidad efectiva y los bloqueos.</li>
<li>Usa el <strong>Planificador por material</strong> para cruzar actividades pendientes con materiales comprados.</li>
<li>Detecta qué puedes adelantar y qué está bloqueado por compra o restricción.</li>
</ol>
<p>Esto convierte la compra en parte del programa, no en un tema aparte.</p>
HTML,
            'ejercicio' => 'Crea una dependencia entre dos materiales y revisa cómo cambia la fecha de disponibilidad.',
            'ensenar' => 'Ejemplo simple: no puedes instalar la cámara sin el soporte. Ese es el tipo de dependencia que enseñas.',
        ],
        [
            'id' => 'm4-l3',
            'titulo' => 'Almacén, órdenes de compra y salidas',
            'cuerpo' => <<<'HTML'
<p>El almacén controla lo que entra, lo que sale y a qué actividad se carga.</p>
<ol>
<li>Abre <strong>Almacén</strong> y registra existencias.</li>
<li>Crea una orden de compra con materiales y proveedor.</li>
<li>Registra recepciones parciales.</li>
<li>Registra salidas a las actividades.</li>
<li>Revisa el costo del material consumido por actividad.</li>
</ol>
<p>Sin salidas, el almacén se vuelve una lista que nadie sabe si es real.</p>
HTML,
            'ejercicio' => 'Crea una orden de compra, recibe parcialmente y registra una salida a una actividad.',
            'ensenar' => 'Enseña al almacenista solo tres acciones: recibir, surtir y contar. Con eso se mantiene vivo.',
        ],
        [
            'id' => 'm4-l4',
            'titulo' => 'Costos, presupuesto y estimaciones',
            'cuerpo' => <<<'HTML'
<p>El costo se conecta con el avance para saber si la obra gana o pierde.</p>
<ol>
<li>Abre <strong>Costos y presupuesto</strong> y carga el presupuesto por partida.</li>
<li>Define cantidades y precio unitario para que el presupuesto se calcule solo.</li>
<li>Registra costo real y dinero comprometido.</li>
<li>Revisa CPI y SPI, valor ganado y flujo de caja.</li>
<li>En <strong>Estimaciones</strong>, genera la estimación con IVA, retención y anticipo, y su PDF.</li>
</ol>
<p>Si el cliente no quiere costos, no lo fuerces: empieza por programa y campo. Los costos entran cuando la captura ya es confiable.</p>
HTML,
            'ejercicio' => 'Carga una partida con cantidad y precio unitario, registra un costo real y revisa el CPI.',
            'ensenar' => 'No vendas “contabilidad”. Vende saber si la obra va ganando o perdiendo antes de que sea tarde.',
        ],
        [
            'id' => 'm4-l5',
            'titulo' => 'Órdenes de cambio y líneas base',
            'cuerpo' => <<<'HTML'
<p>Un cambio sin registro es un regalo al cliente. Una línea base sin comparación es un adorno.</p>
<ol>
<li>Abre <strong>Órdenes de cambio</strong> y registra el cambio: instalar, retirar o reubicar.</li>
<li>Agrega impacto en costo y días, y renglones de instalación temporal con su retiro pendiente.</li>
<li>Adjunta evidencia y responsable.</li>
<li>Guarda una línea base antes del cambio.</li>
<li>Compara la línea base con el programa actual y registra la causa.</li>
</ol>
[[fig:cambios]]
HTML,
            'ejercicio' => 'Registra una orden de cambio con impacto de dos días y compara la línea base.',
            'ensenar' => 'Enseña al cliente a registrar el cambio el mismo día. Al mes siguiente nadie se acuerda de por qué se movió la fecha.',
        ],
        [
            'id' => 'm4-l6',
            'titulo' => 'Portafolio y comparación de obras',
            'cuerpo' => <<<'HTML'
<p>Cuando hay varias obras, el dueño necesita comparar, no sumar PDFs.</p>
<ol>
<li>Abre <strong>Tablero de portafolio</strong>.</li>
<li>Compara avance, SPI, CPI, PPC, punch y salud por obra.</li>
<li>Revisa las alertas por tendencia.</li>
<li>Exporta el portafolio a PDF o Excel para dirección.</li>
</ol>
<p>El portafolio responde una pregunta distinta: “¿en cuál obra debo poner atención hoy?”.</p>
HTML,
            'ejercicio' => 'Abre el portafolio con dos proyectos y ordena por salud. Identifica la obra que requiere atención.',
            'ensenar' => 'Enseña al dueño a revisar el portafolio los lunes y el detalle de una obra solo si el semáforo lo pide.',
        ],
    ],
],

[
    'id' => 'm5',
    'titulo' => 'Módulo 5 · Documentos, planos y diseño',
    'icono' => 'fa-folder-open',
    'objetivo' => 'Manejar la documentación, los planos y las láminas de la obra en su versión vigente, sin depender de carpetas compartidas.',
    'duracion' => '120 min',
    'lecciones' => [
        [
            'id' => 'm5-l1',
            'titulo' => 'Archivo y Visor',
            'cuerpo' => <<<'HTML'
<p>El Archivo es la fuente de verdad de planos, contratos, especificaciones y permisos.</p>
<ol>
<li>Abre <strong>Archivo</strong> y crea categorías por disciplina o tipo de documento.</li>
<li>Sube el archivo con número de revisión y fecha.</li>
<li>El sistema revisa el archivo con antivirus antes de aceptarlo.</li>
<li>Usa <strong>Visor</strong> para abrir PDF, imágenes y hojas de cálculo sin salir de la plataforma.</li>
<li>Controla quién puede ver y descargar cada archivo.</li>
</ol>
<p>Un plano vencido en la obra es un error caro. El Archivo con revisión evita que eso pase.</p>
HTML,
            'ejercicio' => 'Sube dos revisiones de un mismo plano y marca cuál es la vigente.',
            'ensenar' => 'Pregunta al cliente dónde guarda hoy los planos. Si la respuesta es “en WhatsApp”, ahí tienes la venta.',
        ],
        [
            'id' => 'm5-l2',
            'titulo' => 'Redactor y SheetFlow',
            'cuerpo' => <<<'HTML'
<p>El Redactor es para documentos de texto; SheetFlow, para tablas con fórmulas.</p>
<ol>
<li>En <strong>Redactor</strong>, crea un acta o memoria con portada, secciones y tablas del programa.</li>
<li>Usa las variables de proyecto para no escribir a mano nombre, fecha y usuario.</li>
<li>Guarda versiones y exporta a DOCX, ODT o PDF.</li>
<li>En <strong>SheetFlow</strong>, crea una tabla con columnas, fórmulas y formato.</li>
<li>Importa y exporta XLSX, CSV o PDF.</li>
</ol>
<p>La ventaja no es “otro Word”: es que las tablas del programa y los datos del proyecto se insertan solos.</p>
HTML,
            'ejercicio' => 'Crea un acta con una tabla del programa y una hoja de SheetFlow con una fórmula de suma.',
            'ensenar' => 'Enseña primero el acta de junta. Es el documento que el cliente ya hace y entiende.',
        ],
        [
            'id' => 'm5-l3',
            'titulo' => 'Comparador, Anotador y Unir documentos',
            'cuerpo' => <<<'HTML'
<p>Tres herramientas para no imprimir ni marcar planos a mano:</p>
<ul>
<li><strong>Comparador:</strong> empareja dos revisiones de un plano o PDF y muestra los cambios con deslizador, fundido o diferencias con tolerancia.</li>
<li><strong>Anotador:</strong> abre un PDF o imagen y anota globos, textos, formas, polígonos con área/perímetro y volumen, imágenes, estampas y firmas. Exporta el PDF aplanado.</li>
<li><strong>Unir documentos (PDF):</strong> une documentos del Redactor y PDF del Archivo en un solo PDF que se actualiza solo cuando cambian las fuentes.</li>
</ul>
<p>El comparador es oro para revisar revisiones de planos. El anotador, para dejar evidencia de una revisión.</p>
HTML,
            'ejercicio' => 'Compara dos revisiones de un plano y anota un globo con una medida.',
            'ensenar' => 'Muestra el comparador con dos planos reales del cliente. El “antes/después” vende solo.',
        ],
        [
            'id' => 'm5-l4',
            'titulo' => 'Editor, Símbolos, estampas y cajetines',
            'cuerpo' => <<<'HTML'
<p>El Editor y los Símbolos sirven para croquis, diagramas y láminas.</p>
<ol>
<li>En <strong>Editor</strong>, crea un gráfico con varias hojas, líneas, formas, textos e imágenes.</li>
<li>Usa la <strong>Biblioteca de símbolos</strong> para cámaras, racks, extintores, luminarias y tableros.</li>
<li>Inserta <strong>estampas inteligentes</strong>: bloques con etiquetas que se llenan solas (avance, fechas, semáforos, actividades del día).</li>
<li>Configura un <strong>cajetín</strong> con logo y datos del proyecto.</li>
<li>Descarga en PNG, PDF o presentación .odp.</li>
</ol>
<p>Las estampas y cajetines ahorran repetir datos en cada lámina.</p>
HTML,
            'ejercicio' => 'Crea un croquis con tres símbolos y una estampa de avance. Exporta a PDF.',
            'ensenar' => 'Enseña el cajetín y la estampa como “sellos que se llenan solos”. Es lo que más impresiona en la junta.',
        ],
        [
            'id' => 'm5-l5',
            'titulo' => 'View360, Traductor y Normativa',
            'cuerpo' => <<<'HTML'
<p>Tres herramientas que amplían lo que el cliente puede ver y entender:</p>
<ul>
<li><strong>View360:</strong> un plano con zonas que abren fotos 360° de la obra, con notas para el cliente.</li>
<li><strong>Traductor:</strong> traduce textos y documentos español ↔ inglés sin salir de la plataforma.</li>
<li><strong>Normativa y especificaciones:</strong> carga normas, consúltalas por cláusula y vincúlalas a materiales y actividades. Es solo del administrador.</li>
</ul>
<p>View360 reduce viajes; el traductor abre clientes internacionales; la normativa evita trabajar con una especificación vencida.</p>
[[fig:normativa]]
HTML,
            'ejercicio' => 'Sube una norma, vincúlala a un material y traduce un párrafo al inglés.',
            'ensenar' => 'Usa View360 con el dueño de la obra: ver su obra desde otro país sin viajar es un argumento fuerte.',
        ],
        [
            'id' => 'm5-l6',
            'titulo' => 'Ensamblador con IA y Ordenar/Renumerar',
            'cuerpo' => <<<'HTML'
<p>El <strong>Ensamblador</strong> vive en la app de escritorio y convierte PDF, PPTX o DOCX en partidas, materiales y submittals con IA. Tú revisas y aceptas.</p>
<ol>
<li>Instala la app de escritorio desde Ajustes → App de escritorio.</li>
<li>Abre <em>Herramientas IA → Ensamblador</em> y sube el documento.</li>
<li>Indica qué buscar con los botones o el chat.</li>
<li>Revisa la propuesta con casillas y aplica o deshaz.</li>
<li>Para ordenar el programa sin IA, usa <strong>Ordenar y renumerar</strong>: calcula el orden por fechas y renumera la EDT con vista previa.</li>
</ol>
<p>La IA no cambia datos sin que tú aceptes. Ese es el contrato.</p>
HTML,
            'ejercicio' => 'Ordena y renumera un programa de prueba. Revisa la vista previa antes de aplicar.',
            'ensenar' => 'Explica que el Ensamblador ahorra captura, pero el criterio técnico sigue siendo del residente.',
        ],
    ],
],

[
    'id' => 'm6',
    'titulo' => 'Módulo 6 · Administración y gobierno',
    'icono' => 'fa-scale-balanced',
    'objetivo' => 'Cerrar el círculo administrativo: contratos, equipo, cierre, marca, integraciones y respaldos.',
    'duracion' => '90 min',
    'lecciones' => [
        [
            'id' => 'm6-l1',
            'titulo' => 'Contratos, fianzas, seguros y subcontratos',
            'cuerpo' => <<<'HTML'
<p>La obra no termina en el programa: termina en el contrato y en el cierre.</p>
<ol>
<li>En <strong>Contratos y fianzas</strong>, registra contratos, fianzas, seguros y licencias con vencimiento y aviso previo.</li>
<li>En <strong>Subcontratos</strong>, captura alcance, monto, retención, anticipo, documentos obligatorios y estimaciones.</li>
<li>Sin documentos vigentes no se autoriza el pago.</li>
<li>Revisa los avisos de vencimiento antes de que detengan la obra.</li>
</ol>
<p>Un seguro vencido puede detener una estimación. La plataforma avisa antes.</p>
HTML,
            'ejercicio' => 'Registra un contrato con vencimiento próximo y una fianza. Revisa el aviso.',
            'ensenar' => 'Enseña al administrador del cliente a revisar vencimientos cada lunes. Es dinero, no papeleo.',
        ],
        [
            'id' => 'm6-l2',
            'titulo' => 'Equipo, maquinaria, directorio y especialidades',
            'cuerpo' => <<<'HTML'
<p>Los recursos también se controlan:</p>
<ul>
<li><strong>Equipo y maquinaria:</strong> horas, costo y mantenimiento.</li>
<li><strong>Directorio:</strong> personal, departamentos, categorías y especialidades compartidos por las herramientas.</li>
<li><strong>Especialidades:</strong> etiquetas para clasificar materiales y actividades.</li>
</ul>
<p>Un directorio bien cargado evita escribir el mismo nombre en cinco herramientas.</p>
HTML,
            'ejercicio' => 'Crea un departamento, una especialidad y un equipo con costo por hora.',
            'ensenar' => 'Empieza por el personal que ya aparece en el parte diario. El directorio se llena solo con el uso.',
        ],
        [
            'id' => 'm6-l3',
            'titulo' => 'Campos personalizados, diseño de listas y plantillas',
            'cuerpo' => <<<'HTML'
<p>La plataforma se adapta a la empresa, no al revés.</p>
<ol>
<li>En <strong>Campos personalizados</strong>, crea columnas propias (Nivel, Frente, Torre…).</li>
<li>Úsalas en filtros y agrupaciones de las listas.</li>
<li>En <strong>Diseño de listas</strong>, elige qué columnas ve cada herramienta.</li>
<li>En <strong>Plantillas de proyecto</strong>, guarda un proyecto completo para reutilizarlo.</li>
</ol>
<p>Los campos personalizados son la diferencia entre “otro sistema” y “el sistema de esta empresa”.</p>
HTML,
            'ejercicio' => 'Crea un campo “Frente”, agrégalo a una lista y filtra por él.',
            'ensenar' => 'Pregunta al cliente qué columna le falta en su Excel. Esa es la primera que creas.',
        ],
        [
            'id' => 'm6-l4',
            'titulo' => 'Marca, compartir, integraciones y Telegram',
            'cuerpo' => <<<'HTML'
<p>La plataforma también es la cara de la empresa:</p>
<ul>
<li><strong>Marca del cliente:</strong> logo y colores en los PDF de reportes. Disponible desde Constructora; el proyecto puede sobrescribirla.</li>
<li><strong>Compartir en solo lectura:</strong> enlace temporal para el cliente y suscripción de calendario .ics.</li>
<li><strong>Integraciones:</strong> Telegram para avisos, avance y fotos; clima automático; tokens de API REST y webhooks.</li>
<li><strong>API REST:</strong> disponible desde Pro. Se administra en Integraciones.</li>
</ul>
<p>El cliente que recibe un PDF con su logo siente que el sistema es suyo.</p>
HTML,
            'ejercicio' => 'Configura la marca con logo y color, y crea un enlace de solo lectura.',
            'ensenar' => 'Enseña primero el enlace de solo lectura. Es la forma más rápida de que el cliente vea valor sin crear usuarios.',
        ],
        [
            'id' => 'm6-l5',
            'titulo' => 'Ajustes, respaldo, almacenamiento y notificaciones',
            'cuerpo' => <<<'HTML'
<p>La operación diaria también se administra:</p>
<ol>
<li>En <strong>Ajustes del proyecto</strong>, revisa calendario, almacenamiento, respaldo y apariencia.</li>
<li>El <strong>respaldo automático</strong> corre a las 23:30 y conserva diario, semanal, mensual y manuales.</li>
<li>El <strong>almacenamiento</strong> tiene tope por organización y por obra; las obras archivadas siguen ocupando disco.</li>
<li>La <strong>campana de notificaciones</strong> muestra novedades, actividades de hoy y acciones rápidas.</li>
<li>Las <strong>novedades de la plataforma</strong> se publican desde Ajustes.</li>
</ol>
[[fig:respaldos]]
HTML,
            'ejercicio' => 'Haz un respaldo manual, revisa el espacio usado y abre la campana de notificaciones.',
            'ensenar' => 'Explica al cliente que sus datos son suyos y puede exportarlos o respaldarlos. La confianza se gana con eso.',
        ],
        [
            'id' => 'm6-l6',
            'titulo' => 'Cierre de obra y expediente',
            'cuerpo' => <<<'HTML'
<p>El cierre no se improvisa: se arma durante la obra.</p>
<ol>
<li>Abre <strong>Cierre de obra</strong> y revisa la lista de entrega-recepción.</li>
<li>Verifica as-builts, garantías, manuales y pendientes.</li>
<li>Cierra el punch list con evidencia antes/después.</li>
<li>Genera el acta de entrega en PDF.</li>
<li>Guarda el expediente completo en el Archivo.</li>
</ol>
<p>Un cierre ordenado es la mejor carta de presentación para la siguiente obra.</p>
HTML,
            'ejercicio' => 'Revisa la lista de cierre de un proyecto y marca tres pendientes con responsable.',
            'ensenar' => 'Enseña al cliente a revisar el cierre desde la semana 1, no en la última. Lo que no se documenta, no se cobra.',
        ],
    ],
],

[
    'id' => 'm7',
    'titulo' => 'Módulo 7 · IA y análisis',
    'icono' => 'fa-robot',
    'objetivo' => 'Usar la IA como asistente, con revisión humana y auditoría, sin dejar que cambie datos sin permiso.',
    'duracion' => '60 min',
    'lecciones' => [
        [
            'id' => 'm7-l1',
            'titulo' => 'Centro de IA y Programador',
            'cuerpo' => <<<'HTML'
<p>La IA de ProjectWeb propone; tú decides.</p>
<ol>
<li>Abre <strong>Centro de IA</strong> y revisa las propuestas pendientes de todas las herramientas.</li>
<li>En <strong>Programador</strong>, describe la obra y deja que la IA proponga partidas y actividades.</li>
<li>Revisa la propuesta con casillas: aceptar, rechazar o ajustar.</li>
<li>Aplica solo lo que tenga sentido técnico.</li>
<li>Revisa el historial y la auditoría de cada decisión.</li>
</ol>
[[fig:centro-ia]]
HTML,
            'ejercicio' => 'Genera una propuesta de actividades con el Programador y rechaza una actividad.',
            'ensenar' => 'Frase clave para el cliente: “la IA no cambia nada sin que tú lo aceptes”. Eso baja el miedo.',
        ],
        [
            'id' => 'm7-l2',
            'titulo' => 'Revisión de datos y análisis de materiales',
            'cuerpo' => <<<'HTML'
<p>La IA también encuentra lo que no cuadra:</p>
<ul>
<li><strong>Revisión de datos:</strong> fechas que no cuadran, datos duplicados o incompletos, cambios entre versiones y desviaciones sin justificar.</li>
<li><strong>Análisis de materiales:</strong> cruza materiales contra el cronograma y sugiere ajustes.</li>
<li><strong>Materiales inteligentes:</strong> detecta compras vencidas, entregas tardías y submittals sin aprobar.</li>
</ul>
<p>La vista “Por problema” agrupa los hallazgos para corregirlos en lote, pero solo las acciones seguras.</p>
[[fig:inconsistencias]]
HTML,
            'ejercicio' => 'Ejecuta una revisión de datos y corrige un hallazgo con el editor diferido.',
            'ensenar' => 'Enseña al cliente que la IA es un revisor incansable, no un director de obra. Él valida.',
        ],
        [
            'id' => 'm7-l3',
            'titulo' => 'Informes, voz, chat y otras herramientas',
            'cuerpo' => <<<'HTML'
<p>El Centro de IA reúne varias herramientas de apoyo:</p>
<ul>
<li><strong>Informes:</strong> resumen ejecutivo diario o semanal, simple o detallado.</li>
<li><strong>Voz y chat:</strong> consultas en lenguaje natural sobre la obra.</li>
<li><strong>Normativa:</strong> búsqueda y vínculo con materiales y actividades.</li>
<li><strong>Seguridad y punch:</strong> análisis de riesgos y pendientes.</li>
<li><strong>Revisiones:</strong> semáforo ✅ ⚠ ❌ por proyecto y estado de uso de cada herramienta.</li>
</ul>
<p>La IA es útil cuando los datos están capturados. Sin datos, solo inventa.</p>
HTML,
            'ejercicio' => 'Genera un informe simple y revisa qué datos usó. Si falta algo, vuelve a capturarlo.',
            'ensenar' => 'Explica que la IA no reemplaza la captura: la aprovecha. Si el parte diario está vacío, el informe también.',
        ],
        [
            'id' => 'm7-l4',
            'titulo' => 'Auditoría, reversión y pruebas de calidad',
            'cuerpo' => <<<'HTML'
<p>La confianza en la IA se construye con trazabilidad.</p>
<ol>
<li>Cada propuesta aplicada queda en el <strong>historial</strong> con usuario, fecha y qué cambió.</li>
<li>Puedes <strong>revertir</strong> un cambio aceptado.</li>
<li>En <strong>Calidad de IA</strong>, corre casos fijos y revisa la precisión.</li>
<li>Revisa la <strong>salud de la revisión</strong>: precisión, accionables y observaciones.</li>
</ol>
<p>Si no puedes explicar qué cambió y por qué, no aceptes la propuesta.</p>
HTML,
            'ejercicio' => 'Acepta una propuesta, revierte el cambio y revisa el historial.',
            'ensenar' => 'Enseña al cliente a revisar el historial antes de aceptar un lote. La IA se audita, no se cree.',
        ],
    ],
],

[
    'id' => 'm8',
    'titulo' => 'Módulo 8 · Capacitar al cliente',
    'icono' => 'fa-chalkboard-user',
    'objetivo' => 'Convertir lo aprendido en una capacitación de 45 minutos, un piloto de 30 días y una adopción real.',
    'duracion' => '60 min',
    'lecciones' => [
        [
            'id' => 'm8-l1',
            'titulo' => 'Guion de demo de 45 minutos',
            'cuerpo' => <<<'HTML'
<p>Una demo no es un recorrido por 50 herramientas. Es una historia con tres momentos.</p>
<ol>
<li><strong>0–5 min:</strong> entender la obra del cliente: tipo, etapas, quién captura hoy y qué le duele.</li>
<li><strong>5–15 min:</strong> programa y Gantt con su calendario real. Muestra cómo se mueve una fecha y se recalcula todo.</li>
<li><strong>15–25 min:</strong> reporte diario desde el celular, con foto y PDF. Es el momento “ajá” del residente.</li>
<li><strong>25–35 min:</strong> materiales/submittals y una restricción que bloquea una actividad.</li>
<li><strong>35–42 min:</strong> acceso del cliente en solo lectura y salud del proyecto.</li>
<li><strong>42–45 min:</strong> piloto de 30 días, precio, qué incluye y siguiente paso.</li>
</ol>
<p>Nunca muestres una herramienta que el cliente no vaya a usar en su primera semana.</p>
HTML,
            'ejercicio' => 'Haz la demo de 45 min con un compañero y cronométrala. Ajusta lo que sobre.',
            'ensenar' => 'La demo se hace con la obra del cliente, no con el demo genérico. Si no tienes sus datos, pide una foto de su programa.',
            'video' => 'Grabar la demo completa de 45 min y usarla como material de entrenamiento interno.',
        ],
        [
            'id' => 'm8-l2',
            'titulo' => 'Ejercicio guiado con una obra real',
            'cuerpo' => <<<'HTML'
<p>El cliente aprende haciendo. Prepara un ejercicio de 30 minutos con su propia obra:</p>
<ol>
<li>Crea el proyecto con él (5 min).</li>
<li>Carga una partida y cinco actividades (10 min).</li>
<li>Configura el calendario y una dependencia (5 min).</li>
<li>Captura un reporte diario con foto (5 min).</li>
<li>Genera el PDF y compártelo (5 min).</li>
</ol>
<p>Al terminar, el cliente ya hizo el ciclo completo: planeó, ejecutó y documentó.</p>
HTML,
            'ejercicio' => 'Haz el ejercicio con un cliente real o con un compañero que haga de cliente.',
            'ensenar' => 'No toques el teclado por el cliente. Guía con preguntas: “¿qué harías tú aquí?”.',
        ],
        [
            'id' => 'm8-l3',
            'titulo' => 'Objeciones frecuentes y respuestas',
            'cuerpo' => <<<'HTML'
<table class="tabla manual-tabla">
<thead><tr><th>Objeción</th><th>Respuesta</th></tr></thead>
<tbody>
<tr><td>“Ya uso MS Project”</td><td>“Perfecto: lo importamos y sigues exportando. Lo que no te da es campo, materiales y acceso del cliente.”</td></tr>
<tr><td>“Es caro”</td><td>“Un día de atraso en una obra de $30M con penalización del 0.5 % son $150,000. La plataforma cuesta menos que eso al año.”</td></tr>
<tr><td>“Mi gente no lo va a usar”</td><td>“Por eso el piloto incluye acompañamiento. Si en 30 días no lo usan, no sigues.”</td></tr>
<tr><td>“Mis datos”</td><td>“Son tuyos: exportas todo en PDF, Excel, XML y te llevas los archivos originales.”</td></tr>
<tr><td>“No tengo tiempo”</td><td>“La implementación la hacemos nosotros: cargamos tu programa y capacitamos a tu equipo.”</td></tr>
<tr><td>“¿Reemplazan a mi residente?”</td><td>“No. Somos administración y seguimiento; la responsabilidad técnica sigue siendo tuya.”</td></tr>
</tbody>
</table>
HTML,
            'ejercicio' => 'Escribe tus propias respuestas a las tres objeciones que más escuchas.',
            'ensenar' => 'Nunca discutas el precio sin antes hablar del costo de un día de atraso.',
        ],
        [
            'id' => 'm8-l4',
            'titulo' => 'Checklist de onboarding y piloto de 30 días',
            'cuerpo' => <<<'HTML'
<p>El piloto de 30 días es una prueba con una obra real, con acompañamiento y precio acreditable.</p>
<ol>
<li><strong>Antes de arrancar:</strong> patrocinador, campeón, una obra y 3 usuarios.</li>
<li><strong>Día 0:</strong> organización, plan, proyecto, programa y capacitación.</li>
<li><strong>Semana 1:</strong> reportes diarios y primer lookahead.</li>
<li><strong>Semana 2:</strong> materiales/submittals y acceso del cliente.</li>
<li><strong>Semana 3:</strong> restricciones, cambios y revisión de medio piloto.</li>
<li><strong>Semana 4:</strong> informe final, ROI y junta de decisión.</li>
<li><strong>Cierre:</strong> plan anual o cierre honesto con aprendizajes.</li>
</ol>
<p>Criterios de éxito: 3 usuarios activos, 80 % de actividades actualizadas, 15 reportes diarios y al menos un riesgo detectado a tiempo.</p>
HTML,
            'ejercicio' => 'Arma el checklist de onboarding de un cliente real y agenda las cuatro juntas.',
            'ensenar' => 'Sin campeón nombrado no hay piloto. Es la regla que evita perder el mes.',
        ],
        [
            'id' => 'm8-l5',
            'titulo' => 'Métricas de adopción y cierre',
            'cuerpo' => <<<'HTML'
<p>No midas “si les gustó”. Mide uso y valor:</p>
<table class="tabla manual-tabla">
<thead><tr><th>Métrica</th><th>Meta del piloto</th></tr></thead>
<tbody>
<tr><td>Usuarios activos</td><td>3 en la semana 2</td></tr>
<tr><td>Actividades actualizadas</td><td>≥ 80 % de la semana</td></tr>
<tr><td>Reportes diarios</td><td>≥ 4 por semana</td></tr>
<tr><td>Restricciones detectadas</td><td>≥ 1 antes de impactar</td></tr>
<tr><td>Horas liberadas</td><td>≥ 6 h/semana</td></tr>
<tr><td>NPS del patrocinador</td><td>≥ 8</td></tr>
</tbody>
</table>
<p>Al cierre, entrega un informe de una página con estos números y una recomendación.</p>
HTML,
            'ejercicio' => 'Calcula las horas liberadas de un cliente con sus datos reales y prepáralas para la junta de cierre.',
            'ensenar' => 'El cierre se gana durante el mes, no en la última junta. Manda el reporte cada viernes.',
        ],
    ],
],

[
    'id' => 'm9',
    'titulo' => 'Módulo 9 · Certificación del administrador',
    'icono' => 'fa-certificate',
    'objetivo' => 'Comprobar que puedes crear un proyecto completo, operarlo una semana y capacitar a un cliente.',
    'duracion' => '30 min',
    'lecciones' => [
        [
            'id' => 'm9-l1',
            'titulo' => 'Checklist de dominio',
            'cuerpo' => <<<'HTML'
<p>Marca cada punto solo si puedes hacerlo sin ayuda:</p>
<ul class="curso-check-lista">
<li>Crear una organización y asignar un plan.</li>
<li>Crear propietario, gestor, colaborador y visitante.</li>
<li>Crear un proyecto con calendario, propietario y accesos.</li>
<li>Armar partidas, actividades, hitos y dependencias.</li>
<li>Guardar una línea base y comparar.</li>
<li>Preparar lookahead y Last Planner.</li>
<li>Crear una lámina de vPlan.</li>
<li>Capturar un parte diario con foto y PDF.</li>
<li>Registrar una restricción y resolverla.</li>
<li>Crear un ITP y una no conformidad.</li>
<li>Crear materiales, MSR y submittal.</li>
<li>Registrar una orden de cambio.</li>
<li>Subir un plano, comparar revisiones y anotar.</li>
<li>Configurar marca y enlace de solo lectura.</li>
<li>Hacer un respaldo y revisar almacenamiento.</li>
<li>Dar una demo de 45 minutos.</li>
</ul>
HTML,
            'ejercicio' => 'Haz el checklist completo en un proyecto de prueba. Anota lo que no puedas hacer sin ayuda.',
            'ensenar' => 'Este checklist es también el guion de certificación de un cliente.',
        ],
        [
            'id' => 'm9-l2',
            'titulo' => 'Examen práctico',
            'cuerpo' => <<<'HTML'
<p>El examen no es teórico. Tienes 90 minutos para entregar un proyecto de prueba con:</p>
<ol>
<li>Organización y plan configurados.</li>
<li>Un proyecto con calendario y dos usuarios.</li>
<li>Una partida con cinco actividades y un hito.</li>
<li>Dos dependencias y una línea base.</li>
<li>Un lookahead y una lámina de vPlan.</li>
<li>Un parte diario con foto y PDF.</li>
<li>Un material, un submittal y una orden de cambio.</li>
<li>Un enlace de solo lectura y el PDF del programa.</li>
</ol>
<p>Si algo falla, no es que “la herramienta no sirve”: revisa calendario, permisos y datos obligatorios.</p>
HTML,
            'ejercicio' => 'Cronometra el examen práctico y entrega el proyecto a otro administrador para que lo revise.',
            'ensenar' => 'El examen se aprueba cuando otra persona puede seguir tu proyecto sin que se lo expliques.',
        ],
        [
            'id' => 'm9-l3',
            'titulo' => 'Cómo dar el certificado interno',
            'cuerpo' => <<<'HTML'
<p>Al terminar, entrega un certificado interno con:</p>
<ul>
<li>Nombre del administrador y fecha.</li>
<li>Módulos completados y examen práctico.</li>
<li>Proyecto de prueba revisado.</li>
<li>Compromiso de capacitación: demo de 45 min y piloto de 30 días.</li>
</ul>
<p>El certificado no es un adorno: es la señal de que puede capacitar a un cliente sin acompañamiento.</p>
<p><strong>Siguiente paso:</strong> agenda una demo real con un cliente y usa el guion del Módulo 8.</p>
HTML,
            'ejercicio' => 'Prepara el certificado de un compañero y agenda su primera demo real.',
            'ensenar' => 'Un administrador certificado no es el que sabe más: es el que puede enseñar sin depender de ti.',
        ],
    ],
],

];
