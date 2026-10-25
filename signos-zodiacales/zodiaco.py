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

Las funciones devuelven los diccionarios de los datos tal cual (con nombres y descripciones en los
30 idiomas) y lanzan excepción ante una entrada no válida. Solo usa la biblioteca estándar.
"""
import datetime
import json
from functools import lru_cache
from pathlib import Path

DATOS = Path(__file__).resolve().parent / "datos" / "signos.json"
ANIO_NUEVO = Path(__file__).resolve().parent / "datos" / "anio-nuevo-lunar.json"

SISTEMAS = ("tropical", "sideral")

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
    Nuevo de su año pertenece al año lunar anterior. Las fechas son de la hora de China (UTC+8):
    quien nació el mismo día del Año Nuevo pero en otro huso puede caer en el año anterior.
    """
    if not isinstance(fecha, datetime.date):
        raise TypeError(f"fecha debe ser datetime.date, no {type(fecha).__name__}")
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


def anios_del_animal(id_animal, desde, hasta, datos=None):
    """Los años lunares entre desde y hasta (inclusive) que corresponden a un animal."""
    datos = datos or cargar_datos()
    ids = [s["id"] for s in datos["signos"] if s["tipo"] == "chino"]
    if id_animal not in ids:
        raise ValueError(f"animal desconocido: {id_animal!r}")
    indice = ids.index(id_animal)
    return [a for a in range(desde, hasta + 1) if (a - _ANIO_BASE) % 12 == indice]
