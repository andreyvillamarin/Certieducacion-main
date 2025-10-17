<?php
// admin/verify_2fa.php
require_once '../config.php';
require_once '../includes/database.php';

// Cargar la librería 2FA y TODAS sus dependencias
require_once ROOT_PATH . '/libs/Algorithm.php';
require_once ROOT_PATH . '/libs/TwoFactorAuth.php';
require_once ROOT_PATH . '/libs/CustomQRCodeProvider.php'; // Necesario para el constructor

use RobThree\Auth\TwoFactorAuth; // Correct namespace

// Si no hay un ID de admin pendiente de 2FA, redirigir al login.
if (!isset($_SESSION['2fa_admin_id'])) {
    header('Location: index.php');
    exit;
}

$error_message = '';
$admin_id = $_SESSION['2fa_admin_id'];

// Obtener el secreto 2FA del admin
try {
    $stmt = $pdo->prepare("SELECT username, role, 2fa_secret FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    if (!$admin || empty($admin['2fa_secret'])) {
        session_destroy();
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Error al obtener secreto 2FA: ' . $e->getMessage());
    die('Error interno del servidor.');
}

// Procesar el formulario de verificación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['2fa_code']);

    if (empty($code)) {
        $error_message = 'Por favor, ingresa el código de verificación.';
    } else {
        // Instanciar correctamente, incluyendo el proveedor de QR que el constructor necesita
        $qrProvider = new CustomQRCodeProvider();
        $tfa = new TwoFactorAuth($qrProvider, 'CertiEducacion Admin');
        
        if ($tfa->verifyCode($admin['2fa_secret'], $code)) {
            // Código correcto. Finalizar el inicio de sesión.
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin_id;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            unset($_SESSION['2fa_admin_id']);

            header('Location: dashboard.php');
            exit;
        } else {
            $error_message = 'El código de verificación es incorrecto.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Dos Factores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin-style.css">
</head>
<body>
    <div class="main-container">
        <div class="login-card">
            <div class="card-header text-center">
                <h1 class="card-title">Verificación de Dos Factores</h1>
                <p class="card-subtitle">Ingresa el código de tu aplicación de autenticación</p>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <form action="verify_2fa.php" method="POST">
                    <div class="mb-3">
                        <label for="2fa_code" class="form-label">Código de 6 dígitos</label>
                        <input type="text" class="form-control" id="2fa_code" name="2fa_code" required pattern="[0-9]{6}" maxlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Verificar e Ingresar</button>
                </form>
            </div>
            <div class="card-footer text-center">
                <a href="logout.php">Cancelar e ir al inicio</a>
            </div>
        </div>
    </div>
</body>
</html>
