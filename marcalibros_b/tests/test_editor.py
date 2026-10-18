"""Editor de usuario: subidas seguras y renderizado de recetas."""
import io
import json
import sys
from pathlib import Path

import pytest
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "generator"))
import editor  # noqa: E402
import modelo  # noqa: E402
from qa import MIN_CONTRASTE_TEXTO, contraste_texto  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
MANIFIESTO = json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))
PIEZAS = {e["id"] for e in MANIFIESTO["entradas"]}


def _bytes(im, formato, **kw):
    b = io.BytesIO()
    im.save(b, formato, **kw)
    return b.getvalue()


def _foto(color=(120, 140, 160), tam=(600, 1500)):
    return Image.new("RGB", tam, color)


def test_acepta_jpg_png_webp_y_recodifica_a_jpeg_sin_metadatos():
    exif = Image.Exif()
    exif[0x010E] = "descripción privada"  # ImageDescription
    exif[0x0112] = 6  # rotada
    jpg = _bytes(_foto(), "JPEG", exif=exif)
    png = _bytes(Image.new("RGBA", (300, 800), (10, 200, 30, 128)), "PNG")
    webp = _bytes(_foto(tam=(400, 900)), "WEBP")
    for datos in (jpg, png, webp):
        salida = Image.open(io.BytesIO(editor.recodificar(datos)))
        assert salida.format == "JPEG" and salida.mode == "RGB"
        assert not salida.info.get("exif") and not salida.getexif()
    assert Image.open(io.BytesIO(editor.recodificar(jpg))).size == (1500, 600)  # orientación aplicada


@pytest.mark.parametrize("datos", [
    b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    b"GIF89a\x01\x00\x01\x00\x00\x00\x00;",
    b"%PDF-1.4 ...",
    b"MZ\x90\x00 ejecutable",
    b"",
    b"\xff\xd8\xff truncado",
])
def test_rechaza_lo_que_no_es_imagen_permitida(datos):
    with pytest.raises(editor.ErrorEditor):
        editor.recodificar(datos)


def test_limites_de_bytes_pixeles_y_tamano_minimo(monkeypatch):
    with pytest.raises(editor.ErrorEditor, match="grande"):
        editor.recodificar(_bytes(Image.new("L", (6000, 5000)), "PNG"))  # 30 MP, pesa poco
    with pytest.raises(editor.ErrorEditor, match="pequeña"):
        editor.recodificar(_bytes(_foto(tam=(40, 300)), "PNG"))
    monkeypatch.setattr(editor, "MAX_BYTES", 1000)
    with pytest.raises(editor.ErrorEditor, match="pesa"):
        editor.recodificar(_bytes(_foto(), "JPEG"))


def test_png_con_extension_o_contenido_enganoso_no_pasa():
    # firma de PNG pero cuerpo de otra cosa
    with pytest.raises(editor.ErrorEditor):
        editor.recodificar(b"\x89PNG\r\n\x1a\n" + b"<svg/>" * 50)


def test_almacen_ids_aleatorios_cuotas_y_propiedad(tmp_path, monkeypatch):
    a = editor.AlmacenSubidas(tmp_path)
    datos = _bytes(_foto(), "JPEG")
    i1, i2 = a.guardar("u1", datos), a.guardar("u1", datos)
    assert i1 != i2 and len(i1) == 32 and (tmp_path / f"{i1}.jpg").is_file()
    assert a.ruta(i1, "u1").name == f"{i1}.jpg"
    with pytest.raises(editor.ErrorEditor):
        a.ruta(i1, "u2")  # no es suya
    for mal in ("../x", "a" * 31, "g" * 32, "/etc/passwd", i1.upper()):
        with pytest.raises(editor.ErrorEditor):
            a.ruta(mal)
    monkeypatch.setattr(editor, "CUOTA_IMAGENES", 2)
    with pytest.raises(editor.ErrorEditor, match="máximo"):
        a.guardar("u1", datos)
    a.guardar("u2", datos)  # la cuota es por usuario
    a.borrar(i1, "u1")
    assert not (tmp_path / f"{i1}.jpg").exists()
    a.guardar("u1", datos)


def test_cuota_de_bytes(tmp_path, monkeypatch):
    a = editor.AlmacenSubidas(tmp_path)
    monkeypatch.setattr(editor, "CUOTA_BYTES", 10)
    with pytest.raises(editor.ErrorEditor, match="espacio"):
        a.guardar("u1", _bytes(_foto(), "JPEG"))


def _receta(fondo, texto=None):
    r = {"fondo": fondo}
    if texto:
        r["texto"] = texto
    return {"id": "usr:" + "ab" * 16, "origen": "usuario", "version": 1, "propietario": "u1",
            "creado": "2026-10-18T09:30:00Z", "receta": r}


def test_receta_generada_y_plantilla_tienen_el_tamano_del_catalogo():
    gen = _receta({"tipo": "generado", "paleta": "noche", "arquetipo": "colinas", "motivo": "luna", "semilla": 5},
                  {"frase": "Lee despacio.", "autor": "Yo"})
    pla = _receta({"tipo": "plantilla", "pieza": "romanticismo-becquer-01"})
    for d in (gen, pla):
        assert modelo.validar_diseno(d, PIEZAS) == []
        img, _ = editor.renderizar_receta(d["receta"], MANIFIESTO)
        assert img.size == (200, 1400)
    img, info = editor.renderizar_receta(gen["receta"], MANIFIESTO, densidad=2)
    assert img.size == (400, 2800) and info["contraste"] >= MIN_CONTRASTE_TEXTO
    assert editor.renderizar_receta(gen["receta"], MANIFIESTO)[0].tobytes() == editor.renderizar_receta(gen["receta"], MANIFIESTO)[0].tobytes()


def test_valores_desconocidos_se_rechazan():
    for fondo in ({"tipo": "generado", "paleta": "nope", "arquetipo": "colinas"},
                  {"tipo": "generado", "paleta": "noche", "arquetipo": "nope"},
                  {"tipo": "generado", "paleta": "noche", "arquetipo": "colinas", "motivo": "nope"},
                  {"tipo": "plantilla", "pieza": "no-existe-01"}):
        with pytest.raises(editor.ErrorEditor):
            editor.renderizar_receta({"fondo": fondo}, MANIFIESTO)


def test_receta_con_imagen_recorta_y_garantiza_contraste(tmp_path):
    a = editor.AlmacenSubidas(tmp_path)
    # gris medio: ningún tono de texto llega a 4,5:1 sin velo
    ident = a.guardar("u1", _bytes(_foto((128, 128, 128), (700, 2400)), "PNG"))
    receta = {"fondo": {"tipo": "imagen", "imagen": ident, "recorte": {"x": 50, "y": 100, "ancho": 300, "alto": 2100}},
              "texto": {"frase": "Todo lo sólido se desvanece.", "autor": "Marx", "tono": "claro"}}
    img, info = editor.renderizar_receta(receta, MANIFIESTO, a, "u1")
    assert img.size == (200, 1400) and info["velo"] > 0 and info["contraste"] >= MIN_CONTRASTE_TEXTO
    receta["fondo"]["recorte"]["alto"] = 1000
    with pytest.raises(editor.ErrorEditor, match="1:7"):
        editor.renderizar_receta(receta, MANIFIESTO, a, "u1")
    receta["fondo"]["recorte"] = {"x": 600, "y": 0, "ancho": 300, "alto": 2100}
    with pytest.raises(editor.ErrorEditor, match="sale"):
        editor.renderizar_receta(receta, MANIFIESTO, a, "u1")
    receta["fondo"]["recorte"] = {"x": 50, "y": 100, "ancho": 300, "alto": 2100}
    with pytest.raises(editor.ErrorEditor):
        editor.renderizar_receta(receta, MANIFIESTO, a, "otro")  # imagen ajena


def test_salida_jpeg_sin_metadatos():
    img, _ = editor.renderizar_receta({"fondo": {"tipo": "generado", "paleta": "noche", "arquetipo": "colinas"}}, MANIFIESTO)
    j = Image.open(io.BytesIO(editor.a_jpeg(img)))
    assert j.format == "JPEG" and not j.info.get("exif")
