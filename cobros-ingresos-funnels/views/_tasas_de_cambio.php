<?php

use App\Config;
use App\Repositories\MonedaRepository;

/** @var array{ultima: ?DateTimeImmutable, fuente: ?string, pendientes: list<string>} $tasas */
?>
<?php if ($tasas['ultima'] === null): ?>
    <div class="tasas-aviso" role="status">Las tasas de cambio son de ejemplo, no reales: las cifras en USD de esta pantalla son indicativas. Para cargar las reales: <code>php database/actualizar_tasas.php</code> (ver el README).</div>
<?php elseif ($tasas['pendientes'] !== []): ?>
    <div class="tasas-aviso" role="status">Estas monedas tienen movimientos y su tasa de cambio es de ejemplo o lleva más de <?= MonedaRepository::DIAS_DE_VIGENCIA ?> días sin actualizarse: <?= htmlspecialchars(implode(', ', $tasas['pendientes'])) ?>. Las cifras en USD de esas monedas no son reales.</div>
<?php else: ?>
    <?php $dia = $tasas['ultima']->setTimezone(new DateTimeZone(Config::zonaHoraria())); ?>
    <p class="subtitulo">Cifras en USD: cada boleta, pago y nota de crédito a la tasa de cambio de su día; la cartera pendiente, a la última cotización (<?= $dia->format('j') ?> <?= mes_label($dia->format('Y-m')) ?><?= $tasas['fuente'] !== null ? ', ' . htmlspecialchars($tasas['fuente']) : '' ?>).</p>
<?php endif; ?>
