<?php
use App\Support\Config;

// The brand shown in the header and the page titles. It lives in .env (SITE_NAME_GENEALOGY),
// not here; a blank value falls back to the plain name of the site.
$sitio = trim((string)Config::get('SITE_NAME_GENEALOGY', '')) ?: 'Genealogía';
$tiposBusqueda = [10 => 'Personas', 6 => 'Apellidos', 9 => 'Sucesos', 8 => 'Registros', 7 => 'Colecciones', 5 => 'Organizaciones'];
// Every page of a person, event, record, tree, surname or organization links to /r/{id}/ to ask
// for it to be withdrawn (RetiroController). Not on an error page: there is nothing to withdraw.
$primero = explode('/', trim((string)strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), '/'))[0];
if (ctype_digit($primero) && (http_response_code() ?: 200) === 200
    && \App\Support\ServiceLocator::getInstance()->getRouter()->typeForLength(strlen($primero)) !== null) {
    $pedirRetiro = '/r/' . $primero . '/';
}
include __DIR__ . '/../_layout.php';
