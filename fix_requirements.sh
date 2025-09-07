#!/bin/bash

# Script para resolver conflitos no requirements.txt
echo "Resolvendo conflitos no requirements.txt..."

# Backup do arquivo original
cp requirements.txt requirements.txt.backup

# Remove marcadores de conflito do Git
sed -i '/^<<<<<<< HEAD$/d' requirements.txt
sed -i '/^=======$/d' requirements.txt
sed -i '/^>>>>>>> /d' requirements.txt

# Remove linhas em branco extras
sed -i '/^$/d' requirements.txt

echo "Conflito resolvido. Verifique o arquivo:"
cat requirements.txt

echo "Fazendo commit da correção..."
git add requirements.txt
git commit -m "Resolve conflito no requirements.txt"
git push origin main
