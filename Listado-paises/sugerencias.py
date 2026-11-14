"""Sugerencias de IG, IO e IL calculadas desde los datos, para revisar el juicio propio.

Los tres índices son juicio propio (ver README.md, rúbricas). Este módulo ajusta, para cada uno,
una regresión lineal por mínimos cuadrados sobre columnas de datos de paises.csv, con los
puntajes actuales como objetivo. No reemplaza el juicio: marca los países donde el puntaje se
aparta del patrón del resto en al menos DESVIO_MINIMO puntos, que son los que conviene revisar.

Lo usa generar_tabla.py, que escribe sugerencias_indices.md y lo comprueba con --comprobar.
Solo biblioteca estándar.
"""
import math

DESVIO_MINIMO = 1.5
FALTANTES_MAXIMOS = 1  # un país con más datos faltantes que esto no entra en el ajuste ni en la lista
RIDGE = 1e-6  # regularización mínima, para que el sistema siempre tenga solución
LIBERTAD = {"no libre": 0.0, "parcial": 1.0, "libre": 2.0}


def _log_emigrantes(d):
    return math.log1p(d["emi"]) if d["emi"] is not None else None


def _libertad(d):
    return LIBERTAD.get(d["fh"])


def _escala(clave, divisor=1.0):
    return lambda d: d[clave] / divisor if d[clave] is not None else None


# índice -> (nombre, clave del puntaje en los registros, [(variable, función)])
MODELOS = [
    ("IG", "ig", [("ln(1 + emigrantes %)", _log_emigrantes), ("IDH", _escala("idh")),
                  ("internet / 100", _escala("net", 100))]),
    ("IO", "io", [("libertad (0–2)", _libertad), ("CPI / 100", _escala("cpi", 100)),
                  ("EIU / 100", _escala("eiu", 100)), ("internet / 100", _escala("net", 100)),
                  ("IDH", _escala("idh"))]),
    ("IL", "il", [("alfabetización / 100", _escala("alf", 100)), ("internet / 100", _escala("net", 100)),
                  ("IDH", _escala("idh"))]),
]


def resolver(a, b):
    """Solución de a x = b por eliminación de Gauss con pivoteo parcial (a cuadrada)."""
    n = len(a)
    m = [fila[:] + [b[i]] for i, fila in enumerate(a)]
    for col in range(n):
        pivote = max(range(col, n), key=lambda i: abs(m[i][col]))
        if abs(m[pivote][col]) < 1e-12:
            raise ValueError("sistema singular")
        m[col], m[pivote] = m[pivote], m[col]
        for i in range(col + 1, n):
            f = m[i][col] / m[col][col]
            for j in range(col, n + 1):
                m[i][j] -= f * m[col][j]
    x = [0.0] * n
    for i in range(n - 1, -1, -1):
        x[i] = (m[i][n] - sum(m[i][j] * x[j] for j in range(i + 1, n))) / m[i][i]
    return x


def minimos_cuadrados(filas, y):
    """Coeficientes [intercepto, b1, ...] que minimizan la suma de (y - X b)^2."""
    x = [[1.0, *f] for f in filas]
    k = len(x[0])
    xtx = [[sum(r[i] * r[j] for r in x) + (RIDGE if i == j else 0.0) for j in range(k)] for i in range(k)]
    xty = [sum(r[i] * v for r, v in zip(x, y)) for i in range(k)]
    return resolver(xtx, xty)


def con_datos(registros, variables):
    """Los registros a los que no les faltan más de FALTANTES_MAXIMOS variables."""
    return [d for d in registros if sum(f(d) is None for _, f in variables) <= FALTANTES_MAXIMOS]


def ajustar(registros, clave, variables):
    """(coeficientes, R², {codigo: predicción}, medias). Un dato faltante vale la media de esa
    variable entre los países que la tienen."""
    crudos = [[f(d) for _, f in variables] for d in registros]
    medias = []
    for j in range(len(variables)):
        presentes = [fila[j] for fila in crudos if fila[j] is not None]
        medias.append(sum(presentes) / len(presentes) if presentes else 0.0)
    filas = [[v if v is not None else medias[j] for j, v in enumerate(fila)] for fila in crudos]
    y = [float(d[clave]) for d in registros]
    coef = minimos_cuadrados(filas, y)
    pred = [coef[0] + sum(c * v for c, v in zip(coef[1:], fila)) for fila in filas]
    media_y = sum(y) / len(y)
    total = sum((v - media_y) ** 2 for v in y)
    r2 = 1 - sum((v - p) ** 2 for v, p in zip(y, pred)) / total if total else 1.0
    return coef, r2, {d["c"]: p for d, p in zip(registros, pred)}, medias


def desvios(registros, clave, prediccion, minimo=DESVIO_MINIMO):
    """Registros cuyo puntaje se aparta de la predicción (acotada a 1–5) en al menos minimo,
    de mayor a menor desvío."""
    marcados = []
    for d in registros:
        sugerido = min(5.0, max(1.0, prediccion[d["c"]]))
        if abs(d[clave] - sugerido) >= minimo:
            marcados.append((d, sugerido))
    return sorted(marcados, key=lambda par: (-abs(par[0][clave] - par[1]), par[0]["n"]))


def _dato(valor, decimales):
    return "—" if valor is None else f"{valor:.{decimales}f}"


def sugerencias_md(registros):
    """El informe sugerencias_indices.md."""
    partes = ["# Sugerencias de índices desde los datos", "",
              "> Generado por `generar_tabla.py` (`sugerencias.py`). No editar a mano. Para cada índice, "
              "una regresión lineal sobre los datos con los puntajes actuales como objetivo. Se listan "
              f"los países cuyo puntaje se aparta {str(DESVIO_MINIMO).replace('.', ',')} puntos o más de lo "
              "que predicen sus datos: no son errores, son los casos que conviene revisar. Un dato "
              "faltante cuenta como la media de los demás países; los países a los que les falta más "
              f"de {FALTANTES_MAXIMOS} dato del modelo quedan fuera.", ""]
    for nombre, clave, variables in MODELOS:
        usados = con_datos(registros, variables)
        coef, r2, pred, _ = ajustar(usados, clave, variables)
        marcados = desvios(usados, clave, pred)
        partes += [f"## {nombre}", "",
                   f"{len(usados)} países ({len(registros) - len(usados)} sin datos suficientes). "
                   f"R² = {r2:.2f}. Modelo: {nombre} ≈ {coef[0]:.2f} "
                   + " ".join(f"{'+' if c >= 0 else '−'} {abs(c):.2f} × {v}" for c, (v, _) in zip(coef[1:], variables))
                   + ".", ""]
        if not marcados:
            partes += ["Ningún país se aparta tanto.", ""]
            continue
        partes += [f"| País | {nombre} actual | Sugerido | Emigrantes (%) | IDH | Internet (%) | Alfabetización (%) "
                   "| Libertad | CPI | Comentario |",
                   "|---|---|---|---|---|---|---|---|---|---|"]
        for d, sugerido in marcados:
            comentario = (d["cg"] if clave in ("ig", "io") else d["cp"]).replace("|", "/")
            partes.append(f"| {d['n']} | {d[clave]} | {sugerido:.1f} | {_dato(d['emi'], 1)} | {_dato(d['idh'], 3)} "
                          f"| {_dato(d['net'], 0)} | {_dato(d['alf'], 0)} | {d['fh'] or '—'} | {_dato(d['cpi'], 0)} "
                          f"| {comentario} |")
        partes.append("")
    return "\n".join(partes)
