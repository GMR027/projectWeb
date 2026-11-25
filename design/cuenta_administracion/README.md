# ProjectWeb · Cuenta y administración — pantallas de Stitch

Referencias visuales generadas en Google Stitch (proyecto `17702726569564700150`) con el
sistema **Vanguard CivilTech** y la clave `STITCH_API_KEY` del `.env`.

Sistema de diseño: `assets/19e0c62a46b943d3b6fc6b67eba9d241`.

| Carpeta | Pantalla | Screen ID |
|---|---|---|
| `usuarios/` | Usuarios y accesos: hero, KPIs, filtros por rol y tabla | `7ce4dad8e5bd419b81277c9a37a53c07` |
| `organizaciones/` | Cartera de empresas, uso y planes | `b2dd6ce54e094a26b1e80181cf051db8` |
| `organizacion_ficha/` | Ficha 360: plan, topes, equipo, obras y pagos | `d0abb5c330294fa19d10b00e6764a758` |
| `bandeja/` | Bandeja de entrada y lectura de mensajes | `c349710335534b79adb9a32a1ac1cbcd` |
| `sistema/` | Almacenamiento, respaldos, sesiones y publicaciones | `c908b6e5a50b42068d2f3c383f4bc05d` |
| `ajustes/` | Ajustes del proyecto con riel de secciones | `40158be371ec4123a0e2c3fa2016f396` |

Cada carpeta conserva el `code.html` exportado por Stitch y una captura `screen.png`.
La implementación real vive en `src/scss/paginas/_control.scss`, `_administracion.scss`,
`public/vendor/control.js` y las vistas de `views/`; **no se pega el HTML de Stitch** en la
aplicación.
