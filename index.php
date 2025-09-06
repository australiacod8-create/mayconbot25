<?php
require_once 'vendor/autoload.php';

// Bot configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'Coloque_Seu_Token_Aqui');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', 'users.json');
define('PAYMENTS_FILE', 'payments.json');
define('ERROR_LOG', 'error.log');
define('MP_ACCESS_TOKEN', 'APP_USR-2428698313209175-090521-40a9e1a32810a518747fd05a7e85ac21-2669778397');

// Initialize Mercado Pago SDK
MercadoPago\SDK::setAccessToken(MP_ACCESS_TOKEN);

// Initialize bot (set webhook)
function initializeBot() {
    try {
        $webhook_url = getenv('RENDER_EXTERNAL_URL') . '/webhook';
        file_get_contents(API_URL . 'setWebhook?url=' . urlencode($webhook_url));
        return true;
    } catch (Exception $e) {
        logError("Falha na inicialização: " . $e->getMessage());
        return false;
    }
}

// Error logging
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(ERROR_LOG, "[$timestamp] $message\n", FILE_APPEND);
}

// Data management
function loadUsers() {
    try {
        if (!file_exists(USERS_FILE)) {
            file_put_contents(USERS_FILE, json_encode([]));
        }
        return json_decode(file_get_contents(USERS_FILE), true) ?: [];
    } catch (Exception $e) {
        logError("Falha ao carregar usuários: " . $e->getMessage());
        return [];
    }
}

function saveUsers($users) {
    try {
        file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
        return true;
    } catch (Exception $e) {
        logError("Falha ao salvar usuários: " . $e->getMessage());
        return false;
    }
}

function loadPayments() {
    try {
        if (!file_exists(PAYMENTS_FILE)) {
            file_put_contents(PAYMENTS_FILE, json_encode([]));
        }
        return json_decode(file_get_contents(PAYMENTS_FILE), true) ?: [];
    } catch (Exception $e) {
        logError("Falha ao carregar pagamentos: " . $e->getMessage());
        return [];
    }
}

function savePayments($payments) {
    try {
        file_put_contents(PAYMENTS_FILE, json_encode($payments, JSON_PRETTY_PRINT));
        return true;
    } catch (Exception $e) {
        logError("Falha ao salvar pagamentos: " . $e->getMessage());
        return false;
    }
}

// Message sending with inline keyboard
function sendMessage($chat_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $keyboard
            ]);
        }
        
        $url = API_URL . 'sendMessage?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Falha ao enviar mensagem: " . $e->getMessage());
        return false;
    }
}

// Send photo
function sendPhoto($chat_id, $photo_url, $caption = '', $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'photo' => $photo_url,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $keyboard
            ]);
        }
        
        $url = API_URL . 'sendPhoto?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Falha ao enviar foto: " . $e->getMessage());
        return false;
    }
}

// Main keyboard
function getMainKeyboard() {
    return [
        [['text' => '💰 Adicionar saldo', 'callback_data' => 'earn'], ['text' => '💳 Perfil', 'callback_data' => 'balance']],
        [['text' => '🏆 Ranking', 'callback_data' => 'leaderboard'], ['text' => '👥 Indicações', 'callback_data' => 'referrals']],
        [['text' => '🏧 Comprar', 'callback_data' => 'withdraw'], ['text' => '❓ Ajuda', 'callback_data' => 'help']]
    ];
}

// PIX keyboard
function getPixKeyboard() {
    return [
        [['text' => 'R$ 10,00', 'callback_data' => 'pix_10']],
        [['text' => 'R$ 20,00', 'callback_data' => 'pix_20']],
        [['text' => 'R$ 50,00', 'callback_data' => 'pix_50']],
        [['text' => 'R$ 100,00', 'callback_data' => 'pix_100']],
        [['text' => '⬅️ Voltar', 'callback_data' => 'back']]
    ];
}

// Create PIX payment with Mercado Pago
function createPixPayment($amount, $chat_id) {
    try {
        $payment = new MercadoPago\Payment();
        $payment->transaction_amount = $amount;
        $payment->description = "Recarga de saldo - Bot Telegram";
        $payment->payment_method_id = "pix";
        $payment->payer = [
            "email" => "user$chat_id@telegram.com",
            "first_name" => "Usuário Telegram",
            "last_name" => "ID: $chat_id"
        ];
        
        $payment->save();
        
        if ($payment->id && $payment->point_of_interaction->transaction_data->qr_code) {
            $payments = loadPayments();
            $payments[$payment->id] = [
                'chat_id' => $chat_id,
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => time(),
                'qr_code' => $payment->point_of_interaction->transaction_data->qr_code,
                'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64,
                'ticket_url' => $payment->point_of_interaction->transaction_data->ticket_url
            ];
            savePayments($payments);
            
            return [
                'success' => true,
                'payment_id' => $payment->id,
                'qr_code' => $payment->point_of_interaction->transaction_data->qr_code,
                'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64,
                'ticket_url' => $payment->point_of_interaction->transaction_data->ticket_url
            ];
        }
        
        return ['success' => false, 'error' => 'Falha ao criar pagamento'];
    } catch (Exception $e) {
        logError("Erro ao criar pagamento PIX: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Check payment status
function checkPaymentStatus($payment_id) {
    try {
        $payment = MercadoPago\Payment::find_by_id($payment_id);
        return $payment->status;
    } catch (Exception $e) {
        logError("Erro ao verificar status do pagamento: " . $e->getMessage());
        return 'error';
    }
}

// Process commands and callbacks
function processUpdate($update) {
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        $text = trim($update['message']['text'] ?? '');
        
        // Create new user if doesn't exist
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0.00,
                'last_earn' => 0,
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'referred_by' => null
            ];
        }
        
        if (strpos($text, '/start') === 0) {
            $ref = explode(' ', $text)[1] ?? null;
            if ($ref && !$users[$chat_id]['referred_by']) {
                foreach ($users as $id => $user) {
                    if ($user['ref_code'] === $ref && $id != $chat_id) {
                        $users[$chat_id]['referred_by'] = $id;
                        $users[$id]['referrals']++;
                        $users[$id]['balance'] += 50.00;
                        sendMessage($id, "🎉 Nova indicação! Bônus de R$ 50,00!");
                        break;
                    }
                }
            }
            
            $msg = "Bem-vindo ao Bot de Ganhos!\nGanhe dinheiro, convide amigos e compre itens!\nSeu código de indicação: <b>{$users[$chat_id]['ref_code']}</b>";
            sendMessage($chat_id, $msg, getMainKeyboard());
        }
        
    } elseif (isset($update['callback_query'])) {
        $chat_id = $update['callback_query']['message']['chat']['id'];
        $data = $update['callback_query']['data'];
        
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0.00,
                'last_earn' => 0,
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'referred_by' => null
            ];
        }
        
        if (strpos($data, 'confirm_payment_') === 0) {
            $payment_id = str_replace('confirm_payment_', '', $data);
            $payments = loadPayments();
            
            if (isset($payments[$payment_id]) && $payments[$payment_id]['chat_id'] == $chat_id) {
                $status = checkPaymentStatus($payment_id);
                
                if ($status === 'approved') {
                    $amount = $payments[$payment_id]['amount'];
                    $users[$chat_id]['balance'] += $amount;
                    $payments[$payment_id]['status'] = 'approved';
                    
                    $msg = "✅ <b>Pagamento confirmado!</b>\n\nSaldo de R$ " . number_format($amount, 2, ',', '.') . " adicionado com sucesso!\nNovo saldo: R$ " . number_format($users[$chat_id]['balance'], 2, ',', '.');
                    sendMessage($chat_id, $msg, getMainKeyboard());
                } else {
                    $msg = "⏳ <b>Pagamento ainda não identificado</b>\n\nAguarde alguns minutos e tente novamente. Se já efetuou o pagamento, aguarde a confirmação do Mercado Pago.";
                    sendMessage($chat_id, $msg, getMainKeyboard());
                }
                
                savePayments($payments);
            }
        }
        elseif (strpos($data, 'pix_') === 0) {
            $amount = str_replace('pix_', '', $data);
            $payment_result = createPixPayment((float)$amount, $chat_id);
            
            if ($payment_result['success']) {
                $msg = "💳 <b>Pagamento via PIX - R$ " . number_format($amount, 2, ',', '.') . "</b>\n\n";
                $msg .= "⚠️ <b>Pagamento válido por 30 minutos</b>\n\n";
                $msg .= "📋 <b>Instruções:</b>\n";
                $msg .= "1. Abra seu app bancário\n";
                $msg .= "2. Escaneie o QR Code ou use o código PIX\n";
                $msg .= "3. Valor: <b>R$ " . number_format($amount, 2, ',', '.') . "</b>\n";
                $msg .= "4. Efetue o pagamento\n\n";
                $msg .= "💡 Após pagar, clique em '✅ Verificar Pagamento'";
                
                $keyboard = [
                    [['text' => '✅ Verificar Pagamento', 'callback_data' => 'confirm_payment_' . $payment_result['payment_id']]],
                    [['text' => '⬅️ Voltar', 'callback_data' => 'back']]
                ];
                
                // Send QR code image
                if (!empty($payment_result['ticket_url'])) {
                    sendPhoto($chat_id, $payment_result['ticket_url'], $msg, $keyboard);
                } else {
                    sendMessage($chat_id, $msg, $keyboard);
                    sendMessage($chat_id, "Código PIX: <code>" . $payment_result['qr_code'] . "</code>");
                }
            } else {
                $msg = "❌ <b>Erro ao processar pagamento</b>\n\nTente novamente mais tarde ou entre em contato com o suporte.";
                sendMessage($chat_id, $msg, getMainKeyboard());
            }
        }
        else {
            switch ($data) {
                case 'earn':
                    $msg = "💳 <b>Adicionar Saldo via PIX</b>\n\nEscolha o valor que deseja adicionar:";
                    sendMessage($chat_id, $msg, getPixKeyboard());
                    break;
                    
                case 'back':
                    $msg = "Voltando ao menu principal...";
                    sendMessage($chat_id, $msg, getMainKeyboard());
                    break;
                    
                case 'balance':
                    $msg = "💳 Seu Perfil\nSaldo: R$ " . number_format($users[$chat_id]['balance'], 2, ',', '.') . "\nIndicações: {$users[$chat_id]['referrals']}";
                    sendMessage($chat_id, $msg, getMainKeyboard());
                    break;
                    
                case 'leaderboard':
                    $sorted = [];
                    foreach ($users as $id => $user) {
                        $sorted[$id] = $user['balance'];
                    }
                    arsort($sorted);
                    $top = array_slice($sorted, 0, 5, true);
                    $msg = "🏆 Top Ganhadores\n";
                    $i = 1;
                    foreach ($top as $id => $bal) {
                        $msg .= "$i. Usuário $id: R$ " . number_format($bal, 2, ',', '.') . "\n";
                        $i++;
                    }
                    sendMessage($chat_id, $msg, getMainKeyboard());
                    break;
                    
                case 'referrals':
                    $msg = "👥 Sistema de Indicação\nSeu código: <b>{$users[$chat_id]['ref_code']}</b>\nIndicações: {$users[$chat_id]['referrals']}\nLink de convite: t.me/" . BOT_TOKEN . "?start={$users[$chat_id]['ref_code']}\nR$ 50,00 por indicação!";
                    sendMessage($chat_id, $msg, getMainKeyboard());
                    break;
                    
                case 'withdraw':
                    $min = 100.00;
                    if ($users[$chat_id]['balance'] < $min) {
                        $msg = "🏧 Comprar\nMínimo: R$ " . number_format($min, 2, ',', '.') . "\nSeu saldo: R$ " . number_format($users[$chat_id]['balance'], 2, ',', '.') . "\nFaltam R$ " . number_format(($min - $users[$chat_id]['balance']), 2, ',', '.') . "!";
                    } else {
                        $amount = $users[$chat_id]['balance'];
                        $users[$chat_id]['balance'] = 0.00;
                        $msg = "🏧 Compra de R$ " . number_format($amount, 2, ',', '.') . " realizada!\nSeus itens serão entregues em breve.";
                    }
                    sendMessage($chat_id, $msg, getMainKeyboard());
                    break;
                    
                case 'help':
                    $msg = "❓ Ajuda\n💰 Adicionar saldo: Recarregue via PIX\n👥 Indicar: R$ 50,00 por indicação\n🏧 Comprar: Mín R$ 100,00\nUse os botões abaixo para navegar!";
                    sendMessage($chat_id, $msg, getMainKeyboard());
                    break;
            }
        }
        
        saveUsers($users);
    }
}

// Webhook handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = file_get_contents("php://input");
    $update = json_decode($content, true);
    
    if ($update) {
        processUpdate($update);
        http_response_code(200);
        echo "OK";
    } else {
        http_response_code(400);
        echo "Requisição inválida";
    }
} else {
    // Set webhook on first visit
    if (initializeBot()) {
        echo "Webhook configurado com sucesso!";
    } else {
        echo "Falha ao configurar webhook. Verifique error.log";
    }
}
?>