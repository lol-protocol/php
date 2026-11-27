"""Genera el catálogo completo en paralelo: un proceso de `build.py --movimiento <id>` por movimiento, hasta
`--procesos` a la vez (por defecto, los núcleos de la máquina). Cada movimiento es independiente (las semillas
y la comparación de duplicados no cruzan movimientos), y los dos archivos compartidos —manifest y caché— se
escriben bajo el bloqueo de build.py, así que el resultado es el mismo que con `build.py --all`.

Uso: python generator/build_paralelo.py [--procesos N] [--forzar]
"""
import argparse
import os
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

import yaml

RAIZ = Path(__file__).resolve().parent.parent
BUILD = Path(__file__).with_name("build.py")


def movimientos():
    config = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    return [m["id"] for m in config["movimientos"]]


def ordenes(ids, forzar=False):
    """La línea de comando de cada movimiento."""
    return {i: [sys.executable, str(BUILD), "--movimiento", i] + (["--forzar"] if forzar else []) for i in ids}


def main(argv=None):
    ap = argparse.ArgumentParser(description="Genera todos los movimientos en paralelo.")
    ap.add_argument("--procesos", type=int, default=os.cpu_count() or 1)
    ap.add_argument("--forzar", action="store_true", help="ignora la caché y rehace todas las piezas")
    args = ap.parse_args(argv)
    ids = movimientos()
    fallidos = []
    with ThreadPoolExecutor(max(1, args.procesos)) as pool:
        futuros = {pool.submit(subprocess.run, orden, capture_output=True, text=True): i
                   for i, orden in ordenes(ids, args.forzar).items()}
        for futuro in as_completed(futuros):
            movimiento, r = futuros[futuro], futuro.result()
            print(f"=== {movimiento} (código {r.returncode}) ===\n{r.stdout.rstrip()}", flush=True)
            if r.stderr.strip():
                print(r.stderr.rstrip(), file=sys.stderr, flush=True)
            if r.returncode:
                fallidos.append(movimiento)
    print(f"\n{len(ids) - len(fallidos)} de {len(ids)} movimientos sin errores"
          + (f"; con errores: {', '.join(sorted(fallidos))}" if fallidos else ""))
    return 1 if fallidos else 0


if __name__ == "__main__":
    raise SystemExit(main())
