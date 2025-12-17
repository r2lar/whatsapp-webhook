<?php
// ============================================
// CONFIGURACIÓN Y TOKENS
// ============================================
define('VERIFY_TOKEN', 'TU_TOKEN_SECRETO_AQUI'); // Cambia esto por tu token real
define('WHATSAPP_TOKEN', 'TU_TOKEN_WHATSAPP_AQUI'); // Token de acceso de Meta
$log_file = 'interacciones.log'; // Archivo de log

// ============================================
// 1. VERIFICACIÓN DEL WEBHOOK (Solicitud GET)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] === VERIFY_TOKEN) {
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        echo "Token de verificación incorrecto.";
        exit;
    }
}

// ============================================
// 2. PROCESAMIENTO DE MENSAJES (Solicitud POST)
// ============================================
$input = json_decode(file_get_contents('php://input'), true);

// Validar que hay un evento de mensaje
if (!isset($input['entry'][0]['changes'][0]['value']['messages'][0])) {
    http_response_code(200);
    exit;
}

$message = $input['entry'][0]['changes'][0]['value']['messages'][0];
$phone_number = $message['from']; // Número que envió el mensaje
$message_type = $message['type']; // Tipo: text, interactive, etc.

// ============================================
// 3. REGISTRO EN LOG (Guarda todas las interacciones)
// ============================================
function registrarLog($telefono, $accion, $detalles = '') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] Teléfono: $telefono | Acción: $acción | Detalles: $detalles\n";
    
    // Guardar en archivo (en Railway usa /tmp/ para persistencia entre despliegues)
    file_put_contents('/tmp/' . $log_file, $log_entry, FILE_APPEND);
    
    // También mostrar en logs de Railway para debugging
    error_log("LOG: $log_entry");
}

// ============================================
// 4. DEFINICIÓN DEL MENÚ INTERACTIVO
// ============================================
function crearMenuInicial() {
    $menu_text = "¡Hola! 👋\n\n";
    $menu_text .= "Selecciona una opción de nuestro menú:\n\n";
    $menu_text .= "1️⃣ *Consultoría Digital* - Estrategias para tu negocio online\n";
    $menu_text .= "2️⃣ *Desarrollo Web* - Sitios y aplicaciones a medida\n";
    $menu_text .= "3️⃣ *Marketing Digital* - Campañas y redes sociales\n";
    $menu_text .= "4️⃣ *Diseño Gráfico* - Branding y material visual\n";
    $menu_text .= "5️⃣ *Soporte Técnico* - Asistencia y mantenimiento\n";
    $menu_text .= "6️⃣ *Contacto y Precios* - Cotizaciones y información\n\n";
    $menu_text .= "Responde con el *número* de la opción (1-6)";
    
    return $menu_text;
}

// ============================================
// 5. RESPUESTAS SEGÚN OPCIÓN SELECCIONADA
// ============================================
function obtenerRespuestaOpcion($opcion) {
    $respuestas = [
        1 => [
            'titulo' => "📱 *CONSULTORÍA DIGITAL*",
            'descripcion' => "Analizamos tu presencia digital y creamos una estrategia personalizada para aumentar tu visibilidad y conversiones.\n\n• Auditoría de competencia\n• Plan de transformación digital\n• Métricas y KPIs\n• Implementación guiada",
            'tiempo' => "⏱️ Proyectos de 2-4 semanas",
            'contacto' => "¿Te interesa? Responde 'INFO' para más detalles."
        ],
        2 => [
            'titulo' => "💻 *DESARROLLO WEB*",
            'descripcion' => "Creamos sitios web y aplicaciones funcionales, rápidas y optimizadas.\n\n• Landing pages\n• Tiendas online (e-commerce)\n• Aplicaciones web personalizadas\n• WordPress a medida",
            'tiempo' => "⏱️ Desarrollo: 3-8 semanas según complejidad",
            'contacto' => "¿Tienes un proyecto? Responde 'COTIZAR' para una estimación."
        ],
        3 => [
            'titulo' => "📢 *MARKETING DIGITAL*",
            'descripcion' => "Potenciamos tu marca con campañas efectivas en redes sociales y Google.\n\n• Gestión de redes sociales\n• Publicidad en Meta/Google\n• Email marketing\n• Contenido estratégico",
            'tiempo' => "⏱️ Resultados visibles desde el primer mes",
            'contacto' => "¿Quieres crecer online? Responde 'MARKETING' para un diagnóstico gratis."
        ],
        4 => [
            'titulo' => "🎨 *DISEÑO GRÁFICO*",
            'descripcion' => "Diseños impactantes que comunican la esencia de tu marca.\n\n• Logos e identidad visual\n• Material publicitario\n• Presentaciones profesionales\n• Infografías y folletos",
            'tiempo' => "⏱️ Entrega en 5-10 días hábiles",
            'contacto' => "¿Necesitas diseño? Responde 'DISENO' para ver portafolio."
        ],
        5 => [
            'titulo' => "🔧 *SOPORTE TÉCNICO*",
            'descripcion' => "Mantenimiento y asistencia para que tu tecnología siempre funcione.\n\n• Soporte remoto 24/7\n• Mantenimiento de sitios web\n• Resolución de incidencias\n• Copias de seguridad",
            'tiempo' => "⏱️ Respuesta en menos de 2 horas",
            'contacto' => "¿Tienes una emergencia? Responde 'SOPORTE' para ayuda inmediata."
        ],
        6 => [
            'titulo' => "📞 *CONTACTO Y PRECIOS*",
            'descripcion' => "Contáctanos para una cotización personalizada según tus necesidades.\n\n• Presupuestos detallados\n• Reunión virtual gratuita\n• Modalidades de pago\n• Garantía de satisfacción",
            'tiempo' => "⏱️ Respuesta en 24 horas máximo",
            'contacto' => "📧 contacto@tudominio.com\n📱 +1 (123) 456-7890\n📍 Oficina principal: Ciudad, País"
        ]
    ];
    
    if (isset($respuestas[$opcion])) {
        $r = $respuestas[$opcion];
        $respuesta = $r['titulo'] . "\n\n" . 
                    $r['descripcion'] . "\n\n" . 
                    $r['tiempo'] . "\n\n" . 
                    $r['contacto'];
        return $respuesta;
    }
    
    return "Opción no válida. Por favor, elige un número del 1 al 6.";
}

// ============================================
// 6. LÓGICA PRINCIPAL DE INTERACCIÓN
// ============================================
if ($message_type == 'text') {
    $text = strtolower(trim($message['text']['body']));
    
    // Registrar la interacción en el log
    registrarLog($phone_number, "MENSAJE_TEXTO", "Contenido: $text");
    
    // Mostrar menú si es el primer mensaje o piden "menú"
    if ($text == 'hola' || $text == 'holi' || $text == 'menu' || $text == 'menú' || $text == '0') {
        $respuesta_texto = crearMenuInicial();
        $accion_log = "SOLICITUD_MENU";
    } 
    // Procesar opciones del 1 al 6
    elseif (in_array($text, ['1', '2', '3', '4', '5', '6'])) {
        $opcion = intval($text);
        $respuesta_texto = obtenerRespuestaOpcion($opcion);
        $accion_log = "SELECCION_OPCION_$opcion";
    }
    // Comandos especiales
    elseif ($text == 'info') {
        $respuesta_texto = "Te enviaremos información detallada por email en las próximas 24 horas. ¡Gracias por tu interés!";
        $accion_log = "SOLICITUD_INFO";
    }
    elseif ($text == 'cotizar') {
        $respuesta_texto = "Perfecto. Para darte una cotización precisa, necesito saber:\n1. Tipo de proyecto\n2. Plazo estimado\n3. Presupuesto aproximado\n\nResponde con estos datos o agenda una llamada en: calendly.com/tulink";
        $accion_log = "SOLICITUD_COTIZACION";
    }
    else {
        $respuesta_texto = "No entendí tu mensaje. Por favor, selecciona una opción del 1 al 6 o escribe 'menú' para ver las opciones.";
        $accion_log = "MENSAJE_NO_RECONOCIDO";
    }
    
    // Actualizar log con la acción específica
    registrarLog($phone_number, $accion_log, "Respuesta enviada");
    
} elseif ($message_type == 'interactive') {
    // Manejo de botones interactivos (opcional)
    $button_id = $message['interactive']['button_reply']['id'];
    registrarLog($phone_number, "BOTON_INTERACTIVO", "ID: $button_id");
    $respuesta_texto = "Has seleccionado una opción rápida. Próximamente tendremos más funcionalidades interactivas.";
} else {
    // Otros tipos de mensaje (imagen, audio, etc.)
    registrarLog($phone_number, "MENSAJE_NO_TEXTO", "Tipo: $message_type");
    $respuesta_texto = "Por ahora solo puedo procesar mensajes de texto. Por favor, escribe 'menú' para ver nuestras opciones.";
}

// ============================================
// 7. ENVÍO DE RESPUESTA A WHATSAPP
// ============================================
function enviarWhatsApp($phone_number, $message_text) {
    $url = 'https://graph.facebook.com/v17.0/' . WHATSAPP_TOKEN . '/messages';
    
    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $phone_number,
        'type' => 'text',
        'text' => ['body' => $message_text]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . WHATSAPP_TOKEN
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

// Enviar la respuesta
$response = enviarWhatsApp($phone_number, $respuesta_texto);

// ============================================
// 8. FUNCIÓN PARA VER LOGS (Útil para debugging)
// ============================================
function mostrarLogs() {
    global $log_file;
    if (file_exists('/tmp/' . $log_file)) {
        return file_get_contents('/tmp/' . $log_file);
    }
    return "No hay registros aún.";
}

// Respuesta HTTP exitosa
http_response_code(200);
echo "OK";
?>
