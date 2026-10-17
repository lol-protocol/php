"""Modelo de escena compartido por los dos renderers.

Una escena es una lista de primitivas simples: degradado, círculo y polígono.
El renderer SVG las convierte en vector y el renderer raster en píxeles.
Así una misma composición puede salir como SVG o como JPG sin divergir.
"""
from dataclasses import dataclass, field

ANCHO = 200
ALTO = 1400  # proporción 1:7, solo vertical (decisión del plan)


@dataclass
class Escena:
    semilla: int
    primitivas: list = field(default_factory=list)
    grano: float = 0.0  # solo lo aplica el renderer raster (variantes JPG)
    franja: tuple = None  # (x0, y0, x1, y1) reservada para texto, o None si no cupo
    sol: tuple = None  # (cx, cy, r) del sol o la luna, por si un adorno lo necesita
    bbox_motivo: tuple = None  # (x0, y0, x1, y1) del motivo dibujado, si lo hay

    def degradado(self, arriba, abajo):
        self.primitivas.append({"tipo": "degradado", "arriba": arriba, "abajo": abajo})

    def circulo(self, cx, cy, r, color):
        self.primitivas.append({"tipo": "circulo", "cx": cx, "cy": cy, "r": r, "color": color})

    def poligono(self, puntos, color):
        self.primitivas.append({"tipo": "poligono", "puntos": puntos, "color": color})

    def velo(self, x0, y0, x1, y1, color, opacidad, radio=10):
        """Rectángulo translúcido (se dibuja encima de todo lo anterior)."""
        self.primitivas.append({"tipo": "velo", "x0": x0, "y0": y0, "x1": x1, "y1": y1,
                                "color": color, "opacidad": opacidad, "radio": radio})

    def texto(self, bloques, color):
        """Texto como contornos de glifos (ver texto.py). `bloques`: [{"tam", "glifos": [(c, x, y)]}]."""
        self.primitivas.append({"tipo": "texto", "bloques": bloques, "color": color})


# --- Ayudas para siluetas: curvas muestreadas a polígono -----------------------------

def bezier(p0, p1, p2, n=6):
    """Curva cuadrática de p0 a p2 con control p1, sin el punto inicial."""
    return [(round((1 - t) ** 2 * p0[0] + 2 * (1 - t) * t * p1[0] + t * t * p2[0], 1),
             round((1 - t) ** 2 * p0[1] + 2 * (1 - t) * t * p1[1] + t * t * p2[1], 1)) for t in (k / n for k in range(1, n + 1))]


def suave(pts, n=4):
    """Curva Catmull-Rom abierta por los puntos dados (incluye el primero)."""
    ext = [pts[0]] + list(pts) + [pts[-1]]
    out = [pts[0]]
    for i in range(1, len(ext) - 2):
        a, b, c, d = ext[i - 1], ext[i], ext[i + 1], ext[i + 2]
        for k in range(1, n + 1):
            t = k / n
            out.append((round(0.5 * (2 * b[0] + (-a[0] + c[0]) * t + (2 * a[0] - 5 * b[0] + 4 * c[0] - d[0]) * t * t + (-a[0] + 3 * b[0] - 3 * c[0] + d[0]) * t ** 3), 1),
                        round(0.5 * (2 * b[1] + (-a[1] + c[1]) * t + (2 * a[1] - 5 * b[1] + 4 * c[1] - d[1]) * t * t + (-a[1] + 3 * b[1] - 3 * c[1] + d[1]) * t ** 3), 1)))
    return out


def mover(pts, x, y):
    return [(round(x + a, 1), round(y + b, 1)) for a, b in pts]
