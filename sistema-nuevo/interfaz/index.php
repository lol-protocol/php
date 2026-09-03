<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Backoffice · Actividad de usuarios</title>
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/login.css">
  <link rel="stylesheet" href="css/topbar.css">
  <link rel="stylesheet" href="css/sesion.css">
  <link rel="stylesheet" href="css/layout.css">
  <link rel="stylesheet" href="css/leyenda.css">
  <link rel="stylesheet" href="css/alertas.css">
  <link rel="stylesheet" href="css/estado.css">
  <link rel="stylesheet" href="css/linea-tiempo.css">
  <link rel="stylesheet" href="css/paginacion.css">
  <link rel="stylesheet" href="css/grafico.css">
  <link rel="stylesheet" href="css/tarjetas.css">
  <link rel="stylesheet" href="css/comentarios.css">
  <link rel="stylesheet" href="css/badges.css">
</head>
<body>
  <?php include __DIR__ . '/partes/pantalla-login.php'; ?>

  <div id="app" hidden>
    <?php include __DIR__ . '/partes/topbar.php'; ?>
    <?php include __DIR__ . '/partes/panel-principal.php'; ?>
  </div>

  <script type="module" src="js/eventos.js"></script>
</body>
</html>
