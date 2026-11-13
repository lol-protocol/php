<?php
/**
 * Importador de CSV a MySQL / MariaDB, compartido por privacy-laws/ y copyright-laws/.
 *
 * Cada módulo conserva su `import_database.php` como envoltorio: dice solo lo suyo (nombres de la base y
 * de las tablas, columnas, y su validación propia) y llama a `ImportadorLeyes::ejecutar()`. Lo que los
 * dos hacían igual vive aquí una vez: la conexión, el esquema, la lectura del CSV, la transacción, los
 * avisos y los comandos.
 *
 * Conexión: DB_HOST (localhost), DB_PORT (3306), DB_USER (root), DB_PASSWORD (vacía).
 *
 * Comandos:
 *   --create-schema --yes   borra y vuelve a crear la base (sin --yes se niega, antes de conectarse)
 *   --import                reemplaza el contenido de las tablas con el CSV (se puede repetir)
 *   --validate              comprueba la base
 *   --all --yes             las tres cosas
 *
 * Configuración ($config):
 *   titulo, db, csv, esquema   nombre para mostrar, base, ruta del CSV y del esquema SQL
 *   padre, clavePadre, unidad  tabla de países/jurisdicciones, su clave, y cómo llamarlas en los mensajes
 *   ley, leyes                 tabla de leyes y cómo llamarlas en los mensajes
 *   columnas                   columnas del CSV que se insertan en la tabla de leyes, en orden
 *   obligatorias               las que tienen que estar en la cabecera del CSV (el resto puede faltar)
 *   fechas                     columnas de fecha: vacío o N/A es NULL; otra cosa que no sea AAAA-MM-DD, un error
 *   porDefecto                 valor de una columna que falta en la cabecera del CSV (data_status => curated)
 *   campoNoVacio               columna que se avisa si queda vacía en la validación
 *   validacionExtra            función opcional (PDO, callable $log): lo que valida solo ese módulo
 */
class ImportadorLeyes
{
    private ?PDO $pdo = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Punto de entrada del envoltorio: `ImportadorLeyes::ejecutar($config, $argv);`
     */
    public static function ejecutar(array $config, array $argv): void
    {
        $importador = new self($config);
        $args = array_slice($argv, 1);
        $confirmado = in_array('--yes', $args, true);
        $args = array_values(array_filter($args, fn ($a) => $a !== '--yes'));
        $accion = $args[0] ?? 'help';

        if (in_array($accion, ['--create-schema', '--all'], true) && !$confirmado) {
            $importador->error(
                "$accion borra y vuelve a crear la base {$config['db']} en "
                . (getenv('DB_HOST') ?: 'localhost') . ':' . (getenv('DB_PORT') ?: '3306')
                . ". Si es lo que quieres, repítelo con --yes.",
                2
            );
        }

        switch ($accion) {
            case '--create-schema':
                $importador->crearEsquema();
                break;
            case '--import':
                $importador->importar();
                break;
            case '--validate':
                $importador->validar();
                break;
            case '--all':
                $importador->crearEsquema();
                $importador->importar();
                $importador->validar();
                break;
            default:
                echo "{$config['titulo']} Database Importer\n\n";
                echo "Usage:\n";
                echo "  php import_database.php --create-schema --yes   Drop and recreate the database schema\n";
                echo "  php import_database.php --import                Replace the tables' contents with the CSV\n";
                echo "  php import_database.php --validate              Validate data integrity\n";
                echo "  php import_database.php --all --yes             All of the above\n";
                echo "\nConnection: DB_HOST (localhost), DB_PORT (3306), DB_USER (root), DB_PASSWORD (empty).\n";
        }
    }

    /**
     * AAAA-MM-DD → AAAA-MM-DD; vacío o N/A → null. Cualquier otra cosa lanza una excepción: antes una
     * fecha mal escrita se convertía en NULL sin avisar.
     */
    public static function parseFecha(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || $valor === 'N/A') {
            return null;
        }
        $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
        $errores = DateTime::getLastErrors();
        if ($fecha === false || ($errores && ($errores['warning_count'] || $errores['error_count'])) || $fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException("fecha no válida «$valor» (se espera AAAA-MM-DD)");
        }
        return $valor;
    }

    private function conectar(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        try {
            $this->pdo = new PDO(
                "mysql:host=$host;port=$port;charset=utf8mb4",
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASSWORD') ?: '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
        }
        return $this->pdo;
    }

    public function crearEsquema(): bool
    {
        $c = $this->config;
        if (!file_exists($c['esquema'])) {
            $this->error("Schema file not found: {$c['esquema']}");
        }
        $this->log('📋 Creating database schema...');
        try {
            $pdo = $this->conectar();
            $pdo->exec("DROP DATABASE IF EXISTS {$c['db']}");
            $this->log('  ✓ Dropped existing database');
            foreach (preg_split('/;(?=\s*$)/m', file_get_contents($c['esquema'])) as $sentencia) {
                $sentencia = trim($sentencia);
                if ($sentencia !== '') {
                    $pdo->exec($sentencia);
                }
            }
            $this->log('  ✓ Schema created successfully');
            return true;
        } catch (Exception $e) {
            $this->error('Schema creation failed: ' . $e->getMessage());
        }
    }

    /** Filas de un CSV que está junto al maestro, con la cabecera como claves; si falta el archivo, ninguna. */
    private function leerLateral(string $archivo): array
    {
        $ruta = dirname($this->config['csv']) . '/' . $archivo;
        if (!file_exists($ruta)) {
            return [];
        }
        $f = fopen($ruta, 'r');
        $cabecera = fgetcsv($f);
        $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]);
        $filas = [];
        while (($fila = fgetcsv($f)) !== false) {
            if ($fila !== [null]) {
                $filas[] = array_combine($cabecera, $fila);
            }
        }
        fclose($f);
        return $filas;
    }

    /**
     * field_sources.csv (con source_urls.csv), changes.csv y value_translations.csv, dentro de la transacción de la importación: de
     * dónde sale cada valor, qué cambió y cuándo, y las traducciones. Un law_name vacío es la única ley del
     * país. Devuelve cuántas filas entraron de cada uno.
     */
    private function importarLaterales(PDO $pdo, array $leyesId): array
    {
        $leyDe = function (array $fila, string $archivo) use ($leyesId): int {
            $leyes = $leyesId[$fila['country_code']] ?? [];
            if ($fila['law_name'] === '' && count($leyes) === 1) {
                return (int) reset($leyes);
            }
            if (!isset($leyes[$fila['law_name']])) {
                throw new Exception("$archivo: no law {$fila['country_code']} \"{$fila['law_name']}\" in the master CSV");
            }
            return $leyes[$fila['law_name']];
        };

        // field_sources.csv apunta a su URL con source_id; source_urls.csv guarda cada URL una vez.
        $urls = [];
        foreach ($this->leerLateral('source_urls.csv') as $u) {
            $urls[$u['source_id']] = $u['url'];
        }
        $insertar = $pdo->prepare('INSERT INTO field_sources (law_id, field, url, confidence, checked) VALUES (?, ?, ?, ?, ?)');
        $fuentes = $this->leerLateral('field_sources.csv');
        foreach ($fuentes as $f) {
            if (!isset($urls[$f['source_id']])) {
                throw new Exception("field_sources.csv: source_id {$f['source_id']} is not in source_urls.csv");
            }
            $insertar->execute([$leyDe($f, 'field_sources.csv'), $f['field'], $urls[$f['source_id']], $f['confidence'], $f['checked'] !== '' ? self::parseFecha($f['checked']) : null]);
        }

        $insertar = $pdo->prepare('INSERT INTO law_changes (changed_on, jurisdiction_code, law_name, field, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)');
        $cambios = $this->leerLateral('changes.csv');
        foreach ($cambios as $f) {
            $insertar->execute([self::parseFecha($f['date']), $f['country_code'], $f['law_name'], $f['field'], $f['old_value'], $f['new_value']]);
        }

        $insertar = $pdo->prepare('INSERT INTO value_translations (lang, source_text, translated_text) VALUES (?, ?, ?)');
        $traducciones = $this->leerLateral('value_translations.csv');
        foreach ($traducciones as $f) {
            $insertar->execute([$f['lang'], $f['source'], $f['text']]);
        }

        return [count($fuentes), count($cambios), count($traducciones)];
    }

    public function importar(): bool
    {
        $c = $this->config;
        if (!file_exists($c['csv'])) {
            $this->error("CSV file not found: {$c['csv']}");
        }
        $this->log('📥 Importing data from CSV...');
        $pdo = $this->conectar();
        try {
            $pdo->exec("USE {$c['db']}");

            // Todo o nada y repetible: las tablas quedan con exactamente el CSV, nunca a medias ni con las
            // mismas filas dos veces. Las tablas hijas se van con su ley (ON DELETE CASCADE).
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM law_changes');
            $pdo->exec('DELETE FROM value_translations');
            $pdo->exec("DELETE FROM {$c['ley']}");
            $pdo->exec("DELETE FROM {$c['padre']}");

            $archivo = fopen($c['csv'], 'r');
            if (!$archivo) {
                $this->error("Cannot open CSV file: {$c['csv']}");
            }
            $cabecera = fgetcsv($archivo);
            if ($cabecera === false) {
                $this->error("CSV file is empty: {$c['csv']}");
            }
            // Un BOM de UTF-8 acabaría en el nombre de la primera columna.
            $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]);
            $faltan = array_diff($c['obligatorias'], $cabecera);
            if ($faltan) {
                $this->error('The CSV header lacks: ' . implode(', ', $faltan));
            }

            $insertarPadre = $pdo->prepare("INSERT IGNORE INTO {$c['padre']} (code, name, tld, region) VALUES (?, ?, ?, ?)");
            $buscarPadre = $pdo->prepare("SELECT {$c['clavePadre']} FROM {$c['padre']} WHERE code = ?");
            $columnas = $c['columnas'];
            $insertarLey = $pdo->prepare(
                "INSERT INTO {$c['ley']} ({$c['clavePadre']}, " . implode(', ', $columnas) . ') VALUES (?'
                . str_repeat(', ?', count($columnas)) . ')'
            );

            $linea = 1;
            $importadas = 0;
            $padres = [];
            $leyesId = []; // código => [nombre de la ley => id], para los archivos laterales
            while (($fila = fgetcsv($archivo)) !== false) {
                $linea++;
                if ($fila === [null]) {
                    continue; // línea en blanco (las herramientas de Node también las saltan)
                }
                if (count($fila) !== count($cabecera)) {
                    $this->error("Line $linea: expected " . count($cabecera) . ' columns, got ' . count($fila));
                }
                $dato = array_combine($cabecera, $fila);

                $codigo = trim($dato['country_code']);
                if (!isset($padres[$codigo])) {
                    $insertarPadre->execute([$codigo, $dato['country_name'], strtolower($codigo), trim($dato['region'])]);
                    $buscarPadre->execute([$codigo]);
                    $padres[$codigo] = $buscarPadre->fetch()[$c['clavePadre']];
                }

                $valores = [$padres[$codigo]];
                foreach ($columnas as $columna) {
                    $valor = $dato[$columna] ?? ($c['porDefecto'][$columna] ?? '');
                    if (in_array($columna, $c['fechas'], true)) {
                        try {
                            $valor = self::parseFecha($valor);
                        } catch (InvalidArgumentException $e) {
                            $this->error("Line $linea, column $columna: " . $e->getMessage());
                        }
                    }
                    $valores[] = $valor;
                }
                $insertarLey->execute($valores);
                $leyesId[$codigo][$dato['law_name']] = (int) $pdo->lastInsertId();
                $importadas++;
            }
            fclose($archivo);
            [$fuentes, $cambios, $traducciones] = $this->importarLaterales($pdo, $leyesId);
            $pdo->commit();

            $this->log("  ✓ Imported {$importadas} {$c['leyes']} from " . count($padres) . " {$c['unidad']}");
            $this->log("  ✓ Imported {$fuentes} field sources, {$cambios} recorded changes and {$traducciones} translations");
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->error('Import failed: ' . $e->getMessage());
        }
    }

    public function validar(): bool
    {
        $c = $this->config;
        $this->log('✅ Validating data...');
        try {
            $pdo = $this->conectar();
            $pdo->exec("USE {$c['db']}");
            $cuenta = fn (string $sql) => (int) $pdo->query($sql)->fetch()['count'];

            $this->log("  ✓ {$c['unidad']}: " . $cuenta("SELECT COUNT(*) AS count FROM {$c['padre']}"));
            $this->log("  ✓ {$c['leyes']}: " . $cuenta("SELECT COUNT(*) AS count FROM {$c['ley']}"));

            $huerfanas = $cuenta(
                "SELECT COUNT(*) AS count FROM {$c['ley']} WHERE {$c['clavePadre']} NOT IN (SELECT {$c['clavePadre']} FROM {$c['padre']})"
            );
            if ($huerfanas > 0) {
                $this->warn("  ⚠ Orphaned laws found: $huerfanas");
            } else {
                $this->log('  ✓ No orphaned records');
            }

            $vacias = $cuenta(
                "SELECT COUNT(*) AS count FROM {$c['ley']} WHERE {$c['campoNoVacio']} IS NULL OR {$c['campoNoVacio']} = ''"
            );
            if ($vacias > 0) {
                $this->warn("  ⚠ Missing {$c['campoNoVacio']}: $vacias");
            }

            foreach ($pdo->query("SELECT region, COUNT(*) AS count FROM {$c['padre']} GROUP BY region ORDER BY region")->fetchAll() as $r) {
                $this->log("  ✓ {$r['region']}: {$r['count']} {$c['unidad']}");
            }

            if (isset($c['validacionExtra'])) {
                ($c['validacionExtra'])($pdo, fn (string $m) => $this->log($m));
            }

            $this->log('  ✓ Validation complete!');
            return true;
        } catch (Exception $e) {
            $this->error('Validation failed: ' . $e->getMessage());
        }
    }

    private function log(string $mensaje): void
    {
        echo '[' . date('H:i:s') . "] $mensaje\n";
    }

    private function warn(string $mensaje): void
    {
        echo "\033[33m[" . date('H:i:s') . "] $mensaje\033[0m\n";
    }

    private function error(string $mensaje, int $codigo = 1): never
    {
        echo "\033[31m[" . date('H:i:s') . "] ERROR: $mensaje\033[0m\n";
        exit($codigo);
    }
}
