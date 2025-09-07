import asyncio
import logging
import os
from telegram.ext import Application, CommandHandler

# Configurar logging detalhado
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)

async def start_command(update, context):
    """Responde ao comando /start"""
    user = update.effective_user
    logging.info(f"Usuário {user.first_name} enviou /start")
    await update.message.reply_text(f"Olá {user.first_name}! Bot está funcionando!")

async def main():
    try:
        # Verificar se o token está disponível
        token = os.getenv("BOT_TOKEN")
        if not token:
            logging.error("❌ BOT_TOKEN não encontrado nas variáveis de ambiente")
            return
        
        logging.info("✅ BOT_TOKEN encontrado")
        
        # Configuração do bot
        application = Application.builder().token(token).build()
        
        # Adicionar handler para comando /start
        application.add_handler(CommandHandler("start", start_command))
        
        logging.info("🤖 Bot iniciado e aguardando mensagens...")
        await application.run_polling()
        
    except Exception as e:
        logging.error(f"❌ Erro no bot: {e}")
        import traceback
        logging.error(traceback.format_exc())

if __name__ == "__main__":
    asyncio.run(main())
