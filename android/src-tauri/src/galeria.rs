//! Plugin Android mínimo para copiar una foto del almacenamiento privado de la app
//! al álbum público del teléfono (`Pictures/Project Web/<álbum>`), usando MediaStore.
//! Las herramientas de campo guardan sus datos solo en el teléfono; este plugin no
//! habla con la web ni con la base de la plataforma.

use tauri::plugin::{Builder, PluginHandle, TauriPlugin};
use tauri::{Manager, Runtime};

pub struct Galeria<R: Runtime> {
    pub handle: PluginHandle<R>,
}

pub fn init<R: Runtime>() -> TauriPlugin<R> {
    Builder::new("galeria")
        .setup(|app, api| {
            let handle = api.register_android_plugin(
                "com.projectweb.android.galeria",
                "GaleriaPlugin",
            )?;
            app.manage(Galeria { handle });
            Ok(())
        })
        .build()
}
