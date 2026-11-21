package com.projectweb.android.galeria

import android.Manifest
import android.app.Activity
import android.content.ContentValues
import android.content.pm.PackageManager
import android.media.MediaScannerConnection
import android.os.Build
import android.os.Environment
import android.provider.MediaStore
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import app.tauri.annotation.Command
import app.tauri.annotation.InvokeArg
import app.tauri.annotation.TauriPlugin
import app.tauri.plugin.Invoke
import app.tauri.plugin.Plugin
import java.io.File

@InvokeArg
class GuardarArgs {
  lateinit var path: String
  var album: String = "Project Web"
  var nombre: String = ""
  var mime: String = "image/jpeg"
}

private fun limpiar(valor: String, porDefecto: String, max: Int = 80): String {
  val base = java.text.Normalizer.normalize(valor, java.text.Normalizer.Form.NFD)
  val limpio = base
    .replace(Regex("\\p{Mn}+"), "")
    .filter { it.isLetterOrDigit() || it == ' ' || it == '-' || it == '_' || it == '.' }
    .trim()
    .replace(Regex("\\s+"), " ")
  return (limpio.ifBlank { porDefecto }).take(max)
}

/**
 * Copia una foto ya guardada en los datos privados de la app al álbum público
 * `Pictures/Project Web/<álbum>`. No sube nada a la web ni a la plataforma.
 */
@TauriPlugin
class GaleriaPlugin(private val activity: Activity) : Plugin(activity) {
  @Command
  fun guardar(invoke: Invoke) {
    try {
      val args = invoke.parseArgs(GuardarArgs::class.java)
      val origen = File(args.path)
      if (!origen.isFile) {
        invoke.reject("La foto ya no está disponible en el teléfono.")
        return
      }
      val album = limpiar(args.album, "Project Web")
      val nombre = limpiar(args.nombre.ifBlank { origen.name }, "projectweb-${System.currentTimeMillis()}.jpg", 120)
      val mime = args.mime.ifBlank { "image/jpeg" }

      if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
        val valores = ContentValues().apply {
          put(MediaStore.Images.Media.DISPLAY_NAME, nombre)
          put(MediaStore.Images.Media.MIME_TYPE, mime)
          put(MediaStore.Images.Media.RELATIVE_PATH, "Pictures/Project Web/$album")
          put(MediaStore.Images.Media.IS_PENDING, 1)
        }
        val resolver = activity.contentResolver
        val uri = resolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, valores)
          ?: throw IllegalStateException("Android no permitió crear la foto en el álbum.")
        try {
          resolver.openOutputStream(uri)?.use { salida -> origen.inputStream().use { it.copyTo(salida) } }
            ?: throw IllegalStateException("No se pudo abrir el álbum del teléfono.")
          valores.clear()
          valores.put(MediaStore.Images.Media.IS_PENDING, 0)
          resolver.update(uri, valores, null, null)
        } catch (ex: Exception) {
          resolver.delete(uri, null, null)
          throw ex
        }
      } else {
        if (ContextCompat.checkSelfPermission(activity, Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) {
          ActivityCompat.requestPermissions(activity, arrayOf(Manifest.permission.WRITE_EXTERNAL_STORAGE), 9101)
          invoke.reject("Permite guardar fotos en el teléfono y vuelve a intentarlo.")
          return
        }
        val carpeta = File(
          Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_PICTURES),
          "Project Web/$album"
        )
        if (!carpeta.exists() && !carpeta.mkdirs()) {
          throw IllegalStateException("No se pudo crear el álbum en el teléfono.")
        }
        val destino = File(carpeta, nombre)
        origen.copyTo(destino, overwrite = true)
        MediaScannerConnection.scanFile(activity, arrayOf(destino.absolutePath), arrayOf(mime), null)
      }
      invoke.resolve()
    } catch (ex: Exception) {
      invoke.reject(ex.message ?: "No se pudo guardar la foto en el álbum del teléfono.")
    }
  }
}
