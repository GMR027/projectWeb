# Telegram y clima automático

Un solo bot para toda la plataforma. Migración: `database/migracion_telegram.sql` (tablas `telegram_vinculos`, `telegram_codigos`, `telegram_destinos`, `telegram_estado`; columnas `proyectos.clima_auto|clima_ultimo|clima_aviso`; también en `schema.sql` y `aplicar_migraciones_cluster.sh`). UI: sección «Telegram» y «Clima automático» de `/integraciones`. Manual: sección `telegram`.

## Configuración (una vez, el administrador)
1. `@BotFather` → `/newbot` → token. El admin lo pega en **Integraciones → Telegram** y se guarda **cifrado** en la tabla `telegram_config` (AES-256-GCM; clave en `TELEGRAM_KEY` o en `storage/secretos/telegram.key`, 0600 y fuera del repo). En pantalla solo se ve la máscara (últimos 4). **Respaldos:** si no hay fila, se usa `TELEGRAM_BOT_TOKEN` del `.env`/entorno (igual que antes) y `TELEGRAM_BOT_USERNAME` es opcional: si falta, se consulta con `getMe` y se guarda en `storage/telegram_bot.json`. **Rotar el token** = pegarlo otra vez ahí; el botón *Guardar y activar* además re-registra el webhook (el secreto se deriva del token).
2. Servidor público con https: botón «Activar el bot (registrar webhook)» en `/integraciones` o `php bin/telegram.php --webhook` (usa `APP_URL`). Equipo local: `php bin/telegram.php --sondeo` (quita el webhook y pregunta a Telegram con `getUpdates`). `php bin/telegram.php --info` muestra el estado.
3. Clima: `bin/clima_programado.php` en cron a las 18:00 (`CLIMA_AUTO_HORA`); sin cron lo dispara la primera petición de alguien con sesión pasada la hora (`public/index.php`).

## Piezas
| Archivo | Qué hace |
|---|---|
| `classes/Telegram.php` | API (`api`, `enviar`, `descargar`), permisos sin sesión (`puedeVer/puedeGestionar`, mismos que la web; visitante nunca), vinculación por códigos de 15 min (`crearCodigo`, `enlace`), destinos de avisos, avisos (`aviso` en cola → `vaciar` al terminar la petición), `mensajeEvento`, `atender` (webhook), `registrarWebhook` |
| `classes/TelegramBot.php` | Conversación: `/start CODIGO`, `/avance`, `/hoy`, fotos, `/nota`, `/clima`, `/proyecto`, `/cancelar`; botones (`callback_data`: `pr:`, `a:`, `p:id:n`, `f:`); estado por chat en `telegram_estado` |
| `classes/ClimaAuto.php` | Anota el clima del día en la bitácora (departamento «Clima», una vez al día) y avisa (`clima.alerta`) si mañana es adverso, con las actividades de exterior de ese día. Usa `Clima` (Open-Meteo) |
| `bin/telegram.php`, `bin/clima_programado.php` | Sondeo/registro del bot; corrida del clima |

## Reglas que conviene no romper
- **Webhook**: `Telegram::esWebhook()` se atiende en `public/index.php` antes de CSRF y sesión. El secreto es `hash_hmac(token)` (cabecera `X-Telegram-Bot-Api-Secret-Token`); sin él, 403. Con secreto correcto siempre 200 (si no Telegram reintenta sin parar). Cada update se atiende una vez (`telegram_estado.ultimo_update`).
- **Permisos**: ver = admin, dueño o acceso asignado; escribir (avance, fotos, notas) = admin o cliente dueño, igual que `puede_gestionar_proyecto`. Los grupos solo reciben avisos: no aceptan órdenes (solo `/start CODIGO`).
- **Avisos**: `Webhooks::emitir()` llama también a `Telegram::aviso()`; eventos en `Telegram::EVENTOS` (los iniciales en `INICIALES`). Un destino privado se descarta si la persona perdió el acceso. 5 fallos seguidos pausan el destino; expulsar al bot de un grupo lo borra.
- **Hora**: la vigencia de la conversación la mide MySQL (`NOW()`), no PHP (zonas horarias distintas).
- **Avance/fotos** reutilizan `Actividad::sincronizarStatus()/guardar()`, `Almacenamiento::cabe()` e `Imagenes::guardarEn()` (las fotos van a `evidencias`, `descripcion` = pie de foto).
- Textos del bot en español fijo (no pasan por `t()`).

## Pruebas
`php tests/run.php` (comando, escape, mensajes, resumen del clima) y `tests/integracion.php` (BD real, API de Telegram simulada con `Telegram::$http` y `Clima::$http`): vinculación, avance por botones/número, fotos, notas, permisos de cliente/visitante, grupos, avisos, expulsión, clima sin duplicados. Para correrlo en local usa una BD propia: `DB_NAME=project_claude_<n> php tests/integracion.php` (la borra y recrea).
