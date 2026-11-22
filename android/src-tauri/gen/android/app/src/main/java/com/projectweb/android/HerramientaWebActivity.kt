package com.projectweb.android

import android.app.DownloadManager
import android.content.ContentValues
import android.content.Intent
import android.graphics.Color
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.print.PrintManager
import android.provider.MediaStore
import android.util.Base64
import android.view.View
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.MimeTypeMap
import android.webkit.URLUtil
import android.webkit.WebView
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.enableEdgeToEdge
import androidx.core.content.FileProvider
import androidx.core.view.ViewCompat
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import java.io.File
import java.util.concurrent.Executors

/**
 * Herramienta web de Project Web abierta dentro de la app.
 *
 * Es una Activity aparte para no descargar la app nativa (Inicio, Obras, asistente,
 * parte diario, cola offline…). La ventana la crea el comando Rust
 * `abrir_herramienta_web`, que solo acepta la URL del puente de sesión del servidor
 * configurado. La Activity se encarga de lo que el WebView no hace:
 *
 *  • descargas GET con la cookie de sesión (DownloadManager);
 *  • descargas blob:/data: y exportaciones POST (puente JavaScript → Descargas);
 *  • PDFs que el WebView no puede mostrar (se descargan y se abren con el sistema);
 *  • atrás: historial web y, en la primera página, volver a la app nativa.
 *
 * Las cookies de la sesión web se borran al cerrar la Activity: la próxima herramienta
 * pide un código nuevo a `/api/app/enlace` y no queda una sesión web viva.
 */
class HerramientaWebActivity : TauriActivity() {

  private var webViewPrincipal: WebView? = null
  private var origenWeb: String? = null
  private val guardado = Executors.newSingleThreadExecutor()

  /** Wry no registra su callback: aquí se decide entre historial web y volver a la app. */
  override val handleBackNavigation: Boolean = false

  override fun onCreate(savedInstanceState: Bundle?) {
    enableEdgeToEdge()
    super.onCreate(savedInstanceState)

    val fondo = Color.parseColor("#0f172a")
    val raiz = findViewById<View>(android.R.id.content)
    raiz?.setBackgroundColor(fondo)
    WindowCompat.getInsetsController(window, window.decorView).apply {
      isAppearanceLightStatusBars = false
      isAppearanceLightNavigationBars = false
    }
    if (raiz != null) {
      ViewCompat.setOnApplyWindowInsetsListener(raiz) { v, insets ->
        val b = insets.getInsets(
          WindowInsetsCompat.Type.systemBars() or
            WindowInsetsCompat.Type.ime() or
            WindowInsetsCompat.Type.displayCutout()
        )
        v.setPadding(b.left, b.top, b.right, b.bottom)
        WindowInsetsCompat.CONSUMED
      }
      ViewCompat.requestApplyInsets(raiz)
    }

    onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
      override fun handleOnBackPressed() {
        val w = webViewPrincipal
        if (w == null) {
          finish()
          return
        }
        val lista = w.copyBackForwardList()
        val anterior = if (lista.currentIndex > 0) {
          lista.getItemAtIndex(lista.currentIndex - 1)?.url
        } else {
          null
        }
        val volverAlHistorial = anterior != null &&
          !anterior.contains("/acceso-app") &&
          !anterior.contains("/login")
        if (volverAlHistorial && w.canGoBack()) w.goBack() else finish()
      }
    })
  }

  override fun onWebViewCreate(webView: WebView) {
    webViewPrincipal = webView
    webView.setBackgroundColor(Color.parseColor("#0f172a"))
    webView.addJavascriptInterface(PuenteWeb(), "ProjectWebAndroid")

    webView.setDownloadListener { url, agente, disposicion, tipo, _ ->
      try {
        when {
          url.startsWith("blob:") ->
            descargarBlob(webView, url, URLUtil.guessFileName(url, disposicion, tipo), tipo ?: "")
          url.startsWith("data:") ->
            guardarDataUrl(url, URLUtil.guessFileName(url, disposicion, tipo), tipo ?: "")
          url.startsWith("http") ->
            descargarUrl(url, URLUtil.guessFileName(url, disposicion, tipo), tipo ?: "", agente)
          else ->
            Toast.makeText(this, "Esta descarga no es compatible con la app; ábrela en el navegador.", Toast.LENGTH_LONG).show()
        }
      } catch (e: Exception) {
        Toast.makeText(this, "No se pudo descargar: ${e.message}", Toast.LENGTH_LONG).show()
      }
    }
  }

  /** Primera URL http(s) que no sea el origen local de Tauri: el servidor de confianza. */
  override fun onWebViewHistoryChanged(webView: WebView) {
    if (origenWeb != null) return
    val uri = Uri.parse(webView.url ?: return)
    if ((uri.scheme == "https" || uri.scheme == "http") && uri.host != "tauri.localhost") {
      origenWeb = "${uri.scheme}://${uri.authority}"
    }
  }

  private fun origenActual(): String? {
    val uri = Uri.parse(webViewPrincipal?.url ?: return origenWeb)
    if ((uri.scheme == "https" || uri.scheme == "http") && uri.host != "tauri.localhost") {
      return "${uri.scheme}://${uri.authority}"
    }
    return origenWeb
  }

  private fun mismoOrigen(url: String): Boolean {
    val actual = origenActual() ?: return false
    val uri = Uri.parse(url)
    if (uri.scheme != "https" && uri.scheme != "http") return false
    return "${uri.scheme}://${uri.authority}" == actual
  }

  private fun nombreSeguro(nombre: String, tipo: String): String {
    var limpio = nombre.replace(Regex("[\\\\/:*?\"<>|]+"), "_").trim().ifBlank { "descarga" }
    val extension = MimeTypeMap.getSingleton().getExtensionFromMimeType(tipo)
    if (extension != null && !limpio.lowercase().endsWith(".$extension")) {
      limpio = "$limpio.$extension"
    }
    return limpio.take(120)
  }

  private fun descargarUrl(url: String, nombre: String, tipo: String, agente: String? = null) {
    if (!mismoOrigen(url)) {
      Toast.makeText(this, "La descarga no pertenece a este servidor.", Toast.LENGTH_LONG).show()
      return
    }
    val archivo = nombreSeguro(nombre, tipo.ifBlank { "application/octet-stream" })
    val mime = tipo.ifBlank { "application/octet-stream" }
    val solicitud = DownloadManager.Request(Uri.parse(url)).apply {
      setMimeType(mime)
      CookieManager.getInstance().getCookie(url)?.let { addRequestHeader("Cookie", it) }
      (agente ?: webViewPrincipal?.settings?.userAgentString)?.let { addRequestHeader("User-Agent", it) }
      setTitle(archivo)
      setDescription("Project Web")
      setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
      if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
        setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, archivo)
      } else {
        // Sin permiso de almacenamiento: queda en la carpeta privada de la app y se abre con FileProvider.
        setDestinationInExternalFilesDir(this@HerramientaWebActivity, Environment.DIRECTORY_DOWNLOADS, archivo)
      }
    }
    (getSystemService(DOWNLOAD_SERVICE) as DownloadManager).enqueue(solicitud)
    Toast.makeText(this, "Descargando $archivo…", Toast.LENGTH_SHORT).show()
  }

  private fun descargarBlob(webView: WebView, url: String, nombre: String, tipo: String) {
    val js = "(function(){var u=" + org.json.JSONObject.quote(url) +
      ",n=" + org.json.JSONObject.quote(nombre) +
      ",t=" + org.json.JSONObject.quote(tipo) + ";" +
      "fetch(u).then(function(r){return r.blob();}).then(function(b){return new Promise(function(ok,ko){" +
      "var f=new FileReader();f.onload=function(){ok([f.result,b.type]);};f.onerror=ko;f.readAsDataURL(b);});})" +
      ".then(function(x){var d=String(x[0]);ProjectWebAndroid.guardar(d.substring(d.indexOf(',')+1),n,x[1]||t);})" +
      ".catch(function(e){ProjectWebAndroid.error(String(e&&e.message||e));});})();"
    webView.evaluateJavascript(js, null)
  }

  private fun guardarDataUrl(url: String, nombre: String, tipo: String) {
    val coma = url.indexOf(',')
    if (coma < 0) return
    val cabecera = url.substring(5, maxOf(5, coma))
    val mime = cabecera.substringBefore(';').ifBlank { tipo.ifBlank { "application/octet-stream" } }
    val datos = url.substring(coma + 1)
    val base64 = if (cabecera.contains(";base64")) {
      datos
    } else {
      Base64.encodeToString(Uri.decode(datos).toByteArray(), Base64.NO_WRAP)
    }
    guardarBase64(base64, nombre, mime)
  }

  private fun guardarBase64(base64: String, nombre: String, tipo: String) {
    guardado.execute {
      try {
        val bytes = Base64.decode(base64, Base64.DEFAULT)
        val mime = tipo.ifBlank { "application/octet-stream" }
        val archivo = nombreSeguro(nombre, mime)
        val uri: Uri
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
          val valores = ContentValues().apply {
            put(MediaStore.Downloads.DISPLAY_NAME, archivo)
            put(MediaStore.Downloads.MIME_TYPE, mime)
            put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS)
          }
          uri = contentResolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, valores)
            ?: throw IllegalStateException("Android no dio espacio en Descargas")
          contentResolver.openOutputStream(uri)?.use { it.write(bytes) }
            ?: throw IllegalStateException("no se pudo escribir el archivo")
        } else {
          val carpeta = getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS) ?: filesDir
          val destino = File(carpeta, archivo)
          destino.writeBytes(bytes)
          uri = FileProvider.getUriForFile(this, "$packageName.fileprovider", destino)
        }
        runOnUiThread {
          Toast.makeText(this, "Guardado: $archivo", Toast.LENGTH_SHORT).show()
          abrirArchivo(uri, mime)
        }
      } catch (e: Exception) {
        runOnUiThread {
          Toast.makeText(this, "No se pudo guardar: ${e.message}", Toast.LENGTH_LONG).show()
        }
      }
    }
  }

  private fun abrirArchivo(uri: Uri, mime: String) {
    try {
      val ver = Intent(Intent.ACTION_VIEW).apply {
        setDataAndType(uri, mime)
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
      }
      startActivity(Intent.createChooser(ver, "Abrir con").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    } catch (_: Exception) {
      // Sin visor instalado: el archivo ya quedó guardado.
    }
  }

  override fun onDestroy() {
    try {
      val cm = CookieManager.getInstance()
      cm.removeAllCookies { cm.flush() }
    } catch (_: Exception) {
      // La sesión web caduca sola si Android no deja limpiar la cookie.
    }
    guardado.shutdown()
    super.onDestroy()
  }

  /** Puente que ve la página mientras está dentro de la app. */
  inner class PuenteWeb {
    @JavascriptInterface
    fun guardar(base64: String, nombre: String, tipo: String) {
      if (base64.length > 56 * 1024 * 1024) {
        runOnUiThread {
          Toast.makeText(
            this@HerramientaWebActivity,
            "El archivo es demasiado grande para guardarlo desde la app; ábrelo en el navegador.",
            Toast.LENGTH_LONG
          ).show()
        }
        return
      }
      guardarBase64(base64, nombre, tipo)
    }

    @JavascriptInterface
    fun error(mensaje: String) {
      runOnUiThread {
        Toast.makeText(this@HerramientaWebActivity, mensaje, Toast.LENGTH_LONG).show()
      }
    }

    @JavascriptInterface
    fun abrirPdf(url: String) {
      runOnUiThread {
        try {
          descargarUrl(
            url,
            URLUtil.guessFileName(url, null, "application/pdf"),
            "application/pdf"
          )
        } catch (e: Exception) {
          Toast.makeText(this@HerramientaWebActivity, "No se pudo abrir el PDF: ${e.message}", Toast.LENGTH_LONG).show()
        }
      }
    }

    @JavascriptInterface
    fun volver() {
      runOnUiThread { finish() }
    }

    /** `window.print()` del WebView no hace nada; se abre el cuadro de impresión de Android. */
    @JavascriptInterface
    fun imprimir(titulo: String) {
      runOnUiThread {
        val w = webViewPrincipal ?: return@runOnUiThread
        val nombre = titulo.ifBlank { "Project Web" }
        try {
          val impresion = getSystemService(PRINT_SERVICE) as PrintManager
          impresion.print(nombre, w.createPrintDocumentAdapter(nombre), null)
        } catch (e: Exception) {
          Toast.makeText(this@HerramientaWebActivity, "No se pudo imprimir: ${e.message}", Toast.LENGTH_LONG).show()
        }
      }
    }
  }
}
