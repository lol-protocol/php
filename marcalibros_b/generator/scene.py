"""Modelo de escena compartido por los dos renderers.

Una escena es una lista de primitivas simples: degradado, círculo y polígono.
El renderer SVG las convierte en vector y el renderer raster en píxeles.
Así una misma composición puede salir como SVG o como JPG sin divergir.
"""
from dataclasses import dataclass, field

ANCHO = 1400
ALTO = 200  # proporción 7:1


@dataclass
class Escena:
    semilla: int
    primitivas: list = field(default_factory=list)
    grano: float = 0.0  # solo lo aplica el renderer raster (variantes JPG)

    def degradado(self, arriba, abajo):
        self.primitivas.append({"tipo": "degradado", "arriba": arriba, "abajo": abajo})

    def circulo(self, cx, cy, r, color):
        self.primitivas.append({"tipo": "circulo", "cx": cx, "cy": cy, "r": r, "color": color})

    def poligono(self, puntos, color):
        self.primitivas.append({"tipo": "poligono", "puntos": puntos, "color": color})
