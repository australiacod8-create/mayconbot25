from flask import request, jsonify
from src.config import Config
from src.models.database import update_payment_status
from src.payment.mercadopago_api import MercadoPagoAPI
import hmac
import hashlib
import json

def verify_webhook_signature(payload, signature):
    """Verifica a assinatura do webhook para segurança"""
    secret = Config.WEBHOOK_SECRET.encode('utf-8')
    expected_signature = hmac.new(secret, payload, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected_signature, signature)

def setup_webhook_routes(app):
    """Configura as rotas de webhook"""
    
    # Webhook para Mercado Pago
    @app.route('/webhook/mercadopago', methods=['POST'])
    def mercadopago_webhook():
        try:
            # Log para debug (útil para ver o que está chegando)
            print(f"Webhook recebido do Mercado Pago: {request.json}")
            
            data = request.json
            if not data:
                return jsonify({'error': 'Dados não fornecidos'}), 400
            
            # Verificar se é uma notificação de pagamento
            if data.get('type') == 'payment':
                payment_id = data.get('data', {}).get('id')
                
                if payment_id:
                    # Usar a API do Mercado Pago para obter detalhes do pagamento
                    mp_api = MercadoPagoAPI()
                    payment_info = mp_api.get_payment(payment_id)
                    
                    if payment_info and payment_info.get('status') == 'approved':
                        # Pagamento aprovado!
                        order_id = payment_info.get('external_reference', '')
                        amount = payment_info.get('transaction_amount', 0)
                        
                        # Atualizar status no banco de dados
                        update_payment_status(payment_id, 'paid')
                        
                        print(f"Pagamento aprovado: {payment_id}, Pedido: {order_id}, Valor: {amount}")
                        
                        # Aqui você pode adicionar lógica para notificar o usuário
                        # por exemplo, enviar uma mensagem via Telegram
                    
                    return jsonify({'status': 'processed'}), 200
            
            return jsonify({'status': 'ignored'}), 200
            
        except Exception as e:
            print(f"Erro ao processar webhook do Mercado Pago: {e}")
            return jsonify({'error': 'Erro interno'}), 500

    # Health check endpoint
    @app.route('/health', methods=['GET'])
    def health_check():
        """Endpoint para verificar se a aplicação está rodando"""
        return jsonify({'status': 'ok'}), 200

    # Webhook para Pagar.me (mantido para compatibilidade, pode remover depois)
    @app.route('/webhook/pagarme', methods=['POST'])
    def pagarme_webhook():
        return jsonify({'error': 'Endpoint desativado. Use Mercado Pago.'}), 410
