<?php

declare(strict_types=1);

// Sin Composer: el proyecto no tiene dependencias.
foreach (['Perfil', 'Metadatos', 'Analizador', 'Facebook', 'LinkedIn', 'Twitter', 'Instagram', 'Generico', 'Detector'] as $clase) {
    require_once __DIR__ . "/$clase.php";
}
