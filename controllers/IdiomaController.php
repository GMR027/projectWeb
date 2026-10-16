<?php

namespace Controllers;

/** Cambia el idioma de la interfaz (cookie de un año) y regresa a la página donde estaba el usuario */
class IdiomaController
{
    public static function cambiar()
    {
        guardar_idioma((string)($_POST['idioma'] ?? 'es'));
        $volver = (string)($_POST['volver'] ?? '/');
        if ($volver === '' || $volver[0] !== '/' || str_starts_with($volver, '//') || str_contains($volver, "\n")) $volver = '/';
        header('Location: ' . $volver);
        exit;
    }
}
