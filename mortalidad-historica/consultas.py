"""Consultas sobre los CSV de datos/. Solo biblioteca estándar.

Ninguna función recibe una persona, una ficha ni un origen: trabajan con términos, años y regiones. Las
que hablan de causas devuelven correspondencias posibles de un **término** con su confianza, no
diagnósticos (ver «Qué no hace» en el README).
"""
from comun import IDIOMAS, REGIONES, filas_csv, normalizar

_ORDEN_CONFIANZA = {"alta": 0, "media": 1, "baja": 2}


def causa_moderna(termino, idioma):
    """Causas modernas a las que puede corresponder un término histórico, de más a menos segura.

    Cada resultado es un dict con `causa_id`, `nombre`, `cie10` (lista), `confianza` y `nota`. La
    comparación ignora mayúsculas, tildes y diacríticos. Devuelve una lista vacía si el término no
    está; no inventa una causa. Lanza ValueError si el idioma no es uno de los 20 (ISO 639-3).
    """
    if idioma not in IDIOMAS:
        raise ValueError(f"idioma «{idioma}»: debe ser un código ISO 639-3 de {sorted(IDIOMAS)}")
    modernas = {c["id"]: c for c in filas_csv("causas_modernas")}
    clave = normalizar(termino)
    encontradas = [
        {
            "causa_id": f["causa_id"],
            "nombre": modernas[f["causa_id"]]["nombre_spa"],
            "cie10": modernas[f["causa_id"]]["cie10"].split(";"),
            "confianza": f["confianza"],
            "nota": f["nota"],
        }
        for f in filas_csv("causas_historicas")
        if f["idioma"] == idioma and normalizar(f["termino"]) == clave
    ]
    return sorted(encontradas, key=lambda r: (_ORDEN_CONFIANZA[r["confianza"]], r["nombre"]))


def epidemias_en(anio, region=None):
    """Epidemias que cubren un año, y una región si se da (una de África, América, Asia, Europa, Oceanía).

    Una epidemia sin `hasta` sigue abierta. Cada resultado es la fila del CSV (dict), con `regiones`
    como lista. Lanza ValueError si la región no existe.
    """
    if region is not None and region not in REGIONES:
        raise ValueError(f"región «{region}»: debe ser una de {REGIONES}")
    resultado = []
    for f in filas_csv("epidemias"):
        desde = int(f["desde"])
        hasta = int(f["hasta"]) if f["hasta"] else None
        regiones = f["regiones"].split(";")
        if desde <= anio and (hasta is None or anio <= hasta) and (region is None or region in regiones):
            resultado.append({**f, "regiones": regiones})
    return sorted(resultado, key=lambda f: (int(f["desde"]), f["id"]))


def hitos_de(codigo):
    """Hitos de registro (civil o parroquial) de un país (ISO alfa-2), del más antiguo al más reciente."""
    return sorted((f for f in filas_csv("hitos_registro") if f["codigo"] == codigo), key=lambda f: int(f["desde"]))
