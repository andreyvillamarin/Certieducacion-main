<?php
// import_tool.php - Herramienta de importación masiva para Hosting B

// --- CONFIGURACIÓN DE SEGURIDAD (MUY IMPORTANTE) ---
// Define una contraseña para acceder a esta herramienta.
// ¡CAMBIA ESTO POR UNA CONTRASEÑA SEGURA Y ÚNICA!
define('IMPORT_TOOL_PASSWORD', 'admin123');
// ---------------------------------------------------

// Incluir la conexión a la base de datos
require_once 'db_connect.php';

// Función para manejar la autenticación
function authenticate() {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
        $_SERVER['PHP_AUTH_PW'] !== IMPORT_TOOL_PASSWORD) {
        header('WWW-Authenticate: Basic realm="Herramienta de Importacion"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Acceso no autorizado.';
        exit;
    }
}

authenticate(); // Proteger la herramienta con contraseña

// Función para procesar la creación de estudiantes (directamente en PHP, sin cURL)
function create_student_direct($pdo, $data) {
    $identification = trim($data['identification']);
    $name = trim($data['name']);
    $email = isset($data['email']) ? trim($data['email']) : null;
    $phone = isset($data['phone']) ? trim($data['phone']) : null;

    if (empty($identification) || empty($name)) {
        return ['success' => false, 'message' => 'Identificación y nombre son obligatorios.'];
    }

    try {
        // Verificar si la identificación ya existe
        $stmt = $pdo->prepare("SELECT id FROM students WHERE identification = ?");
        $stmt->execute([$identification]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Estudiante ya existe (identificación: ' . $identification . ').'];
        }

        // Verificar si el email ya existe (si se proporciona)
        if ($email) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email ya existe para otro estudiante.'];
            }
        }

        $stmt = $pdo->prepare("INSERT INTO students (identification, name, email, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$identification, $name, $email, $phone]);
        return ['success' => true, 'message' => 'Estudiante creado.'];

    } catch (PDOException $e) {
        error_log("Error al crear estudiante: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error DB al crear estudiante: ' . $e->getMessage()];
    }
}

// Función para procesar la creación de cursos (directamente en PHP)
function create_course_direct($pdo, $data) {
    $course_code = trim($data['course_code']);
    $name = trim($data['name']);
    $duration = isset($data['duration']) ? (int)$data['duration'] : 0;

    if (empty($course_code) || empty($name)) {
        return ['success' => false, 'message' => 'Código y nombre del curso son obligatorios.'];
    }

    try {
        // Verificar si el código de curso ya existe
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
        $stmt->execute([$course_code]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Curso ya existe (código: ' . $course_code . ').'];
        }

        $stmt = $pdo->prepare("INSERT INTO courses (course_code, name, duration) VALUES (?, ?, ?)");
        $stmt->execute([$course_code, $name, $duration]);
        return ['success' => true, 'message' => 'Curso creado.'];

    } catch (PDOException $e) {
        error_log("Error al crear curso: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error DB al crear curso: ' . $e->getMessage()];
    }
}

// Función para asociar estudiante a curso (directamente en PHP)
function associate_student_to_course_direct($pdo, $student_identification, $course_code) {
    if (empty($student_identification) || empty($course_code)) {
        return ['success' => false, 'message' => 'Identificación de estudiante y código de curso son obligatorios para la asociación.'];
    }

    try {
        // Obtener ID del curso
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
        $stmt->execute([$course_code]);
        $course = $stmt->fetch();
        if (!$course) {
            return ['success' => false, 'message' => 'Curso no encontrado (código: ' . $course_code . ').'];
        }
        $course_id = $course['id'];

        // Obtener ID del estudiante
        $stmt = $pdo->prepare("SELECT id FROM students WHERE identification = ?");
        $stmt->execute([$student_identification]);
        $student = $stmt->fetch();
        if (!$student) {
            return ['success' => false, 'message' => 'Estudiante no encontrado (identificación: ' . $student_identification . ').'];
        }
        $student_id = $student['id'];

        // Verificar si la asociación ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_student WHERE course_id = ? AND student_id = ?");
        $stmt->execute([$course_id, $student_id]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Estudiante ya asociado a este curso.'];
        }

        // Insertar la asociación
        $stmt = $pdo->prepare("INSERT INTO course_student (course_id, student_id) VALUES (?, ?)");
        $stmt->execute([$course_id, $student_id]);
        return ['success' => true, 'message' => 'Asociación creada.'];

    } catch (PDOException $e) {
        error_log("Error al asociar estudiante a curso: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error DB al asociar: ' . $e->getMessage()];
    }
}


$message = '';
$message_type = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $import_type = $_POST['import_type'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Error al subir el archivo.';
        $message_type = 'danger';
    } elseif (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
        $header = fgetcsv($handle); // Leer la primera fila (encabezados)
        $row_num = 0;

        while (($row = fgetcsv($handle)) !== FALSE) {
            $row_num++;
            if (count($header) !== count($row)) {
                $results[] = ['row' => $row_num, 'status' => 'Error', 'message' => 'Número de columnas incorrecto.'];
                continue;
            }
            $data = array_combine($header, $row);
            $data = array_map('trim', $data); // Limpiar espacios en blanco

            if ($import_type === 'students_and_associations') {
                // Proceso para estudiantes y asociaciones
                $student_result = create_student_direct($pdo, $data);
                $results[] = ['row' => $row_num, 'status' => $student_result['success'] ? 'Éxito' : 'Error', 'message' => 'Estudiante: ' . $student_result['message']];

                if (isset($data['course_code']) && !empty($data['course_code'])) {
                    $association_result = associate_student_to_course_direct($pdo, $data['identification'], $data['course_code']);
                    $results[] = ['row' => $row_num, 'status' => $association_result['success'] ? 'Éxito' : 'Error', 'message' => 'Asociación: ' . $association_result['message']];
                } else {
                    $results[] = ['row' => $row_num, 'status' => 'Advertencia', 'message' => 'No se especificó course_code para asociación.'];
                }
            } elseif ($import_type === 'courses') {
                // Proceso para cursos
                $course_result = create_course_direct($pdo, $data);
                $results[] = ['row' => $row_num, 'status' => $course_result['success'] ? 'Éxito' : 'Error', 'message' => 'Curso: ' . $course_result['message']];
            }
            elseif ($import_type === 'combined_students_courses') { // NUEVO TIPO DE IMPORTACIÓN
                // 1. Intentar crear/obtener el curso
                $course_creation_message = '';
                if (isset($data['course_code']) && !empty($data['course_code']) && isset($data['course_name']) && !empty($data['course_name']) && isset($data['course_duration']) && !empty($data['course_duration'])) {
                    $course_data_for_creation = [
                        'course_code' => $data['course_code'],
                        'name' => $data['course_name'],
                        'duration' => $data['course_duration']
                    ];
                    $course_result = create_course_direct($pdo, $course_data_for_creation);
                    $course_creation_message = 'Curso: ' . $course_result['message'];
                    $results[] = ['row' => $row_num, 'status' => $course_result['success'] ? 'Éxito' : 'Error', 'message' => $course_creation_message];
                } else {
                    $course_creation_message = 'Advertencia: Datos de curso incompletos o ausentes.';
                    $results[] = ['row' => $row_num, 'status' => 'Advertencia', 'message' => $course_creation_message];
                }

                // 2. Intentar crear/obtener el estudiante
                $student_result = create_student_direct($pdo, $data);
                $results[] = ['row' => $row_num, 'status' => $student_result['success'] ? 'Éxito' : 'Error', 'message' => 'Estudiante: ' . $student_result['message']];

                // 3. Intentar asociar (si hay datos de curso y estudiante)
                if (isset($data['course_code']) && !empty($data['course_code']) && isset($data['identification']) && !empty($data['identification'])) {
                    $association_result = associate_student_to_course_direct($pdo, $data['identification'], $data['course_code']);
                    $results[] = ['row' => $row_num, 'status' => $association_result['success'] ? 'Éxito' : 'Error', 'message' => 'Asociación: ' . $association_result['message']];
                } else {
                    $results[] = ['row' => $row_num, 'status' => 'Advertencia', 'message' => 'No se pudo intentar la asociación (datos incompletos).'];
                }
            }
        }
        fclose($handle);
        $message = 'Importación completada. Revisa los resultados abajo.';
        $message_type = 'success';
    } else {
        $message = 'No se pudo abrir el archivo CSV.';
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herramienta de Importación Masiva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 800px; margin-top: 50px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .card-header { background-color: #007bff; color: white; border-radius: 10px 10px 0 0; }
        .table-results th, .table-results td { vertical-align: middle; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header text-center">
                <h2>Herramienta de Importación Masiva</h2>
                <p class="mb-0">Crea estudiantes, cursos y asociaciones desde un archivo CSV.</p>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="import_tool.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="import_type" class="form-label">Tipo de Importación:</label>
                        <select class="form-select" id="import_type" name="import_type" required>
                            <option value="">Selecciona...</option>
                            <option value="students_and_associations">Estudiantes y Asociaciones (CSV: identification,name,email,phone,course_code)</option>
                            <option value="courses">Cursos (CSV: course_code,name,duration)</option>
                            <option value="combined_students_courses">Combinado: Estudiantes y Cursos (CSV: identification,name,email,phone,course_code,course_name,course_duration)</option> </select>
                    </div>
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Selecciona Archivo CSV:</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">
                            Para "Estudiantes y Asociaciones": `identification,name,email,phone,course_code`<br>
                            Para "Cursos": `course_code,name,duration`<br>
                            Para "Combinado: Estudiantes y Cursos": `identification,name,email,phone,course_code,course_name,course_duration`
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Importar CSV</button>
                </form>

                <?php if (!empty($results)): ?>
                    <h4 class="mt-4">Resultados de la Importación:</h4>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-results">
                            <thead>
                                <tr>
                                    <th>Fila</th>
                                    <th>Estado</th>
                                    <th>Mensaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <tr class="table-<?php echo ($result['status'] === 'Éxito') ? 'success' : (($result['status'] === 'Advertencia') ? 'warning' : 'danger'); ?>">
                                        <td><?php echo htmlspecialchars($result['row']); ?></td>
                                        <td><?php echo htmlspecialchars($result['status']); ?></td>
                                        <td><?php echo htmlspecialchars($result['message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>