<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use DateTimeImmutable;
use PDO;

final class MonedaRepository
{
    /** Cuantos dias puede tener una tasa real antes de que las pantallas avisen que esta vieja. */
    public const DIAS_DE_VIGENCIA = 7;

    /** @var array<string, string>|null */
    private static ?array $simbolos = null;

    /** @return array<string, string> codigo => simbolo */
    public static function simbolos(): array
    {
        if (self::$simbolos === null) {
            self::$simbolos = [];
            $rows = Database::connection()->query('SELECT codigo, simbolo FROM monedas')->fetchAll();
            foreach ($rows as $row) {
                self::$simbolos[$row['codigo']] = $row['simbolo'];
            }
        }
        return self::$simbolos;
    }

    public static function simbolo(string $codigo): string
    {
        return self::simbolos()[$codigo] ?? $codigo;
    }

    /**
     * Que tan reales son las tasas con las que se consolidan los totales en USD:
     *
     * - ultima y fuente: la cotizacion mas reciente que cargo
     *   database/actualizar_tasas.php y de donde salio. null si nunca corrio: todas
     *   las tasas son las de ejemplo de la migracion 005.
     * - pendientes: las monedas con boletas o pagos cuya tasa sigue siendo la de
     *   ejemplo, o lleva mas de DIAS_DE_VIGENCIA dias sin actualizarse: sus montos
     *   en USD no son reales. Vacio si nunca corrio (ahi son todas, no hace falta
     *   listarlas) o si todo esta al dia.
     *
     * @return array{ultima: ?DateTimeImmutable, fuente: ?string, pendientes: list<string>}
     */
    public static function estadoDeLasTasas(): array
    {
        $db = Database::connection();
        $ultima = $db->query(
            'SELECT tasa_actualizada_en, tasa_fuente FROM monedas
             WHERE tasa_actualizada_en IS NOT NULL
             ORDER BY tasa_actualizada_en DESC LIMIT 1'
        )->fetch();
        if ($ultima === false) {
            return ['ultima' => null, 'fuente' => null, 'pendientes' => []];
        }

        // Primero las candidatas (ejemplo o vieja, que son pocas cuando todo esta al dia); solo si hay alguna se mira cuales se usan.
        $candidatas = $db->prepare(
            "SELECT codigo FROM monedas
             WHERE codigo <> 'USD' AND (tasa_actualizada_en IS NULL OR tasa_actualizada_en < now() - make_interval(days => :dias))
             ORDER BY codigo"
        );
        $candidatas->execute([':dias' => self::DIAS_DE_VIGENCIA]);
        /** @var list<string> $codigos */
        $codigos = $candidatas->fetchAll(PDO::FETCH_COLUMN);

        $pendientes = [];
        if ($codigos !== []) {
            $enUso = $db->prepare(
                'SELECT c.codigo FROM unnest(:codigos::char(3)[]) AS c(codigo)
                 WHERE EXISTS (SELECT 1 FROM boletas b WHERE b.moneda_codigo = c.codigo)
                    OR EXISTS (SELECT 1 FROM pagos p WHERE p.moneda_codigo = c.codigo)
                 ORDER BY c.codigo'
            );
            // Son codigos de moneda de la propia tabla (tres letras): armar el literal del array es seguro.
            $enUso->execute([':codigos' => '{' . implode(',', $codigos) . '}']);
            /** @var list<string> $pendientes */
            $pendientes = $enUso->fetchAll(PDO::FETCH_COLUMN);
        }

        return [
            'ultima' => new DateTimeImmutable((string) $ultima['tasa_actualizada_en']),
            'fuente' => $ultima['tasa_fuente'] === null ? null : (string) $ultima['tasa_fuente'],
            'pendientes' => $pendientes,
        ];
    }
}
