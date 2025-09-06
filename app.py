from flask import Flask
from src.webhook import setup_webhook_routes

app = Flask(__name__)

# Configurar rotas de webhook
setup_webhook_routes(app)

if __name__ == "__main__":
    app.run(debug=True)
