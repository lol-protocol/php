#!/usr/bin/env python3
"""Estima la población histórica acumulada: cuántas personas han nacido en cada país.

    N = suma de P(t) x b(t) para cada año t, desde el primer punto de la serie hasta 2025

    P(t)  población del año t, interpolada entre los puntos de poblacion_larga.csv.
          Antes del primer punto vale 0; después del último, se mantiene el último valor.
          Entre dos puntos positivos la interpolación es geométrica (el crecimiento es
          aproximadamente exponencial); si uno de los dos es 0, es lineal.
    b(t)  tasa bruta de natalidad del año t, como fracción (40 por mil = 0,040).
          Antes de 1950 vale B_PREINDUSTRIAL. Desde 1950 usa natalidad.csv si tiene el
          país y el año; si no, B_PREINDUSTRIAL.

Sensibilidad: N es lineal en b, así que la banda se obtiene multiplicando N por 0,9 y 1,1.

Entradas (en esta carpeta; las genera preparar_datos.py, ver README.md):
    poblacion_larga.csv   codigo,anio,poblacion      puntos de una serie larga
    natalidad.csv         codigo,anio,tasa_por_mil   nacimientos por mil habitantes (opcional)

Salidas: paises.csv (columna poblacion_historica_acumulada, en millones) y
poblacion_historica_detalle.csv (central y banda de sensibilidad).
Si falta poblacion_larga.csv, no hace nada.
"""
import bisect
import csv
from pathlib import Path

AQUI = Path(__file__).resolve().parent
ANIO_FIN = 2025
ANIO_INICIO_TASAS = 1950
ANIO_MINIMO_SERIE = 2000  # una serie que termina antes se saltea: no se extiende en silencio
B_PREINDUSTRIAL = 0.040


def cargar_puntos(ruta):
    """codigo -> [(anio, poblacion), ...] ordenado por año. Falla si un año se repite."""
    puntos = {}
    with ruta.open(encoding="utf-8", newline="") as f:
        for fila in csv.DictReader(f):
            puntos.setdefault(fila["codigo"], []).append((int(fila["anio"]), float(fila["poblacion"])))
    for codigo, lista in puntos.items():
        lista.sort()
        anios = [a for a, _ in lista]
        if len(set(anios)) != len(anios):
            repetidos = sorted({a for a in anios if anios.count(a) > 1})
            raise ValueError(f"{codigo}: años repetidos en poblacion_larga.csv: {repetidos[:5]}")
    return puntos


def cargar_natalidad(ruta):
    """codigo -> {anio: nacimientos por persona}."""
    tasas = {}
    if not ruta.exists():
        return tasas
    with ruta.open(encoding="utf-8", newline="") as f:
        for fila in csv.DictReader(f):
            tasas.setdefault(fila["codigo"], {})[int(fila["anio"])] = float(fila["tasa_por_mil"]) / 1000
    return tasas


def poblacion_en(anios, pobs, t):
    """P(t) según la regla de la cabecera del módulo."""
    if t < anios[0]:
        return 0.0
    if t >= anios[-1]:
        return pobs[-1]
    i = bisect.bisect_right(anios, t) - 1
    a0, a1, p0, p1 = anios[i], anios[i + 1], pobs[i], pobs[i + 1]
    w = (t - a0) / (a1 - a0)
    if p0 > 0 and p1 > 0:
        return p0 * (p1 / p0) ** w
    return p0 + (p1 - p0) * w


def nacimientos(puntos, natal):
    """N central: suma de P(t) x b(t) desde el primer punto hasta ANIO_FIN."""
    anios = [a for a, _ in puntos]
    pobs = [p for _, p in puntos]
    total = 0.0
    for t in range(anios[0], ANIO_FIN + 1):
        p = poblacion_en(anios, pobs, t)
        if p:
            b = natal.get(t, B_PREINDUSTRIAL) if t >= ANIO_INICIO_TASAS else B_PREINDUSTRIAL
            total += p * b
    return total


def main(carpeta=AQUI):
    ruta_larga = carpeta / "poblacion_larga.csv"
    if not ruta_larga.exists():
        print("Falta poblacion_larga.csv: no hay datos para calcular. paises.csv no cambia.")
        return
    puntos = cargar_puntos(ruta_larga)
    natal = cargar_natalidad(carpeta / "natalidad.csv")

    with (carpeta / "paises.csv").open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        columnas = lector.fieldnames
        filas = list(lector)

    detalle, sin_datos, incompletas = [], [], []
    for fila in filas:
        codigo = fila["codigo"]
        # Un país sin serie válida queda vacío: no se arrastra el valor de un cálculo anterior.
        if codigo not in puntos:
            sin_datos.append(codigo)
            fila["poblacion_historica_acumulada"] = ""
            continue
        if puntos[codigo][-1][0] < ANIO_MINIMO_SERIE:
            incompletas.append(codigo)
            fila["poblacion_historica_acumulada"] = ""
            continue
        central = nacimientos(puntos[codigo], natal.get(codigo, {}))
        fila["poblacion_historica_acumulada"] = f"{central / 1e6:.2f}"
        detalle.append((codigo, central, central * 0.9, central * 1.1))

    with (carpeta / "paises.csv").open("w", encoding="utf-8", newline="") as f:
        escritor = csv.DictWriter(f, fieldnames=columnas)
        escritor.writeheader()
        escritor.writerows(filas)

    with (carpeta / "poblacion_historica_detalle.csv").open("w", encoding="utf-8", newline="") as f:
        escritor = csv.writer(f)
        escritor.writerow(["codigo", "central_millones", "bajo_millones", "alto_millones"])
        for codigo, c, bajo, alto in detalle:
            escritor.writerow([codigo, f"{c / 1e6:.2f}", f"{bajo / 1e6:.2f}", f"{alto / 1e6:.2f}"])

    print(f"{len(detalle)} países calculados; sin datos en la serie larga: {', '.join(sin_datos) or 'ninguno'}; "
          f"serie que termina antes de {ANIO_MINIMO_SERIE} (saltados): {', '.join(incompletas) or 'ninguno'}")


if __name__ == "__main__":
    main()
