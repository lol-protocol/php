<?php
use App\Support\Config;

// The brand shown in the header and the page titles. It lives in .env (SITE_NAME_GENEALOGY),
// not here; a blank value falls back to the plain name of the site.
$sitio = trim((string)Config::get('SITE_NAME_GENEALOGY', '')) ?: 'Genealogía';
$tiposBusqueda = [10 => 'Personas', 6 => 'Apellidos', 9 => 'Sucesos', 8 => 'Registros', 7 => 'Colecciones', 5 => 'Organizaciones'];
include __DIR__ . '/../_layout.php';
