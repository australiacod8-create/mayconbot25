#!/bin/bash

echo "Corrigindo erro do python-telegram-bot..."

# Atualizar para a versão mais recente do python-telegram-bot
sed -i 's/python-telegram-bot==20.7/python-telegram-bot>=20.8/' requirements.txt

# Fazer commit e push
git add requirements.txt
git commit -m "Atualiza python-telegram-bot para versão mais recente"
git push origin main

echo "✅ python-telegram-bot atualizado. Deploy automático iniciado."
