<?php
if (isset($alertas) && is_array($alertas)) {
    foreach ($alertas as $tipo => $mensajes) {
        foreach ($mensajes as $mensaje) {
            $esError = $tipo === 'error';
            echo '<div class="alerta ' . ($esError ? 'alerta-error' : 'alerta-exito') . '" role="' . ($esError ? 'alert' : 'status') . '">' . s($mensaje) . '</div>';
        }
    }
}
// Mensajes flash por GET
$flash = [
    'creado' => 'Registro creado correctamente',
    'editado' => 'Registro actualizado correctamente',
    'eliminado' => 'Registro eliminado correctamente',
];
foreach ($flash as $clave => $texto) {
    if (isset($_GET[$clave])) echo '<div class="alerta alerta-exito" role="status">' . $texto . '</div>';
}
if (isset($_GET['importado'])) echo '<div class="alerta alerta-exito" role="status">Importación lista: ' . (int)$_GET['importado'] . ' renglones, ' . (int)($_GET['mat'] ?? 0) . ' materiales nuevos, ' . (int)($_GET['sub'] ?? 0) . ' paquetes nuevos' . (!empty($_GET['omitidos']) ? ', ' . (int)$_GET['omitidos'] . ' duplicados omitidos' : '') . '.</div>';
if (isset($_GET['ok'])) echo '<div class="alerta alerta-exito" role="status">Guardado correctamente</div>';
if (isset($_GET['error'])) echo '<div class="alerta alerta-error" role="alert">' . s($_GET['error']) . '</div>';
?>
