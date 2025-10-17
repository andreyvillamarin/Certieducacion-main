<?php
// admin/2fa_setup.php

require_once '../config.php';
require_once '../includes/database.php';
require_once ROOT_PATH . '/libs/Algorithm.php'; // Dependencia que faltaba
require_once ROOT_PATH . '/libs/TwoFactorAuth.php';
require_once ROOT_PATH . '/libs/CustomQRCodeProvider.php'; // Usar nuestro proveedor

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Algorithm;

// Redirigir si no es admin o no ha iniciado sesión
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$message = '';
$qr_code_url = '';
$secret = '';

// 1. Instanciar nuestro proveedor de QR
$qrProvider = new CustomQRCodeProvider();

// 2. Instanciar TwoFactorAuth con el proveedor y todos los parámetros por defecto para forzar el tipo correcto
$tfa = new TwoFactorAuth($qrProvider, 'CertiEducacion Admin', 6, 30, Algorithm::Sha1);


// Obtener el nombre de usuario del admin
try {
    $stmt = $pdo->prepare("SELECT username, 2fa_secret FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    if (!$admin) {
        header('Location: logout.php');
        exit;
    }
    $admin_username = $admin['username'];
    $current_2fa_secret = $admin['2fa_secret'];
} catch (PDOException $e) {
    error_log('Error al obtener datos del admin para 2FA: ' . $e->getMessage());
    die('Error interno.');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'generate_secret') {
            $secret = $tfa->createSecret();
            
            // 3. Simplificar la generación del QR Code
            $qr_code_url = $tfa->getQRCodeImageAsDataUri('CertiEducacion:' . $admin_username, $secret);
            
            $message = 'Escanea este código QR con tu aplicación de autenticación (ej. Google Authenticator) y luego ingresa un código para confirmar.';
        } elseif ($_POST['action'] === 'confirm_2fa') {
            $entered_secret = $_POST['secret'];
            $entered_code = $_POST['code'];
            $password_confirm = $_POST['password_confirm'];

            // Verificar la contraseña del admin antes de activar 2FA
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin_data = $stmt->fetch();

            if (!password_verify($password_confirm, $admin_data['password'])) {
                $message = '<div class="alert alert-danger">Contraseña incorrecta.</div>';
            } elseif ($tfa->verifyCode($entered_secret, $entered_code)) {
                // Guardar el secreto en la base de datos
                $stmt = $pdo->prepare("UPDATE admins SET 2fa_secret = ? WHERE id = ?");
                $stmt->execute([$entered_secret, $admin_id]);
                $message = '<div class="alert alert-success">Autenticación de dos factores activada exitosamente.</div>';
                $current_2fa_secret = $entered_secret; // Actualizar para mostrar estado
            } else {
                $message = '<div class="alert alert-danger">Código de verificación incorrecto. Inténtalo de nuevo.</div>';
            }
        } elseif ($_POST['action'] === 'disable_2fa') {
            $password_confirm = $_POST['password_confirm'];

            // Verificar la contraseña del admin antes de desactivar 2FA
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin_data = $stmt->fetch();

            if (!password_verify($password_confirm, $admin_data['password'])) {
                $message = '<div class="alert alert-danger">Contraseña incorrecta.</div>';
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET 2fa_secret = NULL WHERE id = ?");
                $stmt->execute([$admin_id]);
                $message = '<div class="alert alert-success">Autenticación de dos factores desactivada.</div>';
                $current_2fa_secret = NULL; // Actualizar para mostrar estado
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar 2FA - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin-style.css">
    <style>
        .qr-code-container {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .qr-code-container img {
            max-width: 200px;
            height: auto;
            border: 1px solid #ddd;
            padding: 5px;
            background-color: #fff;
        }
        .secret-display {
            font-family: monospace;
            background-color: #e9ecef;
            padding: 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 10px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="login-card">
            <div class="card-header text-center">
                <h1 class="card-title">Configurar Autenticación de Dos Factores (2FA)</h1>
                <p class="card-subtitle">Protege tu cuenta de administrador</p>
            </div>
            <div class="card-body">
                <?php if (!empty($message)) echo $message; ?>

                <?php if ($current_2fa_secret): ?>
                    <div class="alert alert-info text-center">
                        La autenticación de dos factores está <strong>ACTIVA</strong> para tu cuenta.
                    </div>
                    <form action="2fa_setup.php" method="POST">
                        <input type="hidden" name="action" value="disable_2fa">
                        <div class="mb-3">
                            <label for="password_confirm_disable" class="form-label">Confirma tu Contraseña de Administrador para Desactivar</label>
                            <input type="password" class="form-control" id="password_confirm_disable" name="password_confirm" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">Desactivar 2FA</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        La autenticación de dos factores está <strong>INACTIVA</strong> para tu cuenta.
                    </div>
                    <form action="2fa_setup.php" method="POST">
                        <input type="hidden" name="action" value="generate_secret">
                        <button type="submit" class="btn btn-primary w-100">Generar Secreto 2FA</button>
                    </form>

                    <?php if ($secret && $qr_code_url): ?>
                        <hr>
                        <div class="qr-code-container">
                            <p>1. Escanea este código QR con tu aplicación de autenticación (ej. Google Authenticator):</p>
                            <img src="<?php echo $qr_code_url; ?>" alt="QR Code">
                            <p class="mt-3">O ingresa el secreto manualmente:</p>
                            <p class="secret-display"><strong>Secreto:</strong> <?php echo $secret; ?></p>
                        </div>
                        <hr>
                        <form action="2fa_setup.php" method="POST">
                            <input type="hidden" name="action" value="confirm_2fa">
                            <input type="hidden" name="secret" value="<?php echo $secret; ?>">
                            <div class="mb-3">
                                <label for="code" class="form-label">2. Ingresa el Código de tu Aplicación</label>
                                <input type="text" class="form-control" id="code" name="code" required pattern="[0-9]{6}" title="Ingresa un código de 6 dígitos">
                            </div>
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">3. Confirma tu Contraseña de Administrador</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Confirmar y Activar 2FA</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>