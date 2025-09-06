<?php
// Bot configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'Coloque_Seu_Token_Aqui');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', 'users.json');
define('ERROR_LOG', 'error.log');
define('PIX_KEY', '65992779486'); // Sua chave PIX

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

// Copy PIX keyboard
function getCopyPixKeyboard($amount) {
    return [
        [['text' => '📋 Copiar Chave PIX', 'callback_data' => 'copy_pix']],
        [['text' => '✅ Pagamento Confirmado', 'callback_data' => 'confirm_payment_' . $amount]],
        [['text' => '⬅️ Voltar', 'callback_data' => 'back']]
    ];
}

// Generate a unique transaction ID
function generateTransactionId() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Process commands and callbacks
function processUpdate($update) {
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        $first_name = $update['message']['chat']['first_name'] ?? '';
        $last_name = $update['message']['chat']['last_name'] ?? '';
        $username = $update['message']['chat']['username'] ?? '';
        $text = trim($update['message']['text'] ?? '');
        
        // Create new user if doesn't exist
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'name' => trim("$first_name $last_name"),
                'username' => $username,
                'balance' => 0.00,
                'last_earn' => 0,
                'referrals' => 0,
                'referral_points' => 0.00,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'referred_by' => null
            ];
        } else {
            // Update user info if already exists
            $users[$chat_id]['name'] = trim("$first_name $last_name");
            $users[$chat_id]['username'] = $username;
        }
        
        if (strpos($text, '/start') === 0) {
            $ref = explode(' ', $text)[1] ?? null;
            if ($ref && !$users[$chat_id]['referred_by']) {
                foreach ($users as $id => $user) {
                    if ($user['ref_code'] === $ref && $id != $chat_id) {
                        $users[$chat_id]['referred_by'] = $id;
                        $users[$id]['referrals']++;
                        $users[$id]['balance'] += 1.00; // Bônus de R$ 1,00 por indicação
                        $users[$id]['referral_points'] += 1.00;
                        sendMessage($id, "🎉 Nova indicação! Bônus de R$ 1,00 adicionado!");
                        break;
                    }
                }
            }
            
            // Mensagem de boas-vindas
            $welcome_msg = "BEM VINDO A MELHOR LOJA DE STREAMING! \n\n";
            $welcome_msg .= "⚠️ Antes de efetuar um pagamento, confira a disponibilidade da conta desejada.\n";
            $welcome_msg .= "🚫 Não realizamos reembolsos. Caso necessário, oferecemos Gift Cards dentro do bot.\n\n";
            $welcome_msg .= "Não tem o login desejado contate o nosso suporte ☺\n";
            $welcome_msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
            
            // Detalhes da conta
            $balance_formatted = number_format($users[$chat_id]['balance'], 2, ',', '.');
            $referral_points_formatted = number_format($users[$chat_id]['referral_points'], 2, ',', '.');
            $username_display = $users[$chat_id]['username'] ? '@' . $users[$chat_id]['username'] : 'Não informado';
            
            $account_msg = "📝 DETALHES DA SUA CONTA\n\n";
            $account_msg .= "👤Nome: " . ($users[$chat_id]['name'] ?: 'Não informado') . "\n";
            $account_msg .= "🔹Usuário: " . $username_display . "\n";
            $account_msg .= "🆔 Identificação: <code>" . $chat_id . "</code>\n";
            $account_msg .= "💵 Saldo disponível: R$" . $balance_formatted . "\n";
            $account_msg .= "🎖️Indicações acumuladas: " . $users[$chat_id]['referrals'] . "\n\n";
            $account_msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
            $account_msg .= "Aproveite ao máximo e boas compras!";
            
            // Enviar mensagem de boas-vindas
            sendMessage($chat_id, $welcome_msg);
            
            // Enviar detalhes da conta com teclado
            sendMessage($chat_id, $account_msg, getMainKeyboard());
        }
        
    } elseif (isset($update['callback_query'])) {
        $chat_id = $update['callback_query']['message']['chat']['id'];
        $data = $update['callback_query']['data'];
        $message_id = $update['callback_query']['message']['message_id'];
        
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'name' => '',
                'username' => '',
                'balance' => 0.00,
                'last_earn' => 0,
                'referrals' => 0,
                'referral_points' => 0.00,
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
                
            case 'copy_pix':
                // Envia mensagem com chave PIX copiável
                $pix_key = PIX_KEY;
                $msg = "📋 <b>Chave PIX Copiada!</b>\n\n<code>$pix_key</code>\n\n✅ Chave PIX copiada para a área de transferência!\n\nApós realizar o pagamento, clique em '✅ Pagamento Confirmado' para adicionar o saldo automaticamente.";
                
                // Responde ao callback para mostrar "copiado" para o usuário
                answerCallbackQuery($update['callback_query']['id'], "Chave PIX copiada! ✅");
                sendMessage($chat_id, $msg);
                break;
                
            case strpos($data, 'confirm_payment_') === 0:
                // Extrai o valor do pagamento do callback_data
                $amount = floatval(str_replace('confirm_payment_', '', $data));
                
                // Adiciona o saldo ao usuário
                $users[$chat_id]['balance'] += $amount;
                
                $amount_formatted = number_format($amount, 2, ',', '.');
                $new_balance_formatted = number_format($users[$chat_id]['balance'], 2, ',', '.');
                
                $msg = "✅ <b>Pagamento confirmado!</b>\n\nValor: R$ {$amount_formatted}\nSaldo adicionado com sucesso!\nSeu novo saldo: R$ {$new_balance_formatted}";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'back':
                $msg = "Voltando ao menu principal...";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
                
            case 'balance':
                $balance_formatted = number_format($users[$chat_id]['balance'], 2, ',', '.');
                $referral_points_formatted = number_format($users[$chat_id]['referral_points'], 2, ',', '.');
                $username_display = $users[$chat_id]['username'] ? '@' . $users[$chat_id]['username'] : 'Não informado';
                
                $msg = "📝 DETALHES DA SUA CONTA\n\n";
                $msg .= "👤Nome: " . ($users[$chat_id]['name'] ?: 'Não informado') . "\n";
                $msg .= "🔹Usuário: " . $username_display . "\n";
                $msg .= "🆔 Identificação: <code>" . $chat_id . "</code>\n";
                $msg .= "💵 Saldo disponível: R$" . $balance_formatted . "\n";
                $msg .= "🎖️Indicações acumuladas: " . $users[$chat_id]['referrals'] . "\n";
                $msg .= "💰 Pontos de indicação: R$" . $referral_points_formatted;
                
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
                $msg = "👥 Sistema de Indicação\nSeu código: <b>{$users[$chat_id]['ref_code']}</b>\nIndicações: {$users[$chat_id]['referrals']}\nLink de convite: t.me/" . BOT_TOKEN . "?start={$users[$chat_id]['ref_code']}\nR$ 1,00 por indicação!";
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
                $msg = "❓ Ajuda\n💰 Adicionar saldo: Recarregue via PIX\n👥 Indicar: R$ 1,00/indicação\n🏧 Comprar: Mín R$ 10,00\nUse os botões abaixo para navegar!";
                sendMessage($chat_id, $msg, getMainKeyboard());
                break;
        }
        
        saveUsers($users);
    }
}

// Answer callback query (para mostrar feedback ao usuário)
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
    $pix_key = PIX_KEY;
    $amount_formatted = number_format($amount, 2, ',', '.');
    $transaction_id = generateTransactionId();
    
    $msg = "💳 <b>Comprar Saldo com PIX Automático</b>\n\n";
    $msg .= "⏰ Expira em: 15 minutos\n";
    $msg .= "💰 Valor: R$ {$amount_formatted}\n\n";
    $msg .= "ID da compra: {$transaction_id}\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "Este código \"copia e cola\" é válido para apenas 1 pagamento!\n";
    $msg .= "Ou seja, se você utilizar ele mais de 1 vez para adicionar saldo, você PERDERÁ o saldo e não tem direito a reembolso!\n\n";
    $msg .= "PIX copia e cola:\n";
    $msg .= "Clique no código para copiá-lo.\n\n";
    $msg .= "<b>copiar</b>\n";
    $msg .= "<code>{$pix_key}</code>\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "✅ Após o pagamento ser efetuado, seu saldo será liberado instantaneamente.";
    
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