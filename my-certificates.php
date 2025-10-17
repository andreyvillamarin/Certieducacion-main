<?php
// my-certificates.php

// Incluir la configuración global
require_once 'config.php';

// 1. Proteger la página: verificar si el estudiante ha iniciado sesión.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}

$student_id = $_SESSION['student_id']; // Este es el ID interno de la DB local (si se usa)
$student_name = 'Estudiante'; // Valor por defecto
$certificates = []; // Inicializar como array vacío

// --- INICIO DE LA NUEVA LÓGICA CON API EXTERNA ---
try {
    // 2. Obtener el nombre del estudiante y la identificación para la API desde la sesión
    if (isset($_SESSION['external_student_data']['name'])) {
        $student_name = htmlspecialchars($_SESSION['external_student_data']['name']);
    }

    $student_identification_for_api = $_SESSION['external_student_data']['identification'] ?? null;

    if (empty($student_identification_for_api)) {
        // Manejar el caso donde la identificación no está en la sesión (ej. redirigir o mostrar error)
        error_log("Error: Identificación del estudiante no encontrada en la sesión para la API en my-certificates.php.");
        header('Location: index.php'); // Redirigir al login si no hay identificación
        exit;
    }

    // 3. Obtener la lista de certificados desde la API externa usando la IDENTIFICACIÓN
    $api_url = API_BASE_URL . 'certificates/student.php?identification=' . urlencode($student_identification_for_api);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    // Opcional: Autenticación si es necesaria
    // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer TU_API_KEY']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $api_certificates = json_decode($response, true);
        $certificates = []; // Reinicializar para asegurar que solo se usen los de la API
        foreach ($api_certificates as $cert) {
            $certificates[] = [
                'course_name' => $cert['course']['name'],
                'issue_date' => $cert['issue_date'],
                'download_url' => $cert['pdf_url'] // La URL ya es absoluta desde la API
            ];
        }
    } else {
        // Si la API falla, no se mostrarán certificados, pero la página no morirá.
        // Se registrará el error para depuración.
        error_log("Error al obtener certificados de la API para el estudiante {$student_identification_for_api}. Código HTTP: {$http_code}. Respuesta: {$response}");
    }

} catch (Exception $e) {
    error_log('Error en my-certificates.php: ' . $e->getMessage());
    // No usamos die() para no romper la página para el usuario.
    // Simplemente se mostrará que no hay certificados.
}
// --- FIN DE LA NUEVA LÓGICA ---

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Certificados - <?php echo $student_name; ?></title>
    <link rel="icon" type="image/png" href="https://qdos.network/demos/certieducacion4/assets/img/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; align-items: flex-start; }
        .dashboard-container { max-width: 900px; width: 100%; margin: 2rem auto; }
        .dashboard-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .dashboard-header { padding: 1.5rem 2rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .dashboard-header h1 { font-size: 1.5rem; margin-bottom: 0; font-weight: 600; }
        .list-group-item { border-left: 0; border-right: 0;}\
        .list-group-item:first-child { border-top-left-radius: 0; border-top-right-radius: 0; }\
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-card">
            <div class="dashboard-header">
                <h1>Bienvenido, <?php echo $student_name; ?></h1>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
            <div class="card-body p-0">
                <div class="p-3">
                    <h5 class="mb-0">Tus Certificados</h5>
                    <p class="text-muted small">Aquí encontrarás todos los certificados que has obtenido.</p>
                </div>

                <?php if (empty($certificates)): ?>
                    <div class="alert alert-info m-3 text-center">
                        Aún no tienes certificados registrados en la plataforma.
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($certificates as $cert): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($cert['course_name']); ?></h6>
                                    <small class="text-muted">Emitido el: <?php echo date("d/m/Y", strtotime($cert['issue_date'])); ?></small>
                                </div>
                                <a href="<?php echo htmlspecialchars($cert['download_url']); ?>" class="btn btn-primary btn-sm" target="_blank" title="Ver PDF">
                                    <i class="fas fa-download"></i> Descargar PDF
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <a href="index.php" class="link-secondary"><i class="fas fa-arrow-left"></i> Volver al portal principal</a>
            </div>
        </div>
    </div>
</body>
</html>