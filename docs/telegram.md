# Telegram y clima automático

Un solo bot para toda la plataforma. Migración: `database/migracion_telegram.sql` (tablas `telegram_vinculos`, `telegram_codigos`, `telegram_destinos`, `telegram_estado`; columnas `proyectos.clima_auto|clima_ultimo|clima_aviso|resumen_matutino|resumen_ultimo`; también en `schema.sql` y `aplicar_migraciones_cluster.sh`). UI: sección «Telegram», «Clima automático» y «Resumen de la obra» de `/integraciones`. Manual: sección `telegram`.

## Configuración (una vez, el administrador)
1. `@BotFather` → `/newbot` → token. El admin lo pega en **Integraciones → Telegram** y se guarda **cifrado** en la tabla `telegram_config` (AES-256-GCM; clave en `TELEGRAM_KEY` o en `storage/secretos/telegram.key`, 0600 y fuera del repo; **en el clúster ese archivo lo monta el Secret `projectweb-telegram`, nunca el PVC de usuarios**). En pantalla solo se ve la máscara (últimos 4). **Respaldos:** si no hay fila, se usa `TELEGRAM_BOT_TOKEN` del `.env`/entorno (igual que antes) y `TELEGRAM_BOT_USERNAME` es opcional: si falta, se consulta con `getMe` y se guarda en `storage/telegram_bot.json`. **Rotar el token** = pegarlo otra vez ahí; el botón *Guardar y activar* además re-registra el webhook (el secreto se deriva del token) y el menú «/» (`setMyCommands` con `Telegram::COMANDOS`).
2. Servidor público con https: botón «Activar el bot (registrar webhook)» en `/integraciones` o `php bin/telegram.php --webhook` (usa `APP_URL`). Equipo local: `php bin/telegram.php --sondeo` (quita el webhook y pregunta a Telegram con `getUpdates`). `php bin/telegram.php --info` muestra el estado.
3. Clima: `bin/clima_programado.php` en cron a las 18:00 (`CLIMA_AUTO_HORA`); sin cron lo dispara la primera visita de un **admin** pasada la hora (`public/index.php`). En el clúster los CronJobs `telegram-clima` y `telegram-resumen` (`helm/projectweb/templates/cronjobs-telegram.yaml`) lo hacen solos, a la hora de `values.yaml` (`app.climaHora`, `app.resumenHora`).
4. Resumen de la obra: activarlo en Integraciones → «Resumen de la obra» (columna `proyectos.resumen_matutino`, **apagado por defecto**) y marcar la casilla «Resumen de la obra cada mañana» en los avisos de cada destino (evento `resumen.matutino`, opt-in). Cron `bin/resumen_programado.php` a las 6:00 (`RESUMEN_HORA`); sin cron, el mismo disparo diferido de `public/index.php` (solo admin).

## Piezas
| Archivo | Qué hace |
|---|---|
| `classes/Telegram.php` | API (`api`, `enviar`, `enviarArchivo` (sendDocument), `descargar`), permisos sin sesión (`puedeVer/puedeGestionar`, mismos que la web; visitante nunca), vinculación por códigos de 15 min (`crearCodigo`, `enlace`), destinos de avisos, avisos (`aviso` en cola → `vaciar` al terminar la petición) con botones (`botonesEvento`: privado = acciones, grupo = solo «Ver»; `urlApp` para la URL), `mensajeEvento`, `registrarComandos` (menú «/»), `atender` (webhook), `registrarWebhook` |
| `classes/TelegramBot.php` | Conversación: `/start CODIGO`, `/avance`, `/hoy`, fotos, `/nota`, `/clima`, **`/salud`** (semáforo de `Salud`), **`/pdf`** (programa o punch vía `sendDocument`), `/proyecto`, `/cancelar`; botones (`callback_data`: `pr:`, `a:`, `p:id:n`, `f:`, **`doc:tipo`**, **`pu:id:estado`** (Punch::estado), **`rs:id`** → paso `rfi` y el siguiente texto se guarda con `Rfi::guardar(estado=respondido)`, **`su:id:estatus`** (Submittal + `sincronizarMsr`)); estado por chat en `telegram_estado` |
| `classes/ClimaAuto.php` | Anota el clima del día en la bitácora (departamento «Clima», una vez al día) y avisa (`clima.alerta`) si mañana es adverso, con las actividades de exterior de ese día. Usa `Clima` (Open-Meteo) |
| `classes/ResumenObra.php` | Resumen matutino (`resumen.matutino`): clima guardado de hoy, actividades de hoy y atrasadas, restricciones urgentes, punch grave, RFI y semáforo; vacío si no hay nada relevante; una vez al día por proyecto (`resumen_ultimo`) y solo si algún destino pidió el evento |
| `bin/telegram.php`, `bin/clima_programado.php`, `bin/resumen_programado.php` | Sondeo/registro del bot; corrida del clima; corrida del resumen |

## Reglas que conviene no romper
- **Webhook**: `Telegram::esWebhook()` se atiende en `public/index.php` antes de CSRF y sesión. El secreto es `hash_hmac(token)` (cabecera `X-Telegram-Bot-Api-Secret-Token`); sin él, 403. Con secreto correcto siempre 200 (si no Telegram reintenta sin parar). Cada update se atiende una vez (`telegram_estado.ultimo_update`).
- **Permisos**: ver = admin, dueño o acceso asignado; escribir (avance, fotos, notas **y los botones de punch/RFI/submittal**) = admin o cliente dueño, igual que `puede_gestionar_proyecto`. Los grupos solo reciben avisos: no aceptan órdenes (solo `/start CODIGO`) y sus avisos solo llevan botón URL.
- **Botones de acción**: cada callback revalida el proyecto del registro con `gestionableDe()` antes de tocar datos; los cambios pasan por los mismos métodos de la web (`Punch::estado`, `Rfi::guardar`, `Submittal::sincronizarMsr`).
- **Avisos**: `Webhooks::emitir()` llama también a `Telegram::aviso()`; eventos en `Telegram::EVENTOS` (los iniciales en `INICIALES`; `resumen.matutino` no está a propósito). Un destino privado se descarta si la persona perdió el acceso. 5 fallos seguidos pausan el destino; expulsar al bot de un grupo lo borra.
- **Sesión en CLI**: `bin/telegram.php` arranca la sesión antes del primer `echo` (Auditoria/Salud llaman `usuario_actual()`).
- **Hora**: la vigencia de la conversación la mide MySQL (`NOW()`), no PHP (zonas horarias distintas).
- **Avance/fotos** reutilizan `Actividad::sincronizarStatus()/guardar()`, `Almacenamiento::cabe()` e `Imagenes::guardarEn()` (las fotos van a `evidencias`, `descripcion` = pie de foto).
- Textos del bot en español fijo (no pasan por `t()`).

## Seguridad y operación

- **Clave AES del token (`telegram.key`)**: en el clúster vive en el **Secret `projectweb-telegram`** (llave `telegram.key`), que el chart monta sobre `storage/secretos` **read-only y fuera del PVC de usuarios** — un bug de subida no puede leerla ni reemplazarla. El PVC ya no participa. Bootstrap:
  ```bash
  kubectl -n projectweb create secret generic projectweb-telegram \
    --from-literal=TELEGRAM_BOT_TOKEN=… \
    --from-literal=telegram.key=$(openssl rand -hex 32)
  ```
  **Migración desde antes de este cambio:** la clave vieja estaba en el PVC; sácala antes del upgrade y métela al Secret o el token de la BD queda ilegible (se verá «ilegible» en Integraciones y habrá que volver a pegarlo):
  ```bash
  kubectl -n projectweb exec deploy/projectweb -- cat /var/www/html/storage/secretos/telegram.key
  ```
- **Token en la URL de salida**: la Bot API autentica en el path (`/bot<TOKEN>/…`), así que **cualquier log de egress (proxy, mesh, `-v` de curl) deja el token**. En el código no hay `CURLOPT_VERBOSE` y los `error_log` pasan por `Telegram::sanearError()` (enmascara `bot<TOKEN>`). Si un proxy de salida loggea paths, o el token ya se loggeó, **rótalo** pegándolo otra vez en Integraciones (eso re-registra webhook y menú).
- **Quién ve el token**: solo el rol `admin` de plataforma (`is_admin()` en el controlador y `esAdmin` en la vista). Los clientes (dueños de proyecto) solo tocan `telegram_destinos`/`telegram_vinculos`: destinos, eventos y vinculación. El token es único para la plataforma.
- **Fallback por entorno**: `TELEGRAM_BOT_TOKEN` del entorno es solo **bootstrap** (cuando aún no hay fila en `telegram_config`); el camino normal es la BD cifrada. En el chart viene de `secretKeyRef` (Secret, opcional), no de un literal.
- **Límite en el webhook**: `Telegram::limite()` da 120 peticiones/min por IP (`TELEGRAM_RATE_MAX`; `0` lo desactiva) en la tabla `telegram_limites` (migración `migracion_telegram_limites.sql`). Al pasarse, el webhook responde **200 sin procesar** (no se provocan reintentos de Telegram); cuerpos > 1 MB → 413. Se aplica **antes** de validar el secreto, pero la cuota es por IP: un intruso solo quema la suya, nunca la de Telegram (que entra desde otras IPs). `ip_cliente()` respeta `TRUSTED_PROXIES`.
- **Deduplicación de reintentos**: si Telegram reintenta un update, `TelegramBot::nuevo()` lo descarta por `telegram_estado.ultimo_update` (se marca *antes* de procesar). El secret del webhook es `hash_hmac(token)` comparado con `hash_equals()`.
- **Cron vs disparo perezoso**: en clúster manda el CronJob (hora exacta, sin sesión). El trigger de `public/index.php` es **fallback** (equipo local / clúster sin CronJob) y exige `is_admin()`; el trabajo ocurre en `register_shutdown_function` y, en Apache/mod_php, **sí espera al usuario** — por eso el cron es lo recomendado. Las marcas JSON de `storage/` se pierden al recrear el Pod, pero la verdad está en MySQL (`clima_ultimo`, `resumen_ultimo`), que evita duplicados.
- **Backlog (sin implementar)**: NetworkPolicy (app↔mysql:3306, egress a `api.telegram.org:443`), TLS a MySQL, secret de webhook aleatorio guardado en BD (hoy derivado del token), token de respaldo por file-mount en vez de env, límite de intentos fallidos de `telegram_codigos` (hoy: 10 chars de alfabeto de 32 ≈ 50 bits, un solo uso, 15 min — no es fuerza-bruteable práctica).

## Pruebas
`php tests/run.php` (comando, escape, mensajes, botones de acción, hora del resumen, clima) y `tests/integracion.php` (BD real, API de Telegram simulada con `Telegram::$http`): vinculación, avance por botones/número, fotos, notas, permisos de cliente/visitante, grupos, avisos, expulsión, `/salud`, `/pdf` (sendDocument), acciones por botón con permisos, resumen matutino sin duplicados, clima sin duplicados, límite por IP del webhook. Para correrlo en local usa una BD propia: `DB_NAME=project_claude_<n> php tests/integracion.php` (la borra y recrea).
