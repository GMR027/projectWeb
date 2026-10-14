# Contexto del proyecto — ProjectWeb Programa de Obra

Actualizado: 13/10/2026. Para uso de quien continúe el trabajo (humano o agente). Complementa `AGENTS.md`.

## 1. Stack y entorno
- PHP 8 MVC (patrón UpTask_MVC), MySQL `project`, raíz `/home/edgar/Documentos/programacion/PROJECT/PO`.
- Servidor: `cd public && php -S localhost:8000`. Assets: `npx gulp` (SCSS en `src/scss/`, JS en `src/js/`, salida `public/build/`).
- Librerías: Dompdf 3, PhpSpreadsheet, Intervention Image v3, Konva y Chart.js vendorizados en `public/vendor/`.
- Zona horaria en `includes/app.php` (`APP_TIMEZONE` o `America/Mazatlan`).
- Admin: `admin@po.local / admin123`. Otros: id 2 "Cliente Test" (cliente), id 3 "Visitante Test" (contraseñas desconocidas).
- Idioma de trabajo: español.
- Diseño: Vanguard CivilTech (dark mode, glassmorphism), medidas en `rem` (html 62.5%).

## 2. Módulos construidos
- **Programa de obra:** partidas/actividades con calendario (jornada semanal por proyecto + festivos), Gantt CSS Grid, edición inline de fechas (`/api/actividad/actualizar-fecha`), exportación PDF tabloide / XML MSPDI / POD.
- **Hitos:** `actividades.hito` (duración 0, fin = inicio); casilla "Es un hito"; rombo en Gantt; "Hito" en PDF; Milestone en XML.
- **Roles y accesos por proyecto:** `admin`, `cliente`, `visitante`; helpers `is_admin()`, `puede_editar()`, `puede_descargar()`, `proyecto_de_peticion()`, `exigir_ver_proyecto`, `exigir_gestionar_proyecto`. `/ajustes` solo para quien gestiona.
- **4WLA (Lookahead):** export PDF/xlsx/ods.
- **MSR:** materiales, submittals, importador xlsx, export PDF A4/xlsx/ods. `/submittals` ordenado por `numero_submittal`.
- **Curvas de productividad:** cálculo automático con `database/curvas_porcentajes.json`, gráfica maestra, línea HOY, export vía `GraficaGd`.
- **Last Planner:** post-its 7×7 cm por día y partida, rombos verde/amarillo/rojo, PDF 2 o 3 columnas. Opción "★ Mostrar todas" (`LastPlanner::tarjetasTodas`, en pantalla y PDF como `partida_id=juntas`).
- **vPlan:** láminas por partida; tablas adicionales (catálogo con `nombre_interno` distinto del título impreso); recovery 1/2; restricciones automáticas por lámina (`Vplan::asegurarRestricciones`); editor Konva (`public/vendor/vplan-editor.js`); PDF tabloide `VplanPdfExporter`. Inicio/fin de cada tarea editables en la lámina: son la misma actividad del programa.
- **Modal unificado:** `window.confirmarModal/avisoModal`, `data-confirm="titulo|mensaje|botón"`.
- **Exportar todo:** `POST /exportar/todo` (`proyecto_id`, `nombre_base`) → ZIP (Programa, 4WLA, MSR, Curvas, vPlan; sin Last Planner; LEEME.txt). Exportaciones individuales: `POST /exportar/generar` (`proyecto_id`, `formato`, `nombre_base`, …).
- Helpers de nombre: `generar_nombre_archivo()`, `slug_archivo()` (sin acentos), `fecha_corta_es()`, `fecha_larga_es()`.

## 3. Proyecto de prueba (id 10, código GRX-PRUEBA)
Cargado desde `/home/edgar/Descargas/informacionprueba` (PDF originales no se tocan). Dueño Cliente Test (2), acceso visitante 3. 16 partidas, 90 actividades (3 hitos), jornada L–D = 1, MSR 59 renglones / 11 submittals, 3 curvas, 15 láminas vPlan.
- Datos y scripts en el scratchpad (`/tmp/claude-1000/-home-edgar-Documentos-programacion-PROJECT-PO/bd58d722-4bbd-4859-b553-6b4434a49074/scratchpad/ip/`, ruta guardada en `/tmp/claude-1000/sp`): `build_datos2.py` → `datos.json`, `cargar2.php` (borra el proyecto GRX-PRUEBA anterior y recarga). El id del proyecto vigente está en `proyecto_id.txt` / `/tmp/claude-1000/pidp`. El scratchpad es temporal.
- Informe de correcciones y pendientes: `informe_datos_prueba.md` (raíz del proyecto).

Decisiones del usuario aplicadas: fechas del programa = START/FINISH del vPlan (recovery se guarda en la lámina); domingo trabajable; hitos; partidas nuevas CAM-19, "60 y 9 bahías" y Desmantelamiento (2 partidas, 7 tareas, 2 láminas); actividades "Cableado horizontal ACP → IT-HUB" y "Panel de control de acceso - Fase 1" (25–26/05/2026 supuestas); fin de Submittals = fecha más larga de respuesta del MSR; MSR con instalación = entrega si era anterior; curvas medidas con nodos / tubería IMC 3/4" / cable UTP Cat 6A; sin Punch list; actividades vencidas se dejan.

## 4. Pendientes y preguntas abiertas
1. ¿Quitar las 16 tareas finales por área (prueba/servidor/licencia/remate) duplicadas con "60 y 9 bahías"?
2. Fechas reales del panel de control de acceso Fase 1.
3. Confirmar Submittal 3.3 termina 08/03/2026 (interpretación propia).
4. Fechas "TBD" sin soporte; baseline no implementada.
5. Planos de láminas son recortes aproximados; MSR sin fotos; sin contraseña de Cliente Test (no probado el login como cliente).
6. No probado en navegador: casilla "Es un hito", hitos en Gantt/XML, edición de fechas desde la lámina vPlan.
7. Roadmap (AGENTS.md): notificaciones por email, dependencias/CPM, curva S, API REST, evidencias fotográficas, multi-idioma.

## 5. Avisos operativos
- No usar `rm` con globs (se bloqueó una vez); no usar `pkill -f`; matar por PID.
- No modificar datos reales del usuario; borrar solo datos de prueba propios.
- Confirmar antes de acciones difíciles de revertir o externas.
- Esquema: `database/schema.sql` y `database/migracion_hitos_catalogo.sql` (hito, nombre_interno).
