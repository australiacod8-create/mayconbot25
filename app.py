from telegram import Update
from flask import Flask, request, jsonify
import asyncio
from src.bot import setup_application
import logging
import os

app = Flask(__name__)

# Variável global para a aplicação
bot_application = None

@app.route('/')
def home():
    return "Servidor e bot estão rodando via webhook!"

@app.route('/health')
def health():
    return "OK", 200

@app.route('/webhook', methods=['POST'])
def webhook():
    """Endpoint para receber updates do Telegram"""
    try:
        global bot_application
        if bot_application is None:
            return 'Bot não inicializado', 500
        
        # Processar update
        update = Update.de_json(request.get_json(), bot_application.bot)
        asyncio.run(bot_application.process_update(update))
        return 'ok'
    except Exception as e:
        logging.error(f"Erro no webhook: {e}")
        return 'error', 500

@app.before_request
def initialize_bot():
    """Inicializa o bot se necessário"""
    global bot_application
    if bot_application is None:
        bot_application = asyncio.run(setup_application())

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=False)
