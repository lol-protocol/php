<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\Database;
use App\Descarga;
use App\EnvioUnico;
use App\Etiquetas;
use App\FiltroDePeriodo;
use App\Filtros;
use App\Paginacion;
use App\Peticion;
use App\ReglasDePago;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\IngresosYCobrosRepository;
use App\Repositories\MonedaRepository;
use App\Repositories\NotaCreditoRepository;
use App\Repositories\PagoRepository;
use App\View;
use Generator;

final class PagosController
{
    /** Por que no se anula un pago cuya boleta ya se anulo: la nota de credito ya devolvio lo cobrado. */
    private const NO_SE_ANULA_UN_PAGO_DE_BOLETA_ANULADA = 'La boleta de este pago está anulada y ya tiene su nota de crédito: anularlo descontaría la plata dos veces.';

    public function index(): void
    {
        $filtros = FiltroDePeriodo::rangoActivo();
        ['desde' => $desde, 'hasta' => $hasta] = $filtros;
        $filtros['avisos'] = [...Avisos::confirmaciones('pagos'), ...$filtros['avisos']];
        $cliente = Filtros::texto('cliente');
        $pagina = Paginacion::pagina();

        $ingresosRepo = new IngresosYCobrosRepository();
        $listado = (new PagoRepository())->listado($desde, $hasta, $cliente ?: null, $pagina);

        View::render('pagos/listado', $filtros + [
            'cliente' => $cliente,
            'pagina' => $listado['pagina'],
            'tasas' => MonedaRepository::estadoDeLasTasas(),
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

    /**
     * Descarga en CSV TODOS los pagos que el listado mostraria con los filtros de la URL (el
     * periodo y el cliente: los mismos que lee index()), no solo la pagina que se ve. Un
     * periodo invalido cae en el de por defecto como en la pantalla, pero sin avisos.
     */
    public function exportar(): void
    {
        ['desde' => $desde, 'hasta' => $hasta] = FiltroDePeriodo::rangoActivo();
        $cliente = Filtros::texto('cliente');

        Descarga::csv(
            'pagos',
            ['ID', 'Fecha', 'Cliente ID', 'Cliente', 'Boleta', 'Método', 'Moneda', 'Monto', 'Anulado'],
            self::filasParaExportar((new PagoRepository())->exportar($desde, $hasta, $cliente ?: null))
        );
    }

    /**
     * Cada pago como una fila de CSV: el monto va como decimal (no como texto) y la boleta
     * vacia si es un anticipo.
     *
     * @param iterable<array<string, mixed>> $pagos
     * @return Generator<int, list<string|int|float|null>>
     */
    private static function filasParaExportar(iterable $pagos): Generator
    {
        foreach ($pagos as $p) {
            yield [
                (int) $p['id'],
                $p['fecha_pago'],
                (int) $p['cliente_id'],
                $p['cliente'],
                $p['boleta_id'] === null ? null : (int) $p['boleta_id'],
                Etiquetas::metodoPago($p['metodo']),
                $p['moneda_codigo'],
                (float) $p['monto'],
                $p['anulada'] ? 'Sí' : 'No',
            ];
        }
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
                Peticion::redirigir($reenvio);
            }

            $monto = Peticion::monto();
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

                    // El saldo se valida en la moneda de la boleta: el pago tiene que
                    // quedar en esa misma moneda, no en la que hoy tenga el pais
                    // del cliente (si cambiara, se restarian monedas distintas).
                    $moneda = $boleta !== null ? $boleta['moneda_codigo'] : ($clienteElegido['moneda_codigo'] ?? null);

                    if ($clienteElegido === null) {
                        return ['error' => 'Elegí un cliente válido.'];
                    } elseif (($errorDeCampos = ReglasDePago::errorDeLosCampos($fechaPago, $metodo, $monto, $moneda)) !== null) {
                        return ['error' => $errorDeCampos];
                    } elseif ($boletaId > 0 && !ReglasDePago::boletaEsValidaParaCliente($boleta, $clienteId)) {
                        return ['error' => 'La boleta elegida no es válida para este cliente.'];
                    } elseif ($boleta !== null && ($errorContraLaBoleta = ReglasDePago::errorContraLaBoleta($fechaPago, $monto, $boleta)) !== null) {
                        return ['error' => $errorContraLaBoleta];
                    }

                    return ['redireccion' => EnvioUnico::ejecutar($token, static function () use ($boletaId, $clienteId, $monto, $moneda, $clienteElegido, $fechaPago, $metodo): string {
                        $id = (new PagoRepository())->crear([
                            'boleta_id' => $boletaId ?: null,
                            'cliente_id' => $clienteId,
                            'monto' => $monto,
                            'moneda_codigo' => $moneda,
                            'fecha_pago' => $fechaPago,
                            'metodo' => $metodo,
                        ]);
                        AuditoriaRepository::auditar('crear', 'pago', $id, sprintf(
                            'Pago #%d de %s: %s%s',
                            $id,
                            $clienteElegido['nombre'],
                            dinero_en_moneda($monto, $moneda),
                            $boletaId ? " (boleta #{$boletaId})" : ' (anticipo)'
                        ));

                        return '?page=pagos&creado=' . $id;
                    })];
                });

                if (isset($resultado['redireccion'])) {
                    Peticion::redirigir($resultado['redireccion']);
                }
                $error = $resultado['error'];
            }
        }

        View::render('pagos/alta', [
            'clientes' => $clienteRepo->paraSelector(),
            'clientesTruncados' => $clienteRepo->superaElLimiteDelSelector(),
            'clienteElegido' => $clienteElegido,
            'boletasCliente' => $clienteElegido ? $boletaRepo->porCliente($clienteId) : [],
            'error' => $error,
            'valores' => $_POST,
            'activePage' => 'pagos',
            'titulo' => 'Nuevo pago',
        ]);
    }

    public function editar(): void
    {
        $id = Peticion::id();
        $pagoRepo = new PagoRepository();
        $pago = $pagoRepo->porId($id);
        if (Peticion::abortarSiNoExiste($pago, 'Pago no encontrado.')) {
            return;
        }
        if (Peticion::abortarSiConflicto($pago['anulada'], 'El pago está anulado y no se puede editar.')) {
            return;
        }

        $boleta = $pago['boleta_id'] ? (new BoletaRepository())->porId($pago['boleta_id']) : null;
        if (Peticion::abortarSiConflicto(
            ReglasDePago::boletaAnuladaCongelaElPago($boleta),
            'La boleta de este pago está anulada y ya tiene su nota de crédito: editarlo descuadraría la devolución.'
        )) {
            return;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $monto = Peticion::monto();
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

                if ($pago['anulada'] || ReglasDePago::boletaAnuladaCongelaElPago($boleta)) {
                    return 'El pago o su boleta fueron anulados mientras lo editabas.';
                } elseif (($errorDeCampos = ReglasDePago::errorDeLosCampos($fechaPago, $metodo, $monto, $pago['moneda_codigo'])) !== null) {
                    return $errorDeCampos;
                } elseif ($boleta !== null && ($errorContraLaBoleta = ReglasDePago::errorContraLaBoleta($fechaPago, $monto, $boleta, (float) $pago['monto'])) !== null) {
                    return $errorContraLaBoleta;
                }

                $antes = dinero_en_moneda((float) $pago['monto'], $pago['moneda_codigo']) . " ({$pago['metodo']})";
                $despues = dinero_en_moneda($monto, $pago['moneda_codigo']) . " ({$metodo})";
                $pagoRepo->actualizar($id, ['monto' => $monto, 'fecha_pago' => $fechaPago, 'metodo' => $metodo]);
                AuditoriaRepository::auditar('editar', 'pago', $id, sprintf('Pago #%d: %s -> %s', $id, $antes, $despues));
                return null;
            });

            if ($resultado === null) {
                Peticion::redirigir('?page=pagos&editado=' . $id);
            }
            $error = $resultado;
            $pago = array_merge($pago, ['monto' => $monto, 'fecha_pago' => $fechaPago, 'metodo' => $metodo]);
        }

        View::render('pagos/edicion', [
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
            ReglasDePago::boletaAnuladaCongelaElPago($boleta),
            self::NO_SE_ANULA_UN_PAGO_DE_BOLETA_ANULADA
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
                    if (ReglasDePago::boletaAnuladaCongelaElPago($boletaRepo->porId((int) $pago['boleta_id']))) {
                        return true;
                    }
                }
                if (!$pagoRepo->anularSiEstabaActiva($id)) {
                    return false;
                }
                AuditoriaRepository::auditar('anular', 'pago', $id, sprintf(
                    'Pago #%d (%s)',
                    $id,
                    dinero_en_moneda((float) $pago['monto'], $pago['moneda_codigo'])
                ));
                return false;
            });
            if (Peticion::abortarSiConflicto($congelado, self::NO_SE_ANULA_UN_PAGO_DE_BOLETA_ANULADA)) {
                return;
            }
            Peticion::redirigir('?page=pagos&anulado=' . $id);
        }

        View::render('pagos/anulacion', [
            'pago' => $pago,
            'activePage' => 'pagos',
            'titulo' => 'Anular pago',
        ]);
    }

}
