import os
import logging
from telegram import Update
from telegram.ext import Application, CommandHandler, ContextTypes
from src.config import Config
from src.payment.pix_service import PIXService
from src.models.database import init_db, save_payment_info, get_last_payment

# Configurar logging
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)

# Inicializar serviços
pix_service = PIXService()
init_db()

def generate_order_id():
    """Gera um ID único para o pedido"""
    import uuid
    return str(uuid.uuid4())[:8]

async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Comando /start"""
    await update.message.reply_text(
        "Olá! Eu sou um bot de pagamentos PIX. Use /pix para gerar um pagamento."
    )

async def pix_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Comando /pix - Gera um pagamento PIX"""
    try:
        user = update.message.from_user
        order_id = generate_order_id()
        
        customer_info = {
            "name": user.first_name + (f" {user.last_name}" if user.last_name else ""),
            "email": f"{user.id}@telegram.user",
            "type": "individual",
            "document": "00000000000",
            "phones": {
                "home_phone": {
                    "country_code": "55",
                    "number": "000000000",
                    "area_code": "00"
                }
            }
        }
        
        # Valor fixo para exemplo - pode ser parametrizado
        amount = 50.00
        
        # Gerar pagamento PIX
        pix_data = pix_service.generate_pix_payment(amount, customer_info, order_id)
        
        if not pix_data:
            await update.message.reply_text("❌ Erro ao gerar pagamento. Tente novamente.")
            return
        
        # Salvar informações do pagamento
        save_payment_info(user.id, order_id, pix_data['charge_id'], amount)
        
        # Enviar QR Code para o usuário
        message = f"💳 *Pagamento PIX*\n\n"
        message += f"Valor: R$ {amount:.2f}\n"
        message += f"ID do Pedido: {order_id}\n"
        message += f"Expira em: {pix_data['expiration']}\n\n"
        message += "Use o QR Code abaixo para pagar:"
        
        # Enviar imagem do QR Code
        await update.message.reply_photo(
            photo=pix_data['qr_code_image'],
            caption=message,
            parse_mode='Markdown'
        )
        
        # Enviar código PIX copia e cola
        await update.message.reply_text(
            f"📋 *Código PIX (Copia e Cola):*\n\n`{pix_data['qr_code_text']}`",
            parse_mode='Markdown'
        )
        
    except Exception as e:
        logging.error(f"Erro no comando pix: {e}")
        await update.message.reply_text("❌ Ocorreu um erro. Tente novamente.")

async def check_payment(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Comando /check - Verifica status do pagamento"""
    try:
        user_id = update.message.from_user.id
        
        # Buscar informações do último pagamento
        payment_info = get_last_payment(user_id)
        
        if not payment_info:
            await update.message.reply_text("ℹ️ Nenhum pagamento encontrado.")
            return
        
        # Verificar status
        status = pix_service.check_payment_status(payment_info['charge_id'])
        
        if status and status['status'] == 'paid':
            await update.message.reply_text("✅ Pagamento confirmado! Obrigado.")
        else:
            await update.message.reply_text("⏳ Pagamento ainda não confirmado.")
            
    except Exception as e:
        logging.error(f"Erro ao verificar pagamento: {e}")
        await update.message.reply_text("❌ Ocorreu um erro ao verificar pagamento.")

def main():
    """Inicia o bot"""
    # Obter token do Telegram
    telegram_token = Config.TELEGRAM_BOT_TOKEN
    if not telegram_token:
        logging.error("Token do Telegram não configurado!")
        return
    
    # Criar aplicação
    application = Application.builder().token(telegram_token).build()
    
    # Adicionar handlers
    application.add_handler(CommandHandler("start", start))
    application.add_handler(CommandHandler("pix", pix_command))
    application.add_handler(CommandHandler("check", check_payment))
    
    # Iniciar bot
    logging.info("Bot iniciado...")
    application.run_polling()

if __name__ == "__main__":
    main()
