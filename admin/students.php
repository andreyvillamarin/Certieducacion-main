<?php
// admin/students.php (Versión Definitiva con Modales Restaurados)
$page_title = 'Gestión de Estudiantes';
$page_specific_js = 'js/students.js';
include 'includes/header.php'; // Solo se incluye el header, que ya debería incluir config.php y database.php
// Incluir logger.php si no está ya en el header.php
if (file_exists(__DIR__ . '/../includes/logger.php')) {
    require_once __DIR__ . '/../includes/logger.php';
} else {
    error_log('Advertencia CRÍTICA: logger.php no encontrado desde students.php');
}


// CORRECCIÓN: Se procesa la eliminación aquí, no en un handler AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $student_id_to_delete = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

    if ($student_id_to_delete) {
        try {
            // Obtener datos del estudiante ANTES de eliminar para el log
            // Obtener datos del estudiante ANTES de eliminar para el log
            $stmt_get = $pdo->prepare("SELECT name, identification FROM students WHERE id = ? AND deleted_at IS NULL");
            $stmt_get->execute([$student_id_to_delete]);
            $student_data_for_log = $stmt_get->fetch(PDO::FETCH_ASSOC);

            // CORRECCIÓN: Cambiar DELETE por UPDATE para borrado suave
            $stmt_soft_delete = $pdo->prepare("UPDATE students SET deleted_at = NOW() WHERE id = ?");
            $stmt_soft_delete->execute([$student_id_to_delete]);

            if ($stmt_soft_delete->rowCount() > 0) {
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Estudiante desactivado correctamente.'];
                // Registrar actividad de borrado suave
                if (function_exists('log_activity') && $student_data_for_log) {
                    $current_admin_id_for_log = $_SESSION['admin_id'] ?? null;
                    $log_details_delete = "Estudiante desactivado (borrado suave): Nombre: {$student_data_for_log['name']}, ID SIS: {$student_id_to_delete}, Identificación: {$student_data_for_log['identification']}.";
                    log_activity($pdo, $current_admin_id_for_log, 'estudiante_desactivado', $student_id_to_delete, 'students', $log_details_delete);
                }
            } else {
                $_SESSION['notification'] = ['type' => 'warning', 'message' => 'No se encontró el estudiante para desactivar o ya estaba inactivo.'];
            }
        } catch (PDOException $e) {
            error_log("Error al eliminar estudiante (students.php): " . $e->getMessage());
            $_SESSION['notification'] = ['type' => 'danger', 'message' => 'Error de base de datos al eliminar el estudiante. Verifique si tiene certificados asociados.'];
        }
    } else {
        $_SESSION['notification'] = ['type' => 'danger', 'message' => 'ID de estudiante no válido para eliminar.'];
    }
    header("Location: students.php");
    exit;
}

$notification = '';
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}
$search_term = $_GET['search'] ?? '';
$view = $_GET['view'] ?? 'active'; // 'active' o 'archived'

$list_title = 'Listado de Estudiantes Activos';
$sql = "SELECT id, name, identification, phone, email, deleted_at FROM students";
$params = [];
$where_conditions = [];

if ($view === 'archived') {
    $list_title = 'Listado de Estudiantes Archivados';
    $where_conditions[] = "deleted_at IS NOT NULL";
} else {
    // Vista por defecto y vista 'active'
    $where_conditions[] = "deleted_at IS NULL";
}

if (!empty($search_term)) {
    $where_conditions[] = "(name LIKE ? OR identification LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}

$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<h1 class="mt-4">Gestión de Estudiantes</h1>
<p>Administra la base de datos de estudiantes.</p>

<?php if ($notification): ?>
<div class="alert alert-<?php echo htmlspecialchars($notification['type']); ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($notification['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0" id="student-list-title"><?php echo htmlspecialchars($list_title); ?></h5>
        <div>
            <a href="students.php?view=archived" class="btn btn-secondary btn-sm"><i class="fas fa-archive me-1"></i> Ver Archivados</a>
            <a href="students.php" class="btn btn-light btn-sm"><i class="fas fa-user-check me-1"></i> Ver Activos</a>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadCsvModal"><i class="fas fa-file-csv me-1"></i> Cargar CSV</button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="fas fa-plus me-1"></i> Agregar Estudiante</button>
        </div>
    </div>
    <div class="card-body">
        <form action="students.php" method="GET" class="mb-3">
            <div class="input-group">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                <input type="text" name="search" class="form-control" placeholder="Buscar en vista actual..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                 <thead class="table-dark"><tr><th>Nombre</th><th>Identificación</th><th>Teléfono</th><th>Email</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="5" class="text-center">No se encontraron estudiantes.</td></tr>
                    <?php else: foreach ($students as $student): ?>
                        <tr class="<?php echo $student['deleted_at'] ? 'table-danger' : ''; ?>">
                            <td>
                                <?php echo htmlspecialchars($student['name']); ?>
                                <?php if ($student['deleted_at']): ?>
                                    <span class="badge bg-danger ms-2">Desactivado</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['identification']); ?></td>
                            <td><?php echo htmlspecialchars($student['phone']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td class="text-end">
                                <?php if ($student['deleted_at']): ?>
                                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal" title="Reactivar Estudiante" data-id="<?php echo $student['id']; ?>" data-name="<?php echo htmlspecialchars($student['name']); ?>" data-identification="<?php echo htmlspecialchars($student['identification']); ?>" data-phone="<?php echo htmlspecialchars($student['phone']); ?>" data-email="<?php echo htmlspecialchars($student['email']); ?>"><i class="fas fa-undo"></i></button>
                                <?php else: ?>
                                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editStudentModal" data-id="<?php echo $student['id']; ?>" title="Editar"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?php echo $student['id']; ?>" data-name="<?php echo htmlspecialchars($student['name']); ?>" title="Desactivar"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODALES CON SU CONTENIDO COMPLETO Y RESTAURADO -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Agregar Nuevo Estudiante</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="addStudentForm" action="ajax_student_handler.php" method="POST">
            <div class="modal-body"><input type="hidden" name="action" value="add_student"><div class="mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" name="name" required></div><div class="mb-3"><label class="form-label">Identificación</label><input type="text" class="form-control" name="identification" required></div><div class="mb-3"><label class="form-label">Teléfono</label><input type="tel" class="form-control" name="phone"></div><div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Editar Estudiante</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="editStudentForm" action="ajax_student_handler.php" method="POST">
            <div class="modal-body"><input type="hidden" name="action" value="update_student"><input type="hidden" id="edit_student_id" name="student_id"><div class="mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" id="edit_name" name="name" required></div><div class="mb-3"><label class="form-label">Identificación</label><input type="text" class="form-control" id="edit_identification" name="identification" required></div><div class="mb-3"><label class="form-label">Teléfono</label><input type="tel" class="form-control" id="edit_phone" name="phone"></div><div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" id="edit_email" name="email"></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="uploadCsvModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Cargar desde CSV</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="uploadCsvForm" action="ajax_student_handler.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body"><input type="hidden" name="action" value="upload_csv"><div class="alert alert-info"><p class="mb-1"><strong>Instrucciones:</strong></p><ul class="mb-0 small"><li>Archivo debe ser CSV.</li><li>Cabeceras: <code>nombre,identificacion,telefono,email</code></li><li>No se registrarán identificaciones duplicadas.</li></ul></div><div class="mb-3"><label class="form-label">Seleccionar archivo CSV</label><input class="form-control" type="file" name="csv_file" accept=".csv" required></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success">Subir y Procesar</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Confirmar Eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">¿Estás seguro de que deseas eliminar a <strong id="student-name-to-delete"></strong>?</div>
        <div class="modal-footer">
            <form action="students.php" method="POST">
                <input type="hidden" name="action" value="delete"><input type="hidden" id="student_id_to_delete" name="student_id">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Sí, Eliminar</button>
            </form>
        </div>
    </div></div>
</div>

<?php include 'includes/footer.php'; ?>