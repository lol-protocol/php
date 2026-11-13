#!/usr/bin/env python3
"""Prepara los datos a partir de fuentes publicadas en GitHub (OWID, Gapminder y Banco Mundial).

Uso (desde esta carpeta; las rutas apuntan a copias locales de las fuentes, ver README.md):

    python3 preparar_datos.py --co2 owid-co2-data.csv --maddison maddison-2020.csv \\
        --hyde hyde-regiones.csv --clio clio-infra.csv --natalidad-gapminder natalidad-gapminder.csv \\
        --fecundidad-gapminder fecundidad-gapminder.csv --idh idh-gapminder.csv \\
        --alfabetizacion alfabetizacion-gapminder.csv --internet internet-gapminder.csv \\
        --emigrantes emigrantes-gapminder.csv --poblacion-gapminder poblacion-gapminder.csv \\
        --libertad libertad-gapminder.csv --democracia democracia-gapminder.csv \\
        --corrupcion corrupcion-gapminder.csv \\
        --pib-ppa pib-ppa-gapminder.csv --pib-pc-bm pib-pc.csv --poblacion-bm population.csv

Escribe:
    poblacion_larga.csv  serie larga de población por país (codigo, anio, poblacion)
    natalidad.csv        natalidad por país desde 1800 (codigo, anio, tasa_por_mil)
    datos_detalle.csv    año y fuente de cada dato de paises.csv (codigo, columna, anio, fuente)
    paises.csv           población 2024, IDH 2023, PIB per cápita nominal y PPA de 2023,
                         alfabetización de adultos, usuarios de internet, emigrantes,
                         libertad (Freedom House), democracia (EIU) y corrupción (CPI)

Serie larga de cada país. Cada fuente solo cubre los años anteriores al primero de las de arriba:
    OWID histórica    población por país de Our World in Data (HYDE 2023, Gapminder y ONU), desde
                      el 10 000 a. C. en la mayoría, hasta 2023; la completa co2-data (2024).
    Maddison 2020     puntos anteriores (años 1, 1000, 1500...), si los hay.
    Clio-Infra        puntos cada 50 años desde 1500, empalmados (ver empalmar).
    HYDE              la serie regional multiplicada por la cuota del país en su región en el
                      primer año conocido. Supone que esa cuota fue constante hacia atrás.
Con la serie de OWID, Maddison, Clio-Infra y HYDE solo entran en los países cuya serie empieza
después del 10 000 a. C. Si empieza antes de 1800 con menos de POBLACION_POBLAMIENTO personas,
ese primer año es el poblamiento (islas del Caribe, Mauricio, Reunión, Tonga...; ver
empieza_poblado) y no se le agrega nada antes: la cuota de la región inventaría gente donde no
la había. Si empieza en 1800 o después (micro-estados y territorios sin dato propio antes), o
antes con mucha gente (Sudán del Sur, 859 000 en 1600), se completa hacia atrás, salvo los de
SIN_GENTE_ANTES.
Un país que OWID no tiene usa la población del Banco Mundial desde 1960 (Puerto Rico, Gibraltar,
Isla de Man) y, si tampoco está ahí, la de Gapminder hasta ANIO_MAXIMO_GAPMINDER (Jersey, Reunión);
esta última solo entra en la serie larga, no en la población actual.

Natalidad (ver natalidad_pais): la tasa bruta de Gapminder (1800-2015), la del Banco Mundial
después (SP.DYN.CBRT.IN, hasta 2024) y, hasta ANIO_FIN, la última tasa escalada por la fecundidad
de Gapminder. Un país sin tasa usa FACTOR_NATALIDAD x
fecundidad; uno sin ninguna de las dos no entra en natalidad.csv (historia_poblacion usa la de
su época).

PIB per cápita nominal: el indicador del Banco Mundial (NY.GDP.PCAP.CD), del último año entre
ANIO_PIB_NOMINAL_MINIMO y ANIO_PIB_NOMINAL con dato.
PIB per cápita PPA: el de Gapminder de ANIO_PIB_PPA, en dólares internacionales de 2021 (basado
en el Banco Mundial).
IDH: el del PNUD de ANIO_IDH, tal como lo publica Gapminder.
Libertad (estatus de Freedom House), democracia (índice de la EIU, 0-100) y corrupción (índice de
percepción de Transparencia Internacional, 0-100, 100 = sin corrupción), vía Gapminder: el último
dato de cada país desde ANIO_MINIMO_GOBERNANZA.
Alfabetización de adultos (UNESCO) y usuarios de internet (Banco Mundial), también vía Gapminder:
el último dato de cada país desde ANIO_MINIMO_ALFABETIZACION y ANIO_MINIMO_INTERNET.
Emigrantes: los de ANIO_EMIGRANTES (ONU, vía Gapminder), en porcentaje de la población residente.

Solo biblioteca estándar.
"""
import argparse
import json
from pathlib import Path

from comun import (ANIO_EMIGRANTES, ANIO_IDH, ANIO_PIB_NOMINAL, ANIO_PIB_NOMINAL_MINIMO, ANIO_PIB_PPA,
                   ANIO_POBLACION, escribir_dicts, escribir_filas, filas_csv, leer_csv)
from historia_poblacion import ANIO_FIN, poblacion_en

AQUI = Path(__file__).resolve().parent
ANIO_MINIMO_ALFABETIZACION = 2010  # la UNESCO no publica todos los años, y casi nunca en países ricos
ANIO_MINIMO_INTERNET = 2020
ANIO_MINIMO_GOBERNANZA = 2019  # libertad, democracia y corrupción
LIBERTAD = {"F": "libre", "PF": "parcial", "NF": "no libre"}  # estatus de Freedom House
ANIO_MAXIMO_GAPMINDER = 2015  # después, la población de Gapminder es proyección
FACTOR_NATALIDAD = 7.0  # tasa bruta por mil ~ 7 x fecundidad total, para países sin tasa
SUDAMERICA = {"AR", "BO", "BR", "CL", "CO", "EC", "GY", "PY", "PE", "SR", "UY", "VE"}
POBLACION_POBLAMIENTO = 100_000  # ver empieza_poblado
# Deshabitados hasta poco antes del primer año de OWID, aunque la serie empiece en 1800 (Seychelles, 1770).
SIN_GENTE_ANTES = {"SC"}
EMPALME_MAXIMO = 1.5  # Clio-Infra se descarta si difiere más que esto (o su inversa) en el empalme
REGION_HYDE = {"África": "Africa", "Asia": "Asia", "Europa": "Europe", "Oceanía": "Oceania"}
# Las mismas regiones en co2-data, que llega hasta ANIO_POBLACION (HYDE, hasta 2015).
# Denevan (1992), The Native Population of the Americas in 1492, 2.ª ed.: millones en 1492 por
# región. HYDE da un total parecido (unos 60 M en 1500) pero lo reparte mal: México y Brasil
# muy por encima, los Andes muy por debajo. Ver ajustar_america.
DENEVAN = {
    "Norteamérica": (3.79, {"US", "CA", "GL"}),
    "México": (17.174, {"MX"}),
    "Centroamérica": (5.625, {"BZ", "GT", "SV", "HN", "NI", "CR", "PA"}),
    # El Caribe queda como en HYDE: Denevan da 3 M, que es el factor más alto (x3,6) y el más
    # discutido (otros autores dan entre 0,5 y 1 M).
    "Andes": (15.696, {"CO", "EC", "PE", "BO", "CL"}),
    "Sudamérica baja": (8.619, {"BR", "VE", "GY", "SR", "PY", "UY", "AR"}),
}
ANIO_CONTACTO = 1500  # el punto de HYDE más cercano a 1492
REGION_OWID = {"North and Central America": "North America"}
# Nombres que la fuente usa distinto de co2-data.
ALIAS = {"Czech Republic": "CZE", "Macedonia": "MKD", "Swaziland": "SWZ", "Timor": "TLS",
         "Puerto Rico": "PRI", "Kosovo": "OWID_KOS"}


def leer(ruta):
    """@deprecated desde noviembre de 2026: usa comun.filas_csv, que comparten todos los scripts."""
    return filas_csv(ruta)


def region_hyde(fila):
    if fila["region"] == "América":
        return "South America" if fila["codigo"] in SUDAMERICA else "North and Central America"
    return REGION_HYDE[fila["region"]]


def con_cuota(anio0, pob0, region, incluir):
    """Los puntos de la región cuyo año cumple incluir(año), por la cuota del país en anio0."""
    anios, pobs = [a for a, _ in region], [p for _, p in region]
    referencia = poblacion_en(anios, pobs, anio0)
    if referencia <= 0:
        raise SystemExit(f"La región no tiene población en {anio0}: no hay cuota con la que proyectar")
    return [(a, pob0 / referencia * p) for a, p in region if incluir(a)]


def empieza_poblado(historica):
    """True si la serie de OWID (pares año, población) empieza con el poblamiento del país.

    OWID (HYDE) arranca en el 10 000 a. C. salvo donde no había nadie: una serie que empieza
    antes de 1800 con poca gente marca la llegada de los primeros pobladores.
    """
    if not historica:
        return False
    anio0, pob0 = historica[0]
    return -10000 < anio0 < 1800 and pob0 < POBLACION_POBLAMIENTO


def ajustar_america(series):
    """Escala los puntos de América hasta ANIO_CONTACTO para que cada región sume lo de DENEVAN.

    El factor de cada región es Denevan / suma de sus países en ANIO_CONTACTO, y se aplica igual a
    todos los años anteriores: conserva la forma de cada serie y cambia solo su nivel. Desde el
    punto siguiente (1600 en HYDE, ya después del derrumbe) la serie queda como estaba, y entre
    los dos la interpolación une ambos niveles. Devuelve {region: factor}.
    """
    factores = {}
    for region, (millones, codigos) in DENEVAN.items():
        presentes = [c for c in codigos if c in series]
        if not presentes:
            continue
        suma = sum(dict(series[c]).get(ANIO_CONTACTO, 0) for c in presentes)
        if not suma:
            raise SystemExit(f"{region}: sin población en {ANIO_CONTACTO} con la que ajustar a Denevan")
        factor = factores[region] = millones * 1e6 / suma
        for c in presentes:
            series[c] = [(a, p * factor if a <= ANIO_CONTACTO else p) for a, p in series[c]]
    return factores


def retroproyectar(conocidos, region):
    """Puntos anteriores al primer año conocido: región HYDE x cuota del país en ese año."""
    anio0, pob0 = conocidos[0]
    return con_cuota(anio0, pob0, region, lambda a: a < anio0)


def extender(conocidos, region, hasta=ANIO_POBLACION):
    """Puntos posteriores al último año conocido, hasta `hasta`: región x cuota del país en ese año.

    Así una serie que termina antes (Jersey en 2008, Reunión en 2013) sigue la evolución de su
    región en vez de quedar congelada en su último valor.
    """
    anio1, pob1 = conocidos[-1]
    return con_cuota(anio1, pob1, region, lambda a: anio1 < a <= hasta)


def codigo_bm(clave):
    """Código del Banco Mundial a partir del de OWID: igual salvo Kosovo."""
    return "XKX" if clave == "OWID_KOS" else clave


def leer_bm(ruta):
    """Archivo del Banco Mundial (Country Code, Year, Value) -> codigo -> {anio: valor}."""
    datos = {}
    for f in filas_csv(ruta):
        if f["Value"]:
            datos.setdefault(f["Country Code"], {})[int(f["Year"])] = float(f["Value"])
    return datos


def leer_gapminder(ruta, convertir=float):
    """Archivo DDF de Gapminder (geo o country, time, valor) -> código ISO alfa-3 -> {anio: valor}."""
    datos = {}
    for f in filas_csv(ruta):
        geo = f.get("geo") or f["country"]
        (columna,) = set(f) - {"geo", "country", "time"}
        if f[columna]:
            datos.setdefault(geo.upper(), {})[int(f["time"])] = convertir(f[columna])
    return datos


def leer_bm_api(ruta):
    """JSON de la API del Banco Mundial (una página con todo) -> código ISO alfa-3 -> {anio: valor}."""
    datos = {}
    for f in json.loads(Path(ruta).read_text(encoding="utf-8"))[1]:
        if f["value"] is not None and f["countryiso3code"]:
            datos.setdefault(f["countryiso3code"], {})[int(f["date"])] = float(f["value"])
    return datos


def natalidad_pais(tasas, fecundidad, bm=None):
    """{anio: nacimientos por mil} de un país, hasta ANIO_FIN.

    tasas son las de Gapminder; bm, las del Banco Mundial, que mandan después de
    ANIO_MAXIMO_GAPMINDER; los años sin dato entre dos tasas se interpolan. Antes de la primera tasa, FACTOR_NATALIDAD x fecundidad (los países
    que Gapminder no tiene). Después de la última, la última escalada por la fecundidad
    (tasa(t) = tasa(u) x TFR(t) / TFR(u)), o constante si falta la fecundidad.
    Sin tasas: FACTOR_NATALIDAD x fecundidad. Sin ninguna de las dos: vacío.
    """
    tasas = {**tasas, **{a: v for a, v in (bm or {}).items() if a > ANIO_MAXIMO_GAPMINDER}}
    if not tasas:
        return {a: FACTOR_NATALIDAD * v for a, v in fecundidad.items() if a <= ANIO_FIN}
    primero = min(tasas)
    serie = {a: FACTOR_NATALIDAD * v for a, v in fecundidad.items() if a < primero}
    serie.update({a: v for a, v in tasas.items() if a <= ANIO_FIN})
    conocidos = sorted(tasas.items())
    for (a0, v0), (a1, v1) in zip(conocidos, conocidos[1:]):  # huecos: interpolación lineal
        for anio in range(a0 + 1, min(a1, ANIO_FIN + 1)):
            serie[anio] = v0 + (v1 - v0) * (anio - a0) / (a1 - a0)
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
    """Millones con tres cifras significativas como mínimo: 1450.9, 67.50, 4.00, 0.185, 0.034.

    Por debajo de un millón van tres decimales (con dos, 185 482 personas salían como 0,19: un 2 % de
    error); con un decimal o dos por encima, el error no pasa de un 0,5 %.
    """
    m = personas / 1e6
    return f"{m:.1f}" if m >= 10 else f"{m:.2f}" if m >= 1 else f"{m:.3f}"


def main(argv=None, carpeta=AQUI):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    for nombre in ("co2", "maddison", "hyde", "clio", "natalidad-gapminder", "fecundidad-gapminder", "idh",
                   "alfabetizacion", "internet", "emigrantes", "poblacion-gapminder", "poblacion-owid", "libertad", "democracia", "corrupcion",
                   "pib-ppa", "pib-pc-bm", "poblacion-bm", "natalidad-bm"):
        ap.add_argument(f"--{nombre}", required=True)
    args = ap.parse_args(argv)

    columnas, paises = leer_csv(carpeta / "paises.csv")
    owid = {f["codigo"]: f["codigo_owid"] for f in filas_csv(carpeta / "codigos_iso.csv")}

    # co2-data: población hasta 2024.
    poblacion, regiones = {}, {}
    nombre_a_codigo = dict(ALIAS)
    for f in filas_csv(args.co2):
        clave = f["iso_code"] or ("OWID_KOS" if f["country"] == "Kosovo" else "")
        if not clave:
            if f["population"] and f["country"] in set(REGION_HYDE.values()) | {"North America", "South America"}:
                regiones.setdefault(f["country"], []).append((int(f["year"]), float(f["population"])))
            continue
        nombre_a_codigo.setdefault(f["country"], clave)
        if f["population"]:
            poblacion.setdefault(clave, {})[int(f["year"])] = float(f["population"])

    maddison = {}
    for f in filas_csv(args.maddison):
        clave = nombre_a_codigo.get(f["Entity"])
        if clave and f["Population"]:
            maddison.setdefault(clave, {})[int(f["Year"])] = float(f["Population"])

    clio = {}
    for f in filas_csv(args.clio):
        clave = nombre_a_codigo.get(f["Entity"])
        valor = next(v for k, v in f.items() if k.startswith("Population"))
        if clave and valor:
            clio.setdefault(clave, []).append((int(f["Year"]), float(valor)))

    columna_hyde = "Global Population by Region (HYDE (2016) and UN (2017))"
    hyde = {}
    for f in filas_csv(args.hyde):
        if f[columna_hyde]:
            hyde.setdefault(f["Entity"], []).append((int(f["Year"]), float(f[columna_hyde])))
    for serie in hyde.values():
        serie.sort()

    tasas = leer_gapminder(args.natalidad_gapminder)
    natalidad_bm = leer_bm_api(args.natalidad_bm)
    fecundidad = leer_gapminder(args.fecundidad_gapminder)
    idh = leer_gapminder(args.idh)
    alfabetizacion = leer_gapminder(args.alfabetizacion)
    internet = leer_gapminder(args.internet)
    pib_ppa = leer_gapminder(args.pib_ppa)
    emigrantes = leer_gapminder(args.emigrantes)
    gobernanza = (("libertad_fh", leer_gapminder(args.libertad, lambda v: LIBERTAD[v])),
                  ("democracia_eiu", leer_gapminder(args.democracia)),
                  ("corrupcion_cpi", leer_gapminder(args.corrupcion)))

    pib_pc_bm, poblacion_bm = leer_bm(args.pib_pc_bm), leer_bm(args.poblacion_bm)
    # Sin población de OWID, la del Banco Mundial hasta el mismo año. Sin esa, la de Gapminder hasta
    # ANIO_MAXIMO_GAPMINDER, que solo sirve para la serie larga (no tiene ANIO_POBLACION).
    poblacion_gapminder = leer_gapminder(args.poblacion_gapminder)
    # Serie histórica por país de Our World in Data (HYDE 2023, Gapminder y ONU), desde el
    # 10 000 a. C. en la mayoría. Es la base de la serie larga.
    historica = {}
    for f in filas_csv(args.poblacion_owid):
        if f["population_historical"]:
            historica.setdefault(f["code"], {})[int(f["year"])] = float(f["population_historical"])
    con_poblacion_bm, con_poblacion_gapminder = [], []
    for clave in owid.values():
        if clave not in poblacion and codigo_bm(clave) in poblacion_bm:
            poblacion[clave] = {a: p for a, p in poblacion_bm[codigo_bm(clave)].items()
                                if a <= ANIO_POBLACION}
            con_poblacion_bm.append(clave)
        elif clave not in poblacion and clave in poblacion_gapminder:
            poblacion[clave] = {a: p for a, p in poblacion_gapminder[clave].items()
                                if a <= ANIO_MAXIMO_GAPMINDER}
            con_poblacion_gapminder.append(clave)

    con_clio, clio_descartada, extendidas, poblados = [], [], [], []
    series = {}  # codigo -> [(anio, poblacion)]
    filas_larga, filas_natalidad, sin_serie, sin_pob, sin_pib, sin_natalidad = [], [], [], [], [], []
    sin_idh, sin_nominal, nominal_anterior, sin_alfabetizacion, sin_internet = [], [], [], [], []
    sin_emigrantes, sin_gobernanza = [], {columna: [] for columna, _ in gobernanza}
    detalle = []  # (codigo, columna, anio, fuente) de cada dato con valor
    fuente_gobernanza = {"libertad_fh": "Freedom House vía Gapminder", "democracia_eiu": "EIU vía Gapminder",
                         "corrupcion_cpi": "Transparencia Internacional vía Gapminder"}
    for fila in paises:
        codigo, clave = fila["codigo"], owid[fila["codigo"]]

        def anotar(columna, anio, fuente):
            if fila[columna]:
                detalle.append((codigo, columna, anio, fuente))
        modernos = sorted(historica.get(clave, {}).items())
        # La serie histórica termina un año antes que la población actual: se completa con ella.
        modernos += [(a, p) for a, p in sorted(poblacion.get(clave, {}).items())
                     if not modernos or a > modernos[-1][0]]
        poblado = empieza_poblado(modernos) or (codigo in SIN_GENTE_ANTES and bool(modernos))
        if poblado:
            poblados.append(f"{codigo} ({modernos[0][0]})")
        antiguos = [] if poblado else sorted((a, p) for a, p in maddison.get(clave, {}).items()
                                             if not modernos or a < modernos[0][0])
        conocidos = antiguos + modernos
        if conocidos and clave in clio and not poblado:
            de_clio = empalmar(sorted(clio[clave]), conocidos)
            if de_clio:
                con_clio.append(codigo)
            elif min(clio[clave])[0] < conocidos[0][0]:
                clio_descartada.append(codigo)
            conocidos = de_clio + conocidos
        if conocidos:
            region = region_hyde(fila)
            despues = []
            if conocidos[-1][0] < ANIO_POBLACION:
                despues = extender(conocidos, sorted(regiones[REGION_OWID.get(region, region)]))
            if despues:
                extendidas.append(f"{codigo} desde {conocidos[-1][0]}")
            antes = [] if poblado else retroproyectar(conocidos, hyde[region])
            serie = antes + conocidos + despues
            series[codigo] = serie
        else:
            sin_serie.append(codigo)

        actual = poblacion.get(clave, {}).get(ANIO_POBLACION)
        fila["poblacion_millones"] = formato_millones(actual) if actual is not None else ""
        if actual is None:
            sin_pob.append(codigo)
        anotar("poblacion_millones", ANIO_POBLACION,
               "Banco Mundial" if clave in con_poblacion_bm else "Our World in Data")
        emigrados = emigrantes.get(clave, {}).get(ANIO_EMIGRANTES)
        fila["emigrantes_pct"] = f"{100 * emigrados / actual:.1f}" if emigrados is not None and actual else ""
        if not fila["emigrantes_pct"]:
            sin_emigrantes.append(codigo)
        anotar("emigrantes_pct", ANIO_EMIGRANTES, "ONU vía Gapminder")
        for columna, datos in gobernanza:
            dato = ultimo_dato(datos.get(clave, {}), ANIO_MINIMO_GOBERNANZA)
            if not dato:
                fila[columna] = ""
                sin_gobernanza[columna].append(codigo)
            else:  # el estatus de libertad es texto; los índices, números
                fila[columna] = dato[1] if isinstance(dato[1], str) else f"{dato[1]:g}"
                anotar(columna, dato[0], fuente_gobernanza[columna])
        ppa = pib_ppa.get(clave, {}).get(ANIO_PIB_PPA)
        fila["pib_per_capita_ppa_usd"] = str(round(ppa)) if ppa is not None else ""
        if ppa is None:
            sin_pib.append(codigo)
        anotar("pib_per_capita_ppa_usd", ANIO_PIB_PPA, "Gapminder (Banco Mundial)")

        nominal = pib_nominal(pib_pc_bm.get(codigo_bm(clave), {}))
        if not nominal:
            sin_nominal.append(codigo)
        fila["pib_per_capita_usd"] = str(round(nominal[1])) if nominal else ""
        if nominal and nominal[0] != ANIO_PIB_NOMINAL:
            nominal_anterior.append(f"{codigo} {nominal[0]}")
        if nominal:
            anotar("pib_per_capita_usd", nominal[0], "Banco Mundial")

        valor_idh = idh.get(clave, {}).get(ANIO_IDH)
        fila["idh"] = f"{valor_idh:.3f}" if valor_idh is not None else ""
        if valor_idh is None:
            sin_idh.append(codigo)
        anotar("idh", ANIO_IDH, "PNUD vía Gapminder")

        for columna, datos, minimo, faltan, fuente in (
                ("alfabetizacion_adultos", alfabetizacion, ANIO_MINIMO_ALFABETIZACION, sin_alfabetizacion,
                 "UNESCO vía Gapminder"),
                ("usuarios_internet", internet, ANIO_MINIMO_INTERNET, sin_internet, "Banco Mundial vía Gapminder")):
            dato = ultimo_dato(datos.get(clave, {}), minimo)
            fila[columna] = f"{dato[1]:.1f}" if dato else ""
            if not dato:
                faltan.append(codigo)
            else:
                anotar(columna, dato[0], fuente)

        natal = natalidad_pais(tasas.get(clave, {}), fecundidad.get(clave, {}),
                               natalidad_bm.get(codigo_bm(clave), {}))
        filas_natalidad += [(codigo, a, f"{v:.2f}") for a, v in sorted(natal.items())]
        if clave not in tasas and codigo_bm(clave) not in natalidad_bm:
            sin_natalidad.append(f"{codigo} ({'fecundidad' if natal else 'la de su época'})")

    factores_america = ajustar_america(series)
    filas_larga = [(codigo, a, round(p)) for codigo, serie in series.items() for a, p in serie]
    escribir_filas(carpeta / "poblacion_larga.csv", ["codigo", "anio", "poblacion"], filas_larga)
    # Para que historia_poblacion pueda calcular el escenario sin el ajuste (banda de incertidumbre).
    escribir_filas(carpeta / "ajuste_america.csv", ["codigo", "hasta", "factor"],
                   sorted([c, ANIO_CONTACTO, f"{factores_america[region]:.6f}"]
                          for region, (_, codigos) in DENEVAN.items() if region in factores_america
                          for c in codigos if c in series))
    escribir_filas(carpeta / "natalidad.csv", ["codigo", "anio", "tasa_por_mil"], filas_natalidad)
    escribir_dicts(carpeta / "paises.csv", columnas, paises)
    escribir_filas(carpeta / "datos_detalle.csv", ["codigo", "columna", "anio", "fuente"], detalle)

    print(f"poblacion_larga.csv: {len(filas_larga)} filas; sin serie: {', '.join(sin_serie) or 'ninguno'}; "
          f"con puntos de Clio-Infra: {len(con_clio)}; Clio-Infra descartada en el empalme: "
          f"{', '.join(clio_descartada) or 'ninguno'}; extendidas con su región hasta {ANIO_POBLACION}: "
          f"{', '.join(extendidas) or 'ninguna'}; desde su poblamiento, sin nada antes: "
          f"{', '.join(poblados) or 'ninguno'}; América hasta {ANIO_CONTACTO} ajustada a Denevan: "
          + ", ".join(f"{r} x{f:.2f}" for r, f in factores_america.items()))
    print(f"natalidad.csv: {len(filas_natalidad)} filas; sin tasa de Gapminder (qué usan): "
          f"{', '.join(sin_natalidad) or 'ninguno'}")
    print(f"paises.csv: población {ANIO_POBLACION} sin dato (vacía): {', '.join(sin_pob) or 'ninguno'}")
    print(f"población del Banco Mundial (sin dato en OWID): {', '.join(con_poblacion_bm) or 'ninguno'}; "
          f"de Gapminder, solo para la serie larga: {', '.join(con_poblacion_gapminder) or 'ninguno'}")
    print(f"paises.csv: emigrantes {ANIO_EMIGRANTES} sin dato (vacío): {', '.join(sin_emigrantes) or 'ninguno'}")
    print("paises.csv: sin dato desde " + str(ANIO_MINIMO_GOBERNANZA) + " (vacío): "
          + "; ".join(f"{columna} {len(faltan)}" for columna, faltan in sin_gobernanza.items()))
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
