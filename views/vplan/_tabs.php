<?php // Barra del hub «Planos y láminas»: vPlan · Tablas adicionales · Gráficos · Símbolos.
// Se muestra en todas las vistas del vPlan y en la lista del Editor. Variables: $proyecto, $tab ('laminas'|'tablas').
$activaPlanos = ($tab ?? 'laminas') === 'tablas' ? '/vplan/tablas' : '/vplan';
require_once __DIR__ . '/../templates/herramientas-proyecto.php';
pestanas_herramienta('planos', $activaPlanos, $proyecto);
