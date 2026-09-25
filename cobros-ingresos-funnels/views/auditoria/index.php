<?php

/** @var array $registros */
/** @var int $totalRegistros */
/** @var int $totalPaginas */
/** @var int $pagina */

$accionLabel = ['crear' => 'Creó', 'editar' => 'Editó', 'anular' => 'Anuló', 'activar' => 'Reactivó'];
$entidadLabel = ['boleta' => 'Boleta', 'pago' => 'Pago', 'cliente' => 'Cliente', 'usuario' => 'Usuario'];
?>

<h1>Auditoría</h1>
<p class="subtitulo">Quién hizo qué: <?= $totalRegistros ?> altas, ediciones y anulaciones en total.</p>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th></tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(substr($r['creado_en'], 0, 16)) ?></td>
                    <td><?= htmlspecialchars($r['usuario']) ?></td>
                    <td>
                        <?= $accionLabel[$r['accion']] ?? htmlspecialchars($r['accion']) ?>
                        <?= $entidadLabel[$r['entidad']] ?? htmlspecialchars($r['entidad']) ?>
                    </td>
                    <td><?= htmlspecialchars($r['detalle']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$registros): ?>
                <tr><td colspan="4">Todavia no hay movimientos registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../_paginacion.php'; ?>
</div>
