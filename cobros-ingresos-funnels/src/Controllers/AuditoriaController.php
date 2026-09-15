<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Paginacion;
use App\Repositories\AuditoriaRepository;
use App\View;

final class AuditoriaController
{
    public function index(): void
    {
        $pagina = Paginacion::pagina();
        $listado = (new AuditoriaRepository())->listado($pagina);

        View::render('auditoria/index', [
            'registros' => $listado['filas'],
            'totalRegistros' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
            'pagina' => $pagina,
            'activePage' => 'auditoria',
            'titulo' => 'Auditoría',
        ]);
    }
}
