import sqlite3
import os
from src.config import Config

def init_db():
    """Inicializa o banco de dados"""
    conn = sqlite3.connect('payments.db')
    c = conn.cursor()
    c.execute('''CREATE TABLE IF NOT EXISTS payments
                 (user_id INTEGER, order_id TEXT, payment_id TEXT,
                  amount REAL, status TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)''')
    conn.commit()
    conn.close()

def save_payment_info(user_id, order_id, payment_id, amount, status='pending'):
    """Salva informações de pagamento no banco de dados"""
    conn = sqlite3.connect('payments.db')
    c = conn.cursor()
    c.execute("INSERT INTO payments (user_id, order_id, payment_id, amount, status) VALUES (?, ?, ?, ?, ?)",
              (user_id, order_id, payment_id, amount, status))
    conn.commit()
    conn.close()

def get_last_payment(user_id):
    """Obtém o último pagamento de um usuário"""
    conn = sqlite3.connect('payments.db')
    c = conn.cursor()
    c.execute("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 1", (user_id,))
    result = c.fetchone()
    conn.close()
   
    if result:
        return {
            'user_id': result[0],
            'order_id': result[1],
            'payment_id': result[2],
            'amount': result[3],
            'status': result[4]
        }
    return None

def update_payment_status(payment_id, status):
    """Atualiza o status de um pagamento"""
    conn = sqlite3.connect('payments.db')
    c = conn.cursor()
    c.execute("UPDATE payments SET status = ? WHERE payment_id = ?", (status, payment_id))
    conn.commit()
    conn.close()
