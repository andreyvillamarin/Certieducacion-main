<?php
// courses/update_status.php - Endpoint para actualizar el estado de un curso

require_once '../../api_php/db_connect.php'; // Asegúrate de que la ruta sea correcta

// Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(["error" => "Método no permitido."], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['course_code']) || !isset($data['status'])) {
    send_json_response(["error" => "Faltan datos: se requiere course_code y status."], 400);
}

$course_code = trim($data['course_code']);
$new_status = trim($data['status']);

// Validar que el estado sea uno permitido (opcional pero recomendado)
$allowed_statuses = ['active', 'completed', 'archived']; // Puedes añadir más si necesitas
if (!in_array($new_status, $allowed_statuses)) {
    send_json_response(["error" => "Estado no válido. Estados permitidos: " . implode(', ', $allowed_statuses) . "."], 400);
}

try {
    // Actualizar el estado del curso
    $stmt = $pdo->prepare("UPDATE courses SET status = ? WHERE course_code = ?");
    $stmt->execute([$new_status, $course_code]);

    if ($stmt->rowCount() === 0) {
        send_json_response(["error" => "Curso no encontrado o estado ya actualizado."], 404);
    }

    send_json_response([
        "success" => true,
        "message" => "Estado del curso '{$course_code}' actualizado a '{$new_status}'."
    ], 200);

} catch (PDOException $e) {
    error_log("Error al actualizar estado del curso: " . $e->getMessage());
    send_json_response(["error" => "Error interno del servidor."], 500);
}
?>