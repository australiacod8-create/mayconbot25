import requests
import json
from src.config import Config

class PagarmeAPI:
    def __init__(self):
        self.api_key = Config.PAGARME_API_KEY
        self.base_url = "https://api.pagar.me/core/v5"
        self.headers = {
            "Authorization": f"Basic {self.api_key}",
            "Content-Type": "application/json"
        }
    
    def create_pix_charge(self, amount, customer_info, order_id):
        """Cria uma cobrança PIX"""
        payload = {
            "items": [
                {
                    "amount": int(amount * 100),  # Converter para centavos
                    "description": f"Pagamento para o pedido #{order_id}",
                    "quantity": 1
                }
            ],
            "customer": customer_info,
            "payments": [
                {
                    "payment_method": "pix",
                    "pix": {
                        "expires_in": 3600  # Expira em 1 hora
                    }
                }
            ],
            "metadata": {
                "order_id": order_id
            }
        }
        
        try:
            response = requests.post(
                f"{self.base_url}/orders",
                headers=self.headers,
                json=payload
            )
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"Erro ao criar cobrança PIX: {e}")
            return None
    
    def get_charge(self, charge_id):
        """Obtém informações de uma cobrança"""
        try:
            response = requests.get(
                f"{self.base_url}/charges/{charge_id}",
                headers=self.headers
            )
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"Erro ao obter cobrança: {e}")
            return None
