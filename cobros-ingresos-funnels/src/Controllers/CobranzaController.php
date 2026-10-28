<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\Paginacion;
use App\Repositories\CobranzaRepository;
use App\Repositories\MonedaRepository;
use App\View;

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
