"""Lector mínimo de archivos .rda de R (serialización XDR), solo para extraer datos numéricos.

Lo usa preparar_datos.py para leer los coeficientes del modelo log-cuadrático. Trata el archivo como datos: no
ejecuta nada de lo que contiene. Soporta lo que hay en esos archivos (listas con nombre, vectores, cadenas,
atributos y data.frame); un tipo que no conoce lo hace fallar en vez de adivinar. Solo biblioteca estándar.
"""
import bz2, gzip, lzma, struct, sys

def abrir(ruta):
    datos = open(ruta, "rb").read()
    for f in (gzip.decompress, bz2.decompress, lzma.decompress):
        try:
            return f(datos)
        except Exception:
            pass
    return datos

class Lector:
    def __init__(self, b):
        self.b, self.i, self.refs = b, 0, []
    def int(self):
        v = struct.unpack(">i", self.b[self.i:self.i+4])[0]; self.i += 4; return v
    def dbl(self, n):
        v = struct.unpack(">%dd" % n, self.b[self.i:self.i+8*n]); self.i += 8*n; return list(v)
    def raw(self, n):
        v = self.b[self.i:self.i+n]; self.i += n; return v
    def longitud(self):
        n = self.int()
        if n == -1:
            n = (self.int() << 32) + self.int()
        return n
    def item(self):
        flags = self.int()
        tipo, tiene_attr, tiene_tag = flags & 0xFF, bool(flags & 0x200), bool(flags & 0x400)
        if tipo == 254: return None            # NULL
        if tipo in (253, 242, 241, 251, 252): return ("env-especial", tipo)
        if tipo == 255: return self.refs[(flags >> 8) - 1]
        if tipo == 1:                          # símbolo
            s = self.item(); self.refs.append(s); return s
        if tipo == 9:                          # CHARSXP
            n = self.int()
            return None if n == -1 else self.raw(n).decode("utf-8", "replace")
        if tipo in (2, 6, 5, 17):              # LISTSXP, LANGSXP, ...
            attr = self.item() if tiene_attr else None
            tag = self.item() if tiene_tag else None
            car = self.item(); cdr = self.item()
            return ("par", tag, car, cdr, attr)
        if tipo == 4:                          # ENVSXP
            self.int(); self.refs.append(("env",)); self.item(); self.item(); self.item(); self.item(); return ("env",)
        if tipo in (10, 13):
            n = self.longitud(); v = [self.int() for _ in range(n)]
            return self.con_attr(v, tiene_attr)
        if tipo == 14:
            n = self.longitud(); v = self.dbl(n); return self.con_attr(v, tiene_attr)
        if tipo == 16:
            n = self.longitud(); v = [self.item() for _ in range(n)]; return self.con_attr(v, tiene_attr)
        if tipo in (19, 20):
            n = self.longitud(); v = [self.item() for _ in range(n)]; return self.con_attr(v, tiene_attr)
        if tipo == 24:
            n = self.longitud(); return self.con_attr(self.raw(n), tiene_attr)
        raise ValueError("tipo no soportado %d en %d" % (tipo, self.i))
    def con_attr(self, v, tiene_attr):
        if tiene_attr:
            return ("attr", v, self.item())
        return v

def cargar(ruta):
    b = abrir(ruta)
    assert b[:5] in (b"RDX2\n", b"RDX3\n"), b[:6]
    l = Lector(b); l.i = 5
    assert b[l.i:l.i+2] == b"X\n"; l.i += 2
    version = l.int(); l.int(); l.int()
    if version == 3:
        n = l.int(); l.raw(n)
    # un .rda es un pairlist de objetos con nombre
    raiz = l.item()
    return raiz

def aplanar(par):
    out = {}
    while par:
        _, tag, car, cdr, _ = par
        out[tag] = car; par = cdr
    return out
