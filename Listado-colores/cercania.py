"""El color CSS más cercano de cada nombre de nombres-completos.csv (CIEDE2000, ver colorimetria.py) y el texto de ese CSV.
"""
import bisect
import csv
import io

import colorimetria
from constantes import *  # noqa: F401,F403



def cercania_de(delta, mismo_hex):
    if mismo_hex:
        return "exacto"
    for banda, tope in BANDAS_CERCANIA:
        if delta <= tope:
            return banda
    return "baja"


# CIEDE2000 nunca es menor que |ΔL| / S_L, y S_L = 1 + 0,015 (L̄-50)² / raíz(20 + (L̄-50)²) vale a lo sumo 1,7471 para L entre 0
# y 100; con 1,75 el tope es seguro. Sirve para no calcular la distancia a los colores CSS que ya no pueden ganar.
COTA_SL = 1.75


def color_css_mas_cercano(lab, referencias):
    """(distancia redondeada a 9 decimales, nombre, hex) del color CSS más cercano a `lab`.

    `referencias` va ordenada por luminosidad: [(L, nombre, hex, lab)]. Se recorre desde la luminosidad más parecida y se
    para cuando |ΔL| / COTA_SL ya supera la mejor distancia: el resultado es el de calcular las 148, sin ese trabajo.
    """
    luminosidades = [r[0] for r in referencias]
    abajo = bisect.bisect_left(luminosidades, lab[0]) - 1
    arriba = abajo + 1
    mejor = None
    while abajo >= 0 or arriba < len(referencias):
        paso_abajo = abajo >= 0 and (arriba >= len(referencias) or lab[0] - luminosidades[abajo] <= luminosidades[arriba] - lab[0])
        indice = abajo if paso_abajo else arriba
        if mejor is not None and abs(luminosidades[indice] - lab[0]) / COTA_SL > mejor[0] + 1e-6:
            break  # esta y todas las demás están más lejos en luminosidad: ninguna puede ganar ni empatar
        if paso_abajo:
            abajo -= 1
        else:
            arriba += 1
        _, nombre, hex_, lab_ref = referencias[indice]
        candidato = (round(colorimetria.ciede2000(lab, lab_ref), 9), nombre, hex_)
        if mejor is None or candidato < mejor:
            mejor = candidato
    return mejor


def calcular_cercanias(completa, anotada):
    """Para cada nombre de la lista completa: color CSS más cercano (CIEDE2000), distancia y banda.

    Si dos colores CSS están igual de cerca (por ejemplo gray y grey, que comparten hex), gana el
    primero por orden alfabético. La comparación redondea a 9 decimales para que el desempate no
    dependa del ruido de coma flotante entre plataformas.
    """
    referencias = sorted((colorimetria.hex_a_lab(f["hex"])[0], f["nombre_ingles"], f["hex"], colorimetria.hex_a_lab(f["hex"]))
                         for f in anotada)
    resultado = []
    for fila in completa:
        distancia, nombre, color = color_css_mas_cercano(colorimetria.hex_a_lab(fila["hex"]), referencias)
        resultado.append((nombre, distancia, cercania_de(distancia, color == fila["hex"])))
    return resultado


def texto_completa(completa, cercanias):
    salida = io.StringIO()
    escritor = csv.writer(salida, lineterminator="\n")
    escritor.writerow(COLUMNAS_COMPLETA)
    for fila, (css, distancia, cercania) in zip(completa, cercanias):
        escritor.writerow([fila["nombre_ingles"], fila["hex"], fila["curada"], css, f"{distancia:.2f}", cercania])
    return salida.getvalue()


# Lo que reexporta generar-tabla.py con `import *`: lo definido aquí, no lo importado.
__all__ = [n for n, v in list(globals().items()) if not n.startswith("_") and (n.isupper() or getattr(v, "__module__", None) == __name__)]
