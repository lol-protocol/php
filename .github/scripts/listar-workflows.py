#!/usr/bin/env python3
"""Genera .github/WORKFLOWS.md: una tabla de los workflows con lo que se puede leer en ellos mismos
(nombre, cuándo corren y qué jobs tienen), para que esos datos no se mantengan a mano en el README.

El README raíz sigue explicando para qué sirve cada workflow (eso lo escribe una persona); esta tabla
cubre lo que se desactualizaba sin que nada fallara: los disparadores y los jobs.

  python3 .github/scripts/listar-workflows.py              # escribe .github/WORKFLOWS.md
  python3 .github/scripts/listar-workflows.py --comprobar  # falla si el archivo no está al día

Sin dependencias (no usa PyYAML): lee solo las líneas de nivel alto de cada workflow, que en este repo
siguen siempre el mismo formato (`name:`, `on:` con sus disparadores a 2 espacios, `jobs:` con cada job a
2 espacios y su `name:` a 4).
"""
import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parents[2]
CARPETA = RAIZ / ".github" / "workflows"
SALIDA = RAIZ / ".github" / "WORKFLOWS.md"
DISPARADORES = ["push", "pull_request", "schedule", "workflow_dispatch", "workflow_call", "workflow_run", "release"]


def quitar_comillas(valor):
    valor = valor.split(" #")[0].strip()
    if len(valor) >= 2 and valor[0] == valor[-1] and valor[0] in "'\"":
        valor = valor[1:-1]
    return valor


def leer(ruta):
    lineas = ruta.read_text(encoding="utf-8").splitlines()
    nombre = None
    disparadores = []
    jobs = []
    seccion = None
    for linea in lineas:
        if not linea.strip() or linea.lstrip().startswith("#"):
            continue
        if not linea.startswith(" "):
            m = re.match(r"^(\w+|'on'|\"on\"):\s*(.*)$", linea)
            seccion = m.group(1).strip("'\"") if m else None
            if seccion == "name":
                nombre = quitar_comillas(m.group(2))
            elif seccion == "on" and m.group(2).strip():
                # forma corta: on: [push, pull_request]  o  on: push
                disparadores += [d.strip() for d in m.group(2).strip("[] ").split(",") if d.strip()]
            continue
        if seccion == "on":
            m = re.match(r"^  (\w+):", linea)
            if m:
                disparadores.append(m.group(1))
        elif seccion == "jobs":
            m = re.match(r"^  ([\w-]+):\s*$", linea)
            if m:
                jobs.append([m.group(1), None])
            m = re.match(r"^    name:\s*(.+)$", linea)
            if m and jobs and jobs[-1][1] is None:
                jobs[-1][1] = quitar_comillas(m.group(1))
    return nombre, [d for d in DISPARADORES if d in disparadores] + [d for d in disparadores if d not in DISPARADORES], jobs


def celda(texto):
    return texto.replace("|", "\\|").replace("\n", " ")


def generar():
    filas = []
    for ruta in sorted(list(CARPETA.glob("*.yml")) + list(CARPETA.glob("*.yaml"))):
        nombre, disparadores, jobs = leer(ruta)
        trabajos = ", ".join(f"`{i}`" for i, _ in jobs) or "—"
        filas.append(
            f"| [`{ruta.name}`](workflows/{ruta.name}) | {celda(nombre or '—')} | "
            f"{', '.join(f'`{d}`' for d in disparadores) or '—'} | {trabajos} |"
        )
    return (
        "<!-- Generado por .github/scripts/listar-workflows.py: no lo edites a mano. -->\n"
        "# Workflows\n\n"
        "Lista generada de `.github/workflows/`: el nombre, cuándo corre cada uno y sus jobs, leídos del propio\n"
        "workflow. Para qué sirve cada uno está en la sección «CI/CD» del [README](../README.md); esta tabla\n"
        "se regenera con `python3 .github/scripts/listar-workflows.py`, y `estructura.yml` falla si no está al día.\n\n"
        "| Archivo | Nombre | Cuándo corre | Jobs |\n|---|---|---|---|\n" + "\n".join(filas) + "\n"
    )


def main():
    contenido = generar()
    if "--comprobar" in sys.argv[1:]:
        actual = SALIDA.read_text(encoding="utf-8") if SALIDA.exists() else ""
        if actual != contenido:
            print(f"{SALIDA.relative_to(RAIZ)} no está al día: corre `python3 .github/scripts/listar-workflows.py` y súbelo.")
            return 1
        print(f"{SALIDA.relative_to(RAIZ)} al día ({contenido.count(chr(10) + '| [')} workflows).")
        return 0
    SALIDA.write_text(contenido, encoding="utf-8")
    print(f"Escrito {SALIDA.relative_to(RAIZ)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
