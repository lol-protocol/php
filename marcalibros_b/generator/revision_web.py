#!/usr/bin/env python3
"""Genera la página de revisión del catálogo (catalog/revision/web/).

Una hoja de miniaturas por movimiento (sprites/<movimiento>.jpg, sacadas de la caché
del build) y un index.html con los datos de las 1000 piezas. La página se publica
como Artifact: quien la abre marca cada obra y cada autor como «bien» o «corregir»
con una nota, y esas marcas se guardan en la base de datos del Artifact.

    python generator/build.py --all          # primero, para tener la caché al día
    python generator/revision_web.py
"""
import json
from pathlib import Path

import yaml
from PIL import Image

RAIZ = Path(__file__).resolve().parent.parent
CATALOGO = RAIZ / "catalog"
SALIDA = CATALOGO / "revision" / "web"
CELDA = (80, 560)  # vistas de revisión guardadas por el build (ESCALA_REVISION = 0.4)
COLUMNAS = 20


def main():
    config = yaml.safe_load((CATALOGO / "taxonomia.yaml").read_text(encoding="utf-8"))
    motivos = yaml.safe_load((CATALOGO / "motivos.yaml").read_text(encoding="utf-8"))
    manifiesto = {e["id"]: e for e in json.loads((CATALOGO / "manifest.json").read_text(encoding="utf-8"))["entradas"]}
    (SALIDA / "sprites").mkdir(parents=True, exist_ok=True)

    movimientos, autores, piezas = [], [], []
    for mi, mov in enumerate(config["movimientos"]):
        movimientos.append({"id": mov["id"], "nombre": mov["nombre"]})
        ids = []
        for autor in mov["autores"]:
            autores.append({"m": mov["id"], "id": autor["id"], "nombre": autor["nombre"],
                            "epoca": autor["epoca"], "region": autor["region"]})
            for v, obra in enumerate(autor.get("obras", []), 1):
                ident = f"{mov['id']}-{autor['id']}-{v:02d}"
                e = manifiesto[ident]
                piezas.append({
                    "id": ident, "m": mov["id"], "a": autor["id"], "v": v, "t": obra["titulo"], "tp": obra["tipo"],
                    "de": obra.get("de", ""), "mo": obra["motivo"], "an": obra["animo"], "es": e["arquetipo"],
                    "pa": e["paleta"], "f": e["formato"], "g": obra["gutenberg"]["estado"],
                    "fr": (e.get("frase") or {}).get("texto", ""), "fa": (e.get("frase") or {}).get("autor", ""),
                    "s": [mi, len(ids)],
                })
                ids.append(ident)
        filas = (len(ids) + COLUMNAS - 1) // COLUMNAS
        hoja = Image.new("RGB", (COLUMNAS * CELDA[0], filas * CELDA[1]), (0, 0, 0))
        for k, ident in enumerate(ids):
            img = Image.open(CATALOGO / ".cache" / "rev" / f"{ident}.png").convert("RGB")
            if img.size != CELDA:
                img = img.resize(CELDA, Image.Resampling.LANCZOS)
            hoja.paste(img, ((k % COLUMNAS) * CELDA[0], (k // COLUMNAS) * CELDA[1]))
        hoja.save(SALIDA / "sprites" / f"{mov['id']}.jpg", "JPEG", quality=78, optimize=True, progressive=True)

    datos = {
        "movimientos": movimientos, "autores": autores, "piezas": piezas,
        "motivos": {m["id"]: m["nombre"] for m in motivos["motivos"]},
        "celda": list(CELDA), "columnas": COLUMNAS,
    }
    plantilla = (Path(__file__).with_name("revision_web.html")).read_text(encoding="utf-8")
    html = plantilla.replace("/*__DATOS__*/null", json.dumps(datos, ensure_ascii=False, separators=(",", ":")))
    (SALIDA / "index.html").write_text(html, encoding="utf-8")
    peso = sum(f.stat().st_size for f in (SALIDA / "sprites").glob("*.jpg"))
    print(f"página: {(SALIDA / 'index.html').relative_to(RAIZ)} ({len(piezas)} piezas), "
          f"sprites: {len(movimientos)} ({peso / 1e6:.1f} MB)")


if __name__ == "__main__":
    main()
