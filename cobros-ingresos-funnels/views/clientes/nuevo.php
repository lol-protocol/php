<?php

/** @var array $paises */
/** @var string|null $error */

$generos = ['Femenino', 'Masculino', 'No especifica'];
$segmentos = ['general', 'starter', 'pro', 'enterprise'];
?>

<h1>Nuevo cliente</h1>
<p class="subtitulo"><a href="?page=clientes">&larr; Volver a Clientes</a></p>

<div class="panel">
    <form class="form-alta" method="post">
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <label for="nombre">Nombre</label>
        <input type="text" name="nombre" id="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">

        <label for="email">Email</label>
        <input type="email" name="email" id="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label for="pais_codigo">Pais</label>
        <select name="pais_codigo" id="pais_codigo" required>
            <option value="">Seleccioná un pais...</option>
            <?php foreach ($paises as $p): ?>
                <option value="<?= $p['codigo'] ?>" <?= ($_POST['pais_codigo'] ?? '') === $p['codigo'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nombre']) ?> (<?= $p['moneda_codigo'] ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="ciudad">Ciudad</label>
        <input type="text" name="ciudad" id="ciudad" required value="<?= htmlspecialchars($_POST['ciudad'] ?? '') ?>">

        <label for="idioma">Idioma</label>
        <input type="text" name="idioma" id="idioma" value="<?= htmlspecialchars($_POST['idioma'] ?? 'Espanol') ?>">

        <label for="genero">Genero</label>
        <select name="genero" id="genero">
            <?php foreach ($generos as $g): ?>
                <option value="<?= $g ?>"><?= $g ?></option>
            <?php endforeach; ?>
        </select>

        <label for="fecha_nacimiento">Fecha de nacimiento</label>
        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">

        <label for="segmento">Segmento</label>
        <select name="segmento" id="segmento">
            <?php foreach ($segmentos as $s): ?>
                <option value="<?= $s ?>"><?= $s ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Crear cliente</button>
    </form>
</div>
