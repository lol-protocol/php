<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\Database;
use App\FechaDeDevolucion;
use App\Paginacion;
use App\Peticion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\NotaCreditoRepository;
use App\View;
use InvalidArgumentException;

/**
 * Las notas de credito (devoluciones a clientes) y el registro de cuando se
 * devolvio la plata. Las notas no se crean aca: las emite BoletasController al
 * anular una boleta que ya tenia pagos.
 */
final class NotasCreditoController
{
    public function index(): void
    {
        $avisos = Avisos::confirmaciones('notas');
        $pedido = (string) ($_GET['estado'] ?? '');
        $estado = NotaCreditoRepository::estadoPedido($pedido);
        if ($pedido !== '' && $estado === '') {
            $avisos[] = Avisos::atencion('El estado pedido no existe; se muestran las notas pendientes de devolver.');
        }
        $estado = $estado ?: 'pendientes';

        $repo = new NotaCreditoRepository();
        $listado = $repo->listado($estado, Paginacion::pagina());

        View::render('notas/listado', [
            'avisos' => $avisos,
            'estado' => $estado,
            'resumen' => $repo->pendientes(),
            'notas' => $listado['filas'],
            'totalNotas' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
            'pagina' => $listado['pagina'],
            'activePage' => 'notas',
            'titulo' => 'Notas de crédito',
        ]);
    }

    /** Anota el dia en que se le devolvio la plata al cliente por una nota. */
    public function devolver(): void
    {
        $id = Peticion::id();
        $repo = new NotaCreditoRepository();
        $nota = $repo->porId($id);
        if (Peticion::abortarSiNoExiste($nota, 'Nota de crédito no encontrada.')) {
            return;
        }

        $hoy = date('Y-m-d');
        $fecha = $hoy;
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($nota['fecha_devolucion'] !== null) {
                // Ya estaba devuelta (un doble clic, o un formulario viejo): no se
                // registra ni se audita otra vez, y se va a donde fue la primera.
                header('Location: ?page=notas&devuelta=' . $id);
                exit;
            }

            $fecha = (string) ($_POST['fecha_devolucion'] ?? '');
            try {
                $valida = FechaDeDevolucion::validar($fecha, $nota['fecha'], $hoy);
            } catch (InvalidArgumentException $e) {
                $valida = null;
                $error = $e->getMessage();
            }

            if ($valida !== null) {
                // Atomico: la devolucion y su auditoria juntas. Si dos envios llegan a
                // la vez, el UPDATE deja pasar solo a uno (ver
                // registrarDevolucionSiEstabaPendiente()) y el otro no audita nada.
                Database::transaccion(static function () use ($repo, $id, $nota, $valida): void {
                    if (!$repo->registrarDevolucionSiEstabaPendiente($id, $valida)) {
                        return;
                    }
                    AuditoriaRepository::auditar('devolver', 'nota_credito', $id, sprintf(
                        'Nota de crédito #%d (boleta #%d): %s devueltos a %s el %s',
                        $id,
                        $nota['boleta_id'],
                        dinero_en_moneda((float) $nota['monto'], $nota['moneda_codigo']),
                        $nota['cliente'],
                        $valida
                    ));
                });
                header('Location: ?page=notas&devuelta=' . $id);
                exit;
            }
        }

        View::render('notas/devolucion', [
            'nota' => $nota,
            'fecha' => $fecha,
            'hoy' => $hoy,
            'error' => $error,
            'activePage' => 'notas',
            'titulo' => 'Registrar devolución',
        ]);
    }
}
