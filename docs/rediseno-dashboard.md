# Rediseño — Inicio (dashboard)

Rediseño del **Inicio** (`/dashboard`): hero de mando, KPIs, rejilla de widgets, tarjetas
de obra, lanzador de herramientas y ventana de personalización. Las pantallas se generaron en
Google Stitch con la clave `STITCH_API_KEY` de `.env` y el sistema **Vanguard CivilTech**.

- Proyecto Stitch: `projects/11371487877285600733` (**ProjectWeb · Dashboard e inicio**)
- Sistema de diseño: `assets/18bc16bf28b84176ab46ee8bdee7800a`
- Referencias exportadas: `design/dashboard/` (`code.html` y `screen.png`)

## Objetivo

Que al entrar se lea, sin abrir nada, **qué obras tengo, qué requiere atención y desde dónde
trabajo**. El inicio deja de ser una lista de bloques y pasa a ser un **centro de mando** con
una rejilla de widgets que el usuario puede ordenar, ocultar y dimensionar.

## Decisiones de diseño

- **Hero compacto**: saludo, fecha, plan/organización, chips de cartera y acciones
  (nuevo proyecto, herramientas y personalizar).
- **KPIs accionables**: proyectos, actividades de hoy, atrasadas y restricciones activas. Los
  dos últimos llevan al bloque de atención o al programa.
- **Rejilla de 12 columnas**: «Requiere atención» (pestañas Hoy/Atrasadas), «Proyectos»,
  «Herramientas», «Novedades» y «Accesos rápidos». Cada widget tiene cabecera, contador y
  acción propia.
- **Proyectos**: tarjeta con código, estado (por iniciar/en ejecución/finalizada/archivada),
  avance, fechas, salud compacta, revisiones y acciones.
- **Herramientas**: selector de obra, chips de las 7 secciones de `herramientas_proyecto()` y
  fichas de herramientas. Las fijadas y recientes de la navegación compacta se muestran
  primero; «Ver todas» abre la ventana buscable.
- **Ventanas**: la personalización y el lanzador completo son `<dialog>` nativos sobre la capa
  común `.ventana-*`; en móvil se comportan como hoja inferior.
- **Tema**: todo sale de las variables (`--surface`, `--primary`, `--primary-soft`, `--border`,
  `--text-muted`, `--danger`…), por lo que respeta tema claro/oscuro y la paleta del proyecto.
- **Lista de proyectos**: cabecera de portafolio con icono, contadores de obras activas y
  archivadas, chips de cupo y acciones; tarjetas con estado (en ejecución, por iniciar,
  finalizada o archivada), anillo de avance, fechas, carátula opcional y acciones completas.
- **Móvil**: KPIs en 2 columnas, widgets a ancho completo, lista de atención compacta, selectores
  táctiles y la barra inferior existente.

## Cambios principales

| Archivo | Cambio |
|---|---|
| `views/dashboard/index.php` + `_hero`, `_android`, `_resumen`, `_atencion`, `_proyectos`, `_herramientas`, `_novedades`, `_atajos`, `_vacio`, `_personalizar`, `_herramientas-dialogo` | Inicio dividido en parciales y nueva estructura de widgets |
| `classes/InicioUsuario.php` | Bloques `atencion` y `herramientas`, ancho por bloque (`ancho`: 12/6/4), migración de lectura de `hoy`/`atrasadas`, KPI de restricciones |
| `controllers/DashboardController.php` | Proyecto de referencia del lanzador, conteo de obras activas y guardado del ancho de cada bloque |
| `public/vendor/inicio.js` | Pestañas, diálogos, orden/anchura, vista previa, selector de proyecto y fijadas/recientes |
| `src/scss/paginas/_inicio.scss` | Capa visual del inicio y sus ventanas |
| `views/proyectos/listar.php` + `_card.php` | Lista de proyectos con cabecera de portafolio, chips de estado y tarjeta compartida con avance, fechas y acciones |
| `src/scss/paginas/_dashboard.scss` | Estilos de la lista de proyectos, tarjeta compartida y ficha de detalle |
| `src/scss/layout/_layout.scss` | `align-content: start` en `.app-container` para que la barra superior no se estire en páginas cortas |
| `views/salud/_semaforo.php` | En modo compacto muestra hasta 6 indicadores y un enlace «+N más» a la ficha de salud |
| `includes/funciones.php` | `dashboard` carga `dashboard + personalizacion + inicio + ventanas`; `proyectos-listar` usa la capa de suite |
| `src/scss/app.scss` | Importa `paginas/inicio` después de personalización |
| `includes/lang/en.php` | Traducciones de los textos nuevos |
| `tests/run.php` | Bloques y anchos válidos del inicio |
| `design/dashboard/` | Referencias de Stitch (inicio y proyectos) |

**Sin migración de base de datos.** `usuarios.inicio_json` sigue siendo la fuente: las
configuraciones antiguas se leen sin cambios y los bloques nuevos se añaden desactivados.

## Pantallas de Stitch

| Pantalla | Ruta | En el repositorio |
|---|---|---|
| Inicio · centro de mando | `projects/11371487877285600733/screens/0b4704f5551d406d91726570f67dc643` | `design/dashboard/escritorio/` |
| Ventana «Todas las herramientas» | `.../screens/b8c84fecb3b044b7b35d867138747e36` | `design/dashboard/herramientas/` |
| Personalizar inicio | `.../screens/9ca0abfe79f646f790d6053547ca61f4` | `design/dashboard/personalizar/` |
| Inicio móvil | `.../screens/8a196b3677f94989b813e90fdb759d73` | `design/dashboard/movil/` |
| Primer proyecto (vacío) | `.../screens/01f51b77b4f84852bf24579ba16dc4da` | `design/dashboard/vacio/` |
| Proyectos · escritorio | `.../screens/c07c8b8bd09041dc92a68448772ff31e` | `design/dashboard/proyectos/escritorio/` |
| Proyectos · móvil | `.../screens/39ac066949dd42339b63372e712d7dd6` | `design/dashboard/proyectos/movil/` |

La implementación usa las variables del tema y el marcado propio de la aplicación, **no** el
HTML de Stitch.

## Cómo probar

```bash
npx gulp
php tests/run.php
php bin/idioma_cobertura.php
cd public && php -S localhost:8000 router.php
```

- Entrar con un usuario con varias obras; revisar KPIs, pestañas Hoy/Atrasadas y tarjetas.
- Abrir «Herramientas» desde el hero y con `Ctrl/⌘ + K`; buscar, cambiar de sección y de obra.
- Abrir `/proyectos`; revisar la cabecera, los chips, los estados de cada obra y las acciones.
- Abrir «Personalizar inicio»; ocultar, reordenar y cambiar el ancho de los bloques; guardar y
  recargar. Probar «Volver al inicio de siempre».
- Revisar con 0 proyectos (estado de primeros pasos), con 1 y con muchas obras.
- Alternar tema claro/oscuro y ancho móvil (390 px). Probar teclado: tabulación, Escape y
  `Ctrl/⌘ + K`.
- Entrar como cliente y visitante: las herramientas deben seguir filtrándose por permisos.

## Notas

- La hoja `_dashboard.scss` se conserva para las tarjetas de `/proyectos`; el inicio usa
  `_inicio.scss`. Al cambiar una tarjeta compartida, revisar ambas vistas.
- El selector de obra actualiza el `?proyecto=` de los enlaces; el servidor renderiza las
  herramientas del proyecto más reciente y el JS cambia el destino. Si en el futuro se necesita
  filtrar herramientas distintas por obra, se puede añadir un endpoint que devuelva los grupos y
  reemplace el HTML del lanzador.
- `pw-nav-fijadas` y `pw-recientes` se comparten con la navegación compacta; no se duplican en
  la base.
- La clave de Stitch se leyó de `.env` (`STITCH_API_KEY`); la clave fija que tenía la
  configuración del MCP ya no era válida. Las pantallas se descargaron con esa misma clave y
  quedaron en `design/dashboard/`.
