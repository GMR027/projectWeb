<?php
/**
 * Recalcula las alertas de portafolio de todos los usuarios (admin y clientes) para la campana.
 * Pensado para cron:  php bin/portafolio_alertas.php
 */
require __DIR__ . '/../includes/app.php';
if (!\Classes\Portafolio::disponible()) {
    fwrite(STDERR, "Falta database/migracion_portafolio.sql\n");
    exit(1);
}
$total = 0;
$usuarios = \Model\Usuario::whereAll('rol', 'cliente');
$admin = \Model\Usuario::whereAll('rol', 'admin');
foreach (array_merge($admin, $usuarios) as $u) {
    $usuario = ['id' => (int)$u->id, 'rol' => $u->rol];
    $total += \Classes\Portafolio::refrescar($usuario);
}
echo "Alertas de portafolio actualizadas: $total\n";
