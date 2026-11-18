<?php

declare(strict_types=1);

// Panel interno de interruptores. NO se publica: ver interruptores/README.md.
// Los usuarios se crean con bin/usuarios.php. Sin usuarios, el panel no entra.

require_once __DIR__ . '/../src/Interruptores.php';
require_once __DIR__ . '/../src/Usuarios.php';

use LolProtocol\Interruptores\DependenciaRota;
use LolProtocol\Interruptores\Interruptores;
use LolProtocol\Interruptores\UnknownInterruptor;
use LolProtocol\Interruptores\Usuarios;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");

// Carpeta con estado y usuarios. Por defecto, var/ del proyecto; las pruebas la cambian.
$var = rtrim((string) (getenv('INTERRUPTORES_VAR') ?: __DIR__ . '/../var'), '/');
$rutaCat = __DIR__ . '/../catalogo.json';
$rutaEst = $var . '/estado.json';
$usuarios = new Usuarios($var . '/usuarios.json');

if (!$usuarios->hay()) {
    http_response_code(503);
    echo 'Panel sin usuarios. Crea uno con: php interruptores/bin/usuarios.php crear <nombre> editor';
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

// --- Acciones ---------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'entrar') {
        $nombre = strtolower(trim((string) ($_POST['usuario'] ?? '')));
        $clave = (string) ($_POST['clave'] ?? '');
        if ($usuarios->bloqueado($nombre)) {
            volver('Demasiados intentos fallidos. Espera 15 minutos.');
        }
        $ok = $usuarios->verificar($nombre, $clave);
        if ($ok !== null) {
            session_regenerate_id(true);
            $_SESSION = ['usuario' => $ok['nombre'], 'rol' => $ok['rol'], 'csrf' => bin2hex(random_bytes(16))];
            volver();
        }
        usleep(400000); // frena la fuerza bruta además del bloqueo por usuario
        volver('Usuario o clave incorrectos.');
    }

    if ($accion === 'salir') {
        // Con token: una página ajena no puede cerrarle la sesión a nadie.
        if (!empty($_SESSION['usuario']) && hash_equals(tokenCsrf(), (string) ($_POST['csrf'] ?? ''))) {
            $_SESSION = [];
            session_destroy();
        }
        header('Location: index.php');
        exit;
    }

    if (empty($_SESSION['usuario'])) {
        volver('Entra primero.');
    }
    if (!hash_equals(tokenCsrf(), (string) ($_POST['csrf'] ?? ''))) {
        volver('La sesión venció: vuelve a intentarlo.');
    }

    if ($accion === 'cambiar') {
        // El rol se comprueba aquí, en el servidor: un lector no cambia nada aunque falsifique el formulario.
        if (($_SESSION['rol'] ?? '') !== 'editor') {
            volver('Tu rol (lector) no permite cambiar nada.');
        }
        $ref = (string) ($_POST['ref'] ?? '');
        $encender = ($_POST['valor'] ?? '') === '1';
        try {
            (new Interruptores($rutaCat, $rutaEst))->cambiar($ref, $encender, (string) $_SESSION['usuario']);
            volver(($encender ? 'Encendido: ' : 'Apagado: ') . $ref . '.');
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

if (empty($_SESSION['usuario'])) {
    $aviso = (string) ($_SESSION['aviso'] ?? '');
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
  <?php if ($aviso !== ''): ?><p class="aviso"><?= escapar($aviso) ?></p><?php endif; ?>
  <input type="text" name="usuario" placeholder="Usuario" autocomplete="username" required autofocus>
  <input type="password" name="clave" placeholder="Clave" autocomplete="current-password" required>
  <input type="hidden" name="accion" value="entrar">
  <button type="submit">Entrar</button>
</form>
</body>
</html>
    <?php
    exit;
}

$cat = new Interruptores($rutaCat, $rutaEst);
$propios = $cat->interruptores();
$aviso = (string) ($_SESSION['aviso'] ?? '');
unset($_SESSION['aviso']);
$csrf = tokenCsrf();
$puedeCambiar = ($_SESSION['rol'] ?? '') === 'editor';

$porPlataforma = [];
foreach ($cat->catalogo() as $id => $f) {
    $porPlataforma[$f['plataforma']][$id] = $f;
}
$encendidos = count(array_filter($propios));
$total = count($propios);

/** Botón on/off de una ref, con su motivo si no se puede cambiar ahora. */
function boton(Interruptores $cat, array $propios, string $ref, string $csrf, bool $esOpcion, bool $puedeCambiar): string
{
    $on = $propios[$ref];
    $motivo = '';
    if (!$puedeCambiar) {
        $motivo = 'Solo lectura: tu rol no cambia interruptores.';
    } elseif (!$on && !$esOpcion) {
        $faltan = $cat->faltanParaEncender($ref);
        $motivo = $faltan !== [] ? 'Necesita encendido: ' . implode(', ', $faltan) : '';
    } elseif ($on) {
        $dep = $cat->dependientesActivos($ref);
        $motivo = $dep !== [] ? 'Otras la necesitan: ' . implode(', ', $dep) : '';
    }

    $html = '<form class="boton" method="post" action="index.php">';
    $html .= '<input type="hidden" name="accion" value="cambiar">';
    $html .= '<input type="hidden" name="csrf" value="' . escapar($csrf) . '">';
    $html .= '<input type="hidden" name="ref" value="' . escapar($ref) . '">';
    $html .= '<input type="hidden" name="valor" value="' . ($on ? '0' : '1') . '">';
    $html .= '<button type="submit" class="sw' . ($on ? ' on' : '') . '"' . ($motivo !== '' ? ' disabled' : '')
        . ' title="' . escapar($motivo) . '">' . ($on ? 'Encendido' : 'Apagado') . '</button>';
    $html .= '</form>';
    if ($motivo !== '' && $puedeCambiar) {
        $html .= '<div class="dep">' . escapar($motivo) . '</div>';
    }
    return $html;
}

$historial = $cat->historial(20);
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
.resumen,.quien{color:var(--sutil)}
main{padding:1rem 1.25rem 3rem;max-width:1100px;margin:auto}
h2{font-size:1rem;color:var(--oro);margin:2rem 0 .5rem;text-transform:uppercase;letter-spacing:.06em}
.canal{background:var(--panel);border:1px solid var(--borde);border-radius:10px;padding:.7rem .9rem;margin:.5rem 0}
.fila{display:grid;grid-template-columns:auto 1fr auto;gap:.9rem;align-items:center}
.led{width:12px;height:12px;border-radius:50%;background:var(--off)}
.canal.on > .fila .led{background:var(--on);box-shadow:0 0 10px var(--on)}
.nombre{font-weight:600}
.meta{color:var(--sutil);font-size:.85rem}
.dep{color:var(--rojo);font-size:.85rem;margin-top:.2rem}
form.boton{margin:0}
button.sw{min-width:110px;font:inherit;font-weight:600;padding:.45rem .8rem;border-radius:999px;border:1px solid var(--borde);cursor:pointer;background:#15110e;color:var(--texto)}
button.sw.on{background:var(--on);color:#15110e;border-color:var(--on)}
button.sw:disabled{opacity:.4;cursor:not-allowed}
.opciones{margin:.7rem 0 0 1.6rem;display:grid;gap:.35rem;border-left:2px solid var(--borde);padding-left:1rem}
.opcion{display:grid;grid-template-columns:1fr auto;gap:.6rem;align-items:center}
.opcion.inactiva{opacity:.6}
.aviso{background:#3a2a1c;border:1px solid var(--oro);padding:.6rem .8rem;border-radius:8px;margin:1rem 0}
table{width:100%;border-collapse:collapse;font-size:.88rem}
th,td{text-align:left;padding:.35rem .5rem;border-bottom:1px solid var(--borde)}
header form button{background:none;border:1px solid var(--borde);color:var(--sutil);padding:.4rem .8rem;border-radius:8px;cursor:pointer;font:inherit}
@media (max-width:600px){.fila{grid-template-columns:auto 1fr}.fila form{grid-column:1/-1}button.sw{width:100%}}
</style>
</head>
<body>
<header>
  <h1>Interruptores</h1>
  <span class="resumen"><?= $encendidos ?> de <?= $total ?> encendidos (funciones y opciones)</span>
  <span class="quien"><?= escapar((string) $_SESSION['usuario']) ?> · <?= escapar((string) $_SESSION['rol']) ?></span>
  <form method="post" action="index.php"><input type="hidden" name="accion" value="salir"><input type="hidden" name="csrf" value="<?= escapar($csrf) ?>"><button type="submit">Salir</button></form>
</header>
<main>
<?php if ($aviso !== ''): ?><p class="aviso"><?= escapar($aviso) ?></p><?php endif; ?>

<?php foreach ($cat->plataformas() as $clave => $titulo): ?>
  <?php if (empty($porPlataforma[$clave])) { continue; } ?>
  <h2><?= escapar($titulo) ?></h2>
  <?php foreach ($porPlataforma[$clave] as $id => $f): ?>
    <?php $on = $propios[$id]; ?>
    <div class="canal <?= $on ? 'on' : '' ?>">
      <div class="fila">
        <span class="led" aria-hidden="true"></span>
        <div>
          <div class="nombre"><?= escapar($f['nombre']) ?></div>
          <div class="meta"><code><?= escapar($id) ?></code> · <?= escapar($f['tipo']) ?><?= $f['opciones'] !== [] ? ' · ' . count($f['opciones']) . ' opciones' : '' ?></div>
        </div>
        <div><?= boton($cat, $propios, $id, $csrf, false, $puedeCambiar) ?></div>
      </div>
      <?php if ($f['opciones'] !== []): ?>
        <div class="opciones">
          <?php foreach ($f['opciones'] as $op): ?>
            <?php $ref = $id . ':' . $op['id']; $efectiva = $cat->activo($ref); ?>
            <div class="opcion <?= $efectiva ? '' : 'inactiva' ?>">
              <div>
                <span class="nombre" style="font-weight:500"><?= escapar($op['nombre']) ?></span>
                <span class="meta"><code><?= escapar($ref) ?></code><?= $efectiva ? '' : ' · inactiva ahora' ?></span>
              </div>
              <div><?= boton($cat, $propios, $ref, $csrf, true, $puedeCambiar) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<h2>Últimos cambios</h2>
<?php if ($historial === []): ?>
  <p class="meta">Todavía no hay cambios.</p>
<?php else: ?>
  <table>
    <thead><tr><th>Cuándo (UTC)</th><th>Quién</th><th>Interruptor</th><th>Cambio</th></tr></thead>
    <tbody>
    <?php foreach ($historial as $e): ?>
      <tr>
        <td><?= escapar((string) $e['ts']) ?></td>
        <td><?= escapar((string) $e['quien']) ?></td>
        <td><code><?= escapar((string) $e['ref']) ?></code></td>
        <td><?= $e['antes'] ? 'encendido' : 'apagado' ?> → <?= $e['despues'] ? 'encendido' : 'apagado' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</main>
</body>
</html>
