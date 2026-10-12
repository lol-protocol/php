<?php

// 10. Revisar mensajes de chat (ChatLineReviewer): insultos, burlas, contenido sexual y bélico.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\Chat\ChatLineReviewer;

// Una instancia por idioma, reutilizable: cargar las listas cuesta unos milisegundos, revisar una línea, décimas.
$chat = ChatLineReviewer::create(__DIR__ . '/../config', 'spa');

$lines = [
    'Hola abuela, ¿vienes mañana?',
    'vamos a coger el bus de las ocho',          // «coger» sola es ambigua: no cuenta
    'mi abuelo luchó en la guerra',              // se informa como bélico, pero no se censura
    'Callate vejestorio',                        // burlesco → revisión
    'Mándame nudes ahora',                       // sexual → bloquear
    'vamos a f o l l a r',                       // las letras sueltas se unen
    'eres una puuuuta',                          // 3+ iguales: nunca legítimas, bloquea
    'eres una puuta',                            // 2 iguales: puede ser un apellido → revisión
    'vivimos en la calle Mayor',                 // «calle» → «calé» se evita con `legit`
    'te voy a matar',                            // amenaza → bloquear
    'ojalá te mueras, hijo de puta',             // deseo de muerte + insulto
];

foreach ($lines as $line) {
    $result = $chat->review($line);

    printf(
        "%-8s %-26s %s\n    «%s»\n",
        $result->getDecision(),
        implode(', ', $result->getContentTypes()) ?: '—',
        $result->shouldCensor() ? 'censurar' : 'dejar pasar',
        $result->censored()
    );
}

// En otro idioma: las listas de temas existen para spa y eng; los demás idiomas detectan igualmente los insultos.
$english = ChatLineReviewer::create(__DIR__ . '/../config', 'eng');
$result = $english->review("i'm gonna kill you");

printf("\n[eng] %s → %s (%s)\n", $result->getLine(), $result->getDecision(), implode(', ', $result->getContentTypes()));
