<?php

declare(strict_types=1);

/** The texts in English. Same keys and {marks} as lang/spa.php, which is the source (TextosTest checks it). */
return [
    // Header and footer of every page
    'layout.tipo' => 'Type',
    'layout.buscar' => 'Search',
    'layout.buscar_ph' => 'Search…',
    'layout.mi_cuenta' => 'My account',
    'layout.migas' => 'Breadcrumbs',
    'layout.inicio' => 'Home',
    'layout.carrito' => 'Cart',
    'layout.pedir_retiro' => 'Ask for this page to be withdrawn',
    'layout.pedir_datos' => 'Ask to see, correct or delete my data',
    'tipo.persona' => 'People',
    'tipo.apellido' => 'Surnames',
    'tipo.suceso' => 'Events',
    'tipo.registro' => 'Records',
    'tipo.colecciones' => 'Collections',
    'tipo.organizacion' => 'Organizations',
    'tipo.productos' => 'Products',
    'tipo.categorias' => 'Categories',
    'tipo.etiquetas' => 'Tags',
    'tipo.atributos' => 'Attributes',

    // Error pages (ErrorPage::page)
    'error.400' => 'Bad request',
    'error.401' => 'Restricted access',
    'error.403' => 'Forbidden',
    'error.404' => 'Page not found',
    'error.405' => 'Method not allowed',
    'error.500' => 'Internal server error',
    'error.429_pedidos' => 'Too many requests',
    'error.volver_inicio' => 'Back to home',
    'error.buscar_personas' => 'Search people',
    'error.ver_catalogo' => 'Browse the catalog',

    // Ask for a page to be withdrawn (/r/{id}/)
    'retiro.titulo' => 'Ask for a page to be withdrawn',
    'retiro.title' => 'Ask for withdrawal',
    'retiro.pagina' => 'Page:',
    'retiro.intro' => 'If this page is about you or about a relative who has died and it affects you, or you think it breaks the terms of use, tell us what should be withdrawn and why. You do not need an account. A person reads it: while they review it they may hide the page, and they will answer the e-mail you leave with what they decided.',
    'retiro.motivo' => 'Reason',
    'retiro.motivo.me_nombra' => 'It is about me',
    'retiro.motivo.familiar' => 'It is about a relative of mine who has died',
    'retiro.motivo.terminos' => 'It breaks the terms of use',
    'retiro.motivo.otro' => 'Another reason',
    'retiro.trampa' => 'Do not fill in this field',
    'retiro.detalle' => 'What should be withdrawn, and why',
    'retiro.correo' => 'Your e-mail, so we can answer you',
    'retiro.guardamos' => 'We keep your e-mail and what you write only to answer this request.',
    'retiro.enviar' => 'Send the request',
    'retiro.err.motivo' => 'Choose a reason.',
    'retiro.err.detalle' => 'Tell us what should be withdrawn and why, in up to {max} characters.',
    'retiro.err.contacto' => 'Enter an e-mail where we can answer you.',
    'retiro.flash.trampa' => 'We received your withdrawal request. A person will review it.',
    'retiro.flash.ok' => 'We received your withdrawal request no. {numero}. A person will review it and answer you at {correo}.',
    'retiro.limite.hora' => 'You sent several requests in a row. Please try again later.',
    'retiro.limite.pagina' => 'This page already has several withdrawal requests waiting for a person to review them.',

    // The withdrawn page (410)
    'retirado.titulo' => 'Page withdrawn',
    'retirado.texto' => 'This page was withdrawn at the request of a person, while it is reviewed or for good.',
];
