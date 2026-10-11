<?php
/**
 * @var int    $status
 * @var string $heading
 * @var string $message
 */
?>
<div class="envoltura envoltura--estrecha pagina-error">
  <h1><?= $this->e($heading) ?></h1>
  <p class="lead"><?= $this->e($message) ?></p>
  <p class="acciones">
    <a class="boton" href="/">Ver el catálogo</a>
    <a class="boton boton--claro" href="/crear">Crear el mío</a>
  </p>
</div>
