#!/bin/bash

# Script para corrigir o erro do before_first_request no Flask
echo "Corrigindo erro do before_first_request..."

# Criar um novo app.py sem o before_first_request
cat > app.py << 'APP_EOF'
from flask import Flask, request
import threading
import asyncio
from src.bot import main as bot_main
import signal
import os

app = Flask(__name__)

# Variável global para controlar o bot
bot_thread = None
bot_started = False

@app.route('/')
def home():
    return "Servidor e bot estão rodando!"

@app.route('/health')
def health():
    return "OK", 200

def run_bot():
    """Função para executar o bot em uma thread separada"""
    try:
        print("🚀 Iniciando bot do Telegram...")
        print("✅ Função run_bot() foi chamada!")
        asyncio.run(bot_main())
        print("✅ Bot iniciado com sucesso!")
    except Exception as e:
        print(f"❌ Erro no bot: {e}")
        import traceback
        traceback.print_exc()

def start_bot():
    """Inicia o bot em uma thread separada"""
    global bot_thread, bot_started
    if not bot_started and (bot_thread is None or not bot_thread.is_alive()):
        bot_thread = threading.Thread(target=run_bot, daemon=True)
        bot_thread.start()
        bot_started = True
        print("✅ Bot iniciado em thread separada")

# Inicia o bot quando a primeira requisição chegar
@app.before_request
def initialize_bot():
    global bot_started
    if not bot_started:
        start_bot()

# Manipulador para graceful shutdown
def shutdown_handler(signum, frame):
    print("Recebido sinal de desligamento, encerrando aplicação...")
    exit(0)

if __name__ == '__main__':
    # Registra manipulador de sinais para shutdown graceful
    signal.signal(signal.SIGINT, shutdown_handler)
    signal.signal(signal.SIGTERM, shutdown_handler)
    
    # Inicia o bot
    start_bot()
    
    # Inicia o servidor Flask
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=False)
APP_EOF

# Fazer commit e push das mudanças
git add app.py
git commit -m "Corrige deprecated before_first_request do Flask"
git push origin main

echo "✅ Correção aplicada. Deploy automático iniciado no Render."
