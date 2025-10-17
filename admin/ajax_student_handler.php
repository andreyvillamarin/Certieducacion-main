<?php
// admin/ajax_student_handler.php

// Establecer la ruta raíz del proyecto de forma robusta
// __DIR__ es el directorio del archivo actual (admin)
// dirname(__DIR__) sube un nivel a la raíz del proyecto (ej: 'certieducacion')
$project_root = dirname(__DIR__);

// Incluir config.php primero
if (file_exists($project_root . '/config.php')) {
    require_once $project_root . '/config.php';
} else {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'message' => 'Error crítico: El archivo de configuración principal (config.php) no se encuentra en la ruta esperada: ' . $project_root . '/config.php']);
    exit;
}

// Incluir database.php
if (file_exists($project_root . '/includes/database.php')) {
    require_once $project_root . '/includes/database.php';
} else {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'message' => 'Error crítico: El archivo de base de datos (database.php) no se encuentra en la ruta esperada: ' . $project_root . '/includes/database.php']);
    exit;
}

// Incluir logger.php
if (file_exists($project_root . '/includes/logger.php')) {
    require_once $project_root . '/includes/logger.php';
} else {
    // No es tan crítico como la BD, pero se podría loguear o enviar una respuesta de error si el logging es mandatorio.
    // Por ahora, solo un error_log para no interrumpir la funcionalidad principal si el logger falla.
    error_log('Advertencia: El archivo logger.php no se encuentra en la ruta esperada: ' . $project_root . '/includes/logger.php');
}


// ESTA ES LA CABECERA IMPORTANTE. Debe estar antes de CUALQUIER echo o salida.
if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
  

// ESTA ES LA CABECERA IMPORTANTE. Debe estar antes de CUALQUIER echo o salida.
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Iniciar sesión si es necesaria y no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$current_admin_id = $_SESSION['admin_id'] ?? null; // Obtener el admin_id para el logging

// Función para unificar respuestas
function send_response($success, $message = '', $data = []) {
    // Si las cabeceras no se enviaron antes (ej. por un error de file_exists), intentarlo aquí.
    // Aunque lo ideal es que la cabecera principal ya se haya enviado.
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Verificar si $pdo se inicializó correctamente en database.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    // No usar send_response aquí si $pdo es necesario para ella (aunque en este caso no lo es directamente)
    // Pero es mejor un error genérico si la BD no está.
    error_log('Error crítico: La conexión a la base de datos ($pdo) no está disponible en ajax_student_handler.php.');
    send_response(false, 'Error crítico: La conexión a la base de datos no está disponible.');
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_student':
        $name = trim($_POST['name'] ?? '');
        $id_num = trim($_POST['identification'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($name) || empty($id_num)) {
            send_response(false, 'Nombre e identificación son requeridos.');
        }

        try {
            // Verificar si el estudiante ya existe (activo o inactivo)
            $stmt_check = $pdo->prepare("SELECT id, deleted_at FROM students WHERE identification = ?");
            $stmt_check->execute([$id_num]);
            $existing_student = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($existing_student) {
                // Si existe y está inactivo (borrado suave), reactivarlo y actualizar sus datos
                if ($existing_student['deleted_at'] !== null) {
                    $sql_reactivate = "UPDATE students SET name = ?, phone = ?, email = ?, deleted_at = NULL WHERE id = ?";
                    $params_reactivate = [$name, $phone, $email, $existing_student['id']];
                    $stmt_reactivate = $pdo->prepare($sql_reactivate);
                    $stmt_reactivate->execute($params_reactivate);

                    if (function_exists('log_activity')) {
                        $log_details = "Estudiante reactivado y actualizado: Nombre: {$name}, ID: {$id_num}.";
                        log_activity($pdo, $current_admin_id, 'estudiante_reactivado', $existing_student['id'], 'students', $log_details);
                    }
                    send_response(true, 'Un estudiante inactivo con esta identificación ha sido reactivado y actualizado.');
                } else {
                    // Si existe y está activo, es un duplicado
                    send_response(false, 'La identificación ingresada ya pertenece a otro estudiante activo.');
                }
            } else {
                // Si no existe, crear un nuevo estudiante
                $sql_insert = "INSERT INTO students (name, identification, phone, email) VALUES (?, ?, ?, ?)";
                $params_insert = [$name, $id_num, $phone, $email];
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute($params_insert);
                $new_student_id = $pdo->lastInsertId();

                if (function_exists('log_activity')) {
                    $log_details = "Estudiante agregado: Nombre: {$name}, ID: {$id_num}.";
                    log_activity($pdo, $current_admin_id, 'estudiante_creado', $new_student_id, 'students', $log_details);
                }
                send_response(true, 'Estudiante agregado con éxito.', ['id' => $new_student_id, 'name' => $name, 'identification' => $id_num, 'phone' => $phone, 'email' => $email]);
            }
        } catch (PDOException $e) {
            error_log("Error en add_student (student): " . $e->getMessage());
            send_response(false, 'Error de base de datos al agregar estudiante.');
        }
        break;

    case 'update_student':
        $name = trim($_POST['name'] ?? '');
        $id_num = trim($_POST['identification'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

        if (empty($name) || empty($id_num)) {
            send_response(false, 'Nombre e identificación son requeridos.');
        }
        if ($student_id === 0) {
            send_response(false, 'ID de estudiante no proporcionado o no válido para actualizar.');
        }

        try {
            // Obtener datos antiguos para comparar
            $stmt_old_data = $pdo->prepare("SELECT name, identification, phone, email FROM students WHERE id = ?");
            $stmt_old_data->execute([$student_id]);
            $old_data = $stmt_old_data->fetch(PDO::FETCH_ASSOC);

            if (!$old_data) {
                send_response(false, 'Estudiante no encontrado para actualizar.');
            }

            $stmt_check_duplicate = $pdo->prepare("SELECT id FROM students WHERE identification = ? AND id != ?");
            $stmt_check_duplicate->execute([$id_num, $student_id]);
            if ($stmt_check_duplicate->fetch()) {
                send_response(false, 'La identificación ingresada ya pertenece a otro estudiante.');
            }

            $sql = "UPDATE students SET name = ?, identification = ?, phone = ?, email = ? WHERE id = ?";
            $params = [$name, $id_num, $phone, $email, $student_id];
            $stmt_action = $pdo->prepare($sql);
            $stmt_action->execute($params);

            // Registrar actividad
            if (function_exists('log_activity')) {
                $changes = [];
                if ($old_data['name'] !== $name) $changes[] = "Nombre: '{$old_data['name']}' -> '{$name}'";
                if ($old_data['identification'] !== $id_num) $changes[] = "Identificación: '{$old_data['identification']}' -> '{$id_num}'";
                if ($old_data['phone'] !== $phone) $changes[] = "Teléfono: '{$old_data['phone']}' -> '{$phone}'";
                if ($old_data['email'] !== $email) $changes[] = "Email: '{$old_data['email']}' -> '{$email}'";

                if (!empty($changes)) {
                    $log_details = "Estudiante ID {$student_id} actualizado. Cambios: " . implode(', ', $changes) . ".";
                    log_activity($pdo, $current_admin_id, 'student_updated', $student_id, 'students', $log_details);
                }
            }
            send_response(true, 'Estudiante actualizado con éxito.');

        } catch (PDOException $e) {
            error_log("Error en update_student (student): " . $e->getMessage());
            send_response(false, 'Error de base de datos al actualizar estudiante.');
        }
        break;

    case 'get_student':
        $student_id_get = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($student_id_get === 0) {
            send_response(false, 'ID de estudiante no proporcionado para get_student.');
        }
        try {
            $stmt = $pdo->prepare("SELECT id, name, identification, phone, email FROM students WHERE id = ?");
            $stmt->execute([$student_id_get]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC); 
            if ($student) {
                send_response(true, 'Estudiante encontrado.', $student); 
            } else {
                send_response(false, 'Estudiante no encontrado.');
            }
        } catch (PDOException $e) {
            error_log("Error en get_student: " . $e->getMessage()); 
            send_response(false, 'Error de base de datos al obtener estudiante.'); 
        }
        break;
    
    case 'upload_csv':
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
            send_response(false, 'Error al subir el archivo o archivo no seleccionado.');
        }

        $file_handle = fopen($_FILES['csv_file']['tmp_name'], "r");
        if ($file_handle === FALSE) {
            send_response(false, 'No se pudo abrir el archivo CSV.');
        }

        $header = fgetcsv($file_handle);
        if ($header !== ['nombre', 'identificacion', 'telefono', 'email']) {
            fclose($file_handle);
            send_response(false, 'Las cabeceras del CSV son incorrectas. Deben ser: nombre,identificacion,telefono,email');
        }

        $added = 0;
        $skipped = 0;
        $reactivated = 0;
        
        $pdo->beginTransaction();
        try {
            $stmt_check = $pdo->prepare("SELECT id, deleted_at FROM students WHERE identification = ?");
            $stmt_insert = $pdo->prepare("INSERT INTO students (name, identification, phone, email) VALUES (?, ?, ?, ?)");
            $stmt_reactivate = $pdo->prepare("UPDATE students SET name = ?, phone = ?, email = ?, deleted_at = NULL WHERE id = ?");

            while (($data = fgetcsv($file_handle)) !== FALSE) {
                if (count($data) !== 4) { continue; } // Ignorar filas malformadas

                $csv_name = trim($data[0]);
                $csv_id = trim($data[1]);
                $csv_phone = trim($data[2]);
                $csv_email = trim($data[3]);

                if (empty($csv_id)) { continue; }

                $stmt_check->execute([$csv_id]);
                $existing_student = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($existing_student) {
                    if ($existing_student['deleted_at'] !== null) {
                        // Reactivar y actualizar
                        $stmt_reactivate->execute([$csv_name, $csv_phone, $csv_email, $existing_student['id']]);
                        $reactivated++;
                        if (function_exists('log_activity')) {
                            $log_details = "Estudiante reactivado y actualizado vía CSV: Nombre: {$csv_name}, ID: {$csv_id}.";
                            log_activity($pdo, $current_admin_id, 'estudiante_reactivado_csv', $existing_student['id'], 'students', $log_details);
                        }
                    } else {
                        // Omitir duplicado activo
                        $skipped++;
                    }
                } else {
                    // Agregar nuevo
                    $stmt_insert->execute([$csv_name, $csv_id, $csv_phone, $csv_email]);
                    $new_id = $pdo->lastInsertId();
                    $added++;
                    if (function_exists('log_activity')) {
                        $log_details = "Estudiante agregado vía CSV: Nombre: {$csv_name}, ID: {$csv_id}.";
                        log_activity($pdo, $current_admin_id, 'estudiante_creado_csv', $new_id, 'students', $log_details);
                    }
                }
            }
            $pdo->commit();
            fclose($file_handle);
            
            $message = "Proceso completado. Agregados: $added. Reactivados: $reactivated. Omitidos (duplicados activos): $skipped.";
            send_response(true, $message);

        } catch (Exception $e) {
            $pdo->rollBack();
            fclose($file_handle);
            error_log("Error en carga CSV de estudiantes: " . $e->getMessage());
            send_response(false, 'Ocurrió un error durante la carga masiva.');
        }
        break;

    case 'process_csv_for_certificates':
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
            send_response(false, 'Error al subir el archivo o archivo no seleccionado.');
        }

        $file_path = $_FILES['csv_file']['tmp_name'];
        $file_handle = fopen($file_path, "r");

        if ($file_handle === false) {
            send_response(false, 'No se pudo abrir el archivo CSV.');
        }

        // Leer cabeceras y encontrar el índice de 'identificacion'
        $header = fgetcsv($file_handle);
        $id_column_index = array_search('identificacion', $header);

        if ($id_column_index === false) {
            fclose($file_handle);
            send_response(false, 'El archivo CSV debe contener una columna con la cabecera "identificacion".');
        }

        $ids_from_csv = [];
        while (($data = fgetcsv($file_handle)) !== false) {
            if (isset($data[$id_column_index]) && !empty(trim($data[$id_column_index]))) {
                $ids_from_csv[] = trim($data[$id_column_index]);
            }
        }
        fclose($file_handle);

        if (empty($ids_from_csv)) {
            send_response(false, 'No se encontraron identificaciones válidas en el archivo CSV.');
        }

        try {
            $pdo->beginTransaction();

            // 1. Buscar todos los estudiantes (activos e inactivos) que coincidan con las IDs del CSV
            $placeholders = implode(',', array_fill(0, count($ids_from_csv), '?'));
            $sql_select = "SELECT id, name, identification, deleted_at FROM students WHERE identification IN ($placeholders)";
            $stmt_select = $pdo->prepare($sql_select);
            $stmt_select->execute($ids_from_csv);
            $all_found_students = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

            $students_to_reactivate_ids = [];
            foreach ($all_found_students as $student) {
                if ($student['deleted_at'] !== null) {
                    $students_to_reactivate_ids[] = $student['id'];
                }
            }

            // 2. Reactivar los estudiantes que estaban inactivos
            $reactivated_count = 0;
            if (!empty($students_to_reactivate_ids)) {
                $placeholders_reactivate = implode(',', array_fill(0, count($students_to_reactivate_ids), '?'));
                $sql_reactivate = "UPDATE students SET deleted_at = NULL WHERE id IN ($placeholders_reactivate)";
                $stmt_reactivate = $pdo->prepare($sql_reactivate);
                $stmt_reactivate->execute($students_to_reactivate_ids);
                $reactivated_count = $stmt_reactivate->rowCount();

                // 3. Registrar la actividad para cada estudiante reactivado
                if (function_exists('log_activity')) {
                    foreach ($all_found_students as $student) {
                        if (in_array($student['id'], $students_to_reactivate_ids)) {
                            $log_details = "Estudiante reactivado automáticamente vía CSV: Nombre: {$student['name']}, ID: {$student['identification']}.";
                            log_activity($pdo, $current_admin_id, 'estudiante_reactivado_csv', $student['id'], 'students', $log_details);
                        }
                    }
                }
            }

            $pdo->commit();

            // 3. Preparar la respuesta
            $found_ids = array_column($all_found_students, 'identification');
            $not_found_ids = array_diff($ids_from_csv, $found_ids);

            $message = count($all_found_students) . " estudiante(s) encontrado(s) y añadido(s) a la selección.";
            if ($reactivated_count > 0) {
                $message .= " Se reactivaron automáticamente {$reactivated_count} estudiante(s).";
            }
            if (!empty($not_found_ids)) {
                $message .= " " . count($not_found_ids) . " identificacion(es) no fueron encontradas.";
            }

            send_response(true, $message, [
                'students_to_select' => $all_found_students,
                'errors' => array_map(function($id) { return "Identificación no encontrada: $id"; }, array_values($not_found_ids))
            ]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error en process_csv_for_certificates: " . $e->getMessage());
            send_response(false, 'Error de base de datos al procesar el CSV.');
        }
        break;

    default:
        send_response(false, 'Acción desconocida: ' . htmlspecialchars($action));
        break;
}

?>