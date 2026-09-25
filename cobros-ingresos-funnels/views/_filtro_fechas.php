<?php
/** @var string $desde */
/** @var string $hasta */
/** @var bool $personalizado */
?>
<label for="desde">Desde</label>
<input type="date" name="desde" id="desde" value="<?= $personalizado ? htmlspecialchars($desde) : '' ?>">
<label for="hasta">Hasta</label>
<input type="date" name="hasta" id="hasta" value="<?= $personalizado ? htmlspecialchars($hasta) : '' ?>">
