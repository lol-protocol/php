<?php /* Expects $dispersion: rows with lugar_ruta, lugar_nombre, total. */ ?>
<?php if ($dispersion === []): ?>
<p class="vacio">Sin lugares de nacimiento registrados.</p>
<?php else: ?>
<?php $totalNacidos = array_sum(array_map('intval', array_column($dispersion, 'total'))); ?>
<table>
    <thead><tr><th>Lugar de nacimiento</th><th class="num">Personas</th><th class="num">%</th></tr></thead>
    <tbody>
<?php foreach ($dispersion as $d): ?>
        <tr>
            <td><a href="<?= esc(enlaceLugar(explode('/', $d['lugar_ruta']))) ?>"><?= esc($d['lugar_nombre']) ?></a> <span class="meta"><?= esc($d['lugar_ruta']) ?></span></td>
            <td class="num"><?= (int)$d['total'] ?></td>
            <td class="num"><?= esc(number_format(100 * (int)$d['total'] / $totalNacidos, 1)) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
