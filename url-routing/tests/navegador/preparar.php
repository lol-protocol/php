<?php

declare(strict_types=1);

/**
 * Deja la base de genealogia lista para ejecutar.mjs: tres pedidos de retiro en los tres estados que muestra la
 * cola de /0/3/ (uno esperando, con marcas del filtro; uno retirado; uno resuelto y ya depurado).
 *
 *   php tests/navegador/preparar.php        (con DB_DSN_GENEALOGY en el entorno, ya migrada)
 */

use App\Repositories\Genealogy\RetiroRepository;
use App\Support\Database;

require __DIR__ . '/../../vendor/autoload.php';

$repo = new RetiroRepository(Database::forSite('genealogy'));

$repo->crear('persona', 6128473105, 'me_nombra', "Dice que estafó a mi familia: es falso.\nPido que lo retiren.", 'ana@example.com', 'acusación de delito');

$retirado = $repo->crear('grupo', 582317, 'terminos', 'Copia un texto ajeno.', 'beto@example.com');
$repo->decidir($retirado, 'retirar', 'Se retira.');

$depurado = $repo->crear('registro', 81372002, 'otro', 'Ya está resuelto.', 'carla@example.com');
$repo->decidir($depurado, 'rechazar', 'El acta es pública.');
$repo->depurar(12, strtotime('+13 months'));

// Una entrega con clave (/e/{token}/): su enlace y su clave los lee ejecutar.mjs desde ENTREGA_SALIDA.
$entrega = (new \App\Repositories\Genealogy\EntregaRepository(Database::forSite('genealogy')))->crear(6128473105);
if (($salida = getenv('ENTREGA_SALIDA')) !== false && $salida !== '') {
    file_put_contents($salida, json_encode(['token' => $entrega['token'], 'clave' => $entrega['clave']], JSON_THROW_ON_ERROR));
}

// Dos solicitudes de datos para el panel del propietario (/0/4/): una esperando y una ya cerrada.
$solicitudes = new \App\Repositories\Genealogy\SolicitudRepository(Database::forSite('genealogy'));
$solicitudes->crear(6128473105, 'supresion', "Pido que se borren mis datos.\nGracias.", 'dana@example.com');
$cerrada = $solicitudes->crear(6128473106, 'acceso', 'Quiero ver qué tienen de mí.', 'eva@example.com');
$solicitudes->cerrar($cerrada, 'atender', 'Resumen mandado.');

echo "pedidos: 3\n";
