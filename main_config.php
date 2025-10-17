<?php
// main_config.php (AHORA EN EL DIRECTORIO SEGURO)

// --- NO MODIFICAR: ROOT_PATH se define en el config.php de la raíz web --- 

// -- 1. CONFIGURACIÓN DE LA BASE DE DATOS --
// Las credenciales de la base de datos se han movido a un archivo separado.
// Ahora se carga desde el directorio seguro.
require_once SECURE_CONFIG_DIR . '/db_credentials.php';

// Incluir la conexión a la base de datos
require_once ROOT_PATH . '/includes/database.php';

// -- 2. CONFIGURACIÓN GENERAL DE LA APLICACIÓN --
define('BASE_URL', 'https://qdos.network/demos/certieducacion3/');
define('WHATSAPP_SUPPORT_NUMBER', '573204615527');

// -- 3. CONFIGURACIÓN DE ENVÍO DE CORREO (BREVO SMTP) --
define('BREVO_SMTP_HOST', '');
define('BREVO_SMTP_PORT', 587);
define('BREVO_SMTP_USER', '');
define('BREVO_SMTP_KEY', '');
define('SMTP_FROM_EMAIL', 'andreyvillamarin@gmail.com');
define('SMTP_FROM_NAME', 'Comfamiliar');

// -- 4. CONFIGURACIÓN DE ENVÍO DE SMS (ALTIRIA) --
define('ALTIRIA_LOGIN', 'andreyvillamarin@gmail.com');
define('ALTIRIA_PASSWORD', 'eb9xmheb');
define('ALTIRIA_SENDER_ID', 'Comfamiliar');

// -- Configuración Interna (No modificar) --
// -- 5. CONFIGURACIÓN DE SEGURIDAD --
// Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
// Configuración de cookies de sesión para mayor seguridad
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
'lifetime' => $cookieParams['lifetime'],
'path' => $cookieParams['path'],
'domain' => '',
'secure' => true, // Solo enviar cookies sobre HTTPS
'httponly' => true, // Prevenir acceso a cookies desde JavaScript
'samesite' => 'Lax' // Prevenir ataques CSRF
]);
session_start();
}

// -- 6. GENERACIÓN DE TOKEN CSRF --
// Para prevenir ataques de tipo Cross-Site Request Forgery.
if (empty($_SESSION['csrf_token'])) {
 $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Headers de seguridad para prevenir ataques comunes
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' https://placehold.co data: https://qdos.network; object-src 'none'; frame-ancestors 'self'; form-action 'self'; base-uri 'self';");
header("X-Content-Type-Options: nosniff"); // Previene que el navegador interprete archivos con un tipo MIME incorrecto
header("X-Frame-Options: SAMEORIGIN"); // Alternativa a CSP para prevenir Clickjacking
header("X-XSS-Protection: 1; mode=block"); // Activa el filtro XSS en navegadores antiguos

error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en producción
ini_set('log_errors', 1); // Registrar errores en el log
date_default_timezone_set('America/Bogota');
?>
