"""Modelo log-cuadrático de mortalidad (Wilmoth et al., 2012): una tabla de vida completa a partir de la
mortalidad de 0 a 5 años (5q0) y la esperanza de vida al nacer (e0).

    log m(x) = a(x) + b(x)·h + c(x)·h² + v(x)·k          con h = log(5q0)

`m(x)` es la tasa de mortalidad en cada grupo de edad (0, 1-4, 5-9 … 105-109, 110+), `a`, `b`, `c` y `v` son los
coeficientes ajustados por Wilmoth y colaboradores con las tablas de la Human Mortality Database (datos/
modelo_logcuadratico.csv, de DemoTools, CC BY-SA 3.0 IGO) y `k` es el único parámetro libre: se busca el que hace
que la tabla resultante tenga la esperanza de vida al nacer dada.

Es un **modelo**: dice cómo suele repartirse la mortalidad por edades en poblaciones con esa mortalidad infantil y
esa esperanza de vida, no lo que se midió en un país y una época. Antes de 1950 casi todo lo que entra (e0, 5q0)
es ya una estimación, y el modelo se ajustó con poblaciones que tuvieron registro de nacimientos y defunciones;
para otras épocas y lugares es una extrapolación. Por eso las filas que salen de aquí son de calidad C.

Simplificaciones respecto de la implementación de referencia (DemoTools, R): la edad media de muerte de los
menores de un año (a0) y de los de 1 a 4 (4a1) son fórmulas simples y no las tablas de Coale-Demeny. Afectan
sobre todo a las esperanzas de vida de los primeros años; sobre las de 15 años o más, menos de 0,01 años. Las
pruebas comprueban el resultado contra el ejemplo publicado de DemoTools (5q0 = 0,05 y e0 = 65, ambos sexos):
coincide a menos de 0,01 años hasta los 75.

Solo biblioteca estándar.
"""
import csv
import math
from functools import lru_cache

from comun import DATOS

# Grupos de edad del modelo: 0, 1-4, 5-9, 10-14 … 105-109, 110+ (24 en total).
EDADES = [0, 1] + list(range(5, 115, 5))
AMPLITUD = [1, 4] + [5] * 21
RAIZ_K = (-10.0, 10.0)  # intervalo en el que se busca k; e0 baja al subir k


@lru_cache(maxsize=1)
def coeficientes():
    """Los coeficientes a, b, c y v del modelo (ambos sexos), de datos/modelo_logcuadratico.csv."""
    with (DATOS / "modelo_logcuadratico.csv").open(encoding="utf-8", newline="") as f:
        filas = list(csv.DictReader(f))
    if [int(fila["edad"]) for fila in filas] != EDADES:
        raise ValueError("modelo_logcuadratico.csv no tiene los 24 grupos de edad esperados")
    return tuple(tuple(float(fila[c]) for fila in filas) for c in ("ax", "bx", "cx", "vx"))


def tabla(q5, k):
    """Tabla de vida del modelo para una mortalidad 5q0 (0 < q5 < 1) y un k. Devuelve (l, e): la proporción de
    sobrevivientes y la esperanza de vida restante en cada uno de los 24 grupos de edad (l(0) = 1)."""
    if not 0 < q5 < 1:
        raise ValueError(f"5q0 fuera de rango: {q5}")
    ax, bx, cx, vx = coeficientes()
    h = math.log(q5)
    m = [math.exp(a + b * h + c * h * h + v * k) for a, b, c, v in zip(ax, bx, cx, vx)]
    # Edad media de muerte de quien muere antes de cumplir 1 año: fórmula simple de Andreev y Kingkade.
    a0 = 0.35 if m[0] >= 0.107 else 0.0425 + 2.875 * m[0]
    q0 = m[0] / (1 + (1 - a0) * m[0])
    # 4q1 se fuerza para que cuadre con 1q0 y 5q0.
    q = [q0, 1 - (1 - q5) / (1 - q0)] + [min(1.0, 5 * mx / (1 + 2.5 * mx)) for mx in m[2:-1]] + [1.0]
    a = [a0, 1.5] + [2.5] * (len(EDADES) - 3) + [1 / m[-1]]
    l = [1.0]
    for i in range(len(EDADES) - 1):
        l.append(l[-1] * (1 - q[i]))
    personas_anio = [AMPLITUD[i] * l[i + 1] + a[i] * l[i] * q[i] for i in range(len(EDADES) - 1)] + [l[-1] / m[-1]]
    e, acumulado = [0.0] * len(EDADES), 0.0
    for i in range(len(EDADES) - 1, -1, -1):
        acumulado += personas_anio[i]
        e[i] = acumulado / l[i] if l[i] > 0 else 0.0
    return l, e


def resolver_k(q5, e0):
    """El k para el que la tabla del modelo tiene esperanza de vida al nacer `e0`, o None si no existe en
    (-10, 10): una e0 y una 5q0 que el modelo no puede cuadrar a la vez."""
    def f(k):
        return tabla(q5, k)[1][0] - e0

    bajo, alto = RAIZ_K
    if not f(bajo) > 0 > f(alto):
        return None
    for _ in range(80):
        medio = (bajo + alto) / 2
        if f(medio) > 0:
            bajo = medio
        else:
            alto = medio
    return (bajo + alto) / 2


def esperanzas_por_edad(q5_por_mil, e0, edades):
    """{edad: (esperanza restante, probabilidad de llegar a esa edad)} del modelo para esa mortalidad de 0 a 5
    años (por mil) y esa esperanza al nacer; None si el modelo no las puede cuadrar. `edades` son grupos de edad
    del modelo (5, 10, 15 … 105)."""
    q5 = q5_por_mil / 1000.0
    if not 0 < q5 < 1:
        return None
    k = resolver_k(q5, e0)
    if k is None:
        return None
    l, e = tabla(q5, k)
    return {edad: (e[EDADES.index(edad)], l[EDADES.index(edad)]) for edad in edades}
