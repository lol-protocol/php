<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Filtros;
use App\Paginacion;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FunnelRepository;
use App\Repositories\PagoRepository;
use App\Repositories\PaisRepository;
use App\View;

final class ClienteController
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $pagina = Paginacion::pagina();
        $listado = (new ClienteRepository())->buscar($q, $pagina);

        View::render('clientes/index', [
            'q' => $q,
            'pagina' => $pagina,
            'clientes' => $listado['filas'],
            'totalClientes' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
            'activePage' => 'clientes',
            'titulo' => 'Clientes',
        ]);
    }

    public function ficha(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $cliente = (new ClienteRepository())->porId($id);
        if ($cliente === null) {
            http_response_code(404);
            echo 'Cliente no encontrado.';
            return;
        }

        View::render('clientes/ficha', [
            'cliente' => $cliente,
            'boletas' => (new BoletaRepository())->porCliente($id),
            'pagos' => (new PagoRepository())->porCliente($id),
            'viajeFunnel' => (new FunnelRepository())->viajeDeCliente($id),
            'activePage' => 'clientes',
            'titulo' => $cliente['nombre'],
        ]);
    }

    public function nuevo(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $paisCodigo = (string) ($_POST['pais_codigo'] ?? '');
            $ciudad = trim((string) ($_POST['ciudad'] ?? ''));
            $idioma = trim((string) ($_POST['idioma'] ?? ''));
            $genero = (string) ($_POST['genero'] ?? '');
            $fechaNacimiento = (string) ($_POST['fecha_nacimiento'] ?? '');
            $segmento = (string) ($_POST['segmento'] ?? 'general');

            if ($nombre === '' || $email === '' || $paisCodigo === '' || $ciudad === '' || $fechaNacimiento === '') {
                $error = 'Completá todos los campos obligatorios.';
            } elseif (!Filtros::esFechaValida($fechaNacimiento)) {
                $error = 'La fecha de nacimiento no es válida.';
            } else {
                try {
                    $id = (new ClienteRepository())->crear([
                        'nombre' => $nombre,
                        'email' => $email,
                        'segmento' => $segmento,
                        'fecha_alta' => date('Y-m-d'),
                        'pais_codigo' => $paisCodigo,
                        'ciudad' => $ciudad,
                        'idioma' => $idioma ?: 'Espanol',
                        'genero' => $genero ?: 'No especifica',
                        'fecha_nacimiento' => $fechaNacimiento,
                    ]);
                    AuditoriaRepository::auditarComoUsuarioActual('crear', 'cliente', $id, "Cliente #{$id}: {$nombre} ({$email})");
                    header('Location: ?page=cliente&id=' . $id);
                    exit;
                } catch (\PDOException $e) {
                    $error = str_contains($e->getMessage(), 'unique')
                        ? 'Ya existe un cliente con ese email.'
                        : 'No se pudo crear el cliente.';
                }
            }
        }

        View::render('clientes/nuevo', [
            'paises' => (new PaisRepository())->listado(),
            'error' => $error,
            'activePage' => 'clientes',
            'titulo' => 'Nuevo cliente',
        ]);
    }
}
