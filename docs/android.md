# App Android (Tauri + Rust)

Estado: **Fase 1 completa** (sin push con la app cerrada). La app se conecta con `/api/app`, descarga las
obras, guarda el contexto en SQLite, permite actualizar avance y notas sin conexión y
sincroniza al volver. El asistente ya ejecuta **Qwen2.5 GGUF con llama.cpp en el propio
teléfono** (sin nube) y el historial se consulta en la web. Los avisos son locales y se
muestran al abrir o sincronizar; no se usa FCM/Google. Falta el resto de la Fase 2/3.

APK 0.2.10 release firmado (arm64, ~28 MB con llama.cpp y SQLCipher) y publicado desde Ajustes → App Android.
El GGUF se descarga aparte (1.0–2.1 GB según el equipo), no va dentro del APK. La cabecera y el asistente usan el logo de Project Web (casco); el icono de Ajustes es un engrane y no hay mascota de chatbot.

## Decisiones cerradas

| Tema | Decisión |
|---|---|
| Distribución | APK directo desde Ajustes; no se publica en Google Play |
| Quién la usa | Admin y cliente (visitante/colaborador quedan fuera de la v1) |
| UI | Tauri v2 (Rust + WebView) para reutilizar el diseño web y cambiar rápido |
| IA | Local en el teléfono con `llama.cpp`; sin nube |
| Modelo | Automático según RAM; Qwen2.5 3B (≥12 GB) / 1.5B (5–11 GB) / 0.5B, descarga por Wi-Fi |
| Sin conexión | Datos y archivos que el usuario abra/descargue; crear/editar y sincronizar |
| Conflictos | Campo se suma; avance último cambio con historial; estructura solo con conexión |
| Chat | En la app; historial de solo lectura en la web; admin ve todas las conversaciones |
| Retención | Conversaciones 6 meses desde su creación; borradores 30 días |
| Push | Avisos locales al abrir/sincronizar; sin FCM ni servicios de Google. Si se requiere push con la app cerrada, se evaluará otro proveedor |
| Firma | `/home/edgar/Documentos/Llaves apk/projectweb-android-llave/` |

## Estructura

```
android/
├── src/                       # UI WebView: index.html, styles.css, app.js, assets/projectweb.svg
├── src-tauri/                 # Rust + configuración Tauri
│   ├── src/                   # main.rs / lib.rs
│   ├── Cargo.toml
│   ├── tauri.conf.json        # identifier com.projectweb.android, minSdk 26
│   ├── icons/                 # casco naranja (icono de la app)
│   └── gen/android/           # proyecto Gradle generado por Tauri
├── package.json
└── README.md
```

- App ID: `com.projectweb.android`; debug: `com.projectweb.android.debug`.
- `minSdkVersion = 26` (Android 8), `targetSdk = 37`, ABI principal `arm64-v8a`.
- El icono de la app y el asistente usan el **casco de Project Web**; no hay mascota de chatbot.



## API de la app

Todas las rutas viven bajo `/api/app` y usan `Authorization: Bearer pwa_…` o
`X-App-Token: pwa_…` (excepto login y OPTIONS). Responden JSON; CORS abierto porque no usan
cookies. Apache debe exponer `Authorization` a PHP (`SetEnvIf` en `docker/apache.conf`).

| Método | Ruta | Qué hace |
|---|---|---|
| POST | `/api/app/login` | Correo/contraseña y datos del dispositivo; devuelve token |
| GET | `/api/app/me` | Usuario y sesión actual |
| POST | `/api/app/logout` | Revoca el token del teléfono |
| GET | `/api/app/bootstrap` | Proyectos, servidor, APK y modelos |
| GET | `/api/app/sync` | Contexto de una obra o de todas las visibles |
| POST | `/api/app/sync` | Aplica operaciones offline idempotentes (avance, bitácora, **punch/rfi crear y estado**, **parte diario**, cierre y **evidencias**) |
| POST | `/api/app/enlace` | URL de un solo uso para abrir una herramienta web con la sesión del usuario |
| GET | `/api/app/archivo?id=` | Descarga un documento con el token (también acepta `?token=` para el visor del sistema) |
| GET | `/api/app/sesiones` | Dispositivos con sesión |
| POST | `/api/app/sesiones/cerrar` | Cierra una sesión |
| GET | `/api/app/chat` | Historial de la conversación de una obra |
| POST | `/api/app/chat/nueva` | Crea una conversación nueva y devuelve su id |
| POST | `/api/app/chat/mensaje` | Guarda un mensaje del chat |
| GET/POST | `/api/app/chat/borradores` | Lista/crea borradores |
| POST | `/api/app/chat/borrador/aplicar\|rechazar` | Revisa un borrador |
| GET | `/api/app/modelos` | Catálogo de modelos (vacío hasta publicar el GGUF) |

## Fase 1 — lo que ya está hecho

- **Login de app:** `POST /api/app/login` con correo/contraseña y token `pwa_…`; se guarda
  solo su SHA-256 en `sesiones_usuario`. El token dura 90 días y se puede revocar.
- **Bootstrap:** `GET /api/app/bootstrap` devuelve usuario, organización, proyectos, APK
  publicada y catálogo de modelos (vacío hasta publicar un GGUF).
- **Sincronización:** `GET /api/app/sync` entrega actividades, punch, RFI, materiales con
  verificación/normativa, equipo, contratos, bitácora, seguridad, riesgos, recordatorios,
  partes recientes, calendario y salud por cada obra visible. El cliente solo recibe sus
  obras; el admin, todas.
- **Puente de sesión app → web:** `POST /api/app/enlace` crea un código de un solo uso
  (120 s, guardado como SHA-256 en `app_enlaces`); el navegador abre
  `/acceso-app?c=…`, inicia la sesión del mismo usuario y redirige a la herramienta con
  `?proyecto=ID`. Así no se vuelve a pedir la contraseña al abrir Programa, Costos, etc.
- **Punch y RFI nativos:** crear pendientes y consultas, y cambiar su estado, sin conexión;
  se encolan como `punch_crear`, `punch_estado`, `rfi_crear` y `rfi_estado`.
- **Escritura offline:** `POST /api/app/sync` aplica avance de actividad, bitácora, punch y
  RFI con idempotencia por UUID (`app_operaciones`). Un avance con `base_progreso` distinto
  devuelve `conflicto` para que la app avise.
- **SQLite local cifrado:** comandos Rust `cache_guardar/leer/borrar/limpiar` sobre
  `projectweb.db` con **SQLCipher** (AES-256). La llave se genera en el teléfono y vive en
  el **Android Keystore** (`tauri-plugin-secure-keystore`); si el Keystore no está
  disponible se usa un archivo privado 0600 como respaldo. La primera vez migra el caché
  plano existente sin perder datos. Ajustes → **Seguridad de los datos** muestra el estado.
  Guarda usuario, proyectos, sync, outbox y chats por obra; el token también está en el
  Keystore.
- **Cola de salida:** la app guarda avance y notas en el teléfono aunque no haya señal y
  los envía al reconectar; muestra “N pendientes de enviar”.
- **Chat local:** modelo Qwen2.5 GGUF en el teléfono con respaldo de reglas deterministas
  (estatus, punch, atrasos, seguridad, materiales/normativa, recordatorios y qué fechas
  revisar). El historial se sube a `/api/app/chat` y se puede leer en `/asistente`.
- **Historial web y borradores:** tablas `asistente_conversaciones`, `asistente_mensajes` y
  `asistente_borradores`; la web `/asistente` es de solo lectura (el chat se hace en la app),
  muestra el historial y permite aplicar/rechazar borradores de punch, RFI y bitácora.
  Conversaciones: 6 meses desde su creación; borradores: 30 días.
- **Avisos locales:** `tauri-plugin-notification`; la app avisa de recordatorios críticos
  nuevos al sincronizar y hay un botón “Probar aviso”.
- **Sesiones desde la app:** lista y cierra dispositivos con `/api/app/sesiones`.
- **Actualización:** la app compara `servidor.apk.versionCode` con su versión y muestra un
  aviso para bajar la nueva APK desde la web.

### Modelo local de IA (llama.cpp)

- **Motor:** `llama-cpp-2` compilado para `aarch64-linux-android` dentro del APK; el
  `.so` arrastra `libc++_shared.so`. Carga el GGUF con `mmap` y genera en un hilo aparte
  para no bloquear la UI.
- **Elección automática por RAM** (`modelo_actual()`): **3B** en equipos de 12 GB o más,
  **1.5B** de 5 a 11 GB y **0.5B** por debajo. El usuario nunca elige modelo.
  En la tablet de 8 GB el 3B entraba en swap y generaba a 0.4 tok/s; el 1.5B responde a
  ~10–11 tok/s con el mismo contexto, por eso el umbral del 3B subió a 12 GB.
- **Descarga:** directa del GGUF oficial de Qwen en Hugging Face, por Wi-Fi, con reanudación
  por `Range` y validación de tamaño + cabecera `GGUF`. Se guarda en
  `<datos privados>/files/modelos/`. Al cambiar de gama se borran los modelos anteriores.
- **Comandos Rust:** `motor_estado`, `motor_descargar`, `motor_cancelar_descarga`,
  `motor_detener`, `motor_eliminar` y `motor_responder`. Los eventos `motor-progreso`,
  `motor-token`, `motor-fin`, `motor-error` y `motor-cargando` alimentan la UI.
- **Contexto:** la app arma un resumen compacto e intencional (obra, salud, actividades
  atrasadas/próximas, punch, RFI, recordatorios, materiales con normativa, seguridad,
  equipo, riesgos y portafolio) y una **respuesta base verificada** con las reglas. El
  modelo la reformula; si contradice un dato (por ejemplo, inventa punch cuando no hay) se
  descarta y se muestra la respuesta base con la etiqueta «Datos verificados».
- **Extras:** dictado por voz con `SpeechRecognition` del WebView (si el teléfono lo soporta) y glosario de obra (RFI, Punch, Lookahead, PPC, SPI, CPI, ATS, NC, ITP).
- **UI:** el asistente se muestra siempre como **«Asistente digital»**; el nombre del modelo y su estado solo aparecen en el menú de administración (Ajustes → Modelo de IA y menú del asistente), nunca para el cliente. Tarjeta de preparación/progreso en el chat, modal de modelo (RAM, hilos, tamaño,
  estado, descarga/cancelación/eliminación), botón de detener respuesta y menú del
  asistente con «Nuevo chat».
- **Privacidad:** la inferencia no sale del teléfono; solo se suben los mensajes al
  historial web (`asistente_mensajes`). El modelo se ejecuta con `n_ctx = 4096` y 4 hilos
  en equipos de 6 GB o más.
- **Pruebas en Xiaomi Pad 6 (Android 13, 8 GB):** descarga del 1.5B por Wi-Fi en ~1 min;
  primera respuesta ~24–38 s (carga + contexto); siguientes a **10–11 tok/s**. El 3B daba
  **0.4 tok/s** por swap. Se validó respuesta con el servidor PHP apagado (datos cacheados
  + modelo local), detener respuesta, «Nuevo chat», historial en `/asistente` y corrección
  de una alucinación de punch con la respuesta base.

### Parte diario nativo (campo)

- **Pantalla `parte`:** se abre desde Inicio («Parte diario»), desde el hub de Obras o desde la tarjeta de la obra. Incluye fecha, turno, área, supervisor, visitas, comentarios, clima, actividades con avance, personal con horas normal/extra, equipo con horas y operador, materiales (consumido/recibido/retirado), fotos, charla de seguridad/EPP y **firma con el dedo**.
- **Sin conexión:** el parte se guarda en la cola local (`parte_diario`) con un UUID idempotente y se envía al sincronizar. Los catálogos (personal, turnos, causas y tipos de material) viajan en el snapshot para poder llenar el formulario sin señal. Incluye **tiempos muertos** (horas, causa, actividad y nota) y una lista de **partes recientes** con su detalle, descargada en el snapshot.
- **Servidor:** `AppApi::opParteDiario()` llama a `ParteDiario::guardar()`; las fotos llegan como data URL base64 (máx. 5 por parte) y se procesan con `ParteDiario::adjuntarFotosBase64()`. El parte se puede guardar como **borrador** o **guardar y cerrar** desde la app; al cerrarlo se agrega el resumen a la bitácora. La web sigue disponible para revisarlo o reabrirlo.

### Archivo nativo y visor (bajo demanda)

- El hub de Obras abre **Archivo** con los documentos del proyecto que vienen en el snapshot (`archivos`, hasta 300).
- Al tocar **Abrir**, el comando Rust `descargar_archivo` baja el documento con el token a `files/archivos/`; si ya existe, lo reutiliza (funciona sin conexión).
- **PDF** se renderiza dentro de la app con `pdf.js` (páginas continuas, zoom y carga diferida); las **imágenes** y el **texto/CSV** se muestran en el visor nativo. Los documentos de oficina (DOCX, XLSX…) se abren con la app externa o el navegador.
- El comando `leer_archivo` entrega los bytes al WebView y solo acepta rutas dentro de `files/archivos`.
- El endpoint `GET /api/app/archivo?id=...` valida el proyecto y el token; PDF e imágenes se sirven inline.
- No se descarga todo el Archivo automáticamente: se baja lo que el usuario abre. Para planos grandes se recomienda Wi-Fi. Al cerrar sesión se borran los documentos descargados (`limpiar_archivos`); el modelo de IA se conserva.

### Evidencias nativas

- Desde el hub de Obras, **Evidencias** abre una pantalla para elegir actividad, fecha, departamento, categoría, descripción y hasta 5 fotos.
- Se guardan en la cola offline con `evidencia_crear` y el servidor las procesa con `Evidencia`/`Imagenes`; aparecen en `/evidencias` de la web.
- Es independiente de las fotos del parte diario; sirve para evidencias sueltas por actividad.
- **Límites actuales:** los incidentes de seguridad detallados y la edición de un parte ya enviado siguen en el formulario web. La firma, las fotos, los tiempos muertos y el historial sí están en la app.

### Extras del asistente

- **Búsqueda en la obra:** «busca fuga» rastrea actividades, bitácora, punch, RFI, materiales, archivo, riesgos, equipo y seguridad.
- **Simulación:** «¿qué pasa si atraso SUB.0.2 5 días?» calcula el nuevo fin con el calendario del proyecto, las actividades posteriores de la partida y el posible impacto en el fin de obra. No cambia nada.
- **Minuta del día:** resume avance, punch, RFI, atrasos y recordatorios, y se puede guardar en la bitácora.
- **Borradores:** desde el menú de acciones se abre un punch o RFI con la última pregunta como base.
- **Lectura y copia:** cada respuesta tiene botones **Escuchar** (TTS del sistema) y **Copiar**.
- **Dictado:** el micrófono usa `SpeechRecognition` del WebView cuando el teléfono lo soporta.

### Pendiente de la Fase 1

- Push con la app cerrada: **no incluido** por decisión (sin Google/FCM); los avisos se muestran al abrir o sincronizar.
- Incidentes de seguridad detallados y edición de partes ya cerrados dentro de la app.
- Las herramientas que no son de campo (Programa/Gantt, Costos, Materiales, Calidad) siguen abriéndose en la web mediante el puente de sesión.

## Fase 0 — lo que ya está hecho

### 1. Distribución del APK

- **Admin:** Sistema → App Android. Sube `projectweb-android-<versión>.apk`, ve la lista,
  descarga, reemplaza o elimina. Solo el admin publica.
- **Admin y clientes:** Ajustes → App Android. Ven la última versión, tamaño, fecha y notas;
  descargan el APK.
- El archivo se guarda en `storage/android/` (fuera del docroot, ignorado por Git). El
  nombre debe ser `projectweb-android-<versión>.apk`; `versionCode` se calcula de la
  versión (`1.2.3` → 1002003, igual que Tauri/Android).
- `GET /api/android/version` devuelve versión, `versionCode`, SHA-256, tamaño, notas y URL.
- `GET /api/android/descargar` entrega el APK con `Content-Type` correcto y soporte de
  `Range` para reanudar descargas.
- El APK se revisa como ZIP: debe traer `AndroidManifest.xml` y `classes.dex`; se comprueban
  rutas, tamaño y bombas de compresión antes de publicarlo.

### 2. Sesiones y dispositivos

- Tabla `sesiones_usuario` (`database/migracion_sesiones_dispositivos.sql`): token hasheado
  SHA-256, tipo web/android, dispositivo, navegador, sistema, IP, último acceso, expiración
  y revocación.
- **Cada usuario** ve las suyas en `/cuenta/sesiones` y desde Ajustes → Cuenta. Puede cerrar
  una o todas las demás. La sesión actual se marca y no se cierra desde la lista.
- **Admin** ve todas en Sistema → Sesiones, con búsqueda por nombre/correo/IP/dispositivo y
  cierre individual.
- Si se cierra una sesión, el navegador se entera en la siguiente petición y vuelve al login.
- Cambiar la contraseña cierra las demás sesiones.
- Sesiones revocadas/caducadas se purgan a los 90 días.
- El registro de intentos (`accesos`) sigue siendo independiente.

### 3. Logo de Project Web

- `public/build/img/projectweb.svg` en la web y `android/src/assets/projectweb.svg` en la app:
  el casco naranja de Project Web.
- Se usa en la cabecera, la tarjeta y el avatar del asistente, los mensajes, la pestaña
  «Asistente», el login y los avisos locales. No hay mascota de chatbot ni carpeta
  `public/build/img/chatbot/`.

### 4. Shell de la app

`android/src/` incluye una pantalla móvil con:
- Inicio con la obra, avance, KPIs, pendientes de hoy y tarjeta del asistente.
- Obras reales, alertas/recordatorios, ajustes, conexión y chat con datos sincronizados.
- **Obras** lista las obras visibles, permite buscar y cambiar la obra activa y muestra avance/atrasos. Al tocar una obra se abre un **hub** con acceso a Programa, Línea de tiempo, Lookahead, Parte diario, Bitácora, Punch, RFI, Archivo, Costos, Materiales, Seguridad y Calidad; las herramientas se abren en la web con `tauri-plugin-opener` (requieren conexión). La app todavía no replica esas herramientas de forma nativa.
- Actualización de avance y notas rápidas que funcionan sin conexión (cola de salida).
- Tema claro/oscuro, navegación inferior, estado de conexión y diseño responsive para móvil
  y tablet (sin desbordes horizontales).

## Pendiente por fase

### Lo que sigue en la app Android

- **Edición de partes ya cerrados** e **incidentes de seguridad detallados** (hoy se editan en la web).
- **Sincronización nativa del resto de herramientas**: avance, bitácora, punch, RFI, parte diario, evidencias y archivo ya son nativos; Programa/Gantt, Costos, Materiales, Calidad y el resto se abren en la web con el puente de sesión.
- **Motor de fechas/ruta crítica en Rust** para propuestas 100 % offline (la simulación «¿qué pasa si…?» ya funciona con el snapshot, pero no recalcula toda la ruta crítica en el teléfono).
- **Whisper local** para dictado (hoy se usa el reconocimiento del WebView) y ajuste de latencia/modelo (3B solo en equipos de 12 GB o más).
- **Vistas nativas de Costos/Materiales/Calidad** si el producto las necesita; hoy son web.
- **Push con la app cerrada:** omitido por decisión (sin Google/FCM); los avisos se ven al abrir o sincronizar.

## Compilar

Requisitos: Rust, Android SDK/NDK, Java 17 y `npx`.

```bash
cd android
npm install                 # una vez
npx tauri android init      # ya ejecutado; regenera gen/android si hace falta
./compilar-apk.sh --debug --ci                           # APK de prueba
./compilar-apk.sh --ci                                   # APK release firmado
```

`compilar-apk.sh` enlaza `aarch64-linux-android-{ar,ranlib,…}` a las herramientas
`llvm-*` del NDK: OpenSSL (que usa SQLCipher) las busca con ese prefijo y el NDK moderno
solo trae `llvm-*`. Sin ese paso el build falla en `make install_dev`.

Salida típica:
`android/src-tauri/gen/android/app/build/outputs/apk/...`

El **release** usa `https://projectweb.iguzman.com.mx` y `usesCleartextTraffic=false`; el
**debug** (`.debug`, cleartext permitido) sirve para probar contra el servidor local. Para
publicar: copiar el APK a `storage/android/projectweb-android-<versión>.apk` y escribir su
`.apk.json` con `versionName`, `versionCode`, `size`, `sha256`, `minSdk`, `notas` y `fecha`
(lo que lee `Classes\ApkAndroid::listar()`).

### Firma

- Keystore y contraseñas: `/home/edgar/Documentos/Llaves apk/projectweb-android-llave/`.
- `android/src-tauri/gen/android/keystore.properties` apunta al keystore; está ignorado por
  Git y no debe subirse.
- Sin esa llave no se pueden instalar actualizaciones encima; hay que respaldarla.
- El bloque `signingConfigs` está en `gen/android/app/build.gradle.kts` y el `roundIcon` en
  `AndroidManifest.xml`. Si vuelves a ejecutar `tauri android init`, revisa que no se hayan
  perdido; el APK de release debe quedar firmado con el mismo certificado.

## Servidor

- **Desplegado:** 2026-11-20 (Helm revisión 103). `POST /api/app/login` responde 422/401 correctamente en producción y el APK 0.2.9 está en `storage/android` del clúster, montado ahora en el PVC (`storage/android`) para que sobreviva a los despliegues.
- **Orden de despliegue:** primero el backend (`public/index.php` con el despacho de
  `/api/app` antes de `csrf_verificar()`, `classes/AppApi.php`, `classes/Asistente.php` y las
  migraciones) y después el APK. Si el backend viejo recibe un POST JSON sin `_csrf`,
  responde «El archivo es demasiado grande…» y el login de la app falla.
- **Cabecera Authorization:** Apache no exponía `Authorization: Bearer` a PHP en producción
  (la app respondía `sin_token`). `docker/apache.conf` ahora tiene `CGIPassAuth On` y
  `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`; además la app manda `X-App-Token`
  como respaldo y el comando Rust lo usa al descargar archivos.
- Clases nuevas: `classes/ApkAndroid.php`, `classes/Sesiones.php`.
- Controladores: `controllers/AndroidController.php`, `controllers/SesionController.php`.
- Rutas: `/api/android/version`, `/api/android/descargar`, `/sistema/android/*`,
  `/cuenta/sesiones`, `/sistema/sesiones/cerrar`.
- Migración: `database/migracion_sesiones_dispositivos.sql` (también en `schema.sql`).
- APK: `storage/android/` (ignorado por Git; el volumen del clúster lo conserva).

## Pruebas

```bash
php tests/run.php
DB_HOST=127.0.0.1 DB_USER=root DB_PASS= DB_NAME=project_claude_1 php tests/integracion.php
cd android && npx tauri android build --debug --apk --target aarch64
```

Las pruebas cubren el nombre/versión del APK, el escáner de APK, las etiquetas de
dispositivo, la tabla de sesiones, el cierre de sesiones, el historial/borradores del
asistente y la integridad del esquema. La app se valida además con `tauri android build`.

## Notas de seguridad

- El token de sesión nunca se guarda en claro: solo su SHA-256.
- El APK se sirve solo con sesión iniciada; no hay listado público de `storage/android`.
- La app usará tokens de dispositivo revocables; al cerrar una sesión desde otro equipo, el
  teléfono pierde acceso en el siguiente sync y borra sus datos locales.
- El modelo local no recibe acceso libre a la base: la app ejecuta habilidades limitadas y
  pide confirmación antes de crear o editar.
