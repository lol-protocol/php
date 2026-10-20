<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/api/notas.php';

// Una nota no puede ocupar sin límite la base: el tope se cuenta en caracteres (no bytes) y sobre el
// texto recortado, igual que lo que AlmacenNotas::guardar() termina guardando.
assert_igual(false, nota_demasiado_larga(str_repeat('a', NOTA_MAX_CARACTERES)), 'notas: el tope exacto se acepta');
assert_igual(true, nota_demasiado_larga(str_repeat('a', NOTA_MAX_CARACTERES + 1)), 'notas: un carácter más se rechaza');
assert_igual(false, nota_demasiado_larga(str_repeat('ñ', NOTA_MAX_CARACTERES)), 'notas: se cuentan caracteres, no bytes');
assert_igual(false, nota_demasiado_larga('  ' . str_repeat('a', NOTA_MAX_CARACTERES) . "  \n"), 'notas: los espacios de los bordes no cuentan');
