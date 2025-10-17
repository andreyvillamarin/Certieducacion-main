<?php
// students/create.php - Endpoint para crear un nuevo estudiante

require_once '../../api_php/db_connect.php'; // Asegúrate de que la ruta sea correcta

// Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(["error" => "Método no permitido."], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['identification']) || !isset($data['name'])) {
    send_json_response(["error" => "Faltan datos: se requiere identification y name."], 400);
}

$identification = trim($data['identification']);
$name = trim($data['name']);
$email = isset($data['email']) ? trim($data['email']) : null;
$phone = isset($data['phone']) ? trim($data['phone']) : null;

try {
    // Verificar si la identificación ya existe
    $stmt = $pdo->prepare("SELECT id FROM students WHERE identification = ?");
    $stmt->execute([$identification]);
    if ($stmt->fetch()) {
        send_json_response(["error" => "La identificación del estudiante ya existe."], 409);
    }

    // Verificar si el email ya existe (si se proporciona)
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            send_json_response(["error" => "El email del estudiante ya existe."], 409);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO students (identification, name, email, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute([$identification, $name, $email, $phone]);

    $new_student_id = $pdo->lastInsertId();

    send_json_response([
        "success" => true,
        "message" => "Estudiante creado con éxito.",
        "student" => [
            "id" => $new_student_id,
            "identification" => $identification,
            "name" => $name,
            "email" => $email,
            "phone" => $phone
        ]
    ], 201);

} catch (PDOException $e) {
    error_log("Error en students/create.php: " . $e->getMessage());
    send_json_response(["error" => "Error interno del servidor."], 500);
}
?>