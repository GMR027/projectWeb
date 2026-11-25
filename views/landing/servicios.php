<?php
$base = '/';
$activa = 'servicios';
$whatsapp = $whatsapp ?? '';
$whatsappUrl = $whatsappUrl ?? '';
$precios = $precios ?? [
    'piloto' => \Classes\LandingContenido::precioServicio('piloto'),
    'diagnostico' => \Classes\LandingContenido::precioServicio('diagnostico'),
    'quincenal' => \Classes\LandingContenido::precioServicio('quincenal'),
    'semanal' => \Classes\LandingContenido::precioServicio('semanal'),
    'oficina' => \Classes\LandingContenido::precioServicio('oficina'),
];
$fmt = fn($n) => '$' . number_format((int)$n, 0, '.', ',');

$grupos = [
    'Planear' => [
        ['fa-chart-gantt', 'Control del programa de obra', 'Avance real contra lo planeado, ruta crítica, holguras y proyección a cuatro semanas, sobre el calendario real de tu obra.', ['Gantt y calendario', 'Ruta crítica', 'Curva S']],
        ['fa-binoculars', 'Lookahead y Last Planner', 'Preparamos el lookahead semanal con sus limitaciones y los post-its del día, listos para la junta de planeación y para imprimir.', ['4 semanas', 'PTA', 'Post-its']],
        ['fa-sheet-plastic', 'Láminas visuales para juntas', 'Planos con el avance marcado, tablas del programa, recovery y restricciones, en láminas del tamaño que pidas.', ['vPlan', 'Tabloide a A0', 'PDF por lámina']],
    ],
    'Ejecutar' => [
        ['fa-clipboard-list', 'Reportes, bitácora y minutas', 'Reporte diario con personal y fotografías, bitácora por departamento y minutas con compromisos, fechados y listos para el cliente.', ['PDF en un clic', 'Evidencias', 'Compromisos']],
        ['fa-triangle-exclamation', 'Restricciones y pendientes', 'Lo que frena a cada frente, con responsable y fecha compromiso, hasta que se libera. Incluye el punch list de cierre con fotos antes y después.', ['Responsable', 'Fecha límite', 'Punch list']],
        ['fa-street-view', 'Recorridos y registro visual', 'Fotos 360° ligadas al plano y evidencias por actividad en una línea de tiempo, para que el cliente recorra la obra sin viajar.', ['View360', 'Línea de tiempo', 'Antes / después']],
    ],
    'Abastecer' => [
        ['fa-truck-ramp-box', 'Materiales, submittals y MSR', 'Aprobaciones, compras y entregas cruzadas con el programa, para avisar con tiempo qué material puede frenar una actividad.', ['MSR a tu medida', 'Submittals', 'Curvas']],
    ],
    'Documentar' => [
        ['fa-folder-open', 'Control documental', 'Planos, especificaciones y contratos por revisión; detectamos qué cambió entre versiones para que nadie trabaje con un plano vencido.', ['Versiones', 'Comparador', 'Envíos con acuse']],
        ['fa-file-lines', 'Documentos y tablas de obra', 'Actas, procedimientos, memorias y tablas de control con fórmulas, con el formato de tu empresa o del cliente, en Word, Excel o PDF.', ['Actas', 'Tablas vivas', 'Traducción ES/EN']],
        ['fa-file-export', 'Exportaciones y respaldo', 'Entregamos el programa en PDF tabloide, Excel, LibreOffice, MS Project o ProjectLibre, y un ZIP completo del proyecto cuando lo necesites.', ['MS Project', 'Excel', 'ZIP']],
    ],
    'Controlar' => [
        ['fa-file-signature', 'Control de cambios y alcance', 'Cada orden de cambio con su impacto en costo y tiempo, y el programa comparado contra su línea base para justificar las desviaciones.', ['Impacto en días', 'Línea base', 'Retiros']],
        ['fa-gauge-high', 'Informes para dirección y cliente', 'Resumen ejecutivo periódico con avance, semáforo de la obra, desviaciones y los puntos que requieren una decisión.', ['Semáforo', 'SPI', 'Decisiones']],
    ],
];

$cifras = [
    ['12', 'frentes administrativos que podemos asumir'],
    ['3 + 2', 'niveles de servicio más piloto y diagnóstico'],
    ['14', 'formatos de entrega, del PDF al XML de MS Project'],
    ['24/7', 'consulta del cliente en solo lectura'],
];

$antesAhora = [
    ['fa-chart-gantt', 'Cada quien con su copia del programa y una junta para saber cuál es la buena.', 'Una sola versión en la nube: todos ven el mismo avance, del celular a la oficina.'],
    ['fa-clipboard-list', 'El reporte se arma en Word al final del mes y las fotos andan en el chat.', 'Reporte diario con personal, actividades y fotos, en PDF listo para el cliente.'],
    ['fa-binoculars', 'Las restricciones se descubren en la junta, cuando ya frenaron la obra.', 'Restricciones con responsable y fecha, visibles antes de que detengan el frente.'],
    ['fa-truck-ramp-box', 'Los materiales viven en una hoja que nadie cruza con el cronograma.', 'Cada material ligado a su actividad, con compras vencidas y submittals a la vista.'],
    ['fa-file-signature', 'Los cambios se explican de memoria y el atraso no tiene culpable.', 'Órdenes de cambio con impacto en costo y días, y línea base para comparar.'],
    ['fa-user-check', 'El cliente espera el PDF de la semana para saber cómo va la obra.', 'Consulta el avance en solo lectura cuando quiera, sin poder modificar nada.'],
];

$entregables = [
    ['Informe ejecutivo', 'Avance, semáforo, desviaciones y decisiones pendientes.', 'Semanal o quincenal', 'check', 'check', 'check'],
    ['Reporte diario', 'Personal, actividades y fotografías de cada día.', 'Diario o por corte', 'base', 'base', 'base'],
    ['Lookahead y Last Planner', 'Las próximas semanas con sus limitaciones y los post-its del día.', 'Semanal', 'check', 'check', 'check'],
    ['Minutas con compromisos', 'Temas, responsables y fechas, en PDF.', 'Después de cada junta', 'check', 'check', 'check'],
    ['Archivo ordenado del proyecto', 'Planos, contratos y documentos por revisión, con acceso controlado.', 'Permanente', 'check', 'check', 'check'],
    ['Láminas vPlan', 'Planos con el avance marcado por frente o por área.', 'Para cada junta', 'medida', 'check', 'check'],
    ['Tablero de materiales', 'Qué falta aprobar, comprar o entregar, y qué lo frena.', 'Semanal', 'medida', 'check', 'check'],
    ['Registro de cambios', 'Órdenes de cambio, líneas base y desviaciones justificadas.', 'Al ocurrir cada cambio', 'medida', 'check', 'check'],
    ['Cierre y punch list', 'Pendientes de calidad con foto antes y después, y acta de entrega.', 'Al cierre de etapa', 'medida', 'medida', 'check'],
    ['Acceso cliente solo lectura', 'El cliente consulta avance, evidencias y documentos sin editar.', 'Permanente', 'check', 'check', 'check'],
];

$formatos = [
    ['Documentos', ['PDF', 'Word (.docx)', 'ODT']],
    ['Hojas de cálculo', ['Excel (.xlsx)', 'ODS', 'CSV']],
    ['Programación', ['XML de MS Project', 'POD de ProjectLibre']],
    ['Planos y gráficos', ['PDF', 'PNG', 'Presentación (.odp)', 'ZIP']],
    ['Integración', ['JSON (API)', 'ICS (calendario)']],
];

$entrada = [
    ['fa-rocket', 'Piloto de 30 días', 'Una obra real, tu equipo y acompañamiento para probar sin comprar a ciegas.', $precios['piloto'], 'acreditable', ['1 obra y 3 usuarios de edición', 'Implementación y capacitación', '4 juntas semanales de 30 min', 'Reporte semanal e informe final']],
    ['fa-stethoscope', 'Diagnóstico de obra', 'Para saber qué corregir antes de contratar seguimiento.', $precios['diagnostico'], 'una vez', ['Revisión de programa y calendario', 'Riesgos de atraso y quick wins', 'Plan de 90 días en PDF', 'Se acredita al primer mes de seguimiento']],
];
$niveles = [
    ['fa-bullseye', 'Esencial', 'Para obras pequeñas o frentes que necesitan orden sin un equipo completo.', $precios['quincenal'], '', ['2 juntas al mes', 'Programa actualizado', 'Reporte quincenal e informe mensual', 'Acceso de solo lectura para tu cliente', 'Restricciones y pendientes básicos']],
    ['fa-people-group', 'Completo', 'Para obras en marcha que necesitan control semanal y decisiones a tiempo.', $precios['semanal'], '', ['1 junta semanal', 'Todo lo del nivel Esencial', 'Restricciones y materiales al día', 'Alertas de atraso y cambios', 'Informe ejecutivo semanal']],
    ['fa-building-circle-check', 'Consolidado', 'Para varias obras o grupos constructores.', $precios['oficina'], 'desde', ['Todo lo del nivel Completo, para varias obras', 'Informes consolidados para dirección', 'Valor ganado y curva S por proyecto', 'Comité mensual y analista con más dedicación']],
];
$faq = [
    ['¿Qué incluye el piloto de 30 días?', 'Una obra real, 3 usuarios de edición, implementación, capacitación, 4 juntas semanales de 30 minutos, reporte semanal e informe final. Cuesta ' . $fmt($precios['piloto']) . ' y se acredita al plan anual si continúas.'],
    ['¿Qué pasa si no continúo después del piloto?', 'No tienes obligación de seguir. Te quedas con tu información y el informe del piloto; no se renueva nada automáticamente.'],
    ['¿Tengo que dejar mis herramientas actuales?', 'No. Nos adaptamos a tu forma de trabajar: podemos importar tu programa (XML de MS Project, ProjectLibre) y entregarte todo en los formatos que ya usas.'],
    ['¿Ustedes aportan la experiencia técnica de la especialidad?', 'No. Nosotros administramos y damos seguimiento a la información; la experiencia y el criterio de cada disciplina los aporta tu empresa, supervisor o residente. Trabajamos con los datos que ellos comparten y validan las decisiones.'],
    ['¿Puedo pedir más o menos información?', 'Sí. El alcance es a la medida: defines con nosotros qué entregables necesitas, con qué frecuencia y en qué formato, según tu presupuesto.'],
    ['¿Quién ve la información de mi obra?', 'Solo las personas que tú autorices. Tu cliente puede tener un acceso de solo lectura y tú eliges qué herramientas ve. Los respaldos son periódicos y cada proyecto tiene su propio espacio.'],
    ['¿Puedo contratar solo una parte del servicio?', 'Sí. Puedes asignarnos un solo frente (por ejemplo materiales o reportes) y ampliar el alcance cuando lo necesites.'],
    ['¿Cada cuánto recibo informes?', 'Lo defines tú: semanal, quincenal o por junta. Además puedes consultar el avance en cualquier momento desde la plataforma.'],
    ['¿Y si ya uso ProjectWeb por mi cuenta?', 'Mejor aún: trabajamos dentro de tu mismo proyecto y tu equipo ve en vivo lo que actualizamos. También podemos capacitarlos.'],
    ['¿Cómo se cotiza?', 'Según el tamaño de la obra, el alcance y la frecuencia del seguimiento. Cuéntanos de tu proyecto y te proponemos un esquema.'],
];
?>

<?php include __DIR__ . '/_nav.php'; ?>

<main id="contenido">
<section class="lp-hero lp-hero-srv" style="--hero: url('/build/img/landing/campo.jpg')">
    <span class="lp-orb lp-orb1"></span><span class="lp-orb lp-orb2"></span>
    <div class="lp-wrap lp-srv-hero">
        <div class="lp-rev">
            <span class="lp-pill"><i class="fa-solid fa-handshake-angle"></i> Servicios administrativos de obra</span>
            <h1>Tú construyes.<br><span class="lp-grad">Nosotros mantenemos el control.</span></h1>
            <p class="lp-lead">Nos encargamos de la administración y el seguimiento de tu obra sobre una plataforma propia. La experiencia técnica de cada disciplina la aporta tu equipo —residente, supervisor o empresa—; nosotros mantenemos la información al día, ordenada y lista para decidir.</p>
            <div class="lp-cta">
                <?php if ($whatsappUrl): ?><a href="<?php echo s($whatsappUrl); ?>" class="lp-btn lp-btn-wa lp-btn-lg" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Hablar por WhatsApp</a><?php endif; ?>
                <a href="#modalidades" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-rocket"></i> Ver modalidades</a>
                <a href="#que-hacemos" class="lp-btn lp-btn-ghost lp-btn-lg">Ver qué ofrecemos</a>
            </div>
            <ul class="lp-check">
                <li><i class="fa-solid fa-circle-check"></i> Informes semanales o por junta</li>
                <li><i class="fa-solid fa-circle-check"></i> Tu equipo conserva la responsabilidad técnica</li>
                <li><i class="fa-solid fa-circle-check"></i> Tu cliente consulta en solo lectura</li>
                <li><i class="fa-solid fa-circle-check"></i> Alcance a tu medida</li>
            </ul>
        </div>
        <aside class="lp-srv-panel lp-rev" aria-label="Ejemplo ilustrativo de un tablero de seguimiento">
            <div class="lp-srv-panel-top"><i></i><i></i><i></i><span>Tablero de seguimiento · ejemplo</span></div>
            <div class="lp-srv-kpis">
                <div><b>68%</b><small>Avance real</small></div>
                <div><b>0.97</b><small>SPI</small></div>
                <div><b class="lp-warn">6</b><small>Restricciones</small></div>
            </div>
            <ul class="lp-srv-sem">
                <li><span class="lp-dot ok"></span> Estructura <em>En tiempo</em></li>
                <li><span class="lp-dot mid"></span> Instalación eléctrica <em>Vigilar</em></li>
                <li><span class="lp-dot bad"></span> Equipo de A/A <em>Compra vencida</em></li>
                <li><span class="lp-dot ok"></span> Acabados <em>En tiempo</em></li>
            </ul>
            <p class="lp-srv-panel-nota"><i class="fa-solid fa-circle-info"></i> Ilustración con datos de ejemplo.</p>
        </aside>
    </div>
</section>

<section class="lp-stats">
    <div class="lp-wrap lp-stats-in">
        <?php foreach ($cifras as $i => [$n, $t]): ?>
            <div class="lp-rev" style="--d:<?php echo $i * 80; ?>ms"><b><?php echo s($n); ?></b><span><?php echo s($t); ?></span></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="lp-sec" id="para-quien">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Antes de contratar</span>
            <h2>¿Esto es para ti?</h2>
            <p>Somos un equipo administrativo de seguimiento, no una constructora ni una consultoría de ingeniería. Decirlo claro ahorra juntas y malentendidos.</p>
        </div>
        <div class="lp-dos-columnas lp-rev">
            <article class="lp-panel-si">
                <h3><i class="fa-solid fa-circle-check"></i> Es para ti si…</h3>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Tienes una o varias obras y la información vive en Excel, WhatsApp y correos.</li>
                    <li><i class="fa-solid fa-check"></i> Tu residente o supervisor llena reportes al final del mes y nadie los cruza con el programa.</li>
                    <li><i class="fa-solid fa-check"></i> Quieres que alguien mantenga el programa, los materiales y los documentos al día.</li>
                    <li><i class="fa-solid fa-check"></i> Necesitas dar visibilidad al cliente o a dirección sin dar acceso de edición.</li>
                </ul>
            </article>
            <article class="lp-panel-no">
                <h3><i class="fa-solid fa-circle-xmark"></i> No es para ti si…</h3>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-xmark"></i> Buscas que alguien construya o dirija la obra por ti.</li>
                    <li><i class="fa-solid fa-xmark"></i> Necesitas ingeniería de detalle, cálculo estructural o firmas de responsiva.</li>
                    <li><i class="fa-solid fa-xmark"></i> Quieres un Gantt suelto sin compartir información con tu equipo.</li>
                    <li><i class="fa-solid fa-xmark"></i> No puedes compartir avances, fotos o documentos con nosotros.</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<section class="lp-sec lp-alt" id="que-hacemos">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Qué podemos llevar por ti</span>
            <h2>El seguimiento de obra,<br>en manos de un equipo dedicado</h2>
            <p>Doce frentes administrativos que podemos asumir total o parcialmente. Se ajustan al tamaño, la etapa y el presupuesto de cada proyecto.</p>
        </div>
        <div class="lp-rol lp-rev">
            <i class="fa-solid fa-people-arrows"></i>
            <div>
                <h3>Nosotros administramos; tu equipo aporta la experiencia técnica</h3>
                <p>Todo lo que llevamos va acompañado de la experiencia de tu empresa, supervisor o residente: ellos comparten la información de la disciplina y validan las decisiones. Nosotros actualizamos los datos, damos seguimiento, preparamos los reportes y mantenemos el control administrativo. No sustituimos la responsabilidad técnica de la obra.</p>
            </div>
        </div>
        <div class="lp-frentes-servicio">
            <?php foreach ($grupos as $grupo => $items): ?>
                <div class="lp-frente-grupo lp-rev">
                    <h3 class="lp-frente-grupo-titulo"><i class="fa-solid fa-layer-group"></i> <?php echo s($grupo); ?> <span><?php echo count($items); ?></span></h3>
                    <div class="lp-grid lp-grid-3">
                        <?php foreach ($items as $i => [$ico, $t, $d, $tags]): ?>
                            <article class="lp-card">
                                <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                                <h3><?php echo s($t); ?></h3>
                                <p><?php echo s($d); ?></p>
                                <ul class="lp-tags"><?php foreach ($tags as $g): ?><li><?php echo s($g); ?></li><?php endforeach; ?></ul>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec" id="antes-ahora">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Antes y ahora</span>
            <h2>De apagar incendios con archivos sueltos<br>a dirigir la obra con un solo tablero</h2>
            <p>El problema no es la falta de datos: es que viven en el programa de una persona, en el chat de la cuadrilla y en el Word de la junta. Cuando el seguimiento se centraliza, cambia lo que puedes ver y decidir.</p>
        </div>
        <div class="lp-antes-grid">
            <?php foreach ($antesAhora as $i => [$ico, $a, $b]): ?>
                <article class="lp-antes-card lp-rev" style="--d:<?php echo ($i % 3) * 70; ?>ms">
                    <span class="lp-antes-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <div class="lp-antes-fila lp-antes-fila-no"><span class="lp-antes-tag">Antes</span><p><?php echo s($a); ?></p></div>
                    <div class="lp-antes-fila lp-antes-fila-si"><span class="lp-antes-tag">Ahora</span><p><?php echo s($b); ?></p></div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="lp-antes-cierre lp-rev"><i class="fa-solid fa-arrow-trend-up"></i> Una sola fuente de verdad, menos retrabajo y decisiones con los datos del día.</p>
    </div>
</section>

<section class="lp-sec lp-alt" id="entregables">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Lo que recibes</span>
            <h2>Entregables base,<br>más lo que tu obra necesite</h2>
            <p>No todos los proyectos necesitan lo mismo. Esta matriz compara los tres niveles; con cada cliente acordamos el alcance, la frecuencia y el formato.</p>
        </div>
        <div class="lp-cmp-wrap lp-rev">
            <table class="lp-cmp lp-matriz">
                <thead>
                    <tr><th>Entregable</th><th>Frecuencia</th><th>Esencial</th><th class="lp-cmp-us">Completo</th><th>Consolidado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($entregables as [$nombre, $desc, $freq, $es, $co, $cn]):
                    $celda = function ($v) {
                        if ($v === 'check') return '<i class="fa-solid fa-circle-check lp-cmp-ok"></i> <span>Incluido</span>';
                        if ($v === 'base') return '<span class="lp-nivel lp-nivel-base">Base</span>';
                        return '<span class="lp-nivel lp-nivel-medida">A medida</span>';
                    };
                ?>
                    <tr>
                        <th scope="row"><?php echo s($nombre); ?><small><?php echo s($desc); ?></small></th>
                        <td data-label="Frecuencia"><?php echo s($freq); ?></td>
                        <td data-label="Esencial"><?php echo $celda($es); ?></td>
                        <td class="lp-cmp-us" data-label="Completo"><?php echo $celda($co); ?></td>
                        <td data-label="Consolidado"><?php echo $celda($cn); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="lp-niveles-nota lp-rev"><span class="lp-nivel lp-nivel-base">Base</span> incluido en cualquier modalidad. <span class="lp-nivel lp-nivel-medida">A medida</span> se cotiza según alcance, frecuencia y presupuesto. <i class="fa-solid fa-circle-info"></i> La matriz es orientativa; el alcance final se firma contigo.</p>

        <div class="lp-formatos lp-rev">
            <div class="lp-formatos-txt">
                <span class="lp-eyebrow">Formatos de entrega</span>
                <h3>En el formato que ya usa tu equipo</h3>
                <p>No tienes que adaptar tu forma de trabajar: entregamos el mismo dato en PDF, Excel, Word, XML de MS Project o en el formato que pida tu cliente.</p>
            </div>
            <div class="lp-formatos-grupos">
                <?php foreach ($formatos as [$grupo, $items]): ?>
                    <div class="lp-formatos-grupo">
                        <h4><?php echo s($grupo); ?></h4>
                        <ul><?php foreach ($items as $it): ?><li><?php echo s($it); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="lp-sec" id="modalidades">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Modalidades</span>
            <h2>Elige cuánto quieres delegar</h2>
            <p>Precios de entrada por obra en pesos mexicanos. El alcance, la frecuencia y los entregables se ajustan a tu presupuesto; el piloto y el diagnóstico se acreditan al siguiente nivel.</p>
        </div>

        <div class="lp-grid lp-grid-3 lp-entradas lp-rev">
            <?php foreach ($entrada as $i => [$ico, $t, $d, $precio, $nota, $items]): ?>
                <article class="lp-card lp-entrada-card">
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <span class="lp-nivel lp-nivel-medida"><?php echo s($nota); ?></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                    <p class="lp-precio lp-precio-servicio"><b><?php echo s($fmt($precio)); ?></b><span> MXN</span></p>
                    <ul class="lp-list"><?php foreach ($items as $it): ?><li><i class="fa-solid fa-check"></i> <?php echo s($it); ?></li><?php endforeach; ?></ul>
                    <a href="/?plan=<?php echo urlencode($t); ?>#contacto" class="lp-btn lp-btn-ghost lp-btn-block">Quiero empezar</a>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="lp-mods">
            <?php foreach ($niveles as $i => [$ico, $t, $d, $precio, $desde, $items]): ?>
                <article class="lp-mod lp-rev <?php echo $t === 'Completo' ? 'lp-mod-top' : ''; ?>" style="--d:<?php echo $i * 80; ?>ms">
                    <?php if ($t === 'Completo'): ?><span class="lp-badge">Más elegida</span><?php endif; ?>
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <span class="lp-mod-nivel">Nivel</span>
                    <h3><?php echo s($t); ?></h3>
                    <p class="lp-mod-lema"><?php echo s($d); ?></p>
                    <p class="lp-precio lp-precio-servicio"><b><?php echo s($desde === 'desde' ? 'desde ' : ''); ?><?php echo s($fmt($precio)); ?></b><span> MXN / mes</span></p>
                    <ul class="lp-list"><?php foreach ($items as $it): ?><li><i class="fa-solid fa-check"></i> <?php echo s($it); ?></li><?php endforeach; ?></ul>
                    <?php $planContacto = ['Esencial' => 'Seguimiento quincenal', 'Completo' => 'Seguimiento semanal', 'Consolidado' => 'Oficina de control'][$t] ?? 'Aún no lo sé'; ?>
                    <a href="/?plan=<?php echo urlencode($planContacto); ?>#contacto" class="lp-btn <?php echo $t === 'Completo' ? 'lp-btn-primary' : 'lp-btn-ghost'; ?>">Cotizar <?php echo s($t); ?></a>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="lp-mods-nota lp-rev">
            <div>
                <h3>¿No encaja en ninguna?</h3>
                <p>Armamos un esquema mixto: tú llevas una parte y nosotros otra. El alcance, la frecuencia y los entregables se ajustan a tu presupuesto.</p>
            </div>
            <a href="/?plan=<?php echo urlencode('Aún no lo sé'); ?>#contacto" class="lp-btn lp-btn-primary"><i class="fa-solid fa-paper-plane"></i> Armar mi esquema</a>
        </div>
    </div>
</section>

<section class="lp-sec lp-alt" id="ejemplo">
    <div class="lp-wrap">
        <div class="lp-show lp-rev">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Entregable de muestra</span>
                <h2>Así se ve un informe ejecutivo</h2>
                <p>Un solo documento para dirección y cliente: avance real, semáforo por frente, desviaciones explicadas y las decisiones que necesitas tomar. Sin 40 hojas de Excel adjuntas.</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Avance, SPI y curva S del periodo</li>
                    <li><i class="fa-solid fa-check"></i> Restricciones y materiales que pueden frenar la obra</li>
                    <li><i class="fa-solid fa-check"></i> Cambios, riesgos y decisiones pendientes</li>
                    <li><i class="fa-solid fa-check"></i> PDF para enviar y versión en la plataforma</li>
                </ul>
                <p class="text-muted text-sm" style="margin-top:1.6rem"><i class="fa-solid fa-circle-info"></i> Ejemplo ilustrativo con datos de referencia, no de un cliente real.</p>
            </div>
            <figure class="lp-informe lp-fig">
                <div class="lp-informe-top"><span>Informe ejecutivo · Semana 42</span><em>Ejemplo</em></div>
                <div class="lp-informe-kpis">
                    <div><b>68%</b><small>Avance real</small></div>
                    <div><b>0.97</b><small>SPI</small></div>
                    <div><b class="lp-warn">6</b><small>Restricciones</small></div>
                </div>
                <div class="lp-informe-barras">
                    <div><span>Estructura</span><i style="--w:82%;--c:#10b981"></i><b>82%</b></div>
                    <div><span>Instalación eléctrica</span><i style="--w:61%;--c:#f59e0b"></i><b>61%</b></div>
                    <div><span>Equipo de A/A</span><i style="--w:34%;--c:#ef4444"></i><b>34%</b></div>
                    <div><span>Acabados</span><i style="--w:55%;--c:#00b4d8"></i><b>55%</b></div>
                </div>
                <ul class="lp-informe-decisiones">
                    <li><i class="fa-solid fa-triangle-exclamation"></i> Compra de chiller vencida: decide proveedor alterno.</li>
                    <li><i class="fa-solid fa-file-signature"></i> OC-004 impacta 3 días la ruta crítica.</li>
                    <li><i class="fa-solid fa-circle-check"></i> Frente de estructura puede liberar nivel 8.</li>
                </ul>
            </figure>
        </div>
    </div>
</section>

<section class="lp-steps">
    <div class="lp-wrap">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Cómo trabajamos</span><h2>De la primera llamada<br>al seguimiento continuo</h2></div>
        <?php $pasos = [
            ['Diagnóstico', 'Revisamos tu programa, tus documentos y cómo llevas hoy el seguimiento. Definimos contigo el alcance.'],
            ['Puesta en marcha', 'Cargamos tu proyecto, ordenamos la información y acordamos reportes, juntas y responsables.'],
            ['Seguimiento periódico', 'Actualizamos avance, materiales, restricciones y cambios con la frecuencia que necesites.'],
            ['Informes y decisiones', 'Te entregamos un panorama claro y alertas a tiempo para decidir mejor, sin dejar la obra.'],
        ]; ?>
        <ol class="lp-ol lp-ol-4">
            <?php foreach ($pasos as $i => [$t, $d]): ?>
                <li class="lp-rev" style="--d:<?php echo $i * 90; ?>ms"><b><?php echo $i + 1; ?></b><h3><?php echo s($t); ?></h3><p><?php echo s($d); ?></p></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="lp-sec" id="faq">
    <div class="lp-wrap lp-narrow">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Preguntas frecuentes</span><h2>Sobre el servicio</h2></div>
        <div class="lp-faq lp-rev">
            <?php foreach ($faq as [$q, $a]): ?><details><summary><?php echo s($q); ?></summary><p><?php echo s($a); ?></p></details><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-band" style="--bg: url('/build/img/landing/campo.jpg')">
    <div class="lp-wrap lp-rev">
        <h2>Hablemos de tu próxima obra</h2>
        <p>Cuéntanos qué necesitas dar seguimiento y te proponemos un esquema a tu medida.</p>
        <div class="lp-cta lp-cta-c">
            <?php if ($whatsappUrl): ?><a href="<?php echo s($whatsappUrl); ?>" class="lp-btn lp-btn-wa lp-btn-lg" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp <?php echo s($whatsapp); ?></a><?php endif; ?>
            <a href="/#contacto" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-paper-plane"></i> Contactar</a>
        </div>
    </div>
</section>
</main>

<footer class="lp-foot">
    <div class="lp-wrap">
        <span class="lp-logo"><span class="lp-logo-i"><i class="fa-solid fa-helmet-safety"></i></span> ProjectWeb</span>
        <span>© <?php echo date('Y'); ?> ProjectWeb · Sistema de programas de obra · <a href="/terminos" class="lp-foot-link">Términos y condiciones de uso</a></span>
        <?php if ($whatsappUrl): ?><a href="<?php echo s($whatsappUrl); ?>" class="lp-foot-link" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> <?php echo s($whatsapp); ?></a><?php endif; ?>
        <span class="lp-credit">Fotografías: <a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a></span>
    </div>
</footer>
