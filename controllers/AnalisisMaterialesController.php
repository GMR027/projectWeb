<?php

namespace Controllers;

use Classes\AnalisisMateriales;

/** Análisis de materiales: la app de escritorio manda el análisis de la IA; aquí se guardan y el admin acepta o rechaza cada solución */
class AnalisisMaterialesController extends HallazgosIAController
{
    protected const MODELO = AnalisisMateriales::class;
    protected const RUTA = 'analisis-materiales';
    protected const SESION_MSG = 'analisis_materiales_msg';
    protected const TITULO = 'Análisis de materiales';
    protected const TXT_SOLO_ADMIN = 'Solo el administrador puede usar el análisis de materiales';
    protected const TXT_PENDIENTES = 'hallazgo(s)';
}
