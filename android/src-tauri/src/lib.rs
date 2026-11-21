//! App Android de Project Web.
//!
//! Guarda el caché de sincronización en SQLite y ejecuta el asistente con un modelo
//! GGUF en el propio teléfono mediante llama.cpp (sin enviar datos a ningún servidor).

use llama_cpp_2::context::params::LlamaContextParams;
use llama_cpp_2::context::LlamaContext;
use llama_cpp_2::llama_backend::LlamaBackend;
use llama_cpp_2::llama_batch::LlamaBatch;
use llama_cpp_2::model::params::LlamaModelParams;
use llama_cpp_2::model::{LlamaChatMessage, LlamaChatTemplate, LlamaModel};
use llama_cpp_2::sampling::LlamaSampler;
use rusqlite::{params, Connection};
use serde::{Deserialize, Serialize};
use std::fs::{self, File, OpenOptions};
use std::io::{Read, Write};
use std::num::NonZeroU32;
use std::path::PathBuf;
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::{Arc, Mutex, OnceLock};
use std::time::{Instant, SystemTime, UNIX_EPOCH};
use tauri::{Emitter, Manager};
use tauri_plugin_secure_keystore::{ItemKey, SecureKeystoreExt, SetItemRequest};
use tauri_plugin_notification::NotificationExt;

const CLAVE_ITEM: &str = "db_key";
static CLAVE_DB: OnceLock<String> = OnceLock::new();

/// ¿El archivo es un SQLite sin cifrar? (cabecera estándar)
fn es_sqlite_plano(ruta: &std::path::Path) -> bool {
    let mut cabecera = [0u8; 16];
    match File::open(ruta).and_then(|mut f| f.read_exact(&mut cabecera)) {
        Ok(_) => cabecera == *b"SQLite format 3\0",
        Err(_) => false,
    }
}

fn clave_aleatoria() -> String {
    let mut buf = [0u8; 32];
    if getrandom::getrandom(&mut buf).is_err() {
        // Respaldo improbable: entropía del reloj y del contador de pila.
        let t = SystemTime::now().duration_since(UNIX_EPOCH).unwrap_or_default();
        let bytes = t.as_nanos().to_le_bytes();
        for (i, b) in buf.iter_mut().enumerate() {
            *b = bytes[i % bytes.len()] ^ (i as u8).wrapping_mul(31);
        }
    }
    let mut clave = String::with_capacity(64);
    for b in buf {
        clave.push_str(&format!("{b:02x}"));
    }
    clave
}

fn clave_valida(k: &str) -> bool {
    k.len() == 64 && k.chars().all(|c| c.is_ascii_hexdigit())
}

fn escribir_clave(ruta: &std::path::Path, clave: &str) -> Result<(), String> {
    fs::write(ruta, clave).map_err(|e| format!("No se pudo guardar la llave local: {e}"))?;
    #[cfg(unix)]
    {
        use std::os::unix::fs::PermissionsExt;
        let _ = fs::set_permissions(ruta, fs::Permissions::from_mode(0o600));
    }
    Ok(())
}

/// Llave de cifrado de SQLite: Android Keystore; si el plugin no está disponible,
/// un archivo 0600 dentro de los datos privados de la app.
fn clave_db(app: &tauri::AppHandle, db_cifrada: bool) -> Result<String, String> {
    if let Some(k) = CLAVE_DB.get() {
        return Ok(k.clone());
    }
    let base = app.path().app_data_dir().map_err(|e| e.to_string())?.join("files");
    fs::create_dir_all(&base).map_err(|e| e.to_string())?;
    let archivo = base.join("db.key");

    let mut clave: Option<String> = None;
    let mut keystore_ok = false;
    match app.secure_keystore().get_item(ItemKey { key: CLAVE_ITEM.to_string() }) {
        Ok(r) => {
            keystore_ok = true;
            if let Some(v) = r.value {
                let v = v.trim().to_string();
                if clave_valida(&v) {
                    clave = Some(v);
                }
            }
        }
        Err(e) => log::warn!("Keystore no disponible para la base local: {e}"),
    }
    if clave.is_none() && archivo.exists() {
        if let Ok(t) = fs::read_to_string(&archivo) {
            let t = t.trim().to_string();
            if clave_valida(&t) {
                clave = Some(t);
            }
        }
    }

    let clave = match clave {
        Some(k) => k,
        None => {
            // Si la base ya está cifrada y no hay llave, no se debe generar otra:
            // eso dejaría los datos actuales imposibles de leer.
            if db_cifrada {
                return Err("No se pudo leer la llave de la base local cifrada.".into());
            }
            let k = clave_aleatoria();
            if keystore_ok {
                if let Err(e) = app.secure_keystore().set_item(SetItemRequest {
                    key: CLAVE_ITEM.to_string(),
                    value: k.clone(),
                }) {
                    log::warn!("No se pudo guardar la llave en Keystore: {e}");
                    escribir_clave(&archivo, &k)?;
                }
            } else {
                escribir_clave(&archivo, &k)?;
            }
            k
        }
    };
    let _ = CLAVE_DB.set(clave.clone());
    Ok(clave)
}

/// Convierte una base plana existente a SQLCipher sin perder el caché. Si algo falla,
/// deja la base original como estaba.
fn migrar_db(dir: &std::path::Path, db: &std::path::Path, clave: &str) -> Result<(), String> {
    let plano = dir.join("projectweb-plano.db");
    if plano.exists() {
        let _ = fs::remove_file(&plano);
    }
    fs::rename(db, &plano).map_err(|e| format!("No se pudo preparar el cifrado: {e}"))?;
    let resultado = (|| -> Result<(), String> {
        let con = Connection::open(db).map_err(|e| e.to_string())?;
        con.execute_batch(&format!("PRAGMA key = \"x'{}'\";", clave))
            .map_err(|e| e.to_string())?;
        con.execute_batch(
            "CREATE TABLE IF NOT EXISTS cache (
                clave TEXT PRIMARY KEY,
                valor TEXT NOT NULL,
                actualizado TEXT NOT NULL DEFAULT (datetime('now'))
            );",
        )
        .map_err(|e| e.to_string())?;
        let ruta = plano.to_string_lossy().replace('\'', "''");
        con.execute_batch(&format!("ATTACH DATABASE '{}' AS plano KEY '';", ruta))
            .map_err(|e| e.to_string())?;
        con.execute_batch(
            "INSERT INTO cache (clave, valor, actualizado) SELECT clave, valor, actualizado FROM plano.cache;
             DETACH DATABASE plano;",
        )
        .map_err(|e| e.to_string())?;
        Ok(())
    })();
    match resultado {
        Ok(_) => {
            let _ = fs::remove_file(&plano);
            Ok(())
        }
        Err(e) => {
            let _ = fs::remove_file(db);
            let _ = fs::rename(&plano, db);
            Err(format!("No se pudo cifrar la base local: {e}"))
        }
    }
}

/// Abre (y crea) la base local cifrada de la app. Es el caché de sincronización.
fn abrir_db(app: &tauri::AppHandle) -> Result<Connection, String> {
    let base = app.path().app_data_dir().map_err(|e| e.to_string())?;
    // files/ es la carpeta estándar de datos y sobrevive a las actualizaciones del APK.
    let dir = base.join("files");
    fs::create_dir_all(&dir).map_err(|e| e.to_string())?;
    let nuevo = dir.join("projectweb.db");
    let viejo = base.join("projectweb.db");
    if viejo.exists() && !nuevo.exists() {
        let _ = fs::rename(&viejo, &nuevo);
    }

    let plano = nuevo.exists() && es_sqlite_plano(&nuevo);
    // Una base de 0 bytes cuenta como nueva, no como cifrada.
    let existente = nuevo.exists() && fs::metadata(&nuevo).map(|m| m.len() > 0).unwrap_or(false);
    let clave = clave_db(app, existente && !plano)?;
    if plano {
        migrar_db(&dir, &nuevo, &clave)?;
    }

    let con = Connection::open(&nuevo).map_err(|e| e.to_string())?;
    con.execute_batch(&format!("PRAGMA key = \"x'{}'\";", clave))
        .map_err(|e| e.to_string())?;
    con.query_row("SELECT count(*) FROM sqlite_master", [], |r| r.get::<_, i64>(0))
        .map_err(|_| "No se pudo abrir la base local cifrada. Vuelve a iniciar sesión.".to_string())?;
    con.execute_batch(
        "CREATE TABLE IF NOT EXISTS cache (
            clave TEXT PRIMARY KEY,
            valor TEXT NOT NULL,
            actualizado TEXT NOT NULL DEFAULT (datetime('now'))
        );",
    )
    .map_err(|e| e.to_string())?;
    Ok(con)
}

/// Estado del almacenamiento local para Ajustes (no expone la llave).
#[tauri::command]
async fn db_estado(app: tauri::AppHandle) -> Result<serde_json::Value, String> {
    let dir = app.path().app_data_dir().map_err(|e| e.to_string())?.join("files");
    let db = dir.join("projectweb.db");
    let existe = db.exists();
    let bytes = fs::metadata(&db).map(|m| m.len()).unwrap_or(0);
    let cifrada = existe && bytes > 0 && !es_sqlite_plano(&db);
    Ok(serde_json::json!({
        "existe": existe,
        "cifrada": cifrada,
        "bytes": bytes,
        "llave_en_archivo": dir.join("db.key").exists(),
    }))
}

#[tauri::command]
async fn cache_guardar(app: tauri::AppHandle, clave: String, valor: String) -> Result<(), String> {
    if clave.is_empty() || clave.len() > 120 {
        return Err("Clave de caché no válida".into());
    }
    let con = abrir_db(&app)?;
    con.execute(
        "INSERT INTO cache (clave, valor, actualizado) VALUES (?1, ?2, datetime('now'))
         ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor, actualizado = excluded.actualizado",
        params![clave, valor],
    )
    .map_err(|e| e.to_string())?;
    Ok(())
}

#[tauri::command]
async fn cache_leer(app: tauri::AppHandle, clave: String) -> Result<Option<String>, String> {
    let con = abrir_db(&app)?;
    let mut stmt = con
        .prepare("SELECT valor FROM cache WHERE clave = ?1")
        .map_err(|e| e.to_string())?;
    let mut rows = stmt.query(params![clave]).map_err(|e| e.to_string())?;
    if let Some(row) = rows.next().map_err(|e| e.to_string())? {
        let valor: String = row.get(0).map_err(|e| e.to_string())?;
        return Ok(Some(valor));
    }
    Ok(None)
}

#[tauri::command]
async fn cache_borrar(app: tauri::AppHandle, clave: String) -> Result<(), String> {
    let con = abrir_db(&app)?;
    con.execute("DELETE FROM cache WHERE clave = ?1", params![clave])
        .map_err(|e| e.to_string())?;
    Ok(())
}

#[tauri::command]
async fn cache_limpiar(app: tauri::AppHandle) -> Result<(), String> {
    let con = abrir_db(&app)?;
    con.execute("DELETE FROM cache", []).map_err(|e| e.to_string())?;
    Ok(())
}

#[tauri::command]
fn notificar(app: tauri::AppHandle, titulo: String, cuerpo: String) -> Result<(), String> {
    if titulo.trim().is_empty() {
        return Err("El título es obligatorio".into());
    }
    let _ = app.notification().request_permission();
    app.notification()
        .builder()
        .title(titulo)
        .body(cuerpo)
        .show()
        .map_err(|e| e.to_string())
}

// =====================================================================================
// Motor local de IA (llama.cpp + GGUF)
// =====================================================================================

const CTX_TOKENS: u32 = 4096;
const MAX_SALIDA: u32 = 300;

struct ModeloDef {
    nombre: &'static str,
    archivo: &'static str,
    url: &'static str,
    bytes: u64,
}

const MODELO_3B: ModeloDef = ModeloDef {
    nombre: "Qwen2.5 3B Instruct",
    archivo: "qwen2.5-3b-instruct-q4_k_m.gguf",
    url: "https://huggingface.co/Qwen/Qwen2.5-3B-Instruct-GGUF/resolve/main/qwen2.5-3b-instruct-q4_k_m.gguf",
    bytes: 2_104_932_768,
};

const MODELO_1_5B: ModeloDef = ModeloDef {
    nombre: "Qwen2.5 1.5B Instruct",
    archivo: "qwen2.5-1.5b-instruct-q4_k_m.gguf",
    url: "https://huggingface.co/Qwen/Qwen2.5-1.5B-Instruct-GGUF/resolve/main/qwen2.5-1.5b-instruct-q4_k_m.gguf",
    bytes: 1_117_320_736,
};

const MODELO_0_5B: ModeloDef = ModeloDef {
    nombre: "Qwen2.5 0.5B Instruct",
    archivo: "qwen2.5-0.5b-instruct-q4_k_m.gguf",
    url: "https://huggingface.co/Qwen/Qwen2.5-0.5B-Instruct-GGUF/resolve/main/qwen2.5-0.5b-instruct-q4_k_m.gguf",
    bytes: 491_400_032,
};

/// Memoria total del equipo en MB. En Android basta leer /proc/meminfo.
fn ram_total_mb() -> u64 {
    let Ok(texto) = fs::read_to_string("/proc/meminfo") else {
        return 0;
    };
    for linea in texto.lines() {
        if let Some(resto) = linea.strip_prefix("MemTotal:") {
            let kb = resto
                .trim()
                .trim_end_matches("kB")
                .trim()
                .parse::<u64>()
                .unwrap_or(0);
            return kb / 1024;
        }
    }
    0
}

/// El modelo se elige solo, según la memoria del teléfono. El usuario nunca decide.
/// En 8 GB el 3B no cabe con holgura (Android y las apps dejan poco libre) y se cambia a swap:
/// por eso el 3B queda para equipos de 12 GB o más y el 1.5B para el resto.
fn modelo_actual() -> &'static ModeloDef {
    let ram = ram_total_mb();
    if ram >= 11_000 {
        &MODELO_3B
    } else if ram >= 5_000 {
        &MODELO_1_5B
    } else {
        &MODELO_0_5B
    }
}

fn hilos_para(ram_mb: u64) -> i32 {
    if ram_mb >= 6_000 {
        4
    } else if ram_mb >= 3_500 {
        3
    } else {
        2
    }
}

fn dir_modelos(app: &tauri::AppHandle) -> Result<PathBuf, String> {
    let base = app.path().app_data_dir().map_err(|e| e.to_string())?;
    // Los modelos viven en files/modelos (la carpeta estándar de datos de Android).
    let dir = base.join("files").join("modelos");
    fs::create_dir_all(&dir).map_err(|e| e.to_string())?;
    // Migración desde la versión que guardaba en la raíz del directorio de datos.
    let viejo = base.join("modelos");
    if viejo.is_dir() {
        if let Ok(entradas) = fs::read_dir(&viejo) {
            for entrada in entradas.flatten() {
                let nombre = entrada.file_name();
                let destino = dir.join(&nombre);
                if !destino.exists() {
                    let _ = fs::rename(entrada.path(), &destino);
                }
            }
        }
        let _ = fs::remove_dir(&viejo);
    }
    Ok(dir)
}

fn ruta_modelo(app: &tauri::AppHandle) -> Result<PathBuf, String> {
    Ok(dir_modelos(app)?.join(modelo_actual().archivo))
}

fn ruta_parcial(app: &tauri::AppHandle) -> Result<PathBuf, String> {
    Ok(dir_modelos(app)?.join(format!("{}.part", modelo_actual().archivo)))
}

/// Borra modelos de otra gama (por ejemplo, el 3B de una instalación anterior) antes de descargar.
fn limpiar_otros_modelos(app: &tauri::AppHandle, def: &ModeloDef) {
    let Ok(dir) = dir_modelos(app) else { return };
    let Ok(entradas) = fs::read_dir(dir) else { return };
    let actual = def.archivo.to_string();
    let parcial_actual = format!("{}.part", def.archivo);
    for entrada in entradas.flatten() {
        let nombre = entrada.file_name().to_string_lossy().to_string();
        if (nombre.ends_with(".gguf") || nombre.ends_with(".part")) && nombre != actual && nombre != parcial_actual {
            let _ = fs::remove_file(entrada.path());
        }
    }
}

struct Motor {
    modelo: Option<&'static LlamaModel>,
    ctx: Option<LlamaContext<'static>>,
    cargando: bool,
    generando: bool,
    descargando: bool,
    cancelar_descarga: Arc<AtomicBool>,
    cancelar_gen: Arc<AtomicBool>,
    pct: f32,
    bytes: u64,
    total: u64,
    error: Option<String>,
}

impl Default for Motor {
    fn default() -> Self {
        Self {
            modelo: None,
            ctx: None,
            cargando: false,
            generando: false,
            descargando: false,
            cancelar_descarga: Arc::new(AtomicBool::new(false)),
            cancelar_gen: Arc::new(AtomicBool::new(false)),
            pct: 0.0,
            bytes: 0,
            total: modelo_actual().bytes,
            error: None,
        }
    }
}

static MOTOR: OnceLock<Mutex<Motor>> = OnceLock::new();

fn motor() -> &'static Mutex<Motor> {
    MOTOR.get_or_init(|| Mutex::new(Motor::default()))
}

fn bloquear_motor() -> std::sync::MutexGuard<'static, Motor> {
    motor().lock().unwrap_or_else(|e| e.into_inner())
}

fn backend_llama() -> &'static LlamaBackend {
    static BACKEND: OnceLock<LlamaBackend> = OnceLock::new();
    BACKEND.get_or_init(|| LlamaBackend::init().expect("no se pudo iniciar llama.cpp"))
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
struct EstadoMotor {
    disponible: bool,
    ram_mb: u64,
    modelo_nombre: String,
    modelo_archivo: String,
    modelo_bytes: u64,
    bytes_parcial: u64,
    modelo_presente: bool,
    bytes_disco: u64,
    ruta: String,
    modelo_cargado: bool,
    descargando: bool,
    generando: bool,
    pct: f32,
    bytes: u64,
    total: u64,
    hilos: i32,
    contexto: u32,
    error: String,
}

#[tauri::command]
fn motor_estado(app: tauri::AppHandle) -> Result<EstadoMotor, String> {
    let def = modelo_actual();
    let ruta = ruta_modelo(&app)?;
    let parcial = ruta_parcial(&app)?;
    let tam = fs::metadata(&ruta).map(|m| m.len()).unwrap_or(0);
    let tam_parcial = fs::metadata(&parcial).map(|m| m.len()).unwrap_or(0);
    let m = bloquear_motor();
    let pct = if m.descargando {
        m.pct
    } else if tam_parcial > 0 && tam == 0 {
        (tam_parcial as f32 / def.bytes as f32 * 100.0).clamp(0.0, 99.9)
    } else if tam > 0 {
        100.0
    } else {
        0.0
    };
    Ok(EstadoMotor {
        disponible: true,
        ram_mb: ram_total_mb(),
        modelo_nombre: def.nombre.to_string(),
        modelo_archivo: def.archivo.to_string(),
        modelo_bytes: def.bytes,
        bytes_parcial: tam_parcial,
        modelo_presente: tam >= def.bytes.saturating_sub(1024 * 1024),
        bytes_disco: tam,
        ruta: ruta.display().to_string(),
        modelo_cargado: m.modelo.is_some(),
        descargando: m.descargando,
        generando: m.generando,
        pct,
        bytes: if m.descargando { m.bytes } else { tam.max(tam_parcial) },
        total: def.bytes,
        hilos: hilos_para(ram_total_mb()),
        contexto: CTX_TOKENS,
        error: m.error.clone().unwrap_or_default(),
    })
}

#[derive(Clone, Serialize)]
#[serde(rename_all = "camelCase")]
struct EventoProgreso {
    pct: f32,
    bytes: u64,
    total: u64,
    mensaje: String,
    error: bool,
}

#[derive(Clone, Serialize)]
#[serde(rename_all = "camelCase")]
struct EventoToken {
    delta: String,
}

#[derive(Clone, Serialize)]
#[serde(rename_all = "camelCase")]
struct EventoFin {
    texto: String,
    tokens: u32,
    ms: u64,
    tps: f32,
}

#[derive(Clone, Serialize)]
#[serde(rename_all = "camelCase")]
struct EventoMensaje {
    mensaje: String,
}

fn emitir_progreso(app: &tauri::AppHandle, pct: f32, bytes: u64, total: u64, mensaje: &str, error: bool) {
    let _ = app.emit(
        "motor-progreso",
        EventoProgreso {
            pct,
            bytes,
            total,
            mensaje: mensaje.to_string(),
            error,
        },
    );
}

/// Descarga el GGUF elegido, con reanudación por HTTP Range. Devuelve la ruta final.
fn descargar_modelo(app: &tauri::AppHandle, def: &ModeloDef, destino: &PathBuf, parcial: &PathBuf) -> Result<(), String> {
    let mut existente = fs::metadata(parcial).map(|m| m.len()).unwrap_or(0);
    if existente >= def.bytes {
        // La descarga terminó pero la app se cerró antes de renombrar: validar e instalar.
        let mut cabecera = [0u8; 4];
        let valido = File::open(parcial)
            .and_then(|mut f| f.read_exact(&mut cabecera))
            .map(|()| &cabecera == b"GGUF")
            .unwrap_or(false);
        if valido {
            fs::rename(parcial, destino).map_err(|e| format!("No se pudo instalar el modelo: {e}"))?;
            return Ok(());
        }
        let _ = fs::remove_file(parcial);
        existente = 0;
    }

    let agente = ureq::AgentBuilder::new()
        .timeout_connect(std::time::Duration::from_secs(25))
        .build();
    let mut peticion = agente.get(&def.url);
    if existente > 0 {
        peticion = peticion.set("Range", &format!("bytes={existente}-"));
        emitir_progreso(app, existente as f32 / def.bytes as f32 * 100.0, existente, def.bytes, "Reanudando descarga…", false);
    } else {
        emitir_progreso(app, 0.0, 0, def.bytes, "Conectando con el servidor del modelo…", false);
    }

    let resp = peticion
        .call()
        .map_err(|e| format!("No se pudo descargar el modelo: {e}"))?;
    let status = resp.status();
    if status != 200 && status != 206 {
        return Err(format!("El servidor del modelo respondió {status}."));
    }

    let reanuda = status == 206 && existente > 0;
    let mut archivo = if reanuda {
        OpenOptions::new()
            .append(true)
            .open(parcial)
            .map_err(|e| format!("No se pudo abrir la descarga: {e}"))?
    } else {
        existente = 0;
        OpenOptions::new()
            .create(true)
            .write(true)
            .truncate(true)
            .open(parcial)
            .map_err(|e| format!("No se pudo crear la descarga: {e}"))?
    };

    let mut lector = resp.into_reader();
    let mut buf = vec![0u8; 1 << 20];
    let mut recibidos = existente;
    let mut ultimo_aviso = Instant::now();

    loop {
        if bloquear_motor().cancelar_descarga.load(Ordering::SeqCst) {
            let _ = archivo.flush();
            return Err("Descarga cancelada.".into());
        }
        match lector.read(&mut buf) {
            Ok(0) => break,
            Ok(n) => {
                archivo
                    .write_all(&buf[..n])
                    .map_err(|e| format!("No se pudo guardar el modelo: {e}"))?;
                recibidos += n as u64;
                if ultimo_aviso.elapsed().as_millis() >= 350 {
                    let pct = (recibidos as f32 / def.bytes as f32 * 100.0).min(99.9);
                    {
                        let mut m = bloquear_motor();
                        m.pct = pct;
                        m.bytes = recibidos;
                    }
                    let mb = recibidos as f64 / 1_048_576.0;
                    let total_mb = def.bytes as f64 / 1_048_576.0;
                    let mensaje = format!("Descargando {mb:.0} de {total_mb:.0} MB…");
                    emitir_progreso(app, pct, recibidos, def.bytes, &mensaje, false);
                    ultimo_aviso = Instant::now();
                }
            }
            Err(e) => return Err(format!("La descarga se interrumpió: {e}")),
        }
    }

    archivo.flush().map_err(|e| e.to_string())?;
    let _ = archivo.sync_all();
    drop(archivo);

    if recibidos < def.bytes.saturating_sub(1024 * 1024) {
        return Err(format!(
            "La descarga quedó incompleta ({} de {} MB). Vuelve a intentarlo: se reanuda donde quedó.",
            recibidos / 1_048_576,
            def.bytes / 1_048_576
        ));
    }

    // Verificar la firma GGUF antes de dar el archivo por bueno.
    let mut cabecera = [0u8; 4];
    let mut f = File::open(parcial).map_err(|e| e.to_string())?;
    f.read_exact(&mut cabecera).map_err(|e| e.to_string())?;
    drop(f);
    if &cabecera != b"GGUF" {
        let _ = fs::remove_file(parcial);
        return Err("El archivo descargado no es un modelo GGUF válido. Se borró; intenta de nuevo.".into());
    }

    fs::rename(parcial, destino).map_err(|e| format!("No se pudo instalar el modelo: {e}"))?;
    Ok(())
}

#[tauri::command]
fn motor_descargar(app: tauri::AppHandle) -> Result<(), String> {
    let def = modelo_actual();
    let destino = ruta_modelo(&app)?;
    let parcial = ruta_parcial(&app)?;
    if fs::metadata(&destino).map(|m| m.len() >= def.bytes.saturating_sub(1024 * 1024)).unwrap_or(false) {
        return Ok(());
    }
    {
        let mut m = bloquear_motor();
        if m.descargando {
            return Err("Ya hay una descarga en curso.".into());
        }
        m.descargando = true;
        m.error = None;
        m.cancelar_descarga.store(false, Ordering::SeqCst);
        m.pct = if fs::metadata(&parcial).map(|x| x.len()).unwrap_or(0) > 0 {
            (fs::metadata(&parcial).unwrap().len() as f32 / def.bytes as f32 * 100.0).min(99.9)
        } else {
            0.0
        };
    }

    let app_hilo = app.clone();
    std::thread::spawn(move || {
        limpiar_otros_modelos(&app_hilo, &def);
        let resultado = descargar_modelo(&app_hilo, &def, &destino, &parcial);
        {
            let mut m = bloquear_motor();
            m.descargando = false;
            match &resultado {
                Ok(()) => {
                    m.pct = 100.0;
                    m.bytes = def.bytes;
                    m.total = def.bytes;
                    m.error = None;
                }
                Err(e) => {
                    m.error = Some(e.clone());
                }
            }
        }
        match resultado {
            Ok(()) => {
                emitir_progreso(&app_hilo, 100.0, def.bytes, def.bytes, "Modelo listo. El asistente ya puede responder sin conexión.", false);
                let _ = app_hilo.emit("motor-descarga-fin", EventoMensaje { mensaje: "listo".into() });
            }
            Err(e) => {
                let cancelada = e.contains("cancelada");
                emitir_progreso(&app_hilo, 0.0, 0, def.bytes, &e, !cancelada);
                let _ = app_hilo.emit("motor-descarga-fin", EventoMensaje { mensaje: e });
            }
        }
    });

    Ok(())
}

#[tauri::command]
fn motor_cancelar_descarga() -> Result<(), String> {
    let m = bloquear_motor();
    m.cancelar_descarga.store(true, Ordering::SeqCst);
    Ok(())
}

#[tauri::command]
fn motor_detener() -> Result<(), String> {
    let m = bloquear_motor();
    m.cancelar_gen.store(true, Ordering::SeqCst);
    Ok(())
}

#[tauri::command]
fn motor_eliminar(app: tauri::AppHandle) -> Result<(), String> {
    let destino = ruta_modelo(&app)?;
    let parcial = ruta_parcial(&app)?;
    {
        let mut m = bloquear_motor();
        if m.generando || m.descargando {
            return Err("Espera a que termine la tarea en curso.".into());
        }
        m.ctx = None;
        m.modelo = None;
        m.pct = 0.0;
        m.bytes = 0;
        m.error = None;
    }
    let _ = fs::remove_file(&destino);
    let _ = fs::remove_file(&parcial);
    emitir_progreso(&app, 0.0, 0, modelo_actual().bytes, "Modelo eliminado del teléfono.", false);
    Ok(())
}

fn crear_contexto(modelo: &'static LlamaModel) -> Result<LlamaContext<'static>, String> {
    let ram = ram_total_mb();
    let hilos = hilos_para(ram);
    let ctx_params = LlamaContextParams::default()
        .with_n_ctx(NonZeroU32::new(CTX_TOKENS))
        .with_n_batch(512)
        .with_n_threads(hilos)
        .with_n_threads_batch(hilos);
    modelo
        .new_context(backend_llama(), ctx_params)
        .map_err(|e| format!("No se pudo preparar el contexto del modelo: {e}"))
}

fn cargar_modelo(app: &tauri::AppHandle) -> Result<(&'static LlamaModel, LlamaContext<'static>), String> {
    let def = modelo_actual();
    let ruta = ruta_modelo(app)?;
    let tam = fs::metadata(&ruta).map(|m| m.len()).unwrap_or(0);
    if tam < def.bytes.saturating_sub(1024 * 1024) {
        return Err("El modelo todavía no está descargado.".into());
    }

    let params = LlamaModelParams::default()
        .with_n_gpu_layers(0)
        .with_use_mmap(true);
    let modelo = LlamaModel::load_from_file(backend_llama(), &ruta, &params)
        .map_err(|e| format!("No se pudo cargar el modelo: {e}"))?;
    let modelo: &'static LlamaModel = Box::leak(Box::new(modelo));
    let ctx = crear_contexto(modelo)?;
    Ok((modelo, ctx))
}

fn obtener_contexto(app: &tauri::AppHandle) -> Result<(&'static LlamaModel, LlamaContext<'static>), String> {
    let (modelo, ctx) = {
        let mut m = bloquear_motor();
        (m.modelo, m.ctx.take())
    };
    if let Some(modelo) = modelo {
        if let Some(ctx) = ctx {
            return Ok((modelo, ctx));
        }
        return crear_contexto(modelo).map(|ctx| (modelo, ctx));
    }
    {
        let mut m = bloquear_motor();
        m.cargando = true;
    }
    let _ = app.emit(
        "motor-cargando",
        EventoMensaje { mensaje: "Cargando el modelo en memoria…".into() },
    );
    let resultado = cargar_modelo(app);
    {
        let mut m = bloquear_motor();
        m.cargando = false;
        if let Ok((modelo, _)) = &resultado {
            m.modelo = Some(*modelo);
        }
    }
    resultado
}

/// Emite solo las partes de UTF-8 completas para no cortar acentos ni signos.
fn emitir_pieza(app: &tauri::AppHandle, pendiente: &mut Vec<u8>, salida: &mut String) {
    loop {
        match std::str::from_utf8(pendiente) {
            Ok(texto) => {
                if !texto.is_empty() {
                    salida.push_str(texto);
                    let _ = app.emit("motor-token", EventoToken { delta: texto.to_string() });
                }
                pendiente.clear();
                break;
            }
            Err(e) => {
                let validos = e.valid_up_to();
                if validos > 0 {
                    if let Ok(texto) = std::str::from_utf8(&pendiente[..validos]) {
                        let texto = texto.to_string();
                        salida.push_str(&texto);
                        let _ = app.emit("motor-token", EventoToken { delta: texto });
                    }
                    pendiente.drain(..validos);
                    continue;
                }
                if e.error_len().is_some() {
                    pendiente.clear();
                }
                break;
            }
        }
    }
}

#[derive(Deserialize)]
struct MensajeEntrada {
    role: String,
    content: String,
}

fn generar_tokens(
    app: &tauri::AppHandle,
    modelo: &'static LlamaModel,
    ctx: &mut LlamaContext<'static>,
    mensajes: &[MensajeEntrada],
    max_tokens: u32,
) -> Result<(String, u32, u64), String> {
    let plantilla = modelo
        .chat_template(None)
        .or_else(|_| LlamaChatTemplate::new("chatml"))
        .map_err(|e| format!("No se pudo preparar la plantilla del chat: {e}"))?;

    let mut chat = Vec::with_capacity(mensajes.len());
    for m in mensajes {
        let rol = match m.role.as_str() {
            "system" | "assistant" => m.role.clone(),
            _ => "user".to_string(),
        };
        let contenido = m.content.chars().take(12_000).collect::<String>();
        chat.push(
            LlamaChatMessage::new(rol, contenido)
                .map_err(|e| format!("Mensaje no válido: {e}"))?,
        );
    }

    let prompt = modelo
        .apply_chat_template(&plantilla, &chat, true)
        .map_err(|e| format!("No se pudo aplicar la plantilla del chat: {e}"))?;
    let tokens = modelo.vocab().tokenize(prompt.as_bytes(), true, true);
    let max_tokens = max_tokens.clamp(32, MAX_SALIDA) as usize;
    if tokens.is_empty() {
        return Err("El mensaje quedó vacío.".into());
    }
    if tokens.len() + max_tokens + 8 >= CTX_TOKENS as usize {
        return Err("La conversación es demasiado larga para el modelo. Inicia un chat nuevo.".into());
    }

    let cancelar = {
        let m = bloquear_motor();
        m.cancelar_gen.clone()
    };
    if cancelar.load(Ordering::SeqCst) {
        return Ok((String::new(), 0, 0));
    }

    ctx.clear_kv_cache();
    let mut batch = LlamaBatch::new(512, 1);
    let total_prompt = tokens.len();
    let ultimo = total_prompt - 1;
    for (i, bloque) in tokens.chunks(256).enumerate() {
        if cancelar.load(Ordering::SeqCst) {
            return Ok((String::new(), 0, 0));
        }
        batch.clear();
        let base = i * 256;
        for (j, token) in bloque.iter().enumerate() {
            let pos = base + j;
            batch
                .add(*token, pos as i32, &[0], pos == ultimo)
                .map_err(|e| format!("No se pudo preparar el lote: {e}"))?;
        }
        ctx.decode(&mut batch)
            .map_err(|e| format!("El modelo no pudo procesar el contexto: {e}"))?;
    }

    let semilla = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map(|d| d.subsec_nanos())
        .unwrap_or(42);
    let mut sampler = LlamaSampler::chain_simple([
        LlamaSampler::temp(0.35),
        LlamaSampler::top_k(40),
        LlamaSampler::top_p(0.9, 1),
        LlamaSampler::dist(semilla),
    ]);

    let inicio = Instant::now();
    let mut salida = String::new();
    let mut pendiente: Vec<u8> = Vec::new();
    let mut n_cur = total_prompt as i32;
    let mut generados: u32 = 0;

    loop {
        if cancelar.load(Ordering::SeqCst) {
            break;
        }
        let token = sampler.sample(ctx, -1);
        if modelo.vocab().is_eog(token) {
            break;
        }
        sampler.accept(token);
        pendiente.extend_from_slice(&modelo.vocab().token_to_piece(token, false, None));
        emitir_pieza(app, &mut pendiente, &mut salida);
        generados += 1;
        if generados as usize >= max_tokens {
            break;
        }
        if n_cur as u32 + 1 >= CTX_TOKENS {
            break;
        }
        batch.clear();
        batch
            .add(token, n_cur, &[0], true)
            .map_err(|e| format!("No se pudo continuar la respuesta: {e}"))?;
        ctx.decode(&mut batch)
            .map_err(|e| format!("El modelo se detuvo: {e}"))?;
        n_cur += 1;
    }

    if !pendiente.is_empty() {
        if let Ok(texto) = std::str::from_utf8(&pendiente) {
            salida.push_str(texto);
            let _ = app.emit("motor-token", EventoToken { delta: texto.to_string() });
        }
    }

    let ms = inicio.elapsed().as_millis() as u64;
    Ok((salida, generados, ms))
}

fn ejecutar_generacion(
    app: &tauri::AppHandle,
    mensajes: Vec<MensajeEntrada>,
    max_tokens: u32,
) {
    let resultado = obtener_contexto(app).and_then(|(modelo, mut ctx)| {
        let r = generar_tokens(app, modelo, &mut ctx, &mensajes, max_tokens);
        bloquear_motor().ctx = Some(ctx);
        r
    });

    match resultado {
        Ok((texto, tokens, ms)) => {
            let tps = if ms > 0 {
                (tokens as f32) / (ms as f32 / 1000.0)
            } else {
                0.0
            };
            if texto.trim().is_empty() {
                let cancelado = bloquear_motor().cancelar_gen.load(Ordering::SeqCst);
                if cancelado {
                    let _ = app.emit("motor-fin", EventoFin { texto: String::new(), tokens: 0, ms, tps: 0.0 });
                } else {
                    let mensaje = "El modelo no devolvió texto. Intenta con una pregunta más corta.".to_string();
                    bloquear_motor().error = Some(mensaje.clone());
                    let _ = app.emit("motor-error", EventoMensaje { mensaje });
                }
            } else {
                let _ = app.emit(
                    "motor-fin",
                    EventoFin { texto, tokens, ms, tps },
                );
            }
        }
        Err(e) => {
            bloquear_motor().error = Some(e.clone());
            let _ = app.emit("motor-error", EventoMensaje { mensaje: e });
        }
    }
    let mut m = bloquear_motor();
    m.generando = false;
    m.cargando = false;
}

#[tauri::command]
fn motor_responder(app: tauri::AppHandle, mensajes: Vec<MensajeEntrada>, max_tokens: Option<u32>) -> Result<(), String> {
    if mensajes.is_empty() {
        return Err("No hay mensajes para responder.".into());
    }
    let total_chars: usize = mensajes.iter().map(|m| m.content.len()).sum();
    if total_chars > 60_000 {
        return Err("El contexto es demasiado largo.".into());
    }
    {
        let mut m = bloquear_motor();
        if m.generando {
            return Err("El asistente todavía está respondiendo.".into());
        }
        m.generando = true;
        m.error = None;
        m.cancelar_gen.store(false, Ordering::SeqCst);
    }
    let app_hilo = app.clone();
    let max = max_tokens.unwrap_or(MAX_SALIDA);
    std::thread::spawn(move || ejecutar_generacion(&app_hilo, mensajes, max));
    Ok(())
}

#[tauri::command]
fn descargar_archivo(
    app: tauri::AppHandle,
    url: String,
    token: String,
    nombre: String,
) -> Result<String, String> {
    if !url.starts_with("https://") && !url.starts_with("http://") {
        return Err("Dirección de archivo no válida.".into());
    }
    if token.trim().is_empty() {
        return Err("Falta la sesión para descargar.".into());
    }
    let base = app
        .path()
        .app_data_dir()
        .map_err(|e| e.to_string())?
        .join("files")
        .join("archivos");
    fs::create_dir_all(&base).map_err(|e| e.to_string())?;

    let mut limpio: String = nombre
        .chars()
        .map(|c| if c.is_ascii_alphanumeric() || matches!(c, '.' | '-' | '_') { c } else { '_' })
        .collect();
    if limpio.trim_matches(['.', '_']).is_empty() {
        limpio = "archivo".into();
    }
    if limpio.len() > 150 {
        let ext = PathBuf::from(&limpio).extension().and_then(|x| x.to_str()).unwrap_or("").to_string();
        limpio.truncate(135);
        if !ext.is_empty() {
            limpio.push('.');
            limpio.push_str(&ext);
        }
    }
    let destino = base.join(&limpio);
    if let Ok(meta) = fs::metadata(&destino) {
        if meta.len() > 0 {
            return Ok(destino.display().to_string());
        }
    }
    let parcial = base.join(format!("{limpio}.part"));
    let agente = ureq::AgentBuilder::new()
        .timeout_connect(std::time::Duration::from_secs(25))
        .build();
    let resp = agente
        .get(&url)
        .set("Authorization", &format!("Bearer {token}"))
        .set("X-App-Token", &token)
        .call()
        .map_err(|e| format!("No se pudo descargar el archivo: {e}"))?;
    if resp.status() != 200 {
        return Err(format!("El servidor respondió {}.", resp.status()));
    }
    let mut lector = resp.into_reader();
    let mut archivo = File::create(&parcial).map_err(|e| e.to_string())?;
    if let Err(e) = std::io::copy(&mut lector, &mut archivo) {
        let _ = fs::remove_file(&parcial);
        return Err(format!("La descarga se interrumpió: {e}"));
    }
    archivo.flush().map_err(|e| e.to_string())?;
    let _ = archivo.sync_all();
    drop(archivo);
    fs::rename(&parcial, &destino).map_err(|e| format!("No se pudo guardar el archivo: {e}"))?;
    Ok(destino.display().to_string())
}

/// Lee un archivo ya descargado en `files/archivos` y lo entrega a la app como bytes
/// (visor PDF/imágenes). Solo acepta rutas dentro de esa carpeta.
#[tauri::command]
fn leer_archivo(app: tauri::AppHandle, ruta: String) -> Result<tauri::ipc::Response, String> {
    let base = app
        .path()
        .app_data_dir()
        .map_err(|e| e.to_string())?
        .join("files")
        .join("archivos");
    let base = base.canonicalize().unwrap_or(base);
    let archivo = std::path::PathBuf::from(&ruta)
        .canonicalize()
        .map_err(|_| "El archivo no está descargado en este teléfono.".to_string())?;
    if !archivo.starts_with(&base) {
        return Err("Ruta de archivo no válida.".into());
    }
    let meta = fs::metadata(&archivo).map_err(|e| e.to_string())?;
    if meta.len() > 80 * 1024 * 1024 {
        return Err("El archivo es demasiado grande para verlo dentro de la app.".into());
    }
    let datos = fs::read(&archivo).map_err(|e| format!("No se pudo leer el archivo: {e}"))?;
    Ok(tauri::ipc::Response::new(datos))
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_notification::init())
        .plugin(tauri_plugin_opener::init())
        .plugin(tauri_plugin_secure_keystore::init())
        .invoke_handler(tauri::generate_handler![
            db_estado,
            cache_guardar,
            cache_leer,
            cache_borrar,
            cache_limpiar,
            notificar,
            motor_estado,
            motor_descargar,
            motor_cancelar_descarga,
            motor_detener,
            motor_eliminar,
            motor_responder,
            descargar_archivo,
            leer_archivo
        ])
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("error while building tauri application");
}
