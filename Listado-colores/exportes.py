"""Exportes de Listado-colores para tuberías de PLN: JSONL, TSV, patrones de spaCy, léxico y ejemplos de Universal Dependencies y una base SQLite.

Lo usa generar-tabla.py con los datos ya leídos y validados (un diccionario `d`, ver `datos` en main() de
generar-tabla.py). Las funciones de texto (colores_jsonl, gazetteer_tsv) son deterministas, así que
--comprobar puede compararlas byte a byte con los archivos de exportes/. La base SQLite no se guarda
en el repositorio: se construye con `python3 generar-tabla.py --sqlite ruta.db`, y --comprobar la
construye en memoria y verifica que los recuentos coincidan con los CSV.
"""
import csv
import io
import json
import re
import sqlite3
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from normalizacion import normalizar  # noqa: E402  (módulo de esta carpeta)

# Orden de las fuentes en el gazetteer y significado de cada una.
FUENTES = ["css", "lexico"]
# forma_normalizada va al final: quien lea las columnas por posición sigue leyendo las mismas.
# categorias, aún más al final, es la lista de categorías gramaticales (UPOS) de la forma, separadas por comas.
COLUMNAS_GAZETTEER = ["forma", "idioma", "fuente", "tipo", "concepto", "hex", "rasgos", "forma_normalizada", "categorias"]


def _json(objeto):
    return json.dumps(objeto, ensure_ascii=False, separators=(",", ":"))


def _rgb(color):
    return [int(color[i:i + 2], 16) for i in (1, 3, 5)]


def colores_jsonl(d):
    """Una línea JSON por color CSS, con todo lo que se sabe de él en los 30 idiomas."""
    codigos = [c for c, _ in d["idiomas"]]
    gramaticales = {}
    for f in d["categorias"]:
        gramaticales.setdefault(f["concepto"], {}).setdefault(f["idioma"], []).append(
            {"forma": f["forma"], "categoria": f["categoria"], "ocurrencia": f["ocurrencia"], "ejemplo": f["ejemplo"]})
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
            "categorias": gramaticales.get(nombre, {}),
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
    categorias = {}
    for f in d["categorias"]:
        categorias.setdefault((f["idioma"], f["concepto"], f["forma"]), []).append(f["categoria"])
    return [fila + (normalizar(fila[0], fila[1]), ",".join(categorias.get((fila[1], fila[4], fila[0]), []))) for fila in unicas]


def gazetteer_tsv(d):
    salida = io.StringIO()
    escritor = csv.writer(salida, delimiter="\t", lineterminator="\n", quoting=csv.QUOTE_NONE, escapechar="\\")
    escritor.writerow(COLUMNAS_GAZETTEER)
    escritor.writerows(filas_gazetteer(d))
    return salida.getvalue()


# Las únicas características de lexico-basico.csv que son de Universal Dependencies (las demás, como Terminacion o
# region, son rasgos propios del proyecto y no pasan a feats).
RASGOS_UD = {"Animacy", "Gender", "Number"}
COLUMNAS_LEXICO_UD = ["forma", "lema", "upos", "feats", "idioma", "concepto"]
ETIQUETA_SPACY = "COLOR"


def spacy_patrones(d):
    """{código: texto JSONL} con un patrón de EntityRuler de spaCy por cada forma de cada idioma, sacadas del gazetteer.

    El patrón es el texto de la forma (spaCy lo parte con el tokenizador del idioma del texto) y `id` lleva el concepto
    (el id del color CSS o del léxico); si la forma sirve para más de uno van separados por «;» y en orden alfabético.
    """
    conceptos = {}
    for forma, idioma, _, _, concepto, *_ in filas_gazetteer(d):
        conceptos.setdefault(idioma, {}).setdefault(forma, set()).add(concepto)
    return {codigo: "".join(
        _json({"label": ETIQUETA_SPACY, "pattern": forma, "id": ";".join(sorted(ids))}) + "\n"
        for forma, ids in conceptos[codigo].items()) for codigo, _ in d["idiomas"]}


def _feats_ud(rasgos):
    """Los rasgos del léxico («Gender=Fem;Number=Sing») como FEATS de CoNLL-U («Gender=Fem|Number=Sing», en orden alfabético) o «_»."""
    pares = [par for par in rasgos.split(";") if par]
    if not pares or any(par.split("=")[0] not in RASGOS_UD for par in pares):
        return "_"
    return "|".join(sorted(pares))


def lexico_ud(d):
    """Texto TSV con una fila (forma, lema, upos, feats, idioma, concepto) por cada forma con categoría gramatical.

    Salen las formas base de categorias.csv con cada una de sus categorías, las flexiones de esas palabras que trae
    lexico-basico.csv (solo como ADJ: los datos no dicen si la lectura de sustantivo se flexiona igual) y las formas
    flexionadas que salen en las frases de ejemplo (`ocurrencia`). Un nombre propio va con su mayúscula inicial.
    """
    flexiones = {}
    for f in d["lexico"]:
        if f["tipo"] == "flexion":
            flexiones.setdefault((f["idioma"], f["concepto"]), []).append((f["forma"], _feats_ud(f["rasgos"])))
    filas = set()
    for f in d["categorias"]:
        idioma, concepto, forma, categoria, ocurrencia = (f[c] for c in ("idioma", "concepto", "forma", "categoria", "ocurrencia"))
        base = ocurrencia if categoria == "PROPN" else forma
        filas.add((base, base, categoria, "_", idioma, concepto))
        if categoria != "PROPN" and ocurrencia.casefold() != forma.casefold():
            filas.add((ocurrencia.lower(), forma, categoria, "_", idioma, concepto))
        if categoria == "ADJ":
            filas.update((flexion, forma, "ADJ", feats, idioma, concepto) for flexion, feats in flexiones.get((idioma, concepto), []))
    orden_idioma = {c: n for n, (c, _) in enumerate(d["idiomas"])}
    orden_upos = {u: n for n, u in enumerate(("ADJ", "NOUN", "PROPN", "VERB"))}
    ordenadas = sorted(filas, key=lambda r: (orden_idioma[r[4]], r[0].casefold(), r[0], r[1], orden_upos[r[2]], r[3], r[5]))
    salida = io.StringIO()
    escritor = csv.writer(salida, delimiter="\t", lineterminator="\n", quoting=csv.QUOTE_NONE, escapechar="\\")
    escritor.writerow(COLUMNAS_LEXICO_UD)
    escritor.writerows(ordenadas)
    return salida.getvalue()


def ejemplos_conllu(d):
    """Las frases de ejemplo de categorias.csv en CoNLL-U, con anotada solo la palabra de color (UPOS y lema).

    Las demás palabras llevan «_» en todas las columnas, así que el archivo no pasa el validador de Universal
    Dependencies: sirve para comprobar qué categoría da un etiquetador a la palabra de color. La separación en tokens es
    simple (cada grupo de letras y dígitos, y cada signo suelto); `categorias.py` exige que la ocurrencia sea uno de ellos.
    """
    bloques = []
    for f in d["categorias"]:
        texto = f["ejemplo"]
        fichas = list(re.finditer(r"\w+|[^\w\s]", texto))
        lema = f["ocurrencia"] if f["categoria"] == "PROPN" else f["forma"]
        lineas = [f"# sent_id = {f['idioma']}-{f['concepto']}-{f['categoria']}", f"# text = {texto}"]
        for n, ficha in enumerate(fichas, start=1):
            pegada = n < len(fichas) and fichas[n].start() == ficha.end()
            marcada = ficha.group() == f["ocurrencia"]
            columnas = [str(n), ficha.group(), lema if marcada else "_", f["categoria"] if marcada else "_",
                        "_", "_", "_", "_", "_", "SpaceAfter=No" if pegada else "_"]
            lineas.append("\t".join(columnas))
        bloques.append("\n".join(lineas) + "\n")
    return "\n".join(bloques) + "\n"  # el último bloque también termina en línea vacía


ESQUEMA = """
CREATE TABLE colores (id TEXT PRIMARY KEY, hex TEXT NOT NULL, r INTEGER, g INTEGER, b INTEGER, forma_natural_en TEXT);
CREATE TABLE nombres (color_id TEXT, idioma TEXT, nombre TEXT, nombre_normalizado TEXT, PRIMARY KEY (color_id, idioma));
CREATE TABLE significados (color_id TEXT, idioma TEXT, psicologico TEXT, mistico TEXT, PRIMARY KEY (color_id, idioma));
CREATE TABLE alias (color_id TEXT, alias_id TEXT, tipo TEXT, PRIMARY KEY (color_id, alias_id));
CREATE TABLE evidencia (color_id TEXT PRIMARY KEY, base_psicologico TEXT, referencias_psicologico TEXT,
                        base_mistico TEXT, referencias_mistico TEXT);
CREATE TABLE culturales (color_id TEXT, ambito TEXT, matiz TEXT, solidez TEXT, referencias TEXT);
CREATE TABLE referencias (id TEXT PRIMARY KEY, tipo TEXT, cita TEXT, url TEXT, verificacion TEXT);
CREATE TABLE conceptos (concepto TEXT PRIMARY KEY, hex TEXT NOT NULL);
CREATE TABLE lexico (concepto TEXT, idioma TEXT, forma TEXT, tipo TEXT, rasgos TEXT, forma_normalizada TEXT);
CREATE TABLE categorias (idioma TEXT, concepto TEXT, forma TEXT, categoria TEXT, ocurrencia TEXT, ejemplo TEXT,
                         PRIMARY KEY (idioma, concepto, categoria));
CREATE TABLE nombres_completos (nombre TEXT PRIMARY KEY, hex TEXT NOT NULL, curada INTEGER,
                                css_cercano TEXT, delta_e REAL, cercania TEXT);
CREATE INDEX nombres_por_nombre ON nombres (nombre);
CREATE INDEX nombres_por_normalizado ON nombres (nombre_normalizado, idioma);
CREATE INDEX lexico_por_forma ON lexico (forma, idioma);
CREATE INDEX lexico_por_normalizada ON lexico (forma_normalizada, idioma);
CREATE INDEX categorias_por_forma ON categorias (forma, idioma);
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
            nombre = f["forma_natural_en"] if c == "en" else f[f"nombre_{c}"]
            nombres.append((n, c, nombre, normalizar(nombre, c)))
            psico, mistico = ((f["significado_psicologico"], f["significado_mistico"]) if c == "es"
                              else d["significados"][(c, n)])
            significados.append((n, c, psico, mistico))
        alias += [(n, otro, tipo) for otro, tipo in d["alias"][n]]
    conexion.executemany("INSERT INTO colores VALUES (?,?,?,?,?,?)", colores)
    conexion.executemany("INSERT INTO nombres VALUES (?,?,?,?)", nombres)
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
    conexion.executemany("INSERT INTO lexico VALUES (?,?,?,?,?,?)", [
        (f["concepto"], f["idioma"], f["forma"], f["tipo"], f["rasgos"], normalizar(f["forma"], f["idioma"]))
        for f in d["lexico"]])
    conexion.executemany("INSERT INTO categorias VALUES (?,?,?,?,?,?)", [
        tuple(f[c] for c in ("idioma", "concepto", "forma", "categoria", "ocurrencia", "ejemplo")) for f in d["categorias"]])
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
        "categorias": len(d["categorias"]),
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
