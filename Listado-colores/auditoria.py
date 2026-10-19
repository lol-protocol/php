"""Auditoría automática de nombres-completos.csv: marca los nombres sospechosos, sin corregir nada.

Solo biblioteca estándar. Lo usa generar-tabla.py con las filas ya validadas de nombres-completos.csv y genera
auditoria-nombres.md. El README admite que los 31.935 nombres no se han verificado uno a uno contra otra fuente; esto
no lo hace, pero mira cosas que sí se pueden comprobar sin una fuente: caracteres raros, mojibake, forma Unicode,
mayúsculas, frases, duplicados que solo se distinguen por un acento o un guion, y hex repetidos. Un hallazgo no es un error:
el generador sigue aceptando el archivo.
"""
import re
import unicodedata

from normalizacion import normalizar

# Cuántos ejemplos se muestran por comprobación, y cuántos casos de las listas largas.
EJEMPLOS = 5
MAXIMO_LISTA = 25
APOSTROFO_TIPOGRAFICO = chr(0x2019)
# Lo que suele salir de leer UTF-8 como Latin-1 (Ã©, â€™, Â…) o de un carácter que no se pudo leer (U+FFFD).
RE_MOJIBAKE = re.compile("Ã[\x80-\xbf]|â€|Â[\x80-\xbf ]|ï¿½|" + chr(0xFFFD))


def _clave(nombre):
    return (nombre.casefold(), nombre)


def _alguno(nombre, categoria, salvo=""):
    return any(unicodedata.category(c) == categoria and c != salvo for c in nombre)


# (clave, título, predicado). Los primeros DEFECTOS buscan defectos: lo normal es que no haya ninguno. Los demás describen la lista.
COMPROBACIONES = [
    ("control", "caracteres de control", lambda n: _alguno(n, "Cc")),
    ("formato", "caracteres invisibles o de formato", lambda n: _alguno(n, "Cf")),
    ("espacios", "espacios que no son el espacio normal", lambda n: _alguno(n, "Zs", salvo=" ")),
    ("espacios_dobles", "espacios dobles o en los extremos", lambda n: "  " in n or n != n.strip()),
    ("no_nfc", "forma Unicode distinta de NFC", lambda n: unicodedata.normalize("NFC", n) != n),
    ("mojibake", "posible mojibake (UTF-8 leído como otra codificación)", lambda n: RE_MOJIBAKE.search(n) is not None),
    ("inicia_minuscula", "empiezan por minúscula", lambda n: n[:1].islower()),
    ("apostrofo_ascii", "apóstrofo ASCII (U+0027), distinto del tipográfico de la lista", lambda n: "'" in n),
    ("todo_mayusculas", "todo en mayúsculas", lambda n: n.isupper()),
    ("inicia_digito", "empiezan por un dígito", lambda n: n[:1].isdigit()),
    ("frase", "cuatro palabras o más", lambda n: len(n.split()) >= 4),
    ("letras_no_ascii", "con letras que no son ASCII (acentos, macrones…)", lambda n: any(c.isalpha() and not c.isascii() for c in n)),
    ("apostrofo_tipografico", "con apóstrofo tipográfico (U+2019)", lambda n: APOSTROFO_TIPOGRAFICO in n),
    ("guion", "con guion (U+002D)", lambda n: "-" in n),
]
DEFECTOS = 8


def analizar(completa):
    """Los hallazgos sobre las filas de nombres-completos.csv ({nombre_ingles, hex, curada}).

    Devuelve un diccionario con: `total`, `curadas` (conjunto de nombres curados), `comprobaciones` ({clave: [nombres]}),
    `duplicados` (grupos de nombres que solo se distinguen al normalizarlos, como inglés), `hex_repetidos` ([(hex, [nombres])]),
    `signos` ({carácter: [nombres]} de los que no son letras ni espacio; los dígitos juntos bajo "0-9") y `mas_largos`.
    """
    nombres = [f["nombre_ingles"] for f in completa]
    comprobaciones = {clave: sorted((n for n in nombres if predicado(n)), key=_clave) for clave, _titulo, predicado in COMPROBACIONES}
    por_forma, por_hex, signos = {}, {}, {}
    for f in completa:
        por_forma.setdefault(normalizar(f["nombre_ingles"], "en"), []).append(f["nombre_ingles"])
        por_hex.setdefault(f["hex"], []).append(f["nombre_ingles"])
    for n in nombres:
        for c in set(n):
            if c != " " and not c.isalpha():
                signos.setdefault("0-9" if unicodedata.category(c) == "Nd" else c, set()).add(n)
    return {
        "total": len(nombres),
        "curadas": {f["nombre_ingles"] for f in completa if f["curada"] == "x"},
        "comprobaciones": comprobaciones,
        "duplicados": [sorted(v, key=_clave) for _forma, v in sorted(por_forma.items()) if len(v) > 1],
        "hex_repetidos": [(h, sorted(v, key=_clave)) for h, v in sorted(por_hex.items()) if len(v) > 1],
        "signos": {c: sorted(v, key=_clave) for c, v in signos.items()},
        "mas_largos": sorted(nombres, key=lambda n: (-len(n), _clave(n)))[:EJEMPLOS],
    }


def _celda(texto):
    return str(texto).replace("|", "&#124;")


def _nombres_md(nombres):
    return ", ".join(f"«{_celda(n)}»" for n in nombres)


def _miles(n):
    return f"{n:,}".replace(",", ".")


def _porcentaje(parte, total):
    return f"{100 * parte / total:.1f}".replace(".", ",") + " %" if total else "0 %"


def _nombre_del_signo(c):
    return "dígitos del 0 al 9" if c == "0-9" else unicodedata.name(c, "sin nombre").lower()


def _lista(elementos, cabecera, separador, fila_de):
    if not elementos:
        return ["No hay casos.", ""]
    resto = len(elementos) - MAXIMO_LISTA
    return [cabecera, separador, *[fila_de(e) for e in elementos[:MAXIMO_LISTA]], *([f"| ... y {resto} más |"] if resto > 0 else []), ""]


def informe(completa):
    """El texto de auditoria-nombres.md."""
    a = analizar(completa)
    total, curadas = a["total"], a["curadas"]
    distintos = len({f["hex"] for f in completa})
    partes = [
        "# Auditoría de los nombres completos",
        "",
        "Generado por `generar-tabla.py` a partir de `nombres-completos.csv`. No editar a mano.",
        "",
        f"Revisa automáticamente los {_miles(total)} nombres de la lista completa ({_miles(len(curadas))} curados, "
        f"{_porcentaje(len(curadas), total)}): caracteres raros, forma Unicode, mayúsculas, frases, duplicados que solo se distinguen "
        "por un acento o un guion, y hex repetidos. **Marca casos sospechosos; no corrige nada ni decide cuáles son errores**: un hallazgo "
        "es solo un sitio donde mirar, y el generador sigue aceptando el archivo.",
        "",
        "Lo que no hace: no compara los nombres con otra fuente (el README dice que no se verificaron uno a uno) y no distingue el idioma "
        "de cada nombre. La lista no es solo inglés: hay nombres en pinyin con tonos, en hindi transliterado, en español o en francés, "
        "como `Bái Sè White` o `Gulābī Pink`, y un etiquetador debe esperarlos.",
        "",
        "## Comprobaciones",
        "",
        f"Las {DEFECTOS} primeras buscan defectos (lo normal es que no haya ninguno); las demás describen la lista. «Curados» son los que "
        "llevan una `x` en `curada`.",
        "",
        "| Comprobación | Nombres | Curados | Ejemplos |",
        "|---|---|---|---|",
    ]
    for numero, (clave, titulo, _predicado) in enumerate(COMPROBACIONES):
        casos = a["comprobaciones"][clave]
        if numero == DEFECTOS:
            partes.append("| **Descripción de la lista** | | | |")
        partes.append(f"| {titulo} | {len(casos)} | {sum(1 for n in casos if n in curadas)} | {_nombres_md(casos[:EJEMPLOS])} |")
    partes += [
        "", "## Duplicados al normalizar", "",
        "Nombres distintos que son el mismo al pasarlos por la forma normalizada (sin mayúsculas, tildes ni diferencias de guion; "
        "se normaliza todo como inglés). Pueden ser el mismo nombre escrito de dos maneras o dos palabras distintas.", "",
    ]
    partes += _lista(a["duplicados"], "| Nombres |", "|---|", lambda g: f"| {_nombres_md(g)} |")
    partes += ["## Hex repetidos", "", f"Hex distintos: {_miles(distintos)} para {_miles(total)} nombres.", ""]
    partes += _lista(a["hex_repetidos"], "| Hex | Nombres |", "|---|---|", lambda g: f"| `{g[0]}` | {_nombres_md(g[1])} |")
    partes += [
        "## Signos que no son letras", "",
        "Todo carácter que no es una letra ni un espacio, con cuántos nombres lo llevan y un ejemplo. Los dígitos van juntos.", "",
        "| Carácter | Nombre Unicode | Nombres | Ejemplo |", "|---|---|---|---|",
    ]
    for c, lista in sorted(a["signos"].items(), key=lambda par: (-len(par[1]), par[0])):
        mostrado = c if c == "0-9" else f"`{_celda(c)}` U+{ord(c):04X}"
        partes.append(f"| {mostrado} | {_nombre_del_signo(c)} | {len(lista)} | {_nombres_md(lista[:1])} |")
    partes += ["", "## Los más largos", "", _nombres_md(a["mas_largos"]) + ".", ""]
    return "\n".join(partes)
