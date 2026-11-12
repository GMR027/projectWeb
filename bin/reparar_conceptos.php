<?php
// Repara el esquema del catálogo de conceptos en una base que quedó a medias.
// Uso: php bin/reparar_conceptos.php
// Es idempotente y no toca datos. Después, si hay conceptos antiguos:
//   php bin/migrar_conceptos.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/app.php';

use Classes\Conceptos;

$estado = Conceptos::estadoEsquema();
if ($estado['ok']) {
    print("El esquema básico ya está completo; revisando llaves e índices...\n");
} else {
    print("Falta en la base de datos:\n");
    foreach ($estado['faltan'] as $f) print("  - $f\n");
}
print("\nReparando...\n");

[$ok, $mensajes, $final] = Conceptos::repararEsquema();
foreach ($mensajes as $m) print("  $m\n");

if (!$ok) {
    print("\nNo se pudo completar. Faltan todavía:\n");
    foreach ($final['faltan'] as $f) print("  - $f\n");
    print("Revisa los permisos del usuario de la base o aplica a mano database/migracion_catalogo_conceptos.sql.\n");
    exit(1);
}

print("\nListo. El esquema del catálogo está completo.\n");
print("Si tienes conceptos de obra antiguos en actividad_cantidades, ejecuta:\n  php bin/migrar_conceptos.php\n");
exit(0);
