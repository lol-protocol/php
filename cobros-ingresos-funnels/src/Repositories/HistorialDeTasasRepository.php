<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Paginacion;
use Generator;
use PDO;

/**
 * El historial de las tasas de cambio (tabla historial_de_tasas, migracion 018): una fila cada vez que cambia
 * la tasa de una moneda, la fecha de su cotizacion o su fuente. La llena un trigger sobre monedas, no este codigo:
 * aqui solo se lee.
 *
 * Una tasa es cuantos dolares vale una unidad de la moneda (monedas.tasa_a_usd). Cuando cotizada_en es NULL
 * la tasa es de ejemplo (la de la migracion 005): no se compara con una real, porque el «cambio» seria entre una
 * cifra inventada y una verdadera.
 *
 * @phpstan-import-type Pagina from Paginacion
 */
final class HistorialDeTasasRepository
{
    /** Con cuantos dias de diferencia se compara la tasa actual en el resumen. */
    public const DIAS_DEL_RESUMEN = 30;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Las monedas de las que se puede pedir el historial, por codigo: «CLP - Peso chileno». El dolar no esta: es la
     * base de todas las tasas y vale siempre 1.
     *
     * @return array<string, string>
     */
    public function monedas(): array
    {
        $opciones = [];
        foreach ($this->db->query("SELECT codigo, nombre FROM monedas WHERE codigo <> 'USD' ORDER BY codigo")->fetchAll() as $fila) {
            $opciones[(string) $fila['codigo']] = $fila['codigo'] . ' — ' . $fila['nombre'];
        }

        return $opciones;
    }

    /**
     * Una fila por moneda con su tasa de hoy y la que tenia DIAS_DEL_RESUMEN dias antes de la fecha de su
     * ultima cotizacion (la mas reciente que ya existia ese dia). tasa_antes es null si la tasa de hoy es de
     * ejemplo o si el historial no llega tan atras: el sistema lleva menos de ese tiempo guardando las tasas, y
     * lo que no se guardo no se puede recuperar. Una tasa de ejemplo (cotizada_en NULL), de hoy o de antes, nunca
     * sale en la comparacion: NULL no es menor ni igual a nada.
     *
     * Se mide hasta la fecha de la ultima cotizacion de cada moneda, no hasta hoy: una moneda cuya tasa lleva
     * dos semanas sin actualizarse no muestra un cambio de «30 dias» que en realidad es de 44.
     *
     * @return list<array{codigo: string, nombre: string, tasa_a_usd: string, cotizada_en: ?string, tasa_antes: ?string}>
     */
    public function resumen(): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.codigo, m.nombre, m.tasa_a_usd, m.tasa_actualizada_en AS cotizada_en, h.tasa_a_usd AS tasa_antes
             FROM monedas m
             LEFT JOIN LATERAL (
                 SELECT x.tasa_a_usd
                 FROM historial_de_tasas x
                 WHERE x.moneda_codigo = m.codigo
                   AND x.cotizada_en <= m.tasa_actualizada_en - make_interval(days => :dias)
                 ORDER BY x.cotizada_en DESC, x.id DESC
                 LIMIT 1
             ) h ON true
             WHERE m.codigo <> 'USD'
             ORDER BY m.codigo"
        );
        $stmt->bindValue(':dias', self::DIAS_DEL_RESUMEN, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array{codigo: string, nombre: string, tasa_a_usd: string, cotizada_en: ?string, tasa_antes: ?string}> */
        return $stmt->fetchAll();
    }

    /**
     * Una pagina del historial de una moneda, de la mas reciente a la mas antigua. tasa_anterior es la tasa de la
     * fila que va justo antes, para mostrar cuanto cambio: null en la primera fila del historial y siempre que una
     * de las dos sea de ejemplo.
     *
     * Se pagina con LIMIT/OFFSET porque es el historial de UNA moneda (una fila por dia como mucho, unos cientos al
     * ano): el de todas juntas no se pide nunca.
     *
     * @return Pagina las filas traen id, tasa_a_usd, cotizada_en, fuente, registrada_en y tasa_anterior
     */
    public function deLaMoneda(string $codigo, int $pagina): array
    {
        return Paginacion::consultar($this->db, ...self::consultaDelHistorial($codigo), pagina: $pagina);
    }

    /**
     * TODAS las filas del historial de una moneda, las mismas y en el mismo orden que deLaMoneda() reparte en
     * paginas, de a una y sin juntarlas en memoria: es lo que baja el CSV, que no puede cortar en paginas.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function exportarHistorial(string $codigo): Generator
    {
        $consulta = self::consultaDelHistorial($codigo);
        unset($consulta['desdeDelConteo']);

        return Paginacion::recorrer($this->db, ...$consulta);
    }

    /**
     * La consulta del historial de una moneda, una sola para la pantalla y el CSV (ver PagoRepository).
     *
     * @return array{columnas: string, desde: string, orden: string, params: array<string, string>, desdeDelConteo: string}
     */
    private static function consultaDelHistorial(string $codigo): array
    {
        return [
            'columnas' => 'h.id, h.tasa_a_usd, h.cotizada_en, h.fuente, h.registrada_en, h.tasa_anterior',
            'desde' => '(SELECT x.*,
                                CASE WHEN x.cotizada_en IS NOT NULL AND LAG(x.cotizada_en) OVER w IS NOT NULL
                                     THEN LAG(x.tasa_a_usd) OVER w END AS tasa_anterior
                         FROM historial_de_tasas x
                         WHERE x.moneda_codigo = :moneda
                         WINDOW w AS (ORDER BY x.id)) h',
            'orden' => 'h.id DESC',
            'params' => [':moneda' => $codigo],
            'desdeDelConteo' => 'historial_de_tasas WHERE moneda_codigo = :moneda',
        ];
    }
}
