<?php

declare(strict_types=1);

/**
 * The check digit of an id (see Support/DigitoVerificador.php), for whoever creates ids:
 *
 *   php bin/id.php completar <numero> [<ancho>]   # 612847310 -> 6128473101 (padded to <ancho> - 1 digits first)
 *   php bin/id.php verificar <id>                 # exit 0 if the last digit is the right one, 1 if not
 *
 * It needs no database and ignores IDS_DIGITO_VERIFICADOR: it is the tool, not the switch.
 */

use App\Support\DigitoVerificador;

require __DIR__ . '/../vendor/autoload.php';

[, $orden, $valor] = $argv + [null, '', ''];
$ancho = $argv[3] ?? null;
$uso = "Uso: php bin/id.php completar <numero> [<ancho del id>]\n     php bin/id.php verificar <id>\n";

if (!ctype_digit((string)$valor) || !in_array($orden, ['completar', 'verificar'], true)
    || ($ancho !== null && (!ctype_digit($ancho) || (int)$ancho < 2 || $orden !== 'completar'))) {
    fwrite(STDERR, $uso);
    exit(2);
}

if ($orden === 'verificar') {
    $valido = DigitoVerificador::esValido($valor);
    echo $valor . ($valido ? " es válido\n" : " NO es válido (el dígito de verificación no coincide)\n");
    exit($valido ? 0 : 1);
}

if ($ancho !== null) {
    if (strlen($valor) > (int)$ancho - 1) {
        fwrite(STDERR, "{$valor} no cabe en un id de {$ancho} dígitos (queda sitio para " . ((int)$ancho - 1) . " y el dígito de verificación).\n");
        exit(1);
    }
    $valor = str_pad($valor, (int)$ancho - 1, '0', STR_PAD_LEFT);
}

echo DigitoVerificador::completar($valor) . "\n";
