<?php
// certificates/create.php - Endpoint para crear y guardar un certificado

require_once '../../api_php/db_connect.php'; // Asegúrate de que la ruta sea correcta

// Definir la ruta base donde se guardarán los PDFs
// ¡AJUSTA ESTA RUTA A LA MISMA UBICACIÓN QUE DEFINISTE EN certificates/download.php!
define('PDF_STORAGE_PATH', '/home/qdosnet/public_html/pdf_certificates/');

// Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(["error" => "Método no permitido."], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

$required_fields = ['pdf_content', 'student_identification', 'course_code', 'validation_code', 'issue_date'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        send_json_response(["error" => "Faltan campos requeridos: " . implode(', ', $required_fields) . "."], 400); // Mensaje mejorado
    }
}

$pdf_content_base64 = $data['pdf_content'];
$student_identification = trim($data['student_identification']);
$course_code = trim($data['course_code']);
$validation_code = trim($data['validation_code']);
$issue_date = trim($data['issue_date']);

error_log("DEBUG: create.php - student_identification: '" . $student_identification . "'"); // <-- AÑADIDO
error_log("DEBUG: create.php - course_code: '" . $course_code . "'"); // <-- AÑADIDO
error_log("DEBUG: create.php - validation_code: '" . $validation_code . "'"); // <-- AÑADIDO
error_log("DEBUG: create.php - issue_date: '" . $issue_date . "'"); // <-- AÑADIDO


try {
    // Obtener ID del estudiante
    $stmt = $pdo->prepare("SELECT id FROM students WHERE identification = ?");
    $stmt->execute([$student_identification]);
    $student = $stmt->fetch();
    if (!$student) {
        error_log("DEBUG: create.php - Estudiante no encontrado para identification: " . $student_identification); // <-- AÑADIDO
        send_json_response(["error" => "Estudiante '" . htmlspecialchars($student_identification) . "' no encontrado."], 404);
    }
    $student_id = $student['id'];
    error_log("DEBUG: create.php - student_id: " . $student_id); // <-- AÑADIDO

    // Obtener ID del curso
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
    $stmt->execute([$course_code]);
    $course = $stmt->fetch();
    if (!$course) {
        error_log("DEBUG: create.php - Curso no encontrado para course_code: " . $course_code); // <-- AÑADIDO
        send_json_response(["error" => "Curso '" . htmlspecialchars($course_code) . "' no encontrado."], 404);
    }
    $course_id = $course['id'];
    error_log("DEBUG: create.php - course_id: " . $course_id); // <-- AÑADIDO

    // Decodificar el contenido PDF
    $pdf_content = base64_decode($pdf_content_base64);
    if ($pdf_content === false) {
        error_log("DEBUG: Contenido PDF base64 inválido."); // <-- AÑADIDO
        send_json_response(["error" => "El contenido del PDF no es un base64 válido."], 400);
    }
    error_log("DEBUG: Tamaño del contenido PDF decodificado: " . strlen($pdf_content) . " bytes."); // <-- AÑADIDO

    // Generar nombre de archivo único para el PDF
    $pdf_filename = "cert-" . $course_code . "-" . $student_identification . "-" . time() . ".pdf";
    $pdf_save_path = PDF_STORAGE_PATH . $pdf_filename;

    error_log("DEBUG: PDF_STORAGE_PATH: " . PDF_STORAGE_PATH); // <-- AÑADIDO
    error_log("DEBUG: pdf_filename: " . $pdf_filename); // <-- AÑADIDO
    error_log("DEBUG: pdf_save_path: " . $pdf_save_path); // <-- AÑADIDO


    // Guardar el archivo PDF
    if (!file_put_contents($pdf_save_path, $pdf_content)) {
        error_log("ERROR: file_put_contents falló al guardar el PDF en: " . $pdf_save_path); // <-- MODIFICADO
        throw new Exception("No se pudo guardar el archivo PDF.");
    } else {
        error_log("DEBUG: Archivo PDF guardado con éxito en: " . $pdf_save_path); // <-- AÑADIDO
    }

    // Insertar el certificado en la base de datos
    $stmt = $pdo->prepare("INSERT INTO certificates (validation_code, issue_date, pdf_filename, student_id, course_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$validation_code, $issue_date, $pdf_filename, $student_id, $course_id]);

    $new_certificate_id = $pdo->lastInsertId();

    send_json_response([
        "success" => true,
        "message" => "Certificado guardado con éxito.",
        "certificate_id" => $new_certificate_id,
        "pdf_filename" => $pdf_filename // Devolver el nombre del archivo guardado
    ], 201);

} catch (Exception $e) {
    error_log("Error en certificates/create.php: " . $e->getMessage());
    send_json_response(["error" => "Error en el servidor: " . $e->getMessage()], 500);
}
?>