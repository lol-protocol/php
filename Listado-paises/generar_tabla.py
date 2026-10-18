#!/usr/bin/env python3
"""Valida paises.csv y paises_pos.csv y genera dos tablas ordenadas por prioridad.

Uso: python3 generar_tabla.py               valida y escribe las dos tablas y priorizacion.csv
     python3 generar_tabla.py --comprobar   valida y falla si las salidas no están al día (no escribe)
     (solo biblioteca estándar de Python 3)

Genealogía (paises.csv):      prioridad = IG x IO;  excluido si IO <= UMBRAL_GENEALOGIA
Etiquetador POS (paises_pos.csv): prioridad = IL x IP local;  excluido si IP local <= UMBRAL_POS
  IP debe ser el máximo de los niveles (niveles_lenguas.csv) de las lenguas de idioma_principal.
  IP local debe ser el nivel de la primera lengua listada (la lengua local).
  El nivel de cada lengua debe salir de sus palabras anotadas (nivel_por_palabras).
Topes por datos: IL no puede superar el de su alfabetización (TOPES_IL), ni IO el de sus usuarios
de internet (TOPE_IO_INTERNET).
"""
import csv
import io
import json
import sys
from pathlib import Path

AQUI = Path(__file__).resolve().parent
PAISES = AQUI / "paises.csv"
POS = AQUI / "paises_pos.csv"
NIVELES = AQUI / "niveles_lenguas.csv"
SALIDA_GENEALOGIA = AQUI / "tabla_genealogia.md"
SALIDA_POS = AQUI / "tabla_pos.md"
SALIDA_FINAL = AQUI / "priorizacion.csv"
SALIDA_VISTA = AQUI / "vista.html"
PLANTILLA_VISTA = AQUI / "vista_plantilla.html"
MAPA = AQUI / "mapa_paises.csv"
COLUMNAS_FINAL = [
    "codigo", "pais", "tipo", "region",
    "indice_genealogia", "indice_operabilidad", "prioridad_genealogia", "excluido_genealogia",
    "motivo_exclusion_genealogia",
    "indice_lectura", "indice_pos_local", "prioridad_pos", "excluido_pos", "motivo_exclusion_pos",
]

COLUMNAS_PAISES = [
    "codigo", "pais", "tipo", "region", "poblacion_millones",
    "poblacion_historica_acumulada", "idh", "pib_per_capita_usd", "pib_per_capita_ppa_usd",
    "alfabetizacion_adultos", "usuarios_internet", "emigrantes_pct", "indice_genealogia", "indice_operabilidad",
    "comentarios",
]
COLUMNAS_POS = ["codigo", "idioma_principal", "indice_lectura", "indice_pos", "indice_pos_local",
                "comentarios_pos"]
COLUMNAS_NIVELES = ["lengua", "codigos", "palabras_anotadas", "treebanks_ud", "paginas_web_pct", "nivel"]
TIPOS = {"estado", "territorio", "entidad disputada"}
NUMERICAS_PAISES = ["poblacion_millones", "poblacion_historica_acumulada", "idh",
                    "pib_per_capita_usd", "pib_per_capita_ppa_usd", "alfabetizacion_adultos",
                    "usuarios_internet", "emigrantes_pct"]
RANGOS = {"idh": (0, 1), "alfabetizacion_adultos": (0, 100), "usuarios_internet": (0, 100)}
ESCALAS_PAISES = ["indice_genealogia", "indice_operabilidad"]
ESCALAS_POS = ["indice_lectura", "indice_pos", "indice_pos_local"]
UMBRAL_GENEALOGIA = 1  # IO <= 1: inviable para operar
UMBRAL_POS = 1  # IP local <= 1: la lengua local no tiene recursos para un etiquetador
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
    with ruta.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames != columnas:
            sys.exit(f"Encabezado inesperado en {ruta.name}: {lector.fieldnames}")
        return list(enumerate(lector, start=2))


def errores_de(nombre, linea, fila, escalas, numericas):
    if None in fila or any(v is None for v in fila.values()):
        return [f"{nombre} línea {linea}: número de columnas distinto del encabezado"]
    errs = []
    if len(fila["codigo"]) != 2:
        errs.append("codigo debe tener 2 letras (ISO 3166-1 alfa-2)")
    for campo in escalas:
        if fila[campo] not in {"1", "2", "3", "4", "5"}:
            errs.append(f"{campo} debe ser un entero de 1 a 5, es '{fila[campo]}'")
    for campo in numericas:
        if fila[campo]:
            try:
                float(fila[campo])
            except ValueError:
                errs.append(f"{campo} no es numérico: '{fila[campo]}'")
    return [f"{nombre} línea {linea} ({fila['codigo']}): {e}" for e in errs]


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


def vista_html(registros, documento=True):
    """La vista web: la plantilla con los datos y el mapa. documento=False da el fragmento sin
    <!doctype>, <head> ni <body>, para publicarlo donde se agregan solos."""
    with MAPA.open(encoding="utf-8", newline="") as f:
        mapa = [[fila["codigo"], fila["trazado"], float(fila["x"]), float(fila["y"])] for fila in csv.DictReader(f)]

    def a_json(valor):  # "</" no puede aparecer dentro de <script>
        return json.dumps(valor, ensure_ascii=False, separators=(",", ":")).replace("</", "<\\/")

    texto = PLANTILLA_VISTA.read_text(encoding="utf-8")
    texto = texto.replace("/*DATOS*/null", a_json(registros)).replace("/*MAPA*/null", a_json(mapa))
    if not documento:
        return texto
    cabeza, cuerpo = texto.split('\n<div class="pagina">', 1)
    return ('<!doctype html>\n<html lang="es">\n<head>\n<meta charset="utf-8">\n'
            '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n'
            + cabeza + "\n</head>\n<body>\n<div class=\"pagina\">" + cuerpo + "</body>\n</html>\n")


def tabla_md(titulo, nota, enc, filas):
    lineas = [f"# {titulo}", "", f"> {nota}", "",
              "| " + " | ".join(enc) + " |",
              "|" + "|".join(["---"] * len(enc)) + "|"]
    for celdas in filas:
        lineas.append("| " + " | ".join(celdas) + " |")
    return "\n".join(lineas) + "\n"


def main(comprobar=False):
    paises = leer(PAISES, COLUMNAS_PAISES)
    pos = leer(POS, COLUMNAS_POS)

    errores = []
    for linea, f in paises:
        errores += errores_de("paises.csv", linea, f, ESCALAS_PAISES, NUMERICAS_PAISES)
        if f.get("tipo") not in TIPOS:
            errores.append(f"paises.csv línea {linea} ({f.get('codigo')}): tipo '{f.get('tipo')}' no válido")
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
    niveles = {l: int(f["nivel"]) for l, f in lenguas.items() if f["nivel"] in {"1", "2", "3", "4", "5"}}
    for linea, f in pos:
        errores += errores_de("paises_pos.csv", linea, f, ESCALAS_POS, [])
        langs = [l.strip() for l in f["idioma_principal"].split(";")]
        sin_nivel = [l for l in langs if l not in niveles]
        if sin_nivel:
            errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): "
                           f"lenguas sin nivel en niveles_lenguas.csv: {sin_nivel}")
        elif f["indice_pos"] in {"1", "2", "3", "4", "5"}:
            esperado = max(niveles[l] for l in langs)
            if int(f["indice_pos"]) != esperado:
                errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): "
                               f"IP {f['indice_pos']} debería ser {esperado} (máximo de sus lenguas)")
            local = niveles[langs[0]]
            if f["indice_pos_local"] in {"1", "2", "3", "4", "5"} and int(f["indice_pos_local"]) != local:
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
        repetidos = {c for c in codigos if codigos.count(c) > 1}
        if repetidos:
            errores.append(f"{nombre}: códigos repetidos {sorted(repetidos)}")
    alfabetizacion = {f["codigo"]: numero(f.get("alfabetizacion_adultos")) for _, f in paises}
    for linea, f in pos:
        dato = alfabetizacion.get(f["codigo"])
        if dato is not None and f["indice_lectura"] in {"1", "2", "3", "4", "5"} and \
                int(f["indice_lectura"]) > tope_il(dato):
            errores.append(f"paises_pos.csv línea {linea} ({f['codigo']}): IL {f['indice_lectura']} supera "
                           f"el tope {tope_il(dato)} de una alfabetización del {dato} %")
    minimo_internet, tope_io = TOPE_IO_INTERNET
    for linea, f in paises:
        internet = numero(f.get("usuarios_internet"))
        if internet is not None and internet < minimo_internet and f["indice_operabilidad"] in {"4", "5"}:
            errores.append(f"paises.csv línea {linea} ({f['codigo']}): IO {f['indice_operabilidad']} supera "
                           f"el tope {tope_io} de un {internet} % de usuarios de internet")
    faltan = set(codigos_paises) ^ set(codigos_pos)
    if faltan:
        errores.append(f"los dos archivos no tienen los mismos códigos; diferencia: {sorted(faltan)}")
    if errores:
        sys.exit("Errores en los CSV:\n  " + "\n  ".join(errores))

    pos_por_codigo = {f["codigo"]: f for _, f in pos}

    filas_gen, filas_pos, filas_final, registros = [], [], [], []
    excluidos_gen = excluidos_pos = 0
    for _, f in paises:
        ig, io = int(f["indice_genealogia"]), int(f["indice_operabilidad"])
        p = pos_por_codigo[f["codigo"]]
        il, ip, ipl = int(p["indice_lectura"]), int(p["indice_pos"]), int(p["indice_pos_local"])
        excl_gen, excl_pos = io <= UMBRAL_GENEALOGIA, ipl <= UMBRAL_POS
        excluidos_gen += excl_gen
        excluidos_pos += excl_pos
        filas_gen.append((excl_gen, -ig * io, f["pais"], [
            f["pais"], f["tipo"], f["region"], celda(f["poblacion_millones"]),
            celda(f["poblacion_historica_acumulada"]),
            celda(f["idh"]), celda(f["pib_per_capita_usd"]),
            celda(f["pib_per_capita_ppa_usd"]), celda(f["usuarios_internet"]), celda(f["emigrantes_pct"]),
            str(ig), str(io), str(ig * io), "sí" if excl_gen else "no", f["comentarios"],
        ]))
        local = lenguas[p["idioma_principal"].split(";")[0].strip()]
        filas_pos.append((excl_pos, -il * ipl, f["pais"], [
            f["pais"], p["idioma_principal"], f["tipo"],
            celda(f["alfabetizacion_adultos"]), celda(f["usuarios_internet"]), str(il), str(ip), str(ipl),
            f"{int(local['palabras_anotadas']):,}".replace(",", " "), celda(local["paginas_web_pct"]),
            str(il * ipl), "sí" if excl_pos else "no", p["comentarios_pos"],
        ]))
        registros.append({
            "c": f["codigo"], "n": f["pais"], "t": f["tipo"], "r": f["region"],
            **{clave: numero(f[columna]) for clave, columna in (
                ("pob", "poblacion_millones"), ("nac", "poblacion_historica_acumulada"), ("idh", "idh"),
                ("pib", "pib_per_capita_usd"), ("ppa", "pib_per_capita_ppa_usd"),
                ("alf", "alfabetizacion_adultos"), ("net", "usuarios_internet"), ("emi", "emigrantes_pct"))},
            "ig": ig, "io": io, "pg": ig * io, "xg": excl_gen, "cg": f["comentarios"],
            "lenguas": p["idioma_principal"], "local": local["lengua"], "il": il, "ip": ip, "ipl": ipl,
            "pal": int(local["palabras_anotadas"]), "web": numero(local["paginas_web_pct"]),
            "pp": il * ipl, "xp": excl_pos, "cp": p["comentarios_pos"],
        })
        filas_final.append({
            "codigo": f["codigo"], "pais": f["pais"], "tipo": f["tipo"], "region": f["region"],
            "indice_genealogia": ig, "indice_operabilidad": io, "prioridad_genealogia": ig * io,
            "excluido_genealogia": "si" if excl_gen else "no",
            "motivo_exclusion_genealogia":
                f"IO {io} (se excluye con IO <= {UMBRAL_GENEALOGIA}): {f['comentarios']}" if excl_gen else "",
            "indice_lectura": il, "indice_pos_local": ipl, "prioridad_pos": il * ipl,
            "excluido_pos": "si" if excl_pos else "no",
            "motivo_exclusion_pos":
                f"IP local {ipl}: la lengua local ({local['lengua']}) no tiene palabras anotadas en "
                "Universal Dependencies ni en MasakhaPOS" if excl_pos else "",
        })

    gen_ordenado = [c for *_, c in sorted(filas_gen, key=lambda x: (x[0], x[1], x[2]))]
    pos_ordenado = [c for *_, c in sorted(filas_pos, key=lambda x: (x[0], x[1], x[2]))]

    texto_gen = tabla_md(
        "Listado de países: genealogía",
        "Generado por `generar_tabla.py` desde `paises.csv`. No editar a mano. Población 2024 de Our "
        "World in Data; PIB nominal del Banco Mundial; PIB PPA (Banco Mundial) e IDH 2023 (PNUD) vía "
        "Gapminder. "
        "Ver `README.md`, «Estado de los datos».",
        ["País", "Tipo", "Región", "Población 2024 (M)", "Nacidos en la historia (M)",
         "IDH 2023", "PIB pc nominal 2023 (USD)", "PIB pc PPA 2023 ($ int. 2021)",
         "Internet (% pob.)", "Emigrantes (% pob.)", "Genealogía (IG)",
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
               (SALIDA_FINAL, texto_csv(COLUMNAS_FINAL, filas_final)), (SALIDA_VISTA, vista_html(registros))]
    if comprobar:
        desactualizadas = []
        for ruta, texto in salidas:
            if not ruta.exists():
                desactualizadas.append(ruta.name)
                continue
            with ruta.open(encoding="utf-8", newline="") as f:  # newline="" conserva los \r\n del CSV
                if f.read() != texto:
                    desactualizadas.append(ruta.name)
        if desactualizadas:
            sys.exit(f"No están al día: {', '.join(desactualizadas)}. "
                     "Ejecuta python3 generar_tabla.py y confirma el resultado.")
    else:
        for ruta, texto in salidas:
            with ruta.open("w", encoding="utf-8", newline="") as f:
                f.write(texto)

    accion = "al día" if comprobar else "escritas"
    print(f"{len(paises)} países. Genealogía: {excluidos_gen} excluidos ({SALIDA_GENEALOGIA.name}). "
          f"POS: {excluidos_pos} excluidos ({SALIDA_POS.name}). Tablas, {SALIDA_FINAL.name} y "
          f"{SALIDA_VISTA.name} {accion}.")


if __name__ == "__main__":
    main(comprobar="--comprobar" in sys.argv[1:])
