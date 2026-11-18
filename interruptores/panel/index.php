<?php

declare(strict_types=1);

// Panel interno de interruptores. NO se publica: ver interruptores/README.md.
// Requiere la variable de entorno INTERRUPTORES_CLAVE; sin ella no arranca.

require_once __DIR__ . '/../src/Interruptores.php';

use LolProtocol\Interruptores\DependenciaRota;
use LolProtocol\Interruptores\Interruptores;
use LolProtocol\Interruptores\UnknownInterruptor;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

$clave = (string) getenv('INTERRUPTORES_CLAVE');
if ($clave === '') {
    http_response_code(503);
    echo 'Panel no configurado: define INTERRUPTORES_CLAVE.';
    exit;
}

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS'])) {
    ini_set('session.cookie_secure', '1');
}
session_start();

function escapar(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function volver(string $mensaje = ''): never
{
    if ($mensaje !== '') {
        $_SESSION['aviso'] = $mensaje;
    }
    header('Location: index.php');
    exit;
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

$rutaCat = __DIR__ . '/../catalogo.json';
$rutaEst = __DIR__ . '/../var/estado.json';

// --- Acciones ---------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'entrar') {
        if (hash_equals($clave, (string) ($_POST['clave'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['dentro'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            volver();
        }
        usleep(400000); // frena la fuerza bruta de forma sencilla
        volver('Clave incorrecta.');
    }

    if ($accion === 'salir') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if (empty($_SESSION['dentro'])) {
        volver('Entra primero.');
    }
    if (!hash_equals(tokenCsrf(), (string) ($_POST['csrf'] ?? ''))) {
        volver('La sesión venció: vuelve a intentarlo.');
    }

    if ($accion === 'cambiar') {
        $id = (string) ($_POST['id'] ?? '');
        $encender = ($_POST['valor'] ?? '') === '1';
        try {
            (new Interruptores($rutaCat, $rutaEst))->cambiar($id, $encender);
            volver(($encender ? 'Encendida: ' : 'Apagada: ') . $id . '.');
        } catch (DependenciaRota | UnknownInterruptor $e) {
            volver($e->getMessage());
        } catch (\Throwable $e) {
            error_log('interruptores: ' . $e->getMessage());
            volver('No se pudo guardar. Revisa el registro del servidor.');
        }
    }

    volver('Acción desconocida.');
}

// --- Vista ------------------------------------------------------------------

if (empty($_SESSION['dentro'])) {
    $aviso = $_SESSION['aviso'] ?? '';
    unset($_SESSION['aviso']);
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Interruptores · entrar</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#15110e;color:#f3ead8;font:16px system-ui,sans-serif}
form{background:#221b16;padding:2rem;border-radius:12px;display:grid;gap:.8rem;min-width:260px}
input,button{font:inherit;padding:.6rem;border-radius:8px;border:1px solid #4a3d32;background:#15110e;color:inherit}
button{background:#d9a441;color:#15110e;font-weight:600;cursor:pointer}
.aviso{color:#ff9d7a}
</style>
</head>
<body>
<form method="post" action="index.php">
  <h1 style="margin:0;font-size:1.2rem">Interruptores</h1>
  <?php if ($aviso !== ''): ?><p class="aviso"><?= escapar((string) $aviso) ?></p><?php endif; ?>
  <input type="password" name="clave" placeholder="Clave" autocomplete="current-password" required autofocus>
  <input type="hidden" name="accion" value="entrar">
  <button type="submit">Entrar</button>
</form>
</body>
</html>
    <?php
    exit;
}

$cat = new Interruptores($rutaCat, $rutaEst);
$estados = $cat->estados();
$aviso = $_SESSION['aviso'] ?? '';
unset($_SESSION['aviso']);
$csrf = tokenCsrf();

$porPlataforma = [];
foreach ($cat->catalogo() as $id => $f) {
    $porPlataforma[$f['plataforma']][$id] = $f;
}
$encendidas = count(array_filter($estados));
$total = count($estados);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Interruptores</title>
<style>
:root{--fondo:#15110e;--panel:#221b16;--borde:#3a2f26;--texto:#f3ead8;--sutil:#a89a88;--on:#8fd16b;--off:#6b5e52;--oro:#d9a441;--rojo:#ff9d7a}
*{box-sizing:border-box}
body{margin:0;background:var(--fondo);color:var(--texto);font:15px/1.45 system-ui,sans-serif}
header{position:sticky;top:0;background:var(--fondo);border-bottom:1px solid var(--borde);padding:1rem 1.25rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap;z-index:1}
header h1{font-size:1.1rem;margin:0;flex:1}
.resumen{color:var(--sutil)}
main{padding:1rem 1.25rem 3rem;max-width:1100px;margin:auto}
h2{font-size:1rem;color:var(--oro);margin:2rem 0 .5rem;text-transform:uppercase;letter-spacing:.06em}
.canal{display:grid;grid-template-columns:auto 1fr auto;gap:.9rem;align-items:center;background:var(--panel);border:1px solid var(--borde);border-radius:10px;padding:.7rem .9rem;margin:.45rem 0}
.canal.apagado{opacity:.72}
.led{width:12px;height:12px;border-radius:50%;background:var(--off);box-shadow:0 0 0 3px #0000}
.canal.on .led{background:var(--on);box-shadow:0 0 10px var(--on)}
.nombre{font-weight:600}
.meta{color:var(--sutil);font-size:.85rem}
.dep{color:var(--rojo);font-size:.85rem;margin-top:.2rem}
form.boton{margin:0}
button.sw{min-width:92px;font:inherit;font-weight:600;padding:.5rem .8rem;border-radius:999px;border:1px solid var(--borde);cursor:pointer;background:#15110e;color:var(--texto)}
button.sw.on{background:var(--on);color:#15110e;border-color:var(--on)}
button.sw:disabled{opacity:.4;cursor:not-allowed}
.aviso{background:#3a2a1c;border:1px solid var(--oro);padding:.6rem .8rem;border-radius:8px;margin:1rem 0}
.salir button{background:none;border:1px solid var(--borde);color:var(--sutil);padding:.4rem .8rem;border-radius:8px;cursor:pointer;font:inherit}
@media (max-width:600px){.canal{grid-template-columns:auto 1fr}.canal form{grid-column:1/-1}button.sw{width:100%}}
</style>
</head>
<body>
<header>
  <h1>Interruptores</h1>
  <span class="resumen"><?= $encendidas ?> de <?= $total ?> encendidas</span>
  <form class="salir" method="post" action="index.php"><input type="hidden" name="accion" value="salir"><button type="submit">Salir</button></form>
</header>
<main>
<?php if ($aviso !== ''): ?><p class="aviso"><?= escapar((string) $aviso) ?></p><?php endif; ?>

<?php foreach ($cat->plataformas() as $clave => $titulo): ?>
  <?php if (empty($porPlataforma[$clave])) { continue; } ?>
  <h2><?= escapar($titulo) ?></h2>
  <?php foreach ($porPlataforma[$clave] as $id => $f): ?>
    <?php
      $on = $estados[$id];
      $faltan = $on ? [] : $cat->faltanParaEncender($id);
      $dependientes = $on ? $cat->dependientesActivos($id) : [];
      $bloqueado = !$on ? $faltan !== [] : $dependientes !== [];
      $motivo = !$on
          ? ($faltan !== [] ? 'Necesita encendida: ' . implode(', ', $faltan) : '')
          : ($dependientes !== [] ? 'Otras la necesitan: ' . implode(', ', $dependientes) : '');
    ?>
    <div class="canal <?= $on ? 'on' : 'apagado' ?>">
      <span class="led" aria-hidden="true"></span>
      <div>
        <div class="nombre"><?= escapar($f['nombre']) ?></div>
        <div class="meta"><code><?= escapar($id) ?></code> · <?= escapar($f['carpeta']) ?> · <?= escapar($f['tipo']) ?></div>
        <?php if ($motivo !== ''): ?><div class="dep"><?= escapar($motivo) ?></div><?php endif; ?>
      </div>
      <form class="boton" method="post" action="index.php">
        <input type="hidden" name="accion" value="cambiar">
        <input type="hidden" name="csrf" value="<?= escapar($csrf) ?>">
        <input type="hidden" name="id" value="<?= escapar($id) ?>">
        <input type="hidden" name="valor" value="<?= $on ? '0' : '1' ?>">
        <button type="submit" class="sw <?= $on ? 'on' : '' ?>" <?= $bloqueado ? 'disabled' : '' ?> title="<?= escapar($motivo) ?>">
          <?= $on ? 'Encendida' : 'Apagada' ?>
        </button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
</main>
</body>
</html>
