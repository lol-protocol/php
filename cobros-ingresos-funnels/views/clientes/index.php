<?php

/** @var array $clientes */
/** @var string $q */
?>

<h1>Clientes</h1>
<p class="subtitulo">Buscá un cliente por nombre o email para ver su ficha.</p>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="clientes">
    <label for="q">Buscar</label>
    <input type="search" name="q" id="q" placeholder="Nombre o email..." value="<?= htmlspecialchars($q) ?>">
    <button type="submit">Buscar</button>
    <a href="?page=cliente-nuevo" style="margin-left:auto;">+ Nuevo cliente</a>
</form>

<div class="panel">
    <h2>Resultado (<?= count($clientes) ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Nombre</th><th>Email</th><th>Pais</th><th>Segmento</th><th>Cliente desde</th></tr>
            </thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><a href="?page=cliente&id=<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></a></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= htmlspecialchars($c['pais_nombre']) ?></td>
                    <td><?= htmlspecialchars($c['segmento']) ?></td>
                    <td><?= htmlspecialchars($c['fecha_alta']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$clientes): ?>
                <tr><td colspan="5">No se encontraron clientes.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
