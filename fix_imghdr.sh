#!/bin/bash

# Script para adicionar imghdr-backport ao requirements.txt
echo "Adicionando imghdr-backport para resolver erro do módulo imghdr..."

# Adiciona a dependência ao requirements.txt
echo "imghdr-backport" >> requirements.txt

# Faz commit e push
git add requirements.txt
git commit -m "Adiciona imghdr-backport para compatibilidade com Python 3.13"
git push origin main

echo "✅ Correção aplicada. O Render fará um novo deploy automaticamente."
