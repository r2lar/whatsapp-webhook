<?php
// webhook.php - Webhook con lista desplegable interactiva para WhatsApp Business API

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Variables de entorno - Configurar en Railway
$WHATSAPP_TOKEN = getenv('WHATSAPP_TOKEN');
$VERIFY_TOKEN = getenv('VERIFY_TOKEN');
$PHONE_NUMBER_ID = getenv('PHONE_NUMBER_ID');
$MYSQL_HOST = getenv('MYSQLHOST');
$MYSQL_PORT = getenv('MYSQLPORT');
$MYSQL_DATABASE = getenv('MYSQLDATABASE');
$MYSQL_USER = getenv('MYSQLUSER');
$MYSQL_PASSWORD = getenv('MYSQLPASSWORD');

// Validar variables críticas
if (!$WHATSAPP_TOKEN || !$VERIFY_TOKEN || !$PHONE_NUMBER_ID) {
    error_log("ERROR CRÍTICO: Variables de WhatsApp no configuradas");
    http_response_code(500);
    exit('Configuración incompleta. Verifica WHATSAPP_TOKEN, VERIFY_TOKEN y PHONE_NUMBER_ID');
}

// Conexión a MySQL
function getDBConnection() {
    global $MYSQL_HOST, $MYSQL_PORT, $MYSQL_DATABASE, $MYSQL_USER, $MYSQL_PASSWORD;
    try {
        $dsn = "mysql:host=$MYSQL_HOST;port=$MYSQL_PORT;dbname=$MYSQL_DATABASE;charset=utf8mb4";
        $pdo = new PDO($dsn, $MYSQL_USER, $MYSQL_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión a BD: " . $e->getMessage());
        return null;
    }
}

// Log a base de datos
function logToDatabase($data) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    try {
        $sql = "INSERT INTO whatsapp_logs 
                (message_id, from_number, message_type, message_text, 
                 message_media_url, message_timestamp, direction, response_sent) 
                VALUES 
                (:message_id, :from_number, :message_type, :message_text, 
                 :message_media_url, :message_timestamp, :direction, :response_sent)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error al guardar log: " . $e->getMessage());
        return false;
    }
}

// Actualizar log con respuesta
function updateLogResponse($logId, $response) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    try {
        $sql = "UPDATE whatsapp_logs SET response_sent = :response WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':response' => mb_substr($response, 0, 1000, 'UTF-8'),
            ':id' => $logId
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al actualizar log: " . $e->getMessage());
        return false;
    }
}

// Obtener servicios activos
function getServicesList() {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY id LIMIT 6");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener servicios: " . $e->getMessage());
        return [];
    }
}

// ENVIAR LISTA DESPLEGABLE INTERACTIVA
function sendInteractiveList($to) {
    global $WHATSAPP_TOKEN, $PHONE_NUMBER_ID;
    
    $services = getServicesList();
    if (empty($services)) {
        error_log("No hay servicios disponibles para enviar lista");
        return sendTextMessage($to, "⚠️ Actualmente no hay servicios disponibles. Por favor, contáctame directamente.");
    }
    
    $rows = [];
    foreach ($services as $service) {
        $rows[] = [
            'id' => 'service_' . $service['id'],
            'title' => substr($service['service_name'], 0, 24),
            'description' => substr($service['description'], 0, 72)
        ];
    }
    
    $data = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'interactive',
        'interactive' => [
            'type' => 'list',
            'header' => [
                'type' => 'text',
                'text' => '📋 Mis Servicios'
            ],
            'body' => [
                'text' => 'Selecciona un servicio de la lista:'
            ],
            'footer' => [
                'text' => 'Total: ' . count($services) . ' servicios disponibles'
            ],
            'action' => [
                'button' => 'Ver Servicios',
                'sections' => [
                    [
                        'title' => 'Servicios Profesionales',
                        'rows' => $rows
                    ]
                ]
            ]
        ]
    ];
    
    $url = "https://graph.facebook.com/v21.0/{$PHONE_NUMBER_ID}/messages";
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $WHATSAPP_TOKEN,
            'Content-Type: application/json'
        ],
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("cURL error al enviar lista: " . curl_error($ch));
    }
    
    curl_close($ch);
    
    $result = [
        'http_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
    
    if ($httpCode === 200) {
        error_log("Lista desplegable enviada exitosamente a $to");
    } else {
        error_log("Error al enviar lista a $to. HTTP $httpCode: " . json_encode($result['response']));
    }
    
    return $result;
}

// ENVIAR MENSAJE DE TEXTO SIMPLE
function sendTextMessage($to, $message) {
    global $WHATSAPP_TOKEN, $PHONE_NUMBER_ID;
    
    $data = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => $message]
    ];
    
    $url = "https://graph.facebook.com/v21.0/{$PHONE_NUMBER_ID}/messages";
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $WHATSAPP_TOKEN,
            'Content-Type: application/json'
        ],
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("cURL error al enviar mensaje: " . curl_error($ch));
    }
    
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

// Manejar selección de lista interactiva
function handleInteractiveResponse($from, $selectedId) {
    $pdo = getDBConnection();
    
    $serviceId = str_replace('service_', '', $selectedId);
    
    if (!is_numeric($serviceId)) {
        return "❌ Selección inválida. Por favor, selecciona una opción de la lista.";
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $serviceId]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            $sessionData = [
                'service_id' => $service['id'],
                'service_name' => $service['service_name'],
                'price_range' => $service['price_range'],
                'selection_time' => date('Y-m-d H:i:s')
            ];
            
            $sql = "INSERT INTO user_sessions 
                    (phone_number, current_step, selected_service, data) 
                    VALUES 
                    (:phone_number, 'service_selected', :service_key, :data)
                    ON DUPLICATE KEY UPDATE 
                    current_step = 'service_selected', 
                    selected_service = :service_key_update,
                    data = :data_update,
                    last_interaction = CURRENT_TIMESTAMP";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':phone_number' => $from,
                ':service_key' => $service['service_key'],
                ':service_key_update' => $service['service_key'],
                ':data' => json_encode($sessionData),
                ':data_update' => json_encode($sessionData)
            ]);
            
            $response = "✅ *Has seleccionado: {$service['service_name']}*\n\n";
            $response .= "📋 *Descripción:* {$service['description']}\n";
            $response .= "💰 *Rango de precios:* {$service['price_range']}\n\n";
            $response .= "Para continuar:\n";
            $response .= "• Escribe *'agendar'* para programar una reunión\n";
            $response .= "• Escribe *'menu'* para ver otros servicios\n";
            $response .= "• O contáctame directamente:\n";
            $response .= "  📧 contacto@tudominio.com\n";
            $response .= "  📞 +XX XXX XXX XXX";
            
            return $response;
        }
    } catch (PDOException $e) {
        error_log("Error al manejar selección: " . $e->getMessage());
    }
    
    return "❌ Servicio no disponible. Escribe *'menu'* para ver la lista nuevamente.";
}

// Obtener sesión del usuario
function getUserSession($phoneNumber) {
    $pdo = getDBConnection();
    if (!$pdo) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE phone_number = :phone");
        $stmt->execute([':phone' => $phoneNumber]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session && $session['data']) {
            $session['data'] = json_decode($session['data'], true);
        }
        
        return $session;
    } catch (PDOException $e) {
        error_log("Error al obtener sesión: " . $e->getMessage());
        return null;
    }
}

// Actualizar datos de sesión
function updateSessionData($phoneNumber, $newData) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $session = getUserSession($phoneNumber);
        $currentData = $session['data'] ?? [];
        $updatedData = array_merge($currentData, $newData);
        
        $sql = "UPDATE user_sessions 
                SET data = :data, last_interaction = CURRENT_TIMESTAMP 
                WHERE phone_number = :phone";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':data' => json_encode($updatedData),
            ':phone' => $phoneNumber
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error al actualizar sesión: " . $e->getMessage());
        return false;
    }
}

// Procesar mensaje entrante
function processIncomingMessage($messageData) {
    $from = $messageData['from'];
    $messageType = $messageData['type'];
    $messageId = $messageData['id'] ?? 'N/A';
    $messageBody = '';
    $interactiveType = '';
    $selectedId = '';
    
    if ($messageType === 'text') {
        $messageBody = strtolower(trim($messageData['text']['body']));
    } elseif ($messageType === 'interactive') {
        $interactiveType = $messageData['interactive']['type'] ?? '';
        if ($interactiveType === 'list_reply') {
            $selectedId = $messageData['interactive']['list_reply']['id'] ?? '';
            $messageBody = "list_selection:" . $selectedId;
        }
    }
    
    $logId = logToDatabase([
        ':message_id' => $messageId,
        ':from_number' => $from,
        ':message_type' => $messageType . ($interactiveType ? '_' . $interactiveType : ''),
        ':message_text' => $messageBody,
        ':message_media_url' => null,
        ':message_timestamp' => date('Y-m-d H:i:s'),
        ':direction' => 'incoming',
        ':response_sent' => 'pending'
    ]);
    
    $response = '';
    $sendResult = null;
    
    if ($messageBody === 'hola' || $messageBody === 'hi' || $messageBody === 'hello') {
        $welcomeResult = sendTextMessage($from, "¡Hola! 👋\n\nTe muestro mis servicios profesionales:");
        if ($welcomeResult['http_code'] === 200) {
            $sendResult = sendInteractiveList($from);
            $response = "Mensaje de bienvenida + lista desplegable enviados";
        }
    }
    elseif ($messageBody === 'menu') {
        $sendResult = sendInteractiveList($from);
        $response = "Lista desplegable enviada";
    }
    elseif (strpos($messageBody, 'list_selection:') === 0) {
        $selectedId = str_replace('list_selection:', '', $messageBody);
        $responseText = handleInteractiveResponse($from, $selectedId);
        $sendResult = sendTextMessage($from, $responseText);
        $response = "Respuesta a selección: " . substr($responseText, 0, 100);
    }
    elseif ($messageBody === 'info') {
        $responseText = "📞 *Información de Contacto*\n\n";
        $responseText .= "Para consultas personalizadas:\n";
        $responseText .= "📧 Email: contacto@tudominio.com\n";
        $responseText .= "🌐 Web: www.tudominio.com\n";
        $responseText .= "📞 Teléfono: +XX XXX XXX XXX\n\n";
        $responseText .= "Escribe *'menu'* para ver servicios.";
        $sendResult = sendTextMessage($from, $responseText);
        $response = "Información de contacto enviada";
    }
    elseif ($messageBody === 'agendar') {
        $session = getUserSession($from);
        
        if (!$session || !$session['selected_service']) {
            $responseText = "⚠️ Primero debes seleccionar un servicio.\n\n";
            $responseText .= "Escribe *'menu'* para ver los servicios disponibles.";
        } else {
            updateSessionData($from, [
                'current_step' => 'awaiting_schedule',
                'started_booking' => date('Y-m-d H:i:s')
            ]);
            
            $serviceName = $session['data']['service_name'] ?? $session['selected_service'];
            
            $responseText = "📅 *Agendar: {$serviceName}*\n\n";
            $responseText .= "Por favor, envíame tu disponibilidad:\n\n";
            $responseText .= "Ejemplo:\n";
            $responseText .= "Lunes 15 de enero - 10:00 AM a 2:00 PM\n";
            $responseText .= "Martes 16 de enero - 3:00 PM a 5:00 PM\n\n";
            $responseText .= "Te confirmaré el horario en menos de 24 horas.";
        }
        
        $sendResult = sendTextMessage($from, $responseText);
        $response = "Instrucciones para agendar enviadas";
    }
    else {
        $responseText = "🤔 No entendí tu mensaje.\n\n";
        $responseText .= "Por favor:\n";
        $responseText .= "• Escribe *'menu'* para ver servicios\n";
        $responseText .= "• O selecciona una opción de la lista";
        $sendResult = sendTextMessage($from, $responseText);
        $response = "Mensaje no reconocido - enviadas instrucciones";
    }
    
    if ($logId) {
        updateLogResponse($logId, $response);
    }
    
    if ($sendResult) {
        $status = ($sendResult['http_code'] === 200) ? 'success' : 'error';
        error_log("Respuesta enviada a $from - Status: $status - HTTP: " . $sendResult['http_code']);
    }
    
    return $sendResult;
}

// Webhook GET (verificación de Meta)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
        error_log("Webhook verificado exitosamente");
        echo $challenge;
        http_response_code(200);
    } else {
        error_log("Error de verificación: mode=$mode, token=$token");
        echo 'Error de verificación';
        http_response_code(403);
    }
    exit;
}

// Webhook POST (mensajes entrantes de WhatsApp)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log("Payload webhook recibido: " . json_encode($input));
    
    if (isset($input['entry'][0]['changes'][0]['value']['statuses'])) {
        error_log("Status update recibido - ignorando");
        http_response_code(200);
        exit;
    }
    
    if (!isset($input['entry'][0]['changes'][0]['value']['messages'][0])) {
        error_log("Webhook recibido sin mensajes válidos");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'No messages to process']);
        exit;
    }
    
    $messageData = $input['entry'][0]['changes'][0]['value']['messages'][0];
    
    try {
        processIncomingMessage($messageData);
        error_log("Mensaje procesado exitosamente");
    } catch (Exception $e) {
        error_log("Error al procesar mensaje: " . $e->getMessage());
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
?>
