<?php

declare(strict_types=1);

namespace App\Tasas;

use App\Database;
use App\Repositories\AuditoriaRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Pasa las tasas de una fuente (RespuestaDeTasas) a monedas.tasa_a_usd, que es
 * lo que usan todos los totales en USD. La fuente da cuantas unidades de cada
 * moneda valen 1 USD; la base guarda cuanto vale 1 unidad en USD: es la inversa.
 *
 * Los totales de todas las pantallas dependen de estas tasas, asi que se
 * escribe solo lo que se puede creer, y todo o nada:
 *
 * - La fuente tiene que traer al menos la mitad de las monedas del catalogo, y
 *   datos de menos de una semana. Una respuesta vacia o vieja no pisa nada.
 * - Una tasa con un valor imposible (cero, o tan chica o tan grande que no entra
 *   en la columna) se deja afuera y se informa.
 * - Una tasa que ya era real y salta mas del 50% de una corrida a otra se deja
 *   como estaba y se informa (puede ser una devaluacion de verdad, o un error de
 *   la fuente): --forzar la acepta. Una tasa de ejemplo, que nadie actualizo,
 *   puede cambiar lo que sea: es la primera carga.
 * - Una moneda que la fuente no trae queda como estaba, con la fecha que tenia.
 * - Todo va en una transaccion, con una entrada en la auditoria.
 * - $simular hace las mismas cuentas y dice que haria, sin escribir nada.
 */
final class ActualizadorDeTasas
{
    /** Que parte de las monedas del catalogo (sin USD) tiene que traer la fuente para fiarse de ella. */
    public const COBERTURA_MINIMA = 0.5;

    /** Cuanto puede cambiar una tasa ya real entre dos corridas (0.5 = 50%) sin pedir --forzar. */
    public const CAMBIO_MAXIMO = 0.5;

    /** Cuantos dias de viejos pueden ser los datos de la fuente. */
    public const DIAS_MAXIMOS_DE_LA_FUENTE = 7;

    /** Lo que entra en monedas.tasa_a_usd, NUMERIC(18, 8): de un cienmillonesimo a mil millones, sin llegar. */
    private const TASA_MINIMA = 1e-8;
    private const TASA_MAXIMA = 1e9;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * @return array{
     *     actualizadas: array<string, array{anterior: string, nueva: string}>,
     *     sinDato: list<string>,
     *     rechazadas: array<string, string>,
     *     sospechosas: array<string, array{anterior: string, propuesta: string}>,
     *     cuando: DateTimeImmutable,
     *     simulacion: bool
     * }
     * @throws TasasInvalidas si la fuente no alcanza para actualizar nada (no se escribio nada)
     */
    public function aplicar(
        RespuestaDeTasas $respuesta,
        string $fuente,
        bool $simular = false,
        bool $forzar = false,
        ?DateTimeImmutable $ahora = null,
    ): array {
        $ahora ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        /** @var list<string> $catalogo */
        $catalogo = $this->db->query("SELECT codigo FROM monedas WHERE codigo <> 'USD' ORDER BY codigo")->fetchAll(PDO::FETCH_COLUMN);
        $presentes = count(array_filter($catalogo, static fn (string $codigo): bool => isset($respuesta->unidadesPorUsd[$codigo])));
        if ($presentes < self::COBERTURA_MINIMA * count($catalogo)) {
            throw new TasasInvalidas(sprintf(
                'La fuente solo trae %d de las %d monedas del catálogo: no alcanza para fiarse de ella. No se actualizó nada.',
                $presentes,
                count($catalogo)
            ));
        }

        $cuando = $respuesta->fecha ?? $ahora;
        if ($cuando < $ahora->modify('-' . self::DIAS_MAXIMOS_DE_LA_FUENTE . ' days')) {
            throw new TasasInvalidas(sprintf(
                'Los datos de la fuente son del %s: hace más de %d días que no se actualizan. No se actualizó nada.',
                $cuando->format('Y-m-d'),
                self::DIAS_MAXIMOS_DE_LA_FUENTE
            ));
        }
        $cuando = min($cuando, $ahora);

        $informe = ['actualizadas' => [], 'sinDato' => [], 'rechazadas' => [], 'sospechosas' => [], 'cuando' => $cuando, 'simulacion' => $simular];

        $decidir = $this->db->prepare(
            "SELECT tasa_a_usd AS anterior,
                    round(1::numeric / :unidades::numeric, 8) AS propuesta,
                    CASE WHEN tasa_actualizada_en IS NULL OR tasa_a_usd = 0 THEN true
                         ELSE abs(round(1::numeric / :unidades_2::numeric, 8) / tasa_a_usd - 1) <= :maximo::numeric
                    END AS acepta
             FROM monedas
             WHERE codigo = :codigo"
        );
        $guardar = $this->db->prepare(
            'UPDATE monedas SET tasa_a_usd = :tasa, tasa_actualizada_en = :cuando, tasa_fuente = :fuente WHERE codigo = :codigo'
        );

        $trabajo = function () use ($catalogo, $respuesta, $fuente, $simular, $forzar, $cuando, $decidir, $guardar, &$informe): void {
            foreach ($catalogo as $codigo) {
                if (isset($respuesta->rechazadas[$codigo])) {
                    $informe['rechazadas'][$codigo] = $respuesta->rechazadas[$codigo];
                    continue;
                }
                if (!isset($respuesta->unidadesPorUsd[$codigo])) {
                    $informe['sinDato'][] = $codigo;
                    continue;
                }
                $unidades = $respuesta->unidadesPorUsd[$codigo];
                $enUsd = 1.0 / (float) $unidades;
                if ($enUsd < self::TASA_MINIMA || $enUsd >= self::TASA_MAXIMA) {
                    $informe['rechazadas'][$codigo] = 'queda fuera de lo que entra en la base';
                    continue;
                }

                $decidir->execute([':unidades' => $unidades, ':unidades_2' => $unidades, ':maximo' => (string) self::CAMBIO_MAXIMO, ':codigo' => $codigo]);
                /** @var array{anterior: string, propuesta: string, acepta: bool} $fila */
                $fila = $decidir->fetch();
                if (!$fila['acepta'] && !$forzar) {
                    $informe['sospechosas'][$codigo] = ['anterior' => $fila['anterior'], 'propuesta' => $fila['propuesta']];
                    continue;
                }

                if (!$simular) {
                    $guardar->execute([
                        ':tasa' => $fila['propuesta'],
                        ':cuando' => $cuando->format('Y-m-d H:i:sP'),
                        ':fuente' => $fuente,
                        ':codigo' => $codigo,
                    ]);
                }
                $informe['actualizadas'][$codigo] = ['anterior' => $fila['anterior'], 'nueva' => $fila['propuesta']];
            }

            if (!$simular) {
                AuditoriaRepository::auditar('editar', 'monedas', 0, sprintf(
                    'Tasas de cambio actualizadas desde %s (cotización del %s UTC): %d monedas%s',
                    $fuente,
                    $cuando->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'),
                    count($informe['actualizadas']),
                    $informe['sinDato'] === [] && $informe['rechazadas'] === [] && $informe['sospechosas'] === []
                        ? ''
                        : sprintf(
                            '; sin dato: %d, rechazadas: %d, con un salto sospechoso: %d',
                            count($informe['sinDato']),
                            count($informe['rechazadas']),
                            count($informe['sospechosas'])
                        )
                ));
            }
        };

        // Simular no escribe nada, asi que no necesita transaccion; actualizar si: o quedan todas o ninguna.
        if ($simular) {
            $trabajo();
        } else {
            Database::transaccion($trabajo);
        }

        return $informe;
    }
}
