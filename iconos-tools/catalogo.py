"""Datos que comparten el generador, el validador y la comprobación de miniaturas: variantes y prefijos de cada set."""

VARIANTS = ('color', 'lineas', 'gris')

# clase de la raíz de los SVG lineas: las reglas de su <style> empiezan por ella. Un <style> en un SVG puesto en línea vale
# para todo el documento, y sin esto una clase ic-<tono> de la página (o de otro icono) cambiaría de color sin querer
SCOPE_CLASS = 'icono25d'

# carpeta de cada set -> prefijos de nombre que admite (el nombre de un icono es <prefijo>_<descripcion>)
PREFIXES = {
    'iconos-genealogia': ('person', 'relationship', 'state', 'view', 'action', 'ui'),
    'iconos-biblioteca': ('book', 'genre', 'format', 'status', 'action', 'attr', 'ui'),
    'iconos-pos': ('catalog', 'order', 'account', 'cart', 'checkout', 'ui', 'action'),
}

# etiqueta de cada prefijo en la galería (los grupos de cada set salen en el orden de sus PREFIXES, no en el de aquí)
GROUPS = {
    'person': 'Personas y parentescos',
    'relationship': 'Relaciones',
    'state': 'Estados',
    'view': 'Vistas',
    'action': 'Acciones',
    'ui': 'Interfaz',
    'book': 'Libros',
    'genre': 'Géneros',
    'format': 'Ediciones y formatos',
    'status': 'Disponibilidad y lectura',
    'attr': 'Atributos',
    'catalog': 'Catálogo',
    'order': 'Pedidos',
    'account': 'Cuenta del cliente',
    'cart': 'Carrito',
    'checkout': 'Pago',
}
