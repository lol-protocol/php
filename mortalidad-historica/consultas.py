"""Consultas sobre los CSV de datos/. Solo biblioteca estándar.

Ninguna función recibe una persona, una ficha ni un origen: trabajan con términos, años y regiones. Las
que hablan de causas devuelven correspondencias posibles de un **término** con su confianza, no
diagnósticos (ver «Qué no hace» en el README).
"""
import functools

import modelo
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
    """{(código, sexo, edad): [(desde, hasta, esperanza, supervivencia o None, banda o None)]} de esperanza_vida.csv."""
    indice = {}
    for f in filas_csv("esperanza_vida"):
        supervivencia = float(f["supervivencia_hasta_edad"]) if f["supervivencia_hasta_edad"] else None
        banda = (float(f["incertidumbre_baja"]), float(f["incertidumbre_alta"])) if f["incertidumbre_baja"] else None
        indice.setdefault((f["codigo"], f["sexo"], int(f["edad"])), []).append(
            (int(f["desde"]), int(f["hasta"]), float(f["esperanza_restante"]), supervivencia, banda))
    return indice


def _periodo_que_cubre(codigo, anio, edad, sexo):
    if sexo not in SEXOS:
        raise ValueError(f"sexo «{sexo}»: debe ser uno de {SEXOS}")
    for desde, hasta, esperanza, supervivencia, banda in _periodos_de_esperanza().get((codigo, sexo, edad), ()):
        if desde <= anio <= hasta:
            return esperanza, supervivencia, banda
    return None


def esperanza_restante(codigo, anio, edad=0, sexo="T"):
    """Años que le quedaban por vivir, en promedio, a quien tenía esa edad en ese país y ese año.

    `codigo` es un ISO alfa-2; `sexo`, `M`, `F` o `T` (ambos). Es una cifra de **período** (la mortalidad de
    la década que contiene `anio`), no de una generación, y un promedio de toda la población: no dice cuánto
    iba a vivir una persona. Devuelve None si no hay dato: país sin serie, año sin cobertura (de 1800 a 2009, y
    desde 1540 solo la esperanza al nacer de Reino Unido, Suecia, Finlandia y Dinamarca), o una edad que todavía
    no está (hoy 0, 5, 15, 30, 45 y 60). Mujeres y hombres, solo desde 1950. No interpola entre edades. De los 15 años
    en adelante la cifra es **modelada** (ver modelo.py), no medida.
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


@functools.cache
def _tasas_de_inglaterra():
    return tuple((int(f["anio"]), float(f["natalidad"]), float(f["mortalidad"])) for f in filas_csv("inglaterra_tasas"))


def tasas_inglaterra(anio):
    """Tasas brutas de natalidad y de mortalidad (por mil habitantes) de Inglaterra (y Gales), de 1541 a 2015.

    Es la serie de Wrigley y Schofield (reconstrucción desde los registros parroquiales) y, desde 1838, el registro civil.
    Hasta 1861 hay **un punto cada cinco años** (1541, 1546, 1551…): cada uno resume un quinquenio, así que suaviza
    los picos de un solo año. Devuelve {"natalidad": float, "mortalidad": float}, o None si ese año no tiene punto.
    Es estadística de población: dice cuánto se moría en el país, no de qué ni quién.
    """
    for a, natalidad, mortalidad in _tasas_de_inglaterra():
        if a == anio:
            return {"natalidad": natalidad, "mortalidad": mortalidad}
    return None


def exceso_de_mortalidad_inglaterra(anio, vecinos=2):
    """Cuántas veces la tasa de mortalidad de ese punto supera a la de los `vecinos` puntos de cada lado (mediana).

    Los vecinos se cuentan en **puntos de la serie**, no en años (hasta 1861 cada punto es un quinquenio). Un 1,0 es un
    punto como los de al lado; 1,3 es una mortalidad muy por encima. Devuelve None si el año no tiene punto o hay menos
    de 4 vecinos con dato (los extremos de la serie).
    """
    serie = _tasas_de_inglaterra()
    for i, (a, _natalidad, mortalidad) in enumerate(serie):
        if a == anio:
            break
    else:
        return None
    valores = sorted(serie[j][2] for j in range(i - vecinos, i + vecinos + 1) if j != i and 0 <= j < len(serie))
    if len(valores) < 4:
        return None
    mitad = len(valores) // 2
    mediana = valores[mitad] if len(valores) % 2 else (valores[mitad - 1] + valores[mitad]) / 2
    return mortalidad / mediana


def anios_de_mortalidad_anormal_inglaterra(umbral=1.15):
    """Los años de la serie de Inglaterra cuyo exceso de mortalidad llega a `umbral` (por defecto, un 15 % sobre los vecinos).

    Devuelve una lista de {"anio": int, "exceso": float}, en orden. Como hasta 1861 la serie es quinquenal, un año
    de peste o de gripe aparece diluido: sirve para ver épocas, no para fechar un brote.
    """
    resultado = []
    for a, _natalidad, _mortalidad in _tasas_de_inglaterra():
        exceso = exceso_de_mortalidad_inglaterra(a)
        if exceso is not None and exceso >= umbral:
            resultado.append({"anio": a, "exceso": exceso})
    return resultado


def banda_esperanza(codigo, anio, edad=0, sexo="T"):
    """(bajo, alto) de la esperanza restante de `esperanza_restante`, o None si no hay dato o no tiene banda.

    Desde 1950 la banda es la discrepancia entre fuentes (el percentil 80); **antes de 1950 es un supuesto del
    proyecto**, no una medición: está para que ninguna cifra parezca más firme de lo que es. Mismas reglas y límites
    que `esperanza_restante`.
    """
    encontrado = _periodo_que_cubre(codigo, anio, edad, sexo)
    return None if encontrado is None else encontrado[2]


# --- Edades de muerte y supervivencia: estadística de población, por país y década -------------------------------
#
# Todas parten de la tabla de vida que el modelo log-cuadrático arma para un país, una década y un sexo, desde la
# esperanza al nacer (del sexo pedido) y la mortalidad de 0 a 5 años (de ambos sexos). Son cifras de **período** y de
# **toda la población**: dicen cómo se repartían las muertes y cuántos niños llegaban a cada edad en esa década, no
# lo que le pasó o le pasará a una persona ni a una familia. Por eso solo reciben país, año, sexo y edades: ninguna
# recibe una ficha ni un apellido (una prueba lo comprueba). Son modeladas (calidad C) y, antes de 1950, apenas
# estimaciones; no hay datos si falta la mortalidad de 0 a 5 años (antes de 1800, o países sin esa serie).

PARAMETROS_PERMITIDOS = frozenset({"codigo", "anio", "sexo", "edad", "desde_edad", "p", "nacidos"})


@functools.cache
def _perfil_de_periodo(codigo, desde, sexo):
    e0 = _periodos_de_esperanza().get((codigo, sexo, 0), ())
    e0 = next((fila[2] for fila in e0 if fila[0] == desde), None)
    l5 = next((fila[3] for fila in _periodos_de_esperanza().get((codigo, "T", 5), ()) if fila[0] == desde), None)
    if e0 is None or l5 is None:
        return None
    return modelo.perfil((1 - l5) * 1000, e0, sexo)


def _perfil(codigo, anio, sexo):
    if sexo not in SEXOS:
        raise ValueError(f"sexo «{sexo}»: debe ser uno de {SEXOS}")
    for desde, hasta, *_ in _periodos_de_esperanza().get((codigo, sexo, 0), ()):
        if desde <= anio <= hasta:
            return _perfil_de_periodo(codigo, desde, sexo)
    return None


def probabilidad_de_llegar_a(codigo, anio, edad, sexo="T"):
    """Probabilidad (0 a 1) de que un recién nacido de esa década y ese país llegue con vida a `edad` (0 a 110).

    Devuelve None si no hay datos (hacen falta la esperanza al nacer y la mortalidad de 0 a 5 años de la década)."""
    perfil = _perfil(codigo, anio, sexo)
    return None if perfil is None else modelo.supervivencia_en(perfil[0], edad)


def supervivientes_esperados(codigo, anio, nacidos, edad, sexo="T"):
    """Cuántos de `nacidos` niños llegaban en promedio a `edad`: `nacidos` por `probabilidad_de_llegar_a`. Es un
    valor esperado de la población (de 6 nacimientos, «4,1»), no lo que pasó en una familia."""
    if nacidos < 0:
        raise ValueError(f"nacidos no puede ser negativo: {nacidos}")
    probabilidad = probabilidad_de_llegar_a(codigo, anio, edad, sexo)
    return None if probabilidad is None else nacidos * probabilidad


def fraccion_de_muertes_antes_de(codigo, anio, edad, desde_edad=0, sexo="T"):
    """De quienes llegaban a `desde_edad`, la fracción (0 a 1) que moría antes de cumplir `edad` en esa década.
    Con `desde_edad=0`, la fracción de todos los nacidos. Pide `edad >= desde_edad`."""
    if edad < desde_edad:
        raise ValueError(f"edad ({edad}) menor que desde_edad ({desde_edad})")
    perfil = _perfil(codigo, anio, sexo)
    if perfil is None:
        return None
    base = modelo.supervivencia_en(perfil[0], desde_edad)
    return None if base <= 0 else 1 - modelo.supervivencia_en(perfil[0], edad) / base


def edad_de_muerte_percentil(codigo, anio, p, desde_edad=0, sexo="T"):
    """La edad por debajo de la cual había muerto la fracción `p` (0 < p < 1) de quienes llegaban a `desde_edad`: con
    p = 0,5, la mediana de la edad de muerte. Devuelve como mucho 110. None si no hay datos."""
    if not 0 < p < 1:
        raise ValueError(f"p fuera de (0, 1): {p}")
    perfil = _perfil(codigo, anio, sexo)
    if perfil is None:
        return None
    base = modelo.supervivencia_en(perfil[0], desde_edad)
    return None if base <= 0 else modelo.edad_de_supervivencia(perfil[0], base * (1 - p))


def edades_de_muerte_comunes(codigo, anio, desde_edad=0, sexo="T"):
    """{"p10", "p25", "p50", "p75", "p90"}: las edades por debajo de las cuales moría el 10, 25, 50, 75 y 90 % de quienes
    llegaban a `desde_edad`. De p10 a p90 está el 80 % central de las muertes: lo «común» en esa década. None si no hay
    datos."""
    edades = {f"p{n}": edad_de_muerte_percentil(codigo, anio, n / 100, desde_edad, sexo) for n in (10, 25, 50, 75, 90)}
    return None if edades["p50"] is None else edades

