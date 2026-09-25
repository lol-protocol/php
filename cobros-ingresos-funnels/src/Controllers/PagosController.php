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
            'pagina' => $listado['pagina'],
            'cobrosPorMes' => $ingresosRepo->cobrosPorMes($desde, $hasta),
            'porMetodo' => $ingresosRepo->porMetodo($desde, $hasta),
            'devoluciones' => (new NotaCreditoRepository())->totalEnRangoUsd($desde, $hasta),
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
            $token = EnvioUnico::tokenRecibido();
            $reenvio = $token === null ? null : EnvioUnico::redireccionPrevia($token);
            if ($reenvio !== null) {
                // Mismo formulario enviado otra vez (doble clic): el pago ya se
                // registro. Sin esto se volvia a validar y a crear otro igual.
                header('Location: ' . $reenvio);
                exit;
            }

            $monto = round((float) ($_POST['monto'] ?? 0), 2);
            $fechaPago = (string) ($_POST['fecha_pago'] ?? '');
            $metodo = (string) ($_POST['metodo'] ?? '');
            $boletaId = (int) ($_POST['boleta_id'] ?? 0);

            if ($token === null) {
                $error = EnvioUnico::MENSAJE_SIN_TOKEN;
            } else {
                // La validacion de saldo/anulada y el INSERT van bajo el candado de
                // la boleta: validar con una lectura previa dejaba sobrecobrar con
                // dos pagos simultaneos, o colar un pago en una boleta que se
                // estaba anulando (quedaba fuera de su nota de credito).
                $resultado = Database::transaccion(function () use ($token, $boletaRepo, $clienteElegido, $clienteId, $boletaId, $monto, $fechaPago, $metodo): array {
                    $boleta = null;
                    if ($boletaId > 0) {
                        $boletaRepo->bloquear($boletaId);
                        $boleta = $boletaRepo->porId($boletaId);
                    }

                    // Con el candado tomado, un envio simultaneo del mismo
                    // formulario ya termino. Si registro el pago, este va a donde
                    // fue aquel: si no, veria el saldo ya consumido y mostraria
                    // "el monto supera el saldo" por un pago que si quedo hecho.
                    $previa = EnvioUnico::redireccionPrevia($token);
                    if ($previa !== null) {
                        return ['redireccion' => $previa];
                    }

                    if ($clienteElegido === null) {
                        return ['error' => 'Elegí un cliente valido.'];
                    } elseif (Validacion::faltanCampos([$fechaPago, $metodo], $monto)) {
                        return ['error' => 'Completá todos los campos con un monto válido.'];
                    } elseif (!Filtros::esFechaValida($fechaPago)) {
                        return ['error' => 'La fecha de pago no es válida.'];
                    } elseif ($boletaId > 0 && !self::boletaEsValidaParaCliente($boleta, $clienteId)) {
                        return ['error' => 'La boleta elegida no es válida para este cliente.'];
                    } elseif ($boleta !== null && !self::fechaPagoEsValida($fechaPago, $boleta)) {
                        return ['error' => 'La fecha de pago no puede ser anterior a la emisión de la boleta.'];
                    } elseif ($boleta !== null && !self::montoNoSuperaElSaldo($monto, $boleta)) {
                        return ['error' => 'El monto supera el saldo pendiente de la boleta.'];
                    }

                    return ['redireccion' => EnvioUnico::ejecutar($token, static function () use ($boletaId, $clienteId, $monto, $clienteElegido, $fechaPago, $metodo): string {
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

                        return '?page=pagos&creado=' . $id;
                    })];
                });

                if (isset($resultado['redireccion'])) {
                    header('Location: ' . $resultado['redireccion']);
                    exit;
                }
                $error = $resultado['error'];
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

    /**
     * Un pago cuya boleta ya fue anulada queda congelado: al anularla se
     * emitio una nota de credito por lo que estaba cobrado en ese momento, y
     * esa nota no se recalcula. Si despues se pudiera anular o editar el
     * pago, la plata se contaria dos veces (el pago sale de la caja y la
     * nota lo sigue devolviendo) o la devolucion quedaria corta. Es la regla
     * simetrica de boletaEsValidaParaCliente(), que ya impide cargar un pago
     * nuevo contra una boleta anulada.
     */
    public static function boletaAnuladaCongelaElPago(?array $boleta): bool
    {
        return $boleta !== null && (bool) $boleta['anulada'];
    }

    /** Tolerancia de un centavo para poder pagar exactamente el saldo restante sin que el redondeo lo rechace. */
    public static function montoNoSuperaElSaldo(float $monto, array $boleta): bool
    {
        return $monto <= (float) $boleta['saldo'] + 0.01;
    }

    /**
     * Misma regla que montoNoSuperaElSaldo() pero al EDITAR un pago ya
     * existente: BoletaRepository::porId() calcula el saldo restando todos
     * los pagos vigentes de la boleta, este mismo pago incluido, asi que
     * hay que sumarle de vuelta $montoViejo antes de comparar contra el
     * monto nuevo (si no, el pago se estaria descontando a si mismo).
     */
    public static function montoNoSuperaElSaldoAlEditar(float $montoNuevo, array $boleta, float $montoViejo): bool
    {
        return self::montoNoSuperaElSaldo($montoNuevo, ['saldo' => (float) $boleta['saldo'] + $montoViejo]);
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

        $boleta = $pago['boleta_id'] ? (new BoletaRepository())->porId($pago['boleta_id']) : null;
        if (Peticion::abortarSiConflicto(
            self::boletaAnuladaCongelaElPago($boleta),
            'La boleta de este pago esta anulada y ya tiene su nota de credito: editarlo descuadraria la devolucion.'
        )) {
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $monto = round((float) ($_POST['monto'] ?? 0), 2);
            $fechaPago = (string) ($_POST['fecha_pago'] ?? '');
            $metodo = (string) ($_POST['metodo'] ?? '');

            // Relectura bajo candado (pago y despues boleta, el mismo orden que
            // anular()): el $pago/$boleta de arriba pueden estar viejos si otro
            // proceso anulo el pago, la boleta o cargo otro pago mientras tanto.
            $resultado = Database::transaccion(function () use ($pagoRepo, $id, $monto, $fechaPago, $metodo): ?string {
                $pagoRepo->bloquear($id);
                $pago = $pagoRepo->porId($id);
                if ($pago === null) {
                    return 'El pago ya no existe.';
                }
                $boletaRepo = new BoletaRepository();
                if ($pago['boleta_id']) {
                    $boletaRepo->bloquear((int) $pago['boleta_id']);
                }
                $boleta = $pago['boleta_id'] ? $boletaRepo->porId((int) $pago['boleta_id']) : null;

                if ($pago['anulada'] || self::boletaAnuladaCongelaElPago($boleta)) {
                    return 'El pago o su boleta fueron anulados mientras lo editabas.';
                } elseif (Validacion::faltanCampos([$fechaPago, $metodo], $monto)) {
                    return 'Completá todos los campos con un monto válido.';
                } elseif (!Filtros::esFechaValida($fechaPago)) {
                    return 'La fecha de pago no es válida.';
                } elseif ($boleta !== null && !self::fechaPagoEsValida($fechaPago, $boleta)) {
                    return 'La fecha de pago no puede ser anterior a la emisión de la boleta.';
                } elseif ($boleta !== null && !self::montoNoSuperaElSaldoAlEditar($monto, $boleta, (float) $pago['monto'])) {
                    return 'El monto supera el saldo pendiente de la boleta.';
                }

                $antes = money_moneda((float) $pago['monto'], $pago['moneda_codigo']) . " ({$pago['metodo']})";
                $despues = money_moneda($monto, $pago['moneda_codigo']) . " ({$metodo})";
                $pagoRepo->actualizar($id, ['monto' => $monto, 'fecha_pago' => $fechaPago, 'metodo' => $metodo]);
                AuditoriaRepository::auditarComoUsuarioActual('editar', 'pago', $id, sprintf('Pago #%d: %s -> %s', $id, $antes, $despues));
                return null;
            });

            if ($resultado === null) {
                header('Location: ?page=pagos&editado=' . $id);
                exit;
            }
            $error = $resultado;
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

        $boleta = $pago['boleta_id'] ? (new BoletaRepository())->porId($pago['boleta_id']) : null;
        if (Peticion::abortarSiConflicto(
            self::boletaAnuladaCongelaElPago($boleta),
            'La boleta de este pago esta anulada y ya tiene su nota de credito: anularlo descontaria la plata dos veces.'
        )) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Atomico por la misma razon que en boletas, y con el chequeo de
            // "todavia estaba activo" dentro de la propia sentencia: si no,
            // dos anulaciones simultaneas duplican la entrada de auditoria.
            // Con la boleta bloqueada, para que no se cruce con su anulacion:
            // si la nota de credito ya conto este pago, anularlo lo descontaria
            // dos veces (la guarda de arriba solo vio la boleta antes del POST).
            $congelado = Database::transaccion(function () use ($pagoRepo, $id, $pago): bool {
                $pagoRepo->bloquear($id);
                if ($pago['boleta_id']) {
                    $boletaRepo = new BoletaRepository();
                    $boletaRepo->bloquear((int) $pago['boleta_id']);
                    if (self::boletaAnuladaCongelaElPago($boletaRepo->porId((int) $pago['boleta_id']))) {
                        return true;
                    }
                }
                if (!$pagoRepo->anularSiEstabaActiva($id)) {
                    return false;
                }
                AuditoriaRepository::auditarComoUsuarioActual('anular', 'pago', $id, sprintf(
                    'Pago #%d (%s)',
                    $id,
                    money_moneda((float) $pago['monto'], $pago['moneda_codigo'])
                ));
                return false;
            });
            if (Peticion::abortarSiConflicto($congelado, 'La boleta de este pago fue anulada y ya tiene su nota de credito: anularlo descontaria la plata dos veces.')) {
                return;
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
