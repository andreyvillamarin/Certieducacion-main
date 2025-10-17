<?php
// verify.php
require_once 'config.php';

// Si no se proporciona un student_id, redirigir al inicio.
if (empty($_GET['student_id'])) {
    header('Location: index.php');
    exit;
}

$student_id = filter_var($_GET['student_id'], FILTER_SANITIZE_NUMBER_INT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código - CertiEducación</title>
    <link rel="icon" type="image/png" href="https://qdos.network/demos/certieducacion/assets/img/favicon.png">
    <!-- Mismos estilos que el index -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="main-container">
        <div class="login-card">
            <div class="card-header text-center">
                <img src="assets/img/logo.png" alt="Logo Institución" class="logo-img mb-3" onerror="this.onerror=null; this.src='https://placehold.co/150x150/003366/FFFFFF?text=Logo';">
                <h1 class="card-title">Ingresa tu Código</h1>
                <p class="card-subtitle">Revisa tu SMS o Email para obtener el código de 6 dígitos.</p>
            </div>
            <div class="card-body">
                <form id="form-verify-code">
                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="verification_code" class="form-label">Código de Verificación</label>
                        <input type="text" class="form-control text-center" id="verification_code" name="verification_code" 
                               maxlength="6" pattern="\d{6}" inputmode="numeric" required
                               placeholder="------">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="btn-verify-code">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Verificar y Acceder
                    </button>
                </form>
                <!-- Mensajes de Error/Alerta -->
                <div id="alert-message" class="alert mt-3 d-none" role="alert"></div>
            </div>
            <div class="card-footer text-center">
                <a href="index.php" class="link-secondary"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/verify.js"></script>
</body>
</html>