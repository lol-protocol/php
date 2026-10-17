#!/usr/bin/env python3
"""Valida paises.csv y paises_pos.csv y genera dos tablas ordenadas por prioridad.

Uso: python3 generar_tabla.py               valida y escribe las dos tablas
     python3 generar_tabla.py --comprobar   valida y falla si las tablas no están al día (no escribe)
     (solo biblioteca estándar de Python 3)

Genealogía (paises.csv):      prioridad = IG x IO;  excluido si IO <= UMBRAL_GENEALOGIA
Etiquetador POS (paises_pos.csv): prioridad = IL x IP local;  excluido si IP local <= UMBRAL_POS
  IP debe ser el máximo de los niveles (niveles_lenguas.csv) de las lenguas de idioma_principal.
  IP local debe ser el nivel de la primera lengua listada (la lengua local).
"""
import csv
import sys
from pathlib import Path

AQUI = Path(__file__).resolve().parent
PAISES = AQUI / "paises.csv"
POS = AQUI / "paises_pos.csv"
NIVELES = AQUI / "niveles_lenguas.csv"
SALIDA_GENEALOGIA = AQUI / "tabla_genealogia.md"
SALIDA_POS = AQUI / "tabla_pos.md"

COLUMNAS_PAISES = [
    "codigo", "pais", "tipo", "region", "poblacion_millones",
    "poblacion_historica_acumulada", "idh", "pib_per_capita_usd", "pib_per_capita_ppa_usd",
    "alfabetizacion_adultos", "usuarios_internet", "indice_genealogia", "indice_operabilidad", "comentarios",
]
COLUMNAS_POS = ["codigo", "idioma_principal", "indice_lectura", "indice_pos", "indice_pos_local",
                "comentarios_pos"]
TIPOS = {"estado", "territorio", "entidad disputada"}
NUMERICAS_PAISES = ["poblacion_millones", "poblacion_historica_acumulada", "idh",
                    "pib_per_capita_usd", "pib_per_capita_ppa_usd", "alfabetizacion_adultos",
                    "usuarios_internet"]
RANGOS = {"idh": (0, 1), "alfabetizacion_adultos": (0, 100), "usuarios_internet": (0, 100)}
ESCALAS_PAISES = ["indice_genealogia", "indice_operabilidad"]
ESCALAS_POS = ["indice_lectura", "indice_pos", "indice_pos_local"]
UMBRAL_GENEALOGIA = 1  # IO <= 1: inviable para operar
UMBRAL_POS = 1  # IP local <= 1: la lengua local no tiene recursos para un etiquetador


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


def celda(valor, vacio="—"):
    return valor if valor else vacio


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
    niveles = {f["lengua"]: int(f["nivel"]) for _, f in leer(NIVELES, ["lengua", "nivel"])}
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

    codigos_paises = [f["codigo"] for _, f in paises]
    codigos_pos = [f["codigo"] for _, f in pos]
    for nombre, codigos in (("paises.csv", codigos_paises), ("paises_pos.csv", codigos_pos)):
        repetidos = {c for c in codigos if codigos.count(c) > 1}
        if repetidos:
            errores.append(f"{nombre}: códigos repetidos {sorted(repetidos)}")
    faltan = set(codigos_paises) ^ set(codigos_pos)
    if faltan:
        errores.append(f"los dos archivos no tienen los mismos códigos; diferencia: {sorted(faltan)}")
    if errores:
        sys.exit("Errores en los CSV:\n  " + "\n  ".join(errores))

    pos_por_codigo = {f["codigo"]: f for _, f in pos}

    filas_gen, filas_pos = [], []
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
            celda(f["poblacion_historica_acumulada"], "pendiente"),
            celda(f["idh"]), celda(f["pib_per_capita_usd"]),
            celda(f["pib_per_capita_ppa_usd"]), celda(f["usuarios_internet"]),
            str(ig), str(io), str(ig * io), "sí" if excl_gen else "no", f["comentarios"],
        ]))
        filas_pos.append((excl_pos, -il * ipl, f["pais"], [
            f["pais"], p["idioma_principal"], f["tipo"],
            celda(f["alfabetizacion_adultos"]), celda(f["usuarios_internet"]), str(il), str(ip), str(ipl), str(il * ipl), "sí" if excl_pos else "no", p["comentarios_pos"],
        ]))

    gen_ordenado = [c for *_, c in sorted(filas_gen, key=lambda x: (x[0], x[1], x[2]))]
    pos_ordenado = [c for *_, c in sorted(filas_pos, key=lambda x: (x[0], x[1], x[2]))]

    texto_gen = tabla_md(
        "Listado de países: genealogía",
        "Generado por `generar_tabla.py` desde `paises.csv`. No editar a mano. Población 2024 y PIB PPA "
        "de Our World in Data; PIB nominal del Banco Mundial; IDH 2023 del PNUD vía Gapminder. "
        "Ver `README.md`, «Estado de los datos».",
        ["País", "Tipo", "Región", "Población 2024 (M)", "Nacidos en la historia (M)",
         "IDH 2023", "PIB pc nominal 2023 (USD)", "PIB pc PPA 2022 ($ int. 2011)",
         "Internet (% pob.)", "Genealogía (IG)",
         "Operabilidad (IO)", "Prioridad", "Excluido", "Comentarios"],
        gen_ordenado)
    texto_pos = tabla_md(
        "Listado de países: etiquetador POS",
        "Generado por `generar_tabla.py` desde `paises.csv` y `paises_pos.csv`. No editar a mano. "
        "Alfabetización de la UNESCO e internet del Banco Mundial, vía Gapminder (último dato de cada "
        "país); índices de juicio propio. Ver `README.md`, «Etiquetador POS».",
        ["País", "Idiomas (local primero)", "Tipo", "Alfabetización adultos (%)", "Internet (% pob.)",
         "Lectura (IL)", "IP (mejor lengua)",
         "IP local", "Prioridad (IL × IP local)", "Excluido", "Comentarios"],
        pos_ordenado)

    if comprobar:
        desactualizadas = [ruta.name for ruta, texto in ((SALIDA_GENEALOGIA, texto_gen), (SALIDA_POS, texto_pos))
                           if not ruta.exists() or ruta.read_text(encoding="utf-8") != texto]
        if desactualizadas:
            sys.exit(f"No están al día: {', '.join(desactualizadas)}. "
                     "Ejecuta python3 generar_tabla.py y confirma el resultado.")
    else:
        SALIDA_GENEALOGIA.write_text(texto_gen, encoding="utf-8")
        SALIDA_POS.write_text(texto_pos, encoding="utf-8")

    accion = "al día" if comprobar else "escritas"
    print(f"{len(paises)} países. Genealogía: {excluidos_gen} excluidos ({SALIDA_GENEALOGIA.name}). "
          f"POS: {excluidos_pos} excluidos ({SALIDA_POS.name}). Tablas {accion}.")


if __name__ == "__main__":
    main(comprobar="--comprobar" in sys.argv[1:])
