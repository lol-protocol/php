#!/usr/bin/env python3
"""Mide los recursos de cada lengua y deriva de ahí su nivel y los IP de cada país.

Uso (desde esta carpeta; las rutas apuntan a copias locales de las fuentes, ver README.md):

    python3 preparar_lenguas.py --ud-stats ud-stats/ --ud-codigos codes_and_flags.yaml \\
        --masakhapos masakhane-pos/data/ --common-crawl languages.csv

Lee niveles_lenguas.csv (lengua y códigos ISO 639-3) y escribe en él:
    palabras_anotadas  palabras con etiqueta gramatical en Universal Dependencies (versión
                       UD_VERSION) más las de MasakhaPOS, sumando todos los códigos de la lengua
    treebanks_ud       número de treebanks de UD
    paginas_web_pct    porcentaje de páginas en la lengua en el rastreo CRAWL de Common Crawl
                       (vacío si Common Crawl no la detecta)
    nivel              generar_tabla.nivel_por_palabras(palabras_anotadas)

Y en paises_pos.csv, que dependen de esos niveles:
    indice_pos         el nivel más alto entre las lenguas del país
    indice_pos_local   el nivel de la primera lengua
    comentarios_pos    el prefijo «IP por <lengua> (mejor recurso listado).» si IP > IP local

ud-stats contiene el stats.xml de cada treebank, con el nombre del treebank
(UD_Spanish-AnCora.xml). Solo biblioteca estándar.
"""
import argparse
import csv
import re
from pathlib import Path

from generar_tabla import nivel_por_palabras, prefijo_ip

AQUI = Path(__file__).resolve().parent
UD_VERSION = "2.18"
CRAWL = "CC-MAIN-2026-39"
PREFIJO = re.compile(r"^IP por .*? \(mejor recurso listado\)\.\s*")


def leer_codigos_ud(ruta):
    """codes_and_flags.yaml de UD -> nombre de la lengua -> código ISO 639-3."""
    codigos, lengua = {}, None
    for linea in Path(ruta).read_text(encoding="utf-8").splitlines():
        if re.match(r"^\S.*:$", linea):
            lengua = linea[:-1]
        elif lengua and (m := re.match(r"^  (iso3|lcode): (\S+)$", linea)):
            # iso3 manda; lcode solo si la lengua no tiene iso3
            if m.group(1) == "iso3" or lengua not in codigos:
                codigos[lengua] = m.group(2)
    return codigos


def palabras_ud(carpeta, codigos_ud):
    """código ISO 639-3 -> [palabras, treebanks], desde los stats.xml de los treebanks."""
    datos = {}
    for archivo in sorted(Path(carpeta).glob("UD_*.xml")):
        lengua = archivo.stem[3:].split("-")[0].replace("_", " ")
        texto = archivo.read_text(encoding="utf-8")
        total = re.search(r"<total>(.*?)</total>", texto, re.S).group(1)
        cuenta = re.search(r"<words>(\d+)</words>", total) or re.search(r"<tokens>(\d+)</tokens>", total)
        par = datos.setdefault(codigos_ud[lengua], [0, 0])
        par[0] += int(cuenta.group(1))
        par[1] += 1
    return datos


def palabras_masakhapos(carpeta):
    """código ISO 639-3 -> palabras etiquetadas (líneas «palabra ETIQUETA») de MasakhaPOS."""
    datos = {}
    for archivo in sorted(Path(carpeta).glob("*/*.txt")):
        lineas = archivo.read_text(encoding="utf-8").splitlines()
        datos[archivo.parent.name] = datos.get(archivo.parent.name, 0) + sum(
            1 for linea in lineas if len(linea.split()) >= 2)
    return datos


def paginas_web(ruta):
    """código ISO 639-3 -> porcentaje de páginas del rastreo CRAWL de Common Crawl."""
    with Path(ruta).open(encoding="utf-8", newline="") as f:
        return {fila["primary_language"]: float(fila["%pages/crawl"])
                for fila in csv.DictReader(f) if fila["crawl"] == CRAWL}


def escribir(ruta, columnas, filas):
    with ruta.open("w", encoding="utf-8", newline="") as f:
        escritor = csv.DictWriter(f, fieldnames=columnas)
        escritor.writeheader()
        escritor.writerows(filas)


def main(argv=None, carpeta=AQUI):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    for nombre in ("ud-stats", "ud-codigos", "masakhapos", "common-crawl"):
        ap.add_argument(f"--{nombre}", required=True)
    args = ap.parse_args(argv)

    ud = palabras_ud(args.ud_stats, leer_codigos_ud(args.ud_codigos))
    masakha = palabras_masakhapos(args.masakhapos)
    web = paginas_web(args.common_crawl)
    if not web:
        raise SystemExit(f"{args.common_crawl} no tiene el rastreo {CRAWL}")

    ruta = carpeta / "niveles_lenguas.csv"
    with ruta.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        columnas, lenguas = lector.fieldnames, list(lector)
    niveles = {}
    for fila in lenguas:
        codigos = fila["codigos"].split()
        palabras = sum(ud.get(c, [0, 0])[0] + masakha.get(c, 0) for c in codigos)
        fila["palabras_anotadas"] = str(palabras)
        fila["treebanks_ud"] = str(sum(ud.get(c, [0, 0])[1] for c in codigos))
        presentes = [web[c] for c in codigos if c in web]
        fila["paginas_web_pct"] = f"{sum(presentes):.4f}" if presentes else ""
        fila["nivel"] = str(nivel_por_palabras(palabras))
        niveles[fila["lengua"]] = int(fila["nivel"])
    escribir(ruta, columnas, lenguas)

    ruta = carpeta / "paises_pos.csv"
    with ruta.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        columnas, paises = lector.fieldnames, list(lector)
    for fila in paises:
        lenguas_pais = [l.strip() for l in fila["idioma_principal"].split(";")]
        fila["indice_pos"] = str(max(niveles[l] for l in lenguas_pais))
        fila["indice_pos_local"] = str(niveles[lenguas_pais[0]])
        resto = PREFIJO.sub("", fila["comentarios_pos"])
        fila["comentarios_pos"] = (prefijo_ip(lenguas_pais, niveles) + " " + resto).strip()
    escribir(ruta, columnas, paises)

    print(f"{len(lenguas)} lenguas; por nivel: "
          + ", ".join(f"{n}: {sum(1 for v in niveles.values() if v == n)}" for n in range(5, 0, -1)))


if __name__ == "__main__":
    main()
