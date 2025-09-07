import os
import logging
from telegram import Update
from telegram.ext import Application, CommandHandler, CallbackContext

# Configurar logging
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)

async def start_command(update: Update, context: CallbackContext):
    """Responde ao comando /start"""
    user = update.effective_user
    logging.info(f"Usuário {user.first_name} enviou /start")
    await update.message.reply_text(f"Olá {user.first_name}! Bot está funcionando via webhook!")

# Configuração global da aplicação
application = None

async def setup_application():
    """Configura a aplicação do bot"""
    global application
    token = os.getenv("BOT_TOKEN")
    if not token:
        logging.error("❌ BOT_TOKEN não encontrado")
        return None
    
    application = Application.builder().token(token).build()
    application.add_handler(CommandHandler("start", start_command))
    return application

async def set_webhook():
    """Configura o webhook"""
    token = os.getenv("BOT_TOKEN")
    if not token:
        return False
    
    try:
        app = await setup_application()
        webhook_url = f"https://{os.getenv('RENDER_EXTERNAL_HOSTNAME', 'mayconbot25.onrender.com')}/webhook"
        await app.bot.set_webhook(webhook_url)
        logging.info(f"✅ Webhook configurado: {webhook_url}")
        return True
    except Exception as e:
        logging.error(f"❌ Erro ao configurar webhook: {e}")
        return False
