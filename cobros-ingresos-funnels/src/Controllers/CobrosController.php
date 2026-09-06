<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Paginacion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\IngresosRepository;
use App\View;

final class CobrosController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        $personalizado = Filtros::rangoPersonalizado();
        [$desde, $hasta] = $personalizado ?? Filtros::rango($meses);
        $estado = $_GET['estado'] ?? '';
        $cliente = trim((string) ($_GET['cliente'] ?? ''));
        $pagina = Paginacion::pagina();

        $ingresosRepo = new IngresosRepository();
        $aging = $ingresosRepo->carteraAging();
        $listado = (new BoletaRepository())->listado($desde, $hasta, $estado ?: null, $cliente ?: null, $pagina);

        View::render('cobros/index', [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado !== null,
            'estado' => $estado,
            'cliente' => $cliente,
            'pagina' => $pagina,
            'kpis' => $ingresosRepo->kpis($desde, $hasta),
            'ingresosPorMes' => $ingresosRepo->ingresosPorMes($desde, $hasta),
            'aging' => $aging,
            'carteraPendiente' => array_sum($aging),
            'boletas' => $listado['filas'],
            'totalBoletas' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
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
                AuditoriaRepository::auditarComoUsuarioActual('crear', 'boleta', $id, sprintf(
                    'Boleta #%d para %s: "%s" %s',
                    $id,
                    $cliente['nombre'],
                    $concepto,
                    money_moneda($monto, $cliente['moneda_codigo'])
                ));
                header('Location: ?page=cobros&creada=' . $id);
                exit;
            }
        }

        View::render('cobros/nueva', [
            'clientes' => $clienteRepo->paraSelector(),
            'error' => $error,
            'valores' => $_POST ?? [],
            'activePage' => 'cobros',
            'titulo' => 'Nueva boleta',
        ]);
    }

    public function editar(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $boletaRepo = new BoletaRepository();
        $boleta = $boletaRepo->porId($id);
        if ($boleta === null) {
            http_response_code(404);
            echo 'Boleta no encontrada.';
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $concepto = trim((string) ($_POST['concepto'] ?? ''));
            $monto = (float) ($_POST['monto'] ?? 0);
            $fechaEmision = (string) ($_POST['fecha_emision'] ?? '');
            $fechaVencimiento = (string) ($_POST['fecha_vencimiento'] ?? '');

            if ($concepto === '' || $monto <= 0 || $fechaEmision === '' || $fechaVencimiento === '') {
                $error = 'Completá todos los campos.';
            } else {
                $antes = sprintf('"%s" %s', $boleta['concepto'], money_moneda((float) $boleta['monto'], $boleta['moneda_codigo']));
                $despues = sprintf('"%s" %s', $concepto, money_moneda($monto, $boleta['moneda_codigo']));

                $boletaRepo->actualizar($id, [
                    'concepto' => $concepto,
                    'monto' => $monto,
                    'fecha_emision' => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                ]);
                AuditoriaRepository::auditarComoUsuarioActual('editar', 'boleta', $id, sprintf('Boleta #%d: %s -> %s', $id, $antes, $despues));
                header('Location: ?page=cobros&editada=' . $id);
                exit;
            }

            $boleta = array_merge($boleta, [
                'concepto' => $concepto,
                'monto' => $monto,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
            ]);
        }

        View::render('cobros/editar', [
            'boleta' => $boleta,
            'error' => $error,
            'activePage' => 'cobros',
            'titulo' => 'Editar boleta',
        ]);
    }

    public function anular(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $boletaRepo = new BoletaRepository();
        $boleta = $boletaRepo->porId($id);
        if ($boleta === null) {
            http_response_code(404);
            echo 'Boleta no encontrada.';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $boletaRepo->anular($id);
            AuditoriaRepository::auditarComoUsuarioActual('anular', 'boleta', $id, sprintf(
                'Boleta #%d ("%s", %s)',
                $id,
                $boleta['concepto'],
                money_moneda((float) $boleta['monto'], $boleta['moneda_codigo'])
            ));
            header('Location: ?page=cobros&anulada=' . $id);
            exit;
        }

        View::render('cobros/anular', [
            'boleta' => $boleta,
            'activePage' => 'cobros',
            'titulo' => 'Anular boleta',
        ]);
    }

}
