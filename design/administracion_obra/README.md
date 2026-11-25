# ProjectWeb · Administración de obra — pantallas de Stitch

Referencias visuales generadas en Google Stitch (proyecto `12374215562357501557`) con el
sistema Vanguard CivilTech y la clave de `STITCH_API_KEY` del `.env`.

| Carpeta | Pantalla |
|---|---|
| `directorio/` | Directorio de obra con pestañas, KPIs y mesa de personas |
| `contratos/` | Contratos, fianzas y seguros con vencimientos y cumplimiento |
| `costos/` | Costos y presupuesto con valor ganado, curva y catálogo |
| `riesgos/` | Riesgos, matriz 3×3 y carga de recursos |
| `historial/` | Historial de cambios, datos de obra y acciones rápidas |

Cada carpeta conserva el `code.html` exportado por Stitch y una captura `screen.png`.
La implementación real vive en `src/scss/paginas/_administracion.scss` y en las vistas de
`views/`; no se pega el HTML de Stitch en la aplicación.
