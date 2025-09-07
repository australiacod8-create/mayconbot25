import requests
import json
from src.config import Config

class MercadoPagoAPI:
    def __init__(self):
        self.access_token = Config.MERCADOPAGO_ACCESS_TOKEN
        self.base_url = "https://api.mercadopago.com"
    
    def create_pix_payment(self, amount, description, order_id):
        """Cria um pagamento PIX"""
        payload = {
            "transaction_amount": amount,
            "description": description,
            "payment_method_id": "pix",
            "external_reference": order_id,
            "notification_url": f"{Config.BASE_URL}/webhook/mercadopago",
            "payer": {
                "email": "customer@email.com"
            }
        }
        
        try:
            response = requests.post(
                f"{self.base_url}/v1/payments",
                headers={
                    "Authorization": f"Bearer {self.access_token}",
                    "Content-Type": "application/json"
                },
                json=payload
            )
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"Erro ao criar pagamento PIX: {e}")
            return None
    
    def get_payment(self, payment_id):
        """Obtém informações de um pagamento"""
        try:
            response = requests.get(
                f"{self.base_url}/v1/payments/{payment_id}",
                headers={
                    "Authorization": f"Bearer {self.access_token}"
                }
            )
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"Erro ao obter pagamento: {e}")
            return None