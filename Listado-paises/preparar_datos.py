#!/usr/bin/env python3
"""Prepara los datos a partir de fuentes publicadas en GitHub (OWID, Gapminder y Banco Mundial).

Uso (desde esta carpeta; las rutas apuntan a copias locales de las fuentes, ver README.md):

    python3 preparar_datos.py --co2 owid-co2-data.csv --maddison maddison-2020.csv \\
        --hyde hyde-regiones.csv --clio clio-infra.csv --natalidad-gapminder natalidad-gapminder.csv \\
        --fecundidad-gapminder fecundidad-gapminder.csv --idh idh-gapminder.csv \\
        --alfabetizacion alfabetizacion-gapminder.csv --internet internet-gapminder.csv \\
        --emigrantes emigrantes-gapminder.csv --poblacion-gapminder poblacion-gapminder.csv \\
        --pib-ppa pib-ppa-gapminder.csv --pib-pc-bm pib-pc.csv --poblacion-bm population.csv

Escribe:
    poblacion_larga.csv  serie larga de población por país (codigo, anio, poblacion)
    natalidad.csv        natalidad por país desde 1800 (codigo, anio, tasa_por_mil)
    paises.csv           población 2024, IDH 2023, PIB per cápita nominal y PPA de 2023,
                         alfabetización de adultos, usuarios de internet y emigrantes

Serie larga de cada país (método compuesto). Cada fuente solo cubre los años anteriores al
primero de las de arriba:
    OWID (co2-data)   población anual hasta 2024; empieza en 1850 en la mayoría de los países y
                      en 1750 en 35.
    Maddison 2020     puntos desde el año 1 (1, 1000, 1500, 1600, 1700...), si los hay.
    Clio-Infra        puntos cada 50 años desde 1500, empalmados (ver empalmar).
    HYDE              la serie regional multiplicada por la cuota del país en su región en el
                      primer año conocido. Supone que esa cuota fue constante hacia atrás.
Un país que OWID no tiene usa la población del Banco Mundial desde 1960 (Puerto Rico, Gibraltar,
Isla de Man) y, si tampoco está ahí, la de Gapminder hasta ANIO_MAXIMO_GAPMINDER (Jersey, Reunión);
esta última solo entra en la serie larga, no en la población actual.

Natalidad (ver natalidad_pais): la tasa bruta de Gapminder (1800-2015) y, hasta ANIO_FIN, la
última tasa escalada por la fecundidad de Gapminder. Un país sin tasa usa FACTOR_NATALIDAD x
fecundidad; uno sin ninguna de las dos no entra en natalidad.csv (historia_poblacion usa la de
su época).

PIB per cápita nominal: el indicador del Banco Mundial (NY.GDP.PCAP.CD), del último año entre
ANIO_PIB_NOMINAL_MINIMO y ANIO_PIB_NOMINAL con dato.
PIB per cápita PPA: el de Gapminder de ANIO_PIB_PPA, en dólares internacionales de 2021 (basado
en el Banco Mundial).
IDH: el del PNUD de ANIO_IDH, tal como lo publica Gapminder.
Alfabetización de adultos (UNESCO) y usuarios de internet (Banco Mundial), también vía Gapminder:
el último dato de cada país desde ANIO_MINIMO_ALFABETIZACION y ANIO_MINIMO_INTERNET.
Emigrantes: los de ANIO_EMIGRANTES (ONU, vía Gapminder), en porcentaje de la población residente.

Solo biblioteca estándar.
"""
import argparse
import csv
from pathlib import Path

from historia_poblacion import ANIO_FIN, poblacion_en

AQUI = Path(__file__).resolve().parent
ANIO_POBLACION = "2024"
ANIO_PIB_PPA = 2023
ANIO_PIB_NOMINAL = 2024
ANIO_PIB_NOMINAL_MINIMO = 2021  # un dato más viejo no se usa: el país queda vacío
ANIO_IDH = 2023
ANIO_MINIMO_ALFABETIZACION = 2010  # la UNESCO no publica todos los años, y casi nunca en países ricos
ANIO_MINIMO_INTERNET = 2020
ANIO_EMIGRANTES = 2024
ANIO_MAXIMO_GAPMINDER = 2015  # después, la población de Gapminder es proyección
FACTOR_NATALIDAD = 7.0  # tasa bruta por mil ~ 7 x fecundidad total, para países sin tasa
SUDAMERICA = {"AR", "BO", "BR", "CL", "CO", "EC", "GY", "PY", "PE", "SR", "UY", "VE"}
EMPALME_MAXIMO = 1.5  # Clio-Infra se descarta si difiere más que esto (o su inversa) en el empalme
REGION_HYDE = {"África": "Africa", "Asia": "Asia", "Europa": "Europe", "Oceanía": "Oceania"}
# Nombres que la fuente usa distinto de co2-data.
ALIAS = {"Czech Republic": "CZE", "Macedonia": "MKD", "Swaziland": "SWZ", "Timor": "TLS",
         "Puerto Rico": "PRI", "Kosovo": "OWID_KOS"}


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


def leer_gapminder(ruta):
    """Archivo DDF de Gapminder (geo o country, time, valor) -> código ISO alfa-3 -> {anio: valor}."""
    datos = {}
    for f in leer(ruta):
        geo = f.get("geo") or f["country"]
        (columna,) = set(f) - {"geo", "country", "time"}
        if f[columna]:
            datos.setdefault(geo.upper(), {})[int(f["time"])] = float(f[columna])
    return datos


def natalidad_pais(tasas, fecundidad):
    """{anio: nacimientos por mil} de un país, hasta ANIO_FIN.

    Con tasas: esas, y después de la última, la última escalada por la fecundidad
    (tasa(t) = tasa(u) x TFR(t) / TFR(u)), o constante si falta la fecundidad.
    Sin tasas: FACTOR_NATALIDAD x fecundidad. Sin ninguna de las dos: vacío.
    """
    if not tasas:
        return {a: FACTOR_NATALIDAD * v for a, v in fecundidad.items() if a <= ANIO_FIN}
    serie = {a: v for a, v in tasas.items() if a <= ANIO_FIN}
    ultimo = max(tasas)
    for anio in range(ultimo + 1, ANIO_FIN + 1):
        if fecundidad.get(ultimo) and fecundidad.get(anio):
            serie[anio] = tasas[ultimo] * fecundidad[anio] / fecundidad[ultimo]
        else:
            serie[anio] = tasas[ultimo]
    return serie


def ultimo_dato(serie, minimo):
    """(año, valor) del último año de la serie si es desde minimo; si no, None."""
    if not serie or max(serie) < minimo:
        return None
    return max(serie), serie[max(serie)]


def pib_nominal(serie):
    """(año, PIB per cápita) del último año del rango con dato, o None."""
    for anio in range(ANIO_PIB_NOMINAL, ANIO_PIB_NOMINAL_MINIMO - 1, -1):
        if anio in serie:
            return anio, serie[anio]
    return None


def empalmar(viejos, conocidos):
    """Puntos de viejos anteriores al primer año conocido, escalados para coincidir en ese año.

    El factor es población conocida / la de viejos interpolada en ese año. Si no está entre
    1 / EMPALME_MAXIMO y EMPALME_MAXIMO, las dos fuentes no cuentan lo mismo: devuelve [].
    """
    anio0, pob0 = conocidos[0]
    anteriores = [(a, p) for a, p in viejos if a < anio0]
    if not anteriores:
        return []
    referencia = poblacion_en([a for a, _ in viejos], [p for _, p in viejos], anio0)
    if referencia <= 0 or not 1 / EMPALME_MAXIMO <= pob0 / referencia <= EMPALME_MAXIMO:
        return []
    return [(a, p * pob0 / referencia) for a, p in anteriores]


def formato_millones(personas):
    m = personas / 1e6
    return f"{m:.1f}" if m >= 10 else f"{m:.2f}" if m >= 0.1 else f"{m:.3f}"


def main(argv=None, carpeta=AQUI):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    for nombre in ("co2", "maddison", "hyde", "clio", "natalidad-gapminder", "fecundidad-gapminder", "idh",
                   "alfabetizacion", "internet", "emigrantes", "poblacion-gapminder", "pib-ppa", "pib-pc-bm",
                   "poblacion-bm"):
        ap.add_argument(f"--{nombre}", required=True)
    args = ap.parse_args(argv)

    paises = leer(carpeta / "paises.csv")
    columnas = list(paises[0].keys())
    owid = {f["codigo"]: f["codigo_owid"] for f in leer(carpeta / "codigos_iso.csv")}

    # co2-data: población hasta 2024.
    poblacion = {}
    nombre_a_codigo = dict(ALIAS)
    for f in leer(args.co2):
        clave = f["iso_code"] or ("OWID_KOS" if f["country"] == "Kosovo" else "")
        if not clave:
            continue
        nombre_a_codigo.setdefault(f["country"], clave)
        if f["population"]:
            poblacion.setdefault(clave, {})[int(f["year"])] = float(f["population"])

    maddison = {}
    for f in leer(args.maddison):
        clave = nombre_a_codigo.get(f["Entity"])
        if clave and f["Population"]:
            maddison.setdefault(clave, {})[int(f["Year"])] = float(f["Population"])

    clio = {}
    for f in leer(args.clio):
        clave = nombre_a_codigo.get(f["Entity"])
        valor = next(v for k, v in f.items() if k.startswith("Population"))
        if clave and valor:
            clio.setdefault(clave, []).append((int(f["Year"]), float(valor)))

    columna_hyde = "Global Population by Region (HYDE (2016) and UN (2017))"
    hyde = {}
    for f in leer(args.hyde):
        if f[columna_hyde]:
            hyde.setdefault(f["Entity"], []).append((int(f["Year"]), float(f[columna_hyde])))
    for serie in hyde.values():
        serie.sort()

    tasas = leer_gapminder(args.natalidad_gapminder)
    fecundidad = leer_gapminder(args.fecundidad_gapminder)
    idh = leer_gapminder(args.idh)
    alfabetizacion = leer_gapminder(args.alfabetizacion)
    internet = leer_gapminder(args.internet)
    pib_ppa = leer_gapminder(args.pib_ppa)
    emigrantes = leer_gapminder(args.emigrantes)

    pib_pc_bm, poblacion_bm = leer_bm(args.pib_pc_bm), leer_bm(args.poblacion_bm)
    # Sin población de OWID, la del Banco Mundial hasta el mismo año. Sin esa, la de Gapminder hasta
    # ANIO_MAXIMO_GAPMINDER, que solo sirve para la serie larga (no tiene ANIO_POBLACION).
    poblacion_gapminder = leer_gapminder(args.poblacion_gapminder)
    con_poblacion_bm, con_poblacion_gapminder = [], []
    for clave in owid.values():
        if clave not in poblacion and codigo_bm(clave) in poblacion_bm:
            poblacion[clave] = {a: p for a, p in poblacion_bm[codigo_bm(clave)].items()
                                if a <= int(ANIO_POBLACION)}
            con_poblacion_bm.append(clave)
        elif clave not in poblacion and clave in poblacion_gapminder:
            poblacion[clave] = {a: p for a, p in poblacion_gapminder[clave].items()
                                if a <= ANIO_MAXIMO_GAPMINDER}
            con_poblacion_gapminder.append(clave)

    con_clio, clio_descartada = [], []
    filas_larga, filas_natalidad, sin_serie, sin_pob, sin_pib, sin_natalidad = [], [], [], [], [], []
    sin_idh, sin_nominal, nominal_anterior, sin_alfabetizacion, sin_internet = [], [], [], [], []
    sin_emigrantes = []
    for fila in paises:
        codigo, clave = fila["codigo"], owid[fila["codigo"]]
        modernos = sorted(poblacion.get(clave, {}).items())
        antiguos = sorted((a, p) for a, p in maddison.get(clave, {}).items()
                          if not modernos or a < modernos[0][0])
        conocidos = antiguos + modernos
        if conocidos and clave in clio:
            de_clio = empalmar(sorted(clio[clave]), conocidos)
            if de_clio:
                con_clio.append(codigo)
            elif min(clio[clave])[0] < conocidos[0][0]:
                clio_descartada.append(codigo)
            conocidos = de_clio + conocidos
        if conocidos:
            serie = retroproyectar(conocidos, hyde[region_hyde(fila)]) + conocidos
            filas_larga += [(codigo, a, round(p)) for a, p in serie]
        else:
            sin_serie.append(codigo)

        actual = poblacion.get(clave, {}).get(int(ANIO_POBLACION))
        fila["poblacion_millones"] = formato_millones(actual) if actual else ""
        if not actual:
            sin_pob.append(codigo)
        emigrados = emigrantes.get(clave, {}).get(ANIO_EMIGRANTES)
        fila["emigrantes_pct"] = f"{100 * emigrados / actual:.1f}" if emigrados and actual else ""
        if not fila["emigrantes_pct"]:
            sin_emigrantes.append(codigo)
        ppa = pib_ppa.get(clave, {}).get(ANIO_PIB_PPA)
        fila["pib_per_capita_ppa_usd"] = str(round(ppa)) if ppa else ""
        if not ppa:
            sin_pib.append(codigo)

        nominal = pib_nominal(pib_pc_bm.get(codigo_bm(clave), {}))
        if not nominal:
            sin_nominal.append(codigo)
        fila["pib_per_capita_usd"] = str(round(nominal[1])) if nominal else ""
        if nominal and nominal[0] != ANIO_PIB_NOMINAL:
            nominal_anterior.append(f"{codigo} {nominal[0]}")

        valor_idh = idh.get(clave, {}).get(ANIO_IDH)
        fila["idh"] = f"{valor_idh:.3f}" if valor_idh else ""
        if not fila["idh"]:
            sin_idh.append(codigo)

        for columna, datos, minimo, faltan in (
                ("alfabetizacion_adultos", alfabetizacion, ANIO_MINIMO_ALFABETIZACION, sin_alfabetizacion),
                ("usuarios_internet", internet, ANIO_MINIMO_INTERNET, sin_internet)):
            dato = ultimo_dato(datos.get(clave, {}), minimo)
            fila[columna] = f"{dato[1]:.1f}" if dato else ""
            if not dato:
                faltan.append(codigo)

        natal = natalidad_pais(tasas.get(clave, {}), fecundidad.get(clave, {}))
        filas_natalidad += [(codigo, a, f"{v:.2f}") for a, v in sorted(natal.items())]
        if clave not in tasas:
            sin_natalidad.append(f"{codigo} ({'fecundidad' if natal else 'la de su época'})")

    with (carpeta / "poblacion_larga.csv").open("w", encoding="utf-8", newline="") as f:
        w = csv.writer(f)
        w.writerow(["codigo", "anio", "poblacion"])
        w.writerows(filas_larga)
    with (carpeta / "natalidad.csv").open("w", encoding="utf-8", newline="") as f:
        w = csv.writer(f)
        w.writerow(["codigo", "anio", "tasa_por_mil"])
        w.writerows(filas_natalidad)
    with (carpeta / "paises.csv").open("w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=columnas)
        w.writeheader()
        w.writerows(paises)

    print(f"poblacion_larga.csv: {len(filas_larga)} filas; sin serie: {', '.join(sin_serie) or 'ninguno'}; "
          f"con puntos de Clio-Infra: {len(con_clio)}; Clio-Infra descartada en el empalme: "
          f"{', '.join(clio_descartada) or 'ninguno'}")
    print(f"natalidad.csv: {len(filas_natalidad)} filas; sin tasa de Gapminder (qué usan): "
          f"{', '.join(sin_natalidad) or 'ninguno'}")
    print(f"paises.csv: población {ANIO_POBLACION} sin dato (vacía): {', '.join(sin_pob) or 'ninguno'}")
    print(f"población del Banco Mundial (sin dato en OWID): {', '.join(con_poblacion_bm) or 'ninguno'}; "
          f"de Gapminder, solo para la serie larga: {', '.join(con_poblacion_gapminder) or 'ninguno'}")
    print(f"paises.csv: emigrantes {ANIO_EMIGRANTES} sin dato (vacío): {', '.join(sin_emigrantes) or 'ninguno'}")
    print(f"paises.csv: PIB PPA {ANIO_PIB_PPA} sin dato (vacío): {', '.join(sin_pib) or 'ninguno'}")
    print(f"paises.csv: PIB nominal sin dato desde {ANIO_PIB_NOMINAL_MINIMO} (vacío): "
          f"{', '.join(sin_nominal) or 'ninguno'}; de un año anterior a {ANIO_PIB_NOMINAL}: "
          f"{', '.join(nominal_anterior) or 'ninguno'}")
    print(f"paises.csv: IDH sin dato (vacío): {', '.join(sin_idh) or 'ninguno'}")
    print(f"paises.csv: alfabetización sin dato desde {ANIO_MINIMO_ALFABETIZACION} (vacío): "
          f"{len(sin_alfabetizacion)} países; internet sin dato desde {ANIO_MINIMO_INTERNET}: "
          f"{', '.join(sin_internet) or 'ninguno'}")


if __name__ == "__main__":
    main()
