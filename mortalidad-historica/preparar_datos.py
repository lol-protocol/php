#!/usr/bin/env python3
"""Arma datos/esperanza_vida.csv, datos/londres_muertes.csv y datos/paises.csv a partir de las fuentes que
baja descargar_fuentes.py y de Listado-paises/.

  python3 preparar_datos.py --fuentes fuentes [--codigos RUTA] [--paises RUTA]

Qué escribe (una fila por país, década, sexo y edad; la década es el promedio de sus 10 años, y se
descarta si falta alguno):

- Edad 0, sexo T, 1800 a 2009: esperanza de vida al nacer de Gapminder (hasta 1949) y de la ONU, revisión
  2017 (desde 1950), tal como la reúne Our World in Data.
- Edad 5, sexo T, 1800 a 2009: esperanza restante y supervivencia hasta los 5 años, **derivadas** de la
  esperanza al nacer y de la mortalidad de 0 a 5 años de Gapminder (ver `esperanza_a_los_5`).
- Edad 0, sexos M y F, 1950 a 2009: esperanza al nacer por sexo de Gapminder (ONU).

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

from comun import DATOS, leer_csv

AQUI = Path(__file__).resolve().parent

DECADAS = range(1800, 2010, 10)          # 1800-1809 ... 2000-2009
PRIMERA_DECADA_POR_SEXO = 1950
# Identificadores de la columna `fuente`; el README («Fuentes») dice qué es cada uno.
FUENTE_E0 = "owid-larga"
FUENTE_E5 = "owid-larga+gapminder-0-5"
FUENTE_SEXO = "gapminder-sexo"

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
    "Vietnam": "VN",
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


def construir(e0_total, q5, e0_mujeres, e0_hombres):
    """Las filas de esperanza_vida.csv (listas de texto, en el orden de COLUMNAS) a partir de las series."""
    filas = []

    def fila(codigo, decada, sexo, edad, esperanza, supervivencia, fuente):
        calidad = "C" if decada < 1950 else "B"
        filas.append([
            codigo, decada, decada + 9, sexo, edad, f"{esperanza:.1f}",
            "" if supervivencia is None else f"{supervivencia:.3f}", "", "", calidad, "verificado", fuente,
        ])

    for codigo in sorted(e0_total):
        e0_dec = promedios_por_decada(e0_total[codigo])
        q5_dec = promedios_por_decada(q5.get(codigo, {}))
        for decada, e0 in e0_dec.items():
            fila(codigo, decada, "T", 0, e0, None, FUENTE_E0)
            if decada in q5_dec:
                # e5 y supervivencia salen de los promedios de la década: la identidad es lineal en e0 y
                # casi lineal en q5, y así la fila de edad 5 cuadra con la de edad 0.
                e5, l5 = esperanza_a_los_5(e0, q5_dec[decada])
                fila(codigo, decada, "T", 5, e5, l5, FUENTE_E5)
    for sexo, series in (("F", e0_mujeres), ("M", e0_hombres)):
        for codigo in sorted(series):
            for decada, e0 in promedios_por_decada(series[codigo], desde=PRIMERA_DECADA_POR_SEXO).items():
                fila(codigo, decada, sexo, 0, e0, None, FUENTE_SEXO)
    # El orden final es estable: país, sexo (T, F, M), edad, década.
    orden_sexo = {"T": 0, "F": 1, "M": 2}
    filas.sort(key=lambda f: (f[0], orden_sexo[f[3]], f[4], f[1]))
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
    filas = construir(
        leer_owid(f / "esperanza-larga-owid.csv", codigos),
        leer_gapminder(f / "mortalidad-0-5-gapminder.csv", "country",
                       "child_mortality_0_5_year_olds_dying_per_1000_born", codigos),
        leer_gapminder(f / "esperanza-mujeres-gapminder.csv", "geo", "life_expectancy_female", codigos),
        leer_gapminder(f / "esperanza-hombres-gapminder.csv", "geo", "life_expectancy_male", codigos),
    )
    comprobar_colera(f / "colera-gran-bretana-owid.csv", _filas(DATOS / "epidemias.csv"))
    _escribir("esperanza_vida", filas)
    print(f"esperanza_vida.csv: {len(filas)} filas, {len({fila[0] for fila in filas})} países.")
    londres = construir_londres(f / "muertes-londres-owid.csv")
    _escribir("londres_muertes", londres)
    print(f"londres_muertes.csv: {len(londres)} años.")
    paises = construir_paises(args.paises)
    _escribir("paises", paises)
    print(f"paises.csv: {len(paises)} países.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
