"""Ayuda común de los módulos que generan tablas en Markdown (ambiguedad, auditoria, categorias y revision).

Solo biblioteca estándar. Antes cada módulo tenía su copia de `celda`; una corrección en una dejaba las otras viejas.
"""


def celda(texto):
    """El texto como celda de una tabla Markdown: sin `|` ni saltos de línea, que partirían la fila."""
    return str(texto).replace("|", "&#124;").replace("\n", " ")


def texto_valido(valor, prohibidos="|\n", sin_espacios_sobrantes=False):
    """Si un texto de un CSV sirve como celda: no vacío, sin ninguno de los `prohibidos` y, si se pide, sin espacios al borde.

    Los validadores repetían esta comprobación con pequeñas variantes (qué caracteres se prohíben y si importan los espacios).
    """
    return (bool(valor.strip()) and not any(c in valor for c in prohibidos)
            and not (sin_espacios_sobrantes and valor != valor.strip()))
