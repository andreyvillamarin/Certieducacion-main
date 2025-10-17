<?php
// admin/ajax_admin_handler.php

// Establecer la ruta raíz del proyecto de forma robusta
// __DIR__ es la ruta al directorio actual (admin)
// dirname(__DIR__) sube un nivel a la raíz del proyecto (asumiendo que es 'certieducacion')
$project_root = dirname(__DIR__);

// Incluir config.php primero, que debería definir DB_HOST y otras constantes
// Asegúrate que esta ruta $project_root . '/config.php' sea correcta para tu estructura.
if (file_exists($project_root . '/config.php')) {
    require_once $project_root . '/config.php';
} else {
    // Manejar el error de alguna manera si config.php no se encuentra
    // Esto es crucial para evitar errores posteriores si las constantes no se definen.
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Error crítico: El archivo de configuración principal no se encuentra.']);
    exit;
}

// Ahora incluir database.php, que puede depender de las constantes de config.php
// Asegúrate que esta ruta $project_root . '/includes/database.php' sea correcta.
if (file_exists($project_root . '/includes/database.php')) {
    require_once $project_root . '/includes/database.php';
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Error crítico: El archivo de base de datos no se encuentra.']);
    exit;
}

// Establecer cabecera JSON con UTF-8
header('Content-Type: application/json; charset=utf-8');

// Iniciar sesión si no está iniciada, ya que se accede a $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Seguridad: solo superadmin puede ejecutar estas acciones
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Acción no autorizada.']);
    exit;
}

function send_response($success, $message = '', $data = []) {
    // Intentar asegurar que los datos estén en UTF-8
    // Esto es una ayuda, pero la fuente de los datos (BD) debe ser consistente
    array_walk_recursive($data, function (&$item) {
        if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
            $item = mb_convert_encoding($item, 'UTF-8');
        }
    });
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

$action = $_POST['action'] ?? '';

// Verificar si $pdo se inicializó correctamente en database.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    send_response(false, 'Error crítico: La conexión a la base de datos no está disponible.');
}

switch ($action) {
    case 'add_admin':
    case 'update_admin':
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; 
        $role = $_POST['role'] ?? '';
        $admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;

        if (empty($username) || empty($role)) {
            send_response(false, 'Nombre de usuario y rol son requeridos.');
        }
        if ($action === 'add_admin' && empty($password)) {
            send_response(false, 'La contraseña es requerida para agregar un nuevo administrador.');
        }
        if (!in_array($role, ['admin', 'superadmin'])) {
            send_response(false, 'Rol no válido.');
        }

        try {
            $stmt_check_duplicate = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
            $stmt_check_duplicate->execute([$username, $admin_id]);
            if ($stmt_check_duplicate->fetch()) {
                send_response(false, 'El nombre de usuario ya existe.');
            }

            if ($action === 'add_admin') {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = $pdo->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
                $stmt_insert->execute([$username, $hashed_password, $role]);
                send_response(true, 'Administrador agregado correctamente.');
            } else { // update_admin
                if ($admin_id === 0) {
                    send_response(false, 'ID de administrador no válido para actualizar.');
                }
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_update = $pdo->prepare("UPDATE admins SET username = ?, password = ?, role = ? WHERE id = ?");
                    $stmt_update->execute([$username, $hashed_password, $role, $admin_id]);
                } else {
                    $stmt_update = $pdo->prepare("UPDATE admins SET username = ?, role = ? WHERE id = ?");
                    $stmt_update->execute([$username, $role, $admin_id]);
                }
                send_response(true, 'Administrador actualizado correctamente.');
            }
        } catch (PDOException $e) {
            error_log("Error en $action (admin): " . $e->getMessage());
            send_response(false, 'Error en la base de datos al procesar la solicitud del administrador.');
        }
        break;

    case 'get_admin':
        $admin_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($admin_id === 0) {
            send_response(false, 'ID de administrador no proporcionado o no válido.');
        }

        try {
            $stmt = $pdo->prepare("SELECT id, username, role FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin_data) {
                send_response(true, 'Administrador encontrado.', $admin_data);
            } else {
                send_response(false, 'Administrador no encontrado.');
            }
        } catch (PDOException $e) {
            error_log("Error en get_admin: " . $e->getMessage());
            send_response(false, 'Error en la base de datos al obtener datos del administrador.');
        }
        break;

    default:
        send_response(false, 'Acción no válida o no especificada: ' . htmlspecialchars($action));
        break;
}
?>