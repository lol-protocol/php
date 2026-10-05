<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\EnvioUnico;
use App\Filtros;
use App\Paginacion;
use App\Peticion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FunnelRepository;
use App\Repositories\NotaCreditoRepository;
use App\Repositories\PagoRepository;
use App\Repositories\PaisRepository;
use App\Validacion;
use App\View;

final class ClienteController
{
    /** El genero tiene que ser uno de los que ofrece el formulario: lo valida la app y lo restringe la base (migracion 004). */
    public static function generoEsValido(string $genero): bool
    {
        return in_array($genero, ClienteRepository::GENEROS, true);
    }

    /** El segmento tiene que ser uno de los que ofrece el formulario: lo valida la app y lo restringe la base (migracion 004). */
    public static function segmentoEsValido(string $segmento): bool
    {
        return in_array($segmento, ClienteRepository::SEGMENTOS, true);
    }

    /**
     * Nadie nacio despues de hoy. Las dos fechas son Y-m-d y ya validadas, asi
     * que alcanza con compararlas como texto. No se pone piso de edad: un
     * menor es un cliente posible y los reportes lo muestran en su tramo.
     */
    public static function nacimientoNoEsFuturo(string $fechaNacimiento, string $hoy): bool
    {
        return $fechaNacimiento <= $hoy;
    }

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $pagina = Paginacion::pagina();
        $listado = (new ClienteRepository())->buscar($q, $pagina);

        View::render('clientes/index', [
            'q' => $q,
            'pagina' => $listado['pagina'],
            'clientes' => $listado['filas'],
            'totalClientes' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
            'activePage' => 'clientes',
            'titulo' => 'Clientes',
        ]);
    }

    public function ficha(): void
    {
        $id = Peticion::id();
        $cliente = (new ClienteRepository())->porId($id);
        if (Peticion::abortarSiNoExiste($cliente, 'Cliente no encontrado.')) {
            return;
        }

        View::render('clientes/ficha', [
            'cliente' => $cliente,
            'boletas' => (new BoletaRepository())->porCliente($id),
            'pagos' => (new PagoRepository())->porCliente($id),
            'notasCredito' => (new NotaCreditoRepository())->porCliente($id),
            'viajeFunnel' => (new FunnelRepository())->viajeDeCliente($id),
            'avisos' => Avisos::confirmaciones('cliente'),
            'activePage' => 'clientes',
            'titulo' => $cliente['nombre'],
        ]);
    }

    public function nuevo(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = EnvioUnico::tokenRecibido();
            $reenvio = $token === null ? null : EnvioUnico::redireccionPrevia($token);
            if ($reenvio !== null) {
                // Mismo formulario enviado otra vez (doble clic): el cliente ya
                // se creo, se redirige igual que la primera vez.
                header('Location: ' . $reenvio);
                exit;
            }

            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $paisCodigo = (string) ($_POST['pais_codigo'] ?? '');
            $ciudad = trim((string) ($_POST['ciudad'] ?? ''));
            $idioma = trim((string) ($_POST['idioma'] ?? ''));
            $genero = trim((string) ($_POST['genero'] ?? ''));
            $fechaNacimiento = (string) ($_POST['fecha_nacimiento'] ?? '');
            $segmento = trim((string) ($_POST['segmento'] ?? ''));
            // Vacio = no eligio: se usa el valor por defecto. Cualquier otro texto tiene que estar en la lista.
            $genero = $genero === '' ? 'No especifica' : $genero;
            $segmento = $segmento === '' ? 'general' : $segmento;

            if ($token === null) {
                $error = EnvioUnico::MENSAJE_SIN_TOKEN;
            } elseif (Validacion::faltanCampos([$nombre, $email, $paisCodigo, $ciudad, $fechaNacimiento])) {
                $error = 'Completá todos los campos obligatorios.';
            } elseif (!Filtros::esFechaValida($fechaNacimiento)) {
                $error = 'La fecha de nacimiento no es válida.';
            } elseif (!self::nacimientoNoEsFuturo($fechaNacimiento, date('Y-m-d'))) {
                $error = 'La fecha de nacimiento no puede ser posterior a hoy.';
            } elseif (!self::generoEsValido($genero)) {
                $error = 'Elegí un género válido.';
            } elseif (!self::segmentoEsValido($segmento)) {
                $error = 'Elegí un segmento válido.';
            } else {
                try {
                    $destino = EnvioUnico::ejecutar($token, static function () use ($nombre, $email, $segmento, $paisCodigo, $ciudad, $idioma, $genero, $fechaNacimiento): string {
                        $id = (new ClienteRepository())->crear([
                            'nombre' => $nombre,
                            'email' => $email,
                            'segmento' => $segmento,
                            'fecha_alta' => date('Y-m-d'),
                            'pais_codigo' => $paisCodigo,
                            'ciudad' => $ciudad,
                            'idioma' => $idioma ?: 'Espanol',
                            'genero' => $genero,
                            'fecha_nacimiento' => $fechaNacimiento,
                        ]);
                        AuditoriaRepository::auditar('crear', 'cliente', $id, "Cliente #{$id}: {$nombre} ({$email})");

                        return '?page=cliente&id=' . $id . '&creado=1';
                    });
                    header('Location: ' . $destino);
                    exit;
                } catch (\PDOException $e) {
                    $error = Validacion::mensajeDeConflicto($e, 'cliente');
                }
            }
        }

        View::render('clientes/nuevo', [
            'paises' => (new PaisRepository())->listado(),
            'generos' => ClienteRepository::GENEROS,
            'segmentos' => ClienteRepository::SEGMENTOS,
            'error' => $error,
            'activePage' => 'clientes',
            'titulo' => 'Nuevo cliente',
        ]);
    }
}
