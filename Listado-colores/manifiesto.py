"""Manifiesto y registro de cambios de los datos de Listado-colores.

Solo biblioteca estándar. Lo usa generar-tabla.py:
- leer_changelog() valida CHANGELOG.md y devuelve la versión de los datos (su entrada más reciente).
- manifiesto() arma el texto de exportes/manifiesto.json: la versión, los idiomas y, de cada CSV de datos y de cada
  exporte, su número de filas y la suma SHA-256 de su contenido.

Quien use los datos puede fijar la versión y comprobar las sumas para saber que tiene lo mismo. El generador no puede
saber si un cambio de datos merece una versión nueva: eso lo decide quien cambia los datos y lo apunta en CHANGELOG.md. El
manifiesto hace visible cualquier cambio (cambia alguna suma), y --comprobar falla si no coincide con los archivos.
"""
import datetime
import hashlib
import json
import re

# Versión X.Y.Z sin ceros a la izquierda, como en el versionado semántico.
RE_ENTRADA = re.compile(r"## (0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*) - (\d{4}-\d{2}-\d{2})")


def sha256(texto):
    """Suma SHA-256 del texto en UTF-8. Los finales de línea ya vienen unificados al leer el archivo (read_text),
    así que un CSV con CRLF (Windows) da la misma suma que con LF."""
    return hashlib.sha256(texto.encode("utf-8")).hexdigest()


def leer_changelog(texto, errores):
    """La versión más reciente de CHANGELOG.md ("1.0.0") o None si no hay ninguna entrada válida; los problemas se añaden a `errores`.

    Cada entrada es un encabezado «## X.Y.Z - AAAA-MM-DD» con texto debajo. Van de la más reciente a la más antigua: las
    versiones bajan estrictamente y las fechas no suben.
    """
    entradas = []  # (versión, fecha, línea del encabezado, hay texto debajo)
    for n, linea in enumerate(texto.splitlines(), start=1):
        if linea.startswith("## "):
            coincide = RE_ENTRADA.fullmatch(linea)
            if not coincide:
                errores.append(f"CHANGELOG.md línea {n}: el encabezado debe ser «## X.Y.Z - AAAA-MM-DD»: {linea!r}")
                continue
            try:
                fecha = datetime.date.fromisoformat(coincide[4])
            except ValueError:
                errores.append(f"CHANGELOG.md línea {n}: fecha que no existe: {coincide[4]!r}")
                continue
            entradas.append([tuple(int(coincide[i]) for i in (1, 2, 3)), fecha, n, False])
        elif entradas and linea.strip():
            entradas[-1][3] = True
    if not entradas:
        errores.append("CHANGELOG.md: no hay ninguna entrada «## X.Y.Z - AAAA-MM-DD»")
        return None
    for version, _fecha, n, con_texto in entradas:
        if not con_texto:
            errores.append(f"CHANGELOG.md línea {n}: la entrada {'.'.join(map(str, version))} no dice qué cambió")
    for (nueva, fecha_nueva, _n, _t), (vieja, fecha_vieja, n, _t2) in zip(entradas, entradas[1:]):
        if nueva <= vieja:
            errores.append(f"CHANGELOG.md línea {n}: las versiones deben bajar de arriba abajo, y {'.'.join(map(str, vieja))} no es menor que {'.'.join(map(str, nueva))}")
        if fecha_nueva < fecha_vieja:
            errores.append(f"CHANGELOG.md línea {n}: las fechas no pueden subir de arriba abajo ({fecha_vieja} va después de {fecha_nueva})")
    return ".".join(map(str, entradas[0][0]))


def manifiesto(version, codigos, entradas, exportes):
    """El texto de exportes/manifiesto.json.

    `entradas` y `exportes` son listas de (ruta, filas, texto, columnas): `texto` es el contenido del archivo (con él se calcula la
    suma) y `columnas` la lista de columnas de un TSV, o None.
    """
    def archivo(ruta, filas, texto, columnas):
        datos = {"archivo": ruta, "filas": filas}
        if columnas is not None:
            datos["columnas"] = columnas
        datos["sha256"] = sha256(texto)
        return datos

    objeto = {
        "version": version,
        "idiomas": list(codigos),
        "entradas": [archivo(*e) for e in entradas],
        "exportes": [archivo(*e) for e in exportes],
    }
    return json.dumps(objeto, ensure_ascii=False, indent=2) + "\n"
