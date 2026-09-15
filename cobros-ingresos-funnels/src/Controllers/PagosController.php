<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Paginacion;
use App\Peticion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\IngresosRepository;
use App\Repositories\PagoRepository;
use App\Validacion;
use App\View;

final class PagosController
{
    public function index(): void
    {
        ['meses' => $meses, 'desde' => $desde, 'hasta' => $hasta, 'personalizado' => $personalizado] = Filtros::rangoActivo();
        $cliente = trim((string) ($_GET['cliente'] ?? ''));
        $pagina = Paginacion::pagina();

        $ingresosRepo = new IngresosRepository();
        $listado = (new PagoRepository())->listado($desde, $hasta, $cliente ?: null, $pagina);

        View::render('pagos/index', [
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'personalizado' => $personalizado,
            'cliente' => $cliente,
            'pagina' => $pagina,
            'cobrosPorMes' => $ingresosRepo->cobrosPorMes($desde, $hasta),
            'porMetodo' => $ingresosRepo->porMetodo($desde, $hasta),
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

            $boleta = $boletaId > 0 ? $boletaRepo->porId($boletaId) : null;

            if ($clienteElegido === null) {
                $error = 'Elegí un cliente valido.';
            } elseif (Validacion::faltanCampos([$fechaPago, $metodo], $monto)) {
                $error = 'Completá todos los campos.';
            } elseif (!Filtros::esFechaValida($fechaPago)) {
                $error = 'La fecha de pago no es válida.';
            } elseif ($boletaId > 0 && !self::boletaEsValidaParaCliente($boleta, $clienteId)) {
                $error = 'La boleta elegida no es válida para este cliente.';
            } elseif ($boletaId > 0 && !self::fechaPagoEsValida($fechaPago, $boleta)) {
                $error = 'La fecha de pago no puede ser anterior a la emisión de la boleta.';
            } elseif ($boletaId > 0 && !self::montoNoSuperaElSaldo($monto, $boleta)) {
                $error = 'El monto supera el saldo pendiente de la boleta.';
            } else {
                $id = (new PagoRepository())->crear([
                    'boleta_id' => $boletaId ?: null,
                    'cliente_id' => $clienteId,
                    'monto' => $monto,
                    'moneda_codigo' => $clienteElegido['moneda_codigo'],
                    'fecha_pago' => $fechaPago,
                    'metodo' => $metodo,
                ]);
                AuditoriaRepository::auditarComoUsuarioActual('crear', 'pago', $id, sprintf(
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

    /**
     * Una boleta solo es un origen valido para el pago de $clienteId si
     * existe, es de ese mismo cliente y no esta anulada (evita registrar un
     * cobro contra una boleta ajena o ya sin efecto).
     */
    public static function boletaEsValidaParaCliente(?array $boleta, int $clienteId): bool
    {
        return $boleta !== null && (int) $boleta['cliente_id'] === $clienteId && !$boleta['anulada'];
    }

    /** No se puede registrar un pago con fecha anterior a que la boleta fue emitida. */
    public static function fechaPagoEsValida(string $fechaPago, array $boleta): bool
    {
        return $fechaPago >= $boleta['fecha_emision'];
    }

    /** Tolerancia de un centavo para poder pagar exactamente el saldo restante sin que el redondeo lo rechace. */
    public static function montoNoSuperaElSaldo(float $monto, array $boleta): bool
    {
        return $monto <= (float) $boleta['saldo'] + 0.01;
    }

    public function editar(): void
    {
        $id = Peticion::id();
        $pagoRepo = new PagoRepository();
        $pago = $pagoRepo->porId($id);
        if (Peticion::abortarSiNoExiste($pago, 'Pago no encontrado.')) {
            return;
        }
        if (Peticion::abortarSiConflicto($pago['anulada'], 'El pago esta anulado y no se puede editar.')) {
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $monto = (float) ($_POST['monto'] ?? 0);
            $fechaPago = (string) ($_POST['fecha_pago'] ?? '');
            $metodo = (string) ($_POST['metodo'] ?? '');

            if (Validacion::faltanCampos([$fechaPago, $metodo], $monto)) {
                $error = 'Completá todos los campos.';
            } elseif (!Filtros::esFechaValida($fechaPago)) {
                $error = 'La fecha de pago no es válida.';
            } else {
                $antes = money_moneda((float) $pago['monto'], $pago['moneda_codigo']) . " ({$pago['metodo']})";
                $despues = money_moneda($monto, $pago['moneda_codigo']) . " ({$metodo})";

                $pagoRepo->actualizar($id, ['monto' => $monto, 'fecha_pago' => $fechaPago, 'metodo' => $metodo]);
                AuditoriaRepository::auditarComoUsuarioActual('editar', 'pago', $id, sprintf('Pago #%d: %s -> %s', $id, $antes, $despues));
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
        $id = Peticion::id();
        $pagoRepo = new PagoRepository();
        $pago = $pagoRepo->porId($id);
        if (Peticion::abortarSiNoExiste($pago, 'Pago no encontrado.')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$pago['anulada']) {
                $pagoRepo->anular($id);
                AuditoriaRepository::auditarComoUsuarioActual('anular', 'pago', $id, sprintf(
                    'Pago #%d (%s)',
                    $id,
                    money_moneda((float) $pago['monto'], $pago['moneda_codigo'])
                ));
            }
            header('Location: ?page=pagos&anulado=' . $id);
            exit;
        }

        View::render('pagos/anular', [
            'pago' => $pago,
            'activePage' => 'pagos',
            'titulo' => 'Anular pago',
        ]);
    }

}
