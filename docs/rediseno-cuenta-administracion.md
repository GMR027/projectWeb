# Rediseño — Cuenta y administración

Rediseño visual de las pantallas de cuenta y operación de la plataforma:
**Usuarios y accesos (`/usuarios`), Organizaciones (`/organizaciones`), Bandeja de entrada
(`/bandeja`), Sistema (`/sistema`) y Ajustes del proyecto (`/ajustes`)**.

Las pantallas se generaron en Google Stitch con la clave `STITCH_API_KEY` del archivo `.env`
y el sistema **Vanguard CivilTech**. El proyecto de Stitch es
`projects/17702726569564700150` (**ProjectWeb · Cuenta y administración**) y las referencias
quedaron exportadas en `design/cuenta_administracion/` (`code.html` y `screen.png`).

## Objetivo

Que la administración de la cuenta y del sistema se lea como un **centro de control** y no
como una suma de formularios: qué cuentas y empresas hay, qué requiere atención, cuánto
espacio y actividad consume la plataforma, y dónde se cambia cada cosa sin perderse entre
secciones.

## Decisiones de diseño

- **Cabecera de mando** en las cinco pantallas: icono en placa, eyebrow, título, subtítulo,
  chips de contexto y acciones a la derecha.
- **KPIs accionables** con `.cb-kpis` / `.cb-kpi`: valor monoespaciado, icono y pista de
  lectura. Los tonos de alerta marcan topes, mensajes sin leer y volumen de disco.
- **Riel de secciones** para `/sistema` y `/ajustes`: sticky en escritorio, horizontal con
  desplazamiento en móvil, buscador y estado activo. En Sistema funciona por anclas y
  scrollspy; en Ajustes mantiene el cambio de sección y el `sessionStorage`.
- **Listas operativas**: avatares con iniciales, cifras tabulares, barras de uso, hover con
  tinte del acento y acciones agrupadas a la derecha.
- **Formularios por bloques** (`ctl-form`, `ctl-bloque`, `ctl-campos`) con foco visible,
  resumen lateral y pie de acciones; se apoyan en los estilos de la suite de administración.
- **Bandeja tipo inbox**: icono de tipo, remitente, asunto, vista previa, fecha y punto de no
  leído. Los modales de lectura y respuesta se conservan y se presentan como hoja de lectura.
- **Tema claro/oscuro**: todo sale de las variables del proyecto (`--surface`, `--primary`,
  `--secondary`, `--border`, `--text-muted`, `--danger`); no se fijan colores del tema oscuro.
- **Móvil**: KPIs en dos columnas, riel desplazable, tablas dentro de su panel, filas de la
  bandeja como tarjetas y acciones táctiles de 44 px.

## Cambios principales

| Archivo | Cambio |
|---|---|
| `src/scss/paginas/_control.scss` | Nueva capa visual: riel, bandeja, avatares, barras de uso, tarjetas de plan, formularios por bloques y responsive |
| `src/scss/paginas/_administracion.scss` | Se añadieron las vistas de cuenta a `$adm` y alias `.ctl-form`, `.ctl-campos`, `.ctl-check`, `.ctl-ancho` y `.ctl-form-pie` |
| `src/scss/paginas/_ajustes.scss` | Se quitó `.alm-barra` (ahora vive en `_control.scss`) y se conservan calendario, exportadores y tema |
| `includes/funciones.php` | `css_paginas_de_vista()` carga `administracion + control` en las cinco vistas; Ajustes conserva sus hojas propias |
| `src/scss/app.scss` | Importa `paginas/control` después de las hojas de administración |
| `public/vendor/control.js` | Riel compartido: búsqueda, pestañas, hash, `sessionStorage` y scrollspy |
| `models/Usuario.php`, `controllers/AuthController.php` | Conteos por rol y filtro `rol` en la búsqueda de usuarios |
| `views/auth/*` | Usuarios, Mi equipo, alta y edición con hero, KPIs, filtros, avatares y formularios por bloques |
| `controllers/OrganizacionController.php`, `views/organizaciones/*` | Filtros de estado y búsqueda, KPIs, tabla con uso, ficha con avisos y formularios por bloques |
| `controllers/BandejaController.php`, `views/bandeja/*` | KPIs globales, búsqueda en vivo, filtro «solo sin leer» y lectura/respuesta rediseñadas |
| `controllers/SistemaController.php`, `views/sistema/ajustes.php` | Riel de secciones, KPIs, un solo aviso global y corrección de la tarjeta de Idioma duplicada |
| `views/ajustes/index.php` | Hero con chips, riel agrupado con búsqueda, sección Apariencia separada y limpieza de scripts duplicados |
| `docs/rediseno-cuenta-administracion.md` | Este documento |
| `design/cuenta_administracion/` | Referencias de Stitch |

**Sin cambios de rutas, permisos, esquema, IDs de formularios ni endpoints.** Los formularios
siguen apuntando a los mismos controladores y el JavaScript existente conserva sus selectores.

## Correcciones incluidas

- **`/sistema` mostraba la tarjeta «Idioma» dos veces** porque estaba dentro del `foreach` de
  los volúmenes. Ahora se renderiza una sola vez, en su sección y con un único `id="idioma"`.
- **`/sistema` repetía el mismo aviso de éxito/error** en varias tarjetas. Ahora hay un solo
  aviso global (`role="status"`/`role="alert"`).
- **`/ajustes` tenía dos scripts propios** para las pestañas y el buscador. Se reemplazaron por
  `control.js`, compartido con Sistema.
- **La ficha de organización** muestra avisos de riesgo (sin propietario, trial por vencer,
  topes al 90 %) calculados con los datos que ya tenía el controlador.

## Pantallas de Stitch

| Pantalla | Ruta de Stitch | En el repositorio |
|---|---|---|
| Usuarios y accesos | `projects/17702726569564700150/screens/7ce4dad8e5bd419b81277c9a37a53c07` | `design/cuenta_administracion/usuarios/` |
| Organizaciones | `.../screens/b2dd6ce54e094a26b1e80181cf051db8` | `design/cuenta_administracion/organizaciones/` |
| Ficha de organización | `.../screens/d0abb5c330294fa19d10b00e6764a758` | `design/cuenta_administracion/organizacion_ficha/` |
| Bandeja de entrada | `.../screens/c349710335534b79adb9a32a1ac1cbcd` | `design/cuenta_administracion/bandeja/` |
| Sistema | `.../screens/c908b6e5a50b42068d2f3c383f4bc05d` | `design/cuenta_administracion/sistema/` |
| Ajustes del proyecto | `.../screens/40158be371ec4123a0e2c3fa2016f396` | `design/cuenta_administracion/ajustes/` |

La implementación usa las variables del tema y el marcado propio de la aplicación, **no** el
HTML de Stitch. La clave del MCP configurado en OpenCode estaba vencida; las pantallas se
generaron con la misma `STITCH_API_KEY` de `.env` que usan los rediseños anteriores.

## Cómo probar

```bash
npx gulp
php tests/run.php
php bin/idioma_cobertura.php
cd public && php -S localhost:8000 router.php
```

- Entrar como administrador y revisar Usuarios: buscar, cambiar de rol, paginar, crear y editar.
- Entrar como cliente dueño: `/usuarios` muestra «Mi equipo» con visitantes, colaboradores y topes.
- Organizaciones: filtrar por estado, buscar por empresa/RFC/propietario, abrir la ficha,
  cambiar plan/topes, suspender y reactivar, agregar clientes y equipo.
- Bandeja: contar sin leer, buscar, filtrar por tipo, abrir un mensaje, responder con fotos y eliminar.
- Sistema: navegar por el riel, comprobar que Idioma aparece una sola vez, revisar almacenamiento,
  respaldos, sesiones, publicaciones, IA y accesos.
- Ajustes: cambiar de sección, buscar en el riel, guardar jornada, festivos, apariencia, respaldo,
  accesos y contraseña.
- Alternar tema claro/oscuro y revisar 1440, 1024 y 390 px.
- Confirmar que `#sesiones`, `#respaldo`, `#idioma`, `#tema` y los demás anclas abren la sección correcta.

## Notas

- `_control.scss` se carga solo en estas vistas; no se pega CSS de otras herramientas.
- `.alm-barra` se movió a `_control.scss` porque la usan Ajustes y Sistema.
- La ficha de organización ya no usa `.grid-proyectos` (hoja del dashboard): usa `.ctl-grid`.
- El riel de Sistema no oculta secciones (modo `scroll`); el de Ajustes sí (modo `tabs`).
- No se añadieron librerías, CDN, fuentes ni migraciones de base de datos.
