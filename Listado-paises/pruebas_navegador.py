"""Abre vista.html en Chromium y prueba lo que hace la página: mapa, tabla, filtros, orden,
búsqueda sin acentos, escenarios, marcas «≠ datos» y tooltip, en escritorio y en móvil.

Las cifras esperadas salen de los CSV del proyecto, no están escritas aquí: si cambian los datos,
las pruebas siguen valiendo.

Uso, desde esta carpeta:
  pip install playwright==1.55.0        (sin descargar navegadores: PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1)
  python3 pruebas_navegador.py

Usa el Chromium de la variable CHROMIUM o, si no está, el primero que encuentre entre
/opt/pw-browsers/chromium, google-chrome, chromium y chromium-browser.
"""
import os
import shutil
import sys
import unicodedata
from pathlib import Path

from playwright.sync_api import sync_playwright

from comun import filas_csv
from generar_tabla import ESCENARIOS_GEN, ESCENARIOS_POS
from sugerencias import desvios_por_pais

AQUI = Path(__file__).resolve().parent
VISTA = AQUI / "vista.html"
CANDIDATOS = ("/opt/pw-browsers/chromium", "google-chrome", "chromium", "chromium-browser")
TAMANIOS = {"escritorio": (1280, 900), "móvil": (390, 844)}


def buscar_chromium():
    if os.environ.get("CHROMIUM"):
        return os.environ["CHROMIUM"]
    for candidato in CANDIDATOS:
        ruta = candidato if Path(candidato).exists() else shutil.which(candidato)
        if ruta:
            return str(ruta)
    sys.exit("No encuentro Chromium: indícalo con la variable CHROMIUM.")


def plano(texto):
    return "".join(c for c in unicodedata.normalize("NFD", texto) if not unicodedata.combining(c)).lower()


def esperados():
    """Lo que la página debería mostrar, calculado desde los CSV."""
    paises = {f["codigo"]: f for f in filas_csv(AQUI / "paises.csv")}
    pos = {f["codigo"]: f for f in filas_csv(AQUI / "paises_pos.csv")}
    niveles = {f["lengua"]: f for f in filas_csv(AQUI / "niveles_lenguas.csv")}
    registros = []
    for c, f in paises.items():
        p = pos[c]
        local = p["idioma_principal"].split(";")[0].strip()
        registros.append({
            "c": c, "n": f["pais"], "r": f["region"], "ig": int(f["indice_genealogia"]),
            "io": int(f["indice_operabilidad"]), "il": int(p["indice_lectura"]), "ip": int(p["indice_pos"]),
            "ipl": int(p["indice_pos_local"]), "local": local, "palabras": niveles[local]["palabras_anotadas"],
            **{k: float(f[col]) if f[col] else None for k, col in (
                ("emi", "emigrantes_pct"), ("idh", "idh"), ("net", "usuarios_internet"),
                ("alf", "alfabetizacion_adultos"), ("cpi", "corrupcion_cpi"), ("eiu", "democracia_eiu"))},
            "fh": f["libertad_fh"], "cg": f["comentarios"], "cp": p["comentarios_pos"],
        })
    marcas = desvios_por_pais(registros)
    return {
        "total": len(registros),
        "regiones": sorted({d["r"] for d in registros}),
        "excluidos_gen": [sum(1 for d in registros if x(d)) for _, _, x in ESCENARIOS_GEN],
        "excluidos_pos": [sum(1 for d in registros if x(d)) for _, _, x in ESCENARIOS_POS],
        "desvios_gen": sum(1 for m in marcas.values() if "ig" in m or "io" in m),
        "desvios_pos": sum(1 for m in marcas.values() if "il" in m),
        "en_mapa": sum(1 for f in filas_csv(AQUI / "mapa_paises.csv") if f["codigo"]),
    }


class Pagina:
    def __init__(self, pagina):
        self.p = pagina

    def filas(self):
        return self.p.locator("#tabla tbody tr").count()

    def codigos(self):
        return self.p.eval_on_selector_all("#tabla tbody tr", "trs => trs.map(t => t.dataset.codigo)")

    def nombres(self):
        return self.p.eval_on_selector_all("#tabla tbody tr .nombre", "els => els.map(e => e.textContent)")

    def columna(self, indice):
        return self.p.eval_on_selector_all(
            "#tabla tbody tr", "(trs, i) => trs.map(t => t.children[i < 0 ? t.children.length + i : i]"
            ".textContent.trim())", indice)

    def escribir(self, selector, texto):
        self.p.fill(selector, texto)
        self.p.dispatch_event(selector, "input")

    def marcar(self, selector, valor):
        self.p.set_checked(selector, valor)
        self.p.dispatch_event(selector, "input")

    def region(self, valor):
        self.p.select_option("#region", valor)
        self.p.dispatch_event("#region", "input")

    def modo(self, valor):
        self.p.click(f'label[for="modo-{valor}"]')

    def escenario(self, i):
        self.p.select_option("#escenario", str(i))

    def resumen(self):
        return self.p.inner_text("#resumen")

    def restablecer(self):
        self.modo("gen")
        self.escribir("#buscar", "")
        self.region("")
        self.marcar("#ver-excluidos", True)
        self.marcar("#solo-desvios", False)


def recorrer(pagina, e, tamanio):
    """Lista de problemas encontrados (vacía si está bien)."""
    problemas = []
    v = Pagina(pagina)

    def exigir(condicion, mensaje):
        if not condicion:
            problemas.append(f"[{tamanio}] {mensaje}")

    total = e["total"]
    # Carga: tabla completa, mapa dibujado, resumen y regiones.
    exigir(v.filas() == total, f"la tabla tiene {v.filas()} filas, se esperaban {total}")
    exigir(pagina.inner_text("#cuenta") == f"{total} de {total}", f"cuenta: {pagina.inner_text('#cuenta')!r}")
    formas = pagina.eval_on_selector_all("[data-codigo]", "els => new Set(els.filter(e => e.closest('svg'))"
                                         ".map(e => e.dataset.codigo)).size")
    exigir(formas >= e["en_mapa"], f"el mapa tiene {formas} países, se esperaban {e['en_mapa']} o más")
    regiones = pagina.eval_on_selector_all("#region option", "os => os.slice(1).map(o => o.value)")
    exigir(sorted(regiones) == e["regiones"], f"regiones del filtro: {regiones}")
    exigir(f"{e['excluidos_gen'][0]} excluidos" in v.resumen(), f"resumen de genealogía: {v.resumen()!r}")

    # Orden por defecto: prioridad de mayor a menor, los excluidos al final.
    prioridades = [int(t) if t.isdigit() else -1 for t in v.columna(-2)]
    exigir(prioridades == sorted(prioridades, reverse=True), "la tabla no empieza ordenada por prioridad")
    # Orden por país: alfabético sin tener en cuenta los acentos; un segundo clic lo invierte.
    pagina.click('#tabla thead button[data-clave="pais"]')
    nombres = v.nombres()
    exigir(nombres == sorted(nombres, key=plano), f"orden por país: {nombres[:5]}…")
    pagina.click('#tabla thead button[data-clave="pais"]')
    exigir(v.nombres() == list(reversed(nombres)), "el segundo clic en «País» no invierte el orden")

    # Búsqueda sin acentos ni mayúsculas.
    v.escribir("#buscar", "PERU")
    exigir("PE" in v.codigos(), "buscar «PERU» no encuentra Perú")
    v.escribir("#buscar", "catalan")
    exigir("AD" in v.codigos(), "buscar «catalan» no encuentra Andorra (por su lengua)")
    v.escribir("#buscar", "zzzz")
    exigir(v.filas() == 0, "una búsqueda sin resultados deja filas")
    v.escribir("#buscar", "")

    # Región.
    v.region("Europa")
    regiones_filas = v.columna(1)
    exigir(regiones_filas and set(regiones_filas) == {"Europa"}, f"filtro de región: {set(regiones_filas)}")
    v.region("")

    # Excluidos y escenarios, en los dos modos.
    for modo, excluidos, desvios in (("gen", e["excluidos_gen"], e["desvios_gen"]),
                                     ("pos", e["excluidos_pos"], e["desvios_pos"])):
        v.modo(modo)
        opciones = pagina.locator("#escenario option").count()
        exigir(opciones == len(excluidos), f"[{modo}] el selector tiene {opciones} escenarios")
        for i, n in enumerate(excluidos):
            v.escenario(i)
            v.marcar("#ver-excluidos", False)
            exigir(v.filas() == total - n, f"[{modo}] escenario {i}: {v.filas()} filas sin excluidos, "
                                            f"se esperaban {total - n}")
            exigir(f"{n} excluidos" in v.resumen(), f"[{modo}] escenario {i}: resumen {v.resumen()!r}")
            v.marcar("#ver-excluidos", True)
        v.escenario(0)
        v.marcar("#solo-desvios", True)
        exigir(v.filas() == desvios, f"[{modo}] «Solo ≠ datos» muestra {v.filas()}, se esperaban {desvios}")
        avisos = pagina.locator("#tabla tbody .aviso").count()
        exigir(avisos == desvios, f"[{modo}] hay {avisos} marcas «≠ datos», se esperaban {desvios}")
        v.marcar("#solo-desvios", False)
    v.modo("pos")
    titulos = pagina.eval_on_selector_all("#tabla thead button", "bs => bs.map(b => b.textContent)")
    exigir("Lengua local" in titulos, f"el modo POS no muestra la lengua local: {titulos}")

    # Tooltip: al tocar un país en el mapa se fija y muestra la banda de nacidos; un clic afuera lo cierra.
    v.restablecer()
    pagina.locator('svg [data-codigo="MX"]').first.dispatch_event("click")
    texto = pagina.inner_text("#tip") if pagina.is_visible("#tip") else ""
    exigir("México" in texto, "el tooltip no se abre al tocar México en el mapa")
    exigir("Nacidos en la historia" in texto and "–" in texto, f"el tooltip no muestra la banda: {texto!r}")
    pagina.click("h1")
    exigir(not pagina.is_visible("#tip"), "el tooltip no se cierra con un clic afuera")

    # Sin desplazamiento horizontal de la página (la tabla se desplaza dentro de su caja).
    ancho = pagina.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth")
    exigir(ancho <= 0, f"la página se desplaza {ancho} px a lo ancho")
    return problemas


def main():
    e = esperados()
    problemas = []
    with sync_playwright() as pw:
        navegador = pw.chromium.launch(executable_path=buscar_chromium(), args=["--no-sandbox"])
        for tamanio, (ancho, alto) in TAMANIOS.items():
            pagina = navegador.new_page(viewport={"width": ancho, "height": alto})
            errores = []
            pagina.on("pageerror", lambda err: errores.append(str(err)))
            pagina.on("console", lambda m: errores.append(m.text) if m.type == "error" else None)
            pagina.goto(VISTA.as_uri())
            problemas += recorrer(pagina, e, tamanio)
            problemas += [f"[{tamanio}] error de JavaScript: {err}" for err in errores]
            pagina.close()
        navegador.close()
    if problemas:
        sys.exit("vista.html tiene problemas:\n  " + "\n  ".join(problemas))
    print(f"vista.html: bien en {', '.join(TAMANIOS)} ({e['total']} países, "
          f"{len(ESCENARIOS_GEN)} + {len(ESCENARIOS_POS)} escenarios).")


if __name__ == "__main__":
    main()
