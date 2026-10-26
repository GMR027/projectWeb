<?php // Variables: $proyecto, $doc, $fragmentos, $q, $puede
use Classes\Normativa;
$pid = (int)$proyecto->id;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-scale-balanced"></i> <?php echo s($doc['titulo']); ?></h2>
        <p class="text-muted"><?php echo s(Normativa::TIPOS[$doc['tipo']]); ?><?php echo $doc['codigo'] !== '' ? ' · ' . s($doc['codigo']) : ''; ?> · <?php echo s(Normativa::IDIOMAS[$doc['idioma']]); ?> · <?php echo (int)$doc['paginas']; ?> páginas · <?php echo (int)$doc['fragmentos']; ?> cláusulas</p>
    </div>
    <div class="inc-acciones">
        <a class="btn btn-ghost" href="/normativa/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$doc['id']; ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> Abrir PDF</a>
        <a href="/normativa?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Documentos</a>
    </div>
</div>
<form method="get" action="/normativa/documento" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$doc['id']; ?>">
    <label style="flex:1">Buscar en este documento <input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Palabra o frase"></label><button class="btn btn-ghost" type="submit">Buscar</button>
</form>
<?php if (!$fragmentos): ?><div class="card prg-vacio"><h3>Sin cláusulas</h3><p class="text-muted"><?php echo s($doc['nota'] ?: 'No hay cláusulas que mostrar.'); ?></p></div>
<?php else: ?>
    <ul class="inc-lista"><?php foreach ($fragmentos as $f): ?>
        <li class="card inc-item sev-baja"><div class="inc-cab"><strong><?php echo s(($f['clausula'] !== '' ? $f['clausula'] . ' ' : '') . $f['titulo']); ?></strong><small class="text-muted">pág. <?php echo (int)$f['pagina']; ?> · <?php echo strtoupper(s($f['idioma'])); ?><?php echo \Classes\Normativa::seccionCsi($f['csi']) !== '' ? ' · ' . s(\Classes\Normativa::seccionCsi($f['csi'])) : ''; ?></small></div>
            <p><?php echo s($f['texto']); ?></p></li>
    <?php endforeach; ?></ul>
<?php endif; ?>
