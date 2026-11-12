#!/usr/bin/env python3
"""Valida los CSV de Listado-colores y genera la tabla de README.md y las páginas por idioma.

- colores.csv: tabla anotada, 148 colores CSS con su forma natural en inglés, nombre en 30 idiomas, hex y
  significados en español. Su tabla resumida va en el README. De los hex se sacan los alias (colores CSS
  con el mismo hex, como aqua y cyan, o gray y grey).
- significados-idiomas.csv: los significados de esos 148 colores en los otros 29 idiomas.
- lexico-basico.csv: términos de color de uso real (blanco, azul claro, granate…) en los 30 idiomas, con
  su forma base, flexiones, sinónimos y variantes.
- evidencia.csv: en qué se apoya cada significado (hay estudios, tradición, derivado) y con qué fuentes.
- significados-culturales.csv: matices por ámbito cultural (China, Japón, India…) de algunos colores.
- categorias.csv: las categorías gramaticales (ADJ, NOUN, PROPN, VERB) de las palabras de color de una sola palabra
  en inglés y español, con una frase de ejemplo; se genera categorias.md (ver categorias.py).
- revisiones.csv: el registro de qué elementos han revisado hablantes nativos y con qué resultado (empieza vacío); se
  genera revisiones.md (la cobertura por idioma); el visor para revisar se construye con --visor (ver revision.py).
- referencias.csv: las fuentes citadas por evidencia.csv y significados-culturales.csv; se genera referencias.md.
- nombres-completos.csv: lista completa de nombres con hex. Sus tres primeras columnas son la fuente;
  css_cercano, delta_e y cercania se calculan aquí (color CSS más cercano según CIEDE2000, en colorimetria.py).
- Por cada idioma se genera por-idioma/<código>.md (nombre, hex y los dos significados en ese idioma)
  y colores-por-idioma.md, que es el índice de esas páginas.

- Se generan además exportes/colores.jsonl y exportes/gazetteer.tsv (ver exportes.py) y, a petición, una
  base SQLite, ambiguedad.md (las formas que designan más de un color, ver ambiguedad.py) y exportes/manifiesto.json
  (la versión de CHANGELOG.md y las filas y sumas SHA-256 de los datos, ver manifiesto.py), y auditoria-nombres.md (los
  nombres sospechosos de la lista completa, ver auditoria.py).

Uso:
  python3 generar-tabla.py                   valida los CSV y regenera los archivos generados
  python3 generar-tabla.py --comprobar       valida y sale con error si los archivos generados no están al día
  python3 generar-tabla.py --sqlite ruta.db  valida los CSV y construye una base SQLite (no regenera nada más)
  python3 generar-tabla.py --conllu ruta.conllu  valida y escribe las frases de ejemplo en CoNLL-U (no regenera nada más)
  python3 generar-tabla.py --visor ruta.html     valida y escribe el visor de revisión, un HTML sin dependencias (no regenera nada más)
"""
import csv
import re
import sys
from pathlib import Path

CARPETA = Path(__file__).resolve().parent
sys.dont_write_bytecode = True  # no dejar __pycache__ en la carpeta al importar los módulos de al lado
sys.path.insert(0, str(CARPETA))
import ambiguedad  # noqa: E402  (módulos de esta carpeta)
import auditoria  # noqa: E402
import categorias  # noqa: E402
import cobertura  # noqa: E402
import colorimetria  # noqa: E402
import exportes  # noqa: E402
import manifiesto  # noqa: E402
from md import texto_valido  # noqa: E402
import revision  # noqa: E402

ANOTADA = CARPETA / "colores.csv"
COMPLETA = CARPETA / "nombres-completos.csv"
SIGNIFICADOS = CARPETA / "significados-idiomas.csv"
LEXICO = CARPETA / "lexico-basico.csv"
COLISIONES_REVISADAS = CARPETA / "colisiones-revisadas.csv"
REFERENCIAS = CARPETA / "referencias.csv"
EVIDENCIA = CARPETA / "evidencia.csv"
CULTURALES = CARPETA / "significados-culturales.csv"
CATEGORIAS = CARPETA / "categorias.csv"
CATEGORIAS_MD = CARPETA / "categorias.md"
COBERTURA = CARPETA / "cobertura.md"
REVISIONES = CARPETA / "revisiones.csv"
REVISIONES_MD = CARPETA / "revisiones.md"
VISOR_PLANTILLA = CARPETA / "visor-plantilla.html"
REFS_MD = CARPETA / "referencias.md"
AMBIGUEDAD = CARPETA / "ambiguedad.md"
AUDITORIA = CARPETA / "auditoria-nombres.md"
CHANGELOG = CARPETA / "CHANGELOG.md"
DIR_EXPORTES = CARPETA / "exportes"
README = CARPETA / "README.md"
INDICE = CARPETA / "colores-por-idioma.md"
DIR_IDIOMAS = CARPETA / "por-idioma"
INICIO = "<!-- tabla:inicio -->"
FIN = "<!-- tabla:fin -->"

from constantes import *  # noqa: E402,F401,F403  (re-exportado: las pruebas y los scripts lo leen como gt.IDIOMAS, etc.)
from validadores import *  # noqa: E402,F401,F403
from cercania import *  # noqa: E402,F401,F403
from paginas import *  # noqa: E402,F401,F403


def filas_de(lector, ruta):
    """Las filas de un DictReader. Sale con error si alguna tiene más o menos campos que la cabecera.

    DictReader descarta en silencio los campos que sobran (por ejemplo, tras una coma sin comillas
    dentro de un texto, que además desplaza las columnas) y rellena con None los que faltan.
    """
    filas = []
    for fila in lector:
        if None in fila or None in fila.values():
            sys.exit(f"{ruta.name} línea {lector.line_num}: debe tener {len(lector.fieldnames)} campos, como la cabecera")
        filas.append(fila)
    return filas


def leer(ruta, columnas):
    with ruta.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames != columnas:
            sys.exit(f"{ruta.name}: cabecera inesperada {lector.fieldnames}")
        return filas_de(lector, ruta)


def leer_completa():
    """Lee nombres-completos.csv con la cabecera completa o solo con las tres columnas de la fuente."""
    with COMPLETA.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames not in (COLUMNAS_FUENTE_COMPLETA, COLUMNAS_COMPLETA):
            sys.exit(f"{COMPLETA.name}: cabecera inesperada {lector.fieldnames}")
        return [{c: fila[c] for c in COLUMNAS_FUENTE_COMPLETA} for fila in filas_de(lector, COMPLETA)]


def comprobar_o_escribir(base, archivos, comprobar, sobrantes=()):
    """Compara contra el disco o escribe los archivos generados (ruta, contenido) de este script.

    Con comprobar=True no escribe nada: devuelve los nombres (relativos a `base`) que no coinciden
    con lo que hay en disco o que faltan, más los de `sobrantes` con el sufijo " (sobra)"; vacía si
    todo está al día. Con comprobar=False escribe cada archivo (creando sus carpetas) y borra los
    sobrantes, y devuelve [].
    """
    archivos = list(archivos)
    if comprobar:
        desfasados = [ruta.relative_to(base).as_posix() for ruta, contenido in archivos
                      if not ruta.exists() or ruta.read_text(encoding="utf-8") != contenido]
        desfasados += [f"{ruta.relative_to(base).as_posix()} (sobra)" for ruta in sobrantes]
        return desfasados
    for ruta, contenido in archivos:
        ruta.parent.mkdir(parents=True, exist_ok=True)
        ruta.write_text(contenido, encoding="utf-8")
    for ruta in sobrantes:
        ruta.unlink()
    return []


def construir_datos(anotada, completa, traducidos, lexico, referencias, evidencia_filas, culturales, gramaticales, alias, cercanias):
    """El diccionario `d` que reciben exportes.py, revision.py y los informes, a partir de los CSV ya leídos y validados.

    Un solo lugar: pruebas/ lo usa también, para no armar una copia que se desfase de la de main().
    """
    return {
        "idiomas": IDIOMAS, "anotada": anotada, "completa": completa, "cercanias": cercanias,
        "significados": {(f["idioma"], f["nombre_ingles"]): (f["significado_psicologico"], f["significado_mistico"])
                         for f in traducidos},
        "lexico": lexico, "referencias": referencias, "evidencia": {f["nombre_ingles"]: f for f in evidencia_filas},
        "culturales": culturales, "categorias": gramaticales, "alias": alias,
        "hex_concepto": {c: hex_de_concepto(c, anotada, completa) for c in CONCEPTOS},
        "hex_cotidiano": {c: hex_cotidiano_de_concepto(c, completa, anotada) for c in CONCEPTOS},
    }


def ruta_de(bandera, que):
    """La ruta que sigue a `bandera` en la línea de comandos, o None si no se pasó la bandera."""
    if bandera not in sys.argv:
        return None
    indice = sys.argv.index(bandera)
    if indice + 1 >= len(sys.argv):
        sys.exit(f"{bandera} necesita la ruta {que}: python3 generar-tabla.py {bandera} ruta")
    return sys.argv[indice + 1]


def leer_todos():
    """Lee los diez CSV. Si varios están mal (cabecera inesperada, fila con campos de más o de menos), los cuenta todos."""
    fallos, leidos = [], {}
    pedidos = (("anotada", leer, (ANOTADA, COLUMNAS_ANOTADA)), ("completa", leer_completa, ()),
               ("traducidos", leer, (SIGNIFICADOS, COLUMNAS_SIGNIFICADOS)), ("lexico", leer, (LEXICO, COLUMNAS_LEXICO)),
               ("referencias", leer, (REFERENCIAS, COLUMNAS_REFERENCIAS)), ("evidencia", leer, (EVIDENCIA, COLUMNAS_EVIDENCIA)),
               ("culturales", leer, (CULTURALES, COLUMNAS_CULTURALES)), ("gramaticales", leer, (CATEGORIAS, categorias.COLUMNAS)),
               ("revisiones", leer, (REVISIONES, revision.COLUMNAS)),
               ("colisiones_revisadas", leer, (COLISIONES_REVISADAS, COLUMNAS_COLISIONES)))
    for nombre, lector, argumentos in pedidos:
        try:
            leidos[nombre] = lector(*argumentos)
        except SystemExit as salida:
            fallos.append(str(salida.code))
    if fallos:
        sys.exit("No se pudieron leer los CSV:\n  " + "\n  ".join(fallos))
    return leidos


def main():
    leidos = leer_todos()
    anotada, completa, traducidos, lexico = leidos["anotada"], leidos["completa"], leidos["traducidos"], leidos["lexico"]
    referencias, evidencia_filas, culturales = leidos["referencias"], leidos["evidencia"], leidos["culturales"]
    gramaticales, revisiones = leidos["gramaticales"], leidos["revisiones"]
    fallos = colorimetria.autoprueba()
    if fallos:
        sys.exit(f"colorimetria.py: CIEDE2000 no coincide con {len(fallos)} vectores de Sharma et al.: {fallos[:2]}")
    errores = []
    validar_anotada(anotada, errores)
    validar_completa(completa, errores)
    validar_significados(traducidos, anotada, errores)
    alias = alias_de(anotada)  # una vez: lo usan la validación, los datos y las 30 páginas por idioma
    validar_alias(anotada, traducidos, errores, alias)
    validar_colisiones(leidos["colisiones_revisadas"], anotada, errores)
    validar_lexico(lexico, anotada, completa, errores)
    ids = validar_referencias(referencias, errores)
    usadas = set()
    validar_evidencia(evidencia_filas, anotada, ids, errores, usadas)
    validar_culturales(culturales, anotada, ids, errores, usadas)
    for i in sorted(ids - usadas):
        errores.append(f"referencias.csv: la referencia {i!r} no la usa ningún otro CSV")
    categorias.validar(gramaticales, anotada, lexico, [c for c, _ in IDIOMAS], CONCEPTOS, errores)
    revisable = revision.universo({"idiomas": IDIOMAS, "anotada": anotada, "lexico": lexico, "categorias": gramaticales})
    revision.validar(revisiones, [c for c, _ in IDIOMAS], errores)
    version = None
    if CHANGELOG.exists():
        version = manifiesto.leer_changelog(CHANGELOG.read_text(encoding="utf-8"), errores)
    else:
        errores.append("CHANGELOG.md: no existe")
    if errores:
        sobran = f"\n  ... y {len(errores) - 30} errores más" if len(errores) > 30 else ""
        sys.exit("Errores en los CSV:\n  " + "\n  ".join(errores[:30]) + sobran)

    texto = README.read_text(encoding="utf-8")
    # Cada marcador debe ocupar una línea entera: así no coincide con menciones en la prosa.
    patron = re.compile(r"^" + re.escape(INICIO) + r"$.*?^" + re.escape(FIN) + r"$", re.S | re.M)
    if not patron.search(texto):
        sys.exit(f"README.md: faltan los marcadores {INICIO} y {FIN}")

    cercanias = calcular_cercanias(completa, anotada)
    datos = construir_datos(anotada, completa, traducidos, lexico, referencias, evidencia_filas, culturales, gramaticales, alias, cercanias)
    significados, evidencia = datos["significados"], datos["evidencia"]
    # Lo que se construye a petición no se guarda en el repositorio: se deriva sin pérdida de los CSV.
    if ruta := ruta_de("--sqlite", "de la base"):
        conexion = exportes.construir_sqlite(ruta, datos)
        tablas = [fila[0] for fila in conexion.execute("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")]
        conexion.close()
        print(f"Base SQLite en {ruta} con las tablas: {', '.join(tablas)}.")
        return 0
    if ruta := ruta_de("--conllu", "del archivo CoNLL-U"):
        Path(ruta).write_text(exportes.ejemplos_conllu(datos), encoding="utf-8")
        print(f"{len(datos['categorias'])} frases de ejemplo en CoNLL-U en {ruta}.")
        return 0
    if ruta := ruta_de("--visor", "del archivo HTML"):
        Path(ruta).write_text(revision.visor_html(datos, VISOR_PLANTILLA.read_text(encoding="utf-8")), encoding="utf-8")
        print(f"Visor de revisión en {ruta}: ábrelo en un navegador.")
        return 0
    completa_texto = texto_completa(completa, cercanias)
    huellas_actuales = revision.huellas(datos)
    filas_gazetteer = exportes.filas_gazetteer(datos)  # ordenarlas cuesta: una vez para el TSV, los patrones y el informe
    jsonl, tsv = exportes.colores_jsonl(datos), exportes.gazetteer_tsv(datos, filas_gazetteer)
    patrones, lexico_ud = exportes.spacy_patrones(datos, filas_gazetteer), exportes.lexico_ud(datos)
    # Las entradas del manifiesto, en el orden del README. nombres-completos.csv va con el texto que genera este programa.
    entradas = [(ruta.name, filas, ruta.read_text(encoding="utf-8"), None) for ruta, filas in (
        (ANOTADA, len(anotada)), (SIGNIFICADOS, len(traducidos)), (LEXICO, len(lexico)), (EVIDENCIA, len(evidencia_filas)),
        (CULTURALES, len(culturales)), (CATEGORIAS, len(gramaticales)), (REFERENCIAS, len(referencias)))]
    entradas.append((COMPLETA.name, len(completa), completa_texto, None))
    exportes_manifiesto = [("exportes/colores.jsonl", jsonl.count("\n"), jsonl, None),
                           ("exportes/gazetteer.tsv", tsv.count("\n") - 1, tsv, exportes.COLUMNAS_GAZETTEER)]
    exportes_manifiesto += [(f"exportes/spacy/{codigo}.jsonl", texto.count("\n"), texto, None) for codigo, texto in patrones.items()]
    exportes_manifiesto += [("exportes/lexico-ud.tsv", lexico_ud.count("\n") - 1, lexico_ud, exportes.COLUMNAS_LEXICO_UD)]
    generados = {
        README: patron.sub(lambda _: f"{INICIO}\n{tabla_readme(anotada, evidencia)}\n{FIN}", texto),
        INDICE: indice_idiomas(anotada),
        REFS_MD: referencias_md(referencias),
        AMBIGUEDAD: ambiguedad.informe(filas_gazetteer, IDIOMAS),
        AUDITORIA: auditoria.informe(completa),
        CATEGORIAS_MD: categorias.informe(gramaticales, IDIOMAS),
        COBERTURA: cobertura.informe(IDIOMAS, anotada, lexico, gramaticales, CONCEPTOS, datos["hex_concepto"]),
        REVISIONES_MD: revision.informe(revisiones, revisable, IDIOMAS, huellas_actuales),
        COMPLETA: completa_texto,
        DIR_EXPORTES / "colores.jsonl": jsonl,
        DIR_EXPORTES / "gazetteer.tsv": tsv,
        DIR_EXPORTES / "lexico-ud.tsv": lexico_ud,
        DIR_EXPORTES / "manifiesto.json": manifiesto.manifiesto(version, [c for c, _ in IDIOMAS], entradas, exportes_manifiesto),
    }
    for codigo, texto in patrones.items():
        generados[DIR_EXPORTES / "spacy" / f"{codigo}.jsonl"] = texto
    for codigo, etiqueta in IDIOMAS:
        generados[DIR_IDIOMAS / f"{codigo}.md"] = pagina_idioma(
            codigo, etiqueta, anotada, significados, lexico, completa, evidencia, culturales, alias)
    sobrantes = sorted(p for carpeta in (DIR_IDIOMAS, DIR_EXPORTES) if carpeta.exists()
                       for p in carpeta.rglob("*") if p.is_file() and p not in generados)

    resumen = (f"{len(anotada)} colores anotados en {len(IDIOMAS)} idiomas, "
               f"{len(traducidos)} significados traducidos, {len(lexico)} formas de léxico básico, "
               f"{len(culturales)} matices culturales, {len(referencias)} referencias y {len(completa)} nombres completos")
    comprobar = "--comprobar" in sys.argv
    if comprobar:
        fallos_sqlite = exportes.comprobar_sqlite(datos)
        if fallos_sqlite:
            print("La base SQLite no coincide con los CSV: " + "; ".join(fallos_sqlite), file=sys.stderr)
            return 1
    atencion_revisiones, _, _ = revision.situacion(revisiones, huellas_actuales)
    if revision.aviso(atencion_revisiones):  # solo avisa: una revisión huérfana no rompe la generación, pero tampoco debe pasar callada
        print(revision.aviso(atencion_revisiones), file=sys.stderr)
    desfasados = comprobar_o_escribir(CARPETA, generados.items(), comprobar, sobrantes)
    if comprobar:
        if desfasados:
            print(f"No coinciden con los CSV: {', '.join(desfasados)}. "
                  "Ejecuta python3 generar-tabla.py", file=sys.stderr)
            return 1
        print(f"OK: {resumen}; archivos generados al día.")
        return 0

    print(f"Generados README.md, nombres-completos.csv, colores-por-idioma.md y {len(IDIOMAS)} páginas en por-idioma/: {resumen}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
