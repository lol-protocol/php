"""Consultas sobre los CSV de datos/. Solo biblioteca estándar.

Ninguna función recibe una persona, una ficha ni un origen: trabajan con términos, años y regiones. Las
que hablan de causas devuelven correspondencias posibles de un **término** con su confianza, no
diagnósticos (ver «Qué no hace» en el README).
"""
import functools

from comun import IDIOMAS, REGIONES, SEXOS, filas_csv, normalizar

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


@functools.cache
def _periodos_de_esperanza():
    """{(código, sexo, edad): [(desde, hasta, esperanza, supervivencia o None)]} de esperanza_vida.csv."""
    indice = {}
    for f in filas_csv("esperanza_vida"):
        supervivencia = float(f["supervivencia_hasta_edad"]) if f["supervivencia_hasta_edad"] else None
        indice.setdefault((f["codigo"], f["sexo"], int(f["edad"])), []).append(
            (int(f["desde"]), int(f["hasta"]), float(f["esperanza_restante"]), supervivencia))
    return indice


def _periodo_que_cubre(codigo, anio, edad, sexo):
    if sexo not in SEXOS:
        raise ValueError(f"sexo «{sexo}»: debe ser uno de {SEXOS}")
    for desde, hasta, esperanza, supervivencia in _periodos_de_esperanza().get((codigo, sexo, edad), ()):
        if desde <= anio <= hasta:
            return esperanza, supervivencia
    return None


def esperanza_restante(codigo, anio, edad=0, sexo="T"):
    """Años que le quedaban por vivir, en promedio, a quien tenía esa edad en ese país y ese año.

    `codigo` es un ISO alfa-2; `sexo`, `M`, `F` o `T` (ambos). Es una cifra de **período** (la mortalidad de
    la década que contiene `anio`), no de una generación, y un promedio de toda la población: no dice cuánto
    iba a vivir una persona. Devuelve None si no hay dato: país sin serie, año fuera de 1800-2009, o una
    edad que todavía no está (hoy solo 0 y 5; 5 solo para ambos sexos). No interpola entre edades.
    """
    encontrado = _periodo_que_cubre(codigo, anio, edad, sexo)
    return None if encontrado is None else encontrado[0]


def supervivencia(codigo, anio, edad=5, sexo="T"):
    """Probabilidad de llegar con vida a esa edad (de 0 a 1), en la década que contiene `anio`.

    Mismas reglas y límites que `esperanza_restante`; hoy solo se guarda para la edad 5 (una mortalidad de
    0 a 5 años de 300 por mil es una supervivencia de 0,7). Devuelve None si no hay dato.
    """
    encontrado = _periodo_que_cubre(codigo, anio, edad, sexo)
    return None if encontrado is None else encontrado[1]
