<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditoriaRepository;
use App\View;

final class AuditoriaController
{
    /**
     * Se pagina por cursor (?antes= pide las mas antiguas que esa fila, ?despues=
     * las mas recientes): ver AuditoriaRepository::pagina(). Un ?pagina= de los
     * links de antes de este cambio se ignora y se muestra lo mas reciente.
     */
    public function index(): void
    {
        $pagina = (new AuditoriaRepository())->pagina(
            isset($_GET['antes']) ? (string) $_GET['antes'] : null,
            isset($_GET['despues']) ? (string) $_GET['despues'] : null
        );

        View::render('auditoria/historial', [
            'registros' => $pagina['filas'],
            'masAntiguas' => $pagina['masAntiguas'],
            'masRecientes' => $pagina['masRecientes'],
            'activePage' => 'auditoria',
            'titulo' => 'Auditoría',
        ]);
    }
}
