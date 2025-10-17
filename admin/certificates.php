<?php
// admin/certificates.php
$page_title = 'Generación de Certificados';
$page_specific_js = 'js/certificates.js'; 
include 'includes/header.php';
// No es necesario require_once '../includes/database.php'; aquí si ya está en header.php
// Si no está en header.php, entonces sí se necesita, asegurando la ruta correcta:
// if (!isset($pdo)) { // Para evitar re-incluir si header.php ya lo hizo.
//    require_once dirname(__DIR__) . '/config.php'; // Asumiendo que config.php define ROOT_PATH o es la raíz
//    require_once ROOT_PATH . '/includes/database.php'; 
// }

// Obtener la lista de estudiantes ACTIVOS para el selector
$stmt_students = $pdo->query("SELECT id, name, identification FROM students WHERE deleted_at IS NULL ORDER BY name ASC");
$all_students = $stmt_students->fetchAll();

$notification = '';
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

$stmt_certs = $pdo->query("SELECT c.id, c.course_name, s.name as student_name, c.pdf_path FROM certificates c JOIN students s ON c.student_id = s.id ORDER BY c.id DESC LIMIT 20");
$generated_certificates = $stmt_certs->fetchAll();
?>

<h1 class="mt-4">Generación de Certificados</h1>
<p>Crea y administra los certificados para los estudiantes.</p>

<?php if (!empty($notification)): ?>
<div class="alert alert-<?php echo htmlspecialchars($notification['type']); ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($notification['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Columna para Generación -->
    <div class="col-lg-6 mb-4"> 
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-award me-2"></i>Generar Certificados</h5></div>
            <div class="card-body">
                <form action="generate_certificate_handler.php" method="POST" id="generateCertForm">
                    <div class="mb-3">
                        <label for="course_name" class="form-label">Nombre del Curso / Programa</label>
                        <input type="text" class="form-control" id="course_name" name="course_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="duration" class="form-label">Intensidad Horaria</label>
                        <input type="number" class="form-control" id="duration" name="duration" placeholder="Ej: 40" required>
                    </div>
                    <div class="mb-3">
                        <label for="issue_date" class="form-label">Fecha de Emisión</label>
                        <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <input type="hidden" name="signature_file" value="director.png">

                    <hr>
                    <h6 class="mb-3">Opción 1: Selección Manual de Estudiantes</h6>
                    
                    <div class="row gx-3">
                        <!-- Columna de Estudiantes Disponibles -->
                        <div class="col-md-6">
                            <label class="form-label">Estudiantes Disponibles (<span id="availableCountDisplay">0</span>)</label>
                            <input type="text" id="studentSearchInput" class="form-control form-control-sm mb-2" placeholder="Buscar estudiante...">
                            <div id="availableStudentsListContainer" class="list-group overflow-auto" style="max-height: 250px; border: 1px solid #dee2e6; padding: 5px;">
                                <?php if (empty($all_students)): ?>
                                    <p class="text-muted text-center m-2">No hay estudiantes registrados.</p>
                                <?php else: ?>
                                    <?php foreach ($all_students as $student): ?>
                                        <a href="#" class="list-group-item list-group-item-action list-group-item-sm available-student-item" data-id="<?php echo $student['id']; ?>" data-name="<?php echo htmlspecialchars($student['name']); ?>" data-identification="<?php echo htmlspecialchars($student['identification']); ?>">
                                            <?php echo htmlspecialchars($student['name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($student['identification']); ?>)</small>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Columna de Estudiantes Seleccionados -->
                        <div class="col-md-6">
                            <label class="form-label">Estudiantes Seleccionados (<span id="selectedCountDisplay">0</span>)</label>
                             <div id="selectedStudentsListContainer" class="list-group overflow-auto" style="max-height: 250px; border: 1px solid #dee2e6; padding: 5px; min-height: 50px; background-color: #f8f9fa;">
                                <small class="text-muted p-2 text-center d-block" id="noSelectedStudentsMessage">Ningún estudiante seleccionado.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Contenedor oculto para los IDs de los estudiantes seleccionados que se enviarán con el formulario -->
                    <div id="selectedStudentIdsFormContainer"></div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <button type="button" id="clearSelectionBtn" class="btn btn-outline-danger btn-sm">Limpiar Selección (<span class="selected-count-btn">0</span>)</button>
                    </div>

                    <!-- Sección para carga de CSV -->
                    <hr>
                    <h6 class="mb-3">Opción 2: Cargar Estudiantes desde CSV</h6>
                    <div id="csvUploadResultContainer" class="mb-3"></div> <!-- Para mostrar mensajes de la carga CSV -->
                    <div class="mb-3">
                        <label for="student_csv_file" class="form-label">Archivo CSV</label>
                        <input type="file" class="form-control" id="student_csv_file" name="student_csv_file" accept=".csv">
                        <div class="form-text">
                            El archivo debe ser CSV con cabeceras: <code>nombre,identificacion,telefono,email</code>.
                        </div>
                    </div>
                    <button type="button" class="btn btn-info w-100 mb-3" id="btnProcessCsv">
                        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                        Procesar CSV y Añadir a Selección
                    </button>
                    <hr>

                    <button type="submit" class="btn btn-primary w-100 mt-2" id="btn-generate">
                        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                        Generar <span id="generateButtonStudentCount">0</span> Certificado(s)
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- Columna para Certificados Recientes -->
    <div class="col-lg-6">
         <div class="card shadow-sm">
             <div class="card-header"><h5 class="mb-0"><i class="fas fa-history me-2"></i>Últimos Certificados Generados</h5></div>
             <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Estudiante</th><th>Curso</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                        <?php if(empty($generated_certificates)): ?>
                            <tr><td colspan="3" class="text-center">No hay certificados generados.</td></tr>
                        <?php else: foreach($generated_certificates as $cert): ?>
                            <tr><td><?php echo htmlspecialchars($cert['student_name']); ?></td><td><?php echo htmlspecialchars($cert['course_name']); ?></td><td class="text-end"><a href="../<?php echo htmlspecialchars($cert['pdf_path']); ?>" class="btn btn-sm btn-info" target="_blank" title="Ver PDF"><i class="fas fa-eye"></i></a></td></tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
