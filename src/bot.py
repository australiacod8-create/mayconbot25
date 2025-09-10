import os
import logging
from telegram import Update
from telegram.ext import Application, CommandHandler, ContextTypes
from aiohttp import web

# Configurar logging
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

async def start_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Responde ao comando /start"""
    user = update.effective_user
    logger.info(f"Usuário {user.first_name} enviou /start")
    await update.message.reply_text(f"Olá {user.first_name}! Bot está funcionando via webhook!")

# Configuração da aplicação
application = None

async def setup_application():
    """Configura a aplicação do bot"""
    global application
    token = os.getenv("BOT_TOKEN")
    if not token:
        logger.error("❌ BOT_TOKEN não encontrado")
        raise ValueError("BOT_TOKEN não encontrado")
    
    application = Application.builder().token(token).build()
    application.add_handler(CommandHandler("start", start_command))
    return application

async def webhook_handler(request):
    """Processa as atualizações do Telegram"""
    try:
        data = await request.json()
        update = Update.de_json(data, application.bot)
        await application.process_update(update)
        return web.Response(status=200)
    except Exception as e:
        logger.error(f"Erro no webhook: {e}")
        return web.Response(status=500)

async def health_check(request):
    """Endpoint para verificação de saúde"""
    return web.Response(text="Bot está funcionando!")

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

async def main():
    """Função principal"""
    await setup_application()
    
    # Configurar webhook
    success = await set_webhook()
    if not success:
        return
    
    # Configurar servidor web
    app = web.Application()
    app.router.add_post("/webhook", webhook_handler)
    app.router.add_get("/health", health_check)
    
    port = int(os.environ.get("PORT", 5000))
    runner = web.AppRunner(app)
    await runner.setup()
    site = web.TCPSite(runner, host="0.0.0.0", port=port)
    await site.start()
    logger.info(f"Servidor iniciado na porta {port}")
    
    # Manter o servidor rodando
    await asyncio.Event().wait()

if __name__ == "__main__":
    import asyncio
    asyncio.run(main())
