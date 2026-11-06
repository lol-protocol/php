#!/usr/bin/env python3
"""Estima la población histórica acumulada: cuántas personas han nacido en cada país.

    N = suma de P(t) x b(t) para cada año t, desde el primer punto de la serie hasta 2025

    P(t)  población del año t, interpolada entre los puntos de poblacion_larga.csv.
          Antes del primer punto vale 0; después del último, se mantiene el último valor.
          Entre dos puntos positivos la interpolación es geométrica (el crecimiento es
          aproximadamente exponencial); si uno de los dos es 0, es lineal.
    b(t)  tasa bruta de natalidad del año t, como fracción (40 por mil = 0,040): la de
          natalidad.csv si tiene el país y el año (desde 1800); si no, la de su época
          (natalidad_por_epoca).

Sensibilidad: N es lineal en b, así que la banda se obtiene multiplicando N por 0,9 y 1,1.

Entradas (en esta carpeta; las genera preparar_datos.py, ver README.md):
    poblacion_larga.csv   codigo,anio,poblacion      puntos de una serie larga
    natalidad.csv         codigo,anio,tasa_por_mil   nacimientos por mil habitantes (opcional)

Salidas: paises.csv (columna poblacion_historica_acumulada, en millones) y
poblacion_historica_detalle.csv (central y banda de sensibilidad).
Si falta poblacion_larga.csv, no hace nada.
"""
import bisect
from collections import Counter
from pathlib import Path

from comun import escribir_dicts, escribir_filas, filas_csv, leer_csv

AQUI = Path(__file__).resolve().parent
ANIO_FIN = 2025
ANIO_MINIMO_SERIE = 2000  # una serie que termina antes se saltea: no se extiende en silencio
# Natalidad sin dato del país, por épocas, según el Population Reference Bureau («How Many People
# Have Ever Lived on Earth?»): (año en que termina la época, tasa). Desde 1750, B_DESDE_1750.
EPOCAS = [(1, 0.080), (1650, 0.060), (1750, 0.050)]
B_DESDE_1750 = 0.040


def natalidad_por_epoca(t):
    for fin, tasa in EPOCAS:
        if t < fin:
            return tasa
    return B_DESDE_1750


def cargar_puntos(ruta):
    """codigo -> [(anio, poblacion), ...] ordenado por año. Falla si un año se repite."""
    puntos = {}
    for fila in filas_csv(ruta):
        puntos.setdefault(fila["codigo"], []).append((int(fila["anio"]), float(fila["poblacion"])))
    for codigo, lista in puntos.items():
        lista.sort()
        repetidos = sorted(a for a, n in Counter(a for a, _ in lista).items() if n > 1)
        if repetidos:
            raise ValueError(f"{codigo}: años repetidos en poblacion_larga.csv: {repetidos[:5]}")
    return puntos


def cargar_natalidad(ruta):
    """codigo -> {anio: nacimientos por persona}."""
    tasas = {}
    if not ruta.exists():
        return tasas
    for fila in filas_csv(ruta):
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
    """N central: suma de P(t) x b(t) desde el primer punto hasta ANIO_FIN.

    Recorre los tramos entre puntos en orden, sin buscar el tramo de cada año: da los mismos P(t)
    que poblacion_en, sumados en el mismo orden, así que el resultado es idéntico.
    """
    total = 0.0
    tramos = [(a0, p0, a1, p1) for (a0, p0), (a1, p1) in zip(puntos, puntos[1:])]
    for a0, p0, a1, p1 in tramos:
        geometrica = p0 > 0 and p1 > 0
        for t in range(a0, min(a1, ANIO_FIN + 1)):
            w = (t - a0) / (a1 - a0)
            p = p0 * (p1 / p0) ** w if geometrica else p0 + (p1 - p0) * w
            if p:
                total += p * (natal[t] if t in natal else natalidad_por_epoca(t))
    ultimo, p = puntos[-1]
    for t in range(ultimo, ANIO_FIN + 1):
        if p:
            total += p * (natal[t] if t in natal else natalidad_por_epoca(t))
    return total


def main(carpeta=AQUI):
    ruta_larga = carpeta / "poblacion_larga.csv"
    if not ruta_larga.exists():
        print("Falta poblacion_larga.csv: no hay datos para calcular. paises.csv no cambia.")
        return
    puntos = cargar_puntos(ruta_larga)
    natal = cargar_natalidad(carpeta / "natalidad.csv")

    columnas, filas = leer_csv(carpeta / "paises.csv")

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

    escribir_dicts(carpeta / "paises.csv", columnas, filas)
    escribir_filas(carpeta / "poblacion_historica_detalle.csv",
                   ["codigo", "central_millones", "bajo_millones", "alto_millones"],
                   [[codigo, f"{c / 1e6:.2f}", f"{bajo / 1e6:.2f}", f"{alto / 1e6:.2f}"]
                    for codigo, c, bajo, alto in detalle])

    print(f"{len(detalle)} países calculados; sin datos en la serie larga: {', '.join(sin_datos) or 'ninguno'}; "
          f"serie que termina antes de {ANIO_MINIMO_SERIE} (saltados): {', '.join(incompletas) or 'ninguno'}")


if __name__ == "__main__":
    main()
