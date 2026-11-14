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


@functools.cache
def _region_por_pais():
    return {f["codigo"]: f["region"] for f in filas_csv("paises")}


def region_de(pais):
    """Región (África, América, Asia, Europa u Oceanía) de un país por su ISO alfa-2, o None si no está."""
    return _region_por_pais().get(pais)


def epidemias_en(anio, region=None, pais=None):
    """Epidemias que cubren un año, filtradas por región y/o por país.

    `region` es una de África, América, Asia, Europa, Oceanía; `pais`, un ISO alfa-2 de paises.csv. Una
    epidemia sin `hasta` sigue abierta. Con `pais`, salen las que lo nombran en `paises` y las que no
    nombran ningún país pero cubren su región (la peste negra para Italia, no para Chile). Cada resultado
    es la fila del CSV (dict), con `regiones` y `paises` como listas. Lanza ValueError si la región o el
    país no existen.
    """
    if region is not None and region not in REGIONES:
        raise ValueError(f"región «{region}»: debe ser una de {REGIONES}")
    if pais is not None and region_de(pais) is None:
        raise ValueError(f"país «{pais}»: no está en paises.csv (ISO alfa-2 en mayúsculas)")
    resultado = []
    for f in filas_csv("epidemias"):
        desde = int(f["desde"])
        hasta = int(f["hasta"]) if f["hasta"] else None
        regiones = f["regiones"].split(";")
        paises = [c for c in f["paises"].split(";") if c]
        if not (desde <= anio and (hasta is None or anio <= hasta)):
            continue
        if region is not None and region not in regiones:
            continue
        if pais is not None and not (pais in paises or (not paises and region_de(pais) in regiones)):
            continue
        resultado.append({**f, "regiones": regiones, "paises": paises})
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
    edad que todavía no está (hoy 0, 5, 15, 30, 45 y 60; todas menos la 0 solo para ambos sexos). No interpola entre
    edades. De los 15 años en adelante la cifra es **modelada** (ver modelo.py), no medida.
    """
    encontrado = _periodo_que_cubre(codigo, anio, edad, sexo)
    return None if encontrado is None else encontrado[0]


def supervivencia(codigo, anio, edad=5, sexo="T"):
    """Probabilidad de llegar con vida a esa edad (de 0 a 1), en la década que contiene `anio`.

    Mismas reglas y límites que `esperanza_restante`; se guarda desde la edad 5 (una mortalidad de
    0 a 5 años de 300 por mil es una supervivencia de 0,7). Devuelve None si no hay dato.
    """
    encontrado = _periodo_que_cubre(codigo, anio, edad, sexo)
    return None if encontrado is None else encontrado[1]


@functools.cache
def _muertes_de_londres():
    return {int(f["anio"]): (int(f["muertes_totales"]), int(f["muertes_viruela"])) for f in filas_csv("londres_muertes")}


def muertes_londres(anio):
    """Muertes en Londres ese año, de las Bills of Mortality y los registros que las siguieron (1629-1902).

    Devuelve {"totales": int, "viruela": int}, o None si el año falta (hay huecos: 1637-1646, 1687-1700,
    1832-1837…). Es la ciudad de Londres de cada época, no el país.
    """
    muertes = _muertes_de_londres().get(anio)
    return None if muertes is None else {"totales": muertes[0], "viruela": muertes[1]}


def exceso_de_muertes_londres(anio, ventana=5):
    """Cuántas veces las muertes totales de ese año superan a las de sus vecinos (mediana de ±`ventana` años).

    Sirve para ver un año de mortalidad anormal (1665, la peste: casi seis veces lo normal). Un 1,0 es un
    año como los de al lado; la población crece, así que la comparación es con los años cercanos y no con el
    promedio de toda la serie. Devuelve None si el año falta o hay menos de 4 vecinos con dato.
    """
    serie = _muertes_de_londres()
    if anio not in serie:
        return None
    vecinos = sorted(serie[a][0] for a in range(anio - ventana, anio + ventana + 1) if a != anio and a in serie)
    if len(vecinos) < 4:
        return None
    mitad = len(vecinos) // 2
    mediana = vecinos[mitad] if len(vecinos) % 2 else (vecinos[mitad - 1] + vecinos[mitad]) / 2
    return serie[anio][0] / mediana


