#!/usr/bin/env python3
"""Valida paises.csv y paises_pos.csv y genera dos tablas ordenadas por prioridad.

Uso: python3 generar_tabla.py               valida y escribe las dos tablas y priorizacion.csv
     python3 generar_tabla.py --comprobar   valida y falla si las salidas no están al día (no escribe)
     (solo biblioteca estándar de Python 3)

Genealogía (paises.csv):      prioridad = IG x IO;  excluido si IO <= UMBRAL_GENEALOGIA
Etiquetador POS (paises_pos.csv): prioridad = IL x IP local;  excluido si IP local <= UMBRAL_POS e IO <= UMBRAL_GENEALOGIA
  IP debe ser el máximo de los niveles (niveles_lenguas.csv) de las lenguas de idioma_principal.
  IP local debe ser el nivel de la primera lengua listada (la lengua local).
  El nivel de cada lengua debe salir de sus palabras anotadas (nivel_por_palabras).
Topes por datos: IL no puede superar el de su alfabetización (TOPES_IL), ni IO el de sus usuarios
de internet (TOPE_IO_INTERNET).
"""
import csv
import io
import json
import math
import statistics
import sys
import unicodedata
from collections import Counter
from pathlib import Path

from comun import filas_csv, leer_csv
from sugerencias import desvios_por_pais, sugerencias_md

AQUI = Path(__file__).resolve().parent
PAISES = AQUI / "paises.csv"
POS = AQUI / "paises_pos.csv"
NIVELES = AQUI / "niveles_lenguas.csv"
SALIDA_GENEALOGIA = AQUI / "tabla_genealogia.md"
SALIDA_POS = AQUI / "tabla_pos.md"
SALIDA_FINAL = AQUI / "priorizacion.csv"
SALIDA_VISTA = AQUI / "vista.html"
SALIDA_ESCENARIOS = AQUI / "escenarios.md"
SALIDA_SUGERENCIAS = AQUI / "sugerencias_indices.md"
# Escenarios de ranking: (nombre, puntaje, excluido). El primero de cada grupo es la regla vigente.
ESCENARIOS_GEN = [
    ("Vigente: IG × IO, excluye IO ≤ 1", lambda d: d["ig"] * d["io"], lambda d: d["io"] <= 1),
    ("Excluye IO ≤ 2", lambda d: d["ig"] * d["io"], lambda d: d["io"] <= 2),
    ("Más peso al interés: IG² × IO", lambda d: d["ig"] ** 2 * d["io"], lambda d: d["io"] <= 1),
    ("Más peso a operar: IG × IO²", lambda d: d["ig"] * d["io"] ** 2, lambda d: d["io"] <= 1),
]
ESCENARIOS_POS = [
    ("Vigente: IL × IP local, excluye IP local ≤ 1 con IO ≤ 1", lambda d: d["il"] * d["ipl"],
     lambda d: d["ipl"] <= 1 and d["io"] <= 1),
    ("Excluye IP local ≤ 1 (la regla anterior)", lambda d: d["il"] * d["ipl"], lambda d: d["ipl"] <= 1),
    ("IL × IP de la mejor lengua", lambda d: d["il"] * d["ip"], lambda d: d["ipl"] <= 1 and d["io"] <= 1),
    ("Más peso a la lectura: IL² × IP local", lambda d: d["il"] ** 2 * d["ipl"],
     lambda d: d["ipl"] <= 1 and d["io"] <= 1),
]
PUESTOS_MOSTRADOS = 25
PLANTILLA_VISTA = AQUI / "vista_plantilla.html"
MAPA = AQUI / "mapa_paises.csv"
DETALLE = AQUI / "datos_detalle.csv"
BANDA = AQUI / "poblacion_historica_detalle.csv"
README = AQUI / "README.md"
INICIO_RESUMEN = "<!-- resumen: lo escribe generar_tabla.py; no editar a mano -->"
FIN_RESUMEN = "<!-- fin del resumen -->"
COLUMNAS_FINAL = [
    "codigo", "pais", "tipo", "region",
    "indice_genealogia", "indice_operabilidad", "prioridad_genealogia", "excluido_genealogia",
    "motivo_exclusion_genealogia",
    "indice_lectura", "indice_pos_local", "prioridad_pos", "excluido_pos", "motivo_exclusion_pos",
]

COLUMNAS_PAISES = [
    "codigo", "pais", "tipo", "region", "poblacion_millones",
    "poblacion_historica_acumulada", "idh", "pib_per_capita_usd", "pib_per_capita_ppa_usd",
    "alfabetizacion_adultos", "usuarios_internet", "emigrantes_pct", "libertad_fh", "democracia_eiu",
    "corrupcion_cpi", "indice_genealogia", "indice_operabilidad", "comentarios",
]
COLUMNAS_POS = ["codigo", "idioma_principal", "indice_lectura", "indice_pos", "indice_pos_local",
                "comentarios_pos"]
COLUMNAS_NIVELES = ["lengua", "codigos", "palabras_anotadas", "treebanks_ud", "paginas_web_pct", "nivel"]
TIPOS = {"estado", "territorio", "entidad disputada"}
NUMERICAS_PAISES = ["poblacion_millones", "poblacion_historica_acumulada", "idh",
                    "pib_per_capita_usd", "pib_per_capita_ppa_usd", "alfabetizacion_adultos",
                    "usuarios_internet", "emigrantes_pct", "democracia_eiu", "corrupcion_cpi"]
ESCALA = {"1", "2", "3", "4", "5"}  # los valores de los índices IG, IO, IL, IP e IP local
LIBERTADES = {"", "libre", "parcial", "no libre"}
RANGOS = {"idh": (0, 1), "alfabetizacion_adultos": (0, 100), "usuarios_internet": (0, 100),
          "emigrantes_pct": (0, 100), "democracia_eiu": (0, 100), "corrupcion_cpi": (0, 100)}
ESCALAS_PAISES = ["indice_genealogia", "indice_operabilidad"]
ESCALAS_POS = ["indice_lectura", "indice_pos", "indice_pos_local"]
UMBRAL_GENEALOGIA = 1  # IO <= 1: inviable para operar
UMBRAL_POS = 1  # IP local <= 1: la lengua local no tiene recursos; excluye solo si además IO <= 1
# Nivel de una lengua según sus palabras anotadas (Universal Dependencies + MasakhaPOS):
# (mínimo de palabras, nivel). Sin ninguna, nivel 1.
UMBRALES_NIVEL = [(500_000, 5), (100_000, 4), (10_000, 3), (1, 2)]
# Alfabetización de adultos (%) -> IL máximo: (mínimo, tope). Por debajo de 50, IL 1. Sin dato, sin tope.
TOPES_IL = [(97, 5), (90, 4), (75, 3), (50, 2)]
TOPE_IO_INTERNET = (60, 3)  # con menos del 60 % de usuarios de internet, IO como máximo 3


def nivel_por_palabras(palabras):
    for minimo, nivel in UMBRALES_NIVEL:
        if palabras >= minimo:
            return nivel
    return 1


def tope_il(alfabetizacion):
    for minimo, tope in TOPES_IL:
        if alfabetizacion >= minimo:
            return tope
    return 1


def prefijo_ip(lenguas, niveles):
    """«IP por <lengua> (mejor recurso listado).» si la mejor lengua supera a la local; si no, ""."""
    mejor = max(lenguas, key=lambda l: niveles[l])  # max devuelve la primera con el nivel más alto
    return f"IP por {mejor} (mejor recurso listado)." if niveles[mejor] > niveles[lenguas[0]] else ""


def leer(ruta, columnas):
    encabezado, filas = leer_csv(ruta)
    if encabezado != columnas:
        sys.exit(f"Encabezado inesperado en {ruta.name}: {encabezado}")
    return list(enumerate(filas, start=2))


def anios_tipicos():
    """columna -> año más frecuente en datos_detalle.csv: el que vale para casi todos los países.

    Los encabezados lo usan en vez de repetir los años de preparar_datos.py.
    """
    por_columna = {}
    for f in filas_csv(DETALLE):
        por_columna.setdefault(f["columna"], Counter())[f["anio"]] += 1
    return {columna: int(cuenta.most_common(1)[0][0]) for columna, cuenta in por_columna.items()}


def errores_de(nombre, linea, fila, escalas, numericas):
    if None in fila or any(v is None for v in fila.values()):
        return [f"{nombre} línea {linea}: número de columnas distinto del encabezado"]
    errs = []
    if len(fila["codigo"]) != 2:
        errs.append("codigo debe tener 2 letras (ISO 3166-1 alfa-2)")
    for campo in escalas:
        if fila[campo] not in ESCALA:
            errs.append(f"{campo} debe ser un entero de 1 a 5, es '{fila[campo]}'")
    for campo in numericas:
        if fila[campo]:
            try:
                valor = float(fila[campo])
            except ValueError:
                errs.append(f"{campo} no es numérico: '{fila[campo]}'")
                continue
            if not math.isfinite(valor):
                errs.append(f"{campo} no es un número finito: '{fila[campo]}'")
            elif valor < 0:
                errs.append(f"{campo} no puede ser negativo: '{fila[campo]}'")
    return [f"{nombre} línea {linea} ({fila['codigo']}): {e}" for e in errs]


def si_no(valor, ascii=False):
    """«sí» o «no» de las tablas; en priorizacion.csv, que es de datos, «si» sin acento."""
    return ("si" if ascii else "sí") if valor else "no"


def clave_nombre(nombre):
    """Orden alfabético de un nombre sin tener en cuenta los acentos (Líbano antes que Liberia), el
    mismo de la vista web (localeCompare «es»). Solo la ñ se trata como una n."""
    plano = "".join(c for c in unicodedata.normalize("NFD", nombre) if not unicodedata.combining(c))
    return (plano.casefold(), nombre)


def numero(valor):
    """float del valor, o None si está vacío o no es un número (eso lo informa errores_de)."""
    try:
        return float(valor) if valor else None
    except ValueError:
        return None


def celda(valor, vacio="—"):
    return valor if valor else vacio


def texto_csv(columnas, filas):
    salida = io.StringIO()
    escritor = csv.DictWriter(salida, fieldnames=columnas)
    escritor.writeheader()
    escritor.writerows(filas)
    return salida.getvalue()


def vista_html(registros, anios, documento=True):
    """La vista web: la plantilla con los datos y el mapa. documento=False da el fragmento sin
    <!doctype>, <head> ni <body>, para publicarlo donde se agregan solos.

    Cada registro trae el puntaje y la exclusión de cada escenario (eg, ep) y los índices que se
    apartan de los datos (sg): la vista no repite esas reglas en JavaScript."""
    mapa = [[fila["codigo"], fila["trazado"], float(fila["x"]), float(fila["y"])] for fila in filas_csv(MAPA)]

    def a_json(valor):  # "</" no puede aparecer dentro de <script>
        return json.dumps(valor, ensure_ascii=False, separators=(",", ":")).replace("</", "<\\/")

    texto = PLANTILLA_VISTA.read_text(encoding="utf-8")
    texto = (texto.replace("/*DATOS*/null", a_json(registros)).replace("/*MAPA*/null", a_json(mapa))
             .replace("/*ANIOS*/null", a_json(anios))
             .replace("/*ESCENARIOS*/null", a_json({"gen": [n for n, _, _ in ESCENARIOS_GEN],
                                                    "pos": [n for n, _, _ in ESCENARIOS_POS]})))
    if not documento:
        return texto
    cabeza, cuerpo = texto.split('\n<div class="pagina">', 1)
    return ('<!doctype html>\n<html lang="es">\n<head>\n<meta charset="utf-8">\n'
            '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n'
            + cabeza + "\n</head>\n<body>\n<div class=\"pagina\">" + cuerpo + "</body>\n</html>\n")


def puestos(registros, puntaje, excluido):
    """codigo -> puesto (1 = mejor; los empates comparten puesto) o None si está excluido."""
    validos = [d for d in registros if not excluido(d)]
    valores = sorted((puntaje(d) for d in validos), reverse=True)
    primero = {}
    for i, v in enumerate(valores, start=1):
        primero.setdefault(v, i)
    return {d["c"]: (primero[puntaje(d)] if not excluido(d) else None) for d in registros}


def escenarios_md(registros):
    """Tabla de puestos por escenario: los países que entran entre los PUESTOS_MOSTRADOS primeros
    de algún escenario, ordenados por el vigente."""
    partes = ["# Escenarios de ranking", "",
              "> Generado por `generar_tabla.py`. No editar a mano. Cada columna es un escenario; el "
              "número es el puesto del país (los empates comparten puesto) y «excl.» indica que ese "
              "escenario lo excluye. La primera columna es la regla vigente.", ""]
    nombres = {d["c"]: d["n"] for d in registros}
    for titulo, escenarios in (("Genealogía", ESCENARIOS_GEN), ("Etiquetador POS", ESCENARIOS_POS)):
        tablas = [puestos(registros, puntaje, excluido) for _, puntaje, excluido in escenarios]
        excluidos = [sum(1 for v in t.values() if v is None) for t in tablas]
        codigos = [c for c in nombres if any(t[c] is not None and t[c] <= PUESTOS_MOSTRADOS for t in tablas)]
        codigos.sort(key=lambda c: (tablas[0][c] is None, tablas[0][c] or 0, clave_nombre(nombres[c])))
        enc = ["País"] + [f"{nombre} ({n} excl.)" for (nombre, _, _), n in zip(escenarios, excluidos)]
        partes += [f"## {titulo}", "", "| " + " | ".join(enc) + " |", "|" + "|".join(["---"] * len(enc)) + "|"]
        for c in codigos:
            partes.append("| " + " | ".join([nombres[c]] + [str(t[c]) if t[c] is not None else "excl."
                                                            for t in tablas]) + " |")
        partes.append("")
    return "\n".join(partes)


def resumen_md(registros, niveles):
    """Las cifras que cambian con los datos, para el bloque «Resumen» del README."""
    def reparto(valores):
        return ", ".join(f"{n}: {sum(1 for v in valores if v == n)}" for n in range(5, 0, -1))

    def nombres(filtro):
        lista = sorted((d["n"] for d in registros if filtro(d)), key=clave_nombre)
        return ", ".join(lista[:-1]) + " y " + lista[-1] if len(lista) > 1 else "".join(lista)

    tipos = Counter(d["t"] for d in registros)
    medianas = []
    for nivel_io in range(1, 6):
        cpi = [d["cpi"] for d in registros if d["io"] == nivel_io and d["cpi"] is not None]
        if cpi:
            medianas.append(f"IO {nivel_io}: {statistics.median(cpi):g}")
    sin_corpus_dentro = sum(1 for d in registros if d["ipl"] <= UMBRAL_POS and not d["xp"])
    lineas = [
        f"- **Filas:** {len(registros)} ({tipos['estado']} estados, {tipos['territorio']} territorios y "
        f"{tipos['entidad disputada']} entidades disputadas).",
        f"- **Excluidos de genealogía** (IO ≤ {UMBRAL_GENEALOGIA}): {sum(d['xg'] for d in registros)}: "
        f"{nombres(lambda d: d['xg'])}. Con IO ≤ {UMBRAL_GENEALOGIA + 1} serían "
        f"{sum(1 for d in registros if d['io'] <= UMBRAL_GENEALOGIA + 1)}.",
        f"- **Excluidos del etiquetador POS:** {sum(d['xp'] for d in registros)}: "
        f"{nombres(lambda d: d['xp'])}. Otros {sin_corpus_dentro} no tienen corpus anotado en su lengua "
        "local y siguen dentro, con prioridad baja.",
        f"- **IP de la mejor lengua**, países por nivel: {reparto([d['ip'] for d in registros])}.",
        f"- **IP local**, países por nivel: {reparto([d['ipl'] for d in registros])}.",
        f"- **Lenguas por nivel:** {reparto(list(niveles.values()))}.",
        f"- **Corrupción mediana por IO** (100 = sin corrupción): {'; '.join(medianas)}.",
    ]
    return "\n".join(lineas)


def readme_con_resumen(resumen):
    texto = README.read_text(encoding="utf-8")
    if INICIO_RESUMEN not in texto or FIN_RESUMEN not in texto:
        sys.exit(f"README.md no tiene las marcas del resumen: {INICIO_RESUMEN} y {FIN_RESUMEN}")
    antes, resto = texto.split(INICIO_RESUMEN, 1)
    _, despues = resto.split(FIN_RESUMEN, 1)
    return f"{antes}{INICIO_RESUMEN}\n{resumen}\n{FIN_RESUMEN}{despues}"


def celda_md(texto):
    """Un texto dentro de una celda de tabla Markdown: «|» y los saltos de línea la romperían."""
    return texto.replace("|", "\\|").replace("\r", " ").replace("\n", " ")


def tabla_md(titulo, nota, enc, filas):
    lineas = [f"# {titulo}", "", f"> {nota}", "",
              "| " + " | ".join(enc) + " |",
              "|" + "|".join(["---"] * len(enc)) + "|"]
    for celdas in filas:
        lineas.append("| " + " | ".join(celda_md(c) for c in celdas) + " |")
    return "\n".join(lineas) + "\n"


def comprobar_o_escribir(archivos, comprobar):
    """Compara contra el disco o escribe los archivos generados (ruta, contenido) de este script.

    newline="" conserva el CRLF/LF exacto de priorizacion.csv al comparar y al escribir. Con
    comprobar=True no escribe nada: devuelve los nombres que no coinciden con lo que hay en disco o
    que faltan (vacía si todo está al día). Con comprobar=False escribe cada archivo y devuelve [].
    """
    if comprobar:
        desactualizadas = []
        for ruta, texto in archivos:
            if not ruta.exists():
                desactualizadas.append(ruta.name)
                continue
            with ruta.open(encoding="utf-8", newline="") as f:
                if f.read() != texto:
                    desactualizadas.append(ruta.name)
        return desactualizadas
    for ruta, texto in archivos:
        with ruta.open("w", encoding="utf-8", newline="") as f:
            f.write(texto)
    return []


def main(comprobar=False):
    paises = leer(PAISES, COLUMNAS_PAISES)
    pos = leer(POS, COLUMNAS_POS)

    errores = []
    for linea, f in paises:
        errores += errores_de("paises.csv", linea, f, ESCALAS_PAISES, NUMERICAS_PAISES)
        if f.get("tipo") not in TIPOS:
            errores.append(f"paises.csv línea {linea} ({f.get('codigo')}): tipo '{f.get('tipo')}' no válido")
        if f.get("libertad_fh") not in LIBERTADES:
            errores.append(f"paises.csv línea {linea} ({f.get('codigo')}): libertad_fh "
                           f"'{f.get('libertad_fh')}' no válida")
        for campo, (minimo, maximo) in RANGOS.items():
            valor = f.get(campo) or ""
            try:
                fuera = valor and not minimo <= float(valor) <= maximo
            except ValueError:
                fuera = False  # ya lo informa errores_de
            if fuera:
                errores.append(f"paises.csv línea {linea} ({f['codigo']}): {campo} fuera de "
                               f"{minimo}–{maximo}: {valor}")
    lenguas = {f["lengua"]: f for _, f in leer(NIVELES, COLUMNAS_NIVELES)}
    for lengua, f in lenguas.items():
        try:
            palabras, _ = int(f["palabras_anotadas"]), int(f["treebanks_ud"])
            float(f["paginas_web_pct"] or 0)
        except ValueError:
            errores.append(f"niveles_lenguas.csv ({lengua}): columnas numéricas no válidas")
            continue
        if f["nivel"] != str(nivel_por_palabras(palabras)):
            errores.append(f"niveles_lenguas.csv ({lengua}): nivel {f['nivel']} debería ser "
                           f"{nivel_por_palabras(palabras)} ({palabras} palabras anotadas)")
    niveles = {l: int(f["nivel"]) for l, f in lenguas.items() if f["nivel"] in ESCALA}
    for linea, f in pos:
        errores += errores_de("paises_pos.csv", linea, f, ESCALAS_POS, [])
        langs = [l.strip() for l in f["idioma_principal"].split(";")]
        sin_nivel = [l for l in langs if l not in niveles]
        if sin_nivel:
            errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): "
                           f"lenguas sin nivel en niveles_lenguas.csv: {sin_nivel}")
        elif f["indice_pos"] in ESCALA:
            esperado = max(niveles[l] for l in langs)
            if int(f["indice_pos"]) != esperado:
                errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): "
                               f"IP {f['indice_pos']} debería ser {esperado} (máximo de sus lenguas)")
            local = niveles[langs[0]]
            if f["indice_pos_local"] in ESCALA and int(f["indice_pos_local"]) != local:
                errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): IP local "
                               f"{f['indice_pos_local']} debería ser {local} (nivel de «{langs[0]}»)")
            prefijo, comentario = prefijo_ip(langs, niveles), f["comentarios_pos"]
            if prefijo and not comentario.startswith(prefijo):
                errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): el comentario debe "
                               f"empezar por «{prefijo}»")
            elif not prefijo and comentario.startswith("IP por"):
                errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): el comentario no debe "
                               "empezar por «IP por»: el IP sale de la lengua local")

    codigos_paises = [f["codigo"] for _, f in paises]
    codigos_pos = [f["codigo"] for _, f in pos]
    for nombre, codigos in (("paises.csv", codigos_paises), ("paises_pos.csv", codigos_pos)):
        repetidos = {c for c, n in Counter(codigos).items() if n > 1}
        if repetidos:
            errores.append(f"{nombre}: códigos repetidos {sorted(repetidos)}")
    alfabetizacion = {f["codigo"]: numero(f.get("alfabetizacion_adultos")) for _, f in paises}
    for linea, f in pos:
        dato = alfabetizacion.get(f["codigo"])
        if dato is not None and f["indice_lectura"] in ESCALA and \
                int(f["indice_lectura"]) > tope_il(dato):
            errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): IL {f['indice_lectura']} supera "
                           f"el tope {tope_il(dato)} de una alfabetización del {dato} %")
    minimo_internet, tope_io = TOPE_IO_INTERNET
    for linea, f in paises:
        internet = numero(f.get("usuarios_internet"))
        if internet is not None and internet < minimo_internet and f["indice_operabilidad"] in ESCALA \
                and int(f["indice_operabilidad"]) > tope_io:
            errores.append(f"paises.csv línea {linea} ({f['codigo']}): IO {f['indice_operabilidad']} supera "
                           f"el tope {tope_io} de un {internet} % de usuarios de internet")
    faltan = set(codigos_paises) ^ set(codigos_pos)
    if faltan:
        errores.append(f"los dos archivos no tienen los mismos códigos; diferencia: {sorted(faltan)}")
    if errores:
        sys.exit("Errores en los CSV:\n  " + "\n  ".join(errores))

    pos_por_codigo = {f["codigo"]: f for _, f in pos}
    banda = {f["codigo"]: (numero(f["minimo_millones"]), numero(f["maximo_millones"]))
             for f in filas_csv(BANDA)} if BANDA.exists() else {}

    filas_gen, filas_pos, filas_final, registros = [], [], [], []
    excluidos_gen = excluidos_pos = 0
    # La regla vigente es ESCENARIOS_GEN[0]/ESCENARIOS_POS[0]: puntaje y exclusión salen de ahí, no de una
    # copia aparte, para que no puedan desincronizarse (escenarios.md los etiqueta "Vigente").
    _, puntaje_gen, excluido_gen = ESCENARIOS_GEN[0]
    _, puntaje_pos, excluido_pos = ESCENARIOS_POS[0]
    for _, f in paises:
        ig, io = int(f["indice_genealogia"]), int(f["indice_operabilidad"])
        p = pos_por_codigo[f["codigo"]]
        il, ip, ipl = int(p["indice_lectura"]), int(p["indice_pos"]), int(p["indice_pos_local"])
        d = {"ig": ig, "io": io, "il": il, "ip": ip, "ipl": ipl}
        pg, pp = puntaje_gen(d), puntaje_pos(d)
        excl_gen, excl_pos = excluido_gen(d), excluido_pos(d)
        excluidos_gen += excl_gen
        excluidos_pos += excl_pos
        filas_gen.append((excl_gen, -pg, clave_nombre(f["pais"]), [
            f["pais"], f["tipo"], f["region"], celda(f["poblacion_millones"]),
            celda(f["poblacion_historica_acumulada"]),
            celda(f["idh"]), celda(f["pib_per_capita_usd"]),
            celda(f["pib_per_capita_ppa_usd"]), celda(f["usuarios_internet"]), celda(f["emigrantes_pct"]),
            celda(f["libertad_fh"]), celda(f["democracia_eiu"]), celda(f["corrupcion_cpi"]), str(ig), str(io), str(pg), si_no(excl_gen), f["comentarios"],
        ]))
        local = lenguas[p["idioma_principal"].split(";")[0].strip()]
        filas_pos.append((excl_pos, -pp, clave_nombre(f["pais"]), [
            f["pais"], p["idioma_principal"], f["tipo"],
            celda(f["alfabetizacion_adultos"]), celda(f["usuarios_internet"]), str(il), str(ip), str(ipl),
            f"{int(local['palabras_anotadas']):,}".replace(",", " "), celda(local["paginas_web_pct"]),
            str(pp), si_no(excl_pos), p["comentarios_pos"],
        ]))
        registros.append({
            "c": f["codigo"], "n": f["pais"], "t": f["tipo"], "r": f["region"],
            **{clave: numero(f[columna]) for clave, columna in (
                ("pob", "poblacion_millones"), ("nac", "poblacion_historica_acumulada"), ("idh", "idh"),
                ("pib", "pib_per_capita_usd"), ("ppa", "pib_per_capita_ppa_usd"),
                ("alf", "alfabetizacion_adultos"), ("net", "usuarios_internet"), ("emi", "emigrantes_pct"),
                ("eiu", "democracia_eiu"), ("cpi", "corrupcion_cpi"))},
            "fh": f["libertad_fh"],
            "nmin": banda.get(f["codigo"], (None, None))[0], "nmax": banda.get(f["codigo"], (None, None))[1],
            "ig": ig, "io": io, "pg": pg, "xg": excl_gen, "cg": f["comentarios"],
            "lenguas": p["idioma_principal"], "local": local["lengua"], "il": il, "ip": ip, "ipl": ipl,
            "pal": int(local["palabras_anotadas"]), "web": numero(local["paginas_web_pct"]),
            "pp": pp, "xp": excl_pos, "cp": p["comentarios_pos"],
        })
        filas_final.append({
            "codigo": f["codigo"], "pais": f["pais"], "tipo": f["tipo"], "region": f["region"],
            "indice_genealogia": ig, "indice_operabilidad": io, "prioridad_genealogia": pg,
            "excluido_genealogia": si_no(excl_gen, ascii=True),
            "motivo_exclusion_genealogia":
                f"IO {io} (se excluye con IO <= {UMBRAL_GENEALOGIA}): {f['comentarios']}" if excl_gen else "",
            "indice_lectura": il, "indice_pos_local": ipl, "prioridad_pos": pp,
            "excluido_pos": si_no(excl_pos, ascii=True),
            "motivo_exclusion_pos":
                f"IP local {ipl} (se excluye con IP local <= {UMBRAL_POS}) e IO {io} (<= {UMBRAL_GENEALOGIA}): "
                f"la lengua local ({local['lengua']}) no tiene palabras anotadas en Universal Dependencies "
                "ni en MasakhaPOS, y el país es inviable para operar" if excl_pos else "",
        })

    marcas = desvios_por_pais(registros)
    vista = [{**d, "eg": [[puntaje(d), excluido(d)] for _, puntaje, excluido in ESCENARIOS_GEN],
              "ep": [[puntaje(d), excluido(d)] for _, puntaje, excluido in ESCENARIOS_POS],
              "sg": marcas.get(d["c"], {})} for d in registros]
    gen_ordenado = [c for *_, c in sorted(filas_gen, key=lambda x: (x[0], x[1], x[2]))]
    pos_ordenado = [c for *_, c in sorted(filas_pos, key=lambda x: (x[0], x[1], x[2]))]

    anios = anios_tipicos()
    anios_vista = {"pob": anios["poblacion_millones"], "idh": anios["idh"]}
    texto_gen = tabla_md(
        "Listado de países: genealogía",
        "Generado por `generar_tabla.py` desde `paises.csv`. No editar a mano. Población "
        f"{anios['poblacion_millones']} de Our World in Data; PIB nominal del Banco Mundial; PIB PPA "
        f"(Banco Mundial), IDH {anios['idh']} (PNUD), libertad (Freedom House {anios['libertad_fh']}), "
        f"democracia (EIU {anios['democracia_eiu']}) y corrupción (Transparencia Internacional "
        f"{anios['corrupcion_cpi']}) vía Gapminder. El año de cada dato está en `datos_detalle.csv`. "
        "Ver `README.md`, «Estado de los datos».",
        ["País", "Tipo", "Región", f"Población {anios['poblacion_millones']} (M)", "Nacidos en la historia (M)",
         f"IDH {anios['idh']}", f"PIB pc nominal {anios['pib_per_capita_usd']} (USD)",
         f"PIB pc PPA {anios['pib_per_capita_ppa_usd']} ($ int. 2021)",
         "Internet (% pob.)", "Emigrantes (% pob.)", "Libertad (Freedom House)",
         "Democracia (EIU, 0–100)", "Corrupción (CPI, 100 = limpio)", "Genealogía (IG)",
         "Operabilidad (IO)", "Prioridad", "Excluido", "Comentarios"],
        gen_ordenado)
    texto_pos = tabla_md(
        "Listado de países: etiquetador POS",
        "Generado por `generar_tabla.py` desde `paises.csv` y `paises_pos.csv`. No editar a mano. "
        "Alfabetización de la UNESCO e internet del Banco Mundial, vía Gapminder (último dato de cada "
        "país). IP sale de las palabras anotadas de cada lengua (Universal Dependencies y MasakhaPOS); "
        "el texto web, de Common Crawl. IL es juicio propio con topes por alfabetización. Ver "
        "`README.md`, «Etiquetador POS».",
        ["País", "Idiomas (local primero)", "Tipo", "Alfabetización adultos (%)", "Internet (% pob.)",
         "Lectura (IL)", "IP (mejor lengua)", "IP local", "Palabras anotadas (lengua local)",
         "Texto web (% páginas, lengua local)", "Prioridad (IL × IP local)", "Excluido", "Comentarios"],
        pos_ordenado)

    salidas = [(SALIDA_GENEALOGIA, texto_gen), (SALIDA_POS, texto_pos),
               (SALIDA_FINAL, texto_csv(COLUMNAS_FINAL, filas_final)), (SALIDA_VISTA, vista_html(vista, anios_vista)),
               (SALIDA_ESCENARIOS, escenarios_md(registros)),
               (SALIDA_SUGERENCIAS, sugerencias_md(registros)),
               (README, readme_con_resumen(resumen_md(registros, niveles)))]
    desactualizadas = comprobar_o_escribir(salidas, comprobar)
    if comprobar and desactualizadas:
        sys.exit(f"No están al día: {', '.join(desactualizadas)}. "
                 "Ejecuta python3 generar_tabla.py y confirma el resultado.")

    accion = "al día" if comprobar else "escritas"
    print(f"{len(paises)} países. Genealogía: {excluidos_gen} excluidos ({SALIDA_GENEALOGIA.name}). "
          f"POS: {excluidos_pos} excluidos ({SALIDA_POS.name}). Tablas, {SALIDA_FINAL.name}, "
          f"{SALIDA_VISTA.name}, {SALIDA_ESCENARIOS.name} y {SALIDA_SUGERENCIAS.name} {accion}.")


if __name__ == "__main__":
    main(comprobar="--comprobar" in sys.argv[1:])
