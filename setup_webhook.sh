#!/bin/bash

echo "Configurando webhook para o bot..."

# Criar nova versão do bot.py com webhook
cat > src/bot.py << 'BOT_EOF'
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
BOT_EOF

# Atualizar app.py para suportar webhook
cat > app.py << 'APP_EOF'
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
APP_EOF

# Fazer commit e push
git add .
git commit -m "Implementa webhook para o bot"
git push origin main

echo "✅ Webhook configurado. Deploy automático iniciado."
