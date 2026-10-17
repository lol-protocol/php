#!/usr/bin/env python3
"""Prepara los datos a partir de fuentes publicadas en GitHub (Our World in Data y Banco Mundial).

Uso (desde esta carpeta; las rutas apuntan a copias locales de las fuentes, ver README.md):

    python3 preparar_datos.py --co2 owid-co2-data.csv --maddison maddison-2020.csv \\
        --hyde hyde-regiones.csv --fertilidad fertilidad-onu-2015.csv \\
        --pib-bm gdp.csv --poblacion-bm population.csv --idh owid-covid-latest.csv

Escribe:
    poblacion_larga.csv  serie larga de población por país (codigo, anio, poblacion)
    natalidad.csv        natalidad aproximada desde 1950 (codigo, anio, tasa_por_mil)
    paises.csv           población 2024, IDH 2019, PIB per cápita nominal 2023 y PPA 2022

Serie larga de cada país (método compuesto):
    1750-2024    población de OWID (co2-data).
    año 1-1749   puntos de Maddison 2020 (años 1, 1000, 1500, 1600, 1700...), si los hay.
    antes        la serie regional de HYDE multiplicada por la cuota del país en su región
                 en el primer año conocido. Supone que esa cuota fue constante hacia atrás.
Natalidad desde 1950: 7 x fecundidad total de la ONU (revisión 2015), interpolada entre sus
puntos quinquenales y mantenida fuera de 1955-2015. Es una aproximación de la tasa bruta.
Un país que OWID no tiene (Puerto Rico) usa la población del Banco Mundial desde 1960.

PIB per cápita nominal: PIB del Banco Mundial (NY.GDP.MKTP.CD) entre su población (SP.POP.TOTL),
del último año entre ANIO_PIB_NOMINAL_MINIMO y ANIO_PIB_NOMINAL con los dos datos.
IDH: valores de 2019 del PNUD (Informe 2020), tal como los publica OWID.

Solo biblioteca estándar.
"""
import argparse
import csv
from pathlib import Path

from historia_poblacion import poblacion_en

AQUI = Path(__file__).resolve().parent
ANIO_POBLACION = "2024"
ANIO_PIB_PPA = "2022"
ANIO_PIB_NOMINAL = 2023
ANIO_PIB_NOMINAL_MINIMO = 2021  # un dato más viejo no se usa: el país queda vacío
FACTOR_NATALIDAD = 7.0  # tasa bruta por mil ~ 7 x fecundidad total
SUDAMERICA = {"AR", "BO", "BR", "CL", "CO", "EC", "GY", "PY", "PE", "SR", "UY", "VE"}
REGION_HYDE = {"África": "Africa", "Asia": "Asia", "Europa": "Europe", "Oceanía": "Oceania"}
# Nombres que la fuente usa distinto de co2-data.
ALIAS = {"Czech Republic": "CZE", "Macedonia": "MKD", "Swaziland": "SWZ", "Timor": "TLS",
         "Puerto Rico": "PRI", "Kosovo": "OWID_KOS"}
# Países donde el cociente PIB / población del Banco Mundial no es su PIB per cápita.
PIB_NOMINAL_EXCLUIDO = {"CY": "el PIB cubre solo la zona que controla la República y la población, toda la isla"}


def leer(ruta):
    with Path(ruta).open(encoding="utf-8", newline="") as f:
        return list(csv.DictReader(f))


def region_hyde(fila):
    if fila["region"] == "América":
        return "South America" if fila["codigo"] in SUDAMERICA else "North and Central America"
    return REGION_HYDE[fila["region"]]


def retroproyectar(conocidos, region):
    """Puntos anteriores al primer año conocido: región HYDE x cuota del país en ese año."""
    anio0, pob0 = conocidos[0]
    anios, pobs = [a for a, _ in region], [p for _, p in region]
    cuota = pob0 / poblacion_en(anios, pobs, anio0)
    return [(a, cuota * p) for a, p in region if a < anio0]


def codigo_bm(clave):
    """Código del Banco Mundial a partir del de OWID: igual salvo Kosovo."""
    return "XKX" if clave == "OWID_KOS" else clave


def leer_bm(ruta):
    """Archivo del Banco Mundial (Country Code, Year, Value) -> codigo -> {anio: valor}."""
    datos = {}
    for f in leer(ruta):
        if f["Value"]:
            datos.setdefault(f["Country Code"], {})[int(f["Year"])] = float(f["Value"])
    return datos


def pib_nominal(pib, poblacion):
    """(año, PIB per cápita) del último año del rango con PIB y población, o None."""
    for anio in range(ANIO_PIB_NOMINAL, ANIO_PIB_NOMINAL_MINIMO - 1, -1):
        if anio in pib and poblacion.get(anio):
            return anio, pib[anio] / poblacion[anio]
    return None


def formato_millones(personas):
    m = personas / 1e6
    return f"{m:.1f}" if m >= 10 else f"{m:.2f}" if m >= 0.1 else f"{m:.3f}"


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    for nombre in ("co2", "maddison", "hyde", "fertilidad", "pib-bm", "poblacion-bm", "idh"):
        ap.add_argument(f"--{nombre}", required=True)
    args = ap.parse_args()

    paises = leer(AQUI / "paises.csv")
    columnas = list(paises[0].keys())
    owid = {f["codigo"]: f["codigo_owid"] for f in leer(AQUI / "codigos_iso.csv")}

    # co2-data: población 1750-2024 y PIB (Maddison 2023, $ internacionales de 2011).
    poblacion, pib = {}, {}
    nombre_a_codigo = dict(ALIAS)
    for f in leer(args.co2):
        clave = f["iso_code"] or ("OWID_KOS" if f["country"] == "Kosovo" else "")
        if not clave:
            continue
        nombre_a_codigo.setdefault(f["country"], clave)
        if f["population"]:
            poblacion.setdefault(clave, {})[int(f["year"])] = float(f["population"])
        if f["year"] == ANIO_PIB_PPA and f["gdp"] and f["population"]:
            pib[clave] = float(f["gdp"]) / float(f["population"])

    maddison = {}
    for f in leer(args.maddison):
        clave = nombre_a_codigo.get(f["Entity"])
        if clave and f["Population"]:
            maddison.setdefault(clave, {})[int(f["Year"])] = float(f["Population"])

    columna_hyde = "Global Population by Region (HYDE (2016) and UN (2017))"
    hyde = {}
    for f in leer(args.hyde):
        if f[columna_hyde]:
            hyde.setdefault(f["Entity"], []).append((int(f["Year"]), float(f[columna_hyde])))
    for serie in hyde.values():
        serie.sort()

    fecundidad = {}
    for f in leer(args.fertilidad):
        clave = nombre_a_codigo.get(f["Entity"])
        if clave and f[next(k for k in f if k.startswith("Fertility"))]:
            valor = float(f[next(k for k in f if k.startswith("Fertility"))])
            fecundidad.setdefault(clave, []).append((int(f["Year"]), valor))

    pib_bm, poblacion_bm = leer_bm(args.pib_bm), leer_bm(args.poblacion_bm)
    # Sin población de OWID, la del Banco Mundial hasta el mismo año.
    con_poblacion_bm = []
    for clave in owid.values():
        if clave not in poblacion and codigo_bm(clave) in poblacion_bm:
            poblacion[clave] = {a: p for a, p in poblacion_bm[codigo_bm(clave)].items()
                                if a <= int(ANIO_POBLACION)}
            con_poblacion_bm.append(clave)
    idh = {f["iso_code"]: f["human_development_index"] for f in leer(args.idh) if f["iso_code"]}

    filas_larga, filas_natalidad, sin_serie, sin_pob, sin_pib, sin_natalidad = [], [], [], [], [], []
    sin_idh, sin_nominal, nominal_anterior = [], [], []
    for fila in paises:
        codigo, clave = fila["codigo"], owid[fila["codigo"]]
        modernos = sorted(poblacion.get(clave, {}).items())
        antiguos = sorted((a, p) for a, p in maddison.get(clave, {}).items()
                          if not modernos or a < modernos[0][0])
        conocidos = antiguos + modernos
        if conocidos:
            serie = retroproyectar(conocidos, hyde[region_hyde(fila)]) + conocidos
            filas_larga += [(codigo, a, round(p)) for a, p in serie]
        else:
            sin_serie.append(codigo)

        actual = poblacion.get(clave, {}).get(int(ANIO_POBLACION))
        if actual:
            fila["poblacion_millones"] = formato_millones(actual)
        else:
            sin_pob.append(codigo)
        if clave in pib:
            fila["pib_per_capita_ppa_usd"] = str(round(pib[clave]))
        else:
            fila["pib_per_capita_ppa_usd"] = ""
            sin_pib.append(codigo)

        nominal = None
        if codigo in PIB_NOMINAL_EXCLUIDO:
            sin_nominal.append(f"{codigo} ({PIB_NOMINAL_EXCLUIDO[codigo]})")
        else:
            cbm = codigo_bm(clave)
            nominal = pib_nominal(pib_bm.get(cbm, {}), poblacion_bm.get(cbm, {}))
            if not nominal:
                sin_nominal.append(codigo)
        fila["pib_per_capita_usd"] = str(round(nominal[1])) if nominal else ""
        if nominal and nominal[0] != ANIO_PIB_NOMINAL:
            nominal_anterior.append(f"{codigo} {nominal[0]}")

        fila["idh"] = f"{float(idh[clave]):.3f}" if idh.get(clave) else ""
        if not fila["idh"]:
            sin_idh.append(codigo)

        puntos = sorted(fecundidad.get(clave, []))
        if puntos:
            anios, valores = [a for a, _ in puntos], [v for _, v in puntos]
            for anio in range(1950, 2026):
                t = min(max(anio, anios[0]), anios[-1])
                tfr = poblacion_en(anios, valores, t)
                filas_natalidad.append((codigo, anio, f"{FACTOR_NATALIDAD * tfr:.1f}"))
        else:
            sin_natalidad.append(codigo)

    with (AQUI / "poblacion_larga.csv").open("w", encoding="utf-8", newline="") as f:
        w = csv.writer(f)
        w.writerow(["codigo", "anio", "poblacion"])
        w.writerows(filas_larga)
    with (AQUI / "natalidad.csv").open("w", encoding="utf-8", newline="") as f:
        w = csv.writer(f)
        w.writerow(["codigo", "anio", "tasa_por_mil"])
        w.writerows(filas_natalidad)
    with (AQUI / "paises.csv").open("w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=columnas)
        w.writeheader()
        w.writerows(paises)

    print(f"poblacion_larga.csv: {len(filas_larga)} filas; sin serie: {', '.join(sin_serie) or 'ninguno'}")
    print(f"natalidad.csv: {len(filas_natalidad)} filas; sin fecundidad (usarán 0,040): "
          f"{', '.join(sin_natalidad) or 'ninguno'}")
    print(f"paises.csv: población {ANIO_POBLACION} sin dato (se mantiene la anterior): {', '.join(sin_pob) or 'ninguno'}")
    print(f"población del Banco Mundial (sin dato en OWID): {', '.join(con_poblacion_bm) or 'ninguno'}")
    print(f"paises.csv: PIB PPA {ANIO_PIB_PPA} sin dato (vacío): {len(sin_pib)} países")
    print(f"paises.csv: PIB nominal sin dato desde {ANIO_PIB_NOMINAL_MINIMO} o excluido (vacío): "
          f"{', '.join(sin_nominal) or 'ninguno'}; de un año anterior a {ANIO_PIB_NOMINAL}: "
          f"{', '.join(nominal_anterior) or 'ninguno'}")
    print(f"paises.csv: IDH sin dato (vacío): {', '.join(sin_idh) or 'ninguno'}")


if __name__ == "__main__":
    main()
