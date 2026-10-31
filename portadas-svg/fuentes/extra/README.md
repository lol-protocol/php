# fuentes/extra

Aquí van, si hacen falta, los contornos de las letras que las fuentes del proyecto no tienen (chino, japonés,
coreano, hebreo…). Cada `.json` de esta carpeta es un `{"código": [avance, "trazado"]}` como los de
`../trazos/`, y se genera con `herramientas/glifos-extra.py` a partir de una fuente que ya tengas:

```bash
python3 herramientas/glifos-extra.py NotoSansTC-Regular.otf fuentes/extra/chino.json --lista libros.csv
php bin/generar.php --lista=libros.csv --trazos
```

Sirven solo para la salida con `--trazos` (una fuente incrustada no se puede componer con estas letras), y con
ellas el generador mide el texto. No se suben aquí si la licencia de tu fuente no permite redistribuir sus
contornos; con `--extra=carpeta` puedes tenerlos en otro sitio. Esta carpeta se entrega vacía.
