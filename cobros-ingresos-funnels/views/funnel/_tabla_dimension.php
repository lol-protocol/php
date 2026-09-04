<?php
/** @var string $titulo */
/** @var array $filas */
?>
<div class="panel">
    <h2><?= htmlspecialchars($titulo) ?></h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>&nbsp;</th><th class="num">Visitantes</th><th class="num">Registrados</th><th class="num">Leads</th><th class="num">Clientes</th><th class="num">Conversion</th></tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): $tasa = $f['visitantes'] > 0 ? $f['clientes'] / $f['visitantes'] * 100 : 0; ?>
                <tr>
                    <td><?= htmlspecialchars($f['etiqueta']) ?></td>
                    <td class="num"><?= $f['visitantes'] ?></td>
                    <td class="num"><?= $f['registrados'] ?></td>
                    <td class="num"><?= $f['leads'] ?></td>
                    <td class="num"><?= $f['clientes'] ?></td>
                    <td class="num"><?= number_format($tasa, 1) ?>%</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$filas): ?>
                <tr><td colspan="6">Sin datos para este periodo.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
