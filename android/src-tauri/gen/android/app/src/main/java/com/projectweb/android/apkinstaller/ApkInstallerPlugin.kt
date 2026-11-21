package com.projectweb.android.apkinstaller

import android.app.Activity
import android.content.Intent
import android.net.Uri
import android.provider.Settings
import androidx.core.content.FileProvider
import app.tauri.annotation.Command
import app.tauri.annotation.InvokeArg
import app.tauri.annotation.TauriPlugin
import app.tauri.plugin.Invoke
import app.tauri.plugin.Plugin
import java.io.File

@InvokeArg
class InstallArgs {
  lateinit var path: String
}

@TauriPlugin
class ApkInstallerPlugin(private val activity: Activity) : Plugin(activity) {
  @Command
  fun install(invoke: Invoke) {
    try {
      val args = invoke.parseArgs(InstallArgs::class.java)
      val archivo = File(args.path)
      if (!archivo.isFile) {
        invoke.reject("El archivo de actualización ya no está disponible.")
        return
      }

      // Android 8+: el usuario debe permitir instalar apps desde Project Web una vez.
      if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O &&
        !activity.packageManager.canRequestPackageInstalls()
      ) {
        val ajustes = Intent(
          Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
          Uri.parse("package:${activity.packageName}")
        )
        ajustes.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        activity.startActivity(ajustes)
        invoke.reject("Permite instalar apps desde Project Web y vuelve a tocar «Descargar e instalar».")
        return
      }

      val uri = FileProvider.getUriForFile(
        activity,
        "${activity.packageName}.fileprovider",
        archivo
      )
      val intent = Intent(Intent.ACTION_VIEW).apply {
        setDataAndType(uri, "application/vnd.android.package-archive")
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
      }
      activity.startActivity(intent)
      invoke.resolve()
    } catch (ex: Exception) {
      invoke.reject(ex.message ?: "No se pudo abrir el instalador de Android.")
    }
  }
}
