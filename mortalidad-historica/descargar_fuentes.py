#!/usr/bin/env python3
"""Baja las fuentes de los datos externos (esperanza de vida, muertes en Londres, cólera), fijadas por commit, y comprueba su SHA-256.

  python3 descargar_fuentes.py --destino fuentes            # baja y comprueba contra fuentes.sha256
  python3 descargar_fuentes.py --destino fuentes --rehacer  # además corre preparar_datos.py

  python3 descargar_fuentes.py --destino fuentes --escribir-manifiesto
      # reescribe fuentes.sha256 con lo que bajó: solo al cambiar de fuente o de commit, y se revisa el diff

Todas las URL apuntan a un commit concreto de GitHub, así que el contenido no cambia; el manifiesto lo
comprueba igual. Solo biblioteca estándar. Las licencias están en el README («Fuentes»).
"""
import argparse
import hashlib
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
OWID = f"{RAW}/owid/owid-datasets/6155d4ca1ea14ef30e753010a25521eeb416e8a2/datasets"
SG = f"{RAW}/open-numbers/ddf--gapminder--systema_globalis/ee190605fdec9d1c35c38e18e869a400a5818fe6/countries-etc-datapoints"
FT = f"{RAW}/open-numbers/ddf--gapminder--fasttrack/dee8978535ee563526704d2793497f8537280469/countries_etc_datapoints"

_LARGA = "Long run life expectancy - Gapminder, UN"
_LONDRES = "Deaths from Smalllpox and all causes in London 1629-1902"  # sí: «Smalllpox», con tres eles
_COLERA = "UK Cholera Death over the long-term - ONS"


def _owid(nombre):
    return f"{OWID}/{urllib.parse.quote(nombre)}/{urllib.parse.quote(nombre)}.csv"


# archivo local -> URL
FUENTES = {
    "esperanza-larga-owid.csv": _owid(_LARGA),
    "muertes-londres-owid.csv": _owid(_LONDRES),
    "colera-gran-bretana-owid.csv": _owid(_COLERA),
    "mortalidad-0-5-gapminder.csv": f"{FT}/ddf--datapoints--child_mortality_0_5_year_olds_dying_per_1000_born--by--country--time.csv",
    "esperanza-mujeres-gapminder.csv": f"{SG}/ddf--datapoints--life_expectancy_female--by--geo--time.csv",
    "esperanza-hombres-gapminder.csv": f"{SG}/ddf--datapoints--life_expectancy_male--by--geo--time.csv",
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


def sha256(ruta):
    return hashlib.sha256(ruta.read_bytes()).hexdigest()


def leer_manifiesto():
    """{archivo: sha256} de fuentes.sha256 (formato de sha256sum: «hash  archivo»)."""
    if not MANIFIESTO.exists():
        return {}
    return {linea.split(None, 1)[1].strip(): linea.split()[0]
            for linea in MANIFIESTO.read_text(encoding="utf-8").splitlines() if linea.strip()}


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--destino", required=True, type=Path, help="carpeta donde guardar las fuentes")
    parser.add_argument("--rehacer", action="store_true", help="correr preparar_datos.py con lo bajado")
    parser.add_argument("--escribir-manifiesto", action="store_true", help="reescribir fuentes.sha256")
    args = parser.parse_args(argv)

    with ThreadPoolExecutor(max_workers=4) as grupo:
        list(grupo.map(lambda par: bajar(par[1], args.destino / par[0]), FUENTES.items()))
    huellas = {nombre: sha256(args.destino / nombre) for nombre in FUENTES}

    if args.escribir_manifiesto:
        MANIFIESTO.write_text("".join(f"{h}  {n}\n" for n, h in sorted(huellas.items())), encoding="utf-8")
        print(f"Escrito {MANIFIESTO.name} con {len(huellas)} fuentes.")
    else:
        esperadas = leer_manifiesto()
        malas = [n for n in FUENTES if esperadas.get(n) != huellas[n]]
        if malas:
            raise SystemExit("El SHA-256 no coincide con fuentes.sha256 en: " + ", ".join(malas))
        print(f"Las {len(huellas)} fuentes coinciden con fuentes.sha256.")

    if args.rehacer:
        subprocess.run([sys.executable, str(AQUI / "preparar_datos.py"), "--fuentes", str(args.destino)], check=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
