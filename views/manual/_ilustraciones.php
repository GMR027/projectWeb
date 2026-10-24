<?php
// Ilustraciones del Manual: maquetas esquemáticas en SVG (sin datos reales) con globos numerados
// que el texto del manual referencia. Los colores salen de clases .fg-* (ver _lookahead.scss), así que siguen el tema claro/oscuro.
// Uso en el cuerpo de una sección: [[fig:nombre]]. Cada figura es [leyenda, svg].

if (!function_exists('mf_t')) {
    function mf_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
    function mf_r($x, $y, $w, $h, $c = 'fg-panel', $rx = 6): string { return "<rect class=\"$c\" x=\"$x\" y=\"$y\" width=\"$w\" height=\"$h\" rx=\"$rx\"/>"; }
    function mf_t($x, $y, $txt, $c = 'fg-txt', $sz = 12, $anc = 'start', $b = false): string {
        return "<text class=\"$c\" x=\"$x\" y=\"$y\" font-size=\"$sz\" text-anchor=\"$anc\"" . ($b ? ' font-weight="700"' : '') . '>' . mf_e($txt) . '</text>';
    }
    function mf_l($x1, $y1, $x2, $y2, $c = 'fg-line'): string { return "<line class=\"$c\" x1=\"$x1\" y1=\"$y1\" x2=\"$x2\" y2=\"$y2\"/>"; }
    /** Globo numerado (se explica en el texto) */
    function mf_n(int $n, $x, $y): string { return "<g><circle class=\"fg-globo\" cx=\"$x\" cy=\"$y\" r=\"10\"/>" . mf_t($x, $y + 4, (string)$n, 'fg-globo-t', 11, 'middle', true) . '</g>'; }
    function mf_btn($x, $y, $w, $txt, $c = 'fg-pri', $h = 24): string {
        return mf_r($x, $y, $w, $h, $c, 5) . mf_t($x + $w / 2, $y + $h / 2 + 4, $txt, $c === 'fg-ghost' ? 'fg-txt' : 'fg-blanco', 11, 'middle', true);
    }
    function mf_campo($x, $y, $w, $etq, $val = ''): string {
        return mf_t($x, $y, $etq, 'fg-mut', 10) . mf_r($x, $y + 5, $w, 22, 'fg-input', 4) . ($val !== '' ? mf_t($x + 8, $y + 20, $val, 'fg-txt', 11) : '');
    }
    function mf_chip($x, $y, $w, $txt, $c = 'fg-ok'): string { return mf_r($x, $y, $w, 18, $c, 9) . mf_t($x + $w / 2, $y + 13, $txt, 'fg-blanco', 10, 'middle', true); }
    function mf_flecha($x1, $y1, $x2, $y2): string { return "<line class=\"fg-flecha\" x1=\"$x1\" y1=\"$y1\" x2=\"$x2\" y2=\"$y2\" marker-end=\"url(#fg-pta)\"/>"; }
    function mf_caja($x, $y, $w, $h, $titulo, $sub = '', $c = 'fg-panel'): string {
        return mf_r($x, $y, $w, $h, $c, 8) . mf_t($x + $w / 2, $y + ($sub === '' ? $h / 2 + 4 : $h / 2 - 2), $titulo, 'fg-txt', 12, 'middle', true) . ($sub !== '' ? mf_t($x + $w / 2, $y + $h / 2 + 14, $sub, 'fg-mut', 10, 'middle') : '');
    }
    /** Barra lateral de la app: $items = textos, $act = índice resaltado */
    function mf_lateral($x, $y, $h, array $items, int $act = -1, int $w = 118): string {
        $o = mf_r($x, $y, $w, $h, 'fg-lat', 0) . mf_t($x + 12, $y + 22, '▲ ProjectWeb', 'fg-pri-t', 11, 'start', true);
        foreach ($items as $i => $it) {
            $yy = $y + 40 + $i * 20;
            if ($yy > $y + $h - 8) break;
            if ($i === $act) $o .= mf_r($x + 6, $yy - 12, $w - 12, 18, 'fg-act', 4);
            $o .= mf_t($x + 14, $yy, $it, $i === $act ? 'fg-blanco' : 'fg-mut', 10);
        }
        return $o;
    }
    /** Marco de ventana: contenido en coordenadas del lienzo (ancho $w, alto $h) */
    function mf_svg($w, $h, string $titulo, string $dentro, string $barra = ''): string {
        return "<svg class=\"fg-svg\" viewBox=\"0 0 $w $h\" role=\"img\" aria-label=\"" . mf_e($titulo) . "\" xmlns=\"http://www.w3.org/2000/svg\">"
            . '<defs><marker id="fg-pta" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" class="fg-pta"/></marker></defs>'
            . mf_r(0.5, 0.5, $w - 1, $h - 1, 'fg-fondo', 10)
            . mf_r(0.5, 0.5, $w - 1, 22, 'fg-barra', 10) . mf_r(0.5, 12, $w - 1, 11, 'fg-barra', 0)
            . '<circle class="fg-pto1" cx="14" cy="11" r="4"/><circle class="fg-pto2" cx="28" cy="11" r="4"/><circle class="fg-pto3" cx="42" cy="11" r="4"/>'
            . mf_t($w / 2, 15, $barra ?: $titulo, 'fg-mut', 10, 'middle')
            . $dentro . '</svg>';
    }
}

$menuApp = ['Inicio', 'Portafolio', 'Usuarios', 'Bandeja', 'Ajustes', 'Programa', 'Calendario', 'Lookahead', 'Centro de IA'];

return [

'pantalla' => ['Así se organiza la pantalla: ① menú lateral, ② encabezado del proyecto, ③ barra de herramientas, ④ contenido y ⑤ Manual.',
    mf_svg(640, 300, 'Organización de la pantalla', mf_lateral(0, 23, 277, $menuApp, 5)
        . mf_r(130, 34, 498, 54, 'fg-panel') . mf_t(144, 56, 'PO-001 · Torre Norte', 'fg-txt', 14, 'start', true) . mf_t(144, 74, '184 días · 6 meses · 01/02/2026 → 31/07/2026', 'fg-mut', 10)
        . mf_r(130, 96, 498, 24, 'fg-panel') . mf_t(142, 112, 'Programa  Dependencias  Lookahead  vPlan  Reporte  Bitácora  Exportar…', 'fg-mut', 10)
        . mf_r(130, 128, 498, 138, 'fg-panel') . mf_t(144, 148, 'Aquí aparece la herramienta que abriste', 'fg-mut', 11)
        . mf_r(144, 158, 470, 14, 'fg-input', 3) . mf_r(144, 178, 470, 14, 'fg-input', 3) . mf_r(144, 198, 470, 14, 'fg-input', 3) . mf_r(144, 218, 300, 14, 'fg-input', 3)
        . mf_r(6, 252, 106, 18, 'fg-act', 4) . mf_t(59, 265, 'Manual de usuario', 'fg-blanco', 10, 'middle')
        . mf_n(1, 124, 60) . mf_n(2, 612, 44) . mf_n(3, 612, 108) . mf_n(4, 612, 150) . mf_n(5, 100, 244))],

'flujo' => ['Orden recomendado para montar un proyecto nuevo.',
    mf_svg(640, 170, 'Flujo de trabajo recomendado',
        mf_caja(14, 50, 90, 52, 'Proyecto', 'crear') . mf_flecha(104, 76, 124, 76)
        . mf_caja(126, 50, 90, 52, 'Calendario', 'jornada + festivos') . mf_flecha(216, 76, 236, 76)
        . mf_caja(238, 50, 90, 52, 'Programa', 'partidas + act.') . mf_flecha(328, 76, 348, 76)
        . mf_caja(350, 50, 90, 52, 'Dependencias', 'ruta crítica') . mf_flecha(440, 76, 460, 76)
        . mf_caja(462, 50, 90, 52, 'Seguimiento', 'avance semanal') . mf_flecha(552, 76, 572, 76)
        . mf_caja(574, 50, 56, 52, 'Exportar')
        . mf_caja(126, 118, 140, 38, 'Materiales / MSR', '', 'fg-panel2') . mf_caja(280, 118, 140, 38, 'Reportes y bitácora', '', 'fg-panel2') . mf_caja(434, 118, 140, 38, 'Centro de IA', '', 'fg-panel2')
        . mf_t(14, 34, 'Una vez, al inicio', 'fg-pri-t', 11, 'start', true) . mf_t(462, 34, 'Cada semana', 'fg-pri-t', 11, 'start', true)
        . mf_n(1, 59, 44) . mf_n(2, 171, 44) . mf_n(3, 283, 44) . mf_n(4, 395, 44) . mf_n(5, 507, 44) . mf_n(6, 602, 44))],

'usuarios' => ['Formulario de usuario: ① datos y rol, ② límites del cliente, ③ proyectos con acceso, ④ herramientas del visitante.',
    mf_svg(640, 290, 'Formulario de usuario', mf_lateral(0, 23, 267, $menuApp, 2)
        . mf_t(134, 48, 'Nuevo usuario', 'fg-txt', 15, 'start', true)
        . mf_campo(134, 62, 190, 'Nombre completo *', 'Ana Pérez') . mf_campo(338, 62, 190, 'Correo *', 'ana@empresa.com')
        . mf_campo(134, 106, 190, 'Rol', 'Cliente ▾') . mf_campo(338, 106, 190, 'Contraseña / repetir', '••••••••')
        . mf_campo(134, 150, 90, 'Límite de proyectos', '3') . mf_campo(238, 150, 130, 'Espacio por proyecto (MB)', '1024')
        . mf_t(386, 160, 'Logo de la empresa', 'fg-mut', 10) . mf_btn(386, 166, 110, 'Elegir imagen…', 'fg-ghost')
        . mf_r(134, 196, 240, 72, 'fg-panel') . mf_t(144, 212, 'Proyectos con acceso', 'fg-mut', 10)
        . mf_r(146, 220, 10, 10, 'fg-chk', 2) . mf_t(162, 229, 'PO-001 Torre Norte', 'fg-txt', 10) . mf_r(146, 236, 10, 10, 'fg-chk', 2) . mf_t(162, 245, 'PO-002 Plaza Sur', 'fg-txt', 10) . mf_r(146, 252, 10, 10, 'fg-input', 2) . mf_t(162, 261, 'PO-003 Nave 4', 'fg-txt', 10)
        . mf_r(386, 196, 240, 72, 'fg-panel') . mf_t(396, 212, 'Herramientas (solo visitante)', 'fg-mut', 10)
        . mf_r(398, 220, 10, 10, 'fg-chk', 2) . mf_t(414, 229, 'Programa', 'fg-txt', 10) . mf_r(398, 236, 10, 10, 'fg-chk', 2) . mf_t(414, 245, 'vPlan', 'fg-txt', 10) . mf_r(500, 220, 10, 10, 'fg-input', 2) . mf_t(516, 229, 'Archivo', 'fg-txt', 10)
        . mf_btn(540, 62, 86, 'Guardar')
        . mf_n(1, 128, 66) . mf_n(2, 128, 154) . mf_n(3, 128, 200) . mf_n(4, 380, 200))],

'bandeja' => ['Bandeja de entrada: ① filtros, ② lista con los no leídos en negritas, ③ detalle y respuesta.',
    mf_svg(640, 250, 'Bandeja de entrada', mf_lateral(0, 23, 227, $menuApp, 3)
        . mf_btn(134, 36, 56, 'Todos') . mf_btn(196, 36, 66, 'Contacto', 'fg-ghost') . mf_btn(268, 36, 64, 'Soporte', 'fg-ghost')
        . mf_r(134, 70, 200, 160, 'fg-panel')
        . mf_r(142, 78, 184, 34, 'fg-act2', 4) . mf_t(150, 92, '● Laura R. · Duda de planos', 'fg-txt', 10, 'start', true) . mf_t(150, 106, 'Soporte · hace 2 h', 'fg-mut', 9)
        . mf_t(150, 134, 'Carlos M. · Cotización', 'fg-txt', 10) . mf_t(150, 148, 'Contacto · ayer', 'fg-mut', 9) . mf_l(142, 118, 326, 118) . mf_l(142, 154, 326, 154)
        . mf_t(150, 176, 'Obra Sur · Acceso', 'fg-txt', 10) . mf_t(150, 190, 'Soporte · hace 3 d', 'fg-mut', 9)
        . mf_r(344, 70, 284, 160, 'fg-panel') . mf_t(356, 90, 'Duda de planos', 'fg-txt', 13, 'start', true) . mf_r(356, 100, 258, 34, 'fg-input', 4)
        . mf_t(356, 152, 'Responder', 'fg-mut', 10) . mf_campo(356, 156, 258, 'Título', '') . mf_btn(356, 196, 90, '+ Foto', 'fg-ghost') . mf_btn(540, 196, 74, 'Enviar')
        . mf_n(1, 128, 48) . mf_n(2, 128, 94) . mf_n(3, 350, 90))],

'respaldos' => ['Ajustes del sistema: ① espacio usado, ② evolución, ③ respaldos manuales y automáticos.',
    mf_svg(640, 270, 'Ajustes del sistema', mf_lateral(0, 23, 247, $menuApp, 4)
        . mf_r(134, 34, 494, 64, 'fg-panel') . mf_t(146, 54, 'Volumen de archivos', 'fg-txt', 12, 'start', true) . mf_r(146, 62, 470, 12, 'fg-input', 6) . mf_r(146, 62, 290, 12, 'fg-pri', 6) . mf_t(146, 90, '4.1 GB de 8 GB · 62 %', 'fg-mut', 10)
        . mf_r(134, 106, 240, 66, 'fg-panel') . mf_t(146, 124, 'Evolución del espacio', 'fg-txt', 11, 'start', true) . '<polyline class="fg-curva" points="146,160 190,154 230,150 270,142 310,138 360,128" fill="none"/>'
        . mf_r(384, 106, 244, 66, 'fg-panel') . mf_t(396, 124, 'Disco del servidor', 'fg-txt', 11, 'start', true) . mf_r(396, 134, 220, 10, 'fg-input', 5) . mf_r(396, 134, 120, 10, 'fg-sec', 5)
        . mf_r(134, 180, 494, 80, 'fg-panel') . mf_t(146, 198, 'Respaldos', 'fg-txt', 12, 'start', true) . mf_btn(456, 186, 160, 'Crear respaldo ahora')
        . mf_t(146, 220, '2026_10_17_auto.zip   120 MB', 'fg-txt', 10) . mf_btn(430, 207, 90, 'Descargar', 'fg-ghost', 20) . mf_btn(526, 207, 90, 'Restaurar', 'fg-bad', 20)
        . mf_t(146, 244, '☑ Respaldo diario automático · conservar 7', 'fg-mut', 10)
        . mf_n(1, 128, 56) . mf_n(2, 128, 124) . mf_n(3, 128, 200))],

'programa' => ['Programa de obra: ① acciones, ② tabla WBS con partidas y actividades, ③ Gantt por semanas.',
    mf_svg(640, 270, 'Programa de obra', mf_lateral(0, 23, 247, $menuApp, 5)
        . mf_btn(134, 32, 100, '+ Nueva Partida') . mf_btn(240, 32, 112, 'Ordenar y renum.', 'fg-ghost') . mf_btn(358, 32, 70, 'Filtros', 'fg-ghost')
        . mf_r(134, 64, 240, 190, 'fg-panel') . mf_t(144, 82, 'WBS', 'fg-mut', 10, 'start', true) . mf_t(184, 82, 'Actividad', 'fg-mut', 10, 'start', true) . mf_t(330, 82, '%', 'fg-mut', 10, 'start', true)
        . mf_l(138, 88, 370, 88) . mf_r(138, 92, 232, 20, 'fg-act2', 3) . mf_t(144, 106, '1.0', 'fg-txt', 10, 'start', true) . mf_t(184, 106, 'Cimentación', 'fg-txt', 10, 'start', true)
        . mf_t(150, 128, '1.1', 'fg-txt', 10) . mf_t(184, 128, 'Excavación', 'fg-txt', 10) . mf_chip(326, 116, 38, '100%', 'fg-ok')
        . mf_t(150, 150, '1.2', 'fg-txt', 10) . mf_t(184, 150, 'Armado de acero', 'fg-txt', 10) . mf_chip(326, 138, 38, '60%', 'fg-warn')
        . mf_t(150, 172, '1.3', 'fg-txt', 10) . mf_t(184, 172, 'Colado', 'fg-txt', 10) . mf_chip(326, 160, 38, '0%', 'fg-mut2')
        . mf_r(144, 188, 224, 20, 'fg-act2', 3) . mf_t(144, 202, '2.0', 'fg-txt', 10, 'start', true) . mf_t(184, 202, 'Estructura', 'fg-txt', 10, 'start', true)
        . mf_r(384, 64, 244, 190, 'fg-panel') . mf_t(392, 82, 'S1    S2    S3    S4    S5    S6', 'fg-mut', 10)
        . mf_r(392, 96, 40, 12, 'fg-pri', 3) . mf_r(392, 118, 36, 12, 'fg-ok', 3) . mf_r(420, 140, 70, 12, 'fg-warn', 3) . mf_r(480, 162, 64, 12, 'fg-sec', 3) . mf_r(496, 196, 100, 12, 'fg-pri', 3)
        . mf_l(520, 88, 520, 246, 'fg-hoy') . mf_t(520, 262, 'hoy', 'fg-pri-t', 9, 'middle')
        . mf_n(1, 438, 46) . mf_n(2, 128, 100) . mf_n(3, 614, 76))],

'escritorio' => ['App de escritorio: ① menú de Herramientas IA agrupado, ② panel de la herramienta, ③ Ajustes, ④ conexión de IA activa.',
    mf_svg(640, 290, 'App de escritorio', mf_r(0, 23, 150, 266, 'fg-lat', 0) . mf_t(12, 44, 'Project Web', 'fg-pri-t', 12, 'start', true)
        . mf_t(12, 66, 'Resultados y control', 'fg-mut', 9, 'start', true) . mf_t(20, 82, 'Análisis inteligente', 'fg-txt', 10)
        . mf_t(12, 102, 'Planeación', 'fg-mut', 9, 'start', true) . mf_r(8, 108, 134, 18, 'fg-act', 4) . mf_t(20, 121, 'Programador de act.', 'fg-blanco', 10)
        . mf_t(12, 142, 'Ensamblador', 'fg-mut', 9, 'start', true) . mf_t(20, 158, 'Ensamblador de docs', 'fg-txt', 10) . mf_t(20, 174, 'Ordenar por fechas', 'fg-txt', 10)
        . mf_t(12, 194, 'Revisión de datos', 'fg-mut', 9, 'start', true) . mf_t(20, 210, 'Calidad de datos', 'fg-txt', 10) . mf_chip(100, 198, 42, 'al día', 'fg-ok')
        . mf_t(12, 232, 'Project Web (páginas)', 'fg-mut', 9, 'start', true) . mf_btn(8, 258, 134, '⚙ Ajustes', 'fg-ghost')
        . mf_r(150, 23, 490, 28, 'fg-panel', 0) . mf_t(164, 42, 'Proyecto: Torre Norte ▾', 'fg-txt', 11) . mf_chip(500, 28, 126, 'IA: Claude (CLI) ▾', 'fg-sec')
        . mf_r(164, 62, 462, 214, 'fg-panel') . mf_t(176, 84, 'Programador de actividades', 'fg-txt', 14, 'start', true)
        . mf_t(176, 102, 'Revisa el programa y propone relaciones entre actividades.', 'fg-mut', 10)
        . mf_btn(176, 116, 130, 'Analizar proyecto') . mf_r(176, 152, 438, 110, 'fg-input', 6) . mf_t(190, 174, 'Resultados y propuestas aparecen aquí…', 'fg-mut', 10)
        . mf_n(1, 140, 66) . mf_n(2, 608, 80) . mf_n(3, 130, 270) . mf_n(4, 490, 36))],

'ia-conexion' => ['Ajustes → Configuración de IA: ① pestañas por tipo de conexión, ② modelo o servicio, ③ qué conexión usa cada tarea.',
    mf_svg(640, 270, 'Configuración de IA', mf_r(0, 23, 150, 247, 'fg-lat', 0) . mf_t(12, 44, 'Ajustes', 'fg-pri-t', 12, 'start', true)
        . mf_t(14, 70, '🤖 Configuración de IA', 'fg-txt', 10, 'start', true) . mf_t(14, 90, '🎤 Voz', 'fg-mut', 10) . mf_t(14, 108, '🎨 Apariencia', 'fg-mut', 10) . mf_t(14, 126, '💾 Almacenamiento', 'fg-mut', 10) . mf_t(14, 144, '⬆ Servidor', 'fg-mut', 10)
        . mf_btn(164, 34, 80, 'IA local') . mf_btn(250, 34, 90, 'Suscripción', 'fg-ghost') . mf_btn(346, 34, 50, 'API', 'fg-ghost') . mf_btn(402, 34, 140, 'Conexiones guardadas', 'fg-ghost')
        . mf_r(164, 68, 464, 100, 'fg-panel') . mf_campo(176, 84, 200, 'Modelo', 'qwen2.5:14b ▾') . mf_campo(390, 84, 150, 'Clave / CLI', '••••••••') . mf_btn(176, 128, 110, 'Probar conexión', 'fg-ghost') . mf_btn(294, 128, 90, 'Guardar')
        . mf_r(164, 178, 464, 80, 'fg-panel') . mf_t(176, 196, 'Qué conexión usa cada tarea', 'fg-txt', 11, 'start', true)
        . mf_campo(176, 214, 200, 'Revisiones, verificaciones y chat', 'Local ▾') . mf_campo(390, 214, 200, 'Planeación e informes', 'Suscripción ▾')
        . mf_n(1, 158, 46) . mf_n(2, 158, 96) . mf_n(3, 158, 200))],

'ciclo-ia' => ['Ciclo de toda herramienta de IA: la IA propone, tú decides y todo queda registrado y se puede revertir.',
    mf_svg(640, 190, 'Ciclo de la IA',
        mf_caja(14, 60, 100, 56, 'Analiza', 'sistema + IA') . mf_flecha(114, 88, 138, 88)
        . mf_caja(140, 60, 100, 56, 'Propone', 'con casillas') . mf_flecha(240, 88, 264, 88)
        . mf_caja(266, 60, 100, 56, 'Tú decides', 'aceptar / rechazar') . mf_flecha(366, 88, 390, 88)
        . mf_caja(392, 60, 110, 56, 'Se aplica', 'guarda el «antes»') . mf_flecha(502, 88, 526, 88)
        . mf_caja(528, 60, 100, 56, 'Auditoría', 'revertir') . mf_t(320, 150, 'Nada cambia en tus datos sin tu aceptación', 'fg-pri-t', 13, 'middle', true)
        . mf_t(320, 170, 'Las cifras y fechas las calcula el servidor; la IA solo clasifica, explica y redacta.', 'fg-mut', 11, 'middle')
        . mf_n(1, 64, 54) . mf_n(2, 190, 54) . mf_n(3, 316, 54) . mf_n(4, 447, 54) . mf_n(5, 578, 54))],

'centro-ia' => ['Centro de IA: ① pestañas, ② propuestas pendientes con severidad, ③ aceptar o rechazar.',
    mf_svg(640, 250, 'Centro de IA', mf_lateral(0, 23, 227, $menuApp, 8)
        . mf_btn(134, 32, 84, 'Propuestas') . mf_btn(222, 32, 66, 'Informes', 'fg-ghost') . mf_btn(292, 32, 90, 'Grabaciones', 'fg-ghost') . mf_btn(386, 32, 150, 'Auditoría de la IA', 'fg-ghost') . mf_btn(540, 32, 80, 'Biblioteca', 'fg-ghost')
        . mf_r(134, 66, 494, 164, 'fg-panel')
        . mf_t(146, 86, 'Herramienta', 'fg-mut', 10, 'start', true) . mf_t(280, 86, 'Propuesta', 'fg-mut', 10, 'start', true) . mf_t(450, 86, 'Severidad', 'fg-mut', 10, 'start', true) . mf_l(140, 92, 620, 92)
        . mf_t(146, 114, 'Inconsistencias', 'fg-txt', 10) . mf_t(280, 114, 'Act. 3.4 fin antes de inicio', 'fg-txt', 10) . mf_chip(450, 102, 50, 'alta', 'fg-bad') . mf_btn(520, 100, 46, '✔', 'fg-ok', 20) . mf_btn(572, 100, 46, '✖', 'fg-ghost', 20)
        . mf_t(146, 144, 'Materiales', 'fg-txt', 10) . mf_t(280, 144, 'Cemento: compra vencida', 'fg-txt', 10) . mf_chip(450, 132, 50, 'media', 'fg-warn') . mf_btn(520, 130, 46, '✔', 'fg-ok', 20) . mf_btn(572, 130, 46, '✖', 'fg-ghost', 20)
        . mf_t(146, 174, 'Programador', 'fg-txt', 10) . mf_t(280, 174, 'Adelantar 2.1 junto con 2.2', 'fg-txt', 10) . mf_chip(450, 162, 50, 'baja', 'fg-sec') . mf_btn(520, 160, 46, '✔', 'fg-ok', 20) . mf_btn(572, 160, 46, '✖', 'fg-ghost', 20)
        . mf_n(1, 128, 46) . mf_n(2, 424, 108) . mf_n(3, 594, 98))],

'programador' => ['Programador: ① clasifica y empaca sin IA, ② la IA analiza cada paquete, ③ integra y revisa, ④ comparas contra el programa actual.',
    mf_svg(640, 200, 'Programador de actividades',
        mf_caja(14, 50, 138, 60, 'Fase 1 · sin IA', 'clasifica y empaca') . mf_flecha(152, 80, 172, 80)
        . mf_caja(174, 50, 138, 60, 'Fase 2 · IA', 'analiza cada paquete') . mf_flecha(312, 80, 332, 80)
        . mf_caja(334, 50, 138, 60, 'Fases 3-4 · IA', 'integra y revisa') . mf_flecha(472, 80, 492, 80)
        . mf_caja(494, 50, 134, 60, 'Propuesta', 'se guarda, no se aplica')
        . mf_r(14, 126, 614, 58, 'fg-panel') . mf_t(26, 146, 'Comparación', 'fg-txt', 11, 'start', true) . mf_r(120, 138, 150, 10, 'fg-mut2', 5) . mf_r(120, 154, 100, 10, 'fg-pri', 5)
        . mf_t(282, 146, 'Actual: termina 31/07', 'fg-mut', 10) . mf_t(282, 162, 'Propuesto: termina 17/07 (−10 días hábiles)', 'fg-ok-t', 10, 'start', true)
        . mf_n(1, 83, 44) . mf_n(2, 243, 44) . mf_n(3, 403, 44) . mf_n(4, 561, 44))],

'inconsistencias' => ['Inconsistencias: ① «Revisar ahora (sin IA)», ② filtros, ③ hallazgo con su solución propuesta y botones.',
    mf_svg(640, 240, 'Inconsistencias de proyecto', mf_lateral(0, 23, 217, $menuApp, -1)
        . mf_btn(134, 32, 150, 'Revisar ahora (sin IA)') . mf_t(300, 48, 'Análisis: 17/10 · 5 hallazgos', 'fg-mut', 10)
        . mf_campo(134, 70, 90, 'Estado', 'Abiertos ▾') . mf_campo(234, 70, 90, 'Severidad', 'Todas ▾') . mf_campo(334, 70, 110, 'Categoría', 'Todas ▾') . mf_campo(454, 70, 110, 'Detectada por', 'Todos ▾')
        . mf_r(134, 104, 494, 126, 'fg-panel') . mf_chip(146, 114, 50, 'alta', 'fg-bad') . mf_t(204, 123, '3.4 «Colado losa»: fin (02/03) anterior al inicio (09/03)', 'fg-txt', 11, 'start', true)
        . mf_t(146, 148, 'Solución: ajustar fechas → fin = 13/03 (según calendario)', 'fg-mut', 10)
        . mf_btn(146, 158, 80, 'Aceptar', 'fg-ok', 22) . mf_btn(232, 158, 80, 'Rechazar', 'fg-ghost', 22) . mf_btn(318, 158, 130, 'Ver actividad →', 'fg-ghost', 22)
        . mf_l(140, 190, 620, 190) . mf_chip(146, 198, 60, 'media', 'fg-warn') . mf_t(214, 211, '1.2 al 60 % pero con estatus «no iniciada»', 'fg-txt', 11)
        . mf_n(1, 128, 44) . mf_n(2, 128, 84) . mf_n(3, 128, 128))],

'materiales-ia' => ['Análisis de materiales: ① resumen por estado, ② lista de hallazgos por material, ③ acción sugerida.',
    mf_svg(640, 230, 'Análisis de materiales', mf_lateral(0, 23, 207, $menuApp, -1)
        . mf_r(134, 34, 150, 44, 'fg-panel') . mf_t(146, 54, 'Compra vencida', 'fg-mut', 10) . mf_t(146, 71, '3', 'fg-bad-t', 16, 'start', true)
        . mf_r(292, 34, 150, 44, 'fg-panel') . mf_t(304, 54, 'Sin fecha', 'fg-mut', 10) . mf_t(304, 71, '5', 'fg-warn-t', 16, 'start', true)
        . mf_r(450, 34, 178, 44, 'fg-panel') . mf_t(462, 54, 'A tiempo / en sitio', 'fg-mut', 10) . mf_t(462, 71, '22', 'fg-ok-t', 16, 'start', true)
        . mf_r(134, 88, 494, 128, 'fg-panel')
        . mf_t(146, 108, 'Material', 'fg-mut', 10, 'start', true) . mf_t(300, 108, 'Necesario', 'fg-mut', 10, 'start', true) . mf_t(380, 108, 'Estado', 'fg-mut', 10, 'start', true) . mf_t(480, 108, 'Acción', 'fg-mut', 10, 'start', true) . mf_l(140, 114, 620, 114)
        . mf_t(146, 134, 'Cemento CPC 30', 'fg-txt', 10) . mf_t(300, 134, '09/03', 'fg-txt', 10) . mf_chip(380, 122, 80, 'compra vencida', 'fg-bad') . mf_btn(480, 120, 120, 'Marcar comprado', 'fg-ghost', 20)
        . mf_t(146, 164, 'Varilla #4', 'fg-txt', 10) . mf_t(300, 164, '16/03', 'fg-txt', 10) . mf_chip(380, 152, 80, 'sin fecha', 'fg-warn') . mf_btn(480, 150, 120, 'Nota', 'fg-ghost', 20)
        . mf_t(146, 194, 'Block 15 cm', 'fg-txt', 10) . mf_t(300, 194, '23/03', 'fg-txt', 10) . mf_chip(380, 182, 80, 'a tiempo', 'fg-ok')
        . mf_n(1, 128, 56) . mf_n(2, 128, 134) . mf_n(3, 612, 128))],

'ensamblador' => ['Ensamblador: ① subir el documento, ② indicar qué buscar (botones o chat), ③ revisar la propuesta con casillas, ④ aplicar o deshacer.',
    mf_svg(640, 260, 'Ensamblador de documentos',
        mf_r(14, 34, 150, 214, 'fg-panel') . mf_t(26, 54, '1 · Archivos', 'fg-txt', 11, 'start', true) . mf_r(24, 64, 130, 56, 'fg-drop', 6) . mf_t(89, 90, 'Arrastra PDF, PPTX,', 'fg-mut', 10, 'middle') . mf_t(89, 104, 'DOCX o TXT', 'fg-mut', 10, 'middle')
        . mf_t(26, 142, 'especificaciones.pdf', 'fg-txt', 10) . mf_t(26, 160, 'alcance.docx', 'fg-txt', 10)
        . mf_r(176, 34, 210, 214, 'fg-panel') . mf_t(188, 54, '2 · ¿Qué busco?', 'fg-txt', 11, 'start', true)
        . mf_btn(188, 62, 184, 'Partidas nuevas', 'fg-ghost', 20) . mf_btn(188, 86, 184, 'Materiales', 'fg-ghost', 20) . mf_btn(188, 110, 184, 'Submittal nuevo', 'fg-ghost', 20) . mf_btn(188, 134, 184, 'Dependencias y contrato', 'fg-ghost', 20) . mf_btn(188, 158, 184, '✨ Lo que encuentres', 'fg-pri', 20)
        . mf_r(188, 186, 184, 22, 'fg-input', 11) . mf_t(200, 201, 'o escribe tu pregunta…', 'fg-mut', 10)
        . mf_r(398, 34, 230, 214, 'fg-panel') . mf_t(410, 54, '3 · Propuesta', 'fg-txt', 11, 'start', true)
        . mf_r(410, 66, 10, 10, 'fg-chk', 2) . mf_t(426, 75, 'Partida 4.0 Instalaciones', 'fg-txt', 10) . mf_chip(560, 64, 56, 'nueva', 'fg-ok')
        . mf_r(410, 90, 10, 10, 'fg-chk', 2) . mf_t(426, 99, 'Material: tubo PVC 4"', 'fg-txt', 10) . mf_chip(560, 88, 56, 'nuevo', 'fg-ok')
        . mf_r(410, 114, 10, 10, 'fg-input', 2) . mf_t(426, 123, 'Partida 1.0 (¿ya existe?)', 'fg-txt', 10) . mf_chip(560, 112, 56, 'dudosa', 'fg-warn')
        . mf_btn(410, 214, 100, 'Aplicar', 'fg-ok') . mf_btn(516, 214, 100, 'Descartar', 'fg-ghost')
        . mf_n(1, 20, 34) . mf_n(2, 182, 34) . mf_n(3, 404, 34) . mf_n(4, 410, 208))],

'ordenar' => ['Ordenar y renumerar: ① arrastra o usa las flechas, ② vista previa de los códigos, ③ guardar o deshacer.',
    mf_svg(640, 240, 'Ordenar y renumerar', mf_lateral(0, 23, 217, $menuApp, 5)
        . mf_r(134, 34, 494, 140, 'fg-panel')
        . mf_t(146, 54, '☰', 'fg-mut', 13) . mf_t(172, 54, '1.0 Cimentación', 'fg-txt', 11, 'start', true) . mf_t(450, 54, '▲ ▼', 'fg-mut', 11) . mf_t(520, 54, '→ 1.0', 'fg-ok-t', 11, 'start', true)
        . mf_t(172, 78, '1.1 Excavación', 'fg-txt', 10) . mf_t(520, 78, '→ 1.1', 'fg-ok-t', 10, 'start', true)
        . mf_r(160, 88, 440, 24, 'fg-act2', 4) . mf_t(146, 104, '☰', 'fg-mut', 13) . mf_t(172, 104, '1.3 Colado', 'fg-txt', 10, 'start', true) . mf_t(450, 104, '▲ ▼', 'fg-mut', 11) . mf_t(520, 104, '→ 1.2', 'fg-pri-t', 10, 'start', true)
        . mf_t(172, 130, '1.2 Armado de acero', 'fg-txt', 10) . mf_t(520, 130, '→ 1.3', 'fg-pri-t', 10, 'start', true)
        . mf_t(146, 160, '☑ Renumerar (1.0, 2.0… / 1.1, 1.2…)', 'fg-mut', 10)
        . mf_btn(134, 186, 120, 'Guardar orden') . mf_btn(262, 186, 120, 'Deshacer ese orden', 'fg-ghost') . mf_t(400, 202, 'ⓘ detalle de cada código', 'fg-mut', 10)
        . mf_n(1, 128, 100) . mf_n(2, 512, 44) . mf_n(3, 128, 198))],

'cambios' => ['Orden de cambio: ① estado de la orden, ② renglones con su ciclo, ③ retiros pendientes.',
    mf_svg(640, 240, 'Órdenes de cambio',
        mf_caja(14, 36, 86, 34, 'Borrador', '', 'fg-panel2') . mf_flecha(100, 53, 116, 53) . mf_caja(118, 36, 86, 34, 'Solicitada', '', 'fg-panel2') . mf_flecha(204, 53, 220, 53)
        . mf_caja(222, 36, 86, 34, 'Aprobada', '', 'fg-ok') . mf_flecha(308, 53, 324, 53) . mf_caja(326, 36, 86, 34, 'Ejecutada', '', 'fg-panel2') . mf_flecha(412, 53, 428, 53) . mf_caja(430, 36, 86, 34, 'Cerrada', '', 'fg-panel2')
        . mf_caja(526, 36, 100, 34, 'Rechazada', '', 'fg-panel2')
        . mf_r(14, 84, 614, 144, 'fg-panel') . mf_t(26, 104, 'Renglones del cambio', 'fg-txt', 12, 'start', true) . mf_btn(500, 90, 116, '+ Agregar renglón', 'fg-ghost', 20)
        . mf_t(26, 128, 'Tipo', 'fg-mut', 10, 'start', true) . mf_t(150, 128, 'Descripción', 'fg-mut', 10, 'start', true) . mf_t(380, 128, 'Estado', 'fg-mut', 10, 'start', true) . mf_l(20, 134, 620, 134)
        . mf_t(26, 154, 'Instalación temporal', 'fg-txt', 10) . mf_t(150, 154, 'Apuntalamiento zona B', 'fg-txt', 10) . mf_chip(380, 142, 70, 'ejecutado', 'fg-warn') . mf_t(462, 154, '⚠ retiro pendiente', 'fg-bad-t', 10, 'start', true)
        . mf_t(26, 182, 'Reubicación', 'fg-txt', 10) . mf_t(150, 182, 'Cambio de ducto eléctrico', 'fg-txt', 10) . mf_chip(380, 170, 70, 'verificado', 'fg-ok')
        . mf_t(26, 210, 'Demolición', 'fg-txt', 10) . mf_t(150, 210, 'Muro divisorio 2.4', 'fg-txt', 10) . mf_chip(380, 198, 70, 'pendiente', 'fg-mut2')
        . mf_n(1, 20, 36) . mf_n(2, 20, 104) . mf_n(3, 612, 152))],

'lineas-base' => ['Comparar con una línea base: la barra gris «fantasma» es lo que se planeó; la de color, lo que hay hoy.',
    mf_svg(640, 220, 'Línea base contra programa actual', mf_lateral(0, 23, 197, $menuApp, -1)
        . mf_t(134, 46, 'Línea base «Contrato 01/02»', 'fg-txt', 13, 'start', true) . mf_btn(480, 32, 148, 'Comparar con hoy')
        . mf_r(134, 60, 494, 146, 'fg-panel') . mf_t(146, 80, 'Actividad', 'fg-mut', 10, 'start', true) . mf_t(300, 80, 'S1    S2    S3    S4    S5    S6    S7', 'fg-mut', 10)
        . mf_t(146, 108, 'Excavación', 'fg-txt', 10) . mf_r(300, 98, 60, 10, 'fg-mut2', 3) . mf_r(300, 111, 60, 10, 'fg-ok', 3) . mf_chip(560, 100, 56, 'igual', 'fg-ok')
        . mf_t(146, 142, 'Armado de acero', 'fg-txt', 10) . mf_r(360, 132, 70, 10, 'fg-mut2', 3) . mf_r(388, 145, 92, 10, 'fg-warn', 3) . mf_chip(560, 134, 56, '+6 d', 'fg-warn')
        . mf_t(146, 176, 'Colado losa', 'fg-txt', 10) . mf_r(430, 166, 60, 10, 'fg-mut2', 3) . mf_r(486, 179, 60, 10, 'fg-bad', 3) . mf_chip(560, 168, 56, 'sin justif.', 'fg-bad')
        . mf_n(1, 128, 40) . mf_n(2, 330, 126) . mf_n(3, 612, 134))],

'datos-obra' => ['Datos de obra: ① alerta de contrato, ② fechas contractuales, ③ responsables, ④ cantidades y contactos.',
    mf_svg(640, 250, 'Datos de obra', mf_lateral(0, 23, 227, $menuApp, -1)
        . mf_r(134, 32, 494, 30, 'fg-alerta', 6) . mf_t(146, 52, '⚠ El programa termina 12 días después de la fecha del contrato', 'fg-blanco', 11, 'start', true)
        . mf_r(134, 72, 240, 70, 'fg-panel') . mf_t(146, 90, 'Fechas contractuales', 'fg-txt', 11, 'start', true) . mf_t(146, 110, 'Inicio 01/02/2026', 'fg-mut', 10) . mf_t(146, 126, 'Fin 15/07/2026 · penalización $5,000/día', 'fg-mut', 10)
        . mf_r(388, 72, 240, 70, 'fg-panel') . mf_t(400, 90, 'Responsables por actividad', 'fg-txt', 11, 'start', true) . mf_t(400, 110, '1.2 Armado → J. Torres', 'fg-mut', 10) . mf_t(400, 126, '1.3 Colado → M. Ruiz', 'fg-mut', 10)
        . mf_r(134, 152, 240, 82, 'fg-panel') . mf_t(146, 170, 'Cantidades de obra', 'fg-txt', 11, 'start', true) . mf_t(146, 190, 'E-101 Concreto · 240 m³ · $480,000', 'fg-mut', 10) . mf_t(146, 208, 'Total: $1,250,000', 'fg-txt', 10, 'start', true)
        . mf_r(388, 152, 240, 82, 'fg-panel') . mf_t(400, 170, 'Directorio de contactos', 'fg-txt', 11, 'start', true) . mf_t(400, 190, 'J. Torres · Residente · 55 1234 5678', 'fg-mut', 10)
        . mf_n(1, 128, 46) . mf_n(2, 128, 100) . mf_n(3, 382, 100) . mf_n(4, 128, 192))],

'revisiones' => ['Revisiones: ① veredicto de cada elemento, ② estado de uso de las herramientas de IA.',
    mf_svg(640, 230, 'Revisiones', mf_lateral(0, 23, 207, $menuApp, -1)
        . mf_btn(134, 32, 70, 'Resumen') . mf_btn(208, 32, 78, 'Materiales', 'fg-ghost') . mf_btn(290, 32, 78, 'Actividades', 'fg-ghost') . mf_btn(372, 32, 150, 'Herramientas de IA', 'fg-ghost') . mf_btn(526, 32, 70, 'Historial', 'fg-ghost')
        . mf_r(134, 66, 240, 140, 'fg-panel') . mf_t(146, 86, 'Materiales', 'fg-txt', 11, 'start', true)
        . mf_t(146, 110, 'Cemento CPC 30', 'fg-txt', 10) . mf_t(330, 110, '✅', 'fg-txt', 12) . mf_t(146, 134, 'Varilla #4', 'fg-txt', 10) . mf_t(330, 134, '⚠', 'fg-txt', 12)
        . mf_t(146, 158, 'Block 15 cm', 'fg-txt', 10) . mf_t(330, 158, '❌', 'fg-txt', 12) . mf_t(146, 182, 'Pintura vinílica', 'fg-txt', 10) . mf_t(330, 182, '⚪', 'fg-txt', 12)
        . mf_r(388, 66, 240, 140, 'fg-panel') . mf_t(400, 86, 'Herramientas de IA', 'fg-txt', 11, 'start', true)
        . mf_t(400, 110, 'Inconsistencias', 'fg-txt', 10) . mf_chip(524, 98, 92, 'al día', 'fg-ok') . mf_t(400, 134, 'Programador', 'fg-txt', 10) . mf_chip(524, 122, 92, 'desactualizada', 'fg-warn')
        . mf_t(400, 158, 'Materiales inteligentes', 'fg-txt', 10) . mf_chip(524, 146, 92, 'sin ejecutar', 'fg-mut2')
        . mf_n(1, 128, 80) . mf_n(2, 382, 80))],

'punch' => ['Punch list: ① registra el pendiente con foto «antes», ② asigna responsable y fecha límite, ③ cierra con foto «después».',
    mf_svg(640, 220, 'Punch list', mf_lateral(0, 23, 197, $menuApp, -1)
        . mf_r(134, 34, 200, 170, 'fg-panel') . mf_t(146, 54, 'Nuevo pendiente', 'fg-txt', 12, 'start', true) . mf_campo(146, 76, 176, 'Ubicación', 'Nivel 2 · Eje C') . mf_campo(146, 118, 84, 'Severidad', 'Alta ▾') . mf_campo(238, 118, 84, 'Fecha límite', '28/10') . mf_btn(146, 170, 100, 'Foto «antes»', 'fg-ghost', 22) . mf_btn(252, 170, 70, 'Registrar', 'fg-pri', 22)
        . mf_r(346, 34, 282, 170, 'fg-panel') . mf_t(358, 54, 'Pendiente #12', 'fg-txt', 12, 'start', true) . mf_r(358, 64, 124, 84, 'fg-foto', 6) . mf_t(420, 110, 'Antes', 'fg-mut', 11, 'middle') . mf_r(492, 64, 124, 84, 'fg-foto', 6) . mf_t(554, 110, 'Después', 'fg-mut', 11, 'middle')
        . mf_chip(358, 160, 70, 'abierto', 'fg-warn') . mf_btn(480, 168, 136, 'Marcar corregido', 'fg-ok', 22)
        . mf_n(1, 140, 40) . mf_n(2, 140, 126) . mf_n(3, 552, 58))],

'seguridad' => ['Seguridad y permisos: ① permisos que exigen las actividades próximas, ② solicitud con riesgos y medidas, ③ incidentes.',
    mf_svg(640, 220, 'Seguridad y permisos', mf_lateral(0, 23, 197, $menuApp, -1)
        . mf_r(134, 34, 494, 44, 'fg-panel') . mf_t(146, 54, 'Próximos 14 días: trabajo en altura (actividad 3.2) · trabajo en caliente (4.1)', 'fg-txt', 10) . mf_btn(500, 44, 116, 'Solicitar permiso', 'fg-pri', 22)
        . mf_r(134, 88, 300, 120, 'fg-panel') . mf_t(146, 106, 'Permiso de trabajo en altura', 'fg-txt', 11, 'start', true) . mf_t(146, 126, 'Riesgos: caída, objetos', 'fg-mut', 10) . mf_t(146, 142, 'Medidas: arnés, línea de vida, barandal', 'fg-mut', 10) . mf_chip(146, 156, 70, 'solicitado', 'fg-warn') . mf_btn(300, 170, 124, 'Descargar PDF', 'fg-ghost', 22)
        . mf_r(446, 88, 182, 120, 'fg-panel') . mf_t(458, 106, 'Incidentes', 'fg-txt', 11, 'start', true) . mf_t(458, 126, '12/10 · Media · Resbalón', 'fg-mut', 10) . mf_btn(458, 168, 156, 'Registrar incidente', 'fg-ghost', 22)
        . mf_n(1, 128, 56) . mf_n(2, 128, 120) . mf_n(3, 440, 120))],

'normativa' => ['Normativa: ① carga documentos, ② busca en todos, ③ cada cláusula se vincula a actividades y materiales (tú aceptas).',
    mf_svg(640, 220, 'Normativa y especificaciones', mf_lateral(0, 23, 197, $menuApp, -1)
        . mf_r(134, 34, 200, 80, 'fg-drop', 8) . mf_t(234, 70, 'Cargar PDF o Word', 'fg-mut', 11, 'middle') . mf_t(234, 86, '(puedes elegir varios)', 'fg-mut', 9, 'middle')
        . mf_r(346, 34, 282, 30, 'fg-input', 15) . mf_t(362, 54, '🔎 buscar «resistencia concreto»…', 'fg-mut', 10)
        . mf_r(346, 72, 282, 42, 'fg-panel') . mf_t(358, 90, 'NMX-C-155 · cláusula 5.2', 'fg-txt', 10, 'start', true) . mf_t(358, 105, 'f’c mínimo 250 kg/cm² para cimentación…', 'fg-mut', 9)
        . mf_r(134, 124, 494, 82, 'fg-panel') . mf_t(146, 142, 'Vínculos de la cláusula 5.2', 'fg-txt', 11, 'start', true)
        . mf_t(146, 162, 'Actividad 1.3 Colado', 'fg-txt', 10) . mf_chip(520, 150, 44, 'Aplica', 'fg-ok') . mf_btn(568, 148, 50, 'Quitar', 'fg-ghost', 20)
        . mf_t(146, 188, 'Material: Concreto f’c 250', 'fg-txt', 10) . mf_chip(520, 176, 44, 'Aplica', 'fg-ok') . mf_btn(568, 174, 50, 'Quitar', 'fg-ghost', 20)
        . mf_n(1, 128, 40) . mf_n(2, 622, 40) . mf_n(3, 128, 148))],

'ia-manual-uso' => ['La app de escritorio y la web comparten sesión: la IA se ejecuta en tu equipo y envía los resultados al servidor.',
    mf_svg(640, 170, 'Escritorio, servidor y datos',
        mf_caja(14, 50, 160, 64, 'App de escritorio', 'modelo de IA + tus claves') . mf_flecha(174, 82, 254, 82)
        . mf_caja(256, 50, 160, 64, 'Servidor ProjectWeb', 'valida y calcula') . mf_flecha(416, 82, 466, 82)
        . mf_caja(468, 50, 160, 64, 'Base de datos', 'tus proyectos')
        . mf_t(214, 70, 'propuesta', 'fg-mut', 10, 'middle') . mf_t(441, 70, 'aplica', 'fg-mut', 10, 'middle')
        . mf_t(320, 144, 'Las claves de API viven cifradas en tu equipo, nunca en el servidor.', 'fg-mut', 11, 'middle')
        . mf_n(1, 94, 44) . mf_n(2, 336, 44) . mf_n(3, 548, 44))],

];
