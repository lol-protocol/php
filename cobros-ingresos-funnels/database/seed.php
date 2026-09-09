<?php

declare(strict_types=1);

/**
 * Reconstruye el esquema (PostgreSQL) y carga datos de ejemplo reproducibles.
 * Uso: php database/seed.php
 * Variables de conexion: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/paises_monedas.php';

use App\Database;

mt_srand(2024);

$pdo = Database::connection();
$schema = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schema);

const DIA = 86400;
$hoy = new DateTimeImmutable('today');

function fecha(DateTimeImmutable $d): string
{
    return $d->format('Y-m-d');
}

function diasAleatorios(DateTimeImmutable $desde, int $maxDias): DateTimeImmutable
{
    return $desde->modify('+' . random_int(0, max(0, $maxDias)) . ' days');
}

/** @param array<string,float> $pesos */
function eleccionPonderada(array $pesos): string
{
    $total = array_sum($pesos);
    $r = mt_rand() / mt_getrandmax() * $total;
    $acum = 0.0;
    foreach ($pesos as $clave => $peso) {
        $acum += $peso;
        if ($r <= $acum) {
            return $clave;
        }
    }
    return array_key_last($pesos);
}

// --- 0) Catalogo de referencia: monedas y paises (~200 territorios). ---
$catalogo = catalogoPaisesMonedas();

$insMoneda = $pdo->prepare(
    'INSERT INTO monedas (codigo, nombre, simbolo, tasa_a_usd) VALUES (:codigo, :nombre, :simbolo, :tasa_a_usd)'
);
foreach ($catalogo['monedas'] as $codigo => $m) {
    $insMoneda->execute([
        ':codigo' => $codigo,
        ':nombre' => $m['nombre'],
        ':simbolo' => $m['simbolo'],
        ':tasa_a_usd' => $m['tasa_a_usd'],
    ]);
}

$insPais = $pdo->prepare('INSERT INTO paises (codigo, nombre, moneda_codigo) VALUES (:codigo, :nombre, :moneda)');
foreach ($catalogo['paises'] as $codigo => $p) {
    $insPais->execute([':codigo' => $codigo, ':nombre' => $p['nombre'], ':moneda' => $p['moneda']]);
}

// --- 0b) Usuarios para entrar al panel. ---
$emailAdmin = 'admin@ejemplo.com';
$passwordAdmin = 'admin1234';
$insUsuarioSistema = $pdo->prepare(
    'INSERT INTO usuarios_sistema (nombre, email, password_hash) VALUES (:nombre, :email, :hash)'
);
$insUsuarioSistema->execute([
    ':nombre' => 'Administrador',
    ':email' => $emailAdmin,
    ':hash' => password_hash($passwordAdmin, PASSWORD_DEFAULT),
]);
// Un segundo usuario de ejemplo, para que la pantalla de Usuarios no muestre
// una sola fila y se pueda probar "cambiar contraseña"/"revocar" sobre un
// usuario que no sea el que esta logueado.
$insUsuarioSistema->execute([
    ':nombre' => 'Soporte',
    ':email' => 'soporte@ejemplo.com',
    ':hash' => password_hash('soporte1234', PASSWORD_DEFAULT),
]);

$nombresPila = ['Lucia', 'Mateo', 'Sofia', 'Diego', 'Valentina', 'Santiago', 'Camila', 'Emilio',
    'Martina', 'Nicolas', 'Renata', 'Sebastian', 'Paula', 'Joaquin', 'Daniela', 'Andres',
    'Isabella', 'Gabriel', 'Fernanda', 'Tomas', 'Antonia', 'Rodrigo', 'Carla', 'Ignacio',
    'Victoria', 'Emiliano', 'Regina', 'Alejandro', 'Ximena', 'Bruno'];
$apellidos = ['Garcia', 'Rodriguez', 'Martinez', 'Fernandez', 'Lopez', 'Gonzalez', 'Perez',
    'Sanchez', 'Ramirez', 'Torres', 'Flores', 'Diaz', 'Vargas', 'Castro', 'Ortiz',
    'Silva', 'Rojas', 'Medina', 'Herrera', 'Nunez'];
$dominios = ['acme.com', 'globalcorp.com', 'nimbus.io', 'vertice.mx', 'delta-soft.com', 'gmail.com'];

function nombreUnico(array $usados, array $pila, array $apellidos): array
{
    do {
        $nombre = $pila[array_rand($pila)] . ' ' . $apellidos[array_rand($apellidos)];
    } while (isset($usados[$nombre]));
    return [$nombre, $nombre . ' ' . $apellidos[array_rand($apellidos)]];
}

$emailsUsados = [];
function emailUnico(string $nombre, array $dominios, array &$emailsUsados): string
{
    $base = strtolower(str_replace(' ', '.', $nombre));
    $base = strtr($base, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    $dominio = $dominios[array_rand($dominios)];
    $email = $base . '@' . $dominio;
    $i = 1;
    while (isset($emailsUsados[$email])) {
        $email = $base . $i . '@' . $dominio;
        $i++;
    }
    $emailsUsados[$email] = true;
    return $email;
}

$segmentos = ['starter', 'pro', 'enterprise'];
$conceptos = ['Suscripcion mensual', 'Licencia anual', 'Implementacion', 'Soporte premium', 'Consultoria'];
$canalPesos = ['organico' => 35, 'ads' => 25, 'referido' => 20, 'redes_sociales' => 15, 'email' => 5];
$metodoPesos = ['transferencia' => 50, 'tarjeta' => 35, 'efectivo' => 15];

// Subconjunto de paises usado para generar personas de ejemplo (el catalogo
// completo de ~200 queda cargado igual para los formularios de alta).
$ciudadesPorPais = [
    'MX' => ['Ciudad de Mexico', 'Guadalajara', 'Monterrey'],
    'CO' => ['Bogota', 'Medellin'],
    'AR' => ['Buenos Aires', 'Cordoba'],
    'CL' => ['Santiago'],
    'PE' => ['Lima'],
    'ES' => ['Madrid', 'Barcelona'],
    'US' => ['Miami', 'Los Angeles', 'Nueva York'],
    'BR' => ['Sao Paulo', 'Rio de Janeiro'],
    'EC' => ['Quito', 'Guayaquil'],
    'UY' => ['Montevideo'],
    'GT' => ['Ciudad de Guatemala'],
    'DO' => ['Santo Domingo'],
    'PA' => ['Ciudad de Panama'],
    'VE' => ['Caracas'],
    'PY' => ['Asuncion'],
    'BO' => ['La Paz'],
    'CA' => ['Toronto', 'Vancouver'],
    'FR' => ['Paris'],
    'DE' => ['Berlin', 'Munich'],
    'GB' => ['Londres'],
    'IT' => ['Roma', 'Milan'],
    'IN' => ['Bombay', 'Delhi'],
    'JP' => ['Tokio'],
    'CN' => ['Shanghai', 'Pekin'],
    'AU' => ['Sidney'],
    'ZA' => ['Johannesburgo'],
    'NG' => ['Lagos'],
    'PH' => ['Manila'],
];
$idiomaPorPais = [
    'MX' => 'Espanol', 'CO' => 'Espanol', 'AR' => 'Espanol', 'CL' => 'Espanol', 'PE' => 'Espanol',
    'ES' => 'Espanol', 'EC' => 'Espanol', 'UY' => 'Espanol', 'GT' => 'Espanol', 'DO' => 'Espanol',
    'PA' => 'Espanol', 'VE' => 'Espanol', 'PY' => 'Espanol', 'BO' => 'Espanol',
    'US' => 'Ingles', 'CA' => 'Ingles', 'GB' => 'Ingles', 'AU' => 'Ingles',
    'IN' => 'Ingles', 'ZA' => 'Ingles', 'NG' => 'Ingles', 'PH' => 'Ingles',
    'BR' => 'Portugues', 'FR' => 'Frances', 'DE' => 'Aleman', 'IT' => 'Italiano',
    'JP' => 'Japones', 'CN' => 'Chino',
];
$paisPesos = [
    'MX' => 22, 'CO' => 13, 'AR' => 10, 'CL' => 8, 'PE' => 7, 'ES' => 8, 'US' => 7, 'BR' => 5,
    'EC' => 2, 'UY' => 2, 'GT' => 2, 'DO' => 2, 'PA' => 1, 'VE' => 2, 'PY' => 1, 'BO' => 1,
    'CA' => 2, 'FR' => 2, 'DE' => 2, 'GB' => 2, 'IT' => 1,
    'IN' => 1, 'JP' => 1, 'CN' => 1, 'AU' => 1, 'ZA' => 1, 'NG' => 1, 'PH' => 1,
];
$generoPesos = ['Femenino' => 48, 'Masculino' => 48, 'No especifica' => 4];
$monedaPorPais = array_map(static fn ($p) => $p['moneda'], $catalogo['paises']);

/** @return array{pais_codigo:string, ciudad:string, idioma:string, genero:string, fecha_nacimiento:string, moneda:string} */
function perfilAleatorio(array $paisPesos, array $ciudadesPorPais, array $idiomaPorPais, array $monedaPorPais, array $generoPesos, DateTimeImmutable $hoy): array
{
    $paisCodigo = eleccionPonderada($paisPesos);
    $edad = random_int(18, 68);
    $nacimiento = $hoy->modify("-{$edad} years")->modify('-' . random_int(0, 364) . ' days');
    $ciudades = $ciudadesPorPais[$paisCodigo];

    return [
        'pais_codigo' => $paisCodigo,
        'ciudad' => $ciudades[array_rand($ciudades)],
        'idioma' => $idiomaPorPais[$paisCodigo],
        'genero' => eleccionPonderada($generoPesos),
        'fecha_nacimiento' => fecha($nacimiento),
        'moneda' => $monedaPorPais[$paisCodigo],
    ];
}

$insCliente = $pdo->prepare(
    'INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
     VALUES (:nombre, :email, :segmento, :fecha_alta, :pais_codigo, :ciudad, :idioma, :genero, :fecha_nacimiento)
     RETURNING id'
);
$insUsuario = $pdo->prepare(
    'INSERT INTO usuarios_funnel (nombre, email, canal, pais_codigo, ciudad, idioma, genero, fecha_nacimiento,
                                  fecha_visita, fecha_registro, fecha_lead, fecha_conversion, cliente_id)
     VALUES (:nombre, :email, :canal, :pais_codigo, :ciudad, :idioma, :genero, :fecha_nacimiento,
             :fecha_visita, :fecha_registro, :fecha_lead, :fecha_conversion, :cliente_id)'
);
$insBoleta = $pdo->prepare(
    'INSERT INTO boletas (cliente_id, concepto, monto, moneda_codigo, fecha_emision, fecha_vencimiento)
     VALUES (:cliente_id, :concepto, :monto, :moneda_codigo, :fecha_emision, :fecha_vencimiento)
     RETURNING id'
);
$insPago = $pdo->prepare(
    'INSERT INTO pagos (boleta_id, cliente_id, monto, moneda_codigo, fecha_pago, metodo)
     VALUES (:boleta_id, :cliente_id, :monto, :moneda_codigo, :fecha_pago, :metodo)
     RETURNING id'
);

$pdo->beginTransaction();

// 1) Base de clientes "legacy": ya existian antes de que se empezara a medir el funnel.
$clientesInfo = [];
$nombresUsados = [];
$inicioLegacy = $hoy->modify('-14 months');
for ($i = 0; $i < 18; $i++) {
    [$nombre] = nombreUnico($nombresUsados, $nombresPila, $apellidos);
    $nombresUsados[$nombre] = true;
    $email = emailUnico($nombre, $dominios, $emailsUsados);
    $altaLegacy = diasAleatorios($inicioLegacy, 150);
    $perfil = perfilAleatorio($paisPesos, $ciudadesPorPais, $idiomaPorPais, $monedaPorPais, $generoPesos, $hoy);
    $insCliente->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':segmento' => $segmentos[array_rand($segmentos)],
        ':fecha_alta' => fecha($altaLegacy),
        ':pais_codigo' => $perfil['pais_codigo'],
        ':ciudad' => $perfil['ciudad'],
        ':idioma' => $perfil['idioma'],
        ':genero' => $perfil['genero'],
        ':fecha_nacimiento' => $perfil['fecha_nacimiento'],
    ]);
    $id = (int) $insCliente->fetchColumn();
    $clientesInfo[] = ['id' => $id, 'fecha_alta' => fecha($altaLegacy), 'moneda' => $perfil['moneda']];
}

// 2) Funnel de conversion: visitantes -> registro -> lead -> cliente, ultimos 6 meses.
$inicioFunnel = $hoy->modify('-6 months');
$rangoFunnelDias = (int) (($hoy->getTimestamp() - $inicioFunnel->getTimestamp()) / DIA);

for ($i = 0; $i < 320; $i++) {
    [$nombre] = nombreUnico($nombresUsados, $nombresPila, $apellidos);
    $nombresUsados[$nombre] = true;
    $email = emailUnico($nombre, $dominios, $emailsUsados);
    $canal = eleccionPonderada($canalPesos);
    $perfil = perfilAleatorio($paisPesos, $ciudadesPorPais, $idiomaPorPais, $monedaPorPais, $generoPesos, $hoy);

    $fVisita = diasAleatorios($inicioFunnel, $rangoFunnelDias);
    $fRegistro = $fLead = $fConversion = null;
    $clienteId = null;

    if (mt_rand(1, 100) <= 55) {
        $fRegistro = diasAleatorios($fVisita, 5);
        if (mt_rand(1, 100) <= 50) {
            $fLead = diasAleatorios($fRegistro, 10);
            if (mt_rand(1, 100) <= 45 && $fLead < $hoy) {
                $fConversion = diasAleatorios($fLead, 14);
                if ($fConversion > $hoy) {
                    $fConversion = $hoy;
                }
                $insCliente->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':segmento' => $segmentos[array_rand($segmentos)],
                    ':fecha_alta' => fecha($fConversion),
                    ':pais_codigo' => $perfil['pais_codigo'],
                    ':ciudad' => $perfil['ciudad'],
                    ':idioma' => $perfil['idioma'],
                    ':genero' => $perfil['genero'],
                    ':fecha_nacimiento' => $perfil['fecha_nacimiento'],
                ]);
                $clienteId = (int) $insCliente->fetchColumn();
                $clientesInfo[] = ['id' => $clienteId, 'fecha_alta' => fecha($fConversion), 'moneda' => $perfil['moneda']];
            }
        }
    }

    $insUsuario->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':canal' => $canal,
        ':pais_codigo' => $perfil['pais_codigo'],
        ':ciudad' => $perfil['ciudad'],
        ':idioma' => $perfil['idioma'],
        ':genero' => $perfil['genero'],
        ':fecha_nacimiento' => $perfil['fecha_nacimiento'],
        ':fecha_visita' => fecha($fVisita),
        ':fecha_registro' => $fRegistro ? fecha($fRegistro) : null,
        ':fecha_lead' => $fLead ? fecha($fLead) : null,
        ':fecha_conversion' => $fConversion ? fecha($fConversion) : null,
        ':cliente_id' => $clienteId,
    ]);
}

// 3) Boletas (ingresos devengados) y pagos del usuario (cobros/caja) por cliente.
// Cada boleta y sus pagos quedan en la moneda del pais del cliente.
$boletaIds = [];
$pagoIds = [];
foreach ($clientesInfo as $cliente) {
    $altaCliente = new DateTimeImmutable($cliente['fecha_alta']);
    if ($altaCliente >= $hoy) {
        continue;
    }
    $numBoletas = random_int(1, 6);
    $cursor = $altaCliente;

    for ($f = 0; $f < $numBoletas; $f++) {
        if ($cursor >= $hoy) {
            break;
        }
        $emision = diasAleatorios($cursor, 25);
        if ($emision >= $hoy) {
            break;
        }
        $vencimiento = $emision->modify('+30 days');
        $monto = round(mt_rand(15000, 320000) / 100, 2);
        $concepto = $conceptos[array_rand($conceptos)];

        $insBoleta->execute([
            ':cliente_id' => $cliente['id'],
            ':concepto' => $concepto,
            ':monto' => $monto,
            ':moneda_codigo' => $cliente['moneda'],
            ':fecha_emision' => fecha($emision),
            ':fecha_vencimiento' => fecha($vencimiento),
        ]);
        $boletaId = (int) $insBoleta->fetchColumn();
        $boletaIds[] = $boletaId;

        $comportamiento = eleccionPonderada(['pagada' => 70, 'parcial' => 15, 'pendiente' => 15]);
        if ($comportamiento !== 'pendiente') {
            $montoPago = $comportamiento === 'pagada'
                ? $monto
                : round($monto * (mt_rand(30, 70) / 100), 2);
            $limitePago = min($hoy, $vencimiento->modify('+20 days'));
            $fPago = diasAleatorios($emision, max(1, (int) (($limitePago->getTimestamp() - $emision->getTimestamp()) / DIA)));
            if ($fPago <= $hoy) {
                $insPago->execute([
                    ':boleta_id' => $boletaId,
                    ':cliente_id' => $cliente['id'],
                    ':monto' => $montoPago,
                    ':moneda_codigo' => $cliente['moneda'],
                    ':fecha_pago' => fecha($fPago),
                    ':metodo' => eleccionPonderada($metodoPesos),
                ]);
                $pagoIds[] = (int) $insPago->fetchColumn();
            }
        }

        $cursor = $emision->modify('+' . random_int(20, 45) . ' days');
    }
}

// 4) Un puñado de anticipos / pagos sueltos no ligados a una boleta puntual.
for ($i = 0; $i < 10; $i++) {
    $cliente = $clientesInfo[array_rand($clientesInfo)];
    $fPago = diasAleatorios($hoy->modify('-90 days'), 90);
    $insPago->execute([
        ':boleta_id' => null,
        ':cliente_id' => $cliente['id'],
        ':monto' => round(mt_rand(5000, 60000) / 100, 2),
        ':moneda_codigo' => $cliente['moneda'],
        ':fecha_pago' => fecha($fPago),
        ':metodo' => eleccionPonderada($metodoPesos),
    ]);
    $pagoIds[] = (int) $insPago->fetchColumn();
}

// 5) Anular un par de boletas y pagos de ejemplo, para poder ver la insignia
// "Anulada" y probar el filtro sin tener que anular nada a mano primero.
$anulBoleta = $pdo->prepare('UPDATE boletas SET anulada = TRUE WHERE id = :id');
foreach (array_slice($boletaIds, 4, 2) as $id) {
    $anulBoleta->execute([':id' => $id]);
}
$anulPago = $pdo->prepare('UPDATE pagos SET anulada = TRUE WHERE id = :id');
foreach (array_slice($pagoIds, 4, 2) as $id) {
    $anulPago->execute([':id' => $id]);
}

$pdo->commit();

$totalPaises = $pdo->query('SELECT COUNT(*) FROM paises')->fetchColumn();
$totalMonedas = $pdo->query('SELECT COUNT(*) FROM monedas')->fetchColumn();
$totalClientes = $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
$totalUsuarios = $pdo->query('SELECT COUNT(*) FROM usuarios_funnel')->fetchColumn();
$totalBoletas = $pdo->query('SELECT COUNT(*) FROM boletas')->fetchColumn();
$totalPagos = $pdo->query('SELECT COUNT(*) FROM pagos')->fetchColumn();
$boletasAnuladas = $pdo->query('SELECT COUNT(*) FROM boletas WHERE anulada')->fetchColumn();
$pagosAnulados = $pdo->query('SELECT COUNT(*) FROM pagos WHERE anulada')->fetchColumn();

echo "Seed completado:\n";
echo "  paises:          {$totalPaises}\n";
echo "  monedas:         {$totalMonedas}\n";
echo "  clientes:        {$totalClientes}\n";
echo "  usuarios_funnel: {$totalUsuarios}\n";
echo "  boletas:         {$totalBoletas} ({$boletasAnuladas} anuladas)\n";
echo "  pagos:           {$totalPagos} ({$pagosAnulados} anulados)\n";
echo "\nLogin: {$emailAdmin} / {$passwordAdmin}\n";
echo "(tambien: soporte@ejemplo.com / soporte1234)\n";
