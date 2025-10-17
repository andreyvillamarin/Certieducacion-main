<?php
// includes/logger.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Registra una actividad en la tabla activity_log.
 *
 * @param PDO $pdo La instancia de conexión PDO.
 * @param int|null $user_id ID del usuario que realiza la acción. Puede ser null si es una acción del sistema.
 * @param string $action_type Tipo de acción (ej: 'certificate_created', 'student_updated').
 * @param int|null $target_id ID del objeto afectado (ej: ID del certificado, ID del estudiante).
 * @param string|null $target_table Tabla a la que pertenece el objeto afectado (ej: 'certificates', 'students').
 * @param string|null $details Detalles adicionales sobre la acción.
 * @return bool True si el log se guardó correctamente, False en caso contrario.
 */
function log_activity(PDO $pdo, $user_id, string $action_type, $target_id = null, $target_table = null, $details = null): bool {
    try {
        $sql = "INSERT INTO activity_log (user_id, action_type, target_id, target_table, details) 
                VALUES (:user_id, :action_type, :target_id, :target_table, :details)";
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':user_id', $user_id, $user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':action_type', $action_type, PDO::PARAM_STR);
        $stmt->bindParam(':target_id', $target_id, $target_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':target_table', $target_table, $target_table === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, $details === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        return $stmt->execute();
    } catch (PDOException $e) {
        // En un entorno de producción, podrías querer loguear este error a un archivo en lugar de mostrarlo.
        error_log("Error al registrar actividad: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene el nombre de usuario del administrador actual.
 * Útil para los detalles del log si no se quiere solo el ID.
 *
 * @param PDO $pdo La instancia de conexión PDO.
 * @return string|null El nombre de usuario o null si no está logueado o no se encuentra.
 */
function get_current_admin_username(PDO $pdo): ?string {
    if (isset($_SESSION['admin_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = :admin_id");
            $stmt->bindParam(':admin_id', $_SESSION['admin_id'], PDO::PARAM_INT);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            return $admin['username'] ?? null;
        } catch (PDOException $e) {
            error_log("Error al obtener nombre de admin para log: " . $e->getMessage());
            return null;
        }
    }
    return null;
}

?>
