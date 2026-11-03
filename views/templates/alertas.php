<?php
// Los errores con campo se pintan junto al campo (src/js/form-errores.js); en la
// alerta general solo queda lo que no pertenece a un campo concreto.
$__campos = (isset($alertas['error_campo']) && is_array($alertas['error_campo'])) ? $alertas['error_campo'] : [];
if ($__campos) {
    $__json = json_encode($__campos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script type="application/json" id="errores-campo">' . $__json . '</script>';
}
if (isset($alertas) && is_array($alertas)) {
    foreach ($alertas as $tipo => $mensajes) {
        if ($tipo === 'error_campo') continue;
        if ($tipo === 'error' && $__campos) $mensajes = array_values(array_diff($mensajes, array_values($__campos)));
        foreach ($mensajes as $mensaje) {
            $esError = $tipo === 'error';
            echo '<div class="alerta ' . ($esError ? 'alerta-error' : 'alerta-exito') . '" role="' . ($esError ? 'alert' : 'status') . '">' . s($mensaje) . '</div>';
        }
    }
    if ($__campos) {
        echo '<div class="alerta alerta-error" role="alert">Revisa los campos marcados en el formulario.</div>';
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
