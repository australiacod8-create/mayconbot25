import os
import logging
from telegram import Update
from telegram.ext import Application, CommandHandler, ContextTypes
from flask import Flask, request

# Configurar logging
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

# Inicializar aplicação Flask
app = Flask(__name__)

# Configuração da aplicação do Telegram
application = None

async def start_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Responde ao comando /start"""
    user = update.effective_user
    logger.info(f"Usuário {user.first_name} enviou /start")
    await update.message.reply_text(f"Olá {user.first_name}! Bot está funcionando via webhook!")

def setup_application():
    """Configura a aplicação do bot"""
    global application
    token = os.getenv("BOT_TOKEN")
    if not token:
        logger.error("❌ BOT_TOKEN não encontrado")
        raise ValueError("BOT_TOKEN não encontrado")
    
    application = Application.builder().token(token).build()
    application.add_handler(CommandHandler("start", start_command))
    return application

@app.route('/webhook', methods=['POST'])
async def webhook():
    """Processa as atualizações do Telegram"""
    try:
        data = request.get_json()
        update = Update.de_json(data, application.bot)
        await application.process_update(update)
        return '', 200
    except Exception as e:
        logger.error(f"Erro no webhook: {e}")
        return '', 500

@app.route('/health')
def health_check():
    """Endpoint para verificação de saúde"""
    return 'Bot está funcionando!'

@app.route('/')
def index():
    """Página inicial"""
    return 'Bot Telegram está rodando!'

async def set_webhook():
    """Configura o webhook"""
    token = os.getenv("BOT_TOKEN")
    webhook_url = f"https://{os.getenv('RENDER_EXTERNAL_HOSTNAME')}/webhook"
    
    try:
        await application.bot.set_webhook(webhook_url)
        logger.info(f"✅ Webhook configurado: {webhook_url}")
        return True
    except Exception as e:
        logger.error(f"❌ Erro ao configurar webhook: {e}")
        return False

if __name__ == "__main__":
    # Configurar aplicação
    setup_application()
    
    # Configurar webhook em segundo plano
    import threading
    import asyncio
    
    def run_async():
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        loop.run_until_complete(set_webhook())
    
    thread = threading.Thread(target=run_async)
    thread.start()
    
    # Iniciar servidor Flask
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port, debug=False)
