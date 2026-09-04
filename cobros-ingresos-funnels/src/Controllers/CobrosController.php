<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\View;

final class CobrosController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        [$desde, $hasta] = Filtros::rango($meses);
        $estado = $_GET['estado'] ?? '';
        $cliente = trim((string) ($_GET['cliente'] ?? ''));

        $boletaRepo = new BoletaRepository();
        $aging = $boletaRepo->carteraAging();

        View::render('cobros/index', [
            'meses' => $meses,
            'estado' => $estado,
            'cliente' => $cliente,
            'kpis' => $boletaRepo->kpis($desde, $hasta),
            'ingresosPorMes' => $boletaRepo->ingresosPorMes($desde, $hasta),
            'aging' => $aging,
            'carteraPendiente' => array_sum($aging),
            'boletas' => $boletaRepo->listado($desde, $hasta, $estado ?: null, $cliente ?: null),
            'activePage' => 'cobros',
            'titulo' => 'Cobros e ingresos',
        ]);
    }

    public function nueva(): void
    {
        $clienteRepo = new ClienteRepository();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clienteId = (int) ($_POST['cliente_id'] ?? 0);
            $concepto = trim((string) ($_POST['concepto'] ?? ''));
            $monto = (float) ($_POST['monto'] ?? 0);
            $fechaEmision = (string) ($_POST['fecha_emision'] ?? '');
            $fechaVencimiento = (string) ($_POST['fecha_vencimiento'] ?? '');

            $cliente = $clienteId > 0 ? $clienteRepo->porId($clienteId) : null;
            if ($cliente === null) {
                $error = 'Elegí un cliente valido.';
            } elseif ($concepto === '' || $monto <= 0 || $fechaEmision === '' || $fechaVencimiento === '') {
                $error = 'Completá todos los campos.';
            } else {
                $id = (new BoletaRepository())->crear([
                    'cliente_id' => $clienteId,
                    'concepto' => $concepto,
                    'monto' => $monto,
                    'moneda_codigo' => $cliente['moneda_codigo'],
                    'fecha_emision' => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                ]);
                header('Location: ?page=cobros&creada=' . $id);
                exit;
            }
        }

        View::render('cobros/nueva', [
            'clientes' => $clienteRepo->buscar('', 500),
            'error' => $error,
            'valores' => $_POST ?? [],
            'activePage' => 'cobros',
            'titulo' => 'Nueva boleta',
        ]);
    }
}
