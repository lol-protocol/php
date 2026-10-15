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
