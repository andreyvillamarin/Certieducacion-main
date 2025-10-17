<?php
// admin/profile.php (Actualizado)
$page_title = 'Mi Perfil';
include 'includes/header.php'; // Incluye el guardián de seguridad y la UI base
require_once '../includes/database.php';

$admin_id = $_SESSION['admin_id'];

// Variables para mensajes
$success_message = '';
$error_message = '';

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_username = trim($_POST['new_username']);
    
    // Obtener los datos actuales del admin para verificar la contraseña
    $stmt = $pdo->prepare("SELECT username, password, role FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    // Verificar la contraseña actual
    if (!password_verify($current_password, $admin['password'])) {
        $error_message = 'La contraseña actual es incorrecta.';
    } else {
        // La contraseña actual es correcta, proceder con las actualizaciones
        
        $params = [];
        $sql = "UPDATE admins SET username = ?";
        $params[] = $new_username;

        // SOLO el superadmin puede cambiar su contraseña desde aquí
        if ($admin['role'] === 'superadmin') {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    $error_message = 'Las nuevas contraseñas no coinciden.';
                } elseif (strlen($new_password) < 6) {
                    $error_message = 'La nueva contraseña debe tener al menos 6 caracteres.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql .= ", password = ?";
                    $params[] = $hashed_password;
                }
            }
        }
        
        // Si no hay errores, ejecutar las actualizaciones
        if (empty($error_message)) {
            $sql .= " WHERE id = ?";
            $params[] = $admin_id;

            $stmt_update = $pdo->prepare($sql);
            $stmt_update->execute($params);

            // Actualizar los datos de la sesión
            $_SESSION['admin_username'] = $new_username;

            $success_message = 'Tu perfil ha sido actualizado correctamente.';
        }
    }
}
?>

<h1 class="mt-4">Mi Perfil</h1>
<p>Actualiza tu información de la cuenta.</p>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Información de la Cuenta</h5></div>
            <div class="card-body">
                <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
                <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

                <form action="profile.php" method="POST">
                    <div class="mb-3">
                        <label for="new_username" class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" id="new_username" name="new_username" value="<?php echo $admin_username; ?>" required>
                    </div>
                    
                    <?php if ($admin_role === 'superadmin'): // Mostrar campos de contraseña SÓLO al superadmin ?>
                    <hr>
                    <p class="text-muted">Para cambiar tu contraseña, completa los siguientes campos. De lo contrario, déjalos en blanco.</p>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-1"></i> Solo un superadministrador puede modificar tu contraseña desde la sección "Administradores".
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña Actual (Obligatoria para guardar cambios)</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>