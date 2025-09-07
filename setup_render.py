#!/usr/bin/env python3
"""
Script de automação para configuração do projeto no Render.
Executa todos os passos necessários para configurar o ambiente.
"""

import os
import sys
import shutil
from pathlib import Path

def check_project_structure():
    """Verifica se a estrutura do projeto está correta"""
    print("Verificando estrutura do projeto...")
    
    required_files = [
        "app.py",
        "requirements.txt",
        "src/bot.py"
    ]
    
    missing_files = []
    for file in required_files:
        if not os.path.exists(file):
            missing_files.append(file)
    
    if missing_files:
        print(f"Arquivos faltantes: {missing_files}")
        return False
    
    print("✓ Estrutura do projeto verificada com sucesso")
    return True

def create_app_file():
    """Cria o arquivo app.py unificado"""
    app_content = '''from flask import Flask, request
import threading
import asyncio
from src.bot import main as bot_main
import signal
import os

app = Flask(__name__)

# Variável global para controlar o bot
bot_thread = None

@app.route('/')
def home():
    return "Servidor e bot estão rodando!"

@app.route('/health')
def health():
    return "OK", 200

def run_bot():
    """Função para executar o bot em uma thread separada"""
    try:
        print("Iniciando bot do Telegram...")
        asyncio.run(bot_main())
    except Exception as e:
        print(f"Erro no bot: {e}")

def start_bot():
    """Inicia o bot em uma thread separada"""
    global bot_thread
    if bot_thread is None or not bot_thread.is_alive():
        bot_thread = threading.Thread(target=run_bot, daemon=True)
        bot_thread.start()
        print("Bot iniciado em thread separada")

# Inicia o bot quando o aplicativo Flask iniciar
@app.before_first_request
def initialize():
    start_bot()

# Manipulador para graceful shutdown
def shutdown_handler(signum, frame):
    print("Recebido sinal de desligamento, encerrando aplicação...")
    exit(0)

if __name__ == '__main__':
    # Registra manipulador de sinais para shutdown graceful
    signal.signal(signal.SIGINT, shutdown_handler)
    signal.signal(signal.SIGTERM, shutdown_handler)
    
    # Inicia o bot
    start_bot()
    
    # Inicia o servidor Flask
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=False)
'''

    with open('app.py', 'w') as f:
        f.write(app_content)
    print("✓ Arquivo app.py criado/atualizado")

def create_procfile():
    """Cria o arquivo Procfile"""
    procfile_content = 'web: python app.py'
    
    with open('Procfile', 'w') as f:
        f.write(procfile_content)
    print("✓ Procfile criado/atualizado")

def update_requirements():
    """Atualiza o arquivo requirements.txt se necessário"""
    requirements_file = 'requirements.txt'
    
    # Lista de dependências necessárias
    required_packages = [
        'flask>=2.3.0',
        'python-telegram-bot==20.0a0',
        'gunicorn',
        'requests',
        'cryptography',
        'qrcode[pil]',
        'python-dotenv'
    ]
    
    # Ler requirements existentes
    existing_packages = set()
    if os.path.exists(requirements_file):
        with open(requirements_file, 'r') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#'):
                    pkg_name = line.split('==')[0].split('>=')[0].split('<=')[0]
                    existing_packages.add(pkg_name.lower())
    
    # Adicionar pacotes faltantes
    with open(requirements_file, 'a') as f:
        for package in required_packages:
            pkg_name = package.split('==')[0].split('>=')[0].split('<=')[0].lower()
            if pkg_name not in existing_packages:
                f.write(f"\n{package}")
                print(f"✓ Adicionado {package} ao requirements.txt")

def create_runtime_file():
    """Cria arquivo runtime.txt com a versão do Python"""
    runtime_content = 'python-3.10.12'
    
    with open('runtime.txt', 'w') as f:
        f.write(runtime_content)
    print("✓ runtime.txt criado")

def create_env_example():
    """Cria arquivo .env.example com variáveis necessárias"""
    env_example_content = '''# Configurações do Telegram Bot
BOT_TOKEN=seu_token_do_bot_aqui

# Configurações do Flask
FLASK_ENV=production
SECRET_KEY=sua_chave_secreta_aqui

# Outras configurações
DATABASE_URL=sqlite:///app.db
'''

    with open('.env.example', 'w') as f:
        f.write(env_example_content)
    print("✓ .env.example criado")

def main():
    """Função principal"""
    print("Iniciando configuração automática para o Render...")
    print("=" * 50)
    
    # Verificar estrutura do projeto
    if not check_project_structure():
        print("Erro: Estrutura do projeto incompleta.")
        sys.exit(1)
    
    # Executar todas as etapas de configuração
    try:
        create_app_file()
        create_procfile()
        update_requirements()
        create_runtime_file()
        create_env_example()
        
        print("=" * 50)
        print("✓ Configuração concluída com sucesso!")
        print("\nPróximos passos:")
        print("1. Configure as variáveis de ambiente no painel do Render")
        print("2. Faça commit das mudanças: git add .")
        print("3. Envie para o repositório: git commit -m 'Configuração Render' && git push")
        print("4. O Render fará deploy automaticamente")
        
    except Exception as e:
        print(f"Erro durante a configuração: {e}")
        sys.exit(1)

if __name__ == '__main__':
    main()
