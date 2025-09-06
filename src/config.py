import os
from dotenv import load_dotenv

load_dotenv()

class Config:
    # Configurações do Pagar.me
    PAGARME_API_KEY = os.getenv('PAGARME_API_KEY')
    PAGARME_ENCRYPTION_KEY = os.getenv('PAGARME_ENCRYPTION_KEY')
    
    # Configurações do Webhook
    WEBHOOK_SECRET = os.getenv('WEBHOOK_SECRET')
    
    # Configurações do Telegram
    TELEGRAM_BOT_TOKEN = os.getenv('TELEGRAM_BOT_TOKEN')
    
    # Outras configurações
    BASE_URL = os.getenv('BASE_URL', 'https://seu-bot.onrender.com')
    DATABASE_URL = os.getenv('DATABASE_URL', 'sqlite:///payments.db')
