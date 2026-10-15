"""Renderer SVG: convierte una Escena en un documento SVG determinista.

Los números se formatean con {:g} para que la misma escena siempre
produzca exactamente el mismo texto, byte a byte.
"""
from scene import ALTO, ANCHO


def a_svg(esc):
    lineas = [
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {ANCHO} {ALTO}" '
        f'width="{ANCHO}" height="{ALTO}">'
    ]
    for prim in esc.primitivas:
        tipo = prim["tipo"]
        if tipo == "degradado":
            lineas.append(
                '<defs><linearGradient id="cielo" x1="0" y1="0" x2="0" y2="1">'
                f'<stop offset="0" stop-color="{prim["arriba"]}"/>'
                + "".join(f'<stop offset="{o:g}" stop-color="{c}"/>' for o, c in prim.get("paradas", ()))
                + f'<stop offset="1" stop-color="{prim["abajo"]}"/>'
                "</linearGradient></defs>"
            )
            lineas.append(f'<rect width="{ANCHO}" height="{ALTO}" fill="url(#cielo)"/>')
        elif tipo == "circulo":
            lineas.append(
                f'<circle cx="{prim["cx"]:g}" cy="{prim["cy"]:g}" r="{prim["r"]:g}" '
                f'fill="{prim["color"]}"/>'
            )
        elif tipo == "velo":
            lineas.append(
                f'<rect x="{prim["x0"]:g}" y="{prim["y0"]:g}" width="{prim["x1"] - prim["x0"]:g}" '
                f'height="{prim["y1"] - prim["y0"]:g}" rx="{prim["radio"]:g}" fill="{prim["color"]}" '
                f'fill-opacity="{prim["opacidad"]:g}"/>'
            )
        elif tipo == "poligono":
            pts = " ".join(f"{x:g},{y:g}" for x, y in prim["puntos"])
            lineas.append(f'<polygon points="{pts}" fill="{prim["color"]}"/>')
    lineas.append("</svg>")
    return "\n".join(lineas) + "\n"
