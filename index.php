<?php
// Bot configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'Coloque_Seu_Token_Aqui');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', 'users.json');
define('ERROR_LOG', 'error.log');

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

// Message sending with inline keyboard
function sendMessage($chat_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
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

// Copy PIX keyboard - VERSÃO SIMPLIFICADA E FUNCIONAL
function getCopyPixKeyboard($amount) {
    return [
        [['text' => '📋 Copiar Chave PIX', 'callback_data' => 'show_pix_key']],
        [['text' => '✅ Pagamento Confirmado', 'callback_data' => 'confirm_payment_' . $amount]],
        [['text' => '⬅️ Voltar', 'callback_data' => 'back']]
    ];
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
                        $users[$id]['balance'] += 10.00;
                        sendMessage($id, "🎉 Nova indicação! Bônus de R$ 10,00 adicionado!");
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
        
        switch ($data) {
            case 'earn':
                $msg = "💳 <b>Adicionar Saldo via PIX</b>\n\nEscolha o valor que deseja adicionar:";
                sendMessage($chat_id, $msg, getPixKeyboard());
                break;
                
            case 'pix_10':
                processPixPayment($chat_id, 10.00, $users);
                break;
                
            case 'pix_20':
                processPixPayment($chat_id, 20.00, $users);
                break;
                
            case 'pix_50':
                processPixPayment($chat_id, 50.00, $users);
                break;
                
            case 'pix_100':
                processPixPayment($chat_id, 100.00, $users);
                break;
                
            case 'show_pix_key':
                $pix_key = "65992779486";
                $msg = "📋 <b>CHAVE PIX PARA COPIAR:</b>\n\n";
                $msg .= "👉 <code>$pix_key</code> 👈\n\n";
                $msg .= "📋 <b>Como copiar:</b>\n";
                $msg .= "1. Toque e segure no código acima\n";
                $msg .= "2. Selecione 'Copiar' no menu\n";
                $msg .= "3. A chave será copiada automaticamente\n";
                $msg .= "4. Abra seu app bancário e cole\n\n";
                $msg .= "💰 <b>Após pagar, clique em '✅ Pagamento Confirmado'</b>";
                
                answerCallbackQuery($update['callback_query']['id'], "Toque e segure no código para copiar!");
                sendMessage($chat_id, $msg);
                break;
                
            case strpos($data, 'confirm_payment_') === 0:
                $amount = floatval(str_replace('confirm_payment_', '', $data));
                $users[$chat_id]['balance'] += $amount;
                
                $amount_formatted = number_format($amount, 2, ',', '.');
                $new_balance_formatted = number_format($users[$chat_id]['balance'], 2, ',', '.');
                
                $msg = "✅ <b>Pagamento confirmado!</b>\n\n";
                $msg .= "💰 Valor: R$ {$amount_formatted}\n";
                $msg .= "💳 Saldo adicionado com sucesso!\n";
                $msg .= "📊 Seu novo saldo: R$ {$new_balance_formatted}";
                
                answerCallbackQuery($update['callback_query']['id'], "Pagamento confirmado! Saldo adicionado.");
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'back':
                $msg = "Voltando ao menu principal...";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'balance':
                $balance_formatted = number_format($users[$chat_id]['balance'], 2, ',', '.');
                $msg = "💳 Seu Perfil\nSaldo: R$ {$balance_formatted}\nIndicações: {$users[$chat_id]['referrals']}";
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
                foreach ($top as $id => $balance) {
                    $balance_formatted = number_format($balance, 2, ',', '.');
                    $msg .= "$i. Usuário $id: R$ {$balance_formatted}\n";
                    $i++;
                }
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'referrals':
                $msg = "👥 Sistema de Indicação\nSeu código: <b>{$users[$chat_id]['ref_code']}</b>\nIndicações: {$users[$chat_id]['referrals']}\nLink de convite: t.me/" . BOT_TOKEN . "?start={$users[$chat_id]['ref_code']}\nR$ 10,00 por indicação!";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'withdraw':
                $min = 10.00;
                if ($users[$chat_id]['balance'] < $min) {
                    $balance_formatted = number_format($users[$chat_id]['balance'], 2, ',', '.');
                    $missing = number_format($min - $users[$chat_id]['balance'], 2, ',', '.');
                    $msg = "🏧 Comprar\nMínimo: R$ " . number_format($min, 2, ',', '.') . "\nSeu saldo: R$ {$balance_formatted}\nFaltam R$ {$missing}!";
                } else {
                    $amount = $users[$chat_id]['balance'];
                    $users[$chat_id]['balance'] = 0.00;
                    $amount_formatted = number_format($amount, 2, ',', '.');
                    $msg = "🏧 Compra de R$ {$amount_formatted} realizada!\nSeus itens serão entregues em breve.";
                }
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'help':
                $msg = "❓ Ajuda\n💰 Adicionar saldo: Recarregue via PIX\n👥 Indicar: R$ 10,00/indicação\n🏧 Comprar: Mín R$ 10,00\nUse os botões abaixo para navegar!";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
        }
        
        saveUsers($users);
    }
}

// Answer callback query
function answerCallbackQuery($callback_query_id, $text) {
    try {
        $params = [
            'callback_query_id' => $callback_query_id,
            'text' => $text,
            'show_alert' => false
        ];
        
        $url = API_URL . 'answerCallbackQuery?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Falha ao responder callback: " . $e->getMessage());
        return false;
    }
}

// Process PIX payment
function processPixPayment($chat_id, $amount, &$users) {
    $pix_key = "65992779486";
    $amount_formatted = number_format($amount, 2, ',', '.');
    
    $msg = "💳 <b>PIX Automático - R$ {$amount_formatted}</b>\n\n";
    $msg .= "💰 Valor: <b>R$ {$amount_formatted}</b>\n";
    $msg .= "💳 Saldo a receber: <b>R$ {$amount_formatted}</b>\n\n";
    $msg .= "📋 <b>Instruções:</b>\n";
    $msg .= "1. Clique em '📋 Copiar Chave PIX'\n";
    $msg .= "2. Toque e segure no código para copiar\n";
    $msg .= "3. Abra seu app bancário e cole\n";
    $msg .= "4. Realize o pagamento\n";
    $msg .= "5. Volte e confirme o pagamento\n\n";
    $msg .= "Seu saldo será adicionado automaticamente!";
    
    sendMessage($chat_id, $msg, getCopyPixKeyboard($amount));
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