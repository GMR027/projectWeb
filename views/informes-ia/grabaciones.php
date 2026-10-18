<?php // Variables: $proyecto, $lista, $mensaje
use Classes\Almacenamiento;
use Classes\Grabaciones;
$pid = (int)$proyecto->id;
?>
<?php $pestana = 'grabaciones'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-microphone"></i> <?php echo s(t('Grabaciones de reuniones')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · graba una reunión aquí mismo o súbela desde un archivo (también desde la app de escritorio); se guarda en el proyecto (cuentan para su almacenamiento: <?php echo s(Almacenamiento::formato(Grabaciones::bytes($pid))); ?> usados en grabaciones). Solo el administrador puede escucharlos.</p>
    </div>
</div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo !empty($mensaje['ok']) ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>
<div class="card prg-seccion" id="rec">
    <h3>Grabar una reunión nueva</h3>
    <div class="inc-filtros">
        <label style="flex:1">Nombre <input type="text" id="rec-nombre" maxlength="200" value="<?php echo s('Reunión ' . date('d/m/Y H:i')); ?>"></label>
        <button type="button" class="btn btn-primary" id="rec-iniciar"><i class="fa-solid fa-circle"></i> Grabar</button>
        <button type="button" class="btn btn-ghost" id="rec-pausa" hidden><i class="fa-solid fa-pause"></i> Pausar</button>
        <button type="button" class="btn btn-ghost" id="rec-detener" hidden><i class="fa-solid fa-stop"></i> Detener</button>
        <strong id="rec-tiempo" class="mono" hidden>0:00</strong>
    </div>
    <div id="rec-revisar" hidden>
        <audio controls id="rec-audio" style="width:100%;margin:.8rem 0"></audio>
        <label>Transcripción (opcional) <textarea id="rec-texto" rows="3" style="width:100%" placeholder="Pega aquí el texto si ya lo tienes; la app de escritorio puede transcribirlo después."></textarea></label>
        <div class="inc-filtros"><button type="button" class="btn btn-primary" id="rec-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar grabación</button><button type="button" class="btn btn-ghost" id="rec-descartar">Descartar</button></div>
    </div>
    <p class="text-muted text-sm" id="rec-estado">Avisa a los asistentes que se está grabando. El navegador te pedirá permiso para usar el micrófono (requiere HTTPS o localhost).</p>
    <hr>
    <form method="post" action="/api/ia/grabacion" enctype="multipart/form-data" id="rec-subir" class="inc-filtros">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="volver" value="1">
        <label>O sube un audio existente <input type="file" name="audio" accept="audio/*,.webm,.ogg,.wav,.mp3,.m4a" required></label>
        <label>Nombre <input type="text" name="nombre" maxlength="200" placeholder="Reunión…"></label>
        <button class="btn btn-ghost" type="submit"><i class="fa-solid fa-upload"></i> Subir</button>
    </form>
</div>
<script>
(function () {
    var pid = <?php echo $pid; ?>, $ = function (i) { return document.getElementById(i); };
    var rec, trozos = [], flujo, blob, t0, acum = 0, reloj, pausado = false;
    var fmt = function (s) { return Math.floor(s / 60) + ':' + ('0' + Math.floor(s % 60)).slice(-2); };
    var estado = function (t) { $('rec-estado').textContent = t; };
    var seg = function () { return Math.round((acum + (pausado ? 0 : Date.now() - t0)) / 1000); };
    function ui(grabando) {
        $('rec-iniciar').hidden = grabando; $('rec-pausa').hidden = !grabando; $('rec-detener').hidden = !grabando; $('rec-tiempo').hidden = !grabando;
    }
    $('rec-iniciar').onclick = function () {
        if (!navigator.mediaDevices || !window.MediaRecorder) { estado('Este navegador no permite grabar. Usa uno reciente con HTTPS o sube un archivo.'); return; }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (f) {
            flujo = f; trozos = []; acum = 0; pausado = false;
            rec = new MediaRecorder(f);
            rec.ondataavailable = function (e) { if (e.data && e.data.size) trozos.push(e.data); };
            rec.onstop = function () {
                flujo.getTracks().forEach(function (t) { t.stop(); });
                clearInterval(reloj);
                blob = new Blob(trozos, { type: rec.mimeType || 'audio/webm' });
                $('rec-audio').src = URL.createObjectURL(blob);
                $('rec-revisar').hidden = false; ui(false);
                estado('Revisa la grabación (' + fmt(acum / 1000) + ') y guárdala.');
            };
            rec.start(1000); t0 = Date.now(); ui(true); $('rec-revisar').hidden = true; estado('Grabando…');
            reloj = setInterval(function () { $('rec-tiempo').textContent = fmt(seg()); }, 500);
        }).catch(function () { estado('No se pudo usar el micrófono: revisa el permiso del navegador.'); });
    };
    $('rec-pausa').onclick = function () {
        if (!rec) return;
        if (pausado) { rec.resume(); t0 = Date.now(); pausado = false; this.innerHTML = '<i class="fa-solid fa-pause"></i> Pausar'; estado('Grabando…'); }
        else { rec.pause(); acum += Date.now() - t0; pausado = true; this.innerHTML = '<i class="fa-solid fa-play"></i> Continuar'; estado('En pausa.'); }
    };
    $('rec-detener').onclick = function () { if (!rec || rec.state === 'inactive') return; if (!pausado) acum += Date.now() - t0; rec.stop(); };
    $('rec-descartar').onclick = function () { blob = null; $('rec-revisar').hidden = true; estado('Grabación descartada.'); };
    $('rec-guardar').onclick = function () {
        if (!blob) return;
        var fd = new FormData(), b = this;
        fd.append('proyecto', pid); fd.append('nombre', $('rec-nombre').value); fd.append('duracion', Math.round(acum / 1000));
        if ($('rec-texto').value.trim()) fd.append('transcripcion', $('rec-texto').value);
        fd.append('audio', blob, 'reunion.' + (blob.type.indexOf('mp4') > -1 ? 'm4a' : blob.type.indexOf('ogg') > -1 ? 'ogg' : 'webm'));
        b.disabled = true; estado('Guardando…');
        fetch('/api/ia/grabacion', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (j) {
            if (j.success) location.reload(); else { b.disabled = false; estado(j.error || 'No se pudo guardar.'); }
        }).catch(function () { b.disabled = false; estado('Error de conexión al guardar.'); });
    };
    window.addEventListener('beforeunload', function (e) { if (rec && rec.state !== 'inactive') { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<?php if (!$lista): ?>
    <div class="card prg-vacio"><h3>Aún no hay grabaciones</h3><p class="text-muted">Pulsa «Grabar» arriba para registrar una reunión, o súbela desde un archivo.</p></div>
<?php else: ?>
    <ul class="inc-lista">
        <?php foreach ($lista as $g): ?>
            <li class="card">
                <div class="inc-cab"><strong><?php echo s($g['nombre'] ?: 'Grabación #' . $g['id']); ?></strong>
                    <small class="text-muted"><?php echo s(date('d/m/Y H:i', strtotime($g['creado']))); ?> · <?php echo s(Grabaciones::duracion((int)$g['duracion_seg'])); ?> · <?php echo s(Almacenamiento::formato((int)$g['tamano'])); ?> · <?php echo s($g['creado_por']); ?></small></div>
                <audio controls preload="none" src="/grabaciones/audio?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$g['id']; ?>" style="width:100%;margin:.8rem 0"></audio>
                <?php if ($g['con_texto']): $t = Grabaciones::texto($pid, (int)$g['id']); ?>
                    <details><summary>Transcripción (<?php echo mb_strlen($t); ?> caracteres)</summary><p style="white-space:pre-wrap;font-size:1.3rem"><?php echo s($t); ?></p></details>
                <?php endif; ?>
                <form method="post" action="/grabaciones/eliminar" onsubmit="return confirm('¿Eliminar esta grabación y su audio? No se puede deshacer.')" style="margin-top:.8rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button></form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
