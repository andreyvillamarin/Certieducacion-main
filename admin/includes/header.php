<?php
// admin/includes/header.php (ACTUALIZADO CON CENTRALIZACIÓN)

// CORRECCIÓN CRÍTICA: Se centraliza la carga de la configuración y la base de datos aquí.
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';

// Guardián de seguridad
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$admin_username = htmlspecialchars($_SESSION['admin_username']);
$admin_role = $_SESSION['admin_role'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Panel de Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin-style.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading">CertiEducación</div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                <a href="students.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-user-graduate me-2"></i>Estudiantes</a>
                <a href="certificates.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-certificate me-2"></i>Certificados</a>
                <?php if ($admin_role === 'superadmin'): ?>
                <a href="certificate_designer.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-paint-brush me-2"></i>Diseñar Plantilla</a>
                <?php endif; ?>
                <a href="history.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-history me-2"></i>Historial</a>
                <?php if ($admin_role === 'superadmin'): ?>
                <a href="administrators.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-users-cog me-2"></i>Administradores</a>
                 <a href="activity_log_viewer.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-clipboard-list me-2"></i>Log de Actividad</a>
                <a href="settings.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-cogs me-2"></i>Configuración</a>
                <?php endif; ?>
                <a href="profile.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-user-circle me-2"></i>Mi Perfil</a>
                <a href="2fa_setup.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-shield-alt me-2"></i>Seguridad 2FA</a>
            </div>
        </div>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user me-1"></i> <?php echo $admin_username; ?></a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown"><a class="dropdown-item" href="profile.php">Mi Perfil</a><div class="dropdown-divider"></div><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></div>
                        </li>
                    </ul>
                </div>
            </nav>
            <div class="container-fluid p-4">