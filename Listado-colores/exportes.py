"""Exportes de Listado-colores para tuberías de PLN: JSONL, TSV y una base SQLite.

Lo usa generar-tabla.py con los datos ya leídos y validados (un diccionario `d`, ver `datos` en main() de
generar-tabla.py). Las funciones de texto (colores_jsonl, gazetteer_tsv) son deterministas, así que
--comprobar puede compararlas byte a byte con los archivos de exportes/. La base SQLite no se guarda
en el repositorio: se construye con `python3 generar-tabla.py --sqlite ruta.db`, y --comprobar la
construye en memoria y verifica que los recuentos coincidan con los CSV.
"""
import csv
import io
import json
import sqlite3
from pathlib import Path

# Orden de las fuentes en el gazetteer y significado de cada una.
FUENTES = ["css", "lexico"]
COLUMNAS_GAZETTEER = ["forma", "idioma", "fuente", "tipo", "concepto", "hex", "rasgos"]


def _json(objeto):
    return json.dumps(objeto, ensure_ascii=False, separators=(",", ":"))


def _rgb(color):
    return [int(color[i:i + 2], 16) for i in (1, 3, 5)]


def colores_jsonl(d):
    """Una línea JSON por color CSS, con todo lo que se sabe de él en los 30 idiomas."""
    codigos = [c for c, _ in d["idiomas"]]
    lexico_por_concepto = {}
    for f in d["lexico"]:
        lexico_por_concepto.setdefault(f["concepto"], {}).setdefault(f["idioma"], []).append(
            {"forma": f["forma"], "tipo": f["tipo"], "rasgos": f["rasgos"]})
    culturales = {}
    for f in d["culturales"]:
        culturales.setdefault(f["color"], []).append(
            {"ambito": f["ambito"], "matiz": f["matiz"], "solidez": f["solidez"],
             "referencias": [i for i in f["referencias"].split(";") if i]})
    lineas = []
    for f in d["anotada"]:
        nombre = f["nombre_ingles"]
        e = d["evidencia"][nombre]
        natural = f["forma_natural_en"]
        objeto = {
            "id": nombre,
            "hex": f["hex"],
            "rgb": _rgb(f["hex"]),
            "forma_natural_en": natural,
            "forma_con_guion_en": natural.replace(" ", "-"),
            "alias": [{"id": otro, "tipo": tipo} for otro, tipo in d["alias"][nombre]],
            "nombres": {c: (natural if c == "en" else f[f"nombre_{c}"]) for c in codigos},
            "significados": {
                c: {"psicologico": f["significado_psicologico"], "mistico": f["significado_mistico"]} if c == "es"
                else dict(zip(("psicologico", "mistico"), d["significados"][(c, nombre)]))
                for c in codigos
            },
            "evidencia": {
                "psicologico": {"base": e["base_psicologico"],
                                "referencias": [i for i in e["referencias_psicologico"].split(";") if i]},
                "mistico": {"base": e["base_mistico"],
                            "referencias": [i for i in e["referencias_mistico"].split(";") if i]},
            },
            "culturales": culturales.get(nombre, []),
            "lexico": lexico_por_concepto.get(nombre, {}),
        }
        lineas.append(_json(objeto))
    return "\n".join(lineas) + "\n"


def filas_gazetteer(d):
    """Todas las formas de superficie conocidas (sin la lista completa) con su concepto y hex."""
    filas = []
    for f in d["anotada"]:
        nombre, color = f["nombre_ingles"], f["hex"]
        natural = f["forma_natural_en"]
        filas.append((nombre, "en", "css", "identificador", nombre, color, ""))
        filas.append((natural, "en", "css", "forma_natural", nombre, color, ""))
        if " " in natural:
            filas.append((natural.replace(" ", "-"), "en", "css", "forma_con_guion", nombre, color, ""))
        for codigo, _ in d["idiomas"]:
            if codigo != "en":
                filas.append((f[f"nombre_{codigo}"], codigo, "css", "nombre", nombre, color, ""))
    for f in d["lexico"]:
        filas.append((f["forma"], f["idioma"], "lexico", f["tipo"], f["concepto"], d["hex_concepto"][f["concepto"]], f["rasgos"]))
    orden_idioma = {c: n for n, (c, _) in enumerate(d["idiomas"])}
    orden_fuente = {fuente: n for n, fuente in enumerate(FUENTES)}
    # Se descartan las filas idénticas (por ejemplo, un nombre CSS que coincide con la forma natural).
    unicas = sorted(set(filas), key=lambda r: (r[0].casefold(), r[0], orden_idioma[r[1]], orden_fuente[r[2]], r[3], r[4], r[6]))
    return unicas


def gazetteer_tsv(d):
    salida = io.StringIO()
    escritor = csv.writer(salida, delimiter="\t", lineterminator="\n", quoting=csv.QUOTE_NONE, escapechar="\\")
    escritor.writerow(COLUMNAS_GAZETTEER)
    escritor.writerows(filas_gazetteer(d))
    return salida.getvalue()


ESQUEMA = """
CREATE TABLE colores (id TEXT PRIMARY KEY, hex TEXT NOT NULL, r INTEGER, g INTEGER, b INTEGER, forma_natural_en TEXT);
CREATE TABLE nombres (color_id TEXT, idioma TEXT, nombre TEXT, PRIMARY KEY (color_id, idioma));
CREATE TABLE significados (color_id TEXT, idioma TEXT, psicologico TEXT, mistico TEXT, PRIMARY KEY (color_id, idioma));
CREATE TABLE alias (color_id TEXT, alias_id TEXT, tipo TEXT, PRIMARY KEY (color_id, alias_id));
CREATE TABLE evidencia (color_id TEXT PRIMARY KEY, base_psicologico TEXT, referencias_psicologico TEXT,
                        base_mistico TEXT, referencias_mistico TEXT);
CREATE TABLE culturales (color_id TEXT, ambito TEXT, matiz TEXT, solidez TEXT, referencias TEXT);
CREATE TABLE referencias (id TEXT PRIMARY KEY, tipo TEXT, cita TEXT, url TEXT, verificacion TEXT);
CREATE TABLE conceptos (concepto TEXT PRIMARY KEY, hex TEXT NOT NULL);
CREATE TABLE lexico (concepto TEXT, idioma TEXT, forma TEXT, tipo TEXT, rasgos TEXT);
CREATE TABLE nombres_completos (nombre TEXT PRIMARY KEY, hex TEXT NOT NULL, curada INTEGER,
                                css_cercano TEXT, delta_e REAL, cercania TEXT);
CREATE INDEX nombres_por_nombre ON nombres (nombre);
CREATE INDEX lexico_por_forma ON lexico (forma, idioma);
CREATE INDEX completos_por_hex ON nombres_completos (hex);
"""


def construir_sqlite(ruta, d):
    """Construye la base en `ruta` (o en memoria con ':memory:') y devuelve la conexión abierta."""
    if ruta != ":memory:":
        Path(ruta).unlink(missing_ok=True)
    conexion = sqlite3.connect(ruta)
    conexion.executescript(ESQUEMA)
    codigos = [c for c, _ in d["idiomas"]]
    colores, nombres, significados, alias = [], [], [], []
    for f in d["anotada"]:
        n = f["nombre_ingles"]
        r, g, b = _rgb(f["hex"])
        colores.append((n, f["hex"], r, g, b, f["forma_natural_en"]))
        for c in codigos:
            nombres.append((n, c, f["forma_natural_en"] if c == "en" else f[f"nombre_{c}"]))
            psico, mistico = ((f["significado_psicologico"], f["significado_mistico"]) if c == "es"
                              else d["significados"][(c, n)])
            significados.append((n, c, psico, mistico))
        alias += [(n, otro, tipo) for otro, tipo in d["alias"][n]]
    conexion.executemany("INSERT INTO colores VALUES (?,?,?,?,?,?)", colores)
    conexion.executemany("INSERT INTO nombres VALUES (?,?,?)", nombres)
    conexion.executemany("INSERT INTO significados VALUES (?,?,?,?)", significados)
    conexion.executemany("INSERT INTO alias VALUES (?,?,?)", alias)
    conexion.executemany("INSERT INTO evidencia VALUES (?,?,?,?,?)", [
        (n, e["base_psicologico"], e["referencias_psicologico"], e["base_mistico"], e["referencias_mistico"])
        for n, e in d["evidencia"].items()])
    conexion.executemany("INSERT INTO culturales VALUES (?,?,?,?,?)", [
        (f["color"], f["ambito"], f["matiz"], f["solidez"], f["referencias"]) for f in d["culturales"]])
    conexion.executemany("INSERT INTO referencias VALUES (?,?,?,?,?)", [
        (f["id"], f["tipo"], f["cita"], f["url"], f["verificacion"]) for f in d["referencias"]])
    conexion.executemany("INSERT INTO conceptos VALUES (?,?)", sorted(d["hex_concepto"].items()))
    conexion.executemany("INSERT INTO lexico VALUES (?,?,?,?,?)", [
        (f["concepto"], f["idioma"], f["forma"], f["tipo"], f["rasgos"]) for f in d["lexico"]])
    conexion.executemany("INSERT INTO nombres_completos VALUES (?,?,?,?,?,?)", [
        (f["nombre_ingles"], f["hex"], 1 if f["curada"] == "x" else 0, css, float(f"{distancia:.2f}"), cercania)
        for f, (css, distancia, cercania) in zip(d["completa"], d["cercanias"])])
    conexion.commit()
    return conexion


def comprobar_sqlite(d):
    """Construye la base en memoria y devuelve la lista de recuentos que no coinciden con los CSV."""
    conexion = construir_sqlite(":memory:", d)
    esperados = {
        "colores": len(d["anotada"]),
        "nombres": len(d["anotada"]) * len(d["idiomas"]),
        "significados": len(d["anotada"]) * len(d["idiomas"]),
        "evidencia": len(d["anotada"]),
        "culturales": len(d["culturales"]),
        "referencias": len(d["referencias"]),
        "lexico": len(d["lexico"]),
        "nombres_completos": len(d["completa"]),
    }
    fallos = []
    for tabla, esperado in esperados.items():
        (real,) = conexion.execute(f"SELECT count(*) FROM {tabla}").fetchone()
        if real != esperado:
            fallos.append(f"{tabla}: {real} filas, se esperaban {esperado}")
    # Una consulta que cruza tablas, como la que haría quien use la base.
    (cruce,) = conexion.execute(
        "SELECT count(*) FROM nombres n JOIN significados s ON s.color_id = n.color_id AND s.idioma = n.idioma "
        "JOIN colores c ON c.id = n.color_id").fetchone()
    if cruce != esperados["nombres"]:
        fallos.append(f"el cruce nombres-significados-colores da {cruce} filas, se esperaban {esperados['nombres']}")
    conexion.close()
    return fallos
