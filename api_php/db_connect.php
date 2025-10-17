<?php
// db_connect.php - Conexión a la base de datos para la API

// --- CORS HEADERS ---
// Permite solo a https://qdos.network acceder a esta API
header("Access-Control-Allow-Origin: https://qdos.network");
// Métodos HTTP permitidos
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Cabeceras HTTP permitidas en las peticiones
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar las peticiones "pre-vuelo" OPTIONS (necesario para algunas peticiones complejas)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
// --- FIN CORS HEADERS ---

// --- CONFIGURACIÓN DE LA API ---
// Define la URL base de la API para construir URLs absolutas
define('API_BASE_URL', 'https://qdosnetwork.com/api_php/'); // <-- AÑADIDO
// --- FIN CONFIGURACIÓN DE LA API ---

// Credenciales de la base de datos en HOSTING B
define('DB_HOST', 'localhost'); // O el host interno de tu DB en Hosting B
define('DB_NAME', 'qdosnet_certieducacion'); // Ej: qdosnetw_certieducacion2
define('DB_USER', 'qdosnet_andrey'); // Ej: qdosnetw_webmaster_b
define('DB_PASS', 'qdosnetwork1993');
define('DB_CHARSET', 'utf8mb4'); // <-- AÑADIDO

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión a la base de datos: " . $e->getMessage()]);
    exit();
}

// Función para enviar respuestas JSON
function send_json_response($data, $status_code = 200) {
    header('Content-Type: application/json');
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}
?>