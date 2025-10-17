<?php
// courses/list.php - Endpoint para obtener una lista de todos los cursos con sus estudiantes

require_once '../../api_php/db_connect.php'; // Asegúrate de que la ruta sea correcta

// Solo permitir peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(["error" => "Método no permitido."], 405);
}

try {
    // Obtener todos los cursos
    $stmt_courses = $pdo->query("SELECT id, course_code, name, duration FROM courses WHERE status = 'active' ORDER BY name ASC");
    $courses_data = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);

    $response_courses = [];
    foreach ($courses_data as $course) {
        // Para cada curso, obtener sus estudiantes asociados
        $stmt_students = $pdo->prepare("
            SELECT s.id, s.identification, s.name, s.email, s.phone
            FROM students s
            JOIN course_student cs ON s.id = cs.student_id
            WHERE cs.course_id = ?
            ORDER BY s.name ASC
        ");
        $stmt_students->execute([$course['id']]);
        $students_in_course = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        $response_courses[] = [
            "course_id" => $course['id'],
            "course_code" => $course['course_code'],
            "course_name" => $course['name'],
            "duration" => $course['duration'],
            "students" => $students_in_course // Array de estudiantes asociados
        ];
    }

    send_json_response($response_courses);

} catch (PDOException $e) {
    error_log("Error en courses/list.php: " . $e->getMessage());
    send_json_response(["error" => "Error interno del servidor."], 500);
}
?>