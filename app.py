from flask import Flask, request
import asyncio
from src.bot import setup_application, set_webhook
import logging

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
async def webhook():
    """Endpoint para receber updates do Telegram"""
    try:
        global bot_application
        if not bot_application:
            bot_application = await setup_application()
        
        update = Update.de_json(await request.get_json(), bot_application.bot)
        await bot_application.process_update(update)
        return 'ok'
    except Exception as e:
        logging.error(f"Erro no webhook: {e}")
        return 'error', 500

@app.before_request
async def initialize_bot():
    """Inicializa o bot se necessário"""
    global bot_application
    if not bot_application:
        bot_application = await setup_application()
        await set_webhook()

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=False)
