<?php

use App\Csrf;
use App\EnvioUnico;
use App\MayoriaDeEdad;
use App\Validacion;

/** @var array $paises */
/** @var list<string> $idiomas */
/** @var array $generos */
/** @var array $segmentos */
/** @var string $nacimientoMasReciente la fecha de nacimiento mas reciente que todavia es de un mayor de edad */
/** @var string|null $error */
?>

<h1>Nuevo cliente</h1>
<p class="subtitulo"><a href="?page=clientes">&larr; Volver a Clientes</a></p>

<div class="panel">
    <form class="form-alta" method="post">
        <?= Csrf::campo() ?>
        <?= EnvioUnico::campo() ?>
        <?php include __DIR__ . '/../_error.php'; ?>

        <label for="nombre">Nombre</label>
        <input type="text" name="nombre" id="nombre" required maxlength="<?= Validacion::MAX_NOMBRE ?>" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">

        <label for="email">Email</label>
        <input type="email" name="email" id="email" required maxlength="<?= Validacion::MAX_EMAIL ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label for="pais_codigo">País</label>
        <select name="pais_codigo" id="pais_codigo" required>
            <option value="">Seleccioná un país...</option>
            <?php foreach ($paises as $p): ?>
                <option value="<?= $p['codigo'] ?>" <?= ($_POST['pais_codigo'] ?? '') === $p['codigo'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nombre']) ?> (<?= $p['moneda_codigo'] ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="ciudad">Ciudad</label>
        <input type="text" name="ciudad" id="ciudad" required maxlength="<?= Validacion::MAX_CIUDAD ?>" value="<?= htmlspecialchars($_POST['ciudad'] ?? '') ?>">

        <label for="idioma">Idioma</label>
        <input type="text" name="idioma" id="idioma" list="idiomas" maxlength="<?= Validacion::MAX_IDIOMA ?>" value="<?= htmlspecialchars($_POST['idioma'] ?? 'Espanol') ?>">
        <datalist id="idiomas">
            <?php foreach ($idiomas as $idioma): ?>
                <option value="<?= htmlspecialchars($idioma) ?>">
            <?php endforeach; ?>
        </datalist>

        <label for="genero">Género</label>
        <select name="genero" id="genero">
            <?php foreach ($generos as $g): ?>
                <option value="<?= $g ?>" <?= ($_POST['genero'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>

        <label for="fecha_nacimiento">Fecha de nacimiento</label>
        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required max="<?= $nacimientoMasReciente ?>" value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">
        <p class="nota">Solo se admiten clientes mayores de edad (<?= MayoriaDeEdad::EDAD ?> años cumplidos).</p>

        <label for="segmento">Segmento</label>
        <select name="segmento" id="segmento">
            <?php foreach ($segmentos as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['segmento'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Crear cliente</button>
    </form>
</div>
