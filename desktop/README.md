# Project Web Desktop

App de escritorio (Electron, Linux) solo para el administrador: Project Web con su inicio de sesión (al entrar se accede a toda la información de la cuenta) y, como espacio aparte, Herramientas IA: conexiones a Ollama, APIs (OpenAI-compatibles, Anthropic) y agentes CLI (Claude Code, opencode), agrupadas en «Configuración de IA». Además incluye el «Programador de actividades», que analiza el proyecto abierto con la IA elegida y genera un programa propuesto en Project Web (no reemplaza al actual). Se actualiza desde Project Web.

Los agentes CLI usan la sesión que ya tienes en el terminal: Claude Code con `/login`; opencode, además, pide elegir modelo en la conexión («Listar modelos» corre `opencode models`): `opencode-go/…` va por tu suscripción OpenCode Go y `…-free` por la capa gratuita, que tiene cuota propia por cuenta.

```bash
cd desktop && npm install
npm start              # desarrollo
npm test               # pruebas de conexiones y versiones
npm run dist           # AppImage + deb en desktop/release/
```

Publicar una versión: subir a Project Web → Ajustes → App de escritorio los `projectweb-desktop-linux-*` y `release/latest-linux.yml`. Para que la app lo detecte, subir `version` en `package.json` antes de compilar. Generar ahí el token y pegarlo en la pestaña Actualizaciones de la app.
