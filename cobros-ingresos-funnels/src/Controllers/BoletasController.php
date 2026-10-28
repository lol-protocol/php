<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\Database;
use App\Descarga;
use App\Dinero;
use App\EnvioUnico;
use App\Etiquetas;
use App\FiltroDePeriodo;
use App\Paginacion;
use App\Peticion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\IngresosYCobrosRepository;
use App\Repositories\MonedaRepository;
use App\Repositories\NotaCreditoRepository;
use App\Validacion;
use App\View;
use Generator;
use LogicException;

final class BoletasController
{
    /**
     * Al editar una boleta, el monto no puede bajar de lo que ya se cobro
     * (dejaria un saldo negativo y la boleta se seguiria mostrando como
     * "pagada" sin avisar que en realidad se sobrecobro). Ni por un centavo:
     * se compara en centavos enteros (ver Dinero), sin el margen de un centavo
     * que antes hacia pasar un monto de 0.01 menos que lo cobrado.
     */
    public static function montoCubreLoYaCobrado(float $monto, array $boleta): bool
    {
        return Dinero::centavos($monto) >= Dinero::centavos($boleta['pagado']);
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

    /**
     * El primer error en los campos de una boleta, o null si estan bien. Son los
     * chequeos que comparten el alta y la edicion, con los mismos mensajes: antes
     * estaban escritos dos veces y un mensaje corregido en una podia quedar viejo
     * en la otra. Devuelve el mensaje y no un booleano porque el formulario lo
     * muestra tal cual, igual que Validacion::primerTextoLargo(); lo que es solo
     * de la edicion (lo ya cobrado, el primer pago) lo chequea editar().
     */
    public static function errorDeLosCampos(string $concepto, float $monto, string $fechaEmision, string $fechaVencimiento): ?string
    {
        if (Validacion::faltanCampos([$concepto, $fechaEmision, $fechaVencimiento], $monto)) {
            return 'Completá todos los campos con un monto válido.';
        }
        if (($largo = Validacion::primerTextoLargo([['El concepto', $concepto, Validacion::MAX_CONCEPTO]])) !== null) {
            return $largo;
        }
        if (!Validacion::fechaEsValida($fechaEmision) || !Validacion::fechaEsValida($fechaVencimiento)) {
            return 'La fecha de emisión o de vencimiento no es válida.';
        }
        if (!self::vencimientoNoAnteriorALaEmision($fechaEmision, $fechaVencimiento)) {
            return 'El vencimiento no puede ser anterior a la emisión.';
        }

        return null;
    }

    public function index(): void
    {
        $filtros = FiltroDePeriodo::rangoActivo();
        ['desde' => $desde, 'hasta' => $hasta] = $filtros;
        $filtros['avisos'] = [...Avisos::confirmaciones('cobros'), ...$filtros['avisos']];
        $pedido = (string) ($_GET['estado'] ?? '');
        $estado = self::estadoDelFiltro($pedido);
        if ($pedido !== '' && $estado === '') {
            // Antes un estado inventado daba la tabla vacia con el selector en "Todos".
            $filtros['avisos'][] = Avisos::atencion('El estado pedido no existe; se muestran las boletas de todos los estados.');
        }
        $cliente = trim((string) ($_GET['cliente'] ?? ''));
        $pagina = Paginacion::pagina();

        $ingresosRepo = new IngresosYCobrosRepository();
        $antiguedad = $ingresosRepo->carteraPorAntiguedad();
        $listado = (new BoletaRepository())->listado($desde, $hasta, $estado ?: null, $cliente ?: null, $pagina);

        View::render('boletas/listado', $filtros + [
            'estado' => $estado,
            'cliente' => $cliente,
            'pagina' => $listado['pagina'],
            'tasas' => MonedaRepository::estadoDeLasTasas(),
            'kpis' => $ingresosRepo->kpis($desde, $hasta),
            'ingresosPorMes' => $ingresosRepo->ingresosPorMes($desde, $hasta),
            'antiguedad' => $antiguedad,
            'carteraPendiente' => array_sum($antiguedad),
            'boletas' => $listado['filas'],
            'totalBoletas' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
            'activePage' => 'cobros',
            'titulo' => 'Cobros e ingresos',
        ]);
    }

    /**
     * Descarga en CSV TODAS las boletas que el listado mostraria con los filtros de la URL
     * (el periodo, el estado y el cliente: los mismos que lee index()), no solo la pagina
     * que se ve. Un filtro invalido cae en su valor por defecto como en la pantalla, pero
     * sin avisos: aca no hay donde mostrarlos.
     */
    public function exportar(): void
    {
        ['desde' => $desde, 'hasta' => $hasta] = FiltroDePeriodo::rangoActivo();
        $estado = self::estadoDelFiltro((string) ($_GET['estado'] ?? ''));
        $cliente = trim((string) ($_GET['cliente'] ?? ''));

        Descarga::csv(
            'cobros',
            ['ID', 'Cliente ID', 'Cliente', 'Concepto', 'Moneda', 'Monto', 'Pagado', 'Saldo', 'Emisión', 'Vencimiento', 'Estado'],
            self::filasParaExportar((new BoletaRepository())->exportar($desde, $hasta, $estado ?: null, $cliente ?: null))
        );
    }

    /**
     * Cada boleta como una fila de CSV. Los montos van como decimales (no como texto):
     * una planilla los trata como numeros, y el escritor neutraliza como formula solo el texto.
     *
     * @param iterable<array<string, mixed>> $boletas
     * @return Generator<int, list<string|int|float|null>>
     */
    private static function filasParaExportar(iterable $boletas): Generator
    {
        foreach ($boletas as $b) {
            yield [
                (int) $b['id'],
                (int) $b['cliente_id'],
                $b['cliente'],
                $b['concepto'],
                $b['moneda_codigo'],
                (float) $b['monto'],
                (float) $b['pagado'],
                (float) $b['saldo'],
                $b['fecha_emision'],
                $b['fecha_vencimiento'],
                Etiquetas::estadoBoleta($b['estado']),
            ];
        }
    }

    /** El estado pedido por URL si es uno de los que existen, y '' (todos) si no. */
    private static function estadoDelFiltro(string $pedido): string
    {
        return array_key_exists($pedido, Etiquetas::estadosBoleta()) ? $pedido : '';
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
            $monto = round((float) ($_POST['monto'] ?? 0), 2);
            $fechaEmision = (string) ($_POST['fecha_emision'] ?? '');
            $fechaVencimiento = (string) ($_POST['fecha_vencimiento'] ?? '');

            $cliente = $clienteId > 0 ? $clienteRepo->porId($clienteId) : null;
            if ($token === null) {
                $error = EnvioUnico::MENSAJE_SIN_TOKEN;
            } elseif ($cliente === null) {
                $error = 'Elegí un cliente válido.';
            } elseif (($errorDeCampos = self::errorDeLosCampos($concepto, $monto, $fechaEmision, $fechaVencimiento)) !== null) {
                $error = $errorDeCampos;
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
                    AuditoriaRepository::auditar('crear', 'boleta', $id, sprintf(
                        'Boleta #%d para %s: "%s" %s',
                        $id,
                        $cliente['nombre'],
                        $concepto,
                        dinero_en_moneda($monto, $cliente['moneda_codigo'])
                    ));

                    return '?page=cobros&creada=' . $id;
                });
                header('Location: ' . $destino);
                exit;
            }
        }

        // El cliente elegido llega por POST (al reenviar con un error) o por ?cliente_id=
        // (el enlace "Nueva boleta" de su ficha); se incluye aunque quede fuera del limite del selector.
        $clienteElegido = (int) ($_POST['cliente_id'] ?? $_GET['cliente_id'] ?? 0);

        View::render('boletas/alta', [
            'clientes' => $clienteRepo->paraSelector($clienteElegido > 0 ? $clienteElegido : null),
            'clientesTruncados' => $clienteRepo->superaElLimiteDelSelector(),
            'error' => $error,
            'valores' => $_POST + ['cliente_id' => $clienteElegido],
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
        if (Peticion::abortarSiConflicto($boleta['anulada'], 'La boleta está anulada y no se puede editar.')) {
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

                if ($boleta === null || $boleta['anulada']) {
                    return 'La boleta fue anulada mientras la editabas.';
                } elseif (($errorDeCampos = self::errorDeLosCampos($concepto, $monto, $fechaEmision, $fechaVencimiento)) !== null) {
                    return $errorDeCampos;
                } elseif (!self::montoCubreLoYaCobrado($monto, $boleta)) {
                    return 'El monto no puede ser menor a lo ya cobrado (' . dinero_en_moneda((float) $boleta['pagado'], $boleta['moneda_codigo']) . ').';
                } elseif (!self::emisionNoPosteriorAlPrimerPago($fechaEmision, $boleta)) {
                    return 'La emisión no puede ser posterior al primer pago de la boleta (' . $boleta['primer_pago'] . ').';
                }

                $antes = sprintf('"%s" %s', $boleta['concepto'], dinero_en_moneda((float) $boleta['monto'], $boleta['moneda_codigo']));
                $despues = sprintf('"%s" %s', $concepto, dinero_en_moneda($monto, $boleta['moneda_codigo']));
                $boletaRepo->actualizar($id, [
                    'concepto' => $concepto,
                    'monto' => $monto,
                    'fecha_emision' => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                ]);
                AuditoriaRepository::auditar('editar', 'boleta', $id, sprintf('Boleta #%d: %s -> %s', $id, $antes, $despues));
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

        View::render('boletas/edicion', [
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
                AuditoriaRepository::auditar('anular', 'boleta', $id, sprintf(
                    'Boleta #%d ("%s", %s)',
                    $id,
                    $boleta['concepto'],
                    dinero_en_moneda((float) $boleta['monto'], $boleta['moneda_codigo'])
                ));
                $this->emitirNotaDeCredito($boleta);
            });
            header('Location: ?page=cobros&anulada=' . $id);
            exit;
        }

        View::render('boletas/anulacion', [
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
        if (Dinero::cuentaComoCero($pagado)) {
            return;
        }

        $notaId = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta['id'],
            'cliente_id' => $boleta['cliente_id'],
            'monto' => $pagado,
            'moneda_codigo' => $boleta['moneda_codigo'],
            'fecha' => date('Y-m-d'),
            'motivo' => sprintf('Anulación de la boleta #%d ("%s")', $boleta['id'], $boleta['concepto']),
        ]);

        AuditoriaRepository::auditar('crear', 'nota_credito', $notaId, sprintf(
            'Nota de crédito #%d por %s (boleta #%d anulada con pagos)',
            $notaId,
            dinero_en_moneda($pagado, $boleta['moneda_codigo']),
            $boleta['id']
        ));
    }
}
