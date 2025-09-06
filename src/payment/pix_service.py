import qrcode
import base64
from io import BytesIO
from src.payment.pagarme_api import PagarmeAPI

class PIXService:
    def __init__(self):
        self.pagarme = PagarmeAPI()
    
    def generate_pix_payment(self, amount, customer, order_id):
        """Gera um pagamento PIX e retorna os dados"""
        charge_data = self.pagarme.create_pix_charge(amount, customer, order_id)
        
        if not charge_data:
            return None
        
        # Extrair informações do PIX
        pix_data = charge_data.get('charges', [{}])[0]
        qr_code = pix_data.get('last_transaction', {}).get('qr_code_url')
        qr_code_text = pix_data.get('last_transaction', {}).get('qr_code')
        expiration = pix_data.get('last_transaction', {}).get('expires_at')
        charge_id = pix_data.get('id')
        
        # Gerar QR Code como imagem
        qr_image = self.generate_qr_code_image(qr_code_text)
        
        return {
            'qr_code_url': qr_code,
            'qr_code_text': qr_code_text,
            'qr_code_image': qr_image,
            'expiration': expiration,
            'charge_id': charge_id,
            'amount': amount
        }
    
    def generate_qr_code_image(self, qr_code_text):
        """Gera uma imagem do QR Code a partir do texto"""
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
    
    def check_payment_status(self, charge_id):
        """Verifica o status de um pagamento"""
        charge_data = self.pagarme.get_charge(charge_id)
        
        if not charge_data:
            return None
        
        status = charge_data.get('status')
        paid_amount = charge_data.get('paid_amount', 0) / 100  # Converter de centavos
        
        return {
            'status': status,
            'paid_amount': paid_amount,
            'paid_at': charge_data.get('paid_at')
        }
