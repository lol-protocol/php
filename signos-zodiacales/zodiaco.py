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

Las funciones devuelven los diccionarios de los datos tal cual (con nombres y descripciones en los
30 idiomas) y lanzan excepción ante una entrada no válida. Solo usa la biblioteca estándar.
"""
import datetime
import json
import re
from functools import lru_cache
from pathlib import Path

DATOS = Path(__file__).resolve().parent / "datos" / "signos.json"
ANIO_NUEVO = Path(__file__).resolve().parent / "datos" / "anio-nuevo-lunar.json"

SISTEMAS = ("tropical", "sideral")

HORA_DE_CHINA = datetime.timezone(datetime.timedelta(hours=8))

# Orden caldeo de los siete astros clásicos: regentes de los 36 decanatos, empezando por Marte en el
# primer decanato de Aries (Marte, Sol, Venus · Mercurio, Luna, Saturno · Júpiter, Marte, Sol…).
ORDEN_CALDEO = ("marte", "sol", "venus", "mercurio", "luna", "saturno", "jupiter")

AFINIDADES = ("alta", "media", "neutra", "baja")

_COMPLEMENTARIOS = {frozenset({"fuego", "aire"}), frozenset({"tierra", "agua"})}

# 1984 fue 甲子 (madera, rata): el primer año de un ciclo de 60. El ciclo empieza en el año 4 de la era común.
_ANIO_BASE = 4


@lru_cache(maxsize=1)
def cargar_datos():
    """Lee datos/signos.json una sola vez."""
    with DATOS.open(encoding="utf-8") as f:
        return json.load(f)


@lru_cache(maxsize=1)
def fechas_anio_nuevo():
    """{año: fecha del Año Nuevo lunar} desde datos/anio-nuevo-lunar.json (1900–2099)."""
    with ANIO_NUEVO.open(encoding="utf-8") as f:
        crudas = json.load(f)["fechas"]
    return {int(a): datetime.date.fromisoformat(d) for a, d in crudas.items()}


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
    if not isinstance(fecha, datetime.date):
        raise TypeError(f"fecha debe ser datetime.date, no {type(fecha).__name__}")
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
    if not isinstance(fecha, datetime.date):
        raise TypeError(f"fecha debe ser datetime.date, no {type(fecha).__name__}")
    dia = datetime.date(fecha.year, fecha.month, fecha.day)
    propio = signo_occidental(dia, sistema, datos)
    for d in range(1, margen + 1):
        antes = signo_occidental(dia - datetime.timedelta(days=d), sistema, datos)
        if antes["id"] != propio["id"]:
            return (antes, propio)
        despues = signo_occidental(dia + datetime.timedelta(days=d), sistema, datos)
        if despues["id"] != propio["id"]:
            return (propio, despues)
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
    if not isinstance(fecha, datetime.date):
        raise TypeError(f"fecha debe ser datetime.date, no {type(fecha).__name__}")
    if isinstance(fecha, datetime.datetime) and fecha.tzinfo is not None:
        fecha = fecha.astimezone(HORA_DE_CHINA)
    tabla = fechas_anio_nuevo()
    if fecha.year not in tabla:
        raise ValueError(f"solo hay fechas del Año Nuevo lunar de {min(tabla)} a {max(tabla)}, no de {fecha.year}")
    dia = datetime.date(fecha.year, fecha.month, fecha.day)
    anio_lunar = fecha.year if dia >= tabla[fecha.year] else fecha.year - 1
    return {**animal_chino(anio_lunar, datos), "anio_lunar": anio_lunar}


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
    anio = animal_chino(anio_lunar, datos)
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


def _inicio_del_signo(fecha, signo, sistema):
    """La fecha en que empezó el tramo del signo que contiene a `fecha`."""
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
    Los tramos son aproximados, como las fechas de los signos.
    """
    datos = datos or cargar_datos()
    signo = signo_occidental(fecha, sistema, datos)
    dia = datetime.date(fecha.year, fecha.month, fecha.day)
    numero = min((dia - _inicio_del_signo(dia, signo, sistema)).days // 10, 2) + 1
    occidentales = [s for s in datos["signos"] if s["tipo"] == "occidental"]
    indice = [s["id"] for s in occidentales].index(signo["id"])
    return {
        "signo": signo,
        "numero": numero,
        "regente": regentes_de_decanatos(signo["id"], datos)[numero - 1],
        "signo_regente": occidentales[(indice + 4 * (numero - 1)) % 12],
    }


def regentes_de_decanatos(id_signo, datos=None):
    """Los astros regentes de los tres decanatos de un signo, en el orden caldeo."""
    datos = datos or cargar_datos()
    ids = [s["id"] for s in datos["signos"] if s["tipo"] == "occidental"]
    if id_signo not in ids:
        raise ValueError(f"signo occidental desconocido: {id_signo!r}")
    indice = ids.index(id_signo)
    return [ORDEN_CALDEO[(indice * 3 + n) % 7] for n in range(3)]


def tramos_de_decanatos(signo, sistema="tropical", anio=2025):
    """Los tres tramos de un signo como ([mes, día] desde, [mes, día] hasta) en un año dado."""
    desde = datetime.date(anio, *signo[sistema]["desde"])
    hasta = datetime.date(anio, *signo[sistema]["hasta"])
    if hasta < desde:  # cruza el fin de año (Capricornio, y Sagitario en el sideral)
        hasta = hasta.replace(year=anio + 1)
    cortes = [desde, desde + datetime.timedelta(days=10), desde + datetime.timedelta(days=20), hasta + datetime.timedelta(days=1)]
    return [([a.month, a.day], [(b - datetime.timedelta(days=1)).month, (b - datetime.timedelta(days=1)).day])
            for a, b in zip(cortes, cortes[1:])]


def anios_del_animal(id_animal, desde, hasta, datos=None):
    """Los años lunares entre desde y hasta (inclusive) que corresponden a un animal."""
    datos = datos or cargar_datos()
    ids = [s["id"] for s in datos["signos"] if s["tipo"] == "chino"]
    if id_animal not in ids:
        raise ValueError(f"animal desconocido: {id_animal!r}")
    indice = ids.index(id_animal)
    return [a for a in range(desde, hasta + 1) if (a - _ANIO_BASE) % 12 == indice]
