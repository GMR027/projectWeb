# Referencias de Stitch — Landing y Servicios

Pantallas generadas en Google Stitch con la clave `STITCH_API_KEY` de `.env` y el sistema
**Vanguard CivilTech** (copiado del proyecto de Dashboard).

- Proyecto: `projects/16849126739489043138` (**ProjectWeb · Landing 2026**)
- Sistema de diseño: `assets/8157880286616599207`
- Implementación: `views/landing/`, `views/layout-landing.php`, `src/scss/landing.scss`
  y `public/vendor/landing.js`. **No se pega el HTML de Stitch** en la aplicación.

| Carpeta | Pantalla | Screen ID | Tamaño |
|---|---|---|---|
| `hero/` | Hero + “Un dato, toda la obra” | `deb782f3b98e428f941da53be47f170d` | 2560×6264 |
| `funciones/` | Funciones + cinco frentes | `d256a07bc3f345bb8380e8ef38cfdcfe` | 2880×3958 |
| `ahorro/` | ¿Por qué ProjectWeb? + ahorro | `67a46e5ae2d3487cbd239c559e9c8a8d` | 2560×8972 |
| `servicios/` | Servicios de seguimiento | `562cd573942d4164bda09b60fdb8e296` | 2560×15706 |
| `movil/` | Landing móvil | `b788993da8f84a01b61cd9994b3c6745` | 780×9160 |

Cada carpeta conserva `screen.png` (captura) y `code.html` (referencia de Stitch), más
`stitch.json` con el nombre y las dimensiones de la pantalla.

La implementación usa el tema de la aplicación (variables `--primary`, etc. cuando aplica),
los iconos de Font Awesome locales y las capturas reales del demo en
`public/build/img/landing/`. Los textos y precios reales salen de
`Classes\LandingContenido`, `Classes\Ahorro` y `Classes\Plan`; las pantallas de Stitch son
solo referencia visual.
