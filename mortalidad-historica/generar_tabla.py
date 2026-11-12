#!/usr/bin/env python3
"""Valida los CSV de datos/ y genera por-idioma/*.md y el bloque «Resumen» del README.

  python3 generar_tabla.py              # valida y regenera la salida
  python3 generar_tabla.py --comprobar  # valida y falla si la salida no está al día (no escribe)

Solo biblioteca estándar. Las reglas de cada CSV están en `validar_*`; el significado de cada columna,
en el README.
"""
import sys
from collections import Counter

from comun import (
    CALIDADES, CONFIANZAS, DATOS, ESTADOS, GRUPOS_CAUSA, HERENCIAS, IDIOMAS, INICIOS, POR_IDIOMA, RAIZ,
    RE_CIE10, RE_ID, RE_PAIS, REGIONES, SEXOS, leer_csv,
)

COLUMNAS = {
    "causas_modernas": ["id", "nombre_spa", "nombre_eng", "cie10", "grupo"],
    "causas_historicas": ["termino", "idioma", "causa_id", "confianza", "desde", "hasta", "nota", "estado", "fuente"],
    "epidemias": ["id", "nombre", "agente", "causa_id", "desde", "hasta", "regiones", "mortalidad",
                  "muertes_min", "muertes_max", "confianza", "nota", "estado", "fuente"],
    "hereditarias": ["id", "nombre_spa", "nombre_eng", "herencia", "omim", "frecuencia_mayor_en",
                     "inicio_tipico", "nota", "estado", "fuente"],
    "hitos_registro": ["codigo", "tipo", "desde", "nota", "estado", "fuente"],
    "esperanza_vida": ["codigo", "desde", "hasta", "sexo", "edad", "esperanza_restante",
                       "supervivencia_hasta_edad", "incertidumbre_baja", "incertidumbre_alta", "calidad",
                       "estado", "fuente"],
}
INICIO_RESUMEN = "<!-- resumen: lo escribe generar_tabla.py; no editar a mano -->"
FIN_RESUMEN = "<!-- fin del resumen -->"


class Errores:
    """Junta los errores con la fila en que ocurren, para decirlos todos de una vez."""

    def __init__(self):
        self.lista = []

    def __call__(self, archivo, fila, mensaje):
        donde = archivo if fila is None else f"{archivo}.csv fila {fila}"
        self.lista.append(f"{donde}: {mensaje}")


def _entero(valor, minimo=None, maximo=None):
    """int del texto, o None si no es un entero (o sale del rango)."""
    try:
        n = int(valor)
    except (TypeError, ValueError):
        return None
    if (minimo is not None and n < minimo) or (maximo is not None and n > maximo):
        return None
    return n


def _numero(valor):
    try:
        return float(valor)
    except (TypeError, ValueError):
        return None


def _leer(nombre, error):
    """Filas del CSV, o None si faltan columnas o sobran (el error ya quedó anotado)."""
    columnas, filas = leer_csv(DATOS / f"{nombre}.csv")
    if columnas != COLUMNAS[nombre]:
        error(nombre, None, f"las columnas deberían ser {COLUMNAS[nombre]} y son {columnas}")
        return None
    return filas


def _estado_y_fuente(nombre, i, f, error):
    if f["estado"] not in ESTADOS:
        error(nombre, i, f"estado «{f['estado']}»: debe ser uno de {ESTADOS}")
    elif f["estado"] == "verificado" and not f["fuente"].strip():
        error(nombre, i, "un dato «verificado» necesita su fuente")


def _anios(nombre, i, f, error, desde_obligatorio=True):
    """Valida desde y hasta (años enteros; hasta puede quedar vacío) y devuelve (desde, hasta)."""
    desde = _entero(f["desde"], -5000, 2100)
    hasta = _entero(f["hasta"], -5000, 2100)
    if desde is None and (desde_obligatorio or f["desde"]):
        error(nombre, i, f"desde «{f['desde']}» no es un año")
    if f["hasta"] and hasta is None:
        error(nombre, i, f"hasta «{f['hasta']}» no es un año")
    if desde is not None and hasta is not None and desde > hasta:
        error(nombre, i, f"desde ({desde}) es posterior a hasta ({hasta})")
    return desde, hasta


def _ids_unicos(nombre, filas, error, clave="id"):
    vistos = {}
    for i, f in enumerate(filas, start=2):
        valor = f[clave]
        if not RE_ID.match(valor):
            error(nombre, i, f"{clave} «{valor}» debe ser minúsculas, dígitos y guion bajo")
        if valor in vistos:
            error(nombre, i, f"{clave} «{valor}» repetido (ya está en la fila {vistos[valor]})")
        vistos.setdefault(valor, i)
    return set(vistos)


def validar_causas_modernas(error):
    filas = _leer("causas_modernas", error)
    if filas is None:
        return set()
    ids = _ids_unicos("causas_modernas", filas, error)
    for i, f in enumerate(filas, start=2):
        if not f["nombre_spa"].strip() or not f["nombre_eng"].strip():
            error("causas_modernas", i, "faltan los nombres en español o en inglés")
        if f["grupo"] not in GRUPOS_CAUSA:
            error("causas_modernas", i, f"grupo «{f['grupo']}»: debe ser uno de {GRUPOS_CAUSA}")
        for codigo in f["cie10"].split(";"):
            if not RE_CIE10.match(codigo):
                error("causas_modernas", i, f"cie10 «{codigo}» no tiene la forma A00 ni A00.0")
    return ids


def validar_causas_historicas(error, causas):
    filas = _leer("causas_historicas", error)
    if filas is None:
        return []
    vistos = {}
    for i, f in enumerate(filas, start=2):
        if not f["termino"].strip():
            error("causas_historicas", i, "falta el término")
        if f["idioma"] not in IDIOMAS:
            error("causas_historicas", i, f"idioma «{f['idioma']}»: no es uno de los {len(IDIOMAS)} (ISO 639-3)")
        if f["causa_id"] not in causas:
            error("causas_historicas", i, f"causa_id «{f['causa_id']}» no está en causas_modernas.csv")
        if f["confianza"] not in CONFIANZAS:
            error("causas_historicas", i, f"confianza «{f['confianza']}»: debe ser una de {CONFIANZAS}")
        _anios("causas_historicas", i, f, error, desde_obligatorio=False)
        _estado_y_fuente("causas_historicas", i, f, error)
        clave = (f["termino"].strip().casefold(), f["idioma"], f["causa_id"])
        if clave in vistos:
            error("causas_historicas", i, f"«{f['termino']}» ({f['idioma']}) con la misma causa ya está en la fila {vistos[clave]}")
        vistos.setdefault(clave, i)
    return filas


def validar_epidemias(error, causas):
    filas = _leer("epidemias", error)
    if filas is None:
        return []
    _ids_unicos("epidemias", filas, error)
    for i, f in enumerate(filas, start=2):
        _anios("epidemias", i, f, error)
        if f["causa_id"] and f["causa_id"] not in causas:
            error("epidemias", i, f"causa_id «{f['causa_id']}» no está en causas_modernas.csv")
        regiones = [r for r in f["regiones"].split(";") if r]
        if not regiones:
            error("epidemias", i, "faltan las regiones")
        for r in regiones:
            if r not in REGIONES:
                error("epidemias", i, f"región «{r}»: debe ser una de {REGIONES}")
        if f["confianza"] not in CONFIANZAS:
            error("epidemias", i, f"confianza «{f['confianza']}»: debe ser una de {CONFIANZAS}")
        minimo, maximo = _entero(f["muertes_min"], 0), _entero(f["muertes_max"], 0)
        for campo, valor in (("muertes_min", minimo), ("muertes_max", maximo)):
            if f[campo] and valor is None:
                error("epidemias", i, f"{campo} «{f[campo]}» no es un entero positivo")
        if (f["muertes_min"] == "") != (f["muertes_max"] == ""):
            error("epidemias", i, "muertes_min y muertes_max van juntas o ninguna")
        if minimo is not None and maximo is not None and minimo > maximo:
            error("epidemias", i, "muertes_min es mayor que muertes_max")
        _estado_y_fuente("epidemias", i, f, error)
    return filas


def validar_hereditarias(error):
    filas = _leer("hereditarias", error)
    if filas is None:
        return []
    _ids_unicos("hereditarias", filas, error)
    for i, f in enumerate(filas, start=2):
        if not f["nombre_spa"].strip() or not f["nombre_eng"].strip():
            error("hereditarias", i, "faltan los nombres en español o en inglés")
        if f["herencia"] not in HERENCIAS:
            error("hereditarias", i, f"herencia «{f['herencia']}»: debe ser una de {HERENCIAS}")
        if f["omim"] and not (f["omim"].isdigit() and len(f["omim"]) == 6):
            error("hereditarias", i, f"omim «{f['omim']}» debe tener 6 dígitos")
        if not f["frecuencia_mayor_en"].strip():
            error("hereditarias", i, "falta frecuencia_mayor_en (di «todas las poblaciones» si es el caso)")
        if f["inicio_tipico"] not in INICIOS:
            error("hereditarias", i, f"inicio_tipico «{f['inicio_tipico']}»: debe ser uno de {INICIOS}")
        _estado_y_fuente("hereditarias", i, f, error)
    return filas


def validar_hitos(error):
    filas = _leer("hitos_registro", error)
    if filas is None:
        return []
    vistos = {}
    for i, f in enumerate(filas, start=2):
        if not RE_PAIS.match(f["codigo"]):
            error("hitos_registro", i, f"codigo «{f['codigo']}» debe ser un ISO alfa-2 en mayúsculas")
        if f["tipo"] not in ("civil", "parroquial"):
            error("hitos_registro", i, f"tipo «{f['tipo']}»: debe ser civil o parroquial")
        if _entero(f["desde"], 0, 2100) is None:
            error("hitos_registro", i, f"desde «{f['desde']}» no es un año")
        _estado_y_fuente("hitos_registro", i, f, error)
        clave = (f["codigo"], f["tipo"])
        if clave in vistos:
            error("hitos_registro", i, f"{clave} repetido (ya está en la fila {vistos[clave]})")
        vistos.setdefault(clave, i)
    return filas


def validar_esperanza_vida(error):
    filas = _leer("esperanza_vida", error)
    if filas is None:
        return []
    vistos = {}
    for i, f in enumerate(filas, start=2):
        cod = f["codigo"]
        if not (RE_PAIS.match(cod) or (cod.startswith("R:") and cod[2:] in REGIONES)):
            error("esperanza_vida", i, f"codigo «{cod}»: un ISO alfa-2 o R:<región> ({', '.join(REGIONES)})")
        desde, hasta = _anios("esperanza_vida", i, f, error)
        if hasta is None and f["hasta"] == "":
            error("esperanza_vida", i, "falta hasta (un período siempre se cierra)")
        if f["sexo"] not in SEXOS:
            error("esperanza_vida", i, f"sexo «{f['sexo']}»: debe ser uno de {SEXOS}")
        if _entero(f["edad"], 0, 110) is None:
            error("esperanza_vida", i, f"edad «{f['edad']}» debe ser un entero de 0 a 110")
        esperanza = _numero(f["esperanza_restante"])
        if esperanza is None or esperanza < 0 or esperanza > 120:
            error("esperanza_vida", i, f"esperanza_restante «{f['esperanza_restante']}» debe ser un número de 0 a 120")
        if f["supervivencia_hasta_edad"]:
            s = _numero(f["supervivencia_hasta_edad"])
            if s is None or not 0 <= s <= 1:
                error("esperanza_vida", i, "supervivencia_hasta_edad debe ser un número de 0 a 1")
        baja, alta = _numero(f["incertidumbre_baja"]), _numero(f["incertidumbre_alta"])
        if (f["incertidumbre_baja"] == "") != (f["incertidumbre_alta"] == ""):
            error("esperanza_vida", i, "incertidumbre_baja e incertidumbre_alta van juntas o ninguna")
        elif baja is not None and alta is not None and esperanza is not None and not baja <= esperanza <= alta:
            error("esperanza_vida", i, "la esperanza debe quedar entre incertidumbre_baja e incertidumbre_alta")
        if f["calidad"] not in CALIDADES:
            error("esperanza_vida", i, f"calidad «{f['calidad']}»: debe ser una de {CALIDADES}")
        _estado_y_fuente("esperanza_vida", i, f, error)
        clave = (cod, f["desde"], f["hasta"], f["sexo"], f["edad"])
        if clave in vistos:
            error("esperanza_vida", i, f"{clave} repetido (ya está en la fila {vistos[clave]})")
        vistos.setdefault(clave, i)
    return filas


def validar():
    """(errores, datos): todos los errores de todos los CSV, y las filas ya leídas de cada uno."""
    error = Errores()
    causas = validar_causas_modernas(error)
    datos = {
        "causas_modernas": [],
        "causas_historicas": validar_causas_historicas(error, causas),
        "epidemias": validar_epidemias(error, causas),
        "hereditarias": validar_hereditarias(error),
        "hitos_registro": validar_hitos(error),
        "esperanza_vida": validar_esperanza_vida(error),
    }
    datos["causas_modernas"] = leer_csv(DATOS / "causas_modernas.csv")[1]
    return error.lista, datos


def pagina_idioma(codigo, datos):
    nombre, propio, rtl = IDIOMAS[codigo]
    modernas = {c["id"]: c for c in datos["causas_modernas"]}
    filas = sorted((f for f in datos["causas_historicas"] if f["idioma"] == codigo),
                   key=lambda f: (f["termino"].casefold(), f["causa_id"]))
    lineas = [
        f"# Causas de defunción históricas: {nombre} ({propio}, `{codigo}`)",
        "",
        "<!-- Generado por generar_tabla.py: no lo edites a mano. -->",
        "",
        "> **Borrador.** Lo escribió un asistente de IA y ninguna persona nativa lo ha revisado. Cada término"
        " lleva una **confianza** de su correspondencia con una causa moderna: es una pista para leer un"
        " registro antiguo, **no un diagnóstico**. Ver «Cómo leer una causa de defunción» en el"
        " [README](../README.md).",
        "",
        f"{len(filas)} términos." if filas else "Todavía no hay términos en este idioma.",
        "",
    ]
    if filas:
        if rtl:
            lineas += ['<div dir="rtl">', ""]
        lineas += ["| Término | Causa moderna | CIE-10 | Confianza | Nota | Estado |", "|---|---|---|---|---|---|"]
        for f in filas:
            m = modernas[f["causa_id"]]
            rango = ""
            if f["desde"] or f["hasta"]:
                rango = f" ({f['desde'] or '…'}–{f['hasta'] or '…'})"
            nota = (f["nota"] + rango).strip()
            lineas.append(f"| {f['termino']} | {m['nombre_spa']} | {m['cie10'].replace(';', ', ')} | "
                          f"{f['confianza']} | {nota} | {f['estado']} |")
        if rtl:
            lineas += ["", "</div>"]
    return "\n".join(lineas) + "\n"


def indice_idiomas(datos):
    cuenta = Counter(f["idioma"] for f in datos["causas_historicas"])
    lineas = [
        "# Glosario de causas de defunción por idioma",
        "",
        "<!-- Generado por generar_tabla.py: no lo edites a mano. -->",
        "",
        "Una página por idioma, con el código ISO 639-3. Los términos son un **borrador sin revisión nativa**.",
        "",
        "| Idioma | Código | Términos |",
        "|---|---|---|",
    ]
    for codigo, (nombre, propio, _) in IDIOMAS.items():
        lineas.append(f"| [{nombre} ({propio})]({codigo}.md) | `{codigo}` | {cuenta.get(codigo, 0)} |")
    return "\n".join(lineas) + "\n"


def resumen(datos):
    hist = datos["causas_historicas"]
    por_conf = Counter(f["confianza"] for f in hist)
    estados = Counter(f["estado"] for tabla in ("causas_historicas", "epidemias", "hereditarias", "hitos_registro",
                                                 "esperanza_vida") for f in datos[tabla])
    sin_terminos = sorted(set(IDIOMAS) - {f["idioma"] for f in hist})
    usadas = {f["causa_id"] for f in hist}
    sin_uso = sorted(c["id"] for c in datos["causas_modernas"] if c["id"] not in usadas)
    ev = datos["esperanza_vida"]
    return "\n".join([
        INICIO_RESUMEN,
        f"- **Causas modernas:** {len(datos['causas_modernas'])}; **términos históricos:** {len(hist)} en "
        f"{len({f['idioma'] for f in hist})} de {len(IDIOMAS)} idiomas "
        f"(confianza alta: {por_conf['alta']}, media: {por_conf['media']}, baja: {por_conf['baja']}).",
        f"- **Idiomas sin términos:** {', '.join(sin_terminos) if sin_terminos else 'ninguno'}.",
        f"- **Causas modernas sin ningún término histórico:** {', '.join(sin_uso) if sin_uso else 'ninguna'}.",
        f"- **Epidemias:** {len(datos['epidemias'])}. **Enfermedades hereditarias:** {len(datos['hereditarias'])}. "
        f"**Hitos de registro civil:** {len(datos['hitos_registro'])}.",
        f"- **Esperanza de vida:** {len(ev)} filas"
        + (f" ({len({f['codigo'] for f in ev})} países o regiones)." if ev else " (todavía sin datos)."),
        f"- **Filas verificadas contra su fuente:** {estados['verificado']} de {sum(estados.values())}.",
        FIN_RESUMEN,
    ])


def con_resumen(readme, datos):
    """El README con el bloque «Resumen» regenerado; falla si no están las dos marcas."""
    if INICIO_RESUMEN not in readme or FIN_RESUMEN not in readme:
        raise SystemExit("README.md no tiene las marcas del bloque «Resumen»")
    antes, resto = readme.split(INICIO_RESUMEN, 1)
    _, despues = resto.split(FIN_RESUMEN, 1)
    return antes + resumen(datos) + despues


def salida_esperada(datos):
    """{ruta: contenido} de todo lo que genera este script."""
    esperada = {POR_IDIOMA / "README.md": indice_idiomas(datos)}
    for codigo in IDIOMAS:
        esperada[POR_IDIOMA / f"{codigo}.md"] = pagina_idioma(codigo, datos)
    readme = RAIZ / "README.md"
    esperada[readme] = con_resumen(readme.read_text(encoding="utf-8"), datos)
    return esperada


def main(argv):
    comprobar = "--comprobar" in argv
    errores, datos = validar()
    if errores:
        print("Errores en los datos:", file=sys.stderr)
        for e in errores:
            print(f"  - {e}", file=sys.stderr)
        return 1
    esperada = salida_esperada(datos)
    sobrantes = sorted(p for p in POR_IDIOMA.glob("*.md") if p not in esperada)
    if comprobar:
        problemas = [f"{p.relative_to(RAIZ)} no está al día" for p, c in esperada.items()
                     if not p.exists() or p.read_text(encoding="utf-8") != c]
        problemas += [f"{p.relative_to(RAIZ)} sobra: no corresponde a ningún idioma" for p in sobrantes]
        if problemas:
            print("La salida generada no está al día; corre `python3 generar_tabla.py`:", file=sys.stderr)
            for p in problemas:
                print(f"  - {p}", file=sys.stderr)
            return 1
        print("Datos válidos y salida al día.")
        return 0
    POR_IDIOMA.mkdir(exist_ok=True)
    for ruta, contenido in esperada.items():
        ruta.write_text(contenido, encoding="utf-8")
    for p in sobrantes:
        p.unlink()
    print(f"Datos válidos; {len(esperada)} archivos escritos.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
