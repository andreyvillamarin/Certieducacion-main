<?php
// validate-certificate.php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validar Certificado - CertiEducación</title>
    <link rel="icon" type="image/png" href="https://qdos.network/demos/certieducacion/assets/img/favicon.png">
    <!-- Estilos y fuentes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        #qr-reader {
            width: 100%;
            max-width: 500px;
            margin: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        #qr-reader-results { font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="main-container" style="max-width: 600px;">
        <div class="login-card">
            <div class="card-header text-center">
                 <img src="assets/img/logo.png" alt="Logo Institución" class="logo-img mb-3" onerror="this.onerror=null; this.src='https://placehold.co/150x150/003366/FFFFFF?text=Logo';">
                <h1 class="card-title">Validación de Autenticidad</h1>
                <p class="card-subtitle">Verifica si un certificado es auténtico.</p>
            </div>
            <div class="card-body">
                <form id="form-validate-code">
                    <div class="mb-3">
                        <label for="validation_code" class="form-label">Código Alfanumérico</label>
                        <input type="text" class="form-control" id="validation_code" placeholder="Ingresa el código del certificado" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="btn btn-primary w-100" id="btn-validate">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Validar Código
                    </button>
                </form>
                <div class="text-center my-3">
                    <span class="text-muted">O</span>
                </div>
                <button class="btn btn-outline-secondary w-100" id="btn-scan-qr">
                    <i class="fas fa-qrcode"></i> Escanear Código QR
                </button>
                <div id="qr-reader" class="mt-3" style="display: none;"></div>
                
                <div id="validation-result" class="mt-4"></div>
            </div>
            <div class="card-footer text-center">
                <a href="index.php" class="link-secondary"><i class="fas fa-arrow-left"></i> Volver al portal de estudiantes</a>
            </div>
        </div>
    </div>

    <!-- JS de la biblioteca de escaneo QR -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <!-- JS personalizado -->
    <script src="assets/js/validator.js"></script>
</body>
</html>