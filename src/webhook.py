from flask import request, jsonify
from src.config import Config
from src.models.database import update_payment_status
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
    
    @app.route('/webhook/pagarme', methods=['POST'])
    def pagarme_webhook():
        # Verificar assinatura do webhook
        signature = request.headers.get('X-Hub-Signature', '').replace('sha256=', '')
        payload = request.get_data()
        
        if not verify_webhook_signature(payload, signature):
            return jsonify({'error': 'Assinatura inválida'}), 401
        
        data = request.json
        event_type = data.get('type')
        
        if event_type == 'charge.paid':
            # Processar pagamento confirmado
            charge_id = data['data']['id']
            amount = data['data']['paid_amount'] / 100
            order_id = data['data']['metadata'].get('order_id')
            
            # Atualizar status no banco de dados
            update_payment_status(charge_id, 'paid')
            
            # Aqui você pode adicionar lógica adicional, como notificar o usuário
            print(f"Pagamento confirmado: Charge {charge_id}, Pedido {order_id}, Valor: {amount}")
            
        return jsonify({'status': 'success'}), 200

    @app.route('/health', methods=['GET'])
    def health_check():
        """Endpoint para verificar se a aplicação está rodando"""
        return jsonify({'status': 'ok'}), 200
