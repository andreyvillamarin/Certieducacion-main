<?php
// courses/associate-student.php - Endpoint para asociar un estudiante a un curso

require_once '../../api_php/db_connect.php'; // Asegúrate de que la ruta sea correcta

// Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(["error" => "Método no permitido."], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['course_code']) || !isset($data['student_identification'])) {
    send_json_response(["error" => "Faltan datos: se requiere course_code y student_identification."], 400);
}

$course_code = trim($data['course_code']);
$student_identification = trim($data['student_identification']);

try {
    // Obtener ID del curso
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
    $stmt->execute([$course_code]);
    $course = $stmt->fetch();
    if (!$course) {
        send_json_response(["error" => "Curso no encontrado."], 404);
    }
    $course_id = $course['id'];

    // Obtener ID del estudiante
    $stmt = $pdo->prepare("SELECT id FROM students WHERE identification = ?");
    $stmt->execute([$student_identification]);
    $student = $stmt->fetch();
    if (!$student) {
        send_json_response(["error" => "Estudiante no encontrado."], 404);
    }
    $student_id = $student['id'];

    // Verificar si la asociación ya existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_student WHERE course_id = ? AND student_id = ?");
    $stmt->execute([$course_id, $student_id]);
    if ($stmt->fetchColumn() > 0) {
        send_json_response(["error" => "El estudiante ya está asociado a este curso."], 409);
    }

    // Insertar la asociación
    $stmt = $pdo->prepare("INSERT INTO course_student (course_id, student_id) VALUES (?, ?)");
    $stmt->execute([$course_id, $student_id]);

    send_json_response([
        "success" => true,
        "message" => "Estudiante asociado al curso con éxito."
    ], 200);

} catch (PDOException $e) {
    error_log("Error en courses/associate-student.php: " . $e->getMessage());
    send_json_response(["error" => "Error interno del servidor."], 500);
}
?>