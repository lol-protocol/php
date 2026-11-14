#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

VENV_PATH="/opt/venvs/whisper"
# Version fija de Whisper: sin esto, cada instalacion bajaria "la ultima" y dos servidores quedarian distintos
# (y una version nueva podria romper tu uso). WHISPER_VERSION=<fecha> la cambia (ej. 20240930).
WHISPER_VERSION=${WHISPER_VERSION:-20250625}
# Espacio libre minimo: PyTorch (con CUDA, aunque el VPS no tenga GPU) y sus dependencias ocupan varios GB, y un
# disco que se llena a mitad deja el entorno virtual a medias. WHISPER_MIN_GB=<n> lo cambia; WHISPER_FORCE=1 lo salta.
WHISPER_MIN_GB=${WHISPER_MIN_GB:-8}
[[ "$WHISPER_VERSION" =~ ^[0-9]{8}$ ]] || { echo "ERROR: WHISPER_VERSION debe ser una fecha de 8 digitos (ej. 20250625); recibido: ${WHISPER_VERSION:0:40}" >&2; exit 2; }
[[ "$WHISPER_MIN_GB" =~ ^[1-9][0-9]{0,3}$ ]] || { echo "ERROR: WHISPER_MIN_GB debe ser un entero positivo (GB); recibido: ${WHISPER_MIN_GB:0:20}" >&2; exit 2; }

print_header "02_I" "Python: Whisper + librerias basicas"
echo "NOTA: requiere Python ya instalado (script 02_C-install-python.sh)."
echo "AVISO: esto descarga PyTorch (varios GB en x86 con CUDA). Puede tardar varios minutos."
echo ""

FREE_GB=$(disk_free_gb /opt)
if [ -n "$FREE_GB" ] && [ "$FREE_GB" -lt "$WHISPER_MIN_GB" ]; then
    echo "ERROR: quedan ${FREE_GB} GB libres en el disco y Whisper necesita al menos ${WHISPER_MIN_GB} GB (PyTorch + dependencias)." >&2
    echo "Libera espacio o, si sabes que alcanza, repite con WHISPER_FORCE=1 (o baja WHISPER_MIN_GB)." >&2
    [ "${WHISPER_FORCE:-0}" = 1 ] || exit 1
    echo "WHISPER_FORCE=1: se sigue de todos modos." >&2
fi
RAM_MB=$(mem_total_mb)
if [ "$RAM_MB" -gt 0 ] && [ "$RAM_MB" -lt 2000 ]; then
    echo "AVISO: ${RAM_MB} MB de RAM: usa solo los modelos tiny y base (los demas se quedan sin memoria)."
elif [ "$RAM_MB" -gt 0 ] && [ "$RAM_MB" -lt 5000 ]; then
    echo "AVISO: ${RAM_MB} MB de RAM: hasta el modelo small; medium y large necesitan bastante mas memoria."
fi
echo "Whisper $WHISPER_VERSION (fijo; WHISPER_VERSION lo cambia)."
echo ""

check_dependency python3 "./02_C-install-python.sh"
# python3 viene de fabrica en Ubuntu: lo que hace falta es el modulo venv (lo instala 02_C), y sin el
# "python3 -m venv" fallaria mas abajo, despues de bajar ffmpeg.
if ! python3 -m venv --help > /dev/null 2>&1; then
    echo "ERROR: falta python3-venv." >&2
    echo "Corre primero: ./02_C-install-python.sh" >&2
    exit 1
fi

# ffmpeg es requerido por Whisper para decodificar audio/video
echo "[1/3] Instalando ffmpeg..."
sudo apt-get install -y ffmpeg

# Ubuntu 24.04 bloquea 'pip install' global (PEP 668), asi que usamos
# un entorno virtual compartido en vez de --break-system-packages
echo "[2/3] Creando entorno virtual compartido en $VENV_PATH..."
sudo mkdir -p /opt/venvs
sudo python3 -m venv $VENV_PATH
sudo $VENV_PATH/bin/pip install --upgrade pip

echo "[3/3] Instalando Whisper y librerias basicas (puede tardar)..."
sudo $VENV_PATH/bin/pip install \
    "openai-whisper==$WHISPER_VERSION" \
    requests \
    numpy \
    pandas \
    flask \
    fastapi \
    uvicorn \
    python-dotenv \
    gunicorn

# Comando 'whisper' disponible globalmente sin activar el venv
sudo ln -sf $VENV_PATH/bin/whisper /usr/local/bin/whisper

echo ""
echo "✓ Whisper y librerias basicas instaladas"
echo ""
whisper --help | head -5
echo ""
echo "Uso rapido:"
echo "  whisper audio.mp3 --model base --language Spanish"
echo ""
echo "Para usar las librerias (flask, pandas, etc.) en tus scripts:"
echo "  source $VENV_PATH/bin/activate"
echo "  python3 tu_script.py"
echo ""
echo "Modelos disponibles (de menor a mayor precision/tamaño):"
echo "  tiny, base, small, medium, large"
echo "  (se descargan automaticamente la primera vez que los uses)"
