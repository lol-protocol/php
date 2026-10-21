<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/FiltroNotas.php';

// Una nota habla de un usuario real: lo que decide el filtro es lo que api/notas.php hace con ella.
$filtro = FiltroNotas::desdeEntorno();

assert_igual(['decision' => 'approve', 'motivos' => []], $filtro->revisar('el pago llegó tarde, revisar con soporte'), 'filtro notas: una nota operativa pasa');
assert_igual('reject', $filtro->revisar('es un hijo de puta')['decision'], 'filtro notas: un insulto se rechaza');
assert_igual(['insulto'], $filtro->revisar('es un hijo de puta')['motivos'], 'filtro notas: el motivo de un insulto no repite el insulto');
assert_igual('reject', $filtro->revisar('he is a motherfucker')['decision'], 'filtro notas: también en inglés');

$acusacion = $filtro->revisar('estafó a su familia y estuvo preso');
assert_igual('review', $acusacion['decision'], 'filtro notas: una acusación va a revisión');
assert_igual(['acusación de delito'], $acusacion['motivos'], 'filtro notas: los motivos no se repiten');
assert_igual(['decision' => 'review', 'motivos' => ['dato personal (RUT)']], $filtro->revisar('su RUT es 12.345.678-5'), 'filtro notas: un dato personal va a revisión');

// Más de 2000 caracteres se revisa por partes: lo de la segunda parte cuenta igual.
$larga = str_repeat('el usuario pagó a tiempo. ', 100) . "\n\nes un hijo de puta";
assert_igual('reject', $filtro->revisar($larga)['decision'], 'filtro notas: un texto largo se revisa entero');

$sinLibreria = getenv('FILTRO_DIFAMACION_DIR');
putenv('FILTRO_DIFAMACION_DIR=' . sys_get_temp_dir() . '/no-existe-' . bin2hex(random_bytes(4)));
try {
    FiltroNotas::desdeEntorno();
    assert_verdadero(false, 'filtro notas: sin la librería, desdeEntorno() falla en vez de dejar pasar todo');
} catch (RuntimeException) {
    assert_verdadero(true, 'filtro notas: sin la librería, desdeEntorno() falla en vez de dejar pasar todo');
} finally {
    putenv($sinLibreria === false ? 'FILTRO_DIFAMACION_DIR' : "FILTRO_DIFAMACION_DIR=$sinLibreria");
}
