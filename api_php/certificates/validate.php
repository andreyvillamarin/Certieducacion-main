<?php
// certificates/validate.php - Endpoint para validar un código de certificado

require_once '../../api_php/db_connect.php'; // Asegúrate de que la ruta sea correcta

// Solo permitir peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(["error" => "Método no permitido."], 405);
}

// Obtener el código de validación de la URL
// Ejemplo de uso: /api_php/certificates/validate.php?code=XYZ123
$validation_code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($validation_code)) {
    send_json_response(["error" => "Código de validación es requerido."], 400);
}

try {
    // Consulta para obtener los datos del certificado, estudiante y curso
    $stmt = $pdo->prepare("
        SELECT
            s.id AS student_id, s.identification, s.name AS student_name, s.email, s.phone,
            c.id AS certificate_id, c.validation_code, c.issue_date, c.pdf_filename,
            co.id AS course_id, co.course_code, co.name AS course_name, co.duration
        FROM certificates c
        JOIN students s ON c.student_id = s.id
        JOIN courses co ON c.course_id = co.id
        WHERE c.validation_code = :validation_code
        LIMIT 1
    ");
    $stmt->execute([':validation_code' => $validation_code]);
    $result = $stmt->fetch();

    if (!$result) {
        send_json_response(["error" => "Certificado no encontrado o inválido."], 404);
    }

    $certificate_data = [
        "id" => $result['certificate_id'],
        "validation_code" => $result['validation_code'],
        "issue_date" => $result['issue_date'],
        "pdf_filename" => $result['pdf_filename'],
        "pdf_url" => "/api_php/certificates/download.php?filename=" . urlencode($result['pdf_filename']), // URL para descargar el PDF
        "student" => [
            "id" => $result['student_id'],
            "identification" => $result['identification'],
            "name" => $result['student_name'],
            "email" => $result['email'],
            "phone" => $result['phone']
        ],
        "course" => [
            "id" => $result['course_id'],
            "course_code" => $result['course_code'],
            "name" => $result['course_name'],
            "duration" => $result['duration']
        ]
    ];

    send_json_response($certificate_data);

} catch (PDOException $e) {
    error_log("Error en certificates/validate.php: " . $e->getMessage());
    send_json_response(["error" => "Error interno del servidor."], 500);
}
?>