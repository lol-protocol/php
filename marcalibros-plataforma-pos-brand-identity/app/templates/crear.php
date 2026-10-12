<?php
/**
 * @var array<string, string> $landscapes  nombre de archivo (1920x1080) => nombre para mostrar
 * @var array<string, string> $values
 * @var string|null $error
 * @var int $maxMegabytes
 * @var int $maxBytes
 * @var int $minCropWidth
 * @var float $maxZoom
 * @var int $labelLength
 * @var int $ratio
 */
$source = $values['fuente'] === 'paisaje' ? 'paisaje' : 'subida';
?>
<div class="envoltura envoltura--estrecha crear">
  <h1>Crea tu marcalibro</h1>
  <p class="lead">Sube una imagen o elige un paisaje, mueve el marco hasta encuadrar lo que quieres y descarga tu marcalibro en dos tamaños. No hace falta cuenta.</p>

<?php if ($error !== null): ?>
  <p class="alerta" role="alert" id="error-servidor"><?= $this->e($error) ?></p>
<?php endif ?>

  <form class="formulario" method="post" action="/crear" enctype="multipart/form-data" novalidate
        data-ratio="<?= (int) $ratio ?>" data-max-bytes="<?= (int) $maxBytes ?>" data-max-mb="<?= (int) $maxMegabytes ?>"
        data-min-width="<?= (int) $minCropWidth ?>" data-max-zoom="<?= $this->e((string) $maxZoom) ?>">

    <section class="paso" aria-labelledby="paso-1">
      <h2 id="paso-1"><span class="paso__numero" aria-hidden="true">1</span>Elige la imagen</h2>

      <div class="fuentes" role="radiogroup" aria-labelledby="paso-1">
        <label class="fuente"><input type="radio" name="fuente" value="subida"<?= $source === 'subida' ? ' checked' : '' ?>> <span>Subir una imagen</span></label>
        <label class="fuente"><input type="radio" name="fuente" value="paisaje"<?= $source === 'paisaje' ? ' checked' : '' ?>> <span>Usar un paisaje</span></label>
      </div>

      <div class="opcion" data-opcion="subida">
        <label for="imagen">Imagen (JPG, PNG o WebP, hasta <?= (int) $maxMegabytes ?> MB)</label>
        <input id="imagen" class="archivo" type="file" name="imagen" accept="image/jpeg,image/png,image/webp" aria-describedby="ayuda-imagen">
        <p id="ayuda-imagen" class="ayuda">Mejor si es horizontal y mide al menos <?= (int) $minCropWidth ?> px de ancho. La imagen se procesa en el servidor y no se comparte.<?= $error !== null && $source === 'subida' ? ' Vuelve a elegirla: el navegador no deja que la página conserve el archivo.' : '' ?></p>
      </div>

      <fieldset class="opcion" data-opcion="paisaje">
        <legend>Paisaje</legend>
        <div class="paisajes">
<?php foreach ($landscapes as $file => $name): ?>
<?php $thumb = str_replace('1920x1080', '1200x630', $file); ?>
          <label class="paisaje">
            <input type="radio" name="paisaje" value="<?= $this->e($file) ?>"<?= $values['paisaje'] === $file ? ' checked' : '' ?>>
            <img src="/paisajes/<?= $this->e($thumb) ?>" width="1200" height="630" alt="" loading="lazy" data-full="/paisajes/<?= $this->e($file) ?>">
            <span><?= $this->e($name) ?></span>
          </label>
<?php endforeach ?>
        </div>
      </fieldset>

      <p class="alerta alerta--local" role="alert" id="error-local" hidden></p>
    </section>

    <section class="paso" aria-labelledby="paso-2">
      <h2 id="paso-2"><span class="paso__numero" aria-hidden="true">2</span>Encuadra</h2>

      <noscript><p class="ayuda">Sin JavaScript no se puede mover el marco: se usará el centro de la imagen.</p></noscript>
      <p class="ayuda" id="sin-imagen" hidden>Cuando elijas una imagen, aquí podrás mover el marco para decidir qué parte queda en el marcalibro.</p>

      <div class="encuadre" id="encuadre" hidden>
        <p class="ayuda" id="ayuda-marco">Arrastra el marco sobre la imagen, o usa los controles de abajo. La franja de abajo es el resultado.</p>
        <div class="lienzo" id="lienzo">
          <img id="origen" alt="Imagen elegida" draggable="false">
          <div class="marco" id="marco" tabindex="0" role="group" aria-label="Marco del marcalibro" aria-describedby="ayuda-marco"></div>
        </div>

        <div class="controles">
          <label for="zoom">Acercamiento</label>
          <input id="zoom" type="range" name="zoom" min="1" max="<?= $this->e((string) $maxZoom) ?>" step="0.01" value="<?= $this->e($values['zoom']) ?>">
          <label for="x">Posición horizontal</label>
          <input id="x" type="range" name="x" min="0" max="1" step="0.001" value="<?= $this->e($values['x']) ?>">
          <label for="y">Posición vertical</label>
          <input id="y" type="range" name="y" min="0" max="1" step="0.001" value="<?= $this->e($values['y']) ?>">
        </div>

        <p class="alerta alerta--aviso" id="aviso-recorte" role="status" hidden></p>

        <figure class="resultado">
          <div class="resultado__tira" id="resultado"><img id="resultado-img" alt="" draggable="false"></div>
          <figcaption>Así quedará tu marcalibro.</figcaption>
        </figure>
      </div>
    </section>

    <section class="paso" aria-labelledby="paso-3">
      <h2 id="paso-3"><span class="paso__numero" aria-hidden="true">3</span>Ponle nombre <span class="opcional">(opcional)</span></h2>
      <label for="nombre">Nombre</label>
      <input id="nombre" type="text" name="nombre" maxlength="<?= (int) $labelLength ?>" value="<?= $this->e($values['nombre']) ?>" autocomplete="off" aria-describedby="ayuda-nombre">
      <p id="ayuda-nombre" class="ayuda">Hasta <?= (int) $labelLength ?> caracteres. Solo lo ves tú; no se escribe sobre la imagen.</p>
    </section>

    <p class="acciones">
      <button class="boton" type="submit" id="crear">Crear marcalibro</button>
    </p>
  </form>
</div>
