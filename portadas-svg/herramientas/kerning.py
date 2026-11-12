#!/usr/bin/env python3
"""Calcula el kerning (el ajuste entre pares de letras) de las fuentes del proyecto y lo guarda en datos/kerning.json.gz.

Es una herramienta de desarrollo: el generador en PHP solo lee el resultado. Para cada cara (familia, estilo y peso)
compone con HarfBuzz cada par de caracteres de la misma escritura y guarda, en milésimas de em, lo que el par se aleja
de la suma de los avances sueltos. Solo se guardan los pares que se ajustan al menos MINIMO milésimas. Las escrituras son: latina (con ASCII,
latín extendido y vietnamita), griega y cirílica; cada una se mezcla con la puntuación y las cifras de ASCII, pero
no con las otras (un par latín-griego nunca se junta en una palabra).

Hace falta la fuente completa, no los trozos de `fuentes/` (un trozo no trae sus pares con las letras de los otros).
Se usan las instancias estáticas de Google Fonts, de un paquete de npm de versión fija:

    pip install fonttools brotli uharfbuzz
    npm pack @expo-google-fonts/inter@0.4.2 @expo-google-fonts/source-serif-4@0.4.2
    # cada .tgz se extrae en una carpeta con su nombre: for f in *.tgz; do d=${f%.tgz}; mkdir $d; tar xzf $f -C $d; done
    python3 herramientas/kerning.py carpeta/con/los/dos/paquetes             # escribe datos/kerning.json.gz
    python3 herramientas/kerning.py carpeta/con/los/dos/paquetes --comprobar  # falla si no está al día

Solo se calculan las letras que las fuentes del proyecto tienen (datos/anchos.json). Las ligaduras (fi, fl) se dejan
fuera a propósito: con `letter-spacing` los navegadores las desactivan, y el generador mide letra a letra.
"""

import gzip
import io
import json
import sys
import unicodedata
from pathlib import Path

import uharfbuzz as hb
from fontTools.ttLib import TTFont

RAIZ = Path(__file__).resolve().parent.parent
UNIDADES = 1000
# Los ajustes menores que esto (en milésimas de em: a 20 mm de letra, 0,1 mm) no se ven y harían el archivo enorme.
MINIMO = 5
# familia -> estilo -> peso -> (paquete, archivo) dentro de la carpeta de los paquetes de npm
ORIGEN = {
    "serif": {
        "normal": {400: ("expo-google-fonts-source-serif-4-0.4.2", "400Regular/SourceSerif4_400Regular.ttf")},
        "italic": {400: ("expo-google-fonts-source-serif-4-0.4.2", "400Regular_Italic/SourceSerif4_400Regular_Italic.ttf")},
    },
    "sans": {
        "normal": {
            400: ("expo-google-fonts-inter-0.4.2", "400Regular/Inter_400Regular.ttf"),
            700: ("expo-google-fonts-inter-0.4.2", "700Bold/Inter_700Bold.ttf"),
        },
    },
}


def escritura(codigo: int) -> str:
    """«latina», «griega», «cirilica» o «comun» (ASCII que no es letra: cifras y signos)."""
    letra = chr(codigo)
    if codigo < 0x80 and not letra.isalpha():
        return "comun"
    nombre = unicodedata.name(letra, "")
    for inicio, clave in (("LATIN", "latina"), ("GREEK", "griega"), ("CYRILLIC", "cirilica")):
        if nombre.startswith(inicio):
            return clave
    return "comun"


def codigos_del_proyecto(familia: str, estilo: str, peso: int) -> list[int]:
    """Las letras que tiene la cara en datos/anchos.json, sin espacios ni controles."""
    datos = json.loads((RAIZ / "datos" / "anchos.json").read_text(encoding="utf-8"))
    cara = datos["caras"][familia][estilo][str(peso)]["anchos"]
    return sorted(c for c in map(int, cara) if c > 0x20 and not chr(c).isspace())


def abrir(ruta: Path) -> tuple[hb.Font, TTFont]:
    """La fuente como fuente de HarfBuzz y como TTFont, para sus avances."""
    ttf = TTFont(ruta, recalcTimestamp=False)
    return hb.Font(hb.Face(hb.Blob(ruta.read_bytes()))), ttf


def comprimir(completo: dict[str, int], codigos: list[int]) -> dict:
    """Quita lo que se deduce de la letra base: «á» se ajusta igual que «a» con todas las demás letras.

    Devuelve {"primero": {"á": "a", …}, "segundo": {…}, "pares": {…}}: `primero` vale para la letra que va antes en
    el par y `segundo` para la que va después; cada una solo lista las letras que se ajustan idéntico a su base. En
    `pares` quedan solo los pares entre letras que no se sustituyen. El ajuste de «ab» es
    pares[primero.get(a, a) + segundo.get(b, b)].
    """
    letras = [chr(c) for c in codigos]
    presentes = set(letras)
    primero: dict[str, str] = {}
    segundo: dict[str, str] = {}
    for letra in letras:
        descompuesta = unicodedata.normalize("NFD", letra)
        base = descompuesta[0]
        if len(descompuesta) == 1 or base not in presentes or base == letra:
            continue
        if all(completo.get(letra + x, 0) == completo.get(base + x, 0) for x in letras):
            primero[letra] = base
        if all(completo.get(x + letra, 0) == completo.get(x + base, 0) for x in letras):
            segundo[letra] = base
    pares = {par: ajuste for par, ajuste in completo.items() if par[0] not in primero and par[1] not in segundo}
    return {"primero": dict(sorted(primero.items())), "segundo": dict(sorted(segundo.items())), "pares": dict(sorted(pares.items()))}


def pares(ruta: Path, codigos: list[int]) -> dict[str, int]:
    letra, ttf = abrir(ruta)
    por_em = ttf["head"].unitsPerEm
    cmap = ttf.getBestCmap()
    hmtx = ttf["hmtx"]
    presentes = [c for c in codigos if c in cmap]
    grupos = {c: escritura(c) for c in presentes}
    resultado: dict[str, int] = {}
    for a in presentes:
        for b in presentes:
            # Una palabra no mezcla escrituras; la puntuación y las cifras se llevan con todas.
            if "comun" not in (grupos[a], grupos[b]) and grupos[a] != grupos[b]:
                continue
            texto = hb.Buffer()
            texto.add_str(chr(a) + chr(b))
            texto.guess_segment_properties()
            hb.shape(letra, texto, {"kern": True, "liga": False, "clig": False, "calt": False})
            if len(texto.glyph_positions) != 2:  # una ligadura o una composición: no es un par
                continue
            ajuste = round((texto.glyph_positions[0].x_advance - hmtx[cmap[a]][0]) * UNIDADES / por_em)
            if abs(ajuste) >= MINIMO:
                resultado[chr(a) + chr(b)] = ajuste
    return resultado


def calcular(origen: Path) -> dict:
    caras: dict = {}
    for familia, estilos in ORIGEN.items():
        for estilo, pesos in estilos.items():
            for peso, (paquete, archivo) in pesos.items():
                ruta = origen / paquete / "package" / archivo
                if not ruta.is_file():
                    raise SystemExit(f"falta {ruta}: ver el modo de uso al principio de este archivo")
                codigos = codigos_del_proyecto(familia, estilo, peso)
                caras.setdefault(familia, {}).setdefault(estilo, {})[str(peso)] = comprimir(pares(ruta, codigos), [c for c in codigos if c in TTFont(ruta).getBestCmap()])
    return {"unidades": UNIDADES, "caras": caras}


def main(argumentos: list[str]) -> int:
    carpetas = [a for a in argumentos if not a.startswith("--")]
    if len(carpetas) != 1:
        print(__doc__, file=sys.stderr)
        return 2
    datos = calcular(Path(carpetas[0]))
    texto = (json.dumps(datos, ensure_ascii=False, separators=(",", ":"), sort_keys=True) + "\n").encode("utf-8")
    destino = RAIZ / "datos" / "kerning.json.gz"
    comprimido = gzip.compress(texto, compresslevel=9, mtime=0)
    if "--comprobar" in argumentos:
        if not destino.is_file() or gzip.decompress(destino.read_bytes()) != texto:
            print("datos/kerning.json.gz no está al día: python3 herramientas/kerning.py carpeta", file=sys.stderr)
            return 1
        return 0
    destino.write_bytes(comprimido)
    total = sum(len(p["pares"]) for e in datos["caras"].values() for s in e.values() for p in s.values())
    print(f"{destino.relative_to(RAIZ)}: {total} pares, {len(comprimido)} bytes")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
