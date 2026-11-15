<?php

declare(strict_types=1);

/**
 * Los textos en español: la fuente. Cada idioma activo con traducción tiene su lang/{código}.php con las mismas claves
 * y las mismas {marcas} (TextosTest lo comprueba). Ver Support/Textos.php.
 */
return [
    // Encabezado y pie de todas las páginas
    'layout.tipo' => 'Tipo',
    'layout.buscar' => 'Buscar',
    'layout.buscar_ph' => 'Buscar…',
    'layout.mi_cuenta' => 'Mi cuenta',
    'layout.migas' => 'Migas de pan',
    'layout.inicio' => 'Inicio',
    'layout.carrito' => 'Carrito',
    'layout.pedir_retiro' => 'Pedir el retiro de esta página',
    'layout.pedir_datos' => 'Pedir ver, corregir o borrar mis datos',
    'tipo.persona' => 'Personas',
    'tipo.apellido' => 'Apellidos',
    'tipo.suceso' => 'Sucesos',
    'tipo.registro' => 'Registros',
    'tipo.colecciones' => 'Colecciones',
    'tipo.organizacion' => 'Organizaciones',
    'tipo.productos' => 'Productos',
    'tipo.categorias' => 'Categorías',
    'tipo.etiquetas' => 'Etiquetas',
    'tipo.atributos' => 'Atributos',

    // Páginas de error (ErrorPage::page)
    'error.400' => 'Solicitud incorrecta',
    'error.401' => 'Acceso restringido',
    'error.403' => 'Prohibido',
    'error.404' => 'Página no encontrada',
    'error.405' => 'Método no permitido',
    'error.500' => 'Error interno del servidor',
    'error.429_pedidos' => 'Demasiados pedidos',
    'error.volver_inicio' => 'Volver al inicio',
    'error.buscar_personas' => 'Buscar personas',
    'error.ver_catalogo' => 'Ver el catálogo',

    // Pedir el retiro de una página (/r/{id}/)
    'retiro.titulo' => 'Pedir el retiro de una página',
    'retiro.title' => 'Pedir retiro',
    'retiro.pagina' => 'Página:',
    'retiro.intro' => 'Si esta página habla de ti o de un familiar fallecido y te afecta, o crees que infringe los términos de uso, cuéntanos qué hay que retirar y por qué. No necesitas una cuenta. Lo revisa una persona: mientras lo revisa puede ocultar la página, y te responde al correo que dejes con lo que decidió.',
    'retiro.motivo' => 'Motivo',
    'retiro.motivo.me_nombra' => 'Habla de mí',
    'retiro.motivo.familiar' => 'Habla de un familiar mío fallecido',
    'retiro.motivo.terminos' => 'Infringe los términos de uso',
    'retiro.motivo.otro' => 'Otro motivo',
    'retiro.trampa' => 'No rellenes este campo',
    'retiro.detalle' => 'Qué hay que retirar y por qué',
    'retiro.correo' => 'Tu correo, para responderte',
    'retiro.guardamos' => 'Guardamos tu correo y lo que escribas sólo para responder a este pedido.',
    'retiro.enviar' => 'Enviar el pedido',
    'retiro.err.motivo' => 'Elige un motivo.',
    'retiro.err.detalle' => 'Cuenta qué hay que retirar y por qué, en hasta {max} caracteres.',
    'retiro.err.contacto' => 'Escribe un correo donde podamos responderte.',
    'retiro.flash.trampa' => 'Recibimos tu pedido de retiro. Lo revisa una persona.',
    'retiro.flash.ok' => 'Recibimos tu pedido de retiro n.º {numero}. Lo revisa una persona y te responde a {correo}.',
    'retiro.limite.hora' => 'Enviaste varios pedidos seguidos. Vuelve a intentarlo más tarde.',
    'retiro.limite.pagina' => 'Esta página ya tiene varios pedidos de retiro esperando a que una persona los revise.',

    // La página retirada (410)
    'retirado.titulo' => 'Página retirada',
    'retirado.texto' => 'Esta página se retiró a pedido de una persona, mientras se revisa o de forma definitiva.',
];
