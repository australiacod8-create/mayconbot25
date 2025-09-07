#!/bin/bash

# Script para atualizar a versão do python-telegram-bot
echo "Atualizando python-telegram-bot para versão 20.7..."

# Substitui a versão no requirements.txt
sed -i 's/python-telegram-bot==20.0a0/python-telegram-bot==20.7/' requirements.txt

# Remove a linha do imghdr-backport (se existir)
sed -i '/imghdr-backport/d' requirements.txt

# Faz commit e push
git add requirements.txt
git commit -m "Atualiza python-telegram-bot para versão 20.7 para suporte ao Python 3.13"
git push origin main

echo "✅ python-telegram-bot atualizado. O Render fará um novo deploy automaticamente."
