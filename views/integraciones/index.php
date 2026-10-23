<?php
/** Integraciones. Variables: $usuario, $tg (Telegram y clima), $disponible, $tokens, $proyectos, $proyecto, $tokenNuevo, $mensaje, $url */

$pid = $proyecto ? (int)$proyecto->id : 0;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-plug"></i> <?php echo s(t('Integraciones')); ?></h2>
        <p class="text-muted"><?php echo s(t('Conecta ProjectWeb con otras herramientas: la')); ?> <strong><?php echo s(t('API REST')); ?></strong> <?php echo s(t('lee y escribe datos con un token, el')); ?> <strong><?php echo s(t('bot de Telegram')); ?></strong> <?php echo s(t('manda los avisos a tu móvil y el clima automático anota el día en la bitácora.')); ?></p>
    </div>
</div>
<?php if (!$disponible): ?><div class="card"><p><?php echo s(t('Falta aplicar la migración')); ?> <code>database/migracion_fase5.sql</code>.</p></div><?php return; endif; ?>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>
<?php if ($tokenNuevo): ?>
<div class="card integ-token-nuevo"><strong><?php echo s(t('Tu token nuevo')); ?></strong> <small class="text-muted"><?php echo s(t('· cópialo ahora, no se vuelve a mostrar')); ?></small>
    <div class="co-enlace"><input type="text" readonly value="<?php echo s($tokenNuevo); ?>" onfocus="this.select()" aria-label="Token"><button type="button" class="btn btn-sm btn-primary" data-copiar="<?php echo s($tokenNuevo); ?>"><i class="fa-regular fa-copy"></i> <?php echo s(t('Copiar')); ?></button></div></div>
<?php endif; ?>

<section class="card" id="tokens">
    <h3><?php echo s(t('Tokens de la API')); ?></h3>
    <p class="text-sm text-muted"><?php echo s(t('Un token actúa como tú: ve y modifica lo mismo que tú en la web. Hazlo de')); ?> <em><?php echo s(t('solo lectura')); ?></em> <?php echo s(t('si la otra herramienta solo consulta, y ligado a un proyecto si no debe ver los demás.')); ?></p>
    <?php if ($tokens): ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th><?php echo s(t('Nombre')); ?></th><th class="hide-m"><?php echo s(t('Alcance')); ?></th><th class="hide-m"><?php echo s(t('Vence')); ?></th><th class="hide-m"><?php echo s(t('Uso')); ?></th><th></th></tr></thead>
        <tbody><?php foreach ($tokens as $t): $vivo = !(int)$t['revocado'] && !(int)$t['caducado']; ?>
            <tr>
                <td><strong><?php echo s($t['nombre']); ?></strong><br><small class="mono text-muted"><?php echo s($t['prefijo']); ?>…</small> <?php echo $vivo ? '' : '<span class="cb-tag cb-tag-critica">' . ((int)$t['revocado'] ? 'Revocado' : 'Caducó') . '</span>'; ?></td>
                <td class="hide-m text-sm"><?php echo (int)$t['escritura'] ? 'Lectura y escritura' : 'Solo lectura'; ?><br><small class="text-muted"><?php echo $t['proyecto'] ? s($t['proyecto']) : 'Todos mis proyectos'; ?></small></td>
                <td class="mono hide-m"><?php echo $t['expira'] ? formatear_fecha($t['expira']) : 'No vence'; ?></td>
                <td class="mono hide-m text-sm"><?php echo (int)$t['usos']; ?> peticiones<?php echo $t['ultimo_uso'] ? '<br><small class="text-muted">' . formatear_fecha(substr($t['ultimo_uso'], 0, 10)) . '</small>' : ''; ?></td>
                <td class="acciones"><?php if ($vivo): ?><form method="POST" action="/integraciones/token/revocar" style="display:inline" data-confirm="¿Revocar este token?|Quien lo use dejará de poder entrar.|Revocar"><input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-danger" type="submit"><?php echo s(t('Revocar')); ?></button></form><?php endif; ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
    <form method="POST" action="/integraciones/token/crear" class="cp-fila cp-nuevo" style="align-items:flex-end">
        <input type="text" name="nombre" maxlength="100" placeholder="Para qué es (ej. Power BI)" required aria-label="Nombre del token">
        <select name="proyecto_id" aria-label="Proyecto"><option value="0"><?php echo s(t('Todos mis proyectos')); ?></option><?php foreach ($proyectos as $p): ?><option value="<?php echo (int)$p->id; ?>"><?php echo s($p->nombre); ?></option><?php endforeach; ?></select>
        <select name="dias" aria-label="Vigencia"><option value="0"><?php echo s(t('No vence')); ?></option><option value="30"><?php echo s(t('30 días')); ?></option><option value="90"><?php echo s(t('90 días')); ?></option><option value="365"><?php echo s(t('1 año')); ?></option></select>
        <label class="campo-check"><input type="checkbox" name="escritura" value="1"> <?php echo s(t('Puede escribir')); ?></label>
        <button class="btn btn-primary btn-sm" type="submit"><?php echo s(t('Crear token')); ?></button>
    </form>
</section>

<section class="card" id="telegram">
    <h3><i class="fa-brands fa-telegram"></i> Telegram: avisos, avance y fotos desde el celular</h3>
    <?php if (!$tg['tablas']): ?>
        <p class="text-muted">Falta aplicar la migración <code>database/migracion_telegram.sql</code>.</p>
    <?php elseif (!$tg['configurado']): ?>
        <p class="text-muted">Telegram todavía no está activado en este servidor. <?php if ($tg['esAdmin']): ?>Solo se hace <strong>una vez</strong>:</p>
        <ol class="text-sm">
            <li>En Telegram abre <strong>@BotFather</strong>, escribe <code>/newbot</code>, ponle un nombre y copia el <em>token</em> que te da.</li>
            <li>Pégalo en el archivo <code>.env</code> del servidor como <code>TELEGRAM_BOT_TOKEN=…</code> y reinicia.</li>
            <li>Regresa aquí y pulsa <em>Activar el bot</em>. Con servidor local (sin https) usa <code>php bin/telegram.php --sondeo</code>.</li>
        </ol>
        <?php else: ?>Pide al administrador que lo active.</p><?php endif; ?>
    <?php else: ?>
        <p class="text-sm text-muted">Un solo bot para todos. Conecta tu Telegram y podrás: <strong>recibir avisos</strong> (RFI, pendientes, restricciones, clima), <strong>reportar el avance</strong> de una actividad con botones, <strong>mandar fotos</strong> como evidencia y <strong>anotar en la bitácora</strong>, sin abrir la plataforma.</p>

        <?php if ($tg['enlace']): ?>
        <div class="integ-token-nuevo card"><strong><?php echo $tg['enlace']['tipo'] === 'grupo' ? 'Agregar el bot a un grupo' : 'Conectar mi Telegram'; ?></strong> <small class="text-muted">· vale 15 minutos y se usa una sola vez</small>
            <div class="co-enlace"><input type="text" readonly value="<?php echo s($tg['enlace']['url']); ?>" onfocus="this.select()" aria-label="Enlace"><a class="btn btn-sm btn-primary" href="<?php echo s($tg['enlace']['url']); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-telegram"></i> Abrir Telegram</a></div></div>
        <?php endif; ?>

        <h4>Mi cuenta</h4>
        <?php if ($tg['vinculo']): ?>
            <p><span class="cb-tag">Conectado</span> como <strong><?php echo s($tg['vinculo']['nombre_tg'] ?: 'tu cuenta'); ?></strong> · bot <code>@<?php echo s($tg['bot']); ?></code>.
                En el chat escribe <code>/ayuda</code>. Las órdenes de avance y fotos las puede dar el administrador o el dueño del proyecto.</p>
            <form method="POST" action="/integraciones/telegram/desconectar" data-confirm="¿Desconectar Telegram?|Dejarás de recibir avisos y de poder reportar desde el chat.|Desconectar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-sm btn-ghost" type="submit">Desconectar</button></form>
        <?php else: ?>
            <form method="POST" action="/integraciones/telegram/conectar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-primary btn-sm" type="submit"><i class="fa-brands fa-telegram"></i> Conectar mi Telegram</button></form>
        <?php endif; ?>

        <?php if ($proyectos): ?>
        <h4>Avisos de un proyecto</h4>
        <form method="GET" class="inc-filtros"><label>Proyecto <select name="proyecto" onchange="this.form.action='#telegram';this.form.submit()"><?php foreach ($proyectos as $p): ?><option value="<?php echo (int)$p->id; ?>"<?php echo (int)$p->id === $pid ? ' selected' : ''; ?>><?php echo s($p->nombre); ?></option><?php endforeach; ?></select></label></form>
        <?php $mio = false; foreach ($tg['destinos'] as $d) if ($d['usuario_id'] && (int)$d['usuario_id'] === (int)$usuario['id']) $mio = true; ?>
        <div class="cp-fila" style="gap:1rem;flex-wrap:wrap">
            <?php if ($tg['vinculo']): ?>
            <form method="POST" action="/integraciones/telegram/mi-chat"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><?php if (!$mio): ?><input type="hidden" name="activo" value="1"><?php endif; ?>
                <button class="btn btn-sm <?php echo $mio ? 'btn-ghost' : 'btn-primary'; ?>" type="submit"><?php echo $mio ? 'Dejar de recibir avisos en mi chat' : 'Recibir avisos de este proyecto en mi chat'; ?></button></form>
            <?php endif; ?>
            <form method="POST" action="/integraciones/telegram/grupo"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-users"></i> Agregar a un grupo de la obra</button></form>
        </div>
        <?php foreach ($tg['destinos'] as $d): $evs = $d['eventos'] === '' ? [] : explode(',', $d['eventos']); ?>
            <div class="integ-wh<?php echo (int)$d['activo'] ? '' : ' integ-wh-pausado'; ?>">
                <div><strong><i class="fa-solid <?php echo $d['usuario_id'] ? 'fa-user' : 'fa-users'; ?>"></i> <?php echo s($d['titulo'] ?: 'Chat'); ?></strong> <?php echo (int)$d['activo'] ? '<span class="cb-tag">Activo</span>' : '<span class="cb-tag cb-tag-critica">Pausado</span>'; ?>
                    <?php echo (int)$d['fallos_seguidos'] ? ' <small class="prg-peor">' . (int)$d['fallos_seguidos'] . ' fallo(s) seguidos</small>' : ''; ?>
                    <form method="POST" action="/integraciones/telegram/destino"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>"><input type="hidden" name="accion" value="eventos">
                        <details><summary class="text-sm">Qué avisos recibe (<?php echo count($evs); ?>)</summary>
                            <div class="integ-eventos"><?php foreach ($tg['eventos'] as $k => $desc): ?><label class="campo-check"><input type="checkbox" name="eventos[]" value="<?php echo s($k); ?>"<?php echo in_array($k, $evs, true) ? ' checked' : ''; ?>> <?php echo s($desc); ?></label><?php endforeach; ?></div>
                            <button class="btn btn-sm btn-primary" type="submit">Guardar avisos</button></details></form></div>
                <div class="acciones">
                    <?php foreach ([['probar', 'Probar'], [(int)$d['activo'] ? 'pausar' : 'activar', (int)$d['activo'] ? 'Pausar' : 'Activar'], ['quitar', 'Quitar']] as [$acc, $txt]): ?>
                    <form method="POST" action="/integraciones/telegram/destino"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>"><input type="hidden" name="accion" value="<?php echo $acc; ?>"><button class="btn btn-sm btn-ghost" type="submit"><?php echo $txt; ?></button></form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$tg['destinos']): ?><p class="text-sm text-muted">Este proyecto aún no manda avisos a ningún chat.</p><?php endif; ?>
        <?php endif; ?>

        <?php if ($tg['esAdmin']): ?>
        <details><summary class="text-sm">Administración del bot</summary>
            <p class="text-sm text-muted">Bot <code>@<?php echo s($tg['bot'] ?: '?'); ?></code>. Telegram entrega los mensajes en <code><?php echo s($url); ?>/api/telegram/webhook</code> (necesita https público).
                <?php if ($tg['webhook']): ?> Estado: <?php echo $tg['webhook']['url'] ? '<span class="cb-tag">Registrado</span> · ' . (int)($tg['webhook']['pending_update_count'] ?? 0) . ' pendiente(s)' . (!empty($tg['webhook']['last_error_message']) ? ' · <span class="prg-peor">último error: ' . s($tg['webhook']['last_error_message']) . '</span>' : '') : '<span class="cb-tag cb-tag-critica">Sin registrar</span>'; ?><?php endif; ?></p>
            <form method="POST" action="/integraciones/telegram/webhook"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-sm btn-primary" type="submit">Activar el bot (registrar webhook)</button></form>
        </details>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card" id="clima">
    <h3><i class="fa-solid fa-cloud-sun-rain"></i> Clima automático</h3>
    <?php if (!$proyecto || !$tg['tablas']): ?><p class="text-muted">Necesitas un proyecto que puedas gestionar<?php echo $tg['tablas'] ? '' : ' y aplicar la migración de Telegram'; ?>.</p><?php else: ?>
    <p class="text-sm text-muted">Cada tarde (después de las <?php echo \Classes\ClimaAuto::hora(); ?>:00) se anota en la <strong>bitácora</strong> el clima del día de <strong><?php echo s($proyecto->nombre); ?></strong> (temperaturas, lluvia y viento, de Open-Meteo, gratis y sin cuenta). Si <strong>mañana</strong> habrá lluvia o viento que frenen el exterior, el bot avisa con las actividades afectadas (si el aviso «Mañana habrá lluvia o viento fuerte» está activo en un destino de arriba).</p>
    <p class="text-sm">Ubicación del proyecto: <strong><?php echo $tg['ubicacion'] !== '' ? s($tg['ubicacion']) : '—'; ?></strong><?php echo $tg['lugar'] !== '' ? ' → ubicado en <em>' . s($tg['lugar']) . '</em>' : ''; ?>. <?php if ($tg['ubicacion'] === ''): ?><span class="prg-peor">Falta la ubicación en Ajustes del proyecto (ciudad o «lat,lon»).</span><?php endif; ?></p>
    <div class="cp-fila" style="gap:1rem;flex-wrap:wrap">
        <form method="POST" action="/integraciones/clima"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="accion" value="<?php echo $tg['clima'] ? 'desactivar' : 'activar'; ?>">
            <button class="btn btn-sm <?php echo $tg['clima'] ? 'btn-ghost' : 'btn-primary'; ?>" type="submit"><?php echo $tg['clima'] ? 'Desactivar clima automático' : 'Activar clima automático'; ?></button></form>
        <form method="POST" action="/integraciones/clima"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="accion" value="probar"><button class="btn btn-sm btn-ghost" type="submit" title="Anota el clima de hoy y avisa del de mañana si es adverso">Probar ahora</button></form>
    </div>
    <?php endif; ?>
</section>

<section class="card integ-guia" id="guia">
    <h3><i class="fa-solid fa-book-open"></i> Guía completa de integraciones</h3>
    <p class="text-muted">Qué es cada cosa, cómo se conecta y ejemplos listos para copiar. Abre la parte que necesites.</p>

    <details open><summary>1. Qué es y para qué sirve</summary>
        <p><strong>API REST (la otra herramienta pregunta):</strong> un programa externo —Power BI, Excel, un script, Zapier/Make, tu propio sistema— pide datos a ProjectWeb (<em>«dame las actividades atrasadas del proyecto 14»</em>) o le manda datos (<em>«marca la actividad 388 al 60 %»</em>). Tú decides cuándo; ProjectWeb solo responde.</p>
        <p>Para los avisos al equipo está el <strong>bot de Telegram</strong> y para que el día quede registrado solo, el <strong>clima automático</strong> (las dos secciones siguientes).</p>
        <p>Ejemplos típicos: un tablero de Power BI con el avance de todas tus obras · registrar en ProjectWeb el avance que reporta el sistema de otro contratista · respaldar los datos en tu propio servidor.</p>
    </details>

    <details><summary>2. Empezar en 5 minutos (API)</summary>
        <ol>
            <li>Arriba, en <strong>Tokens de la API</strong>, escribe para qué lo usarás (ej. «Power BI»), elige el alcance y pulsa <em>Crear token</em>.</li>
            <li><strong>Copia el token en ese momento</strong>: empieza con <code>pw_</code> y no se vuelve a mostrar (ProjectWeb solo guarda una huella). Si lo pierdes, revoca ese y crea otro.</li>
            <li>Pruébalo en una terminal (cambia <code>pw_…</code> por tu token):
<pre class="integ-codigo">curl -H "Authorization: Bearer pw_…" <?php echo s($url); ?>/api/v1/proyectos</pre>
            Si responde con una lista de tus proyectos, ya funciona. Cada proyecto trae su <code>id</code>, que usarás en las demás rutas.</li>
        </ol>
        <p><strong>Qué alcance elegir:</strong> <em>solo lectura</em> para tableros y reportes (lo más seguro); <em>puede escribir</em> solo si la otra herramienta debe cambiar datos. Si solo necesita una obra, límítalo a ese <em>proyecto</em>. Ponle <em>vigencia</em> a los que sean temporales.</p>
        <p><strong>Tu token tiene tus mismos permisos:</strong> ve y cambia lo mismo que tú en la web (un cliente solo sus proyectos; un visitante nunca escribe). Trátalo como una contraseña: no lo pegues en chats, repositorios ni páginas web públicas.</p>
    </details>

    <details><summary>3. Cómo se hace una petición</summary>
        <ul>
            <li><strong>Dirección base:</strong> <code><?php echo s($url); ?>/api/v1</code></li>
            <li><strong>Autenticación:</strong> cabecera <code>Authorization: Bearer pw_…</code> en <em>todas</em> las peticiones (también se acepta <code>X-API-Key: pw_…</code>).</li>
            <li><strong>Formato:</strong> se responde siempre JSON (UTF-8). Para enviar datos usa <code>Content-Type: application/json</code> con el cuerpo en JSON (o un formulario normal).</li>
            <li><strong>Métodos:</strong> <code>GET</code> lee, <code>POST</code> crea, <code>PATCH</code> cambia algunos campos de algo que ya existe.</li>
            <li><strong>Listas paginadas:</strong> añade <code>?pagina=2&amp;por_pagina=100</code> (máximo 200). La respuesta trae <code>meta</code> con <code>pagina</code>, <code>por_pagina</code>, <code>total</code> y <code>paginas</code> para saber cuántas faltan.</li>
            <li><strong>Forma de la respuesta:</strong>
<pre class="integ-codigo">{ "data": [ … o { … } ], "meta": { "pagina": 1, "por_pagina": 50, "total": 52, "paginas": 2 } }</pre></li>
            <li><strong>Fechas</strong> en <code>AAAA-MM-DD</code>; avance en número de 0 a 100.</li>
        </ul>
    </details>

    <details><summary>4. Todo lo que puedes hacer (rutas)</summary>
        <p class="text-sm text-muted">Sustituye <code>{id}</code> por el número del proyecto, la actividad, el pendiente o el RFI. «Escribe» significa que necesita un token con <em>Puede escribir</em>.</p>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Método</th><th>Ruta</th><th>Qué hace</th></tr></thead>
            <tbody><?php foreach (\Classes\ApiRest::catalogo() as $r): ?>
                <tr><td class="mono"><strong><?php echo s($r['metodo']); ?></strong></td><td class="mono text-sm"><?php echo s($r['ruta']); ?></td><td><?php echo s($r['resumen']); ?><?php echo $r['escribe'] ? ' <span class="cb-tag">Escribe</span>' : ''; ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <p><strong>Filtros útiles:</strong> actividades → <code>status</code> (no_iniciada, en_proceso, completada), <code>partida</code> (id), <code>desde</code> / <code>hasta</code> (fechas), <code>atrasadas=1</code> · bitácora → <code>importancia</code> (baja, media, alta, critica), <code>desde</code>, <code>hasta</code> · punch → <code>estado</code> (abierto, en_proceso, corregido, cerrado, rechazado o «pendientes»), <code>severidad</code> · RFI → <code>estado</code> (abierto, respondido, cerrado).</p>
    </details>

    <details><summary>5. Ejemplos listos para copiar</summary>
        <h4>Leer: actividades atrasadas de un proyecto</h4>
<pre class="integ-codigo">curl -H "Authorization: Bearer pw_…" \
  "<?php echo s($url); ?>/api/v1/proyectos/14/actividades?atrasadas=1&amp;por_pagina=100"</pre>
        <h4>Escribir: poner una actividad al 60 % y asignarle responsable</h4>
<pre class="integ-codigo">curl -X PATCH -H "Authorization: Bearer pw_…" -H "Content-Type: application/json" \
  -d '{"progreso": 60, "responsable": "Ing. Pérez"}' \
  <?php echo s($url); ?>/api/v1/actividades/388</pre>
        <p class="text-sm">Campos que acepta: <code>progreso</code> (0-100; el estatus se ajusta solo), <code>prioridad</code>, <code>pta</code> (true/false), <code>responsable</code>, <code>rec_inicio</code>, <code>rec_fin</code>, <code>libre_texto</code>, <code>libre_numero</code>, <code>libre_fecha</code>.</p>
        <h4>Escribir: anotar en la bitácora y abrir un RFI</h4>
<pre class="integ-codigo">curl -X POST -H "Authorization: Bearer pw_…" -H "Content-Type: application/json" \
  -d '{"descripcion": "Llegó el cable UTP", "importancia": "media"}' \
  <?php echo s($url); ?>/api/v1/proyectos/14/bitacora

curl -X POST -H "Authorization: Bearer pw_…" -H "Content-Type: application/json" \
  -d '{"asunto": "Diámetro de bajada pluvial", "pregunta": "¿4 o 6 pulgadas?", "prioridad": "alta"}' \
  <?php echo s($url); ?>/api/v1/proyectos/14/rfis</pre>
        <h4>Python</h4>
<pre class="integ-codigo">import requests
H = {"Authorization": "Bearer pw_…"}
B = "<?php echo s($url); ?>/api/v1"
for p in requests.get(f"{B}/proyectos", headers=H).json()["data"]:
    r = requests.get(f"{B}/proyectos/{p['id']}", headers=H).json()["data"]
    print(p["nombre"], r["progreso"], "% ·", r["actividades"]["atrasadas"], "atrasadas")</pre>
        <h4>JavaScript</h4>
<pre class="integ-codigo">const r = await fetch("<?php echo s($url); ?>/api/v1/proyectos/14/curva-s", { headers: { Authorization: "Bearer pw_…" } });
const { data } = await r.json();</pre>
        <h4>Power BI / Excel</h4>
        <p class="text-sm"><em>Power BI:</em> Obtener datos → Web → Avanzado; en «Parámetros de encabezado HTTP» agrega <code>Authorization</code> = <code>Bearer pw_…</code> y pon la URL de la ruta (por ejemplo <code>/api/v1/proyectos/14/actividades?por_pagina=200</code>); luego expande la columna <code>data</code>. <em>Excel:</em> Datos → Obtener datos → Desde la web → Avanzado, con el mismo encabezado. Usa un token <strong>de solo lectura</strong>.</p>
        <h4>Postman / Swagger</h4>
        <p class="text-sm">Importa <a href="/api/v1/openapi"><code>/api/v1/openapi</code></a> (OpenAPI 3) y pega tu token en «Authorization → Bearer Token».</p>
    </details>

    <details><summary>6. Errores comunes</summary>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Código</th><th>Qué significa</th><th>Qué hacer</th></tr></thead>
            <tbody>
                <tr><td class="mono">401</td><td>Falta el token, es inválido, está revocado o caducó.</td><td>Revisa la cabecera <code>Authorization: Bearer pw_…</code>; crea otro token si hace falta.</td></tr>
                <tr><td class="mono">403</td><td>El token es de solo lectura, o tu rol no puede modificar, o no puedes gestionar ese proyecto.</td><td>Crea un token con «Puede escribir» o usa una cuenta con permiso.</td></tr>
                <tr><td class="mono">404</td><td>La ruta no existe, o el proyecto/registro no existe o no tienes acceso (no se distingue a propósito).</td><td>Verifica el <code>id</code> con <code>/api/v1/proyectos</code>.</td></tr>
                <tr><td class="mono">405</td><td>Método no permitido en esa ruta.</td><td>Mira el método correcto en la tabla de rutas.</td></tr>
                <tr><td class="mono">422</td><td>Datos no válidos (falta un campo, fecha mal escrita, valor fuera de lista).</td><td>El <code>mensaje</code> dice cuál campo corregir.</td></tr>
                <tr><td class="mono">429</td><td>Pasaste de 120 peticiones por minuto con ese token.</td><td>Espera 60 s (cabecera <code>Retry-After</code>) y pide menos veces o más datos por página.</td></tr>
            </tbody>
        </table></div>
        <p>Los errores siempre llegan así: <code>{"error": {"codigo": "datos_invalidos", "mensaje": "La descripción es obligatoria"}}</code></p>
    </details>

    <details><summary>7. Seguridad y buenas prácticas</summary>
        <ul>
            <li>Un token por herramienta, con el mínimo alcance (solo lectura, un proyecto, con vigencia). Si se filtra, <strong>revócalo</strong> aquí y listo: deja de funcionar al instante.</li>
            <li>No uses un token en una página web o app móvil que otros puedan inspeccionar: ponlo en un servidor tuyo.</li>
        </ul>
    </details>

    <details><summary>8. Preguntas frecuentes</summary>
        <p><strong>¿Cuesta algo o hay que instalar algo?</strong> No: viene incluido. Solo necesitas que el servidor sea alcanzable desde la otra herramienta.</p>
        <p><strong>¿Puedo traer datos de varios proyectos?</strong> Sí: <code>/api/v1/proyectos</code> lista todos los que ves y pides cada uno por su <code>id</code>, salvo que el token esté ligado a un solo proyecto.</p>
        <p><strong>¿Puedo borrar datos con la API?</strong> No: por seguridad solo hay lectura, altas y cambios puntuales (avance, estados). Borrar se hace desde la web.</p>
        <p><strong>¿Veo en la web lo que cambió la API?</strong> Sí, al instante: es la misma información.</p>
    </details>
</section>

