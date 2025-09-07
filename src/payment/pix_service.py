import qrcode
import base64
from io import BytesIO
from src.payment.mercadopago_api import MercadoPagoAPI  # Alteração aqui

class PIXService:
    def __init__(self):
        self.mercadopago = MercadoPagoAPI()  # Alteração aqui
    
    def generate_pix_payment(self, amount, customer, order_id):
        """Gera um pagamento PIX e retorna os dados"""
        # Alteração para usar Mercado Pago
        payment_data = self.mercadopago.create_pix_payment(
            amount, 
            f"Pagamento para o pedido #{order_id}", 
            order_id
        )
        
        if not payment_data:
            return None
        
        # Extrair informações do PIX (estrutura do Mercado Pago)
        point_of_interaction = payment_data.get('point_of_interaction', {})
        transaction_data = point_of_interaction.get('transaction_data', {})
        
        qr_code = transaction_data.get('qr_code')
        qr_code_base64 = transaction_data.get('qr_code_base64')
        ticket_url = transaction_data.get('ticket_url')
        expiration = payment_data.get('date_of_expiration')
        payment_id = payment_data.get('id')
        
        # Gerar QR Code como imagem (se não vier em base64 da API)
        qr_image = self.generate_qr_code_image(qr_code)
        
        return {
            'qr_code': qr_code,
            'qr_code_image': qr_code_base64 or qr_image,
            'ticket_url': ticket_url,
            'expiration': expiration,
            'payment_id': payment_id,
            'amount': amount
        }
    
    # O resto do código permanece igual...
    # ... (generate_qr_code_image e check_payment_status)
