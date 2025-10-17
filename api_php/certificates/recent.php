<?php
// certificates/recent.php - Endpoint para obtener los últimos certificados generados

require_once '../../api_php/db_connect.php'; // Asegúrate de que la ruta sea correcta

// Solo permitir peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(["error" => "Método no permitido."], 405);
}

try {
    // Consulta para obtener los últimos 20 certificados generados
    $stmt = $pdo->query("
        SELECT
            c.id AS certificate_id, c.validation_code, c.issue_date, c.pdf_filename,
            s.name AS student_name, s.identification AS student_identification,
            co.name AS course_name, co.course_code
        FROM certificates c
        JOIN students s ON c.student_id = s.id
        JOIN courses co ON c.course_id = co.id
        ORDER BY c.id DESC
        LIMIT 20
    ");
    $recent_certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response_certs = [];
    foreach ($recent_certificates as $cert) {
        $response_certs[] = [
            "id" => $cert['certificate_id'],
            "validation_code" => $cert['validation_code'],
            "issue_date" => $cert['issue_date'],
            "pdf_filename" => $cert['pdf_filename'],
             "pdf_url" => API_BASE_URL . "certificates/download.php?filename=" . urlencode($cert['pdf_filename']),
            "student_name" => $cert['student_name'],
            "student_identification" => $cert['student_identification'],
            "course_name" => $cert['course_name'],
            "course_code" => $cert['course_code']
        ];
    }

    send_json_response($response_certs);

} catch (PDOException $e) {
    error_log("Error en certificates/recent.php: " . $e->getMessage());
    send_json_response(["error" => "Error interno del servidor."], 500);
}
?>