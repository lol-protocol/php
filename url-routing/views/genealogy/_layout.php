<?php
use App\Support\Config;

// The brand shown in the header and the page titles. It lives in .env (SITE_NAME_GENEALOGY),
// not here; a blank value falls back to the plain name of the site.
$sitio = trim((string)Config::get('SITE_NAME_GENEALOGY', '')) ?: 'Genealogía';
$tiposBusqueda = [10 => t('tipo.persona'), 6 => t('tipo.apellido'), 9 => t('tipo.suceso'), 8 => t('tipo.registro'), 7 => t('tipo.colecciones'), 5 => t('tipo.organizacion')];
// Every page of a person, event, record, tree, surname or organization links to /r/{id}/ to ask
// for it to be withdrawn (RetiroController). Not on an error page: there is nothing to withdraw. Not for the
// owner either: the form is for a visitor, it answers 404 for what a visitor can't see (an event with a living
// participant, a private colección) and the owner sees those pages, so her link would lead nowhere; she decides
// from the queue at /0/3/.
$primero = explode('/', trim((string)strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), '/'))[0];
if (ctype_digit($primero) && (http_response_code() ?: 200) === 200 && !\App\Support\AccessPolicy::esPropietario()
    && \App\Support\ServiceLocator::getInstance()->getRouter()->typeForLength(strlen($primero)) !== null) {
    $pedirRetiro = '/r/' . $primero . '/';
    // And a persona's page, to ask for her data (SolicitudController): only for a persona, which is a 10-digit id.
    if (\App\Support\ServiceLocator::getInstance()->getRouter()->typeForLength(strlen($primero)) === 'persona') {
        $pedirDatos = '/s/' . $primero . '/';
    }
}
include __DIR__ . '/../_layout.php';
