<?php
// CONFIGURACIÓN
$TOKEN_SECRETO = 'clave_secreta';

// 1. VERIFICACIÓN (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hubMode = $_GET['hub_mode'] ?? '';
    $hubToken = $_GET['hub_verify_token'] ?? '';
    $hubChallenge = $_GET['hub_challenge'] ?? '';
    
    if ($hubMode === 'subscribe' && $hubToken === $TOKEN_SECRETO) {
        header('Content-Type: text/plain');
        echo $hubChallenge;
        exit();
    } else {
        http_response_code(403);
        exit();
    }
}

// 2. RECIBIR MENSAJES (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Guardar mensaje en log
    $logEntry = date('Y-m-d H:i:s') . "\n" . $input . "\n" . str_repeat('-', 50) . "\n\n";
    file_put_contents('messages_log.txt', $logEntry, FILE_APPEND);
    
    // Responder 200 OK
    http_response_code(200);
    echo json_encode(['status' => 'received']);
    exit();
}

// 3. Cualquier otra petición
http_response_code(200);
echo 'OK';
?>
