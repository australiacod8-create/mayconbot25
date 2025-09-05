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
        [['text' => '💰 Ganhar', 'callback_data' => 'earn'], ['text' => '💳 Saldo', 'callback_data' => 'balance']],
        [['text' => '🏆 Ranking', 'callback_data' => 'leaderboard'], ['text' => '👥 Indicações', 'callback_data' => 'referrals']],
        [['text' => '🏧 Sacar', 'callback_data' => 'withdraw'], ['text' => '❓ Ajuda', 'callback_data' => 'help']]
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
            
            $msg = "Bem-vindo ao Bot de Ganhos!\nGanhe pontos, convide amigos e retire seus ganhos!\nSeu código de indicação: <b>{$users[$chat_id]['ref_code']}</b>";
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
                $time_diff = time() - $users[$chat_id]['last_earn'];
                if ($time_diff < 60) {
                    $remaining = 60 - $time_diff;
                    $msg = "⏳ Aguarde $remaining segundos para ganhar novamente!";
                } else {
                    $earn = 10;
                    $users[$chat_id]['balance'] += $earn;
                    $users[$chat_id]['last_earn'] = time();
                    $msg = "✅ Você ganhou $earn pontos!\nNovo saldo: {$users[$chat_id]['balance']}";
                }
                break;
                
            case 'balance':
                $msg = "💳 Seu Saldo\nPontos: {$users[$chat_id]['balance']}\nIndicações: {$users[$chat_id]['referrals']}";
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
                break;
                
            case 'referrals':
                $msg = "👥 Sistema de Indicação\nSeu código: <b>{$users[$chat_id]['ref_code']}</b>\nIndicações: {$users[$chat_id]['referrals']}\nLink de convite: t.me/" . BOT_TOKEN . "?start={$users[$chat_id]['ref_code']}\n50 pontos por indicação!";
                break;
                
            case 'withdraw':
                $min = 100;
                if ($users[$chat_id]['balance'] < $min) {
                    $msg = "🏧 Saque\nMínimo: $min pontos\nSeu saldo: {$users[$chat_id]['balance']}\nFaltam " . ($min - $users[$chat_id]['balance']) . " pontos!";
                } else {
                    $amount = $users[$chat_id]['balance'];
                    $users[$chat_id]['balance'] = 0;
                    $msg = "🏧 Saque de $amount pontos solicitado!\nNossa equipe processará em breve.";
                }
                break;
                
            case 'help':
                $msg = "❓ Ajuda\n💰 Ganhar: Receba 10 pontos/min\n👥 Indicar: 50 pontos/indicação\n🏧 Sacar: Mín 100 pontos\nUse os botões abaixo para navegar!";
                break;
        }
        
        sendMessage($chat_id, $msg, getMainKeyboard());
    }
    
    saveUsers($users);
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