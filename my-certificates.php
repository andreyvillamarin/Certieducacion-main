<?php
// my-certificates.php

// Incluir la configuración global (importante que sea lo primero)
require_once 'config.php'; // O la ruta correcta a config.php

require_once 'includes/database.php';

// 1. Proteger la página: verificar si el estudiante ha iniciado sesión.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['student_id'])) {
    // Si no ha iniciado sesión, redirigir al inicio.
    header('Location: index.php');
    exit;
}

$student_id = $_SESSION['student_id'];

try {
    // 2. Obtener los datos del estudiante (nombre)
    $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        // En caso de que el ID en sesión sea inválido (muy raro), cerrar sesión.
        header('Location: logout.php');
        exit;
    }
    $student_name = htmlspecialchars($student['name']);

    // 3. Obtener la lista de certificados ACTIVOS del estudiante
    $stmt = $pdo->prepare("SELECT course_name, issue_date, pdf_path FROM certificates WHERE student_id = ? AND deleted_at IS NULL ORDER BY issue_date DESC");
    $stmt->execute([$student_id]);
    $certificates = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Error en my-certificates.php: ' . $e->getMessage());
    die('Ocurrió un error al cargar tus datos. Por favor, intenta de nuevo más tarde.');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Certificados - <?php echo $student_name; ?></title>
    <link rel="icon" type="image/png" href="https://qdos.network/demos/certieducacion/assets/img/favicon.png">
    <!-- Mismos estilos que el index -->
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
        .list-group-item { border-left: 0; border-right: 0;}
        .list-group-item:first-child { border-top-left-radius: 0; border-top-right-radius: 0; }
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
                                <a href="<?php echo htmlspecialchars($cert['pdf_path']); ?>" class="btn btn-primary btn-sm" download>
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
