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
        [['text' => '🎬 Canais TV', 'callback_data' => 'tv_channels']],
        [['text' => '🎥 Canais Filmes', 'callback_data' => 'movie_channels']],
        [['text' => '⚽ Canais Esportes', 'callback_data' => 'sports_channels']],
        [['text' => '💰 Adicionar Saldo', 'callback_data' => 'add_balance'], ['text' => '👤 Meu Perfil', 'callback_data' => 'profile']],
        [['text' => '📞 Suporte', 'url' => 'https://t.me/suporte_latina']]
    ];
}

// TV Channels keyboard
function getTvChannelsKeyboard() {
    return [
        [['text' => '📺 Globo', 'callback_data' => 'channel_globo']],
        [['text' => '📺 Record', 'callback_data' => 'channel_record']],
        [['text' => '📺 SBT', 'callback_data' => 'channel_sbt']],
        [['text' => '📺 Band', 'callback_data' => 'channel_band']],
        [['text' => '⬅️ Voltar', 'callback_data' => 'back']]
    ];
}

// PIX keyboard
function getPixKeyboard() {
    return [
        [['text' => 'R$ 10,00 (7 dias)', 'callback_data' => 'pix_10']],
        [['text' => 'R$ 20,00 (15 dias)', 'callback_data' => 'pix_20']],
        [['text' => 'R$ 30,00 (30 dias)', 'callback_data' => 'pix_30']],
        [['text' => 'R$ 50,00 (60 dias)', 'callback_data' => 'pix_50']],
        [['text' => '⬅️ Voltar', 'callback_data' => 'back']]
    ];
}

// Copy PIX keyboard
function getCopyPixKeyboard($amount, $days) {
    return [
        [['text' => '📋 Copiar Chave PIX', 'callback_data' => 'copy_pix']],
        [['text' => '✅ Já Paguei', 'callback_data' => 'confirm_payment_' . $amount]],
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
                'plan_days' => 0,
                'plan_expiry' => time(),
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
                        $users[$id]['balance'] += 5; // Referral bonus
                        sendMessage($id, "🎉 Nova indicação! +5 dias de bônus!");
                        break;
                    }
                }
            }
            
            $msg = "📺 <b>Bem-vindo ao Latina Streaming!</b>\n\n";
            $msg .= "Acesse os melhores canais de TV, filmes e esportes!\n\n";
            $msg .= "💎 Seu código de indicação: <code>{$users[$chat_id]['ref_code']}</code>\n";
            $msg .= "👥 Indicações: {$users[$chat_id]['referrals']}\n";
            
            if ($users[$chat_id]['plan_expiry'] > time()) {
                $expiry_date = date('d/m/Y', $users[$chat_id]['plan_expiry']);
                $msg .= "⏳ Plano ativo até: $expiry_date\n";
            } else {
                $msg .= "❌ Plano expirado. Adquira um novo plano!\n";
            }
            
            sendMessage($chat_id, $msg, getMainKeyboard());
        }
        
    } elseif (isset($update['callback_query'])) {
        $chat_id = $update['callback_query']['message']['chat']['id'];
        $data = $update['callback_query']['data'];
        
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0,
                'plan_days' => 0,
                'plan_expiry' => time(),
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'referred_by' => null
            ];
        }
        
        switch ($data) {
            case 'tv_channels':
            case 'movie_channels':
            case 'sports_channels':
                if ($users[$chat_id]['plan_expiry'] > time()) {
                    $channel_type = str_replace('_channels', '', $data);
                    $msg = "📡 <b>Selecione um canal " . ucfirst($channel_type) . ":</b>";
                    sendMessage($chat_id, $msg, getTvChannelsKeyboard());
                } else {
                    $msg = "❌ <b>Plano expirado!</b>\n\n";
                    $msg .= "Para acessar os canais, você precisa de um plano ativo.\n";
                    $msg .= "Clique em '💰 Adicionar Saldo' para adquirir um novo plano.";
                    sendMessage($chat_id, $msg, getMainKeyboard());
                }
                break;
                
            case 'add_balance':
                $msg = "💳 <b>Planos Disponíveis</b>\n\n";
                $msg .= "Escolha o plano que deseja adquirir:\n\n";
                $msg .= "🎁 R$ 10,00 - Plano 7 dias\n";
                $msg .= "🎁 R$ 20,00 - Plano 15 dias\n";
                $msg .= "🎁 R$ 30,00 - Plano 30 dias\n";
                $msg .= "🎁 R$ 50,00 - Plano 60 dias\n\n";
                $msg .= "💎 Use o código de indicação para ganhar dias extras!";
                sendMessage($chat_id, $msg, getPixKeyboard());
                break;
                
            case 'pix_10':
                processPixPayment($chat_id, 10, 7, $users);
                break;
                
            case 'pix_20':
                processPixPayment($chat_id, 20, 15, $users);
                break;
                
            case 'pix_30':
                processPixPayment($chat_id, 30, 30, $users);
                break;
                
            case 'pix_50':
                processPixPayment($chat_id, 50, 60, $users);
                break;
                
            case 'copy_pix':
                $msg = "📋 <b>Chave PIX Copiada!</b>\n\n";
                $msg .= "<code>65992779486</code>\n\n";
                $msg .= "📝 <b>Instruções:</b>\n";
                $msg .= "1. Copie a chave PIX acima\n";
                $msg .= "2. Abra seu app bancário\n";
                $msg .= "3. Cole a chave no PIX\n";
                $msg .= "4. Efetue o pagamento\n";
                $msg .= "5. Clique em '✅ Já Paguei'\n\n";
                $msg .= "⏳ Seu plano será ativado em até 5 minutos!";
                sendMessage($chat_id, $msg);
                break;
                
            case strpos($data, 'confirm_payment_') === 0:
                $amount = str_replace('confirm_payment_', '', $data);
                $days = 0;
                
                switch ($amount) {
                    case 10: $days = 7; break;
                    case 20: $days = 15; break;
                    case 30: $days = 30; break;
                    case 50: $days = 60; break;
                }
                
                // Atualizar plano do usuário
                $current_time = time();
                if ($users[$chat_id]['plan_expiry'] > $current_time) {
                    $users[$chat_id]['plan_expiry'] += $days * 24 * 60 * 60;
                } else {
                    $users[$chat_id]['plan_expiry'] = $current_time + ($days * 24 * 60 * 60);
                }
                
                $expiry_date = date('d/m/Y', $users[$chat_id]['plan_expiry']);
                $msg = "✅ <b>Pagamento confirmado!</b>\n\n";
                $msg .= "Plano de $days dias ativado com sucesso!\n";
                $msg .= "⏳ Expira em: $expiry_date\n\n";
                $msg .= "📺 Agora você pode acessar todos os canais!";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'profile':
                $msg = "👤 <b>Meu Perfil</b>\n\n";
                $msg .= "💎 Código: <code>{$users[$chat_id]['ref_code']}</code>\n";
                $msg .= "👥 Indicações: {$users[$chat_id]['referrals']}\n";
                
                if ($users[$chat_id]['plan_expiry'] > time()) {
                    $expiry_date = date('d/m/Y', $users[$chat_id]['plan_expiry']);
                    $msg .= "✅ Status: Plano Ativo\n";
                    $msg .= "⏳ Expira em: $expiry_date\n";
                } else {
                    $msg .= "❌ Status: Plano Expirado\n";
                    $msg .= "💳 Adquira um novo plano!\n";
                }
                
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'back':
                $msg = "Voltando ao menu principal...";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case strpos($data, 'channel_') === 0:
                if ($users[$chat_id]['plan_expiry'] > time()) {
                    $channel = str_replace('channel_', '', $data);
                    $msg = "📡 <b>Canal " . ucfirst($channel) . "</b>\n\n";
                    $msg .= "🔗 Link: https://stream.latinatv.com/" . $channel . "\n";
                    $msg .= "📺 Acesse pelo seu aplicativo preferido!\n\n";
                    $msg .= "💡 Dica: Salve o link para acessar rapidamente!";
                    sendMessage($chat_id, $msg);
                } else {
                    $msg = "❌ <b>Plano expirado!</b>\n\n";
                    $msg .= "Para acessar os canais, você precisa de um plano ativo.\n";
                    $msg .= "Clique em '💰 Adicionar Saldo' para adquirir um novo plano.";
                    sendMessage($chat_id, $msg, getMainKeyboard());
                }
                break;
        }
        
        saveUsers($users);
    }
}

// Process PIX payment
function processPixPayment($chat_id, $amount, $days, &$users) {
    $pix_key = "65992779486";
    $msg = "💳 <b>Pagamento via PIX - R$ $amount,00</b>\n\n";
    $msg .= "Plano: <b>$days dias</b>\n";
    $msg .= "Valor: <b>R$ $amount,00</b>\n\n";
    $msg .= "Chave PIX: <code>$pix_key</code>\n\n";
    $msg .= "📝 <b>Instruções:</b>\n";
    $msg .= "1. Copie a chave PIX\n";
    $msg .= "2. Abra seu app bancário\n";
    $msg .= "3. Cole a chave no PIX\n";
    $msg .= "4. Efetue o pagamento de R$ $amount,00\n";
    $msg .= "5. Clique em '✅ Já Paguei'\n\n";
    $msg .= "⏳ Seu plano será ativado em até 5 minutos!";
    
    sendMessage($chat_id, $msg, getCopyPixKeyboard($amount, $days));
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