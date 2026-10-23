# Fase 5 — API, idiomas, calidad de la IA e IA nueva

Migración: `database/migracion_fase5.sql` (crea `api_tokens`, `webhooks` y `webhook_entregas`; también está en `schema.sql` y en `aplicar_migraciones_cluster.sh`).

| # | Qué | Dónde |
|---|---|---|
| 5.1 | API REST | `classes/ApiRest.php`, `ApiTokens.php`, `Webhooks.php` (bus interno), `IntegracionesController`, `views/integraciones/`, ruta `/integraciones` |
| 5.2 | Interfaz ES/EN | `includes/idioma.php` (`t()`), `includes/lang/en.php`, `bin/idioma_cobertura.php` |
| 5.3 | Pruebas de calidad de la IA | `classes/PruebasIA.php`, `Herramientas/PruebasCalidad.php`, `/calidad-ia` (pestaña del Centro de IA) |
| 5.4 | IA nueva | `Herramientas/PrediccionAtrasos.php`, `RedactarRfi.php`, `Contradicciones.php` (registradas en `Registro::todas()`) |

## 5.1 API REST (`/api/v1`)
- `public/index.php` llama a `ApiRest::despachar()` **antes** de `csrf_verificar()`: la API atiende la petición completa (sin Router, sin CSRF, sin cookie de sesión). Los ayudantes de permisos de la web (`puede_ver_proyecto`, `puede_gestionar_proyecto`…) leen `$_SESSION`, así que se les da una de usar y tirar (`session.use_cookies=0`, `session_destroy()` al terminar): **lo que el usuario del token puede hacer en la web, eso mismo en la API**.
- Token: `Authorization: Bearer pw_<48 hex>` (o `X-API-Key`). Solo se guarda el SHA-256 (`api_tokens.token_hash`); el completo se muestra una vez al crearlo. Por token: lectura/escritura, proyecto único opcional, vigencia, revocación y 120 peticiones/min (429 + `Retry-After`). Un visitante nunca escribe.
- Las rutas son `ApiRest::RUTAS` (método, patrón con `{id}`, manejador, ¿escribe?, resumen); de ahí sale también `/api/v1/openapi` (sin extensión porque `php -S` da 404 a URLs `.json` inexistentes; con Apache sirve también `/openapi.json`). **Para agregar un endpoint:** una fila en `RUTAS` + un método privado estático.
- Respuestas `{"data": …, "meta": {pagina, por_pagina, total, paginas}}` y errores `{"error": {"codigo", "mensaje"}}`. Un proyecto ajeno responde 404 (no se distingue de «no existe»).
- Escritura: `PATCH /actividades/{id}` (progreso, prioridad, pta, campos libres), `POST /proyectos/{id}/bitacora|punch|rfis`, `PATCH /punch/{id}`, `PATCH /rfis/{id}`.

### Bus de eventos (`Webhooks`) — la herramienta «Webhooks» se retiró
- **`Webhooks.php` hoy es solo el bus interno**: `Webhooks::emitir($pid, $evento, $datos)` reparte el evento y el único suscriptor es el bot de Telegram (`Telegram::aviso()`, que filtra con `Telegram::EVENTOS`). Ya **no envía HTTP a URL de terceros**: no hay cola, firma ni reintentos.
- Se retiraron la sección y las rutas de `/integraciones/webhook/*` (crear, activo, eliminar, probar, reintentar) y los métodos del controlador; `/api/v1/info` y el OpenAPI ya no anuncian `eventos_webhook`.
- **Las tablas `webhooks` y `webhook_entregas` siguen en la BD** (no se borran datos); ya no se leen ni se escriben. Si algún día se reintegra el envío, la lógica SSRF/firma está en el historial de git.
- Emiten solos los modelos de `Webhooks::EVENTO_DE` (`ActiveRecord::crear/actualizar/eliminar` → `avisar()`), y explícitamente `Punch`, `Rfi` y `ApiRest`. **Ojo:** `ActiveRecord::crear` guarda el `insert_id` *antes* de avisar (el aviso hace consultas); haz lo mismo en cualquier código nuevo.
- Eventos: `Webhooks::EVENTOS` (actividad, restricción, bitácora, reporte, submittal, punch, RFI). El subconjunto que escucha Telegram está en `Telegram::EVENTOS` (`docs/telegram.md`).

## 5.2 Multi-idioma
- El idioma (cookie `idioma`, selector ES/EN en la barra superior y en Ajustes) ya existía; esta fase cubrió las pantallas principales: inicio, proyectos, login, programa, Submittals, Bitácora, Reporte diario, Punch, Lookahead (columnas), diseño de listas, campos, plantillas, marca e integraciones.
- **Para traducir una pantalla:** envuelve el texto en `t('Texto en español')` y agrega `'Texto en español' => 'English text'` a `includes/lang/en.php`. `t('Hola {n}', ['n' => 3])` sustituye variables. El texto original (español) es la clave.
- `php bin/idioma_cobertura.php [--lista]` lista los `t()` sin traducción (falla si falta alguno; también lo corre `tests/run.php`). Los nombres genéricos de columnas (`ListaDiseno`, `ProgramaColumnas`) pasan por `t()` salvo que el usuario les haya puesto nombre propio.
- **No se traducen todavía:** manual, correos, PDF, exportaciones, mensajes de error de controladores y la app de escritorio. Las pantallas sin `t()` siguen en español.
- Cuidado con `«$var»` en PHP (lee `»` como parte del nombre): usa `«{$var}»`.

## 5.3 Pruebas de calidad de la IA
- `PruebasIA::casos()` son 13 casos fijos (extraer, clasificar, redactar sin inventar, razonar) con respuesta esperada y **reglas deterministas** (`igual`, `en`, `contiene`, `lista`, `suma`, `numero`, `conjunto`, `orden`, `largo_*`, `sin_numeros_extra`, `espanol`). `puntuar()` da la nota (fracción de reglas cumplidas); no usa IA, así que es reproducible y se prueba en `tests/run.php`.
- Se corre desde la app de escritorio como cualquier informe (`Herramientas\PruebasCalidad`: un lote por caso, con `clave`); el servidor califica y guarda un informe con un bloque `pruebas {version, nota, casos, ms_medio}`. `desktop/src/informes.js` agrega `_ms` a cada respuesta (tiempo por caso).
- `/calidad-ia` compara la **última corrida de cada modelo** de la versión actual (`PruebasIA::comparar`); las de otras versiones no se mezclan. **Si cambias un caso o una regla, sube `PruebasIA::VERSION`.**

## 5.4 IA nueva (todas son informes: el servidor calcula, la IA redacta, y funcionan sin IA)
- **Predicción de atrasos** (`PrediccionAtrasos`): puntaje 0–100 por actividad (pesos en `PESOS`; ≥60 alto, 30–59 medio) con razones, atraso probable al ritmo actual y fin de obra con el SPI (`desempeno()`). Reutiliza `Salud::riesgosActividades` (material, punch, RFI, permisos). Es estimación, no promesa.
- **Redactar RFI** (`RedactarRfi`): contexto del servidor (actividad, sucesoras, materiales, normativa por `Normativa::buscar`, fecha límite sugerida) + redacción; al aprobar (`AplicarInforme::rfi`) crea el RFI abierto. Sin IA usa plantilla.
- **Contradicciones** (`Contradicciones`): oraciones con magnitudes (número + unidad, conversión a unidad base) de `normativa_fragmentos` y `materiales`; empareja las de documentos distintos con ≥N palabras técnicas en común (`Glosario::canonica` lleva inglés→español) y cifra distinta en la misma magnitud; la IA juzga cada candidata (lotes de 5, máx. 20). Al aprobar, cada confirmada pasa a RFI (`AplicarInforme::contradicciones`, máx. 10). `Contradicciones::magnitudes/claves/difieren` son puras y están probadas.
- Las tres se descubren solas en el menú de la app (`GET /api/ia/herramientas`).

## Pruebas
`php tests/run.php` (piezas sin BD: puntuación de la IA, cobertura de traducción, predicción y magnitudes). Con copia de BD (AGENTS.md, punto 8) se probó el recorrido completo: token → lectura → escritura → informe → RFI creado. El bus de eventos (`Webhooks::emitir()` → Telegram) está probado en `tests/integracion.php`.
