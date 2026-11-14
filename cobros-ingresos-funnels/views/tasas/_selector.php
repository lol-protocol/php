<?php

/** @var array<string, string> $monedas */
/** @var string $moneda */
?>
<form class="filtros" method="get">
    <input type="hidden" name="page" value="tasas">
    <label for="moneda">Moneda</label>
    <select name="moneda" id="moneda">
        <option value="">Todas (resumen)</option>
        <?php foreach ($monedas as $codigo => $texto): ?>
            <option value="<?= e($codigo) ?>" <?= $moneda === $codigo ? 'selected' : '' ?>><?= e($texto) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Ver</button>
</form>
