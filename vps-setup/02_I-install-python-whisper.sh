#!/bin/bash
set -e

source "$(dirname "$0")/lib.sh"

VENV_PATH="/opt/venvs/whisper"

print_header "02_I" "Python: Whisper + librerias basicas"
echo "NOTA: requiere Python ya instalado (script 02_C-install-python.sh)."
echo "AVISO: esto descarga PyTorch (varios GB en x86 con CUDA). Puede tardar varios minutos."
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
    openai-whisper \
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
