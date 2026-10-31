<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\OrdenRepository;
use App\Repositories\POS\ProductoRepository;
use App\Repositories\POS\SinStock;
use App\Support\Cart;
use App\Support\SessionManager;

/**
 * /p/ — the purchase: summary (/p/), shipping address (/p/1/), payment
 * (/p/2/) and confirmation (/p/3/), whose POST places the order. Like the
 * cart, it lives in the session: there are no user accounts. Payment isn't taken
 * online: an order starts 'pendiente' until the shop confirms it was paid.
 */
class CheckoutController extends BaseController
{
    /** The shipping address is free text: these bound it. */
    private const DIRECCION_MIN = 10;
    private const DIRECCION_MAX = 500;

    /** Orders placed from this session: they can be opened from it without being the owner (see OrderController). */
    public const SESION_ORDENES = 'ordenes';

    /**
     * The cart checked against the catalog, or the redirect that sends the
     * buyer back to it: when it is empty, or mixes currencies (an order has one).
     *
     * @return array{list<array>, string, int}|string [lineas, moneda, total] or a response
     */
    private function resumen(): array|string
    {
        $carrito = new Cart();
        [$lineas, $totales] = $carrito->revisar(
            (new ProductoRepository($this->db()))->variantesPorSkus($carrito->skus())
        );

        if ($lineas === []) {
            $this->flash('Tu carrito está vacío.');
            return $this->redirect('/c/');
        }
        if (count($totales) > 1) {
            $this->flash('Tu carrito tiene productos en distintas monedas: paga cada moneda por separado.');
            return $this->redirect('/c/');
        }

        return [$lineas, (string)array_key_first($totales), (int)reset($totales)];
    }

    private static function direccion(): ?string
    {
        $direccion = $_SESSION['envio'] ?? null;
        return is_string($direccion) ? $direccion : null;
    }

    public function index(array $params = []): string
    {
        $resumen = $this->resumen();
        if (is_string($resumen)) {
            return $resumen;
        }
        [$lineas, $moneda, $total] = $resumen;

        return view('pos/checkout/index', ['lineas' => $lineas, 'moneda' => $moneda, 'total' => $total]);
    }

    public function shipping(array $params = []): string
    {
        $denegado = $this->exigirMetodo('GET', 'HEAD', 'POST');
        if ($denegado !== null) {
            return $denegado;
        }
        $resumen = $this->resumen();
        if (is_string($resumen)) {
            return $resumen;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrfToken()) {
                return $this->handleForbidden();
            }
            $direccion = trim((string)preg_replace('/\s+/u', ' ', $this->postString('direccion')));
            $largo = mb_strlen($direccion);
            if (!mb_check_encoding($direccion, 'UTF-8') || $largo < self::DIRECCION_MIN || $largo > self::DIRECCION_MAX) {
                return $this->handleBadRequest('La dirección debe tener entre ' . self::DIRECCION_MIN . ' y ' . self::DIRECCION_MAX . ' caracteres');
            }

            SessionManager::getInstance()->ensureStarted();
            $_SESSION['envio'] = $direccion;
            return $this->redirect('/p/2/');
        }

        return view('pos/checkout/shipping', ['direccion' => self::direccion() ?? '']);
    }

    public function payment(array $params = []): string
    {
        $resumen = $this->resumen();
        if (is_string($resumen)) {
            return $resumen;
        }
        if (self::direccion() === null) {
            $this->flash('Primero indica a dónde enviamos tu compra.');
            return $this->redirect('/p/1/');
        }

        return view('pos/checkout/payment');
    }

    public function confirm(array $params = []): string
    {
        $denegado = $this->exigirMetodo('GET', 'HEAD', 'POST');
        if ($denegado !== null) {
            return $denegado;
        }
        $resumen = $this->resumen();
        if (is_string($resumen)) {
            return $resumen;
        }
        $direccion = self::direccion();
        if ($direccion === null) {
            $this->flash('Primero indica a dónde enviamos tu compra.');
            return $this->redirect('/p/1/');
        }
        [$lineas, $moneda, $total] = $resumen;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return view('pos/checkout/confirm', ['lineas' => $lineas, 'moneda' => $moneda, 'total' => $total, 'direccion' => $direccion]);
        }

        if (!$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $carrito = new Cart();
        try {
            $orden = (new OrdenRepository($this->db()))->crear($carrito->items(), $direccion, self::DEFAULT_USER_ID);
        } catch (SinStock $e) {
            $this->flash("Ya no queda stock suficiente de {$e->nombre}: revisa tu carrito.");
            return $this->redirect('/c/');
        }

        $carrito->vaciar();
        SessionManager::getInstance()->ensureStarted();
        unset($_SESSION['envio']);
        $_SESSION[self::SESION_ORDENES][] = $orden['codigo'];
        $this->flash('¡Gracias! Recibimos tu orden. Guarda este enlace para seguirla.');

        return $this->redirect(enlace('orden', $orden['codigo']));
    }
}
