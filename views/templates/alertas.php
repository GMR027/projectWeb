<?php
if (isset($alertas) && is_array($alertas)) {
    foreach ($alertas as $tipo => $mensajes) {
        foreach ($mensajes as $mensaje) {
            $clase = $tipo === 'error' ? 'alerta-error' : 'alerta-exito';
            echo '<div class="alerta ' . $clase . '">' . s($mensaje) . '</div>';
        }
    }
}
// Mensajes flash por GET
if (isset($_GET['creado'])) echo '<div class="alerta alerta-exito">Registro creado correctamente</div>';
if (isset($_GET['editado'])) echo '<div class="alerta alerta-exito">Registro actualizado correctamente</div>';
if (isset($_GET['eliminado'])) echo '<div class="alerta alerta-exito">Registro eliminado correctamente</div>';
if (isset($_GET['importado'])) echo '<div class="alerta alerta-exito">Importación lista: ' . (int)$_GET['importado'] . ' renglones, ' . (int)($_GET['mat'] ?? 0) . ' materiales nuevos, ' . (int)($_GET['sub'] ?? 0) . ' paquetes nuevos' . (!empty($_GET['omitidos']) ? ', ' . (int)$_GET['omitidos'] . ' duplicados omitidos' : '') . '.</div>';
if (isset($_GET['ok'])) echo '<div class="alerta alerta-exito">Guardado correctamente</div>';
if (isset($_GET['error'])) echo '<div class="alerta alerta-error">' . s($_GET['error']) . '</div>';
?>
