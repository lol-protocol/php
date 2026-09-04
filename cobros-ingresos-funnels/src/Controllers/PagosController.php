<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoRepository;
use App\View;

final class PagosController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        [$desde, $hasta] = Filtros::rango($meses);
        $cliente = trim((string) ($_GET['cliente'] ?? ''));

        $pagoRepo = new PagoRepository();

        View::render('pagos/index', [
            'meses' => $meses,
            'cliente' => $cliente,
            'cobrosPorMes' => $pagoRepo->cobrosPorMes($desde, $hasta),
            'porMetodo' => $pagoRepo->porMetodo($desde, $hasta),
            'pagos' => $pagoRepo->listado($desde, $hasta, $cliente ?: null),
            'activePage' => 'pagos',
            'titulo' => 'Pagos',
        ]);
    }

    public function nuevo(): void
    {
        $clienteRepo = new ClienteRepository();
        $boletaRepo = new BoletaRepository();
        $error = null;
        $clienteId = (int) ($_GET['cliente_id'] ?? $_POST['cliente_id'] ?? 0);
        $clienteElegido = $clienteId > 0 ? $clienteRepo->porId($clienteId) : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $monto = (float) ($_POST['monto'] ?? 0);
            $fechaPago = (string) ($_POST['fecha_pago'] ?? '');
            $metodo = (string) ($_POST['metodo'] ?? '');
            $boletaId = (int) ($_POST['boleta_id'] ?? 0);

            if ($clienteElegido === null) {
                $error = 'Elegí un cliente valido.';
            } elseif ($monto <= 0 || $fechaPago === '' || $metodo === '') {
                $error = 'Completá todos los campos.';
            } else {
                $id = (new PagoRepository())->crear([
                    'boleta_id' => $boletaId ?: null,
                    'cliente_id' => $clienteId,
                    'monto' => $monto,
                    'moneda_codigo' => $clienteElegido['moneda_codigo'],
                    'fecha_pago' => $fechaPago,
                    'metodo' => $metodo,
                ]);
                header('Location: ?page=pagos&creado=' . $id);
                exit;
            }
        }

        View::render('pagos/nuevo', [
            'clientes' => $clienteRepo->buscar('', 500),
            'clienteElegido' => $clienteElegido,
            'boletasCliente' => $clienteElegido ? $boletaRepo->porCliente($clienteId) : [],
            'error' => $error,
            'activePage' => 'pagos',
            'titulo' => 'Nuevo pago',
        ]);
    }
}
