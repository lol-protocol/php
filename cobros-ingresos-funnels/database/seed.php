<?php

declare(strict_types=1);

/**
 * Reconstruye el esquema y carga datos de ejemplo reproducibles.
 * Uso: php database/seed.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

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

$geo = [
    'Mexico' => ['idioma' => 'Espanol', 'ciudades' => ['Ciudad de Mexico', 'Guadalajara', 'Monterrey']],
    'Colombia' => ['idioma' => 'Espanol', 'ciudades' => ['Bogota', 'Medellin']],
    'Argentina' => ['idioma' => 'Espanol', 'ciudades' => ['Buenos Aires', 'Cordoba']],
    'Chile' => ['idioma' => 'Espanol', 'ciudades' => ['Santiago']],
    'Peru' => ['idioma' => 'Espanol', 'ciudades' => ['Lima']],
    'Espana' => ['idioma' => 'Espanol', 'ciudades' => ['Madrid', 'Barcelona']],
    'Estados Unidos' => ['idioma' => 'Ingles', 'ciudades' => ['Miami', 'Los Angeles']],
    'Brasil' => ['idioma' => 'Portugues', 'ciudades' => ['Sao Paulo', 'Rio de Janeiro']],
];
$paisPesos = ['Mexico' => 28, 'Colombia' => 18, 'Argentina' => 14, 'Chile' => 10,
    'Peru' => 8, 'Espana' => 10, 'Estados Unidos' => 8, 'Brasil' => 4];
$generoPesos = ['Femenino' => 48, 'Masculino' => 48, 'No especifica' => 4];

/** @return array{pais:string, ciudad:string, idioma:string, genero:string, fecha_nacimiento:string} */
function perfilAleatorio(array $geo, array $paisPesos, array $generoPesos, DateTimeImmutable $hoy): array
{
    $pais = eleccionPonderada($paisPesos);
    $info = $geo[$pais];
    $edad = random_int(18, 68);
    $nacimiento = $hoy->modify("-{$edad} years")->modify('-' . random_int(0, 364) . ' days');

    return [
        'pais' => $pais,
        'ciudad' => $info['ciudades'][array_rand($info['ciudades'])],
        'idioma' => $info['idioma'],
        'genero' => eleccionPonderada($generoPesos),
        'fecha_nacimiento' => fecha($nacimiento),
    ];
}

$insCliente = $pdo->prepare(
    'INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais, ciudad, idioma, genero, fecha_nacimiento)
     VALUES (:nombre, :email, :segmento, :fecha_alta, :pais, :ciudad, :idioma, :genero, :fecha_nacimiento)'
);
$insUsuario = $pdo->prepare(
    'INSERT INTO usuarios_funnel (nombre, email, canal, pais, ciudad, idioma, genero, fecha_nacimiento,
                                  fecha_visita, fecha_registro, fecha_lead, fecha_conversion, cliente_id)
     VALUES (:nombre, :email, :canal, :pais, :ciudad, :idioma, :genero, :fecha_nacimiento,
             :fecha_visita, :fecha_registro, :fecha_lead, :fecha_conversion, :cliente_id)'
);
$insFactura = $pdo->prepare(
    'INSERT INTO facturas (cliente_id, concepto, monto, fecha_emision, fecha_vencimiento)
     VALUES (:cliente_id, :concepto, :monto, :fecha_emision, :fecha_vencimiento)'
);
$insPago = $pdo->prepare(
    'INSERT INTO pagos (factura_id, cliente_id, monto, fecha_pago, metodo) VALUES (:factura_id, :cliente_id, :monto, :fecha_pago, :metodo)'
);

$pdo->beginTransaction();

// 1) Base de clientes "legacy": ya existian antes de que se empezara a medir el funnel.
$clienteIds = [];
$nombresUsados = [];
$inicioLegacy = $hoy->modify('-14 months');
for ($i = 0; $i < 18; $i++) {
    [$nombre] = nombreUnico($nombresUsados, $nombresPila, $apellidos);
    $nombresUsados[$nombre] = true;
    $email = emailUnico($nombre, $dominios, $emailsUsados);
    $altaLegacy = diasAleatorios($inicioLegacy, 150);
    $perfil = perfilAleatorio($geo, $paisPesos, $generoPesos, $hoy);
    $insCliente->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':segmento' => $segmentos[array_rand($segmentos)],
        ':fecha_alta' => fecha($altaLegacy),
        ':pais' => $perfil['pais'],
        ':ciudad' => $perfil['ciudad'],
        ':idioma' => $perfil['idioma'],
        ':genero' => $perfil['genero'],
        ':fecha_nacimiento' => $perfil['fecha_nacimiento'],
    ]);
    $clienteIds[] = (int) $pdo->lastInsertId();
}

// 2) Funnel de conversion: visitantes -> registro -> lead -> cliente, ultimos 6 meses.
$inicioFunnel = $hoy->modify('-6 months');
$rangoFunnelDias = (int) (($hoy->getTimestamp() - $inicioFunnel->getTimestamp()) / DIA);

for ($i = 0; $i < 320; $i++) {
    [$nombre] = nombreUnico($nombresUsados, $nombresPila, $apellidos);
    $nombresUsados[$nombre] = true;
    $email = emailUnico($nombre, $dominios, $emailsUsados);
    $canal = eleccionPonderada($canalPesos);
    $perfil = perfilAleatorio($geo, $paisPesos, $generoPesos, $hoy);

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
                    ':pais' => $perfil['pais'],
                    ':ciudad' => $perfil['ciudad'],
                    ':idioma' => $perfil['idioma'],
                    ':genero' => $perfil['genero'],
                    ':fecha_nacimiento' => $perfil['fecha_nacimiento'],
                ]);
                $clienteId = (int) $pdo->lastInsertId();
                $clienteIds[] = $clienteId;
            }
        }
    }

    $insUsuario->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':canal' => $canal,
        ':pais' => $perfil['pais'],
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

// 3) Facturacion (ingresos devengados) y pagos (cobros/caja) por cliente.
$clientesInfo = $pdo->query('SELECT id, fecha_alta FROM clientes')->fetchAll();

foreach ($clientesInfo as $cliente) {
    $altaCliente = new DateTimeImmutable($cliente['fecha_alta']);
    if ($altaCliente >= $hoy) {
        continue;
    }
    $numFacturas = random_int(1, 6);
    $cursor = $altaCliente;

    for ($f = 0; $f < $numFacturas; $f++) {
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

        $insFactura->execute([
            ':cliente_id' => $cliente['id'],
            ':concepto' => $concepto,
            ':monto' => $monto,
            ':fecha_emision' => fecha($emision),
            ':fecha_vencimiento' => fecha($vencimiento),
        ]);
        $facturaId = (int) $pdo->lastInsertId();

        $comportamiento = eleccionPonderada(['pagada' => 70, 'parcial' => 15, 'pendiente' => 15]);
        if ($comportamiento !== 'pendiente') {
            $montoPago = $comportamiento === 'pagada'
                ? $monto
                : round($monto * (mt_rand(30, 70) / 100), 2);
            $limitePago = min($hoy, $vencimiento->modify('+20 days'));
            $fPago = diasAleatorios($emision, max(1, (int) (($limitePago->getTimestamp() - $emision->getTimestamp()) / DIA)));
            if ($fPago <= $hoy) {
                $insPago->execute([
                    ':factura_id' => $facturaId,
                    ':cliente_id' => $cliente['id'],
                    ':monto' => $montoPago,
                    ':fecha_pago' => fecha($fPago),
                    ':metodo' => eleccionPonderada($metodoPesos),
                ]);
            }
        }

        $cursor = $emision->modify('+' . random_int(20, 45) . ' days');
    }
}

// 4) Un puñado de anticipos / pagos sueltos no ligados a una factura puntual.
for ($i = 0; $i < 10; $i++) {
    $cliente = $clienteIds[array_rand($clienteIds)];
    $fPago = diasAleatorios($hoy->modify('-90 days'), 90);
    $insPago->execute([
        ':factura_id' => null,
        ':cliente_id' => $cliente,
        ':monto' => round(mt_rand(5000, 60000) / 100, 2),
        ':fecha_pago' => fecha($fPago),
        ':metodo' => eleccionPonderada($metodoPesos),
    ]);
}

$pdo->commit();

$totalClientes = $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
$totalUsuarios = $pdo->query('SELECT COUNT(*) FROM usuarios_funnel')->fetchColumn();
$totalFacturas = $pdo->query('SELECT COUNT(*) FROM facturas')->fetchColumn();
$totalPagos = $pdo->query('SELECT COUNT(*) FROM pagos')->fetchColumn();

echo "Seed completado:\n";
echo "  clientes:        {$totalClientes}\n";
echo "  usuarios_funnel: {$totalUsuarios}\n";
echo "  facturas:        {$totalFacturas}\n";
echo "  pagos:           {$totalPagos}\n";
