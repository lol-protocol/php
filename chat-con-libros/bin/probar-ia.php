<?php
declare(strict_types=1);

/*
 * Prueba el modo con IA contra la API real de Anthropic, con UNA sola pregunta sobre el cuento de
 * ejemplos/muestra.txt. Sirve para comprobar que la clave, el SDK y el modelo funcionan antes de
 * usar --ia con tus libros.
 *
 *   composer require anthropic-ai/sdk guzzlehttp/guzzle
 *   export ANTHROPIC_API_KEY=...
 *   php bin/probar-ia.php
 *
 * Hace una llamada de pocos cientos de tokens (céntimos). Sale con 0 si la respuesta la redactó el
 * modelo, y con 1 si no pudo (dice por qué). No muestra la clave.
 */

require __DIR__ . '/../src/cargar.php';

use ChatLibros\Biblioteca;
use ChatLibros\Conversador;
use ChatLibros\Dialogo;
use ChatLibros\GeneradorClaude;

if (($motivo = GeneradorClaude::motivoNoDisponible()) !== null) {
    fwrite(STDERR, "No se puede probar el modo con IA. $motivo\n");
    exit(1);
}

$raiz = dirname(__DIR__);
$ruta = $raiz . '/ejemplos/muestra.txt';
$generador = new GeneradorClaude();
$dialogo = new Dialogo(new Conversador(Biblioteca::cargarRuta($ruta), Biblioteca::sinonimosDeRuta($raiz, $ruta)), $generador);

$pregunta = '¿Qué decía el mensaje que recibió Tomás?';
echo 'Modelo: ', $generador->modelo(), "\nPregunta: $pregunta\n\n";
$inicio = microtime(true);
$r = $dialogo->responder($pregunta);
printf("(%.1f s)\n", microtime(true) - $inicio);

if (isset($r['aviso'])) {
    echo 'AVISO: ', $r['aviso'], "\n";
}
echo "Modo: {$r['modo']}\n\n{$r['texto']}\n";
if ($r['modo'] !== 'ia') {
    fwrite(STDERR, "\nLa respuesta no la redactó el modelo (ver el aviso). Revisa la clave, la red y el modelo (ANTHROPIC_MODEL).\n");
    exit(1);
}
echo "\nTodo en orden: la respuesta la redactó el modelo con los pasajes del libro.\n";
foreach ($r['citas'] as $cita) {
    echo '  usó: [', $cita['capitulo'], "]\n";
}
