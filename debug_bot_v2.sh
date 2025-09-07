#!/bin/bash

# Script de automação para debug do bot no Render
echo "=============================================="
echo " AUTOMATIZANDO DEBUG DO BOT NO RENDER"
echo "=============================================="

# Backup dos arquivos originais
echo "🔁 Fazendo backup dos arquivos originais..."
cp app.py app.py.backup
cp src/bot.py src/bot.py.backup
echo "✅ Backups criados: app.py.backup e src/bot.py.backup"

# Adicionar logs de depuração ao app.py
echo "📝 Adicionando logs de depuração ao app.py..."
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
    global bot_thread
    if bot_thread is None or not bot_thread.is_alive():
        bot_thread = threading.Thread(target=run_bot, daemon=True)
        bot_thread.start()
        print("✅ Bot iniciado em thread separada")

# Inicia o bot quando o aplicativo Flask iniciar
@app.before_first_request
def initialize():
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

# Adicionar logs de depuração ao bot.py
echo "📝 Adicionando logs de depuração ao src/bot.py..."
cat > src/bot.py << 'BOT_EOF'
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
BOT_EOF

# Fazer pull primeiro para integrar mudanças remotas
echo "🔄 Fazendo pull para integrar mudanças remotas..."
git pull origin main

# Fazer commit e push das mudanças
echo "🚀 Fazendo commit e push das mudanças..."
git add .
git commit -m "Adiciona logs de depuração para troubleshooting do bot"
git push origin main

echo "=============================================="
echo "✅ DEPLOY INICIADO NO RENDER!"
echo "=============================================="
echo "Acesse o dashboard do Render para acompanhar o deploy:"
echo "https://dashboard.render.com/"
echo ""
echo "Após o deploy, verifique os logs para ver:"
echo "1. Se o bot está iniciando"
echo "2. Se há mensagens de erro"
echo "3. Se o token do bot está sendo encontrado"
echo ""
echo "Para restaurar os arquivos originais, execute:"
echo "cp app.py.backup app.py"
echo "cp src/bot.py.backup src/bot.py"
