"""Abre la demo (app/index.html) en Chromium y prueba lo que no ve `node --test`: que la página arranque en
los 30 idiomas (lang, dir, textos sin traducir, errores de consola), el botón «Copiar enlace», la búsqueda
sin acentos con palabras traducidas, la flecha del conversor en árabe, las dos Half Letter y que ninguna
pestaña desborde en 320 px.

Uso, desde la raíz del repositorio o desde esta carpeta:
  pip install playwright==1.55.0        (sin descargar navegadores: PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1)
  python3 document-formats/tests/pruebas_navegador.py

Usa el Chromium de la variable CHROMIUM o, si no está, el primero que encuentre entre
/opt/pw-browsers/chromium, google-chrome, chromium y chromium-browser. Sirve document-formats/ con el
servidor de la biblioteca estándar, porque la demo pide los CSV con fetch.
"""
import functools
import http.server
import os
import re
import shutil
import sys
import threading
from pathlib import Path

from playwright.sync_api import TimeoutError as PlaywrightTimeout, sync_playwright

RAIZ = Path(__file__).resolve().parent.parent
IDIOMAS = sorted(p.stem for p in (RAIZ / "app" / "translations").glob("*.json"))
CANDIDATOS = ("/opt/pw-browsers/chromium", "google-chrome", "chromium", "chromium-browser")
PESTANAS = ("browser", "comparison", "converter", "paper-types", "about")
CLAVE = re.compile(r"^[a-z][A-Za-z0-9]*(\.[A-Za-z0-9]+)+$")  # «demo.copyLink»: una clave sin traducir

problemas = []


def falla(donde, mensaje):
    problemas.append(f"{donde}: {mensaje}")


def chromium():
    if os.environ.get("CHROMIUM"):
        return os.environ["CHROMIUM"]
    for candidato in CANDIDATOS:
        ruta = candidato if candidato.startswith("/") else shutil.which(candidato)
        if ruta and Path(ruta).exists():
            return ruta
    sys.exit("No encuentro un Chromium: pasa su ruta en la variable CHROMIUM")


class Callado(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass


def servir():
    servidor = http.server.ThreadingHTTPServer(("127.0.0.1", 0), functools.partial(Callado, directory=str(RAIZ)))
    threading.Thread(target=servidor.serve_forever, daemon=True).start()
    return servidor, f"http://127.0.0.1:{servidor.server_port}/app/index.html"


def abrir(contexto, url, idioma="en"):
    pagina = contexto.new_page()
    errores = []
    pagina.on("pageerror", lambda e: errores.append(f"JS: {e}"))
    pagina.on("console", lambda m: errores.append(f"consola: {m.text}") if m.type in ("error", "warning") else None)
    pagina.goto(f"{url}#lang={idioma}")
    try:
        pagina.wait_for_selector(".format-card", timeout=10000)
    except PlaywrightTimeout:
        falla(idioma, "la página no mostró ninguna tarjeta en 10 s" + (f" ({errores[0]})" if errores else ""))
    return pagina, errores


def idioma_por_idioma(contexto, url):
    for idioma in IDIOMAS:
        pagina, errores = abrir(contexto, url, idioma)
        estado = pagina.evaluate("""() => ({
            lang: document.documentElement.lang, dir: document.documentElement.dir,
            formatos: Number(document.getElementById('total-formats').textContent),
            sinTraducir: [...document.querySelectorAll('[data-translate]')].filter(el => {
                if (el.dataset.translate.includes(':') || el.dataset.translate.includes('|')) return false;
                const texto = el.textContent.trim();
                return texto === '' || texto === el.dataset.translate;
            }).map(el => el.dataset.translate),
        })""")
        if estado["lang"] != idioma:
            falla(idioma, f"lang={estado['lang']}")
        if estado["dir"] != ("rtl" if idioma == "ar" else "ltr"):
            falla(idioma, f"dir={estado['dir']}")
        if estado["formatos"] < 100:
            falla(idioma, f"solo {estado['formatos']} formatos")
        if estado["sinTraducir"]:
            falla(idioma, f"textos vacíos o sin traducir: {estado['sinTraducir'][:3]}")
        for error in errores:
            falla(idioma, error)
        pagina.close()


def copiar_enlace(contexto, url):
    pagina, errores = abrir(contexto, url, "de")
    pagina.fill("#search-input", "letter")
    pagina.click("#copy-link")
    pagina.wait_for_timeout(200)
    etiqueta = pagina.inner_text("#copy-link")
    portapapeles = pagina.evaluate("navigator.clipboard.readText()")
    direccion = pagina.evaluate("location.href")
    if etiqueta != "Link kopiert":
        falla("Copiar enlace", f"el botón dice «{etiqueta}»")
    if portapapeles != direccion or "q=letter" not in direccion or "lang=de" not in direccion:
        falla("Copiar enlace", f"copió «{portapapeles}» y la dirección es «{direccion}»")
    if pagina.inner_text("#status") != "Link kopiert":
        falla("Copiar enlace", "no avisó en la región status")
    pagina.wait_for_timeout(2300)
    if pagina.inner_text("#copy-link") != "Link kopieren":
        falla("Copiar enlace", "el botón no recuperó su texto")
    for error in errores:
        falla("Copiar enlace", error)
    pagina.close()


def busqueda_con_palabras_traducidas(contexto, url):
    for idioma, palabra in (("de", "umschlag"), ("de", "vereinigte staaten"), ("es", "mexico")):
        pagina, _ = abrir(contexto, url, idioma)
        pagina.fill("#search-input", palabra)
        pagina.wait_for_timeout(150)
        if pagina.locator("#formats-container .format-card").count() == 0:
            falla("búsqueda", f"«{palabra}» no encuentra nada en {idioma}")
        pagina.close()


def conversor(contexto, url):
    for idioma, flecha in (("en", "→"), ("ar", "←")):
        pagina, _ = abrir(contexto, url, idioma)
        pagina.click('[data-tab="converter"]')
        pagina.select_option("#from-format", "Letter")
        pagina.select_option("#to-format", "A4")
        texto = pagina.inner_text("#conversion-info")
        if flecha not in texto:
            falla("conversor", f"en {idioma} falta la flecha {flecha}: «{texto[:60]}»")
        pagina.close()
    pagina, _ = abrir(contexto, url)
    pagina.click('[data-tab="converter"]')
    opciones = pagina.eval_on_selector_all("#from-format option", "o => o.map(x => x.value)")
    for esperada in ("Half Letter (USA)", "Half Letter (International)"):
        if esperada not in opciones:
            falla("conversor", f"falta la opción «{esperada}»")
    if "Half Letter" in opciones:
        falla("conversor", "«Half Letter» sin país sigue en la lista, con dos tamaños")
    pagina.close()


def sin_desborde_a_320(navegador, url):
    contexto = navegador.new_context(viewport={"width": 320, "height": 700})
    for idioma in ("en", "de", "ar", "ja", "ru"):
        pagina, _ = abrir(contexto, url, idioma)
        for pestana in PESTANAS:
            pagina.click(f'[data-tab="{pestana}"]')
            if pagina.evaluate("document.documentElement.scrollWidth > document.documentElement.clientWidth"):
                falla("320 px", f"{idioma}, pestaña {pestana}: la página desborda en horizontal")
        pagina.close()
    contexto.close()


def main():
    servidor, url = servir()
    with sync_playwright() as p:
        navegador = p.chromium.launch(executable_path=chromium())
        contexto = navegador.new_context(viewport={"width": 1100, "height": 800})
        contexto.grant_permissions(["clipboard-read", "clipboard-write"], origin=url.rsplit("/app/", 1)[0])
        idioma_por_idioma(contexto, url)
        copiar_enlace(contexto, url)
        busqueda_con_palabras_traducidas(contexto, url)
        conversor(contexto, url)
        sin_desborde_a_320(navegador, url)
        navegador.close()
    servidor.shutdown()
    if problemas:
        print(f"✗ demo en Chromium: {len(problemas)} problemas")
        for p in problemas:
            print(f"    {p}")
        sys.exit(1)
    print(f"✓ demo en Chromium: {len(IDIOMAS)} idiomas, copiar enlace, búsqueda traducida, conversor y 320 px")


if __name__ == "__main__":
    main()
