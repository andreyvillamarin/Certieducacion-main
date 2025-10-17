<?php
// admin/generate_certificate_handler.php (MODIFICADO PARA API EXTERNA)

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
require_once ROOT_PATH . '/config.php';
require_once ROOT_PATH . '/includes/database.php';
require_once ROOT_PATH . '/includes/logger.php';
require_once __DIR__ . '/includes/pdf_generator_func.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// --- 1. Validar la entrada --- 
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['course_data'])) {
    $_SESSION['notification'] = ['type' => 'danger', 'message' => 'No se seleccionó ningún curso o la solicitud es incorrecta.'];
    header('Location: certificates.php');
    exit;
}

// --- 2. Decodificar datos y obtener fecha ---
$course_data = json_decode($_POST['course_data'], true);
$issue_date_str = $_POST['issue_date'] ?? date('Y-m-d');

if (json_last_error() !== JSON_ERROR_NONE || empty($course_data['students'])) {
    $_SESSION['notification'] = ['type' => 'danger', 'message' => 'Los datos del curso están corruptos o no contienen estudiantes.'];
    header('Location: certificates.php');
    exit;
}

$success_count = 0;
$error_count = 0;

// --- 3. Cargar plantilla de certificado (se mantiene igual) ---
$template_path = ROOT_PATH . '/admin/certificate_template.json';
if (!file_exists($template_path)) {
    $_SESSION['notification'] = ['type' => 'danger', 'message' => 'No se encontró el archivo de plantilla (certificate_template.json).'];
    header('Location: certificates.php');
    exit;
}
$template_json = file_get_contents($template_path);
$template_data = json_decode($template_json, true);

// --- 4. Procesar cada estudiante del curso seleccionado ---
foreach ($course_data['students'] as $student) {
    try {
        // --- SINCRONIZACIÓN DE ESTUDIANTES ---
        // Para mantener la integridad de la base de datos, verificamos si el estudiante ya existe.
        // Si no existe, lo creamos. Esto asegura que el `student_id` foráneo en la tabla de certificados sea válido.
        $stmt = $pdo->prepare("SELECT id FROM students WHERE identification = ?");
        $stmt->execute([$student['identification']]);
        $student_id = $stmt->fetchColumn();

        if (!$student_id) {
            // El estudiante no existe, lo insertamos en la BD local.
            // En un escenario real, podríamos querer evitar esto, pero es necesario por la clave foránea.
            $stmt_insert_student = $pdo->prepare("INSERT INTO students (name, identification) VALUES (?, ?)");
            $stmt_insert_student->execute([$student['name'], $student['identification']]);
            $student_id = $pdo->lastInsertId();
        }

        // --- Generación de PDF y Código ---
        $validation_code = 'CERT-' . strtoupper(uniqid()) . '-' . date('Y');

        // Llamar a la función refactorizada para obtener el contenido del PDF
        $pdf_content_string = create_certificate_from_data(
            $student,
            $course_data['course_name'],
            $course_data['duration'],
            $issue_date_str,
            $template_data,
            $validation_code
        );

// --- 5. Enviar PDF a la API externa ---
$api_url = API_BASE_URL . 'certificates/create.php'; // <-- URL DE LA API CORRECTA

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'pdf_content' => base64_encode($pdf_content_string),
    'student_identification' => $student['identification'],
    'course_code' => $course_data['course_code'], // <-- AÑADIDO
    'validation_code' => $validation_code, // <-- AÑADIDO
    'issue_date' => $issue_date_str // <-- AÑADIDO
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$api_response_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$api_response = json_decode($api_response_json, true);

// Añadir logs para depuración de la respuesta de la API
error_log("DEBUG API Response HTTP Code: " . $http_code); // <-- AÑADIDO
error_log("DEBUG API Response JSON: " . $api_response_json); // <-- AÑADIDO

if ($http_code !== 201 || !($api_response['success'] ?? false)) { // <-- CAMBIADO a 201 (Created)
    throw new Exception("Error al guardar el certificado en el servidor externo. Respuesta: " . ($api_response['message'] ?? 'Sin mensaje') . " HTTP Code: " . $http_code); // <-- MENSAJE MEJORADO
}

// --- 6. Guardar metadatos en la BD local ---
// Usamos la URL/ruta que la API nos devuelve (aunque sea simulada)
// La API PHP devuelve pdf_url, que es una ruta relativa. Necesitamos la URL completa.
$pdf_path_external = $api_response['pdf_filename'] ?? 'unknown.pdf'; // La API devuelve el filename
$full_pdf_url = API_BASE_URL . 'certificates/download.php?filename=' . urlencode($pdf_path_external); // <-- URL COMPLETA DEL PDF

$admin_id = $_SESSION['admin_id'] ?? null;
// Asegúrate de que la tabla 'certificates' en la BD local tenga los campos correctos
// y que 'pdf_path' sea lo suficientemente largo para la URL completa si la guardas.
$stmt_insert = $pdo->prepare("INSERT INTO certificates (student_id, course_name, issue_date, validation_code, pdf_path, generated_by_user_id) VALUES (?, ?, ?, ?, ?, ?)");
$stmt_insert->execute([$student_id, $course_data['course_name'], $issue_date_str, $validation_code, $full_pdf_url, $admin_id]); // <-- USAR full_pdf_url
$certificate_id = $pdo->lastInsertId();

log_activity($pdo, $admin_id, 'certificado_creado', $certificate_id, 'certificates', "Certificado para {$student['name']} del curso {$course_data['course_name']} enviado a API externa.");

$success_count++;

    } catch (Exception $e) {
        $error_count++;
        error_log("Error al generar certificado para estudiante con ID de API {$student['identification']}: " . $e->getMessage());
    }
}

$message = "Proceso completado. Certificados generados y enviados a la API: {$success_count}. Errores: {$error_count}.";
if ($error_count > 0) {
    $message .= " Revise el log de errores del servidor para más detalles.";
}
$_SESSION['notification'] = ['type' => $error_count > 0 ? 'warning' : 'success', 'message' => $message];
header('Location: certificates.php');
exit;
?>