"""Consulta de signos desde datos/signos.json: el signo occidental de una fecha y el animal chino de un año.

    >>> import datetime, zodiaco
    >>> zodiaco.signo_occidental(datetime.date(1990, 4, 3))["id"]
    'aries'
    >>> zodiaco.signo_occidental(datetime.date(1990, 4, 3), sistema="sideral")["id"]
    'piscis'
    >>> a = zodiaco.animal_chino(2026)
    >>> a["signo"]["id"], a["elemento_del_anio"], a["tronco"]["hanzi"] + a["signo"]["rama"]["hanzi"]
    ('caballo', 'fuego', '丙午')
    >>> zodiaco.animal_chino_de_fecha(datetime.date(2026, 2, 16))["signo"]["id"]  # antes del Año Nuevo
    'serpiente'
    >>> [s["id"] for s in zodiaco.cuspide(datetime.date(1990, 4, 20))]
    ['aries', 'tauro']
    >>> zodiaco.nombre_del_anio(2026, "es"), zodiaco.nombre_del_anio(2026, "en"), zodiaco.nombre_del_anio(2026, "zh")
    ('Caballo de Fuego', 'Fire Horse', '火马')
    >>> zodiaco.compatibilidad("rata", "dragon")["afinidad"], zodiaco.compatibilidad("rata", "caballo")["afinidad"]
    ('alta', 'baja')
    >>> d = zodiaco.decanato(datetime.date(1990, 4, 3))
    >>> d["signo"]["id"], d["numero"], d["regente"], d["signo_regente"]["id"]
    ('aries', 2, 'sol', 'leo')
    >>> a = zodiaco.angel_de_fecha(datetime.date(1990, 4, 3))
    >>> a["angel"]["nombre"], a["coro"], a["tramo"]
    ('Sitael', 'serafines', [[3, 31], [4, 4]])
    >>> zodiaco.demonio_de_fecha(datetime.date(1990, 4, 3))["demonio"]["nombre"]
    'Vassago'
    >>> zodiaco.cualidades_de("angel", 3, "es"), zodiaco.cualidades_de("demonio", 1, "en")
    ('Construcción, protección, nobleza', 'Invisibility, wisdom')
    >>> c = zodiaco.arbol_celta(datetime.date(1990, 4, 3))
    >>> c["mes"]["ogham"], c["arbol"]["nombres"]["es"], c["arbol"]["cientifico"]
    ('Fearn', 'aliso', 'Alnus glutinosa')
    >>> h = zodiaco.arbol_de_fecha(datetime.date(1990, 4, 3))
    >>> h["arbol"]["nombres"]["es"], h["tramo"]
    ('serbal', [[4, 1], [4, 10]])
    >>> n = zodiaco.nakshatra_de_instante(datetime.datetime(1992, 4, 12, tzinfo=datetime.timezone.utc))
    >>> n["nakshatra"]["nombre"], n["nakshatra"]["arbol"]
    ('Ashlesha', 'Nagkesar')
    >>> zodiaco.rasgos_de("horoscopo", "olivo", "es")
    'Equilibrio, sabiduría, paz'
    >>> zodiaco.camino_de_vida(datetime.date(1990, 4, 3)), zodiaco.camino_de_vida(datetime.date(1980, 1, 19))
    (8, 11)
    >>> zodiaco.anio_personal(datetime.date(1990, 4, 3), 2026), zodiaco.rasgos_de_numero(11, "es")
    (8, 'Intuición, inspiración, idealismo')

Hay un port a PHP en php/ que da las mismas respuestas (datos/vectores.json las fija).

Las funciones devuelven los diccionarios de los datos tal cual (con nombres y descripciones en los
30 idiomas) y lanzan excepción ante una entrada no válida. Solo usa la biblioteca estándar.
"""
import datetime
import json
import math
import re
from functools import lru_cache
from pathlib import Path

DATOS = Path(__file__).resolve().parent / "datos" / "signos.json"
ANIO_NUEVO = Path(__file__).resolve().parent / "datos" / "anio-nuevo-lunar.json"
ESPIRITUS = Path(__file__).resolve().parent / "datos" / "espiritus.json"
ARBOLES = Path(__file__).resolve().parent / "datos" / "arboles.json"
NUMEROLOGIA = Path(__file__).resolve().parent / "datos" / "numerologia.json"

SISTEMAS = ("tropical", "sideral")

HORA_DE_CHINA = datetime.timezone(datetime.timedelta(hours=8))

# Orden caldeo de los siete astros clásicos: regentes de los 36 decanatos, empezando por Marte en el
# primer decanato de Aries (Marte, Sol, Venus · Mercurio, Luna, Saturno · Júpiter, Marte, Sol…).
ORDEN_CALDEO = ("marte", "sol", "venus", "mercurio", "luna", "saturno", "jupiter")

AFINIDADES = ("alta", "media", "neutra", "baja")

# Los 72 ángeles van de ocho en ocho en nueve coros, de los Serafines a los Ángeles.
CHOROS = ("serafines", "querubines", "tronos", "dominaciones", "virtudes", "potestades", "principados",
          "arcangeles", "angeles")
TIPOS_DE_ESPIRITU = ("angel", "demonio")
RANGOS = ("rey", "duque", "principe", "marques", "conde", "presidente", "caballero")
SISTEMAS_DE_ARBOLES = ("celta", "horoscopo", "nakshatra")
NUMEROS_MAESTROS = (11, 22, 33)

# Ayanamsa de Lahiri (la distancia entre el zodiaco tropical y el sideral): 23°51′11″ en J2000.0 y unos 50,29″ al año.
_AYANAMSA_J2000 = 23.85306
_AYANAMSA_POR_ANIO = 50.29 / 3600

_COMPLEMENTARIOS = {frozenset({"fuego", "aire"}), frozenset({"tierra", "agua"})}

# 1984 fue 甲子 (madera, rata): el primer año de un ciclo de 60. El ciclo empieza en el año 4 de la era común.
_ANIO_BASE = 4


def _cargar_json(ruta):
    with ruta.open(encoding="utf-8") as f:
        return json.load(f)


def _exigir_fecha(fecha):
    if not isinstance(fecha, datetime.date):
        raise TypeError(f"fecha debe ser datetime.date, no {type(fecha).__name__}")


def _copia(x):
    """Una copia de un valor de los datos (diccionarios, listas y tuplas de números y textos, como un JSON).

    Las consultas devuelven copias de los datos, no los datos: así quien modifique lo que recibe no corrompe las
    respuestas siguientes. Las cargar_*() sí devuelven los datos compartidos: no los modifiques. Es unas 3 veces
    más rápida que copy.deepcopy(), que lleva una tabla de memoria que estos datos (sin ciclos) no necesitan.
    """
    if isinstance(x, dict):
        return {k: _copia(v) for k, v in x.items()}
    if isinstance(x, (list, tuple)):
        return type(x)(_copia(v) for v in x)
    return x


@lru_cache(maxsize=1)
def cargar_datos():
    """Lee datos/signos.json una sola vez. Lo comparten todas las consultas: no lo modifiques."""
    return _cargar_json(DATOS)


@lru_cache(maxsize=1)
def _fechas_anio_nuevo():
    return {int(a): datetime.date.fromisoformat(d) for a, d in _cargar_json(ANIO_NUEVO)["fechas"].items()}


def fechas_anio_nuevo():
    """{año: fecha del Año Nuevo lunar} desde datos/anio-nuevo-lunar.json (1900–2099)."""
    return dict(_fechas_anio_nuevo())


def version(datos=None):
    """La versión de los datos (X.Y.Z, la de CHANGELOG.md)."""
    return (datos or cargar_datos())["version"]


def revision(idioma, datos=None):
    """El estado de revisión de las traducciones de un idioma: {"estado": "sin_revisar"} o
    {"estado": "revisado", "por": …, "fecha": "AAAA-MM-DD"}."""
    datos = datos or cargar_datos()
    if idioma not in datos["revision"]:
        raise ValueError(f"idioma desconocido: {idioma!r}")
    return _copia(datos["revision"][idioma])


@lru_cache(maxsize=1)
def cargar_espiritus():
    """Lee datos/espiritus.json una sola vez: los 72 ángeles y los 72 demonios, con sus cualidades. No lo modifiques."""
    return _cargar_json(ESPIRITUS)


@lru_cache(maxsize=1)
def cargar_arboles():
    """Lee datos/arboles.json una sola vez: el calendario celta, el horóscopo de los árboles y los de los nakshatras. No lo modifiques."""
    return _cargar_json(ARBOLES)


@lru_cache(maxsize=1)
def cargar_numerologia():
    """Lee datos/numerologia.json una sola vez: los 12 números del camino de vida (1–9, 11, 22 y 33) y sus rasgos. No lo modifiques."""
    return _cargar_json(NUMEROLOGIA)


def _en_rango(mes_dia, desde, hasta):
    """Si (mes, día) cae entre desde y hasta, inclusive; el rango puede cruzar el fin de año."""
    desde, hasta = tuple(desde), tuple(hasta)
    if desde <= hasta:
        return desde <= mes_dia <= hasta
    return mes_dia >= desde or mes_dia <= hasta


def signo_occidental(fecha, sistema="tropical", datos=None):
    """El signo occidental de una fecha.

    sistema es "tropical" (el zodiaco occidental habitual) o "sideral" (el de la astrología védica,
    unos 24 días más tarde). Las fechas límite son aproximadas: varían un día según el año y la hora.
    """
    return _copia(_signo_occidental(fecha, sistema, datos))


def _signo_occidental(fecha, sistema, datos):
    """Como signo_occidental(), pero devuelve el diccionario de los datos y no una copia (para uso interno)."""
    _exigir_fecha(fecha)
    if sistema not in SISTEMAS:
        raise ValueError(f"sistema debe ser uno de {SISTEMAS}, no {sistema!r}")
    datos = datos or cargar_datos()
    clave = (fecha.month, fecha.day)
    for s in datos["signos"]:
        if s["tipo"] == "occidental" and _en_rango(clave, s[sistema]["desde"], s[sistema]["hasta"]):
            return s
    raise LookupError(f"ningún signo {sistema} cubre el {fecha.day:02d}/{fecha.month:02d}")


def cuspide(fecha, sistema="tropical", margen=1, datos=None):
    """Los dos signos entre los que cae una fecha si está a `margen` días o menos de un cambio de signo.

    Devuelve (signo_anterior, signo_siguiente) o None. Sirve para no afirmar un signo que puede estar
    mal: las fechas límite varían un día según el año y la hora de nacimiento.
    """
    if isinstance(margen, bool) or not isinstance(margen, int) or margen < 0:
        raise ValueError(f"margen debe ser un entero mayor o igual que 0, no {margen!r}")
    _exigir_fecha(fecha)
    dia = datetime.date(fecha.year, fecha.month, fecha.day)
    propio = _signo_occidental(dia, sistema, datos)
    for d in range(1, margen + 1):
        antes = _signo_occidental(dia - datetime.timedelta(days=d), sistema, datos)
        if antes["id"] != propio["id"]:
            return _copia((antes, propio))
        despues = _signo_occidental(dia + datetime.timedelta(days=d), sistema, datos)
        if despues["id"] != propio["id"]:
            return _copia((propio, despues))
    return None


def es_cuspide(fecha, sistema="tropical", margen=1, datos=None):
    """Si la fecha está a `margen` días o menos de un cambio de signo."""
    return cuspide(fecha, sistema, margen, datos) is not None


def animal_chino_de_fecha(fecha, datos=None):
    """Como animal_chino(), pero desde una fecha del calendario gregoriano (1900–2099).

    Usa las fechas del Año Nuevo lunar de datos/anio-nuevo-lunar.json: una fecha anterior al Año
    Nuevo de su año pertenece al año lunar anterior. Esas fechas son de la hora de China (UTC+8): un
    datetime con huso horario se pasa a esa hora antes de comparar (las 20:00 UTC del 16/02/2026
    ya son el 17/02 en China, Caballo). Una fecha o un datetime sin huso se toma tal cual.
    """
    _exigir_fecha(fecha)
    if isinstance(fecha, datetime.datetime) and fecha.tzinfo is not None:
        fecha = fecha.astimezone(HORA_DE_CHINA)
    tabla = _fechas_anio_nuevo()
    if fecha.year not in tabla:
        raise ValueError(f"solo hay fechas del Año Nuevo lunar de {min(tabla)} a {max(tabla)}, no de {fecha.year}")
    dia = datetime.date(fecha.year, fecha.month, fecha.day)
    anio_lunar = fecha.year if dia >= tabla[fecha.year] else fecha.year - 1
    return _copia({**_animal_chino(anio_lunar, datos), "anio_lunar": anio_lunar})


def animal_chino(anio_lunar, datos=None):
    """El animal, el tronco celeste y el elemento de un año del calendario chino.

    anio_lunar es el año lunar, que empieza con el Año Nuevo chino (entre el 21 de enero y el 20 de
    febrero). Para partir de una fecha de nacimiento, usa animal_chino_de_fecha(): quien nació en
    enero o principios de febrero puede pertenecer al año anterior.

    Devuelve un diccionario con:
      signo              el animal (los datos del signo chino)
      tronco             el tronco celeste del año ({"hanzi", "pinyin", "elemento", "polaridad"})
      elemento_del_anio  el elemento del año en el ciclo de 60, que no es el elemento fijo del animal
      polaridad          yang o yin del año
    """
    return _copia(_animal_chino(anio_lunar, datos))


def _animal_chino(anio_lunar, datos):
    """Como animal_chino(), pero devuelve los diccionarios de los datos y no copias (para uso interno)."""
    if isinstance(anio_lunar, bool) or not isinstance(anio_lunar, int):
        raise TypeError(f"anio_lunar debe ser int, no {type(anio_lunar).__name__}")
    datos = datos or cargar_datos()
    chinos = [s for s in datos["signos"] if s["tipo"] == "chino"]
    signo = chinos[(anio_lunar - _ANIO_BASE) % 12]
    tronco = datos["troncos"][(anio_lunar - _ANIO_BASE) % 10]
    return {
        "signo": signo,
        "tronco": tronco,
        "elemento_del_anio": tronco["elemento"],
        "polaridad": tronco["polaridad"],
    }


def nombre_del_anio(anio_lunar, idioma, datos=None):
    """El nombre del año en el ciclo de 60 en un idioma: «Caballo de Fuego», «Fire Horse», «火马».

    La forma sale de nombres_del_ciclo en los datos. Donde no hay una forma fija que no dependa de
    género o caso, es la genérica «Animal (Elemento)» y nombres_del_ciclo[idioma]["idiomatica"] es False.
    """
    datos = datos or cargar_datos()
    if idioma not in datos["nombres_del_ciclo"]:
        raise ValueError(f"idioma desconocido: {idioma!r}")
    regla = datos["nombres_del_ciclo"][idioma]
    anio = _animal_chino(anio_lunar, datos)
    elemento = regla.get("elementos", {}).get(anio["elemento_del_anio"]) or datos["terminos"][anio["elemento_del_anio"]][idioma]
    animal = anio["signo"]["nombres"][idioma]
    nombre = regla["plantilla"].format(elemento=elemento, animal=animal, animal_minusculas=animal.lower())
    if regla.get("elision"):
        # «Rat de Eau» → «Rat d'Eau», «Topo di Acqua» → «Topo d'Acqua».
        nombre = re.sub(r"\b(?:de|di) (?=[AEIOUÉÈÊaeiouéèê])", "d'", nombre)
    return nombre


def compatibilidad(id_a, id_b, datos=None):
    """La afinidad tradicional entre dos signos del mismo zodiaco.

    Devuelve {"relaciones": [...], "afinidad": "alta" | "media" | "neutra" | "baja"}.
      Occidental: mismo_signo, mismo_elemento (trígono), elementos_complementarios (fuego y aire,
        tierra y agua: sextil), opuestos (a seis signos) y cuadratura (a tres signos, en tensión).
      Chino: mismo_signo, trino (三合, los tres animales de un grupo), amigos_secretos (六合) y
        choque (六冲, a seis animales).
    Es lo que dice la tradición astrológica; no tiene respaldo científico.
    """
    datos = datos or cargar_datos()
    por_id = {s["id"]: s for s in datos["signos"]}
    if id_a not in por_id or id_b not in por_id:
        raise ValueError(f"signo desconocido: {id_a if id_a not in por_id else id_b!r}")
    a, b = por_id[id_a], por_id[id_b]
    if a["tipo"] != b["tipo"]:
        raise ValueError("solo se comparan signos del mismo zodiaco (occidental con occidental, chino con chino)")
    del_tipo = [s["id"] for s in datos["signos"] if s["tipo"] == a["tipo"]]
    i, j = del_tipo.index(id_a), del_tipo.index(id_b)
    distancia = min((i - j) % 12, (j - i) % 12)
    relaciones = []
    if distancia == 0:
        relaciones.append("mismo_signo")
    if a["tipo"] == "occidental":
        if a["elemento"] == b["elemento"]:
            relaciones.append("mismo_elemento")
        if frozenset({a["elemento"], b["elemento"]}) in _COMPLEMENTARIOS:
            relaciones.append("elementos_complementarios")
        if distancia == 6:
            relaciones.append("opuestos")
        if distancia == 3:
            relaciones.append("cuadratura")
        if "opuestos" in relaciones:
            afinidad = "media"
        elif "cuadratura" in relaciones:
            afinidad = "baja"
        elif {"mismo_elemento", "elementos_complementarios"} & set(relaciones):
            afinidad = "alta"
        else:
            afinidad = "neutra"
    else:
        if i % 4 == j % 4:
            relaciones.append("trino")
        if (i + j) % 12 == 1:
            relaciones.append("amigos_secretos")
        if distancia == 6:
            relaciones.append("choque")
        if "choque" in relaciones:
            afinidad = "baja"
        elif {"trino", "amigos_secretos"} & set(relaciones):
            afinidad = "alta"
        else:
            afinidad = "neutra"
    return {"relaciones": relaciones, "afinidad": afinidad}


# Para repartir un signo en tramos (decanatos, los 72) se usa un año de 365 días: el 29/02 cuenta como el 28/02. Así un
# tramo es el mismo todos los años y su día no se corre en los bisiestos (antes dependía del año y la ficha, calculada
# para 2025, no coincidía con la función en un año bisiesto).
_DIAS_POR_MES = (31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31)


def _dia_del_anio(mes_dia):
    """El día 1–365 de un [mes, día] en un año sin 29 de febrero (el 29/02 cuenta como el 28/02)."""
    mes, dia = mes_dia
    return sum(_DIAS_POR_MES[:mes - 1]) + min(dia, _DIAS_POR_MES[mes - 1])


def _mes_dia(dia_del_anio):
    """Lo inverso de _dia_del_anio(): el [mes, día] de un día 1–365 (pasarse de 365 da la vuelta al año)."""
    n = (dia_del_anio - 1) % 365 + 1
    for mes, dias in enumerate(_DIAS_POR_MES, 1):
        if n <= dias:
            return [mes, n]
        n -= dias


def _tramos_del_signo(signo, sistema, largos):
    """Parte un signo en tramos de los largos que dé `largos(días del signo)`: [[mes, día] desde, [mes, día] hasta]."""
    inicio = _dia_del_anio(signo[sistema]["desde"])
    dias = (_dia_del_anio(signo[sistema]["hasta"]) - inicio) % 365 + 1
    tramos, acumulado = [], 0
    for n in largos(dias):
        tramos.append([_mes_dia(inicio + acumulado), _mes_dia(inicio + acumulado + n - 1)])
        acumulado += n
    return tramos


def _tramo_que_contiene(fecha, tramos):
    """La posición (desde 0) del tramo que contiene el día de `fecha`; el 29/02 cuenta como el 28/02."""
    clave = tuple(_mes_dia(_dia_del_anio((fecha.month, fecha.day))))
    return next(k for k, (desde, hasta) in enumerate(tramos) if _en_rango(clave, desde, hasta))


def _largos_de_decanatos(dias):
    """Los días de los tres decanatos de un signo de `dias` días: 10, 10 y los que queden (de 9 a 12)."""
    return [10, 10, dias - 20]


def _inicio_del_signo(fecha, signo, sistema):
    """@deprecated desde 1.3.1: los tramos ya no se calculan con fechas reales (dependían del año bisiesto).

    Se conserva como referencia. La fecha en que empezó el tramo del signo que contiene a `fecha`.
    """
    mes, dia = signo[sistema]["desde"]
    inicio = datetime.date(fecha.year, mes, dia)
    return inicio if inicio <= fecha else datetime.date(fecha.year - 1, mes, dia)


def decanato(fecha, sistema="tropical", datos=None):
    """El decanato de una fecha: cada signo se parte en tres tramos de unos 10 días.

    Devuelve un diccionario con:
      signo          el signo occidental de la fecha
      numero         1, 2 o 3
      regente        el astro del decanato en el orden caldeo (clave de "terminos": "marte", "sol"…)
      signo_regente  el signo del mismo elemento que lo rige en el sistema por triplicidad
                     (Aries: Aries, Leo, Sagitario)
    Los tramos son aproximados, como las fechas de los signos, y son los mismos todos los años: el 29/02
    cuenta con el 28/02.
    """
    datos = datos or cargar_datos()
    signo = _signo_occidental(fecha, sistema, datos)
    numero = _tramo_que_contiene(fecha, _tramos_del_signo(signo, sistema, _largos_de_decanatos)) + 1
    occidentales = [s for s in datos["signos"] if s["tipo"] == "occidental"]
    indice = [s["id"] for s in occidentales].index(signo["id"])
    return _copia({
        "signo": signo,
        "numero": numero,
        "regente": regentes_de_decanatos(signo["id"], datos)[numero - 1],
        "signo_regente": occidentales[(indice + 4 * (numero - 1)) % 12],
    })


def regentes_de_decanatos(id_signo, datos=None):
    """Los astros regentes de los tres decanatos de un signo, en el orden caldeo."""
    datos = datos or cargar_datos()
    ids = [s["id"] for s in datos["signos"] if s["tipo"] == "occidental"]
    if id_signo not in ids:
        raise ValueError(f"signo occidental desconocido: {id_signo!r}")
    indice = ids.index(id_signo)
    return [ORDEN_CALDEO[(indice * 3 + n) % 7] for n in range(3)]


def tramos_de_decanatos(signo, sistema="tropical", anio=None):
    """Los tres tramos de un signo como ([mes, día] desde, [mes, día] hasta).

    Son los mismos todos los años (el 29/02 cuenta con el 28/02). El parámetro `anio` está @deprecated desde 1.3.1:
    antes elegía el año con que se calculaban las fechas, y ahora no cambia nada; se conserva para no romper quien
    lo pasaba.
    """
    return [tuple(t) for t in _tramos_del_signo(signo, sistema, _largos_de_decanatos)]


def anios_del_animal(id_animal, desde, hasta, datos=None):
    """Los años lunares entre desde y hasta (inclusive) que corresponden a un animal."""
    datos = datos or cargar_datos()
    ids = [s["id"] for s in datos["signos"] if s["tipo"] == "chino"]
    if id_animal not in ids:
        raise ValueError(f"animal desconocido: {id_animal!r}")
    indice = ids.index(id_animal)
    return [a for a in range(desde, hasta + 1) if (a - _ANIO_BASE) % 12 == indice]


# --- Los 72 ángeles del Shem ha-Mephorash y los 72 demonios de la Goetia ---
# Cada uno se atribuye a un tramo de 5° del zodiaco tropical: seis por signo, desde Aries. Aquí cada signo se
# parte en seis tramos de fechas casi iguales (los primeros se llevan el día que sobre en los signos de 31 o 32
# días), los mismos todos los años. Un ángel y el demonio del mismo número comparten tramo.

def _signo_occidental_por_id(id_signo, datos):
    for s in datos["signos"]:
        if s["tipo"] == "occidental" and s["id"] == id_signo:
            return s
    raise ValueError(f"signo occidental desconocido: {id_signo!r}")


def _largos_de_tramos(dias):
    """Los días de cada uno de los seis tramos de un signo de `dias` días: los primeros se llevan el sobrante."""
    base, sobra = divmod(dias, 6)
    return [base + 1] * sobra + [base] * (6 - sobra)


def _fin_del_signo(inicio, signo):
    """@deprecated desde 1.3.1: los tramos ya no se calculan con fechas reales; se conserva como referencia."""
    mes, dia = signo["tropical"]["hasta"]
    fin = datetime.date(inicio.year, mes, dia)
    return fin if fin >= inicio else datetime.date(inicio.year + 1, mes, dia)


def _tramos_desde(inicio, signo):
    """@deprecated desde 1.3.1: los tramos ya no se calculan con fechas reales; se conserva como referencia.

    [(desde, hasta)] como fechas: los seis tramos del signo que empieza en `inicio`.
    """
    tramos, desde = [], inicio
    for dias in _largos_de_tramos((_fin_del_signo(inicio, signo) - inicio).days + 1):
        hasta = desde + datetime.timedelta(days=dias - 1)
        tramos.append((desde, hasta))
        desde = hasta + datetime.timedelta(days=1)
    return tramos


def tramos_de_espiritus(id_signo, anio=None, datos=None):
    """Los seis tramos de un signo occidental como [[mes, día] desde, [mes, día] hasta].

    Los ángeles y demonios del signo son los seis números que empiezan en (posición del signo × 6 + 1):
    Aries tiene del 1 al 6, Tauro del 7 al 12… Son los mismos todos los años: el 29/02 cuenta con el 28/02. El
    parámetro `anio` está @deprecated desde 1.3.1: antes elegía el año de las fechas y ahora no cambia nada (se
    sigue comprobando que sea un entero, para no romper quien lo pasaba).
    """
    if anio is not None and (isinstance(anio, bool) or not isinstance(anio, int)):
        raise TypeError(f"anio debe ser int, no {type(anio).__name__}")
    datos = datos or cargar_datos()
    return _tramos_del_signo(_signo_occidental_por_id(id_signo, datos), "tropical", _largos_de_tramos)


def _espiritu_de_fecha(fecha, datos):
    """(número 1–72, signo, [desde, hasta] del tramo) de una fecha, en el zodiaco tropical."""
    signo = _signo_occidental(fecha, "tropical", datos)
    tramos = _tramos_del_signo(signo, "tropical", _largos_de_tramos)
    k = _tramo_que_contiene(fecha, tramos)
    occidentales = [s["id"] for s in datos["signos"] if s["tipo"] == "occidental"]
    return occidentales.index(signo["id"]) * 6 + k + 1, signo, tramos[k]


def angel_de_fecha(fecha, datos=None):
    """El ángel del Shem ha-Mephorash que rige una fecha (zodiaco tropical).

    Devuelve {"angel": …, "signo": …, "coro": …, "tramo": [[mes, día], [mes, día]]}: el ángel y el signo son
    los diccionarios de los datos, el coro es una de CHOROS y el tramo, las fechas de ese ángel (el 29/02 cuenta
    con el 28/02). Las fechas son aproximadas, como las de los signos.
    """
    datos = datos or cargar_datos()
    numero, signo, tramo = _espiritu_de_fecha(fecha, datos)
    return _copia({"angel": cargar_espiritus()["angeles"][numero - 1], "signo": signo,
                   "coro": CHOROS[(numero - 1) // 8], "tramo": tramo})


def demonio_de_fecha(fecha, datos=None):
    """El demonio de la Goetia que se atribuye a una fecha: el del mismo número que su ángel.

    Devuelve {"demonio": …, "signo": …, "tramo": [[mes, día], [mes, día]]}. Esa correspondencia es de la
    esoterología moderna, no de los grimorios.
    """
    datos = datos or cargar_datos()
    numero, signo, tramo = _espiritu_de_fecha(fecha, datos)
    return _copia({"demonio": cargar_espiritus()["demonios"][numero - 1], "signo": signo, "tramo": tramo})


def cualidades_de(tipo, numero, idioma):
    """Las cualidades de un ángel o demonio en un idioma, en una frase: «Construcción, protección, nobleza».

    tipo es "angel" o "demonio"; numero, de 1 a 72. Las cualidades salen del glosario de espiritus.json, así
    que cada una se dice igual en todas las fichas.
    """
    if tipo not in TIPOS_DE_ESPIRITU:
        raise ValueError(f"tipo debe ser uno de {TIPOS_DE_ESPIRITU}, no {tipo!r}")
    if isinstance(numero, bool) or not isinstance(numero, int):
        raise TypeError(f"numero debe ser int, no {type(numero).__name__}")
    if not 1 <= numero <= 72:
        raise ValueError(f"numero debe estar entre 1 y 72, no {numero}")
    esp = cargar_espiritus()
    if idioma not in esp["terminos"]["separador"]:
        raise ValueError(f"idioma desconocido: {idioma!r}")
    entrada = esp["angeles" if tipo == "angel" else "demonios"][numero - 1]
    return _frase(entrada["cualidades"], idioma, [esp["cualidades"]], esp["terminos"]["separador"])


def _frase(claves, idioma, glosarios, separador):
    """Las claves de un glosario dichas en un idioma y unidas con su separador, con la primera letra en mayúscula."""
    textos = []
    for c in claves:
        glosario = next((g for g in glosarios if c in g), None)
        if glosario is None:
            raise KeyError(f"la clave {c!r} no está en ningún glosario")
        textos.append(glosario[c][idioma])
    return mayuscula_inicial(separador[idioma].join(textos), idioma)


def mayuscula_inicial(texto, idioma):
    """El texto con la primera letra en mayúscula; en turco, la i pasa a İ."""
    primera = "İ" if idioma == "tr" and texto[0] == "i" else texto[0].upper()
    return primera + texto[1:]


# --- Los árboles: el calendario celta, el horóscopo de los árboles y los árboles de los 27 nakshatras ---
# Tres sistemas distintos que no vienen del mismo sitio ni son antiguos los dos primeros (ver el README).

def _arbol(id_arbol):
    return {"id": id_arbol, **cargar_arboles()["arboles"][id_arbol]}


def arbol_celta(fecha):
    """El mes del calendario celta de los árboles (el de Robert Graves, 1948) al que pertenece una fecha.

    Devuelve {"mes": …, "arbol": …}: el mes de los datos (con su letra ogham, sus fechas y sus rasgos) y el árbol
    ({"id", "cientifico", "nombres"}). Son 13 meses de 28 días; el 23 de diciembre no pertenece a ninguno
    (es el «día del año») y devuelve None.
    """
    _exigir_fecha(fecha)
    clave = (fecha.month, fecha.day)
    celta = cargar_arboles()["calendario_celta"]
    if list(clave) == celta["dia_sin_arbol"]:
        return None
    for mes in celta["meses"]:
        if _en_rango(clave, mes["desde"], mes["hasta"]):
            return _copia({"mes": mes, "arbol": _arbol(mes["arbol"])})
    raise LookupError(f"ningún mes celta cubre el {fecha.day:02d}/{fecha.month:02d}")


def arbol_de_fecha(fecha):
    """El árbol del horóscopo de los árboles (el «druídico» o «galo», popular y de creación moderna) de una fecha.

    Devuelve {"arbol": …, "tramo": [[mes, día], [mes, día]], "rasgos": […]}: el árbol, el tramo de fechas que
    contiene a `fecha` (un árbol tiene uno, dos o tres) y las claves de sus rasgos. Cubre todos los días del año.
    """
    _exigir_fecha(fecha)
    clave = (fecha.month, fecha.day)
    for entrada in cargar_arboles()["horoscopo"]:
        for desde, hasta in entrada["tramos"]:
            if _en_rango(clave, desde, hasta):
                return _copia({"arbol": _arbol(entrada["arbol"]), "tramo": [desde, hasta], "rasgos": entrada["rasgos"]})
    raise LookupError(f"ningún árbol cubre el {fecha.day:02d}/{fecha.month:02d}")


def _dia_juliano(instante):
    if not isinstance(instante, datetime.date):
        raise TypeError(f"instante debe ser datetime.date o datetime.datetime, no {type(instante).__name__}")
    if not isinstance(instante, datetime.datetime):
        instante = datetime.datetime(instante.year, instante.month, instante.day, 12, tzinfo=datetime.timezone.utc)
    elif instante.tzinfo is None:
        instante = instante.replace(tzinfo=datetime.timezone.utc)
    return instante.timestamp() / 86400 + 2440587.5


# Términos principales de la longitud de la Luna (Meeus, «Astronomical Algorithms», cap. 47):
# (D, M, M', F, coeficiente en millonésimas de grado). D es la elongación media; M y M', las anomalías medias del
# Sol y de la Luna; F, el argumento de latitud.
_TERMINOS_LUNARES = (
    (0, 0, 1, 0, 6288774), (2, 0, -1, 0, 1274027), (2, 0, 0, 0, 658314), (0, 0, 2, 0, 213618),
    (0, 1, 0, 0, -185116), (0, 0, 0, 2, -114332), (2, 0, -2, 0, 58793), (2, -1, -1, 0, 57066),
    (2, 0, 1, 0, 53322), (2, -1, 0, 0, 45758), (0, 1, -1, 0, -40923), (1, 0, 0, 0, -34720),
    (0, 1, 1, 0, -30383), (2, 0, 0, -2, 15327), (0, 0, 1, 2, -12528), (0, 0, 1, -2, 10980),
    (4, 0, -1, 0, 10675), (0, 0, 3, 0, 10034), (4, 0, -2, 0, 8548), (2, 1, -1, 0, -7888),
    (2, 1, 0, 0, -6766), (1, 0, -1, 0, -5163), (1, 1, 0, 0, 4987), (2, -1, 1, 0, 4036),
    (2, 0, 2, 0, 3994), (4, 0, 0, 0, 3861), (2, 0, -3, 0, 3665), (0, 1, -2, 0, -2689),
    (2, 0, -1, 2, -2602), (2, -1, -2, 0, 2390), (1, 0, 1, 0, -2348), (2, -2, 0, 0, 2236),
)


def longitud_de_la_luna(instante):
    """La longitud eclíptica tropical de la Luna, en grados (0 a 360), en un instante.

    Es la serie abreviada de Meeus (32 términos): comparada con una efeméride real entre 1900 y 2100, no se
    aleja más de 0,05° (unos 4 minutos de movimiento de la Luna). Un `date` se toma a las 12:00 UTC y un
    `datetime` sin huso, como UTC. No corrige la diferencia entre el tiempo universal y el terrestre (≈ 1 minuto).
    """
    t = (_dia_juliano(instante) - 2451545.0) / 36525.0
    media = 218.3164477 + 481267.88123421 * t - 0.0015786 * t * t
    d = math.radians(297.8501921 + 445267.1114034 * t - 0.0018819 * t * t)
    m = math.radians(357.5291092 + 35999.0502909 * t - 0.0001536 * t * t)
    mp = math.radians(134.9633964 + 477198.8675055 * t + 0.0087414 * t * t)
    f = math.radians(93.2720950 + 483202.0175233 * t - 0.0036539 * t * t)
    e = 1 - 0.002516 * t - 0.0000074 * t * t
    suma = sum(c * e ** abs(cm) * math.sin(cd * d + cm * m + cmp_ * mp + cf * f) for cd, cm, cmp_, cf, c in _TERMINOS_LUNARES)
    a1 = math.radians(119.75 + 131.849 * t)
    a2 = math.radians(53.09 + 479264.290 * t)
    suma += 3958 * math.sin(a1) + 1962 * math.sin(math.radians(media) - f) + 318 * math.sin(a2)
    return (media + suma / 1e6) % 360.0


def nakshatra_de_instante(instante):
    """El nakshatra (mansión lunar) por el que pasa la Luna en un instante, y su árbol (zodiaco sideral, Lahiri).

    Devuelve {"nakshatra": …, "longitud_sideral": grados}. La Luna cambia de nakshatra casi cada día, así que
    **la fecha no basta**: con un `date` se usa el mediodía UTC y puede salir el nakshatra del día anterior o
    del siguiente. Para una persona, hace falta el instante del nacimiento (con su huso horario).
    """
    jd = _dia_juliano(instante)
    sideral = (longitud_de_la_luna(instante) - (_AYANAMSA_J2000 + (jd - 2451545.0) / 365.25 * _AYANAMSA_POR_ANIO)) % 360.0
    return _copia({"nakshatra": cargar_arboles()["nakshatras"][int(sideral // (360 / 27))], "longitud_sideral": sideral})


def rasgos_de(sistema, clave, idioma):
    """Los rasgos de un mes celta, un árbol del horóscopo o un nakshatra en un idioma, en una frase.

    sistema es "celta" (clave: el id del mes, «beth»), "horoscopo" (clave: el id del árbol, «olivo») o
    "nakshatra" (clave: su id, «ashlesha»). Salen de los glosarios de arboles.json y de espiritus.json.
    """
    if sistema not in SISTEMAS_DE_ARBOLES:
        raise ValueError(f"sistema debe ser uno de {SISTEMAS_DE_ARBOLES}, no {sistema!r}")
    arb, esp = cargar_arboles(), cargar_espiritus()
    if idioma not in arb["terminos"]["separador"]:
        raise ValueError(f"idioma desconocido: {idioma!r}")
    entradas = (arb["calendario_celta"]["meses"] if sistema == "celta" else arb["horoscopo"] if sistema == "horoscopo"
                else arb["nakshatras"])
    campo = "arbol" if sistema == "horoscopo" else "id"
    for e in entradas:
        if e[campo] == clave:
            return _frase(e["rasgos"], idioma, [arb["rasgos"], esp["cualidades"]], arb["terminos"]["separador"])
    raise ValueError(f"{sistema}: no hay ninguno con la clave {clave!r}")


# --- Numerología: el número del camino de vida y el año personal ---
# La numerología actual se atribuye a Pitágoras, pero es del siglo XX y no tiene respaldo científico.

def reducir_numero(n, maestros=True):
    """Suma los dígitos de `n` una y otra vez hasta dejar uno solo. Con `maestros`, 11, 22 y 33 no se reducen."""
    if isinstance(n, bool) or not isinstance(n, int) or n < 0:
        raise ValueError(f"n debe ser un entero mayor o igual que 0, no {n!r}")
    while n > 9 and not (maestros and n in NUMEROS_MAESTROS):
        n = sum(int(c) for c in str(n))
    return n


def camino_de_vida(fecha):
    """El número del camino de vida de una fecha de nacimiento: 1 a 9, o uno de los maestros 11, 22 y 33.

    Se suman todos los dígitos del día, el mes y el año, y se repite con el resultado hasta quedar en un dígito,
    salvo que salga 11, 22 o 33, que se conservan. Existen otras formas de calcularlo (reducir antes cada parte,
    por ejemplo) que a veces dan otro número: esta es la de «sumar todos los dígitos».
    """
    _exigir_fecha(fecha)
    return reducir_numero(sum(int(c) for c in f"{fecha.year}{fecha.month}{fecha.day}"))


def anio_personal(fecha, anio):
    """El número del año personal (1 a 9) de quien nació en `fecha`, en el año `anio`.

    Se suman los dígitos del día y el mes de nacimiento y los del año que se consulta, y se reducen a uno solo
    (aquí sin números maestros: el año personal va de 1 a 9).
    """
    _exigir_fecha(fecha)
    if isinstance(anio, bool) or not isinstance(anio, int) or anio < 1:
        raise ValueError(f"anio debe ser un entero positivo, no {anio!r}")
    return reducir_numero(sum(int(c) for c in f"{fecha.day}{fecha.month}{anio}"), maestros=False)


def rasgos_de_numero(numero, idioma):
    """Los rasgos de un número de la numerología (1–9, 11, 22 o 33) en un idioma, en una frase."""
    num, arb, esp = cargar_numerologia(), cargar_arboles(), cargar_espiritus()
    if idioma not in num["terminos"]["separador"]:
        raise ValueError(f"idioma desconocido: {idioma!r}")
    if isinstance(numero, bool) or not isinstance(numero, int):
        raise TypeError(f"numero debe ser int, no {type(numero).__name__}")
    for n in num["numeros"]:
        if n["numero"] == numero:
            return _frase(n["rasgos"], idioma, [arb["rasgos"], esp["cualidades"]], num["terminos"]["separador"])
    raise ValueError(f"no hay un número {numero} en la numerología (son 1–9, 11, 22 y 33)")
