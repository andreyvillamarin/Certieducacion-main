<?php
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error del Servidor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="main-container">
        <div class="login-card">
            <div class="card-header text-center">
                <h1 class="card-title">Error 500</h1>
                <p class="card-subtitle">Error Interno del Servidor</p>
            </div>
            <div class="card-body text-center">
                <p>Ocurrió un error inesperado. Por favor, inténtelo de nuevo más tarde.</p>
                <a href="/" class="btn btn-primary">Volver al Inicio</a>
            </div>
        </div>
    </div>
</body>
</html>
