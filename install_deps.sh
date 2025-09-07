<<<<<<< HEAD
#!/bin/bash

# Script para Windows/Git Bash
echo "Instalando dependências..."

# Verificar Python
python --version
if [ $? -ne 0 ]; then
    echo "Python não encontrado"
    exit 1
fi

# Instalar dependências diretamente (sem ambiente virtual)
pip install --upgrade pip
pip install flask==3.1.2
pip install python-telegram-bot==20.0a0
pip install gunicorn==20.1.0
pip install requests==2.26.0
pip install cryptography==3.3.2
pip install qrcode[pil]==7.3.1
pip install python-dotenv==0.19.0

echo "✅ Instalação concluída!"
=======
#!/bin/bash

# Script para instalar dependências do projeto

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Função para imprimir mensagens de erro
echo_error() {
    echo -e "${RED}[ERRO] $1${NC}"
}

# Função para imprimir mensagens de sucesso
echo_success() {
    echo -e "${GREEN}[SUCESSO] $1${NC}"
}

# Função para imprimir informações
echo_info() {
    echo -e "${YELLOW}[INFO] $1${NC}"
}

# Verifica se o Python está instalado
echo_info "Verificando se o Python está instalado..."
if ! command -v python &> /dev/null; then
    echo_error "Python não encontrado. Por favor, instale o Python primeiro."
    exit 1
fi

# Verifica se o pip está instalado
echo_info "Verificando se o pip está instalado..."
if ! command -v pip &> /dev/null; then
    echo_error "pip não encontrado. Por favor, instale o pip."
    exit 1
fi

# Verifica se o arquivo requirements.txt existe
if [ ! -f "requirements.txt" ]; then
    echo_error "Arquivo requirements.txt não encontrado."
    exit 1
fi

# Pergunta se deseja criar um ambiente virtual
read -p "Deseja criar um ambiente virtual? (s/n): " create_venv
if [ "$create_venv" = "s" ] || [ "$create_venv" = "S" ]; then
    echo_info "Criando ambiente virtual..."
    python -m venv venv
    echo_info "Ativando ambiente virtual..."
    source venv/bin/activate
fi

# Instala as dependências
echo_info "Instalando dependências do requirements.txt..."
pip install -r requirements.txt

# Verifica se a instalação foi bem-sucedida
if [ $? -eq 0 ]; then
    echo_success "Dependências instaladas com sucesso!"
else
    echo_error "Falha ao instalar dependências. Verifique o arquivo requirements.txt."
    exit 1
fi

echo_success "Instalação concluída!"
>>>>>>> 9f3e0deeec6e56ce66d70a82e7a40f7db4fe715b
