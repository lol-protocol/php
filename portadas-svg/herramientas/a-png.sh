#!/usr/bin/env bash
# Convierte los SVG de una carpeta en PNG (o JPG), para las tiendas de ebooks que no admiten SVG.
#
#   php bin/generar.php libro.json --trazos --salida=salida
#   bash herramientas/a-png.sh salida/libro [alto_en_px] [png|jpg]
#
# Usa lo primero que encuentre instalado: rsvg-convert, Inkscape o resvg. Genera la imagen de la portada, la
# contratapa y el lomo al lado de cada SVG. Con el alto por defecto (2560 px), una portada de 135 × 210 mm sale de
# unos 1650 × 2560 px, la medida que piden la mayoría de las tiendas.
#
# Conviene partir de SVG hechos con --trazos: rsvg-convert, Inkscape y resvg ignoran las fuentes incrustadas y
# pondrían otras (ver «Fuentes» en README.md).
set -euo pipefail

carpeta="${1:?uso: a-png.sh carpeta-con-svg [alto_en_px] [png|jpg]}"
alto="${2:-2560}"
formato="${3:-png}"

if [[ "$formato" != "png" && "$formato" != "jpg" ]]; then
    echo "El formato debe ser png o jpg, no «$formato»." >&2
    exit 2
fi
if ! [[ "$alto" =~ ^[0-9]+$ ]]; then
    echo "El alto debe ser un número de píxeles, no «$alto»." >&2
    exit 2
fi

convertir() { # svg png
    if command -v rsvg-convert >/dev/null 2>&1; then
        rsvg-convert --height "$alto" "$1" -o "$2"
    elif command -v inkscape >/dev/null 2>&1; then
        inkscape "$1" --export-type=png --export-height="$alto" --export-filename="$2" >/dev/null 2>&1
    elif command -v resvg >/dev/null 2>&1; then
        resvg --height "$alto" "$1" "$2"
    else
        echo "No hay ningún programa para convertir SVG: instala rsvg-convert (librsvg2-bin), Inkscape o resvg." >&2
        exit 1
    fi
}

hechos=0
for svg in "$carpeta"/*.svg; do
    [[ -e "$svg" ]] || { echo "No hay SVG en $carpeta." >&2; exit 1; }
    base="${svg%.svg}"
    convertir "$svg" "$base.png"
    if [[ "$formato" == "jpg" ]]; then
        # JPG no tiene transparencia: se aplana sobre blanco.
        if command -v convert >/dev/null 2>&1; then
            convert "$base.png" -background white -flatten -quality 92 "$base.jpg"
            rm -f "$base.png"
        else
            echo "Para JPG hace falta ImageMagick (convert)." >&2
            exit 1
        fi
    fi
    hechos=$((hechos + 1))
done
echo "$hechos imágenes en $carpeta"
