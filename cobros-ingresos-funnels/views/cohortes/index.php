<?php

/** @var array $cohortes */
/** @var int $meses */
?>

<h1>Cohortes de conversion</h1>
<p class="subtitulo">De cada cohorte (mes de primera visita), que porcentaje convirtio a cliente dentro de 0, 1, 2 o 3 meses desde su llegada.</p>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="cohortes">
    <label for="meses">Periodo</label>
    <select name="meses" id="meses">
        <option value="3" <?= $meses === 3 ? 'selected' : '' ?>>Ultimos 3 meses</option>
        <option value="6" <?= $meses === 6 ? 'selected' : '' ?>>Ultimos 6 meses</option>
        <option value="12" <?= $meses === 12 ? 'selected' : '' ?>>Ultimos 12 meses</option>
    </select>
    <button type="submit">Aplicar</button>
</form>

<div class="panel">
    <h2>Conversion acumulada por cohorte</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Cohorte</th><th class="num">Visitantes</th>
                <th class="num">Mismo mes</th><th class="num">+1 mes</th><th class="num">+2 meses</th><th class="num">+3 meses</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($cohortes as $fila): ?>
                <?php $total = (int) $fila['total']; ?>
                <tr>
                    <td><?= mes_label($fila['cohorte']) ?></td>
                    <td class="num"><?= $total ?></td>
                    <?php foreach (['m0', 'm1', 'm2', 'm3'] as $col): ?>
                        <?php
                        $valor = (int) $fila[$col];
                        $pct = $total > 0 ? $valor / $total * 100 : 0.0;
                        [$fondo, $texto] = color_celda_cohorte($pct);
                        ?>
                        <td class="num cohorte-celda" style="background: <?= $fondo ?>; color: <?= $texto ?>;">
                            <?= $valor ?> (<?= number_format($pct, 0) ?>%)
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cohortes): ?>
                <tr><td colspan="6">Sin datos para este periodo.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="subtitulo" style="margin-top:14px;">"+1 mes", por ejemplo, es el % que ya habia convertido a mas tardar un mes despues de su primera visita (acumulado, no solo ese mes).</p>
</div>
