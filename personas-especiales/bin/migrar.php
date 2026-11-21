<?php

declare(strict_types=1);

// Aplica las migraciones pendientes de database/migraciones/ a PostgreSQL.
//   PE_DSN="pgsql:host=127.0.0.1;dbname=pe" PE_USER=... PE_PASSWORD=... php bin/migrar.php

require __DIR__ . '/../vendor/autoload.php';

use PersonasEspeciales\Migrador;

$dsn = getenv('PE_DSN');
if ($dsn === false || $dsn === '') {
    fwrite(STDERR, "Falta PE_DSN (ej. pgsql:host=127.0.0.1;dbname=pe).\n");
    exit(2);
}

$pdo = new PDO($dsn, getenv('PE_USER') ?: null, getenv('PE_PASSWORD') ?: null);
$aplicadas = (new Migrador($pdo, __DIR__ . '/../database/migraciones'))->aplicar();

if ($aplicadas === []) {
    echo "sin migraciones pendientes\n";
    exit(0);
}
foreach ($aplicadas as $nombre) {
    echo "aplicada {$nombre}\n";
}
