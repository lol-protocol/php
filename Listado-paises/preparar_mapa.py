#!/usr/bin/env python3
"""Proyecta las fronteras de Natural Earth y escribe mapa_paises.csv, que usa la vista web.

Uso (desde esta carpeta; ver README.md, «Fuentes»):

    python3 preparar_mapa.py --natural-earth ne_50m_admin_0_countries.geojson

Proyección Equal Earth (Šavrič, Patterson y Jenny, 2018), en un lienzo de ANCHO de ancho. Cada
anillo se simplifica (Douglas-Peucker, TOLERANCIA en unidades del lienzo) y se escribe como un
trazado SVG con coordenadas de un decimal. La Antártida no se dibuja.

Escribe mapa_paises.csv: codigo (vacío para los territorios que no están en la tabla), trazado,
y x, y del centro del anillo más grande (la vista dibuja ahí un punto si el país es muy chico).
Los países que Natural Earth no tiene por separado se marcan solo con un punto (PUNTOS).
Solo biblioteca estándar.
"""
import argparse
import csv
import json
import math
from pathlib import Path

AQUI = Path(__file__).resolve().parent
ANCHO = 1000
TOLERANCIA = 0.35
A1, A2, A3, A4 = 1.340264, -0.081106, 0.000893, 0.003796
M = math.sqrt(3) / 2
ESCALA = ANCHO / (2 * 2.7064)  # x máximo de Equal Earth en el ecuador, ±180°
# Natural Earth separa estas zonas; en la tabla son parte del país.
UNIR = {"Somaliland": "SO", "N. Cyprus": "CY"}
OMITIR = {"AQ", "Siachen Glacier"}
# Sin polígono propio en Natural Earth (Reunión va dentro de Francia): latitud y longitud de Gapminder.
PUNTOS = {"GI": (36.14474, -5.35257), "RE": (-20.88231, 55.4504)}


def proyectar(lon, lat):
    lam, theta = math.radians(lon), math.asin(M * math.sin(math.radians(lat)))
    t2 = theta * theta
    t6 = t2 * t2 * t2
    x = lam * math.cos(theta) / (M * (A1 + 3 * A2 * t2 + t6 * (7 * A3 + 9 * A4 * t2)))
    y = theta * (A1 + A2 * t2 + t6 * (A3 + A4 * t2))
    return ANCHO / 2 + ESCALA * x, -ESCALA * y


def simplificar(puntos, tolerancia):
    """Douglas-Peucker iterativo."""
    if len(puntos) < 3:
        return puntos
    conservar = [False] * len(puntos)
    conservar[0] = conservar[-1] = True
    pila = [(0, len(puntos) - 1)]
    while pila:
        i, j = pila.pop()
        (x1, y1), (x2, y2) = puntos[i], puntos[j]
        largo = math.hypot(x2 - x1, y2 - y1) or 1e-12
        mayor, k_mayor = 0.0, None
        for k in range(i + 1, j):
            x0, y0 = puntos[k]
            d = abs((x2 - x1) * (y1 - y0) - (x1 - x0) * (y2 - y1)) / largo
            if d > mayor:
                mayor, k_mayor = d, k
        if k_mayor is not None and mayor > tolerancia:
            conservar[k_mayor] = True
            pila += [(i, k_mayor), (k_mayor, j)]
    return [p for p, c in zip(puntos, conservar) if c]


def area(anillo):
    return abs(sum(x1 * y2 - x2 * y1 for (x1, y1), (x2, y2) in zip(anillo, anillo[1:] + anillo[:1]))) / 2


def centro(anillo):
    xs, ys = [x for x, _ in anillo], [y for _, y in anillo]
    return (min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2


def main(argv=None, carpeta=AQUI):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--natural-earth", required=True)
    args = ap.parse_args(argv)

    with (carpeta / "codigos_iso.csv").open(encoding="utf-8", newline="") as f:
        nuestros = {fila["codigo"] for fila in csv.DictReader(f)}
    datos = json.loads(Path(args.natural_earth).read_text(encoding="utf-8"))

    anillos = {}  # codigo -> lista de anillos proyectados
    for rasgo in datos["features"]:
        props, geom = rasgo["properties"], rasgo["geometry"]
        codigo = UNIR.get(props["NAME"], props["ISO_A2_EH"])
        if codigo in OMITIR or props["NAME"] in OMITIR:
            continue
        poligonos = geom["coordinates"] if geom["type"] == "MultiPolygon" else [geom["coordinates"]]
        for poligono in poligonos:
            for anillo in poligono:
                anillos.setdefault(codigo if codigo in nuestros else "", []).append(
                    [proyectar(lon, lat) for lon, lat in anillo])

    filas = []
    for codigo, lista in sorted(anillos.items()):
        mayor = max(lista, key=area)
        partes = []
        for anillo in lista:
            simple = simplificar(anillo[:-1], TOLERANCIA)
            if len(simple) >= 3 and (area(simple) >= 0.2 or anillo is mayor):
                partes.append("M" + " ".join(f"{x:.1f},{y:.1f}" for x, y in simple) + "Z")
        x, y = centro(mayor)
        filas.append([codigo, "".join(partes), f"{x:.1f}", f"{y:.1f}"])
    for codigo, (lat, lon) in PUNTOS.items():
        x, y = proyectar(lon, lat)
        filas.append([codigo, "", f"{x:.1f}", f"{y:.1f}"])

    with (carpeta / "mapa_paises.csv").open("w", encoding="utf-8", newline="") as f:
        escritor = csv.writer(f)
        escritor.writerow(["codigo", "trazado", "x", "y"])
        escritor.writerows(sorted(filas))
    faltan = sorted(nuestros - {fila[0] for fila in filas})
    print(f"mapa_paises.csv: {len(filas)} filas; países de la tabla sin mapa: {', '.join(faltan) or 'ninguno'}")


if __name__ == "__main__":
    main()
