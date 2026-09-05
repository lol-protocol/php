<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Filtros;
use App\Paginacion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\PagoRepository;
use App\View;

final class PagosController
{
    public function index(): void
    {
        $meses = Filtros::meses();
        $personalizado = Filtros::rangoPersonalizado();
        [$desde, $hasta] = $personalizado ?? Filtros::rango($meses);
        $cliente = trim((string) ($_GET['cliente'] ?? ''));
        $pagina = Paginacion::pagina();

        $pagoRepo = new PagoRepository();
        $listado = $pagoRepo->listado($desde, $hasta, $cliente ?: null, $pagina);

        View::render('pagos/index', [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado !== null,
            'cliente' => $cliente,
            'pagina' => $pagina,
            'cobrosPorMes' => $pagoRepo->cobrosPorMes($desde, $hasta),
            'porMetodo' => $pagoRepo->porMetodo($desde, $hasta),
            'pagos' => $listado['filas'],
            'totalPagos' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
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
                self::auditar('crear', 'pago', $id, sprintf(
                    'Pago #%d de %s: %s%s',
                    $id,
                    $clienteElegido['nombre'],
                    money_moneda($monto, $clienteElegido['moneda_codigo']),
                    $boletaId ? " (boleta #{$boletaId})" : ' (anticipo)'
                ));
                header('Location: ?page=pagos&creado=' . $id);
                exit;
            }
        }

        View::render('pagos/nuevo', [
            'clientes' => $clienteRepo->paraSelector(),
            'clienteElegido' => $clienteElegido,
            'boletasCliente' => $clienteElegido ? $boletaRepo->porCliente($clienteId) : [],
            'error' => $error,
            'activePage' => 'pagos',
            'titulo' => 'Nuevo pago',
        ]);
    }

    public function editar(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $pagoRepo = new PagoRepository();
        $pago = $pagoRepo->porId($id);
        if ($pago === null) {
            http_response_code(404);
            echo 'Pago no encontrado.';
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $monto = (float) ($_POST['monto'] ?? 0);
            $fechaPago = (string) ($_POST['fecha_pago'] ?? '');
            $metodo = (string) ($_POST['metodo'] ?? '');

            if ($monto <= 0 || $fechaPago === '' || $metodo === '') {
                $error = 'Completá todos los campos.';
            } else {
                $antes = money_moneda((float) $pago['monto'], $pago['moneda_codigo']) . " ({$pago['metodo']})";
                $despues = money_moneda($monto, $pago['moneda_codigo']) . " ({$metodo})";

                $pagoRepo->actualizar($id, ['monto' => $monto, 'fecha_pago' => $fechaPago, 'metodo' => $metodo]);
                self::auditar('editar', 'pago', $id, sprintf('Pago #%d: %s -> %s', $id, $antes, $despues));
                header('Location: ?page=pagos&editado=' . $id);
                exit;
            }

            $pago = array_merge($pago, ['monto' => $monto, 'fecha_pago' => $fechaPago, 'metodo' => $metodo]);
        }

        View::render('pagos/editar', [
            'pago' => $pago,
            'error' => $error,
            'activePage' => 'pagos',
            'titulo' => 'Editar pago',
        ]);
    }

    public function anular(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $pagoRepo = new PagoRepository();
        $pago = $pagoRepo->porId($id);
        if ($pago === null) {
            http_response_code(404);
            echo 'Pago no encontrado.';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pagoRepo->anular($id);
            self::auditar('anular', 'pago', $id, sprintf(
                'Pago #%d (%s)',
                $id,
                money_moneda((float) $pago['monto'], $pago['moneda_codigo'])
            ));
            header('Location: ?page=pagos&anulado=' . $id);
            exit;
        }

        View::render('pagos/anular', [
            'pago' => $pago,
            'activePage' => 'pagos',
            'titulo' => 'Anular pago',
        ]);
    }

    private static function auditar(string $accion, string $entidad, int $entidadId, string $detalle): void
    {
        $usuario = Auth::usuarioActual();
        (new AuditoriaRepository())->registrar($usuario['id'] ?? null, $accion, $entidad, $entidadId, $detalle);
    }
}
