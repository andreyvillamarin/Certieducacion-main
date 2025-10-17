<?php
// certificates/student.php - Endpoint para obtener certificados de un estudiante por ID

require_once '../db_connect.php'; // Asegúrate de que la ruta sea correcta

// Solo permitir peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(["error" => "Método no permitido."], 405);
}

// Obtener el número de identificación de la URL
// Ejemplo de uso: /api_php/certificates/student.php?identification=7701810
$identification = isset($_GET['identification']) ? trim($_GET['identification']) : '';

if (empty($identification)) {
    send_json_response(["error" => "Número de identificación es requerido."], 400);
}

try {
    // Consulta para obtener los datos del estudiante y sus certificados
    // Asumimos una estructura de tablas similar a la de la API de Python
    $stmt = $pdo->prepare("
        SELECT
            s.id AS student_id, s.identification, s.name AS student_name, s.email, s.phone,
            c.id AS certificate_id, c.validation_code, c.issue_date, c.pdf_filename,
            co.id AS course_id, co.course_code, co.name AS course_name, co.duration
        FROM students s
        JOIN certificates c ON s.id = c.student_id
        JOIN courses co ON c.course_id = co.id
        WHERE s.identification = :identification
        ORDER BY c.issue_date DESC
    ");
    $stmt->execute([':identification' => $identification]);
    $results = $stmt->fetchAll();

    if (empty($results)) {
        send_json_response([], 200); // Retorna un array vacío si no hay certificados
    }

    $certificates = [];
    foreach ($results as $row) {
        $certificates[] = [
            "id" => $row['certificate_id'],
            "validation_code" => $row['validation_code'],
            "issue_date" => $row['issue_date'],
            "pdf_filename" => $row['pdf_filename'],
            "pdf_url" => API_BASE_URL . "certificates/download.php?filename=" . urlencode($row['pdf_filename']), // URL para descargar el PDF
            "student" => [
                "id" => $row['student_id'],
                "identification" => $row['identification'],
                "name" => $row['student_name'],
                "email" => $row['email'],
                "phone" => $row['phone']
            ],
            "course" => [
                "id" => $row['course_id'],
                "course_code" => $row['course_code'],
                "name" => $row['course_name'],
                "duration" => $row['duration']
            ]
        ];
    }

    send_json_response($certificates);

} catch (PDOException $e) {
    error_log("Error en certificates/student.php: " . $e->getMessage());
    send_json_response(["error" => "Error interno del servidor."], 500);
}
?>