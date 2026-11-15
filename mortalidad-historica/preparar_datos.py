#!/usr/bin/env python3
"""Arma datos/esperanza_vida.csv, datos/londres_muertes.csv y datos/paises.csv a partir de las fuentes que
baja descargar_fuentes.py y de Listado-paises/.

  python3 preparar_datos.py --fuentes fuentes [--codigos RUTA] [--paises RUTA]

Qué escribe en esperanza_vida.csv (una fila por país, década, sexo y edad; la década es el promedio de sus 10 años, y se
descarta si falta alguno):

- Edad 0, sexo T, 1800 a 2009: esperanza de vida al nacer de Gapminder (hasta 1949) y de la ONU, revisión
  2017 (desde 1950), tal como la reúne Our World in Data.
- Edad 5, sexo T, 1800 a 2009: esperanza restante y supervivencia hasta los 5 años, **derivadas** de la
  esperanza al nacer y de la mortalidad de 0 a 5 años de Gapminder (ver `esperanza_a_los_5`).
- Edad 0, sexos M y F, 1950 a 2009: esperanza al nacer por sexo de Gapminder (ONU).
- Edades 15, 30, 45 y 60, sexo T, 1800 a 2009: esperanza restante y supervivencia **modeladas** (modelo
  log-cuadrático de Wilmoth et al., ver modelo.py) desde la esperanza al nacer y la mortalidad de 0 a 5 años.
  Cuando el modelo no puede cuadrar las dos (no hay un k que dé esa e0 con esa 5q0), la fila no se escribe.

También escribe `londres_muertes.csv` (muertes por año en Londres, 1629-1902) y `paises.csv` (código,
nombre y región de cada país), y comprueba que las cifras de cólera de `epidemias.csv` sean las de la fuente.

Los países salen de `codigos_iso.csv` y `paises.csv` de Listado-paises (se leen solo aquí, al rehacer los
datos; las pruebas y la CI no las necesitan). Un nombre o código de las fuentes que no sea un país de esa tabla ni
esté en las listas de omitidos de este archivo hace fallar el script: un país no se pierde en silencio.
Solo biblioteca estándar.
"""
import argparse
import csv
import sys
from pathlib import Path

import leer_rda
import modelo
from comun import DATOS, leer_csv

AQUI = Path(__file__).resolve().parent

DECADAS = range(1800, 2010, 10)          # 1800-1809 ... 2000-2009
PRIMERA_DECADA_POR_SEXO = 1950
# Identificadores de la columna `fuente`; el README («Fuentes») dice qué es cada uno.
FUENTE_E0 = "owid-larga"
FUENTE_E5 = "owid-larga+gapminder-0-5"
FUENTE_SEXO = "gapminder-sexo"
FUENTE_MODELO = "logquad"
FUENTE_MODELO_SEXO = "logquad-sexo"
FUENTE_RILEY = "riley-clio"
EDADES_DEL_MODELO = (15, 30, 45, 60)

# Bandas de incertidumbre: (década desde la que rige, semiancho de la esperanza al nacer en años, semiancho relativo de
# la mortalidad de 0 a 5 años, de dónde sale). Desde 1950 son **medidas**: el percentil 80 de lo que discrepan entre
# sí las revisiones de la ONU (0,59 años) y las dos fuentes de mortalidad infantil, Gapminder y CME (6,6 %), en
# 1950-2009. Antes de 1950 no hay una segunda fuente independiente (Gapminder y Clio Infra salen de la misma
# compilación de Riley y casi no discrepan: mediana de 0,03 años, lo que daría una precisión falsa), así que son
# **supuestos del proyecto**, no mediciones: están ahí para que ninguna cifra parezca más firme de lo que es.
BANDAS = (
    (1950, 0.6, 0.07, "medida"),
    (1900, 3.0, 0.15, "supuesto"),
    (1800, 5.0, 0.20, "supuesto"),
    (0, 6.0, 0.25, "supuesto"),
)
DECADA_MINIMA_ANTES_DE_1800 = 1500

# Edad media a la que mueren los niños que no llegan a los 5 años. Es un supuesto (en la práctica va de
# 0,5 a 1,5 años: casi todas esas muertes ocurren en el primer año). Su efecto sobre la esperanza a los
# 5 años es menor que q5/(1-q5) * 0,5 años (0,33 con una mortalidad de 400 por mil).
EDAD_MEDIA_MUERTE_ANTES_DE_5 = 1.0

# Nombres de Our World in Data que no coinciden con `nombre_iso` de codigos_iso.csv.
ALIAS_OWID = {
    "Bolivia": "BO", "Brunei": "BN", "Cape Verde": "CV", "Cote d'Ivoire": "CI", "Curacao": "CW",
    "Czech Republic": "CZ", "Democratic Republic of Congo": "CD", "Iran": "IR", "Laos": "LA",
    "Macedonia": "MK", "Micronesia (country)": "FM", "Moldova": "MD", "North Korea": "KP",
    "Palestine": "PS", "Reunion": "RE", "Russia": "RU", "South Korea": "KR", "Swaziland": "SZ",
    "Syria": "SY", "Taiwan": "TW", "Tanzania": "TZ", "Timor": "TL", "Turkey": "TR", "Venezuela": "VE",
    "Vietnam": "VN", "Faeroe Islands": "FO",
}
# Regiones, grupos de ingreso y territorios de esa fuente que no son una fila de codigos_iso.csv.
OMITIDOS_OWID = frozenset({
    "Africa", "Aruba", "Asia", "Australia/New Zealand", "Caribbean", "Central America", "Central Asia",
    "Channel Islands", "Eastern Africa", "Eastern Asia", "Eastern Europe", "Europe", "French Guiana",
    "French Polynesia", "Guadeloupe", "Guam", "High-income countries", "Latin America and the Caribbean",
    "Least developed countries", "Less developed regions", "Less developed regions, excluding China",
    "Less developed regions, excluding least developed countries", "Low-income countries",
    "Lower-middle-income countries", "Martinique", "Mayotte", "Melanesia", "Micronesia",
    "Micronesia (region)", "Middle Africa", "Middle-income countries", "More developed regions",
    "Netherlands Antilles", "New Caledonia", "Northern Africa", "Northern America", "Northern Europe",
    "Oceania", "Polynesia", "Serbia (including Kosovo)", "South America", "South-Central Asia",
    "South-Eastern Asia", "Southern Africa", "Southern Asia", "Southern Europe", "Sub-Saharan Africa",
    "United States Virgin Islands", "Upper-middle-income countries", "Western Africa", "Western Asia",
    "Western Europe", "Western Sahara", "World",
    # Territorios y regiones que solo aparecen en el archivo de Riley y Clio Infra.
    "American Samoa", "Americas", "Anguilla", "Bonaire Sint Eustatius and Saba", "British Virgin Islands",
    "Cayman Islands", "Cook Islands", "Falkland Islands", "Montserrat", "Niue", "Northern Mariana Islands",
    "Saint Barthlemy", "Saint Helena", "Saint Martin (French part)", "Saint Pierre and Miquelon",
    "Sint Maarten (Dutch part)", "Tokelau", "Turks and Caicos Islands", "Vatican", "Wallis and Futuna",
})
# Códigos de Gapminder (en minúsculas) que no salen de `codigo_owid`, y los territorios que no son filas.
ALIAS_GAPMINDER = {"kos": "XK", "nld_curacao": "CW"}
OMITIDOS_GAPMINDER = frozenset({
    "abw", "aia", "asm", "cok", "cym", "esh", "flk", "gbg", "gbm", "glp", "guf", "gum", "hos", "maf",
    "mnp", "msr", "mtq", "myt", "ncl", "niu", "pyf", "shn", "spm", "stbar", "sxm", "tca", "tkl", "vgb",
    "vir", "wlf",
})


def esperanza_a_los_5(e0, q5_por_mil, edad_media=EDAD_MEDIA_MUERTE_ANTES_DE_5):
    """(esperanza restante a los 5 años, probabilidad de llegar a los 5) desde e0 y la mortalidad 0-5.

    Parte de que la esperanza al nacer es el promedio de dos grupos: quienes mueren antes de los 5 años
    (una fracción q5, que viven en promedio `edad_media` años) y quienes llegan a los 5 (que viven 5 años
    más su esperanza restante e5):  e0 = q5 * edad_media + (1 - q5) * (5 + e5).  Se despeja e5.
    Siempre sale 5 + e5 >= e0: sobrevivir a la infancia nunca acorta la vida esperada.
    """
    q5 = q5_por_mil / 1000.0
    if not 0 <= q5 < 1:
        raise ValueError(f"mortalidad 0-5 fuera de rango: {q5_por_mil} por mil")
    return (e0 - q5 * edad_media) / (1 - q5) - 5, 1 - q5


def decada_de(anio):
    return anio - anio % 10


def promedios_por_decada(serie, desde=DECADAS[0], hasta=DECADAS[-1] + 9):
    """{década: promedio de sus 10 años} de {año: valor}; las décadas con algún año faltante se omiten."""
    por_decada = {}
    for anio, valor in serie.items():
        if desde <= anio <= hasta:
            por_decada.setdefault(decada_de(anio), {})[anio] = valor
    return {d: sum(v.values()) / 10 for d, v in sorted(por_decada.items()) if len(v) == 10}


def banda_de(decada):
    """(semiancho de e0 en años, semiancho relativo de 5q0, 'medida' o 'supuesto') para una década (año de inicio)."""
    for desde, e0, q5, origen in BANDAS:
        if decada >= desde:
            return e0, q5, origen
    raise ValueError(decada)


def promedios_con_datos(serie, desde, hasta):
    """{década: promedio de los años con dato} de {año: valor}, para décadas con al menos un año (series dispersas)."""
    por_decada = {}
    for anio, valor in serie.items():
        if desde <= anio <= hasta:
            por_decada.setdefault(decada_de(anio), []).append(valor)
    return {d: sum(v) / len(v) for d, v in sorted(por_decada.items())}


def leer_riley(ruta, codigos):
    """{alfa-2: {año: esperanza de vida}} del archivo «Riley (2005), Clio Infra (2015) y ONU (2019)» de OWID."""
    por_nombre = {**_alfa2_por_nombre(codigos), **ALIAS_OWID}
    series, desconocidos = {}, set()
    for f in _filas(ruta):
        entidad = f["Entity"]
        if entidad in OMITIDOS_OWID:
            continue
        if entidad not in por_nombre:
            desconocidos.add(entidad)
            continue
        if f["Life expectancy"] != "":
            series.setdefault(por_nombre[entidad], {})[int(f["Year"])] = float(f["Life expectancy"])
    if desconocidos:
        raise SystemExit("Entidades de Our World in Data (Riley) sin país ni omisión (añádelas a ALIAS_OWID u "
                         f"OMITIDOS_OWID): {sorted(desconocidos)}")
    return series


def _filas(ruta):
    with Path(ruta).open(encoding="utf-8", newline="") as f:
        return list(csv.DictReader(f))


def _alfa2_por_nombre(codigos):
    return {c["nombre_iso"]: c["codigo"] for c in codigos}


def _alfa2_por_alfa3(codigos):
    return {c["codigo_owid"].lower(): c["codigo"] for c in codigos}


def leer_owid(ruta, codigos):
    """{alfa-2: {año: esperanza de vida}} del archivo de Our World in Data (por nombre de país)."""
    por_nombre = {**_alfa2_por_nombre(codigos), **ALIAS_OWID}
    series, desconocidos = {}, set()
    for f in _filas(ruta):
        entidad = f["Entity"]
        if entidad in OMITIDOS_OWID:
            continue
        if entidad not in por_nombre:
            desconocidos.add(entidad)
            continue
        valor = f["Life expectancy (Gapminder, UN)"]
        if valor != "":
            series.setdefault(por_nombre[entidad], {})[int(f["Year"])] = float(valor)
    if desconocidos:
        raise SystemExit("Entidades de Our World in Data sin país ni omisión (añádelas a ALIAS_OWID u "
                         f"OMITIDOS_OWID): {sorted(desconocidos)}")
    return series


def leer_gapminder(ruta, columna_entidad, columna_valor, codigos):
    """{alfa-2: {año: valor}} de un archivo de Gapminder (códigos alfa-3 en minúsculas)."""
    por_alfa3 = {**_alfa2_por_alfa3(codigos), **ALIAS_GAPMINDER}
    series, desconocidos = {}, set()
    for f in _filas(ruta):
        entidad = f[columna_entidad]
        if entidad in OMITIDOS_GAPMINDER:
            continue
        if entidad not in por_alfa3:
            desconocidos.add(entidad)
            continue
        if f[columna_valor] != "":
            series.setdefault(por_alfa3[entidad], {})[int(f["time"])] = float(f[columna_valor])
    if desconocidos:
        raise SystemExit("Códigos de Gapminder sin país ni omisión (añádelos a ALIAS_GAPMINDER u "
                         f"OMITIDOS_GAPMINDER): {sorted(desconocidos)}")
    return series


def leer_coeficientes(ruta, sexo, nombre_objeto):
    """Las filas de modelo_logcuadratico.csv de un sexo: [sexo, edad, ax, bx, cx, vx], de un .rda de DemoTools."""
    objeto = leer_rda.aplanar(leer_rda.cargar(ruta))[nombre_objeto]
    # El objeto es una lista con nombre; `coefficients` es un data.frame con ax, bx, cx y vx por grupo de edad.
    nombres = next(a for a in _atributos(objeto) if a[0] == "names")[1]
    coeficientes = objeto[1][nombres.index("coefficients")]
    columnas = dict(zip(next(a for a in _atributos(coeficientes) if a[0] == "names")[1], coeficientes[1]))
    filas = [[sexo, edad] + [repr(columnas[c][i]) for c in ("ax", "bx", "cx", "vx")] for i, edad in enumerate(modelo.EDADES)]
    if any(len(columnas[c]) != len(modelo.EDADES) for c in ("ax", "bx", "cx", "vx")):
        raise SystemExit("el .rda no tiene los 24 grupos de edad esperados")
    return filas


def _atributos(objeto):
    """[(nombre, valor)] de los atributos de un objeto leído con leer_rda (la pareja ("attr", valor, pairlist))."""
    resultado, par = [], objeto[2]
    while par:
        _, nombre, valor, siguiente, _ = par
        resultado.append((nombre, valor))
        par = siguiente
    return resultado


def _extremos(valores):
    """(mínimo, máximo) de una lista de valores, o None si falta alguno."""
    return None if any(v is None for v in valores) else (min(valores), max(valores))


def _esquinas(q5_mil, e0, ancho_e0, rel_q5):
    """Los casos que acotan la banda: el centro, las dos combinaciones coherentes (menos esperanza al nacer con más
    mortalidad de 0 a 5 años, y al revés) y los dos extremos de la esperanza al nacer con la mortalidad intacta. Se
    dejan fuera «mucha esperanza con mucha mortalidad infantil» y su inverso, que se contradicen entre sí."""
    return [(q5_mil, e0),
            (q5_mil * (1 + rel_q5), e0 - ancho_e0), (q5_mil * (1 - rel_q5), e0 + ancho_e0),
            (q5_mil, e0 - ancho_e0), (q5_mil, e0 + ancho_e0)]


def banda_a_los_5(q5_mil, e0, ancho_e0, rel_q5):
    """(bajo, alto) de la esperanza restante a los 5 años por la identidad, con e0 y 5q0 en su banda."""
    return _extremos([esperanza_a_los_5(e, q)[0] for q, e in _esquinas(q5_mil, e0, ancho_e0, rel_q5)
                      if 0 <= q < 1000])


def modelo_con_bandas(q5_mil, e0, edades, sexo, ancho_e0, rel_q5):
    """({edad: (esperanza, supervivencia)}, {edad: (bajo, alto)}) del modelo para un país y una década, o (None, None)
    si el modelo no puede cuadrar la esperanza al nacer con la mortalidad de 0 a 5 años en el centro. Las esquinas que
    el modelo no puede cuadrar se ignoran (la banda queda más estrecha de lo que sería); si no queda ninguna, el
    valor sale sin banda ({})."""
    centro = modelo.esperanzas_por_edad(q5_mil, e0, edades, sexo)
    if centro is None:
        return None, None
    resultados = [r for r in (modelo.esperanzas_por_edad(q, e, edades, sexo)
                              for q, e in _esquinas(q5_mil, e0, ancho_e0, rel_q5)[1:]) if r is not None]
    if not resultados:
        return centro, {}
    bandas = {}
    for edad in edades:
        valores = [centro[edad][0]] + [r[edad][0] for r in resultados]
        bandas[edad] = (min(valores), max(valores))
    return centro, bandas


def construir(e0_total, q5, e0_mujeres, e0_hombres, edades_modelo=(), riley=None):
    """Las filas de esperanza_vida.csv (listas de texto, en el orden de COLUMNAS) a partir de las series.

    `edades_modelo` son las edades (grupos del modelo) para las que se agregan filas modeladas: de ambos sexos desde
    1800 y de mujeres y hombres desde 1950 (con la mortalidad de 0 a 5 años de ambos sexos, que no hay por sexo).
    `riley` ({país: {año: e0}}) agrega las décadas anteriores a 1800 de los pocos países que las tienen (edad 0)."""
    filas = []
    sin_modelo = []  # (sexo, país, década) en que el modelo no pudo cuadrar e0 con 5q0
    modeladas = (FUENTE_MODELO, FUENTE_MODELO_SEXO, FUENTE_RILEY)

    def fila(codigo, decada, sexo, edad, esperanza, supervivencia, fuente, banda=None):
        calidad = "C" if fuente in modeladas or decada < 1950 else "B"
        bajo, alto = ("", "") if not banda else (f"{banda[0]:.1f}", f"{banda[1]:.1f}")
        filas.append([
            codigo, decada, decada + 9, sexo, edad, f"{esperanza:.1f}",
            "" if supervivencia is None else f"{supervivencia:.3f}", bajo, alto, calidad, "verificado", fuente,
        ])

    for codigo in sorted(e0_total):
        e0_dec = promedios_por_decada(e0_total[codigo])
        q5_dec = promedios_por_decada(q5.get(codigo, {}))
        for decada, e0 in e0_dec.items():
            ancho, rel, _ = banda_de(decada)
            fila(codigo, decada, "T", 0, e0, None, FUENTE_E0, (e0 - ancho, e0 + ancho))
            if decada in q5_dec:
                por_edad = bandas = None
                if edades_modelo:
                    por_edad, bandas = modelo_con_bandas(q5_dec[decada], e0, edades_modelo, "T", ancho, rel)
                    if por_edad is None:
                        # El modelo no puede cuadrar las dos cifras de la fuente (en Gapminder, Barbados tiene una
                        # mortalidad de 0 a 5 años de más de 550 por mil): ninguna edad derivada de ellas es de fiar.
                        sin_modelo.append(("T", codigo, decada))
                        continue
                # e5 y supervivencia salen de los promedios de la década: la identidad es lineal en e0 y
                # casi lineal en q5, y así la fila de edad 5 cuadra con la de edad 0.
                e5, l5 = esperanza_a_los_5(e0, q5_dec[decada])
                fila(codigo, decada, "T", 5, e5, l5, FUENTE_E5, banda_a_los_5(q5_dec[decada], e0, ancho, rel))
                for edad in edades_modelo:
                    fila(codigo, decada, "T", edad, por_edad[edad][0], por_edad[edad][1], FUENTE_MODELO,
                         bandas.get(edad))
    for sexo, series in (("F", e0_mujeres), ("M", e0_hombres)):
        for codigo in sorted(series):
            q5_dec = promedios_por_decada(q5.get(codigo, {}))
            for decada, e0 in promedios_por_decada(series[codigo], desde=PRIMERA_DECADA_POR_SEXO).items():
                ancho, rel, _ = banda_de(decada)
                fila(codigo, decada, sexo, 0, e0, None, FUENTE_SEXO, (e0 - ancho, e0 + ancho))
                if edades_modelo and decada in q5_dec:
                    edades = (5,) + tuple(edades_modelo)
                    por_edad, bandas = modelo_con_bandas(q5_dec[decada], e0, edades, sexo, ancho, rel)
                    if por_edad is None:
                        sin_modelo.append((sexo, codigo, decada))
                        continue
                    for edad in edades:
                        # A los 5 años no se guarda la supervivencia: sería la de ambos sexos, no la de este.
                        fila(codigo, decada, sexo, edad, por_edad[edad][0], None if edad == 5 else por_edad[edad][1],
                             FUENTE_MODELO_SEXO, bandas.get(edad))
    for codigo in sorted(riley or {}):
        for decada, e0 in promedios_con_datos(riley[codigo], DECADA_MINIMA_ANTES_DE_1800, 1799).items():
            ancho, _, _ = banda_de(decada)
            fila(codigo, decada, "T", 0, e0, None, FUENTE_RILEY, (e0 - ancho, e0 + ancho))
    # El orden final es estable: país, sexo (T, F, M), edad, década.
    orden_sexo = {"T": 0, "F": 1, "M": 2}
    filas.sort(key=lambda f: (f[0], orden_sexo[f[3]], f[4], f[1]))
    construir.sin_modelo = sin_modelo
    return filas


FUENTE_LONDRES = "londres-owid"
FUENTE_COLERA = "thomas-2015-owid"


def construir_londres(ruta):
    """Las filas de londres_muertes.csv: [año, muertes totales, muertes por viruela, estado, fuente]."""
    filas = []
    for f in _filas(ruta):
        columnas = [c for c in f if c not in ("Entity", "Year")]
        viruela = next(c for c in columnas if c.startswith("Deaths from Smallpox"))
        totales = next(c for c in columnas if c.startswith("Deaths from all causes"))
        if f[viruela] == "" or f[totales] == "":
            continue
        filas.append([int(f["Year"]), int(float(f[totales])), int(float(f[viruela])), "verificado", FUENTE_LONDRES])
    return sorted(filas)


def construir_paises(ruta):
    """Las filas de paises.csv: [código, nombre, región], de Listado-paises/paises.csv."""
    return sorted([f["codigo"], f["pais"], f["region"]] for f in _filas(ruta))


def comprobar_colera(ruta, epidemias):
    """Falla si una epidemia `verificada` por la serie de cólera de Gran Bretaña no tiene su cifra.

    `epidemias` son las filas de epidemias.csv; las que traen la fuente `thomas-2015-owid` deben tener como
    muertes mínimas y máximas el valor de la serie en su último año (el de 1832 suma el brote de 1831-1832).
    """
    serie = {int(f["Year"]): int(float(f["Cholera deaths"])) for f in _filas(ruta)}
    for f in epidemias:
        if f["fuente"] != FUENTE_COLERA:
            continue
        esperado = serie.get(int(f["hasta"]))
        if esperado is None or f["muertes_min"] != str(esperado) or f["muertes_max"] != str(esperado):
            raise SystemExit(f"epidemias.csv: {f['id']} dice {f['muertes_min']}-{f['muertes_max']} muertes y la "
                             f"fuente dice {esperado} en {f['hasta']}")


def omim_de_mondo(ruta):
    """{id MONDO: ([códigos OMIM de sus xref], obsoleto)} del archivo OBO de MONDO (se lee como texto)."""
    resultado, actual = {}, None
    for linea in Path(ruta).read_text(encoding="utf-8").splitlines():
        if linea == "[Term]":
            actual = {"id": None, "omim": [], "obsoleta": False}
        elif linea == "" and actual is not None:
            if actual["id"]:
                resultado[actual["id"]] = (actual["omim"], actual["obsoleta"])
            actual = None
        elif actual is not None:
            if linea.startswith("id: "):
                actual["id"] = linea[4:]
            elif linea.startswith("is_obsolete: true"):
                actual["obsoleta"] = True
            elif linea.startswith("xref: OMIM:"):
                actual["omim"].append(linea[len("xref: OMIM:"):].split()[0])
    return resultado


def comprobar_omim(ruta, hereditarias):
    """Falla si el código OMIM de una enfermedad hereditaria no es un xref de su término MONDO (o este es obsoleto)."""
    mondo = omim_de_mondo(ruta)
    for f in hereditarias:
        if not f["omim"]:
            continue
        omim, obsoleto = mondo.get(f["mondo"], ([], False))
        if f["omim"] not in omim or obsoleto:
            raise SystemExit(f"hereditarias.csv: {f['id']}: OMIM {f['omim']} no es un xref de {f['mondo']} en MONDO"
                             + (" (término obsoleto)" if obsoleto else ""))


def _escribir(nombre, filas):
    columnas = leer_csv(DATOS / f"{nombre}.csv")[0]
    with (DATOS / f"{nombre}.csv").open("w", encoding="utf-8", newline="") as salida:
        escritor = csv.writer(salida)
        escritor.writerow(columnas)
        escritor.writerows(filas)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--fuentes", required=True, type=Path, help="carpeta con lo que bajó descargar_fuentes.py")
    parser.add_argument("--codigos", type=Path, default=AQUI.parent / "Listado-paises" / "codigos_iso.csv",
                        help="codigos_iso.csv de Listado-paises (por omisión, el de la carpeta vecina)")
    parser.add_argument("--paises", type=Path, default=AQUI.parent / "Listado-paises" / "paises.csv",
                        help="paises.csv de Listado-paises (por omisión, el de la carpeta vecina)")
    args = parser.parse_args(argv)
    for ruta in (args.codigos, args.paises):
        if not ruta.exists():
            raise SystemExit(f"Falta {ruta}: para rehacer los datos hace falta Listado-paises/ al lado.")
    codigos = _filas(args.codigos)
    f = args.fuentes
    coeficientes = []
    for sexo, sufijo, objeto in (("T", "b", "fitted_logquad_b"), ("F", "f", "fitted_logquad_f"), ("M", "m", "fitted_logquad_m")):
        coeficientes += leer_coeficientes(f / f"modelo-logcuadratico-{sufijo}.rda", sexo, objeto)
    _escribir("modelo_logcuadratico", coeficientes)
    modelo.coeficientes.cache_clear()
    filas = construir(
        leer_owid(f / "esperanza-larga-owid.csv", codigos),
        leer_gapminder(f / "mortalidad-0-5-gapminder.csv", "country",
                       "child_mortality_0_5_year_olds_dying_per_1000_born", codigos),
        leer_gapminder(f / "esperanza-mujeres-gapminder.csv", "geo", "life_expectancy_female", codigos),
        leer_gapminder(f / "esperanza-hombres-gapminder.csv", "geo", "life_expectancy_male", codigos),
        edades_modelo=EDADES_DEL_MODELO,
        riley=leer_riley(f / "esperanza-riley-clio-owid.csv", codigos),
    )
    comprobar_colera(f / "colera-gran-bretana-owid.csv", _filas(DATOS / "epidemias.csv"))
    comprobar_omim(f / "mondo-edit.obo", _filas(DATOS / "hereditarias.csv"))
    _escribir("esperanza_vida", filas)
    print(f"esperanza_vida.csv: {len(filas)} filas, {len({fila[0] for fila in filas})} países; "
          f"sin edades modeladas en {len(construir.sin_modelo)} décadas de país (el modelo no cuadra e0 con 5q0).")
    londres = construir_londres(f / "muertes-londres-owid.csv")
    _escribir("londres_muertes", londres)
    print(f"londres_muertes.csv: {len(londres)} años.")
    paises = construir_paises(args.paises)
    _escribir("paises", paises)
    print(f"paises.csv: {len(paises)} países.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
