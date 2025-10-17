<?php
// admin/dashboard.php
$page_title = 'Dashboard'; // Definimos el título de la página
include 'includes/header.php'; // Incluimos el header seguro
require_once '../includes/database.php'; // Incluimos la conexión a la BD para futuras estadísticas

// --- Aquí podrías agregar consultas para mostrar estadísticas ---
// Ejemplo: Contar total de estudiantes
$total_students = $pdo->query('SELECT count(*) FROM students')->fetchColumn();
// Ejemplo: Contar total de certificados
$total_certificates = $pdo->query('SELECT count(*) FROM certificates')->fetchColumn();

?>

<h1 class="mt-4 mb-4">Dashboard</h1>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Estudiantes Registrados</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_students; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Certificados Emitidos</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_certificates; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-certificate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <h4 class="alert-heading">¡Bienvenido al Panel de Control!</h4>
    <p>Desde aquí podrás gestionar todos los aspectos de la plataforma. Usa el menú de la izquierda para navegar entre las diferentes secciones.</p>
</div>


<?php include 'includes/footer.php'; // Incluimos el footer ?>
