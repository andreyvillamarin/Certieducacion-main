<?php
// admin/index.php
// Usamos un config.php relativo a la carpeta raíz del proyecto.
require_once '../config.php'; 
require_once '../includes/database.php';

// Si el admin ya ha iniciado sesión, redirigirlo al dashboard.
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = 'Por favor, ingresa tu usuario y contraseña.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role, 2fa_secret FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            // Verificar si el usuario existe y si la contraseña es correcta
            if ($admin && password_verify($password, $admin['password'])) {
                // Si 2FA está activado, pedir el código
                if (!empty($admin['2fa_secret'])) {
                    $_SESSION['2fa_admin_id'] = $admin['id']; // ID temporal para verificar 2FA
                    header('Location: verify_2fa.php');
                    exit;
                } else {
                    // Si 2FA no está activado, iniciar sesión directamente
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_role'] = $admin['role'];
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                // Credenciales incorrectas
                $error_message = 'El usuario o la contraseña son incorrectos.';
            }
        } catch (PDOException $e) {
            error_log('Error en login de admin: ' . $e->getMessage());
            $error_message = 'Ocurrió un error en el servidor. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso de Administración</title>
    <!-- Usaremos el mismo estilo base para consistencia -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Cargamos un CSS específico para el admin -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin-style.css">
</head>
<body>
    <div class="main-container">
        <div class="login-card">
            <div class="card-header text-center">
                <h1 class="card-title">Panel de Control</h1>
                <p class="card-subtitle">Acceso exclusivo para administradores</p>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <form action="index.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
