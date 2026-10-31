#!/usr/bin/env python3
"""Baja las fuentes de Listado-paises, fijadas por commit, y comprueba su SHA-256.

Uso (desde esta carpeta):

    python3 descargar_fuentes.py --destino /tmp/fuentes            # baja y verifica
    python3 descargar_fuentes.py --destino /tmp/fuentes --rehacer  # además rehace los datos
    python3 descargar_fuentes.py --destino /tmp/fuentes --escribir-manifiesto

Cada archivo se compara con fuentes.sha256; si alguno no coincide, el script se detiene. Con
--rehacer corre preparar_datos.py, historia_poblacion.py, preparar_lenguas.py, preparar_mapa.py
y generar_tabla.py sobre lo bajado: si las fuentes son las mismas, los archivos del proyecto
deben quedar idénticos (la CI lo comprueba con git diff). --escribir-manifiesto rehace
fuentes.sha256 con lo bajado; úsalo solo al cambiar de versión una fuente.

Solo biblioteca estándar.
"""
import argparse
import hashlib
import json
import subprocess
import sys
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

AQUI = Path(__file__).resolve().parent
MANIFIESTO = AQUI / "fuentes.sha256"
RAW = "https://raw.githubusercontent.com"
CO2 = f"{RAW}/owid/co2-data/382ee6c662b0ece26e111f263b44c029afad7787"
OWID = f"{RAW}/owid/owid-datasets/6155d4ca1ea14ef30e753010a25521eeb416e8a2/datasets"
SG = f"{RAW}/open-numbers/ddf--gapminder--systema_globalis/ee190605fdec9d1c35c38e18e869a400a5818fe6/countries-etc-datapoints"
FT = f"{RAW}/open-numbers/ddf--gapminder--fasttrack/dee8978535ee563526704d2793497f8537280469/countries_etc_datapoints"
WDI = f"{RAW}/datasets/world-development-indicators/4ebec5a2273f531ba238a34dbe440394dc5a6c4b"
POBLACION_BM = f"{RAW}/datasets/population/d4b1a4a9a17e2123baf0ef119de05bceced1ab1b/data/population.csv"
UD_AUTOMATIZACION = f"{RAW}/UniversalDependencies/docs-automation/dd6111b95cfe7a5c012bf62d0ea406323f0e232c"
UD_VERSION = "2.18"
MASAKHAPOS = f"{RAW}/masakhane-io/masakhane-pos/376f4161f0425584d4bd7664122b56fa026926d3/data"
MASAKHAPOS_LENGUAS = ["bam", "bbj", "ewe", "fon", "hau", "ibo", "kin", "lug", "luo", "mos",
                      "nya", "pcm", "sna", "swa", "tsn", "twi", "wol", "xho", "yor", "zul"]
COMMON_CRAWL = f"{RAW}/commoncrawl/cc-crawl-statistics/ea9a79ea09b492cd2a0afcafbe93daf8fee45564/plots/languages.csv"
NATURAL_EARTH = (f"{RAW}/nvkelso/natural-earth-vector/ca96624a56bd078437bca8184e78163e5039ad19"
                 "/geojson/ne_50m_admin_0_countries.geojson")


def owid(nombre):
    return f"{OWID}/{urllib.parse.quote(nombre)}/{urllib.parse.quote(nombre)}.csv"


def gapminder(base, indicador, por):
    return f"{base}/ddf--datapoints--{indicador}--by--{por}--time.csv"


# archivo local -> URL
FIJAS = {
    "owid-co2-data.csv": f"{CO2}/owid-co2-data.csv",
    "maddison-2020.csv": owid("Maddison Project Database 2020 (Bolt and van Zanden (2020))"),
    "hyde-regiones.csv": owid("Global Population by Region with projections - HYDE (2016) & UN (2017)"),
    "clio-infra.csv": owid("Population (Clio-infra (2016)), with Island of Ireland (Rep. + Northern)"),
    "natalidad-gapminder.csv": gapminder(SG, "crude_birth_rate_births_per_1000_population", "geo"),
    "idh-gapminder.csv": gapminder(SG, "hdi_human_development_index", "geo"),
    "alfabetizacion-gapminder.csv": gapminder(SG, "literacy_rate_adult_total_percent_of_people_ages_15_and_above", "geo"),
    "internet-gapminder.csv": gapminder(SG, "internet_users", "geo"),
    "poblacion-gapminder.csv": gapminder(SG, "total_population_with_projections", "geo"),
    "fecundidad-gapminder.csv": gapminder(FT, "children_per_woman_total_fertility", "country"),
    "pib-ppa-gapminder.csv": gapminder(FT, "gdp_pcap", "country"),
    "emigrantes-gapminder.csv": gapminder(FT, "emigrant_n", "country"),
    "libertad-gapminder.csv": gapminder(FT, "freedstatus_fh", "country"),
    "democracia-gapminder.csv": gapminder(FT, "demox_eiu", "country"),
    "corrupcion-gapminder.csv": gapminder(FT, "corruption_perception_index_cpi", "country"),
    "pib-pc.csv": f"{WDI}/indicators/ny.gdp.pcap.cd/data.csv",
    "population.csv": POBLACION_BM,
    "codes_and_flags.yaml": f"{UD_AUTOMATIZACION}/codes_and_flags.yaml",
    "releases.json": f"{UD_AUTOMATIZACION}/valdan/releases.json",
    "languages.csv": COMMON_CRAWL,
    "ne_50m_admin_0_countries.geojson": NATURAL_EARTH,
    **{f"masakhane-pos/{lengua}/{parte}.txt": f"{MASAKHAPOS}/{lengua}/{parte}.txt"
       for lengua in MASAKHAPOS_LENGUAS for parte in ("train", "dev", "test")},
}


def bajar(url, ruta, intentos=4):
    ruta.parent.mkdir(parents=True, exist_ok=True)
    for intento in range(intentos):
        try:
            with urllib.request.urlopen(url, timeout=120) as respuesta:
                ruta.write_bytes(respuesta.read())
            return
        except OSError as error:
            if intento == intentos - 1:
                raise SystemExit(f"No se pudo bajar {url}: {error}")
            time.sleep(2 ** (intento + 1))


def bajar_todas(fuentes, destino):
    with ThreadPoolExecutor(max_workers=8) as grupo:
        list(grupo.map(lambda par: bajar(par[1], destino / par[0]), fuentes.items()))


def sha256(ruta):
    return hashlib.sha256(ruta.read_bytes()).hexdigest()


def rehacer(destino):
    d = lambda nombre: str(destino / nombre)
    pasos = [
        ["preparar_datos.py", "--co2", d("owid-co2-data.csv"), "--maddison", d("maddison-2020.csv"),
         "--hyde", d("hyde-regiones.csv"), "--clio", d("clio-infra.csv"),
         "--natalidad-gapminder", d("natalidad-gapminder.csv"), "--fecundidad-gapminder", d("fecundidad-gapminder.csv"),
         "--idh", d("idh-gapminder.csv"), "--alfabetizacion", d("alfabetizacion-gapminder.csv"),
         "--internet", d("internet-gapminder.csv"), "--emigrantes", d("emigrantes-gapminder.csv"),
         "--poblacion-gapminder", d("poblacion-gapminder.csv"), "--libertad", d("libertad-gapminder.csv"),
         "--democracia", d("democracia-gapminder.csv"), "--corrupcion", d("corrupcion-gapminder.csv"),
         "--pib-ppa", d("pib-ppa-gapminder.csv"), "--pib-pc-bm", d("pib-pc.csv"), "--poblacion-bm", d("population.csv")],
        ["historia_poblacion.py"],
        ["preparar_lenguas.py", "--ud-stats", d("ud-stats"), "--ud-codigos", d("codes_and_flags.yaml"),
         "--masakhapos", d("masakhane-pos"), "--common-crawl", d("languages.csv")],
        ["preparar_mapa.py", "--natural-earth", d("ne_50m_admin_0_countries.geojson")],
        ["generar_tabla.py"],
    ]
    for paso in pasos:
        subprocess.run([sys.executable, str(AQUI / paso[0]), *paso[1:]], cwd=AQUI, check=True)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--destino", required=True, help="carpeta donde se bajan las fuentes")
    ap.add_argument("--rehacer", action="store_true", help="rehace los datos del proyecto con lo bajado")
    ap.add_argument("--escribir-manifiesto", action="store_true", help="reescribe fuentes.sha256")
    args = ap.parse_args(argv)
    destino = Path(args.destino).resolve()

    bajar_todas(FIJAS, destino)
    # Los treebanks de UD salen de la lista de la versión, que acaba de bajarse.
    treebanks = json.loads((destino / "releases.json").read_text(encoding="utf-8"))["releases"][UD_VERSION]["treebanks"]
    bajar_todas({f"ud-stats/{t}.xml": f"{RAW}/UniversalDependencies/{t}/r{UD_VERSION}/stats.xml" for t in treebanks},
                destino)

    archivos = sorted(p for p in destino.rglob("*") if p.is_file())
    sumas = {p.relative_to(destino).as_posix(): sha256(p) for p in archivos}
    if args.escribir_manifiesto:
        MANIFIESTO.write_text("".join(f"{suma}  {ruta}\n" for ruta, suma in sorted(sumas.items())), encoding="utf-8")
        print(f"{MANIFIESTO.name}: {len(sumas)} archivos")
    else:
        esperadas = dict(reversed(linea.split("  ", 1)) for linea in MANIFIESTO.read_text(encoding="utf-8").splitlines())
        distintas = sorted(r for r, s in esperadas.items() if sumas.get(r) != s)
        if distintas:
            raise SystemExit(f"{len(distintas)} archivos no coinciden con {MANIFIESTO.name}: {', '.join(distintas[:10])}")
        print(f"{len(esperadas)} archivos verificados contra {MANIFIESTO.name}")
    if args.rehacer:
        rehacer(destino)


if __name__ == "__main__":
    main()
