<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\EnvioUnico;
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
use LogicException;

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
            $token = EnvioUnico::tokenRecibido();
            $reenvio = $token === null ? null : EnvioUnico::redireccionPrevia($token);
            if ($reenvio !== null) {
                // Mismo formulario enviado otra vez (doble clic): la boleta ya
                // se creo, se redirige igual que la primera vez.
                header('Location: ' . $reenvio);
                exit;
            }

            $clienteId = (int) ($_POST['cliente_id'] ?? 0);
            $concepto = trim((string) ($_POST['concepto'] ?? ''));
            $monto = (float) ($_POST['monto'] ?? 0);
            $fechaEmision = (string) ($_POST['fecha_emision'] ?? '');
            $fechaVencimiento = (string) ($_POST['fecha_vencimiento'] ?? '');

            $cliente = $clienteId > 0 ? $clienteRepo->porId($clienteId) : null;
            if ($token === null) {
                $error = EnvioUnico::MENSAJE_SIN_TOKEN;
            } elseif ($cliente === null) {
                $error = 'Elegí un cliente valido.';
            } elseif (Validacion::faltanCampos([$concepto, $fechaEmision, $fechaVencimiento], $monto)) {
                $error = 'Completá todos los campos.';
            } elseif (!Filtros::esFechaValida($fechaEmision) || !Filtros::esFechaValida($fechaVencimiento)) {
                $error = 'La fecha de emisión o de vencimiento no es válida.';
            } elseif (!self::vencimientoNoAnteriorALaEmision($fechaEmision, $fechaVencimiento)) {
                $error = 'El vencimiento no puede ser anterior a la emisión.';
            } else {
                $destino = EnvioUnico::ejecutar($token, static function () use ($clienteId, $concepto, $monto, $cliente, $fechaEmision, $fechaVencimiento): string {
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

                    return '?page=cobros&creada=' . $id;
                });
                header('Location: ' . $destino);
                exit;
            }
        }

        View::render('cobros/nueva', [
            'clientes' => $clienteRepo->paraSelector(),
            'error' => $error,
            'valores' => $_POST,
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
            $monto = (float) ($_POST['monto'] ?? 0);
            $fechaEmision = (string) ($_POST['fecha_emision'] ?? '');
            $fechaVencimiento = (string) ($_POST['fecha_vencimiento'] ?? '');

            if (Validacion::faltanCampos([$concepto, $fechaEmision, $fechaVencimiento], $monto)) {
                $error = 'Completá todos los campos.';
            } elseif (!Filtros::esFechaValida($fechaEmision) || !Filtros::esFechaValida($fechaVencimiento)) {
                $error = 'La fecha de emisión o de vencimiento no es válida.';
            } elseif (!self::vencimientoNoAnteriorALaEmision($fechaEmision, $fechaVencimiento)) {
                $error = 'El vencimiento no puede ser anterior a la emisión.';
            } elseif (!self::montoCubreLoYaCobrado($monto, $boleta)) {
                $error = 'El monto no puede ser menor a lo ya cobrado (' . money_moneda((float) $boleta['pagado'], $boleta['moneda_codigo']) . ').';
            } elseif (!self::emisionNoPosteriorAlPrimerPago($fechaEmision, $boleta)) {
                $error = 'La emisión no puede ser posterior al primer pago de la boleta (' . $boleta['primer_pago'] . ').';
            } else {
                $antes = sprintf('"%s" %s', $boleta['concepto'], money_moneda((float) $boleta['monto'], $boleta['moneda_codigo']));
                $despues = sprintf('"%s" %s', $concepto, money_moneda($monto, $boleta['moneda_codigo']));

                Database::transaccion(static function () use ($boletaRepo, $id, $concepto, $monto, $fechaEmision, $fechaVencimiento, $antes, $despues): void {
                    $boletaRepo->actualizar($id, [
                        'concepto' => $concepto,
                        'monto' => $monto,
                        'fecha_emision' => $fechaEmision,
                        'fecha_vencimiento' => $fechaVencimiento,
                    ]);
                    AuditoriaRepository::auditarComoUsuarioActual('editar', 'boleta', $id, sprintf('Boleta #%d: %s -> %s', $id, $antes, $despues));
                });
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
                if ($boleta === null) {
                    throw new LogicException("La boleta #{$id} se acaba de anular y ya no se encuentra.");
                }
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
