"""Consulta de signos desde datos/signos.json: el signo occidental de una fecha y el animal chino de un año.

    >>> import datetime, zodiaco
    >>> zodiaco.signo_occidental(datetime.date(1990, 4, 3))["id"]
    'aries'
    >>> zodiaco.signo_occidental(datetime.date(1990, 4, 3), sistema="sideral")["id"]
    'piscis'
    >>> a = zodiaco.animal_chino(2026)
    >>> a["signo"]["id"], a["elemento_del_anio"], a["tronco"]["hanzi"] + a["signo"]["rama"]["hanzi"]
    ('caballo', 'fuego', '丙午')

Las funciones devuelven los diccionarios de los datos tal cual (con nombres y descripciones en los
30 idiomas) y lanzan excepción ante una entrada no válida. Solo usa la biblioteca estándar.
"""
import datetime
import json
from functools import lru_cache
from pathlib import Path

DATOS = Path(__file__).resolve().parent / "datos" / "signos.json"

SISTEMAS = ("tropical", "sideral")

# 1984 fue 甲子 (madera, rata): el primer año de un ciclo de 60. El ciclo empieza en el año 4 de la era común.
_ANIO_BASE = 4


@lru_cache(maxsize=1)
def cargar_datos():
    """Lee datos/signos.json una sola vez."""
    with DATOS.open(encoding="utf-8") as f:
        return json.load(f)


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


def animal_chino(anio_lunar, datos=None):
    """El animal, el tronco celeste y el elemento de un año del calendario chino.

    anio_lunar es el año lunar, que empieza con el Año Nuevo chino (entre el 21 de enero y el 20 de
    febrero). Quien nació en enero o principios de febrero puede pertenecer al año anterior: los datos
    no traen las fechas del Año Nuevo, así que esa conversión la tiene que hacer quien llama.

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
