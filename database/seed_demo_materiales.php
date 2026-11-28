<?php
/**
 * Ilustraciones de catálogo de los materiales del proyecto DEMO (solo datos de ejemplo).
 *
 * Se dibujan con GD, sin red ni marcas reales: son fichas de producto consistentes para
 * el catálogo de materiales, el MSR y la lámina de acabados del Vplan. No forman parte
 * del runtime de la aplicación.
 */

require_once __DIR__ . '/seed_demo_planos.php';

/** Sombra suave bajo el producto */
function dmi_sombra(DemoPlanoHoja $h, float $cx = 400, float $cy = 690, float $rx = 230, float $ry = 28): void
{
    $h->ellipse($cx, $cy, $rx, $ry, '#e2e8f0');
    $h->ellipse($cx, $cy, $rx * 0.72, $ry * 0.7, '#d7dee7');
}

/** Caja en perspectiva de gabinete: frente, tapa y costado derecho */
function dmi_box(DemoPlanoHoja $h, float $x, float $y, float $w, float $alto, float $prof, string $frente, string $tapa, string $lado, string $trazo = '#334155', float $lw = 2): void
{
    $dx = $prof * 0.62;
    $dy = $prof * 0.38;
    $h->poly([[$x, $y], [$x + $w, $y], [$x + $w + $dx, $y - $dy], [$x + $dx, $y - $dy]], $tapa, $trazo, $lw);
    $h->poly([[$x + $w, $y], [$x + $w + $dx, $y - $dy], [$x + $w + $dx, $y - $dy + $alto], [$x + $w, $y + $alto]], $lado, $trazo, $lw);
    $h->rect($x, $y, $w, $alto, $frente, $trazo, $lw);
    $h->line($x + $w, $y, $x + $w, $y + $alto, $trazo, $lw);
}

/** Cilindro vertical */
function dmi_cyl(DemoPlanoHoja $h, float $cx, float $cy, float $rx, float $ry, float $alto, string $cuerpo, string $tapa, string $trazo = '#334155', float $lw = 2): void
{
    $h->rect($cx - $rx, $cy - $alto, $rx * 2, $alto, $cuerpo, null);
    $h->line($cx - $rx, $cy - $alto, $cx - $rx, $cy, $trazo, $lw);
    $h->line($cx + $rx, $cy - $alto, $cx + $rx, $cy, $trazo, $lw);
    $h->ellipse($cx, $cy, $rx, $ry, $tapa, $trazo, $lw);
    $h->ellipse($cx, $cy - $alto, $rx, $ry, $tapa, $trazo, $lw);
}

/** Barra gruesa con extremos redondeados */
function dmi_barra(DemoPlanoHoja $h, float $x1, float $y1, float $x2, float $y2, float $grosor, string $color, string $trazo = '#475569', float $lw = 2): void
{
    $h->line($x1, $y1, $x2, $y2, $trazo, $grosor + 4);
    $h->line($x1, $y1, $x2, $y2, $color, $grosor);
    $h->ellipse($x1, $y1, $grosor / 2, $grosor / 2, $color, $trazo, $lw);
    $h->ellipse($x2, $y2, $grosor / 2, $grosor / 2, $color, $trazo, $lw);
}

// ------------------------------------------------------------------ materiales

function dmi_varilla(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 676, 260, 30);
    $barras = [[120, 610, 660, 310], [145, 632, 685, 332], [170, 654, 710, 354], [195, 676, 735, 376],
        [132, 588, 672, 288], [157, 610, 697, 310], [182, 632, 722, 332]];
    foreach ($barras as $i => [$x1, $y1, $x2, $y2]) {
        dmi_barra($h, $x1, $y1, $x2, $y2, 30, $i % 2 ? '#94a3b8' : '#a8b4c2', '#475569', 2);
        $ang = atan2($y2 - $y1, $x2 - $x1);
        for ($t = 0.06; $t < 0.95; $t += 0.075) {
            $xx = $x1 + ($x2 - $x1) * $t;
            $yy = $y1 + ($y2 - $y1) * $t;
            $h->line($xx - sin($ang) * 13, $yy + cos($ang) * 13, $xx + sin($ang) * 13, $yy - cos($ang) * 13, '#64748b', 2);
        }
        $h->line($x1 + 8, $y1 - 8, $x2 + 8, $y2 - 8, '#cbd5e1', 3);
    }
    $h->line(300, 566, 420, 640, '#334155', 3);
    $h->line(300, 574, 420, 648, '#334155', 3);
    $h->text(400, 760, 18, '#64748b', 'ACERO DE REFUERZO · fy 4,200 kg/cm²', false, 'center');
}

function dmi_perfil(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 690, 280, 28);
    $perfil = function (float $x, float $y) use ($h): void {
        $w = 190;
        $alto = 140;
        $patin = 26;
        $alma = 44;
        $pts = [[$x, $y], [$x + $w, $y], [$x + $w, $y + $patin], [$x + $w / 2 + $alma / 2, $y + $patin],
            [$x + $w / 2 + $alma / 2, $y + $alto - $patin], [$x + $w, $y + $alto - $patin], [$x + $w, $y + $alto],
            [$x, $y + $alto], [$x, $y + $alto - $patin], [$x + $w / 2 - $alma / 2, $y + $alto - $patin],
            [$x + $w / 2 - $alma / 2, $y + $patin], [$x, $y + $patin]];
        // Tapa del patín superior en perspectiva
        $h->poly([[$x, $y], [$x + $w, $y], [$x + $w + 30, $y - 16], [$x + 30, $y - 16]], '#cbd5e1', '#334155', 2);
        $h->poly($pts, '#94a3b8', '#334155', 2);
        $h->line($x + 6, $y + 21, $x + $w - 6, $y + 21, '#e2e8f0', 2);
        $h->line($x + 6, $y + $alto - 5, $x + $w - 6, $y + $alto - 5, '#e2e8f0', 2);
    };
    $perfil(150, 470);
    $perfil(120, 300);
    $perfil(90, 130);
    $h->text(400, 760, 18, '#64748b', 'PERFIL ESTRUCTURAL A992 · ALA ANCHA', false, 'center');
}

function dmi_concreto(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 300, 30);
    // Chasis
    $h->rect(110, 500, 590, 38, '#334155', '#0f172a', 2);
    // Cabina
    $h->rect(548, 330, 150, 170, '#f1f5f9', '#334155', 2);
    $h->poly([[548, 330], [600, 280], [698, 280], [698, 330]], '#e2e8f0', '#334155', 2);
    $h->rect(570, 350, 92, 62, '#7dd3fc', '#334155', 2);
    $h->line(616, 350, 616, 412, '#334155', 2);
    $h->rect(560, 452, 120, 22, '#94a3b8', '#334155', 2);
    // Tambor (cilindro inclinado simplificado)
    $h->ellipse(300, 420, 150, 140, '#f8fafc', '#334155', 3);
    $h->ellipse(300, 420, 112, 104, '#e2e8f0', '#94a3b8', 2);
    $h->arc(300, 420, 132, 122, 190, 330, '#94a3b8', 4);
    $h->arc(300, 420, 96, 88, 200, 320, '#94a3b8', 3);
    $h->arc(300, 420, 62, 56, 210, 310, '#94a3b8', 3);
    $h->line(222, 330, 378, 510, '#06b6d4', 12);
    $h->line(205, 355, 395, 485, '#94a3b8', 3);
    // Tolva de carga
    $h->poly([[150, 340], [300, 300], [300, 340], [180, 380]], '#cbd5e1', '#334155', 2);
    // Canaleta
    $h->poly([[140, 470], [235, 455], [100, 620], [40, 620]], '#94a3b8', '#334155', 2);
    // Ruedas
    foreach ([230, 350, 580, 660] as $x) {
        $h->ellipse($x, 620, 44, 44, '#111827', '#0f172a', 2);
        $h->ellipse($x, 620, 20, 20, '#94a3b8', '#334155', 2);
    }
    $h->rect(140, 538, 540, 16, '#475569', '#0f172a', 2);
    $h->text(400, 760, 18, '#64748b', 'CONCRETO PREMEZCLADO · f\'c 350 kg/cm²', false, 'center');
}

function dmi_tablero(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 695, 250, 24);
    dmi_box($h, 260, 210, 280, 430, 100, '#cbd5e1', '#e2e8f0', '#94a3b8');
    $h->rect(278, 228, 244, 394, '#e2e8f0', '#334155', 2);
    // Puerta abierta
    $h->poly([[258, 210], [150, 240], [150, 610], [258, 640]], '#f1f5f9', '#334155', 2);
    $h->line(170, 260, 170, 600, '#94a3b8', 2);
    // Interruptores
    for ($f = 0; $f < 3; $f++) {
        $y = 300 + $f * 92;
        $h->rect(300, $y, 200, 64, '#f8fafc', '#475569', 1.5);
        for ($c = 0; $c < 7; $c++) {
            $x = 310 + $c * 27;
            $h->rect($x, $y + 12, 18, 40, '#334155', '#0f172a', 1);
            $h->rect($x + 4, $y + 16, 10, 18, '#94a3b8', '#0f172a', 1);
            $h->rect($x + 4, $y + 36, 10, 10, '#06b6d4', '#0f172a', 1);
        }
    }
    // Medidor y aviso
    $h->rect(470, 250, 40, 28, '#0f172a', '#334155', 1);
    $h->rect(476, 256, 28, 12, '#22d3ee', null);
    $h->poly([[320, 520], [342, 560], [298, 560]], '#facc15', '#a16207', 1.5);
    $h->text(320, 556, 15, '#713f12', '!', true, 'center');
    // Manija
    $h->rect(486, 430, 10, 92, '#475569', '#1e293b', 1);
    $h->text(400, 760, 18, '#64748b', 'TABLERO DE DISTRIBUCIÓN · 800 A', false, 'center');
}

function dmi_cable(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 690, 250, 26);
    foreach ([[250, 140, 30], [210, 116, 26], [170, 92, 22], [132, 70, 18]] as [$rx, $ry, $g]) {
        $h->ellipse(390, 440, $rx, $ry, null, '#0f172a', $g + 3);
        $h->ellipse(390, 440, $rx, $ry, null, '#1f2937', $g);
        $h->arc(390, 440, $rx - 8, $ry - 8, 200, 330, '#4b5563', 4);
    }
    // Cable que sale del rollo
    dmi_barra($h, 560, 390, 690, 300, 38, '#1f2937', '#0f172a', 2);
    $h->line(565, 380, 675, 300, '#4b5563', 5);
    $h->ellipse(690, 300, 11, 11, '#b45309', '#78350f', 2);
    $h->ellipse(690, 300, 5, 5, '#f59e0b', null);
    // Cinchos
    $h->line(270, 300, 300, 330, '#0ea5e9', 5);
    $h->line(500, 540, 530, 570, '#0ea5e9', 5);
    $h->text(400, 760, 18, '#64748b', 'CABLE DE MEDIA TENSIÓN · 34.5 kV', false, 'center');
}

function dmi_bombeo(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 260, 26);
    dmi_box($h, 180, 610, 440, 34, 80, '#64748b', '#94a3b8', '#475569');
    foreach ([250, 380, 510] as $i => $x) {
        dmi_cyl($h, $x, 610, 52, 16, 210, '#cbd5e1', '#e2e8f0');
        $h->rect($x - 52, 470, 104, 16, '#94a3b8', '#334155', 2);
        $h->line($x - 40, 500, $x + 40, 500, '#64748b', 2);
        $h->line($x - 40, 540, $x + 40, 540, '#64748b', 2);
        $h->line($x - 40, 580, $x + 40, 580, '#64748b', 2);
    }
    // Motor
    dmi_cyl($h, 380, 400, 62, 18, 120, '#2563eb', '#60a5fa');
    for ($y = 300; $y < 390; $y += 14) $h->line(326, $y, 434, $y, '#1d4ed8', 2);
    // Tuberías y manómetros
    $h->line(510, 470, 640, 470, '#94a3b8', 18);
    $h->line(510, 470, 640, 470, '#475569', 2);
    $h->ellipse(640, 470, 16, 16, '#f8fafc', '#334155', 2);
    $h->line(640, 470, 650, 456, '#dc2626', 2);
    $h->line(250, 470, 170, 470, '#94a3b8', 18);
    $h->line(250, 470, 170, 470, '#475569', 2);
    // Tablero de control
    $h->rect(560, 250, 80, 110, '#e2e8f0', '#334155', 2);
    $h->rect(572, 266, 56, 34, '#0f172a', '#334155', 1);
    $h->rect(578, 272, 20, 8, '#22d3ee', null);
    $h->ellipse(586, 326, 7, 7, '#22c55e', '#166534', 1);
    $h->ellipse(610, 326, 7, 7, '#ef4444', '#991b1b', 1);
    $h->text(400, 760, 18, '#64748b', 'EQUIPO DE BOMBEO HIDRONEUMÁTICO', false, 'center');
}

function dmi_sanitarios(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 690, 300, 26);
    // Inodoro de perfil
    $h->rect(120, 250, 150, 150, '#e2e8f0', '#64748b', 3);
    $h->rect(110, 236, 170, 20, '#cbd5e1', '#64748b', 3);
    $h->rect(132, 272, 126, 106, '#f8fafc', '#94a3b8', 1.5);
    $h->ellipse(200, 500, 112, 62, '#e2e8f0', '#64748b', 3);
    $h->ellipse(200, 488, 88, 46, '#f8fafc', '#64748b', 2);
    $h->ellipse(200, 480, 74, 38, '#cbd5e1', '#64748b', 2);
    $h->ellipse(200, 480, 54, 24, '#e2e8f0', '#94a3b8', 1.5);
    $h->rect(178, 430, 46, 66, '#e2e8f0', '#64748b', 2);
    $h->ellipse(200, 620, 62, 16, '#cbd5e1', '#64748b', 2);
    $h->ellipse(200, 250, 10, 10, '#64748b', '#334155', 1);
    // Lavabo con pedestal
    $h->rect(510, 430, 90, 210, '#e2e8f0', '#64748b', 3);
    $h->ellipse(555, 620, 62, 16, '#cbd5e1', '#64748b', 2);
    $h->ellipse(555, 430, 130, 42, '#e2e8f0', '#64748b', 3);
    $h->ellipse(555, 422, 100, 30, '#f8fafc', '#64748b', 2);
    $h->ellipse(555, 426, 72, 18, '#cbd5e1', '#94a3b8', 1.5);
    $h->ellipse(555, 426, 14, 6, '#94a3b8', null);
    // Grifería
    $h->line(555, 400, 555, 342, '#94a3b8', 10);
    $h->line(555, 342, 610, 342, '#94a3b8', 10);
    $h->ellipse(555, 400, 13, 6, '#cbd5e1', '#475569', 2);
    $h->line(610, 342, 610, 372, '#94a3b8', 7);
    $h->text(400, 760, 18, '#64748b', 'MUEBLES SANITARIOS Y GRIFERÍA', false, 'center');
}

function dmi_fibra(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 380, 690, 230, 24);
    foreach ([[200, 110, 16], [166, 90, 14], [132, 70, 12]] as [$rx, $ry, $g]) {
        $h->ellipse(360, 450, $rx, $ry, null, '#a16207', $g + 2);
        $h->ellipse(360, 450, $rx, $ry, null, '#facc15', $g);
    }
    dmi_barra($h, 500, 430, 620, 360, 24, '#facc15', '#a16207', 2);
    $colores = ['#2563eb', '#f97316', '#16a34a', '#78350f', '#64748b', '#f8fafc', '#dc2626', '#0f172a', '#facc15', '#7c3aed', '#ec4899', '#06b6d4'];
    foreach ($colores as $i => $c) {
        $x1 = 620 + ($i % 4) * 4;
        $y1 = 360 + ($i % 3) * 4;
        $x2 = 710 + ($i % 4) * 12;
        $y2 = 300 + intdiv($i, 4) * 34 + ($i % 3) * 8;
        $h->line($x1, $y1, $x2, $y2, $c, 4);
        $h->ellipse($x2, $y2, 5, 5, $c, '#334155', 1);
    }
    $h->text(400, 760, 18, '#64748b', 'FIBRA ÓPTICA MONOMODO · 12 HILOS', false, 'center');
}

function dmi_gabinete(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 695, 240, 24);
    // Postes
    foreach ([[230, 180], [510, 180]] as [$x, $y]) {
        $h->rect($x, $y, 26, 480, '#1f2937', '#0f172a', 2);
        $h->poly([[$x, $y], [$x + 26, $y], [$x + 46, $y - 18], [$x + 20, $y - 18]], '#374151', '#0f172a', 2);
    }
    // Estantes y equipos
    foreach ([300, 430, 560] as $f => $y) {
        $h->rect(230, $y, 306, 12, '#94a3b8', '#334155', 1);
        for ($i = 0; $i < 4; $i++) {
            $x = 250 + $i * 70;
            $h->rect($x, $y - 26, 56, 26, '#0f172a', '#334155', 1);
            for ($p = 0; $p < 5; $p++) $h->rect($x + 6 + $p * 9, $y - 19, 5, 7, '#facc15', null);
            $h->ellipse($x + 46, $y - 8, 3, 3, '#22c55e', null);
        }
    }
    // Cables
    $h->line(300, 560, 320, 700, '#38bdf8', 4);
    $h->line(340, 560, 360, 700, '#facc15', 4);
    $h->line(420, 560, 440, 700, '#38bdf8', 4);
    // Puerta abierta
    $h->poly([[536, 180], [620, 210], [620, 640], [536, 660]], '#bae6fd', '#334155', 2);
    $h->line(550, 220, 550, 620, '#7dd3fc', 2);
    $h->text(400, 760, 18, '#64748b', 'GABINETE DE COMUNICACIONES · 42U', false, 'center');
}

function dmi_camara(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 660, 190, 22);
    // Base y brazo
    $h->ellipse(400, 260, 120, 26, '#e2e8f0', '#94a3b8', 2);
    $h->rect(380, 260, 40, 60, '#cbd5e1', '#94a3b8', 2);
    // Domo
    $h->ellipse(400, 400, 150, 52, '#f8fafc', '#94a3b8', 2);
    $h->poly([[250, 400], [400, 270], [550, 400]], '#f1f5f9', null);
    $h->arc(400, 400, 150, 130, 180, 360, '#94a3b8', 2);
    $h->ellipse(400, 400, 150, 52, null, '#94a3b8', 2);
    // Lente
    $h->ellipse(400, 408, 56, 30, '#0f172a', '#334155', 2);
    $h->ellipse(400, 408, 30, 16, '#1e293b', null);
    $h->ellipse(392, 402, 8, 5, '#38bdf8', null);
    // LED IR
    foreach ([[330, 400], [470, 400], [400, 380]] as [$x, $y]) $h->ellipse($x, $y, 6, 4, '#ef4444', null);
    $h->text(400, 760, 18, '#64748b', 'CÁMARA IP DOMO · 4 MP', false, 'center');
}

function dmi_cerradura(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 420, 700, 220, 22);
    // Puerta
    $h->rect(410, 130, 300, 560, '#d6b48a', '#8a6840', 2);
    $h->line(430, 130, 430, 690, '#c2a074', 2);
    $h->line(470, 130, 470, 690, '#e2c8a6', 2);
    // Cuerpo de la cerradura
    $h->rect(250, 260, 150, 340, '#111827', '#0f172a', 2);
    $h->rect(264, 276, 122, 308, '#1f2937', '#374151', 1);
    // Teclado
    for ($f = 0; $f < 4; $f++) {
        for ($c = 0; $c < 3; $c++) {
            $h->ellipse(292 + $c * 34, 360 + $f * 38, 9, 9, '#475569', '#64748b', 1);
        }
    }
    // Lector y LED
    $h->arc(325, 300, 34, 20, 200, 340, '#94a3b8', 3);
    $h->arc(325, 300, 22, 12, 200, 340, '#94a3b8', 3);
    $h->ellipse(325, 306, 5, 5, '#22d3ee', null);
    // Manija
    $h->rect(400, 450, 60, 18, '#cbd5e1', '#64748b', 2);
    $h->ellipse(400, 459, 18, 18, '#cbd5e1', '#64748b', 2);
    $h->rect(430, 430, 12, 60, '#94a3b8', '#475569', 2);
    $h->text(400, 760, 18, '#64748b', 'CERRADURA ELECTRÓNICA CON LECTOR', false, 'center');
}

function dmi_porcelanato(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 280, 26);
    for ($i = 0; $i < 4; $i++) {
        $y = 560 - $i * 34;
        dmi_box($h, 190 + $i * 4, $y, 360, 26, 90, '#e2e8f0', '#f8fafc', '#cbd5e1', '#64748b', 1.5);
    }
    // Veta suave en la tapa
    $h->arc(340, 540, 110, 28, 200, 340, '#cbd5e1', 2);
    $h->arc(420, 545, 80, 20, 190, 330, '#cbd5e1', 2);
    // Pieza inclinada
    $h->poly([[520, 190], [700, 250], [700, 610], [520, 550]], '#f1f5f9', '#64748b', 2);
    $h->poly([[520, 190], [700, 250], [700, 272], [520, 212]], '#e2e8f0', '#64748b', 2);
    $h->arc(610, 400, 55, 120, 200, 340, '#cbd5e1', 2);
    $h->arc(640, 430, 30, 80, 190, 330, '#cbd5e1', 2);
    $h->text(400, 760, 18, '#64748b', 'PORCELANATO TÉCNICO · 60 × 60 cm', false, 'center');
}

function dmi_canceleria(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 260, 24);
    // Marco exterior con profundidad
    $h->poly([[180, 200], [560, 200], [620, 176], [240, 176]], '#cbd5e1', '#475569', 2);
    $h->poly([[560, 200], [620, 176], [620, 590], [560, 620]], '#94a3b8', '#475569', 2);
    $h->rect(180, 200, 380, 420, '#bae6fd', '#475569', 2);
    // Reflejos
    $h->poly([[200, 210], [330, 210], [270, 600], [220, 600]], '#e0f2fe', null);
    $h->poly([[390, 210], [450, 210], [400, 600], [370, 600]], '#e0f2fe', null);
    // Montantes y travesaños
    $h->rect(180, 200, 14, 420, '#94a3b8', '#475569', 1.5);
    $h->rect(366, 200, 14, 420, '#94a3b8', '#475569', 1.5);
    $h->rect(546, 200, 14, 420, '#94a3b8', '#475569', 1.5);
    $h->rect(180, 200, 380, 12, '#94a3b8', '#475569', 1.5);
    $h->rect(180, 400, 380, 12, '#94a3b8', '#475569', 1.5);
    $h->rect(180, 608, 380, 12, '#94a3b8', '#475569', 1.5);
    // Hoja abierta
    $h->poly([[560, 200], [690, 240], [690, 620], [560, 620]], '#cbd5e1', '#475569', 2);
    $h->text(400, 760, 18, '#64748b', 'CANCELERÍA DE ALUMINIO Y CRISTAL', false, 'center');
}

function dmi_solar(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 250, 24);
    // Panel inclinado
    $p = [[200, 300], [620, 240], [700, 520], [280, 580]];
    $h->poly($p, '#1e3a8a', '#0f172a', 3);
    for ($i = 1; $i < 6; $i++) {
        $t = $i / 6;
        $h->line(200 + 420 * $t, 300 - 60 * $t, 280 + 420 * $t, 580 - 60 * $t, '#3b82f6', 2);
    }
    for ($i = 1; $i < 4; $i++) {
        $t = $i / 4;
        $h->line(200 + 80 * $t, 300 + 280 * $t, 620 + 80 * $t, 240 + 280 * $t, '#3b82f6', 2);
    }
    $h->poly([[200, 300], [620, 240], [610, 256], [190, 316]], '#93c5fd', null);
    // Patas
    $h->line(290, 580, 290, 690, '#64748b', 10);
    $h->line(630, 520, 630, 690, '#64748b', 10);
    $h->line(210, 690, 360, 690, '#475569', 12);
    $h->line(560, 690, 700, 690, '#475569', 12);
    $h->text(400, 760, 18, '#64748b', 'PANEL SOLAR FOTOVOLTAICO · 550 W', false, 'center');
}

function dmi_chiller(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 290, 28);
    dmi_box($h, 190, 360, 420, 230, 120, '#cbd5e1', '#e2e8f0', '#94a3b8');
    // Compresores
    foreach ([280, 450] as $x) {
        dmi_cyl($h, $x, 360, 48, 14, 100, '#64748b', '#94a3b8');
        $h->line($x - 48, 330, $x + 48, 330, '#475569', 2);
        $h->line($x - 48, 300, $x + 48, 300, '#475569', 2);
    }
    // Ventiladores en la tapa
    foreach ([300, 460] as $x) {
        $h->ellipse($x, 310, 54, 28, '#e2e8f0', '#475569', 2);
        $h->ellipse($x, 310, 12, 6, '#94a3b8', '#475569', 1);
        for ($a = 0; $a < 360; $a += 30) $h->line($x, 310, $x + 50 * cos(deg2rad($a)), 310 + 26 * sin(deg2rad($a)), '#94a3b8', 1);
    }
    // Tuberías
    $h->line(190, 470, 90, 470, '#b45309', 16);
    $h->line(190, 520, 100, 520, '#0ea5e9', 16);
    $h->ellipse(90, 470, 12, 12, '#f59e0b', '#78350f', 2);
    $h->ellipse(100, 520, 12, 12, '#38bdf8', '#0369a1', 2);
    // Panel de control
    $h->rect(470, 500, 110, 70, '#f1f5f9', '#334155', 2);
    $h->rect(484, 514, 60, 24, '#0f172a', '#334155', 1);
    $h->rect(490, 520, 20, 8, '#22d3ee', null);
    $h->ellipse(500, 550, 6, 6, '#22c55e', null);
    $h->ellipse(520, 550, 6, 6, '#ef4444', null);
    // Franja
    $h->rect(190, 575, 420, 10, '#06b6d4', null);
    $h->text(400, 760, 18, '#64748b', 'CHILLER ENFRIADO POR AGUA · 250 TR', false, 'center');
}

function dmi_rociadores(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 680, 270, 22);
    // Tubería principal
    dmi_barra($h, 120, 430, 680, 430, 34, '#dc2626', '#7f1d1d', 2);
    $h->line(150, 420, 650, 420, '#f87171', 4);
    foreach ([220, 360, 500, 640] as $x) {
        $h->rect($x - 8, 408, 16, 46, '#991b1b', '#7f1d1d', 1.5);
    }
    // Rociadores
    foreach ([260, 400, 540] as $x) {
        $h->line($x, 414, $x, 330, '#dc2626', 10);
        $h->ellipse($x, 330, 18, 10, '#f8fafc', '#7f1d1d', 2);
        $h->poly([[$x - 24, 322], [$x + 24, 322], [$x + 14, 306], [$x - 14, 306]], '#cbd5e1', '#475569', 1.5);
    }
    // Válvula
    $h->ellipse(140, 430, 34, 34, null, '#7f1d1d', 4);
    $h->line(140, 400, 140, 460, '#7f1d1d', 3);
    $h->line(110, 430, 170, 430, '#7f1d1d', 3);
    $h->text(400, 760, 18, '#64748b', 'ROCIADORES Y TUBERÍA CONTRA INCENDIO', false, 'center');
}

function dmi_charola(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 260, 24);
    // Largueros laterales
    $h->line(120, 520, 680, 360, '#64748b', 12);
    $h->line(120, 600, 680, 440, '#64748b', 12);
    $h->line(120, 520, 120, 600, '#475569', 10);
    $h->line(680, 360, 680, 440, '#475569', 10);
    // Travesaños
    for ($i = 0; $i <= 18; $i++) {
        $t = $i / 18;
        $x1 = 120 + 560 * $t;
        $y1 = 520 - 160 * $t;
        $h->line($x1, $y1, $x1, $y1 + 80, '#94a3b8', 3);
    }
    // Fondo de malla
    for ($i = 0; $i <= 18; $i++) {
        $t = $i / 18;
        $x1 = 120 + 560 * $t;
        $y1 = 540 - 160 * $t;
        $h->line($x1, $y1, $x1 + 58, $y1 - 20, '#94a3b8', 2);
    }
    // Cables
    foreach ([[0, '#38bdf8'], [18, '#facc15'], [36, '#f97316']] as [$off, $c]) {
        $h->line(130, 550 + $off * 0.4, 670, 390 + $off * 0.4, $c, 8);
        $h->line(130, 550 + $off * 0.4, 670, 390 + $off * 0.4, '#ffffff', 2);
    }
    $h->text(400, 760, 18, '#64748b', 'CHAROLA PORTACABLE · MALLA GALVANIZADA', false, 'center');
}

function dmi_luminaria(DemoPlanoHoja $h): void
{
    // Halo
    $h->ellipse(400, 420, 260, 180, '#e0f2fe');
    $h->ellipse(400, 430, 220, 150, '#f0f9ff');
    dmi_sombra($h, 400, 690, 210, 20);
    dmi_box($h, 230, 250, 340, 42, 100, '#f8fafc', '#ffffff', '#e2e8f0', '#94a3b8', 2);
    $h->rect(250, 262, 300, 18, '#ffffff', null);
    $h->rect(250, 262, 300, 18, null, '#bae6fd', 2);
    // Haz de luz
    $h->poly([[250, 292], [550, 292], [640, 620], [160, 620]], '#f0f9ff');
    $h->poly([[280, 292], [520, 292], [580, 560], [220, 560]], '#e0f2fe');
    // Driver
    $h->rect(350, 210, 100, 40, '#64748b', '#334155', 2);
    $h->text(400, 760, 18, '#64748b', 'LUMINARIA LED · PANEL 60 × 60', false, 'center');
}

function dmi_rack(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 230, 24);
    dmi_box($h, 260, 170, 280, 500, 110, '#1f2937', '#374151', '#111827');
    // Puerta de vidrio
    $h->rect(276, 190, 248, 456, '#0f172a', '#334155', 2);
    $h->poly([[290, 200], [380, 200], [340, 640], [290, 640]], '#1e293b', null);
    $h->poly([[430, 200], [450, 200], [410, 640], [395, 640]], '#1e293b', null);
    // Equipos visibles
    for ($f = 0; $f < 8; $f++) {
        $h->rect(300, 230 + $f * 48, 200, 28, '#111827', '#334155', 1);
        for ($p = 0; $p < 6; $p++) $h->ellipse(315 + $p * 30, 244 + $f * 48, 3, 3, $f % 3 === 0 ? '#22d3ee' : '#facc15', null);
    }
    // Manija y display
    $h->rect(500, 400, 10, 80, '#94a3b8', '#475569', 1);
    $h->rect(286, 600, 60, 22, '#0f172a', '#334155', 1);
    $h->rect(294, 606, 28, 8, '#22d3ee', null);
    $h->text(400, 760, 18, '#64748b', 'RACK DE CÓMPUTO · 42U CON PDU', false, 'center');
}

function dmi_ducto(DemoPlanoHoja $h): void
{
    dmi_sombra($h, 400, 700, 270, 24);
    // Tramo horizontal
    dmi_box($h, 150, 420, 380, 160, 100, '#cbd5e1', '#e2e8f0', '#94a3b8');
    // Bridas
    foreach ([150, 530] as $x) {
        $h->rect($x - 8, 412, 16, 176, '#94a3b8', '#475569', 1.5);
        for ($i = 0; $i < 6; $i++) $h->ellipse($x, 424 + $i * 30, 4, 4, '#475569', null);
    }
    // Codo hacia arriba
    $h->poly([[530, 420], [620, 384], [620, 190], [530, 226]], '#94a3b8', '#475569', 2);
    $h->poly([[530, 580], [620, 544], [620, 384], [530, 420]], '#64748b', '#475569', 2);
    $h->poly([[530, 226], [620, 190], [700, 220], [610, 256]], '#e2e8f0', '#475569', 2);
    // Uniones y remaches
    $h->line(200, 440, 200, 560, '#94a3b8', 2);
    $h->line(350, 440, 350, 560, '#94a3b8', 2);
    for ($i = 0; $i < 5; $i++) $h->ellipse(170 + $i * 80, 500, 3, 3, '#64748b', null);
    // Sello
    $h->rect(150, 420, 380, 8, '#06b6d4', null);
    $h->text(400, 760, 18, '#64748b', 'DUCTO GALVANIZADO · CAL. 24', false, 'center');
}

/**
 * Devuelve la ruta de la ilustración de un material (o null si GD no está disponible).
 * Las claves son las que usa seed_demo.php / seed_demo_nuevas.php.
 */
function demo_material_imagen(string $clave): ?string
{
    static $cache = [];
    if (isset($cache[$clave]) && is_file($cache[$clave])) return $cache[$clave];
    if (!function_exists('imagecreatetruecolor')) return null;
    $dir = sys_get_temp_dir() . '/projectweb_demo_materiales';
    @mkdir($dir, 0775, true);
    $ruta = $dir . '/' . preg_replace('/[^a-z0-9-]+/', '-', $clave) . '.png';
    $h = new DemoPlanoHoja(800, 800, '#f6f7f9');
    $mapa = [
        'varilla' => 'dmi_varilla', 'perfil-ipr' => 'dmi_perfil', 'concreto' => 'dmi_concreto',
        'tablero' => 'dmi_tablero', 'cable-mt' => 'dmi_cable', 'bombeo' => 'dmi_bombeo',
        'sanitarios' => 'dmi_sanitarios', 'fibra' => 'dmi_fibra', 'gabinete' => 'dmi_gabinete',
        'camara' => 'dmi_camara', 'cerradura' => 'dmi_cerradura', 'porcelanato' => 'dmi_porcelanato',
        'canceleria' => 'dmi_canceleria', 'solar' => 'dmi_solar', 'chiller' => 'dmi_chiller',
        'rociadores' => 'dmi_rociadores', 'charola' => 'dmi_charola', 'luminaria' => 'dmi_luminaria',
        'rack' => 'dmi_rack', 'ducto' => 'dmi_ducto',
    ];
    $fn = $mapa[$clave] ?? null;
    if (!$fn || !function_exists($fn)) return null;
    $fn($h);
    if (!$h->guardar($ruta)) return null;
    return $cache[$clave] = $ruta;
}
