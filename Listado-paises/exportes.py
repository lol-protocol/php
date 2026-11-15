"""Exportes de la tabla para usarla desde otros proyectos: exportes/paises.json y exportes/paises.sql.

Los escribe generar_tabla.py y los comprueba con --comprobar, como las demás salidas. Cada país trae
sus datos de paises.csv, sus índices y recursos de paises_pos.csv, la prioridad y la exclusión de la
regla vigente de priorizacion.csv, y la banda de nacidos de poblacion_historica_detalle.csv.

JSON: {"paises": [...]}, con números como números, vacíos como null, las exclusiones como booleanos
y los idiomas como lista. SQL: para PostgreSQL; recrea la tabla listado_paises dentro de una
transacción, así que se puede cargar de nuevo sin dejar filas viejas. Solo biblioteca estándar.
"""
import json

NUMERICAS = ["poblacion_millones", "poblacion_historica_acumulada", "idh", "pib_per_capita_usd",
             "pib_per_capita_ppa_usd", "alfabetizacion_adultos", "usuarios_internet", "emigrantes_pct",
             "democracia_eiu", "corrupcion_cpi"]
ENTEROS = ["pib_per_capita_usd", "pib_per_capita_ppa_usd", "corrupcion_cpi", "indice_genealogia",
           "indice_operabilidad", "indice_lectura", "indice_pos", "indice_pos_local",
           "prioridad_genealogia", "prioridad_pos"]
# (columna, tipo SQL), en el orden de las columnas del exporte.
COLUMNAS = [
    ("codigo", "CHAR(2) PRIMARY KEY"), ("pais", "TEXT NOT NULL"),
    ("tipo", "TEXT NOT NULL CHECK (tipo IN ('estado', 'territorio', 'entidad disputada'))"),
    ("region", "TEXT NOT NULL"), ("poblacion_millones", "NUMERIC"),
    ("poblacion_historica_acumulada", "NUMERIC"), ("nacidos_minimo_millones", "NUMERIC"),
    ("nacidos_maximo_millones", "NUMERIC"), ("idh", "NUMERIC CHECK (idh BETWEEN 0 AND 1)"),
    ("pib_per_capita_usd", "INTEGER"), ("pib_per_capita_ppa_usd", "INTEGER"),
    ("alfabetizacion_adultos", "NUMERIC"), ("usuarios_internet", "NUMERIC"),
    ("emigrantes_pct", "NUMERIC"), ("libertad_fh", "TEXT"), ("democracia_eiu", "NUMERIC"),
    ("corrupcion_cpi", "INTEGER"),
    ("indice_genealogia", "SMALLINT NOT NULL CHECK (indice_genealogia BETWEEN 1 AND 5)"),
    ("indice_operabilidad", "SMALLINT NOT NULL CHECK (indice_operabilidad BETWEEN 1 AND 5)"),
    ("prioridad_genealogia", "SMALLINT NOT NULL"), ("excluido_genealogia", "BOOLEAN NOT NULL"),
    ("comentarios", "TEXT NOT NULL"), ("idiomas", "TEXT[] NOT NULL"),
    ("indice_lectura", "SMALLINT NOT NULL CHECK (indice_lectura BETWEEN 1 AND 5)"),
    ("indice_pos", "SMALLINT NOT NULL CHECK (indice_pos BETWEEN 1 AND 5)"),
    ("indice_pos_local", "SMALLINT NOT NULL CHECK (indice_pos_local BETWEEN 1 AND 5)"),
    ("prioridad_pos", "SMALLINT NOT NULL"), ("excluido_pos", "BOOLEAN NOT NULL"),
    ("comentarios_pos", "TEXT NOT NULL"),
]


def _numero(texto, entero):
    if texto == "" or texto is None:
        return None
    return int(float(texto)) if entero else float(texto)


def filas(paises, pos, final, banda):
    """Lista de dicts, uno por país, en el orden de paises (los de paises.csv)."""
    salida = []
    for f in paises:
        c = f["codigo"]
        p, v = pos[c], final[c]
        minimo, maximo = banda.get(c, (None, None))
        fila = {
            "codigo": c, "pais": f["pais"], "tipo": f["tipo"], "region": f["region"],
            **{k: _numero(f[k], k in ENTEROS) for k in NUMERICAS},
            "nacidos_minimo_millones": minimo, "nacidos_maximo_millones": maximo,
            "libertad_fh": f["libertad_fh"] or None,
            "indice_genealogia": int(f["indice_genealogia"]), "indice_operabilidad": int(f["indice_operabilidad"]),
            "prioridad_genealogia": int(v["prioridad_genealogia"]),
            "excluido_genealogia": v["excluido_genealogia"] == "si", "comentarios": f["comentarios"],
            "idiomas": [i.strip() for i in p["idioma_principal"].split(";")],
            "indice_lectura": int(p["indice_lectura"]), "indice_pos": int(p["indice_pos"]),
            "indice_pos_local": int(p["indice_pos_local"]), "prioridad_pos": int(v["prioridad_pos"]),
            "excluido_pos": v["excluido_pos"] == "si", "comentarios_pos": p["comentarios_pos"],
        }
        salida.append({k: fila[k] for k, _ in COLUMNAS})
    return salida


def json_texto(lista):
    return json.dumps({"paises": lista}, ensure_ascii=False, indent=1) + "\n"


def literal(valor):
    """Valor de Python como literal de PostgreSQL."""
    if valor is None:
        return "NULL"
    if isinstance(valor, bool):
        return "TRUE" if valor else "FALSE"
    if isinstance(valor, (int, float)):
        return repr(valor)
    if isinstance(valor, list):
        return "ARRAY[" + ", ".join(literal(v) for v in valor) + "]::TEXT[]"
    return "'" + str(valor).replace("'", "''") + "'"


def sql_texto(lista):
    columnas = ",\n".join(f"  {nombre} {tipo}" for nombre, tipo in COLUMNAS)
    nombres = ", ".join(nombre for nombre, _ in COLUMNAS)
    valores = ",\n".join("  (" + ", ".join(literal(f[nombre]) for nombre, _ in COLUMNAS) + ")" for f in lista)
    return (
        "-- Generado por generar_tabla.py (exportes.py). No editar a mano. Para PostgreSQL.\n"
        "BEGIN;\n"
        "DROP TABLE IF EXISTS listado_paises;\n"
        f"CREATE TABLE listado_paises (\n{columnas}\n);\n"
        f"INSERT INTO listado_paises ({nombres}) VALUES\n{valores};\n"
        "COMMIT;\n")
