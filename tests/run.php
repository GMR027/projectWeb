<?php
// Pruebas rápidas de las piezas sin base de datos: php tests/run.php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/idioma.php';

use Classes\SvgSeguro;
use Classes\Exportadores\OdpExporter;
use Model\Grafico;
use Model\Cajetin;

$fallos = 0;
$ok = function (string $nombre, bool $cond) use (&$fallos) { echo ($cond ? '  ok   ' : '  FALLA ') . $nombre . "\n"; if (!$cond) $fallos++; };

echo "SvgSeguro\n";
$limpio = SvgSeguro::limpiar('<svg viewBox="0 0 10 10" onload="x()"><script>alert(1)</script><rect width="5" height="5" onclick="a" style="fill:url(http://x)"/><image href="http://e/x.png"/></svg>');
$ok('quita scripts, eventos, imágenes y estilos externos', $limpio && !preg_match('/<script|onload|onclick|<image|url\(/i', $limpio) && str_contains($limpio, '<rect'));
$ok('agrega ancho y alto desde el viewBox', $limpio && str_contains($limpio, 'width="10"') && str_contains($limpio, 'height="10"'));
$ok('rechaza entidades externas', SvgSeguro::limpiar('<!DOCTYPE x [<!ENTITY a "b">]><svg/>') === null);
$ok('rechaza lo que no es SVG', SvgSeguro::limpiar('<html></html>') === null);

echo "Grafico::limpiarConfigFondo\n";
$c = Grafico::limpiarConfigFondo(['modo' => 'x', 'repX' => 999, 'opacidad' => 1, 'color' => 'rojo', 'escala' => 50]);
$ok('valores fuera de rango se corrigen', $c['modo'] === 'cubrir' && $c['repX'] === 60 && $c['opacidad'] === 5 && $c['color'] === '#ffffff' && $c['escala'] === 5.0);

echo "Cajetin\n";
$f = json_decode(Cajetin::filasDeTexto("Proyecto | {proyecto}\n\nRevisó |\n"), true);
$ok('una fila por línea, sin líneas vacías', $f === [['Proyecto', '{proyecto}'], ['Revisó', '']]);
$ok('máximo 12 filas', count(json_decode(Cajetin::filasDeTexto(str_repeat("a | b\n", 30)), true)) === 12);

echo "OdpExporter\n";
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
$odp = OdpExporter::generar([['png' => $png, 'nombre' => 'Uno'], ['png' => $png]], 1920, 1080, 'Prueba');
$tmp = tempnam(sys_get_temp_dir(), 'odp'); file_put_contents($tmp, $odp);
$z = new ZipArchive(); $abre = $z->open($tmp) === true;
$ok('es un zip válido', $abre);
$ok('mimetype es la primera entrada y sin comprimir', $abre && $z->getNameIndex(0) === 'mimetype' && $z->getFromName('mimetype') === 'application/vnd.oasis.opendocument.presentation');
$ok('incluye una imagen por lámina', $abre && $z->locateName('Pictures/imagen1.png') !== false && $z->locateName('Pictures/imagen2.png') !== false);
$ok('content.xml bien formado con 2 páginas', $abre && ($d = simplexml_load_string($z->getFromName('content.xml'))) !== false && substr_count($z->getFromName('content.xml'), '<draw:page ') === 2);
@unlink($tmp);


echo "RutaCritica\n";
$cal = new class {
    public function esHabil(string $d): bool { return (int)date('N', strtotime($d)) <= 5; }
    public function getFactor(string $d): float { return $this->esHabil($d) ? 1.0 : 0.0; }
    public function calcularFechaFin(string $ini, int $dur): string { $f = $ini; while (!$this->esHabil($f)) $f = date('Y-m-d', strtotime("$f +1 day")); for ($i = 1; $i < $dur; $i++) { do { $f = date('Y-m-d', strtotime("$f +1 day")); } while (!$this->esHabil($f)); } return $f; }
};
$rc = new Classes\RutaCritica($cal);
$mk = fn($id, $ini, $dur, $hito = 0, $fija = 0) => ['id' => $id, 'inicio' => $ini, 'fin' => $dur ? $cal->calcularFechaFin($ini, $dur) : $ini, 'dur' => $dur, 'hito' => $hito, 'fija' => $fija];
// A 5d → B 4d → C 2d (fin-inicio) y D 3d suelta; todas empiezan el lunes 5-oct-2026
$acts = [1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 4), 3 => $mk(3, '2026-10-05', 2), 4 => $mk(4, '2026-10-05', 3)];
$r = $rc->calcular($acts, [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0], ['id' => 2, 'pred' => 2, 'suc' => 3, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('FS: B empieza el lunes siguiente al fin de A', $r['acts'][2]['es'] === '2026-10-12' && $r['acts'][2]['ef'] === '2026-10-15');
$ok('FS encadenado: C empieza el viernes 16 y termina el lunes 19', $r['acts'][3]['es'] === '2026-10-16' && $r['acts'][3]['ef'] === '2026-10-19');
$ok('B y C estaban en conflicto con sus fechas actuales', $r['acts'][2]['conflicto'] && $r['acts'][3]['conflicto'] && $r['deps'][1]['conflicto']);
$ok('A, B y C son la ruta crítica; D (sin relaciones) no', $r['acts'][1]['critica'] && $r['acts'][2]['critica'] && $r['acts'][3]['critica'] && !$r['acts'][4]['critica'] && !$r['acts'][4]['enRed']);
$ok('fin calculado = fin de C', $r['finCalculado'] === '2026-10-19');
// Holgura: E (2d) cuelga de A y no tiene sucesoras → 9 días hábiles de holgura contra el fin de C
$acts[5] = $mk(5, '2026-10-12', 2);
$r = $rc->calcular($acts, [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0], ['id' => 2, 'pred' => 2, 'suc' => 3, 'tipo' => 'FS', 'retraso' => 0], ['id' => 3, 'pred' => 1, 'suc' => 5, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('holgura de una rama corta: E (2 días) puede empezar hasta el viernes 16 → 4 días hábiles', $r['acts'][5]['holgura'] === 4 && !$r['acts'][5]['critica']);
// SS con retraso 2 y FF
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 3)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'SS', 'retraso' => 2]]);
$ok('SS +2: la sucesora empieza 2 días hábiles después del inicio', $r['acts'][2]['es'] === '2026-10-07');
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 3)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FF', 'retraso' => 0]]);
$ok('FF: la sucesora termina cuando termina la predecesora', $r['acts'][2]['ef'] === '2026-10-09' && $r['acts'][2]['es'] === '2026-10-07');
// fecha fija y que nunca adelanta
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-20', 2, 0, 1)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('una sucesora con fecha posterior no se adelanta', $r['acts'][2]['es'] === '2026-10-20' && !$r['acts'][2]['conflicto']);
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 2, 0, 1)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('fecha fija: no se mueve, pero se marca el conflicto', $r['acts'][2]['es'] === '2026-10-05' && $r['acts'][2]['conflicto']);
// ciclos
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 2), 2 => $mk(2, '2026-10-05', 2)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0], ['id' => 2, 'pred' => 2, 'suc' => 1, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('detecta ciclos', $r['ciclo'] !== null && count($r['ciclo']) === 2);
// hito
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 0, 1)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('hito después de una actividad: mismo día de inicio y fin', $r['acts'][2]['es'] === '2026-10-12' && $r['acts'][2]['ef'] === '2026-10-12');

echo "Idioma\n";
$_COOKIE['idioma'] = 'en';
$ok('traduce al inglés', t('Ajustes') === 'Settings');
$ok('texto compuesto «Sección - Proyecto»', t('Editor - Torre') === 'Editor - Torre' && t('Evidencias - Torre') === 'Evidence - Torre');
$ok('texto sin traducción queda igual', t('Algo inventado') === 'Algo inventado');

echo $fallos ? "\n$fallos prueba(s) fallaron\n" : "\nTodo bien\n";
exit($fallos ? 1 : 0);
