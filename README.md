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

# 3. Configurar entorno (.env NO se versiona: cada equipo tiene el suyo)
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
| **PDF** | Gantt + tabla en tabloide (11×17") horizontal, con columnas semanales, quincenales o mensuales según la duración y un máximo de 14 columnas por página |
| **XML** | Formato MSPDI compatible con MS Project (ábrelo en MSP y guárdalo como .mpp) |
| **POD** | Compatible con ProjectLibre |

El nombre del archivo usa el prefijo y el sufijo elegidos en el Centro de Exportación (fecha `Y-m-d`, `d-m-Y`, numerador o texto) y el formato `prefijo-nombre-CÓDIGO-EMPRESA-sufijo.ext`. Sin configuración, la fecha va al inicio.

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

# Servidor local (permite archivos de hasta 250 MB en la sección Archivo)
cd public && php -d upload_max_filesize=250M -d post_max_size=260M -d max_execution_time=300 -S localhost:8000

# Pruebas de las piezas que no necesitan base de datos
php tests/run.php

# Pruebas de integración (requiere MySQL; usa y borra una BD desechable, p. ej. project_ci)
DB_HOST=127.0.0.1 DB_USER=root DB_PASS= DB_NAME=project_ci php tests/integracion.php

# Resetear BD
mysql -u root -e "DROP DATABASE project;"
mysql -u root < database/schema.sql
```

## Despliegue en el clúster

En una máquina con `docker`, `kubectl`, `helm` y acceso al clúster:

```bash
./deploy.sh                   # solo código: imagen + helm upgrade (no toca la BD)
./deploy.sh --db              # ⚠️ REEMPLAZA la BD del clúster con tu BD local
./deploy.sh --files           # copia uploads, exportaciones y storage encima
```

`./deploy-login-intentos.sh` hace el despliegue de código y además crea la tabla nueva `login_intentos` **solo si no existe**, sin tocar datos existentes. Requiere que el código nuevo ya esté en el repositorio (`git pull` lo hace él mismo).

## Paleta de diseño (Vanguard CivilTech)

- **Fondo:** `#0B132B` (azul profundo)
- **Panel:** `#1C2541` con glassmorphism
- **Acción primaria:** `#FF5A1F` (naranja seguridad)
- **Acento telemetría:** `#00B4D8` (cian láser)
- **Tipografía:** Inter (texto y títulos) y Fira Code (códigos y fechas)

## Próximas integraciones

Este sistema está diseñado para conectarse con:
- **4WLA digitalizado** (vista semanal de lookahead)
- **Sistema de reportes de campo**
- **Dashboard ejecutivo consolidado**

La estructura de BD y los modelos ya contemplan la trazabilidad necesaria (proyectos, partidas, actividades, restricciones, exportaciones).
