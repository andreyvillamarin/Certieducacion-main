<?php
// admin/delete_certificate.php
// CORRECCIÓN: Se elimina la definición local de ROOT_PATH para usar la global de config.php
include 'includes/header.php'; // Este header ya debe incluir config.php
require_once ROOT_PATH . '/includes/logger.php'; // Ahora ROOT_PATH será correcto

// Ensure only admin access (assuming header.php or a session check does this)
// if (!is_admin()) {
//     header('Location: login.php'); // Or some other appropriate action
//     exit;
// }

$deleted_count = 0;
$error_count = 0;
$messages = [];

// --- Function to delete a single certificate ---
function deleteSingleCertificate($pdo, $certificate_id) {
    global $deleted_count, $error_count;

    // Validate ID
    if (!filter_var($certificate_id, FILTER_VALIDATE_INT)) {
        $error_count++;
        return "ID de certificado inválido.";
    }
    $certificate_id = intval($certificate_id);

    try {
        $pdo->beginTransaction();

        // 1. Fetch certificate details for logging and file deletion
        $stmt = $pdo->prepare("SELECT c.pdf_path, c.course_name, s.name as student_name, s.identification as student_identification
                               FROM certificates c
                               JOIN students s ON c.student_id = s.id
                               WHERE c.id = ?");
        $stmt->execute([$certificate_id]);
        $certificate_data = $stmt->fetch();

        if (!$certificate_data) {
            $pdo->rollBack();
            $error_count++;
            return "Certificado no encontrado (ID: $certificate_id).";
        }

        $pdf_file_path = dirname(__DIR__) . '/' . $certificate_data['pdf_path']; // Ajuste de ruta para que sea desde la raíz del proyecto.
                                                                             // ROOT_PATH en este contexto es /admin, así que dirname(__DIR__) es la raíz.

        // 2. Cambiar DELETE por UPDATE para borrado suave
        $stmt_soft_delete = $pdo->prepare("UPDATE certificates SET deleted_at = NOW() WHERE id = ?");
        $soft_delete_success = $stmt_soft_delete->execute([$certificate_id]);

        if ($soft_delete_success) {
            // 3. NO eliminar el archivo PDF físico.
            // El archivo se conserva para el historial.

            $pdo->commit();
            $deleted_count++;

            // Registrar actividad de borrado suave
            $admin_id = $_SESSION['admin_id'] ?? null;
            $log_details = "Certificado desactivado (borrado suave): ID {$certificate_id}, Estudiante: {$certificate_data['student_name']} ({$certificate_data['student_identification']}), Curso: {$certificate_data['course_name']}.";
            log_activity($pdo, $admin_id, 'certificado_desactivado', $certificate_id, 'certificates', $log_details);

            return "Certificado ID $certificate_id desactivado exitosamente. El registro y el archivo PDF se conservan.";

        } else {
            $pdo->rollBack();
            $error_count++;
            return "Error al desactivar el certificado ID $certificate_id en la base de datos.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_count++;
        error_log("Error PDO al eliminar certificado ID $certificate_id: " . $e->getMessage()); // Loguear el error real de PDO
        return "Error de base de datos al eliminar el certificado ID $certificate_id.";
    }
}

// --- Function to restore a single certificate ---
function restoreSingleCertificate($pdo, $certificate_id) {
    // Validate ID
    if (!filter_var($certificate_id, FILTER_VALIDATE_INT)) {
        return "ID de certificado inválido.";
    }
    $certificate_id = intval($certificate_id);

    try {
        $pdo->beginTransaction();

        // 1. Fetch certificate and check if student is active
        $stmt = $pdo->prepare("SELECT c.id, c.course_name, s.name as student_name, s.identification as student_identification, s.deleted_at as student_deleted_at
                               FROM certificates c
                               JOIN students s ON c.student_id = s.id
                               WHERE c.id = ?");
        $stmt->execute([$certificate_id]);
        $certificate_data = $stmt->fetch();

        if (!$certificate_data) {
            $pdo->rollBack();
            return "Certificado no encontrado (ID: $certificate_id).";
        }

        // 2. Prevent restoring a certificate if the student is deleted
        if ($certificate_data['student_deleted_at'] !== null) {
            $pdo->rollBack();
            return "No se puede reactivar el certificado porque el estudiante ({$certificate_data['student_name']}) está desactivado.";
        }

        // 3. Update certificate to set deleted_at to NULL
        $stmt_restore = $pdo->prepare("UPDATE certificates SET deleted_at = NULL WHERE id = ?");
        $restore_success = $stmt_restore->execute([$certificate_id]);

        if ($restore_success && $stmt_restore->rowCount() > 0) {
            $pdo->commit();
            
            // Registrar actividad
            $admin_id = $_SESSION['admin_id'] ?? null;
            $log_details = "Certificado reactivado: ID {$certificate_id}, Estudiante: {$certificate_data['student_name']}, Curso: {$certificate_data['course_name']}.";
            log_activity($pdo, $admin_id, 'certificado_reactivado', $certificate_id, 'certificates', $log_details);

            return "Certificado ID $certificate_id reactivado exitosamente.";
        } else {
            $pdo->rollBack();
            return "No se pudo reactivar el certificado ID $certificate_id. Es posible que ya estuviera activo.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error PDO al reactivar certificado ID $certificate_id: " . $e->getMessage());
        return "Error de base de datos al reactivar el certificado ID $certificate_id.";
    }
}

// --- Handle GET requests (delete or restore) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $certificate_id = $_GET['id'];
    $action = $_GET['action'] ?? 'delete'; // Default to delete for backward compatibility

    if ($action === 'restore') {
        $message = restoreSingleCertificate($pdo, $certificate_id);
        // A simple success/error check based on the message content
        $is_success = strpos(strtolower($message), 'exitosamente') !== false;
        header('Location: history.php?message=' . urlencode($message) . '&type=' . ($is_success ? 'success' : 'error'));
    } else {
        $message = deleteSingleCertificate($pdo, $certificate_id);
        if ($deleted_count > 0) {
            header('Location: history.php?message=' . urlencode($message) . '&type=success');
        } else {
            header('Location: history.php?message=' . urlencode($message) . '&type=error');
        }
    }
    exit;
}

// --- Handle Bulk Deletion (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (isset($_POST['certificate_ids']) && is_array($_POST['certificate_ids'])) {
        $certificate_ids = $_POST['certificate_ids'];
        if (empty($certificate_ids)) {
            header('Location: history.php?message=' . urlencode('No se seleccionaron certificados para eliminar.') . '&type=info');
            exit;
        }

        foreach ($certificate_ids as $id) {
            $result_message = deleteSingleCertificate($pdo, $id);
            // Store individual messages if needed, or just rely on counts
            // For simplicity, we'll just use counts for the final message
        }

        $final_message = "";
        if ($deleted_count > 0) {
            $final_message .= "$deleted_count certificado(s) eliminado(s) exitosamente. ";
        }
        if ($error_count > 0) {
            $final_message .= "$error_count error(es) al eliminar certificados.";
        }

        if ($deleted_count > 0 && $error_count == 0) {
            header('Location: history.php?message=' . urlencode($final_message) . '&type=success');
        } elseif ($deleted_count > 0 && $error_count > 0) {
            header('Location: history.php?message=' . urlencode($final_message) . '&type=warning'); // Partial success
        } elseif ($error_count > 0) {
            header('Location: history.php?message=' . urlencode($final_message ?: 'No se pudieron eliminar los certificados seleccionados.') . '&type=error');
        } else { // Should not happen if IDs were selected but nothing processed
             header('Location: history.php?message=' . urlencode('No se procesó ningún certificado.') . '&type=info');
        }
        exit;

    } else {
        header('Location: history.php?message=' . urlencode('No se seleccionaron certificados para eliminar.') . '&type=info');
        exit;
    }
}

// If accessed directly without proper parameters or method
header('Location: history.php?message=' . urlencode('Acción no válida.') . '&type=error');
exit;
?>
