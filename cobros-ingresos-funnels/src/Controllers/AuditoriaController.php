<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditoriaRepository;
use App\View;

final class AuditoriaController
{
    public function index(): void
    {
        View::render('auditoria/index', [
            'registros' => (new AuditoriaRepository())->listado(),
            'activePage' => 'auditoria',
            'titulo' => 'Auditoría',
        ]);
    }
}
