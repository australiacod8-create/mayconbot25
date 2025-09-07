import qrcode
import base64
from io import BytesIO
from src.payment.mercadopago_api import MercadoPagoAPI

class PIXService:
    def __init__(self):
        self.mercadopago = MercadoPagoAPI()
   
    def generate_pix_payment(self, amount, customer, order_id):
        """Gera um pagamento PIX e retorna os dados"""
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
       
        # Data de expiração (30 minutos a partir de agora)
        from datetime import datetime, timedelta
        expiration = (datetime.now() + timedelta(minutes=30)).isoformat()
       
        payment_id = payment_data.get('id')
       
        # Gerar QR Code como imagem se a API não fornecer em base64
        qr_image = self.generate_qr_code_image(qr_code) if qr_code else None
       
        return {
            'qr_code': qr_code,
            'qr_code_image': qr_code_base64 or qr_image,
            'ticket_url': ticket_url,
            'expiration': expiration,
            'payment_id': payment_id,
            'amount': amount
        }
   
    def generate_qr_code_image(self, qr_code_text):
        """Gera uma imagem do QR Code a partir do texto"""
        if not qr_code_text:
            return None
           
        qr = qrcode.QRCode(
            version=1,
            error_correction=qrcode.constants.ERROR_CORRECT_L,
            box_size=10,
            border=4,
        )
        qr.add_data(qr_code_text)
        qr.make(fit=True)
       
        img = qr.make_image(fill_color="black", back_color="white")
       
        # Converter para base64 para fácil exibição
        buffered = BytesIO()
        img.save(buffered, format="PNG")
        img_str = base64.b64encode(buffered.getvalue()).decode()
       
        return f"data:image/png;base64,{img_str}"
   
    def check_payment_status(self, payment_id):
        """Verifica o status de um pagamento"""
        payment_data = self.mercadopago.get_payment(payment_id)
       
        if not payment_data:
            return None
       
        status = payment_data.get('status')
        paid_amount = payment_data.get('transaction_amount', 0)
       
        return {
            'status': status,
            'paid_amount': paid_amount,
            'paid_at': payment_data.get('date_approved')
        }
