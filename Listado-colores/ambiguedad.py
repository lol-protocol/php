"""Informe de ambigüedad de Listado-colores: formas que designan más de un color.

Solo biblioteca estándar. Lo usa generar-tabla.py con las filas del gazetteer (exportes.filas_gazetteer) y la lista de
idiomas, y genera ambiguedad.md. Una forma es ambigua si en el gazetteer remite a más de un hex: los alias CSS (mismo
hex: gray y grey, aqua y cyan) no cuentan, porque son el mismo color.

Cuatro listas:
1. Dentro de un idioma, una misma forma con varios colores.
2. Entre idiomas, la misma forma con colores distintos en cada uno.
3. Dentro de un idioma, formas distintas que solo coinciden al normalizarlas (ver normalizacion.py).
4. Entre idiomas, igual que la 3.
En la 3 y la 4 solo entran los casos que no estén ya en la 1 y la 2.
"""
import colorimetria

# Distancia CIEDE2000 máxima entre los colores de una forma, en bandas. Son los umbrales de cercanía del proyecto
# (3 y 8, ver README), no un estándar.
BANDAS = [("casi iguales", 3.0), ("parecidos", 8.0)]
BANDA_MAYOR = "distintos"
NOMBRES_BANDAS = [nombre for nombre, _ in BANDAS] + [BANDA_MAYOR]
LISTAS = [
    ("dentro", "Dentro de un idioma"),
    ("entre", "Entre idiomas"),
    ("dentro_normalizada", "Dentro de un idioma, solo al normalizar"),
    ("entre_normalizada", "Entre idiomas, solo al normalizar"),
]


def banda(delta):
    for nombre, tope in BANDAS:
        if delta <= tope:
            return nombre
    return BANDA_MAYOR


def _agrupar(filas, clave_de):
    """{clave: {idioma: {'formas': {formas}, 'colores': {hex: {conceptos}}}}} con la clave que dé clave_de(fila)."""
    grupos = {}
    for fila in filas:
        forma, idioma, concepto, color = fila[0], fila[1], fila[4], fila[5]
        por_idioma = grupos.setdefault(clave_de(fila), {}).setdefault(idioma, {"formas": set(), "colores": {}})
        por_idioma["formas"].add(forma)
        por_idioma["colores"].setdefault(color, set()).add(concepto)
    return grupos


def _clave_exacta(fila):
    return fila[0].casefold()


def _clave_normalizada(fila):
    return fila[7]


def _distancia_maxima(colores):
    """La mayor distancia CIEDE2000 entre dos de los hex dados (0 si hay uno solo)."""
    labs = [colorimetria.hex_a_lab(c) for c in sorted(set(colores))]
    return max((colorimetria.ciede2000(a, b) for i, a in enumerate(labs) for b in labs[i + 1:]), default=0.0)


def _colores(por_hex):
    """[(hex, [conceptos ordenados])] ordenado por hex."""
    return [(color, sorted(conceptos)) for color, conceptos in sorted(por_hex.items())]


def _varios_colores_en_un_idioma(grupos):
    return {(idioma, clave) for clave, por_idioma in grupos.items()
            for idioma, datos in por_idioma.items() if len(datos["colores"]) > 1}


def _colores_distintos_entre_idiomas(grupos):
    return {clave for clave, por_idioma in grupos.items()
            if len(por_idioma) > 1 and len({frozenset(datos["colores"]) for datos in por_idioma.values()}) > 1}


def calcular(filas, codigos):
    """Las cuatro listas de ambigüedades (claves de LISTAS). `codigos` son los códigos de idioma, en el orden de las tablas.

    Cada elemento lleva `delta`, la distancia máxima entre sus colores. Los de las listas por idioma llevan `idioma`, `forma`
    (o `formas` y `clave` en la 3 y la 4) y `colores` [(hex, [conceptos])]; los de las listas entre idiomas, `idiomas`
    [(idioma, formas, colores)].
    """
    orden = {codigo: n for n, codigo in enumerate(codigos)}
    exactas = _agrupar(filas, _clave_exacta)
    normalizadas = _agrupar(filas, _clave_normalizada)
    # Qué formas exactas (por idioma) tiene cada grupo normalizado: la 3 y la 4 no repiten lo que ya está en la 1 y la 2.
    exactas_de_norma = {}
    for fila in filas:
        exactas_de_norma.setdefault(fila[7], set()).add((fila[1], _clave_exacta(fila)))
    dentro_exactas = _varios_colores_en_un_idioma(exactas)
    entre_exactas = _colores_distintos_entre_idiomas(exactas)

    def por_idiomas(grupo):
        return [(idioma, sorted(grupo[idioma]["formas"]), _colores(grupo[idioma]["colores"])) for idioma in sorted(grupo, key=orden.get)]

    def union(grupo):
        return {color for datos in grupo.values() for color in datos["colores"]}

    resultado = {nombre: [] for nombre, _ in LISTAS}
    for idioma, clave in sorted(dentro_exactas, key=lambda par: (orden[par[0]], par[1])):
        datos = exactas[clave][idioma]
        resultado["dentro"].append({"idioma": idioma, "forma": min(datos["formas"]), "colores": _colores(datos["colores"]),
                                    "delta": _distancia_maxima(datos["colores"])})
    for clave in sorted(entre_exactas):
        grupo = exactas[clave]
        resultado["entre"].append({"forma": min(f for datos in grupo.values() for f in datos["formas"]), "idiomas": por_idiomas(grupo),
                                   "delta": _distancia_maxima(union(grupo))})
    for idioma, clave in sorted(_varios_colores_en_un_idioma(normalizadas), key=lambda par: (orden[par[0]], par[1])):
        if any(par in dentro_exactas for par in exactas_de_norma[clave] if par[0] == idioma):
            continue
        datos = normalizadas[clave][idioma]
        resultado["dentro_normalizada"].append({"idioma": idioma, "formas": sorted(datos["formas"]), "clave": clave,
                                                "colores": _colores(datos["colores"]), "delta": _distancia_maxima(datos["colores"])})
    for clave in sorted(_colores_distintos_entre_idiomas(normalizadas)):
        if any(exacta in entre_exactas for _, exacta in exactas_de_norma[clave]):
            continue
        grupo = normalizadas[clave]
        resultado["entre_normalizada"].append({"clave": clave, "idiomas": por_idiomas(grupo), "delta": _distancia_maxima(union(grupo))})
    return resultado


def _celda(texto):
    return str(texto).replace("|", "&#124;")


def _colores_md(colores):
    return " · ".join(f"{', '.join('`' + c + '`' for c in conceptos)} `{color}`" for color, conceptos in colores)


def _formas_md(formas):
    return ", ".join(f"«{_celda(f)}»" for f in formas)


def _idiomas_md(idiomas, con_formas):
    partes = []
    for idioma, formas, colores in idiomas:
        etiqueta = f"`{idioma}`" + (f" {_formas_md(formas)}" if con_formas else "")
        partes.append(f"{etiqueta}: {_colores_md(colores)}")
    return "; ".join(partes)


def informe(filas, idiomas):
    """El texto de ambiguedad.md. `idiomas` es la lista [(código, nombre)] de generar-tabla.py."""
    codigos = [c for c, _ in idiomas]
    listas = calcular(filas, codigos)
    por_banda = {nombre: {b: sum(1 for e in listas[nombre] if banda(e["delta"]) == b) for b in NOMBRES_BANDAS} for nombre, _ in LISTAS}
    partes = [
        "# Ambigüedad de las formas de color",
        "",
        "Generado por `generar-tabla.py`. No editar a mano.",
        "",
        "Una forma es *ambigua* si en el gazetteer (`exportes/gazetteer.tsv`) remite a más de un color, es decir, a más de un hex. "
        "Los alias CSS (`gray` y `grey`, `aqua` y `cyan`) comparten hex y no cuentan. Sirve para saber qué formas no bastan "
        "por sí solas para decidir de qué color se habla, y qué traducciones usan la misma palabra para colores distintos.",
        "",
        "Para cada caso se da la distancia máxima CIEDE2000 (ΔE) entre sus colores, con su banda: *casi iguales* (hasta 3), "
        "*parecidos* (hasta 8) y *distintos* (más de 8). Son los umbrales de cercanía del proyecto, no un estándar.",
        "",
        "Límites: solo cubre las formas del gazetteer (los colores CSS en 30 idiomas y el léxico básico). No detecta palabras que, "
        "además de un color, son otra cosa (una flor, una fruta), ni palabras fuera del listado. Parte de las ambigüedades puede "
        "venir de la traducción, que es una propuesta sin revisión de hablantes nativos: una forma que remite a colores muy "
        "distintos en un idioma es una candidata a revisar. Las listas 3 y 4 usan la forma normalizada (ver el README).",
        "",
        "## Resumen",
        "",
        "| Lista | Casos | " + " | ".join(NOMBRES_BANDAS) + " |",
        "|---|---|" + "---|" * len(NOMBRES_BANDAS),
    ]
    for numero, (nombre, titulo) in enumerate(LISTAS, start=1):
        partes.append(f"| {numero}. {titulo} | {len(listas[nombre])} | " + " | ".join(str(por_banda[nombre][b]) for b in NOMBRES_BANDAS) + " |")
    cuentas = {codigo: sum(1 for e in listas["dentro"] if e["idioma"] == codigo) for codigo in codigos}
    partes += ["", "Casos de la lista 1 por idioma: " + (", ".join(f"`{c}` {n}" for c, n in cuentas.items() if n) or "ninguno") + ".", ""]

    partes += ["## 1. Dentro de un idioma: una forma, varios colores", ""]
    partes += _tabla(listas["dentro"], "| Idioma | Forma | Colores | ΔE máx. | Banda |", "|---|---|---|---|---|",
                     lambda e: f"| `{e['idioma']}` | {_formas_md([e['forma']])} | {_colores_md(e['colores'])} | {e['delta']:.1f} | {banda(e['delta'])} |")
    partes += ["## 2. Entre idiomas: la misma forma, otro color", ""]
    partes += _tabla(listas["entre"], "| Forma | Colores por idioma | ΔE máx. | Banda |", "|---|---|---|---|",
                     lambda e: f"| {_formas_md([e['forma']])} | {_idiomas_md(e['idiomas'], False)} | {e['delta']:.1f} | {banda(e['delta'])} |")
    partes += ["## 3. Dentro de un idioma, solo al normalizar", ""]
    partes += _tabla(listas["dentro_normalizada"], "| Idioma | Formas | Normalizada | Colores | ΔE máx. | Banda |", "|---|---|---|---|---|---|",
                     lambda e: f"| `{e['idioma']}` | {_formas_md(e['formas'])} | `{_celda(e['clave'])}` | {_colores_md(e['colores'])} "
                               f"| {e['delta']:.1f} | {banda(e['delta'])} |")
    partes += ["## 4. Entre idiomas, solo al normalizar", ""]
    partes += _tabla(listas["entre_normalizada"], "| Normalizada | Formas y colores por idioma | ΔE máx. | Banda |", "|---|---|---|---|",
                     lambda e: f"| `{_celda(e['clave'])}` | {_idiomas_md(e['idiomas'], True)} | {e['delta']:.1f} | {banda(e['delta'])} |")
    return "\n".join(partes)


def _tabla(elementos, cabecera, separador, fila_de):
    if not elementos:
        return ["No hay casos.", ""]
    return [cabecera, separador, *[fila_de(e) for e in elementos], ""]
