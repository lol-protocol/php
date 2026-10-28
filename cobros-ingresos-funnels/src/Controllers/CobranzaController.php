<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\Descarga;
use App\Paginacion;
use App\Repositories\CobranzaRepository;
use App\Repositories\MonedaRepository;
use App\View;
use Generator;

/** La lista de cobranza: a quien hay que reclamarle, cuanto y desde cuando. Solo lee. */
final class CobranzaController
{
    public function index(): void
    {
        $avisos = [];
        $alcance = self::opcion('alcance', CobranzaRepository::ALCANCES, 'El alcance pedido no existe; se muestran los clientes con saldo vencido.', $avisos);
        $orden = self::opcion('orden', CobranzaRepository::ORDENES, 'El orden pedido no existe; se ordena por mayor monto vencido.', $avisos);

        $repo = new CobranzaRepository();
        $listado = $repo->listado($alcance, $orden, Paginacion::pagina());

        View::render('cobranza/lista', [
            'avisos' => $avisos,
            'alcance' => $alcance,
            'orden' => $orden,
            'resumen' => $repo->resumen(),
            'tasas' => MonedaRepository::estadoDeLasTasas(),
            'clientes' => $listado['filas'],
            'totalClientes' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
            'pagina' => $listado['pagina'],
            'activePage' => 'cobranza',
            'titulo' => 'Cobranza',
        ]);
    }

    /**
     * Descarga en CSV TODOS los clientes que la lista mostraria con el alcance y el orden de
     * la URL, no solo la pagina que se ve. Un valor invalido cae en el de por defecto como
     * en la pantalla, pero sin avisos.
     */
    public function exportar(): void
    {
        $avisos = [];
        $alcance = self::opcion('alcance', CobranzaRepository::ALCANCES, '', $avisos);
        $orden = self::opcion('orden', CobranzaRepository::ORDENES, '', $avisos);

        Descarga::csv(
            'cobranza',
            ['Cliente ID', 'Cliente', 'Email', 'País', 'Ciudad', 'Boletas', 'Saldo USD', 'Vencido USD', 'Días de la boleta más vencida'],
            self::filasParaExportar((new CobranzaRepository())->exportar($alcance, $orden))
        );
    }

    /**
     * Cada cliente como una fila de CSV: los montos van como decimales y los dias como
     * entero (cero o negativo = no tiene nada vencido; sigue siendo un numero, no una formula).
     *
     * @param iterable<array<string, mixed>> $clientes
     * @return Generator<int, list<string|int|float|null>>
     */
    private static function filasParaExportar(iterable $clientes): Generator
    {
        foreach ($clientes as $c) {
            yield [
                (int) $c['cliente_id'],
                $c['nombre'],
                $c['email'],
                $c['pais'],
                $c['ciudad'],
                (int) $c['boletas'],
                (float) $c['saldo_usd'],
                (float) $c['vencido_usd'],
                (int) $c['dias_mayor_vencimiento'],
            ];
        }
    }

    /**
     * El valor pedido por URL para $parametro si es uno de $permitidos; si no lo es (y
     * no esta vacio), agrega un aviso y usa el primero de la lista, como los demas filtros.
     *
     * @param array<string, string> $permitidos clave => etiqueta; la primera clave es la de por defecto
     * @param list<array{tipo: string, texto: string}> $avisos
     */
    private static function opcion(string $parametro, array $permitidos, string $aviso, array &$avisos): string
    {
        $pedido = (string) ($_GET[$parametro] ?? '');
        $valida = CobranzaRepository::opcionPedida($pedido, $permitidos);
        if ($pedido !== '' && $valida === '') {
            $avisos[] = Avisos::atencion($aviso);
        }

        return $valida !== '' ? $valida : (string) array_key_first($permitidos);
    }
}
