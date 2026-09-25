<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Filtros;
use App\Paginacion;
use App\Peticion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\IngresosRepository;
use App\Repositories\NotaCreditoRepository;
use App\Validacion;
use App\View;

final class CobrosController
{
    /**
     * Al editar una boleta, el monto no puede bajar de lo que ya se cobro
     * (dejaria un saldo negativo y la boleta se seguiria mostrando como
     * "pagada" sin avisar que en realidad se sobrecobro).
     */
    public static function montoCubreLoYaCobrado(float $monto, array $boleta): bool
    {
        return $monto >= (float) $boleta['pagado'] - 0.01;
    }

    /** Una boleta no puede vencer antes de haber sido emitida. */
    public static function vencimientoNoAnteriorALaEmision(string $fechaEmision, string $fechaVencimiento): bool
    {
        return $fechaVencimiento >= $fechaEmision;
    }

    /**
     * Al editar, la emision no puede quedar despues del primer pago que la
     * boleta ya tiene: seria una boleta cobrada antes de existir (el espejo
     * de la validacion que ya hace PagosController del lado del pago).
     */
    public static function emisionNoPosteriorAlPrimerPago(string $fechaEmision, array $boleta): bool
    {
        return $boleta['primer_pago'] === null || $fechaEmision <= $boleta['primer_pago'];
    }

    public function index(): void
    {
        ['meses' => $meses, 'desde' => $desde, 'hasta' => $hasta, 'personalizado' => $personalizado] = Filtros::rangoActivo();
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
            'personalizado' => $personalizado,
            'estado' => $estado,
            'cliente' => $cliente,
            'pagina' => $listado['pagina'],
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
            $monto = round((float) ($_POST['monto'] ?? 0), 2);
            $fechaEmision = (string) ($_POST['fecha_emision'] ?? '');
            $fechaVencimiento = (string) ($_POST['fecha_vencimiento'] ?? '');

            $cliente = $clienteId > 0 ? $clienteRepo->porId($clienteId) : null;
            if ($cliente === null) {
                $error = 'Elegí un cliente valido.';
            } elseif (Validacion::faltanCampos([$concepto, $fechaEmision, $fechaVencimiento], $monto)) {
                $error = 'Completá todos los campos con un monto válido.';
            } elseif (!Filtros::esFechaValida($fechaEmision) || !Filtros::esFechaValida($fechaVencimiento)) {
                $error = 'La fecha de emisión o de vencimiento no es válida.';
            } elseif (!self::vencimientoNoAnteriorALaEmision($fechaEmision, $fechaVencimiento)) {
                $error = 'El vencimiento no puede ser anterior a la emisión.';
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
        $id = Peticion::id();
        $boletaRepo = new BoletaRepository();
        $boleta = $boletaRepo->porId($id);
        if (Peticion::abortarSiNoExiste($boleta, 'Boleta no encontrada.')) {
            return;
        }
        if (Peticion::abortarSiConflicto($boleta['anulada'], 'La boleta esta anulada y no se puede editar.')) {
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $concepto = trim((string) ($_POST['concepto'] ?? ''));
            $monto = round((float) ($_POST['monto'] ?? 0), 2);
            $fechaEmision = (string) ($_POST['fecha_emision'] ?? '');
            $fechaVencimiento = (string) ($_POST['fecha_vencimiento'] ?? '');

            // Bajo candado: pagado/primer_pago/anulada pueden haber cambiado
            // desde la lectura de arriba (un pago nuevo haria que el monto
            // quede por debajo de lo cobrado sin que la validacion lo vea).
            $resultado = Database::transaccion(function () use ($boletaRepo, $id, $concepto, $monto, $fechaEmision, $fechaVencimiento): ?string {
                $boletaRepo->bloquear($id);
                $boleta = $boletaRepo->porId($id);

                if ($boleta['anulada']) {
                    return 'La boleta fue anulada mientras la editabas.';
                } elseif (Validacion::faltanCampos([$concepto, $fechaEmision, $fechaVencimiento], $monto)) {
                    return 'Completá todos los campos con un monto válido.';
                } elseif (!Filtros::esFechaValida($fechaEmision) || !Filtros::esFechaValida($fechaVencimiento)) {
                    return 'La fecha de emisión o de vencimiento no es válida.';
                } elseif (!self::vencimientoNoAnteriorALaEmision($fechaEmision, $fechaVencimiento)) {
                    return 'El vencimiento no puede ser anterior a la emisión.';
                } elseif (!self::montoCubreLoYaCobrado($monto, $boleta)) {
                    return 'El monto no puede ser menor a lo ya cobrado (' . money_moneda((float) $boleta['pagado'], $boleta['moneda_codigo']) . ').';
                } elseif (!self::emisionNoPosteriorAlPrimerPago($fechaEmision, $boleta)) {
                    return 'La emisión no puede ser posterior al primer pago de la boleta (' . $boleta['primer_pago'] . ').';
                }

                $antes = sprintf('"%s" %s', $boleta['concepto'], money_moneda((float) $boleta['monto'], $boleta['moneda_codigo']));
                $despues = sprintf('"%s" %s', $concepto, money_moneda($monto, $boleta['moneda_codigo']));
                $boletaRepo->actualizar($id, [
                    'concepto' => $concepto,
                    'monto' => $monto,
                    'fecha_emision' => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                ]);
                AuditoriaRepository::auditarComoUsuarioActual('editar', 'boleta', $id, sprintf('Boleta #%d: %s -> %s', $id, $antes, $despues));
                return null;
            });

            if ($resultado === null) {
                header('Location: ?page=cobros&editada=' . $id);
                exit;
            }
            $error = $resultado;
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
        $id = Peticion::id();
        $boletaRepo = new BoletaRepository();
        $boleta = $boletaRepo->porId($id);
        if (Peticion::abortarSiNoExiste($boleta, 'Boleta no encontrada.')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Atomico: la boleta no puede quedar anulada sin su nota de
            // credito, porque la guarda de idempotencia impediria reintentar.
            // Y el "todavia estaba activa" se resuelve en la propia sentencia
            // de anulacion, no con el $boleta leido arriba: dos anulaciones
            // simultaneas pasaban las dos y emitian dos notas de credito.
            Database::transaccion(function () use ($boletaRepo, $id): void {
                if (!$boletaRepo->anularSiEstabaActiva($id)) {
                    return;
                }
                // Relectura con la fila ya bloqueada por esta transaccion,
                // para que la nota salga por lo efectivamente cobrado y no
                // por el 'pagado' que se leyo antes de tomar el candado.
                $boleta = $boletaRepo->porId($id);
                AuditoriaRepository::auditarComoUsuarioActual('anular', 'boleta', $id, sprintf(
                    'Boleta #%d ("%s", %s)',
                    $id,
                    $boleta['concepto'],
                    money_moneda((float) $boleta['monto'], $boleta['moneda_codigo'])
                ));
                $this->emitirNotaDeCredito($boleta);
            });
            header('Location: ?page=cobros&anulada=' . $id);
            exit;
        }

        View::render('cobros/anular', [
            'boleta' => $boleta,
            'activePage' => 'cobros',
            'titulo' => 'Anular boleta',
        ]);
    }

    /**
     * Al anular una boleta que ya tenia pagos, los pagos no se tocan (la
     * plata entro de verdad y tiene que seguir en el historial de caja): se
     * emite una nota de credito por lo cobrado, que los reportes restan para
     * que el neto cierre.
     */
    private function emitirNotaDeCredito(array $boleta): void
    {
        $pagado = (float) $boleta['pagado'];
        if ($pagado <= 0.01) {
            return;
        }

        $notaId = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta['id'],
            'cliente_id' => $boleta['cliente_id'],
            'monto' => $pagado,
            'moneda_codigo' => $boleta['moneda_codigo'],
            'fecha' => date('Y-m-d'),
            'motivo' => sprintf('Anulacion de la boleta #%d ("%s")', $boleta['id'], $boleta['concepto']),
        ]);

        AuditoriaRepository::auditarComoUsuarioActual('crear', 'nota_credito', $notaId, sprintf(
            'Nota de credito #%d por %s (boleta #%d anulada con pagos)',
            $notaId,
            money_moneda($pagado, $boleta['moneda_codigo']),
            $boleta['id']
        ));
    }
}
