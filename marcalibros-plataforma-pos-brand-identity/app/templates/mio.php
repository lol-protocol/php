<?php
/**
 * @var array{id: string, source: string, label: ?string, files: array<string, string>, width: int, height: int, created: int} $bookmark
 * @var int|null $expires  Momento en que se borra solo; null si no caduca
 * @var int $retentionDays
 */
$base = '/mio/' . $bookmark['id'];
?>
<div class="envoltura mio">
  <h1><?= $bookmark['label'] !== null && $bookmark['label'] !== '' ? $this->e($bookmark['label']) : 'Tu marcalibro' ?></h1>
  <p class="lead">Listo. Descárgalo en el tamaño que necesites.</p>

  <img class="tira tira--grande" src="<?= $this->e($base) ?>/1x.jpg" width="1400" height="200" alt="Tu marcalibro, 1400 por 200 píxeles">

  <div class="detalle__fila">
    <section aria-labelledby="descarga">
      <h2 id="descarga">Descargar</h2>
      <p class="acciones">
        <a class="boton" href="<?= $this->e($base) ?>/1x.jpg?descargar=1" download>JPG 1400 × 200</a>
        <a class="boton boton--claro" href="<?= $this->e($base) ?>/2x.jpg?descargar=1" download>JPG 2800 × 400</a>
      </p>
      <p class="ayuda">El tamaño grande sirve para pantallas de alta densidad e impresión.</p>
    </section>

    <section aria-labelledby="enlace-titulo">
      <h2 id="enlace-titulo">Tu enlace</h2>
      <p>Guarda este enlace: es la única forma de volver a tu marcalibro. Quien lo tenga puede verlo y borrarlo.</p>
      <div class="copiar">
        <label class="solo-lectores" for="enlace">Enlace de tu marcalibro</label>
        <input id="enlace" type="text" readonly value="<?= $this->e($base) ?>" data-path="<?= $this->e($base) ?>">
        <button class="boton boton--claro" type="button" id="copiar" hidden>Copiar enlace</button>
      </div>
      <p class="ayuda" id="copiado" role="status"></p>
    </section>
  </div>

  <?php if ($expires !== null): ?>
  <p class="expira">Se borrará solo el <?= $this->e($this->date($expires)) ?> (a los <?= (int) $retentionDays ?> días de crearlo).</p>
  <?php else: ?>
  <p class="expira">Se guarda sin fecha de vencimiento; solo se borra si lo borras tú.</p>
  <?php endif; ?>

  <details class="borrar">
    <summary>Borrar ahora</summary>
    <form method="post" action="<?= $this->e($base) ?>/eliminar">
      <p>Se eliminan las dos imágenes y el enlace deja de funcionar. No se puede deshacer.</p>
      <button class="boton boton--peligro" type="submit">Borrar mi marcalibro</button>
    </form>
  </details>

  <p class="acciones"><a class="boton boton--claro" href="/crear">Crear otro</a> <a class="boton boton--claro" href="/">Ver el catálogo</a></p>
</div>
