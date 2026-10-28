"""Abre galeria.html en Chromium y la recorre en los 30 idiomas y las dos variantes de ilustración.

Comprueba, en cada combinación, que no haya errores de JavaScript, que se pinten los 24 signos, que
cada imagen cargue, que la interfaz salga en el idioma elegido, que árabe, hebreo, persa y urdu vayan
de derecha a izquierda, y que el vietnamita use el gato (conejo-vi.svg).

Uso, desde esta carpeta:
  pip install playwright==1.55.0        (sin descargar navegadores: PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1)
  python3 pruebas_navegador.py

Usa el Chromium de la variable CHROMIUM o, si no está, el primero que encuentre entre
/opt/pw-browsers/chromium, google-chrome, chromium y chromium-browser.
"""
import json
import os
import re
import shutil
import sys
from pathlib import Path

import generar

CANDIDATOS = ("/opt/pw-browsers/chromium", "google-chrome", "chromium", "chromium-browser")


def buscar_chromium():
    if os.environ.get("CHROMIUM"):
        return os.environ["CHROMIUM"]
    for candidato in CANDIDATOS:
        ruta = candidato if Path(candidato).exists() else shutil.which(candidato)
        if ruta:
            return str(ruta)
    sys.exit("No encuentro Chromium: indícalo con la variable CHROMIUM.")


def recorrer(pagina, datos):
    """Lista de problemas encontrados en la galería (vacía si está bien)."""
    problemas = []
    terminos = datos["terminos"]
    for codigo in generar.IDIOMAS:
        for carpeta in ("constelaciones", "constelaciones-sin-fondo"):
            donde = f"{codigo}/{carpeta}"
            pagina.select_option("#idioma", codigo)
            pagina.select_option("#variante", carpeta)
            pagina.wait_for_function("() => [...document.images].every(i => i.complete)")
            estado = pagina.evaluate("""() => ({
                figuras: document.querySelectorAll("#contenido figure").length,
                rotas: [...document.images].filter(i => i.naturalWidth === 0).map(i => i.getAttribute("src")),
                fuentes: [...document.images].map(i => i.getAttribute("src")),
                titulo: document.getElementById("titulo").textContent,
                secciones: [...document.querySelectorAll("#contenido h2")].map(h => h.textContent),
                dir: document.documentElement.dir,
                lang: document.documentElement.lang,
                sinAlt: [...document.images].filter(i => !i.alt).length,
            })""")
            if estado["figuras"] != 24:
                problemas.append(f"{donde}: {estado['figuras']} signos en vez de 24")
            if estado["rotas"]:
                problemas.append(f"{donde}: imágenes que no cargan: {estado['rotas'][:3]}")
            if estado["sinAlt"]:
                problemas.append(f"{donde}: {estado['sinAlt']} imágenes sin texto alternativo")
            if estado["titulo"] != terminos["titulo"][codigo]:
                problemas.append(f"{donde}: el título no está en el idioma elegido")
            esperadas = [terminos[clave][codigo] for clave in generar.GRUPOS.values()]
            if estado["secciones"] != esperadas:
                problemas.append(f"{donde}: las secciones no están en el idioma elegido: {estado['secciones']}")
            dir_esperada = "rtl" if codigo in generar.DERECHA_A_IZQUIERDA else "ltr"
            if (estado["dir"], estado["lang"]) != (dir_esperada, codigo):
                problemas.append(f"{donde}: dir={estado['dir']} lang={estado['lang']}")
            if not all(f.startswith(carpeta + "/") for f in estado["fuentes"]):
                problemas.append(f"{donde}: hay imágenes de otra carpeta")
            conejo = [f for f in estado["fuentes"] if re.search(r"/conejo(-\w+)?\.svg$", f)]
            esperado = f"{carpeta}/conejo-vi.svg" if codigo == "vi" else f"{carpeta}/conejo.svg"
            if conejo != [esperado]:
                problemas.append(f"{donde}: el conejo es {conejo}, se esperaba {esperado}")
    return problemas


def main():
    from playwright.sync_api import sync_playwright

    with generar.DATOS.open(encoding="utf-8") as f:
        datos = json.load(f)
    errores_js = []
    with sync_playwright() as p:
        navegador = p.chromium.launch(executable_path=buscar_chromium())
        pagina = navegador.new_page()
        pagina.on("pageerror", lambda e: errores_js.append(str(e)))
        pagina.on("console", lambda m: errores_js.append(m.text) if m.type == "error" else None)
        pagina.goto(generar.GALERIA.resolve().as_uri())
        problemas = recorrer(pagina, datos)
        navegador.close()
    problemas += [f"error de JavaScript: {e}" for e in errores_js]
    if problemas:
        print(f"{len(problemas)} problemas en galeria.html:")
        for problema in problemas:
            print(f"  {problema}")
        return 1
    print(f"galeria.html bien en los {len(generar.IDIOMAS)} idiomas y las 2 variantes.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
