//! Plugin Android mínimo para abrir el instalador de APK con un FileProvider.
//! La descarga y la verificación del SHA-256 las hace el comando `descargar_apk`.

use tauri::plugin::{Builder, PluginHandle, TauriPlugin};
use tauri::{Manager, Runtime};

pub struct ApkInstaller<R: Runtime> {
    pub handle: PluginHandle<R>,
}

pub fn init<R: Runtime>() -> TauriPlugin<R> {
    Builder::new("apk-installer")
        .setup(|app, api| {
            let handle = api.register_android_plugin(
                "com.projectweb.android.apkinstaller",
                "ApkInstallerPlugin",
            )?;
            app.manage(ApkInstaller { handle });
            Ok(())
        })
        .build()
}
