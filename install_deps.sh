#!/bin/bash

# Script para Windows/Git Bash
echo "Instalando dependências..."

# Verificar Python
python --version
if [ $? -ne 0 ]; then
    echo "Python não encontrado"
    exit 1
fi

# Instalar dependências diretamente (sem ambiente virtual)
pip install --upgrade pip
pip install flask==3.1.2
pip install python-telegram-bot==20.0a0
pip install gunicorn==20.1.0
pip install requests==2.26.0
pip install cryptography==3.3.2
pip install qrcode[pil]==7.3.1
pip install python-dotenv==0.19.0

echo "✅ Instalação concluída!"
