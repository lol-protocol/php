<?php

use App\FiltroDePeriodo;

/**
 * El periodo y el rango exacto de las pantallas con filtro de fechas
 * (Dashboard, Cobros, Pagos, Funnel y Cohortes): un solo bloque, para que
 * ofrezcan las mismas opciones y se comporten igual. Desde/Hasta muestran lo
 * que el usuario tipeo aunque se haya ignorado, asi se corrige en vez de
 * volver a escribirlo; el motivo lo explica el aviso de views/_avisos.php.
 *
 * @var int $meses
 * @var string $desdeIngresado
 * @var string $hastaIngresado
 */
?>
<label for="meses">Período</label>
<select name="meses" id="meses">
    <?php foreach (FiltroDePeriodo::OPCIONES_MESES as $valor => $texto): ?>
        <option value="<?= $valor ?>" <?= $meses === $valor ? 'selected' : '' ?>><?= $texto ?></option>
    <?php endforeach; ?>
</select>
<label for="desde">Desde</label>
<input type="date" name="desde" id="desde" value="<?= htmlspecialchars($desdeIngresado) ?>">
<label for="hasta">Hasta</label>
<input type="date" name="hasta" id="hasta" value="<?= htmlspecialchars($hastaIngresado) ?>">
