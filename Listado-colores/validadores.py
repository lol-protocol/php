"""Validación de los CSV de Listado-colores: cada validar_* agrega mensajes a `errores` y no escribe nada.

Lo usa generar-tabla.py (y las pruebas, que lo alcanzan a través de él).
"""
import re

import cobertura
from constantes import *  # noqa: F401,F403
from md import texto_valido



def validar_anotada(filas, errores):
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        if any(not (fila[c] or "").strip() for c in COLUMNAS_ANOTADA):
            errores.append(f"colores.csv línea {n}: hay campos vacíos")
            continue
        if any("|" in v or "\n" in v for v in fila.values()):
            errores.append(f"colores.csv línea {n}: no puede llevar '|' ni saltos de línea")
        nombre = fila["nombre_ingles"]
        if not RE_NOMBRE_CSS.fullmatch(nombre):
            errores.append(f"colores.csv línea {n}: nombre_ingles debe ser minúsculas sin espacios: {nombre!r}")
        if nombre in vistos:
            errores.append(f"colores.csv línea {n}: nombre repetido: {nombre!r}")
        vistos.add(nombre)
        natural = fila["forma_natural_en"]
        if not RE_FORMA_NATURAL.fullmatch(natural) or natural.replace(" ", "") != nombre:
            errores.append(f"colores.csv línea {n}: forma_natural_en debe ser {nombre!r} con espacios entre palabras: {natural!r}")
        if not RE_HEX.fullmatch(fila["hex"]):
            errores.append(f"colores.csv línea {n}: hex inválido {fila['hex']!r} (#RRGGBB en mayúsculas)")
    nombres = [f["nombre_ingles"] for f in filas]
    if nombres != sorted(nombres):
        errores.append("colores.csv: las filas deben estar ordenadas por nombre_ingles")


def validar_completa(filas, errores):
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        nombre = fila["nombre_ingles"]
        if not nombre.strip() or nombre != nombre.strip():
            errores.append(f"nombres-completos.csv línea {n}: nombre vacío o con espacios sobrantes")
            continue
        if any(c in nombre for c in '|,"\n'):
            errores.append(f"nombres-completos.csv línea {n}: no puede llevar '|', ',', comillas ni saltos de línea")
        if nombre.lower() in vistos:
            errores.append(f"nombres-completos.csv línea {n}: nombre repetido (sin distinguir mayúsculas): {nombre!r}")
        vistos.add(nombre.lower())
        if not RE_HEX.fullmatch(fila["hex"]):
            errores.append(f"nombres-completos.csv línea {n}: hex inválido {fila['hex']!r}")
        if fila["curada"] not in ("", "x"):
            errores.append(f"nombres-completos.csv línea {n}: curada debe ser vacía o 'x'")
    claves = [(f["nombre_ingles"].lower(), f["nombre_ingles"]) for f in filas]
    if claves != sorted(claves):
        errores.append("nombres-completos.csv: las filas deben estar ordenadas por nombre")


def validar_significados(filas, anotada, errores):
    """Cada color de colores.csv debe tener su par de significados en cada idioma, salvo el español."""
    orden = {codigo: n for n, codigo in enumerate(c for c, _ in IDIOMAS if c != "es")}
    es = {f["nombre_ingles"]: f for f in anotada}
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        idioma, nombre = fila["idioma"], fila["nombre_ingles"]
        if idioma not in orden:
            errores.append(f"significados-idiomas.csv línea {n}: idioma desconocido o español: {idioma!r}")
            continue
        if nombre not in es:
            errores.append(f"significados-idiomas.csv línea {n}: {nombre!r} no está en colores.csv")
            continue
        if (idioma, nombre) in vistos:
            errores.append(f"significados-idiomas.csv línea {n}: repetido {idioma}/{nombre}")
        vistos.add((idioma, nombre))
        for campo in ("significado_psicologico", "significado_mistico"):
            valor = fila[campo]
            if not valor.strip() or valor != valor.strip():
                errores.append(f"significados-idiomas.csv línea {n}: {campo} vacío o con espacios sobrantes")
            elif "|" in valor or "\n" in valor:
                errores.append(f"significados-idiomas.csv línea {n}: {campo} no puede llevar '|' ni saltos de línea")
            elif valor == es[nombre][campo]:
                errores.append(f"significados-idiomas.csv línea {n}: {campo} de {idioma}/{nombre} está sin traducir")
    faltan = [(c, nom) for c in orden for nom in es if (c, nom) not in vistos]
    if faltan:
        ejemplo = ", ".join(f"{c}/{nom}" for c, nom in faltan[:5])
        errores.append(f"significados-idiomas.csv: faltan {len(faltan)} combinaciones idioma/color (por ejemplo {ejemplo})")
    claves = [(f["nombre_ingles"], orden.get(f["idioma"], -1)) for f in filas]
    if claves != sorted(claves):
        errores.append("significados-idiomas.csv: las filas deben ir ordenadas por nombre_ingles y, dentro, por el orden de IDIOMAS en generar-tabla.py")


def ids_de(campo):
    return [i for i in campo.split(";") if i]


def marcar_referencias(lista, ids, usadas, errores, archivo, n):
    """Marca cada id de `lista` como usada y reporta en `errores` los que no estén en `ids`."""
    for i in lista:
        usadas.add(i)
        if i not in ids:
            errores.append(f"{archivo} línea {n}: referencia desconocida {i!r}")


def validar_referencias(filas, errores):
    ids = []
    for n, fila in enumerate(filas, start=2):
        i = fila["id"]
        if not RE_ID_REFERENCIA.fullmatch(i):
            errores.append(f"referencias.csv línea {n}: id no válido {i!r}")
        if i in ids:
            errores.append(f"referencias.csv línea {n}: id repetido {i!r}")
        ids.append(i)
        if fila["tipo"] not in TIPOS_REFERENCIA or fila["verificacion"] not in VERIFICACION:
            errores.append(f"referencias.csv línea {n}: tipo o verificacion no válidos ({fila['tipo']}, {fila['verificacion']})")
        if not texto_valido(fila["cita"]):
            errores.append(f"referencias.csv línea {n}: cita vacía o con '|' o saltos de línea")
        if fila["url"] and not fila["url"].startswith("https://"):
            errores.append(f"referencias.csv línea {n}: la url debe empezar por https://")
        if fila["tipo"] == "web" and not fila["url"]:
            errores.append(f"referencias.csv línea {n}: una página web necesita url")
    if ids != sorted(ids):
        errores.append("referencias.csv: las filas deben ir ordenadas por id")
    return set(ids)


def validar_evidencia(filas, anotada, ids, errores, usadas):
    if [f["nombre_ingles"] for f in filas] != [f["nombre_ingles"] for f in anotada]:
        errores.append("evidencia.csv: debe tener una fila por color de colores.csv, en el mismo orden")
        return
    con_estudios = set()
    for n, fila in enumerate(filas, start=2):
        nombre = fila["nombre_ingles"]
        if fila["base_psicologico"] not in BASE_PSICOLOGICO or fila["base_mistico"] not in BASE_MISTICO:
            errores.append(f"evidencia.csv línea {n}: base no válida ({fila['base_psicologico']}, {fila['base_mistico']})")
        if fila["base_psicologico"] == "hay_estudios":
            con_estudios.add(nombre)
        for campo in ("referencias_psicologico", "referencias_mistico"):
            lista = ids_de(fila[campo])
            if lista != sorted(set(lista)):
                errores.append(f"evidencia.csv línea {n}: {campo} debe ir ordenado y sin repetidos")
            marcar_referencias(lista, ids, usadas, errores, "evidencia.csv", n)
        if fila["base_psicologico"] == "hay_estudios" and "jonauskaite2020" not in ids_de(fila["referencias_psicologico"]):
            errores.append(f"evidencia.csv línea {n}: {nombre} tiene estudios pero no cita jonauskaite2020")
        if fila["base_psicologico"] == "derivado" and fila["referencias_psicologico"]:
            errores.append(f"evidencia.csv línea {n}: un significado derivado no lleva referencias psicológicas")
        if fila["base_mistico"] == "tradicion_moderna" and "leland2016" not in ids_de(fila["referencias_mistico"]):
            errores.append(f"evidencia.csv línea {n}: {nombre} es tradición moderna pero no cita leland2016")
        if fila["base_mistico"] == "derivado" and fila["referencias_mistico"]:
            errores.append(f"evidencia.csv línea {n}: un significado derivado no lleva referencias místicas")
    if con_estudios != COLORES_CON_ESTUDIOS:
        errores.append(f"evidencia.csv: los colores con estudios deben ser exactamente {sorted(COLORES_CON_ESTUDIOS)}")


def validar_culturales(filas, anotada, ids, errores, usadas):
    orden = {f["nombre_ingles"]: n for n, f in enumerate(anotada)}
    claves = []
    for n, fila in enumerate(filas, start=2):
        if fila["color"] not in orden or fila["ambito"] not in AMBITOS or fila["solidez"] not in SOLIDEZ:
            errores.append(f"significados-culturales.csv línea {n}: color, ámbito o solidez no válidos ({fila['color']}, {fila['ambito']}, {fila['solidez']})")
            continue
        claves.append((orden[fila["color"]], fila["ambito"]))
        if not texto_valido(fila["matiz"]):
            errores.append(f"significados-culturales.csv línea {n}: matiz vacío o con '|' o saltos de línea")
        lista = ids_de(fila["referencias"])
        if not lista:
            errores.append(f"significados-culturales.csv línea {n}: cada matiz necesita al menos una referencia")
        marcar_referencias(lista, ids, usadas, errores, "significados-culturales.csv", n)
    if claves != sorted(claves):
        errores.append("significados-culturales.csv: las filas deben ir ordenadas por color (orden de colores.csv) y ámbito")


def hex_de_concepto(concepto, anotada, completa):
    """Hex de referencia de un concepto: el del color CSS o, si no lo hay, el del nombre de la lista completa."""
    for f in anotada:
        if f["nombre_ingles"] == concepto:
            return f["hex"]
    for f in completa:
        if f["nombre_ingles"].lower() == concepto:
            return f["hex"]
    return None


def hex_cotidiano_de_concepto(concepto, completa, anotada):
    """Hex del nombre de uso corriente (la entrada curada de la lista completa con el mismo nombre) o "" si no hay.

    No sustituye al hex de referencia: el color CSS `lime` es verde puro y `khaki` un amarillo pálido, y quien dice «lima» o
    «caqui» piensa en otro matiz. Se descarta (queda "") si la entrada es el hex de *otro* color CSS: en la lista completa
    `green` es #00FF00, que es `lime` de CSS, y eso no es un verde de uso corriente sino otro color con el mismo nombre.
    """
    for f in completa:
        if f["nombre_ingles"].lower() == concepto and f["curada"] == "x":
            de_otro = any(c["hex"] == f["hex"] and c["nombre_ingles"] != concepto for c in anotada)
            return "" if de_otro else f["hex"]
    return ""


def validar_lexico(filas, anotada, completa, errores):
    """El léxico básico: valida cada fila y que cada concepto tenga su lema en cada idioma donde aparece."""
    codigos = [c for c, _ in IDIOMAS]
    orden_tipo = {tipo: n for n, tipo in enumerate(TIPOS_LEXICO)}
    for concepto in CONCEPTOS:
        if hex_de_concepto(concepto, anotada, completa) is None:
            errores.append(f"lexico-basico.csv: el concepto {concepto!r} no está en colores.csv ni en nombres-completos.csv")
    vistos, lemas, presentes = set(), {}, set()
    claves = []
    for n, fila in enumerate(filas, start=2):
        concepto, idioma, forma, tipo, rasgos = (fila[c] for c in COLUMNAS_LEXICO)
        if concepto not in CONCEPTOS or idioma not in codigos or tipo not in TIPOS_LEXICO:
            errores.append(f"lexico-basico.csv línea {n}: concepto, idioma o tipo desconocido ({concepto}, {idioma}, {tipo})")
            continue
        claves.append((CONCEPTOS.index(concepto), codigos.index(idioma), orden_tipo[tipo]))
        if not texto_valido(forma, '|\n"', sin_espacios_sobrantes=True):
            errores.append(f"lexico-basico.csv línea {n}: forma vacía, con espacios sobrantes o con '|', comillas o saltos de línea")
        if idioma in SIN_LETRAS_LATINAS and re.search(r"[A-Za-z]", forma):
            errores.append(f"lexico-basico.csv línea {n}: letras latinas en {idioma}: {forma!r}")
        if tipo == "flexion" and rasgos not in RASGOS_FLEXION:
            errores.append(f"lexico-basico.csv línea {n}: rasgos de flexión no válidos: {rasgos!r}")
        if tipo == "lema" and rasgos not in ("", ORIGEN_IA):
            errores.append(f"lexico-basico.csv línea {n}: un lema no lleva rasgos (solo {ORIGEN_IA!r} si es una propuesta sin revisar)")
        if tipo in ("sinonimo", "variante") and rasgos and not RE_ETIQUETA.fullmatch(rasgos):
            errores.append(f"lexico-basico.csv línea {n}: etiqueta no válida: {rasgos!r}")
        clave = (concepto, idioma, forma, tipo, rasgos)
        if clave in vistos:
            errores.append(f"lexico-basico.csv línea {n}: fila repetida {clave}")
        vistos.add(clave)
        presentes.add((concepto, idioma))
        if tipo == "lema":
            if (concepto, idioma) in lemas:
                errores.append(f"lexico-basico.csv línea {n}: {concepto}/{idioma} tiene más de un lema")
            lemas[(concepto, idioma)] = forma
    for concepto, idioma in sorted(presentes, key=lambda par: (CONCEPTOS.index(par[0]), codigos.index(par[1]))):
        if (concepto, idioma) not in lemas:
            errores.append(f"lexico-basico.csv: {concepto}/{idioma} tiene formas pero no lema")
    faltan = [(c, i) for c in BASICOS for i in codigos if (c, i) not in lemas]
    if faltan:
        ejemplo = ", ".join(f"{c}/{i}" for c, i in faltan[:5])
        errores.append(f"lexico-basico.csv: faltan {len(faltan)} términos básicos (por ejemplo {ejemplo})")
    if claves != sorted(claves):
        errores.append("lexico-basico.csv: las filas deben ir ordenadas por concepto, idioma y tipo (lema, flexion, sinonimo, variante)")


def alias_de(anotada):
    """Para cada color CSS, los otros que tienen su mismo hex: [(nombre, tipo)].

    El tipo es 'ortografia' si solo cambia gray por grey (la especificación CSS define las dos
    grafías) y 'mismo_color' si son nombres distintos del mismo color (aqua y cyan, fuchsia y magenta).
    """
    por_hex = {}
    for f in anotada:
        por_hex.setdefault(f["hex"], []).append(f["nombre_ingles"])
    alias = {f["nombre_ingles"]: [] for f in anotada}
    for nombres in por_hex.values():
        for a in nombres:
            for b in nombres:
                if a != b:
                    tipo = "ortografia" if a.replace("grey", "gray") == b.replace("grey", "gray") else "mismo_color"
                    alias[a].append((b, tipo))
    return alias


def validar_colisiones(revisadas, anotada, errores, idiomas=IDIOMAS):
    """Un idioma que llama igual a dos colores de distinto hex tiene que estar en colisiones-revisadas.csv, y esa lista no puede
    guardar casos que ya no existen: así una colisión nueva (o una corregida) no pasa sin que alguien la vea."""
    actuales = {(codigo, nombre.casefold(), "|".join(colores))
                for codigo, casos in cobertura.colisiones_nombres(idiomas, anotada).items() for nombre, colores in casos
                if nombre.strip()}  # un nombre vacío ya lo marca validar_anotada
    listadas = {(f["idioma"], f["nombre"].casefold(), f["colores"]) for f in revisadas}
    if len(listadas) != len(revisadas):
        errores.append("colisiones-revisadas.csv: hay filas repetidas")
    for codigo, nombre, colores in sorted(actuales - listadas):
        errores.append(f"colisión nueva sin revisar: en {codigo}, «{nombre}» nombra a {colores.replace('|', ', ')} (añádela a colisiones-revisadas.csv si es correcta)")
    for codigo, nombre, colores in sorted(listadas - actuales):
        errores.append(f"colisiones-revisadas.csv: ya no existe el caso {codigo} «{nombre}» ({colores.replace('|', ', ')}); quítalo")


def validar_alias(anotada, traducidos, errores, alias=None):
    """Dos colores con el mismo hex (aqua y cyan, gray y grey) dicen lo mismo: los dos significados, en español y en cada idioma."""
    significados = {(f["idioma"], f["nombre_ingles"]): (f["significado_psicologico"], f["significado_mistico"]) for f in traducidos}
    for nombre, otros in (alias_de(anotada) if alias is None else alias).items():
        for otro, _ in otros:
            if nombre >= otro:  # cada par una vez
                continue
            propio = next(f for f in anotada if f["nombre_ingles"] == nombre)
            ajeno = next(f for f in anotada if f["nombre_ingles"] == otro)
            if (propio["significado_psicologico"], propio["significado_mistico"]) != (ajeno["significado_psicologico"], ajeno["significado_mistico"]):
                errores.append(f"colores.csv: {nombre} y {otro} tienen el mismo hex y deben tener los mismos significados en español")
            distintos = sorted(i for (i, n), par in significados.items() if n == nombre and par != significados.get((i, otro)))
            if distintos:
                errores.append(f"significados-idiomas.csv: {nombre} y {otro} tienen el mismo hex y deben tener los mismos significados; difieren en {', '.join(distintos)}")
