# ProjectWeb — Sistema de Programas de Obra

Plataforma web para crear y gestionar programas de obra de múltiples proyectos, con diagrama de Gantt por semanas, calendario operativo configurable, restricciones por actividad y exportación multi-formato.

## Stack

- **Backend:** PHP 8+, MySQL (`project`), MVC propio (ActiveRecord + Router)
- **Frontend:** HTML5, CSS3 (SASS), JS vanilla
- **Build:** Gulp (sass + js)
- **PDF:** Dompdf (tabloide 11×17" landscape)
- **Exportación:** XML MSPDI (compatible MS Project), POD (ProjectLibre)

## Instalación

```bash
# 1. Dependencias PHP
composer install

# 2. Dependencias Node
npm install

# 3. Configurar entorno
cp .env.example .env
# Edita .env con tus credenciales de BD

# 4. Crear BD
mysql -u root < database/schema.sql

# 5. Compilar assets
npx gulp

# 6. Levantar servidor
cd public && php -S localhost:8000
```

Abre `http://localhost:8000/login`. Credenciales iniciales:
- **Email:** `admin@po.local`
- **Password:** `admin123` (cámbialo después del primer login)

## Roles

| Rol | Permisos |
|---|---|
| **admin** | Control total: crear/editar/eliminar proyectos, partidas, actividades, restricciones, calendario, usuarios, exportación |
| **cliente** | Ver programa de obra, ver calendario, descargar exportaciones |
| **visitante** | Solo ver programa de obra (sin descargar ni editar) |

## Estructura

```
PO/
├── controllers/          # Lógica HTTP
├── models/               # ActiveRecord (Proyecto, Partida, Actividad, Restriccion, etc.)
├── views/                # Templates PHP
├── classes/
│   ├── Calendario.php    # Motor de días hábiles
│   ├── GanttRenderer.php # Prepara datos para el Gantt
│   └── Exportadores/     # PdfExporter, MspdiXmlExporter, PodExporter
├── includes/             # Bootstrap, helpers, conexión DB
├── public/               # Punto de entrada web
│   ├── index.php
│   └── build/            # CSS/JS compilados
├── src/
│   ├── scss/             # SASS (paleta Vanguard CivilTech)
│   └── js/               # JS vanilla
├── database/schema.sql
├── gulpfile.js
└── composer.json
```

## Calendario operativo (por proyecto)

Cada proyecto tiene su propia jornada semanal y días no laborables:

- **Jornada semanal:** factor 0-1 por día de la semana (L-D). Ej: L-V=1, S=0.5, D=0.
- **Días no laborables:** festivos, paros técnicos, suspensiones por clima.

El motor `Classes/Calendario` calcula automáticamente:
- `fecha_fin` de una actividad = `fecha_inicio` + N días hábiles
- Considera factores parciales (sábado 0.5 cuenta como medio día)
- Salta festivos/paros registrados

Cuando creas o editas una actividad, solo ingresas `fecha_inicio` y `duracion_dias` (hábiles). El sistema calcula `fecha_fin` respetando el calendario del proyecto.

## Diagrama de Gantt

- Vista por semanas con su número ISO (`S40`, `S41`...)
- Barras de partidas en negro/gris con color de fondo opcional
- Barras de actividades coloreadas por status:
  - 🟢 Verde: completada (100%)
  - 🟡 Amarillo: en proceso (1-99%)
  - ⚪ Gris: no iniciada (0%)
- Punto rojo pulsante si la actividad tiene restricción activa

## Exportación

Desde **Ajustes → Centro de Exportación** (requiere rol admin o cliente):

| Formato | Descripción |
|---|---|
| **PDF** | Gantt + tabla en tabloide (11×17") horizontal, paginado cada 12 semanas |
| **XML** | Formato MSPDI compatible con MS Project (ábrelo en MSP y guárdalo como .mpp) |
| **POD** | Compatible con ProjectLibre |

El nombre del archivo se genera automáticamente con formato `Y_m_d_NombreUsuario.extension` y se registra en historial con hash SHA-256.

## Flujo típico

1. **Login** como admin
2. **Crear proyecto** (define fechas, ubicación, logo, color)
3. **Crear partidas** (contenedores con código WBS, ej: P-01 Cimentación)
4. **Crear actividades** dentro de partidas (fecha inicio + duración hábil → fecha fin auto)
5. **Ajustar calendario** (modificar jornada semanal, agregar festivos)
6. **Agregar restricciones** a actividades bloqueadas (RFI, Material, etc.)
7. **Actualizar progreso** conforme avanza la obra
8. **Exportar** PDF para junta o XML para MSP

## Comandos útiles

```bash
# Desarrollo (watch SCSS/JS)
npx gulp dev

# Compilar para producción
npx gulp

# Servidor local
cd public && php -S localhost:8000

# Resetear BD
mysql -u root -e "DROP DATABASE project;"
mysql -u root < database/schema.sql
```

## Paleta de diseño (Vanguard CivilTech)

- **Fondo:** `#0B132B` (azul profundo)
- **Panel:** `#1C2541` con glassmorphism
- **Acción primaria:** `#FF5A1F` (naranja seguridad)
- **Acento telemetría:** `#00B4D8` (cian láser)
- **Tipografía:** Space Grotesk (títulos), Manrope (cuerpo), JetBrains Mono (códigos)

## Próximas integraciones

Este sistema está diseñado para conectarse con:
- **4WLA digitalizado** (vista semanal de lookahead)
- **Sistema de reportes de campo**
- **Dashboard ejecutivo consolidado**

La estructura de BD y los modelos ya contemplan la trazabilidad necesaria (proyectos, partidas, actividades, restricciones, exportaciones).
