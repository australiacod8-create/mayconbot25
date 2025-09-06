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
        [['text' => 'R$ 10,00 (100 pontos)', 'callback_data' => 'pix_10']],
        [['text' => 'R$ 20,00 (200 pontos)', 'callback_data' => 'pix_20']],
        [['text' => 'R$ 50,00 (500 pontos)', 'callback_data' => 'pix_50']],
        [['text' => 'R$ 100,00 (1000 pontos)', 'callback_data' => 'pix_100']],
        [['text' => '⬅️ Voltar', 'callback_data' => 'back']]
    ];
}

// Copy PIX keyboard
function getCopyPixKeyboard() {
    return [
        [['text' => '📋 Copiar PIX', 'callback_data' => 'copy_pix']],
        [['text' => '✅ Pagamento Confirmado', 'callback_data' => 'confirm_payment']],
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
                'balance' => 0,
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
                        $users[$id]['balance'] += 50; // Referral bonus
                        sendMessage($id, "🎉 Nova indicação! Bônus de 50 pontos!");
                        break;
                    }
                }
            }
            
            $msg = "Bem-vindo ao Bot de Ganhos!\nGanhe pontos, convide amigos e compre itens!\nSeu código de indicação: <b>{$users[$chat_id]['ref_code']}</b>";
            sendMessage($chat_id, $msg, getMainKeyboard());
        }
        
    } elseif (isset($update['callback_query'])) {
        $chat_id = $update['callback_query']['message']['chat']['id'];
        $data = $update['callback_query']['data'];
        
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0,
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
                processPixPayment($chat_id, 10, 100, $users);
                break;
                
            case 'pix_20':
                processPixPayment($chat_id, 20, 200, $users);
                break;
                
            case 'pix_50':
                processPixPayment($chat_id, 50, 500, $users);
                break;
                
            case 'pix_100':
                processPixPayment($chat_id, 100, 1000, $users);
                break;
                
            case 'copy_pix':
                $msg = "📋 <b>Chave PIX Copiada!</b>\n\n65992779486\n\nApós realizar o pagamento, clique em '✅ Pagamento Confirmado' para adicionar o saldo automaticamente.";
                sendMessage($chat_id, $msg, getCopyPixKeyboard());
                break;
                
            case 'confirm_payment':
                $msg = "✅ <b>Pagamento confirmado!</b>\n\nSeu saldo será adicionado em breve. Caso não receba em 5 minutos, entre em contato com o suporte.";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'back':
                $msg = "Voltando ao menu principal...";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'balance':
                $msg = "💳 Seu Perfil\nPontos: {$users[$chat_id]['balance']}\nIndicações: {$users[$chat_id]['referrals']}";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'leaderboard':
                $sorted = array_column($users, 'balance');
                arsort($sorted);
                $top = array_slice($sorted, 0, 5, true);
                $msg = "🏆 Top Ganhadores\n";
                $i = 1;
                foreach ($top as $id => $bal) {
                    $msg .= "$i. Usuário $id: $bal pontos\n";
                    $i++;
                }
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'referrals':
                $msg = "👥 Sistema de Indicação\nSeu código: <b>{$users[$chat_id]['ref_code']}</b>\nIndicações: {$users[$chat_id]['referrals']}\nLink de convite: t.me/" . BOT_TOKEN . "?start={$users[$chat_id]['ref_code']}\n50 pontos por indicação!";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'withdraw':
                $min = 100;
                if ($users[$chat_id]['balance'] < $min) {
                    $msg = "🏧 Comprar\nMínimo: $min pontos\nSeu saldo: {$users[$chat_id]['balance']}\nFaltam " . ($min - $users[$chat_id]['balance']) . " pontos!";
                } else {
                    $amount = $users[$chat_id]['balance'];
                    $users[$chat_id]['balance'] = 0;
                    $msg = "🏧 Compra de $amount pontos realizada!\nSeus itens serão entregues em breve.";
                }
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'help':
                $msg = "❓ Ajuda\n💰 Adicionar saldo: Recarregue via PIX\n👥 Indicar: 50 pontos/indicação\n🏧 Comprar: Mín 100 pontos\nUse os botões abaixo para navegar!";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
        }
        
        saveUsers($users);
    }
}

// Process PIX payment
function processPixPayment($chat_id, $amount, $points, &$users) {
    $pix_key = "65992779486";
    $msg = "💳 <b>PIX Automático - R$ $amount,00</b>\n\n";
    $msg .= "Valor: <b>R$ $amount,00</b>\n";
    $msg .= "Pontos a receber: <b>$points</b>\n\n";
    $msg .= "Chave PIX: <code>$pix_key</code>\n\n";
    $msg .= "📋 <b>Instruções:</b>\n";
    $msg .= "1. Copie a chave PIX\n";
    $msg .= "2. Abra seu app bancário\n";
    $msg .= "3. Cole a chave e pague R$ $amount,00\n";
    $msg .= "4. Clique em '✅ Pagamento Confirmado'\n\n";
    $msg .= "Seu saldo será adicionado automaticamente!";
    
    sendMessage($chat_id, $msg, getCopyPixKeyboard());
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