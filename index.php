<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Certificados - CertiEducación</title>
    <link rel="icon" type="image/png" href="https://qdos.network/demos/certieducacion4/assets/img/favicon.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

    <div class="main-container">
        <div class="login-card">
            <div class="card-header text-center">
                <img src="assets/img/logo.png" alt="Logo Institución" class="logo-img mb-3"
                     onerror="this.onerror=null; this.src='https://placehold.co/150x150/003366/FFFFFF?text=Logo';">
                <h1 class="card-title">Portal de Certificados</h1>
                <p class="card-subtitle">Consulta y descarga tus certificados de estudio.</p>
            </div>
            <div class="card-body">
                
                <!-- Paso 1: Ingresar Identificación -->
                <div id="step-1-identification">
                    <form id="form-check-id">
                        <div class="mb-3">
                            <label for="identification" class="form-label">Número de Identificación</label>
                            <input type="text" class="form-control" id="identification" name="identification" placeholder="Ingresa tu cédula" required>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="btn btn-primary w-100" id="btn-check-id">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Consultar
                        </button>
                    </form>
                </div>

                <!-- Paso 2: Escoger Método de Verificación -->
                <div id="step-2-verification" class="d-none">
                    <h5 class="text-center mb-3">Verificación de dos pasos</h5>
                    <p class="text-center small">Para proteger tu información, por favor elige un método para enviarte un código de seguridad.</p>
                    <form id="form-send-code">
                        <input type="hidden" id="student-id-hidden" name="student_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div id="verification-options">
                            <!-- Las opciones de SMS y Email se insertarán aquí con JavaScript -->
                        </div>
                        
                        <div id="no-options-message" class="alert alert-warning text-center d-none">
                            No hemos encontrado un teléfono o email asociado a esta identificación. Por favor, contacta a soporte.
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-3" id="btn-send-code">
                             <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Enviar Código
                        </button>
                    </form>
                </div>

                <!-- Mensajes de Error/Alerta -->
                <div id="alert-message" class="alert mt-3 d-none" role="alert"></div>

            </div>
            <div class="card-footer text-center">
                <a href="validate-certificate.php" class="link-secondary">Validar un certificado con QR o Código</a>
                <hr>
                <p class="small mb-2">¿Tus datos de contacto son incorrectos?</p>
                <a href="https://wa.me/<?php echo WHATSAPP_SUPPORT_NUMBER; ?>?text=Hola,%20necesito%20actualizar%20mis%20datos%20en%20el%20portal%20de%20certificados." target="_blank" class="btn btn-success btn-sm">
                    <i class="fab fa-whatsapp"></i> Actualizar mis datos
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
