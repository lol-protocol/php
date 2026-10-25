"""Datos que comparten el generador, el validador y la comprobación de miniaturas: variantes y prefijos de cada set."""

VARIANTS = ('color', 'lineas', 'gris')

# carpeta de cada set -> prefijos de nombre que admite (el nombre de un icono es <prefijo>_<descripcion>)
PREFIXES = {
    'iconos-genealogia': ('person', 'relationship', 'state', 'view', 'action', 'ui'),
    'iconos-biblioteca': ('book', 'genre', 'format', 'status', 'action', 'attr', 'ui'),
    'iconos-pos': ('catalog', 'order', 'account'),
}

# etiqueta de cada prefijo en la galería, en el orden en que aparecen sus grupos
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
}
