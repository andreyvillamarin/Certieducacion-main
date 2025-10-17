<?php
// courses/create.php - Endpoint para crear un nuevo curso

require_once '../../api_php/db_connect.php'; // Asegúrate de que la ruta sea correcta

// Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(["error" => "Método no permitido."], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['course_code']) || !isset($data['name']) || !isset($data['duration'])) {
    send_json_response(["error" => "Faltan datos: se requiere course_code, name y duration."], 400);
}

$course_code = trim($data['course_code']);
$name = trim($data['name']);
$duration = (int)$data['duration'];

try {
    // Verificar si el código de curso ya existe
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
    $stmt->execute([$course_code]);
    if ($stmt->fetch()) {
        send_json_response(["error" => "El código de curso ya existe."], 409);
    }

    $stmt = $pdo->prepare("INSERT INTO courses (course_code, name, duration) VALUES (?, ?, ?)");
    $stmt->execute([$course_code, $name, $duration]);

    $new_course_id = $pdo->lastInsertId();

    send_json_response([
        "success" => true,
        "message" => "Curso creado con éxito.",
        "course" => [
            "id" => $new_course_id,
            "course_code" => $course_code,
            "name" => $name,
            "duration" => $duration
        ]
    ], 201);

} catch (PDOException $e) {
    error_log("Error en courses/create.php: " . $e->getMessage());
    send_json_response(["error" => "Error interno del servidor."], 500);
}
?>