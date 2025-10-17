<?php
// admin/history.php
$page_title = 'Historial de Certificados';
include 'includes/header.php';

$search_text = $_GET['search_text'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_admin_id = isset($_GET['filter_admin_id']) ? (int)$_GET['filter_admin_id'] : null;

// CORRECCIÓN: Se selecciona el estado de borrado para uso futuro.
$sql = "SELECT 
            c.id, 
            c.course_name, 
            c.issue_date, 
            c.pdf_path, 
            c.generation_timestamp, 
            s.name as student_name, 
            s.identification,
            a.username as admin_username,
            c.deleted_at as certificate_deleted_at,
            s.deleted_at as student_deleted_at
        FROM certificates c 
        JOIN students s ON c.student_id = s.id 
        LEFT JOIN admins a ON c.generated_by_user_id = a.id";

$params = [];
$where_clauses = [];

// Condición base: solo mostrar registros activos por defecto.
// La búsqueda avanzada (siguiente paso) modificará esto.
if (empty($search_text)) {
    $where_clauses[] = "c.deleted_at IS NULL";
    $where_clauses[] = "s.deleted_at IS NULL";
}

if (!empty($search_text)) {
    // --- Lógica de búsqueda mejorada ---
    $search_conditions = [];
    
    // Búsqueda por identificación, curso, o admin (texto completo)
    $search_conditions[] = "s.identification LIKE ?";
    $params[] = "%$search_text%";
    $search_conditions[] = "c.course_name LIKE ?";
    $params[] = "%$search_text%";
    $search_conditions[] = "a.username LIKE ?";
    $params[] = "%$search_text%";

    // Búsqueda por palabras en el nombre del estudiante
    $name_words = array_filter(explode(' ', $search_text));
    if (!empty($name_words)) {
        $name_conditions = [];
        foreach ($name_words as $word) {
            $name_conditions[] = "s.name LIKE ?";
            $params[] = "%$word%";
        }
        $search_conditions[] = "(" . implode(' AND ', $name_conditions) . ")";
    }
    
    // Añade el grupo de condiciones de búsqueda
    $where_clauses[] = "(" . implode(' OR ', $search_conditions) . ")";
}

// Filtros de fecha y admin
if (!empty($start_date)) {
    $where_clauses[] = "DATE(c.generation_timestamp) >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "DATE(c.generation_timestamp) <= ?";
    $params[] = $end_date;
}
if ($filter_admin_id !== null && $filter_admin_id > 0) {
    $where_clauses[] = "c.generated_by_user_id = ?";
    $params[] = $filter_admin_id;
}

// Construir la cláusula WHERE final
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY c.generation_timestamp DESC, s.name ASC";

// CORRECCIÓN: Se elimina la referencia a 'name' en la consulta de administradores.
$admins_stmt = $pdo->query("SELECT id, username FROM admins ORDER BY username ASC");
$admins_list = $admins_stmt->fetchAll();

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$certificates = $stmt->fetchAll();
?>

<h1 class="mt-4">Historial de Certificados</h1>
<p>Busca y filtra todos los certificados generados en la plataforma.</p>

<?php
if (isset($_GET['message'])) {
    $message_type = $_GET['type'] ?? 'info';
    $alert_class = $message_type === 'error' ? 'alert-danger' : 'alert-success';
    echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_GET['message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}
?>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0">Filtros de Búsqueda</h5></div>
    <div class="card-body">
        <form action="history.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label">Buscar (Estudiante, Curso, Admin)</label><input type="text" class="form-control" name="search_text" value="<?php echo htmlspecialchars($search_text); ?>"></div>
            <div class="col-md-2"><label class="form-label">Generado Desde</label><input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"></div>
            <div class="col-md-2"><label class="form-label">Generado Hasta</label><input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"></div>
            <div class="col-md-2">
                <label class="form-label">Generado por Admin</label>
                <select name="filter_admin_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($admins_list as $admin_user): ?>
                        <option value="<?php echo $admin_user['id']; ?>" <?php echo ($filter_admin_id == $admin_user['id'] ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($admin_user['username']); // CORRECCIÓN: Mostrar solo username ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1"><button type="submit" class="btn btn-primary w-100">Buscar</button></div>
            <div class="col-md-1">
                 <a href="history.php" class="btn btn-secondary w-100" title="Limpiar filtros"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0">Resultados</h5></div>
    <div class="card-body">
        <form action="delete_certificate.php" method="POST" id="bulkDeleteForm">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 3%;"><input type="checkbox" id="selectAll" title="Seleccionar todos"></th>
                            <th>Estudiante</th>
                            <th>Identificación</th>
                            <th>Curso</th>
                            <th>Fecha Emisión</th>
                            <th>Generado Por</th>
                            <th>Fecha Generación</th>
                            <th style="width: 10%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($certificates)): ?>
                            <tr><td colspan="8" class="text-center">No se encontraron certificados.</td></tr>
                        <?php else: foreach ($certificates as $cert): 
                            $is_cert_deleted = !is_null($cert['certificate_deleted_at']);
                            $is_student_deleted = !is_null($cert['student_deleted_at']);
                            $row_class = $is_cert_deleted ? 'table-danger' : ($is_student_deleted ? 'table-warning' : '');
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <input type="checkbox" name="certificate_ids[]" value="<?php echo $cert['id']; ?>" class="certificate-checkbox" <?php echo $is_cert_deleted ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($cert['student_name']); ?>
                                    <?php if ($is_student_deleted): ?>
                                        <span class="badge bg-warning text-dark ms-2">Estudiante Desactivado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($cert['identification']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($cert['course_name']); ?>
                                    <?php if ($is_cert_deleted): ?>
                                        <span class="badge bg-danger ms-2">Certificado Desactivado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date("d/m/Y", strtotime($cert['issue_date'])); ?></td>
                                <td><?php echo htmlspecialchars($cert['admin_username'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $generation_date = new DateTime($cert['generation_timestamp'], new DateTimeZone('UTC'));
                                    $generation_date->setTimezone(new DateTimeZone('America/Bogota'));
                                    echo $generation_date->format('d/m/Y H:i:s'); 
                                    ?>
                                </td>
                                <td>
                                    <a href="../<?php echo htmlspecialchars($cert['pdf_path']); ?>" class="btn btn-sm btn-info me-1" target="_blank" title="Ver PDF"><i class="fas fa-eye"></i></a>
                                    <?php if ($is_cert_deleted): ?>
                                        <?php if ($is_student_deleted): ?>
                                            <button class="btn btn-sm btn-secondary" disabled title="Reactiva primero al estudiante para poder reactivar este certificado."><i class="fas fa-undo"></i></button>
                                        <?php else: ?>
                                            <a href="delete_certificate.php?action=restore&id=<?php echo $cert['id']; ?>" class="btn btn-sm btn-success" title="Reactivar Certificado" onclick="return confirm('¿Estás seguro de que deseas reactivar este certificado?');">
                                                <i class="fas fa-undo"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="delete_certificate.php?id=<?php echo $cert['id']; ?>" class="btn btn-sm btn-danger" title="Desactivar Certificado" onclick="return confirm('¿Estás seguro de que desea desactivar este certificado? El registro y el PDF se conservarán.');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($certificates)): ?>
            <div class="mt-3">
                <button type="submit" name="bulk_delete" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que desea eliminar los certificados seleccionados? Esta acción no se puede deshacer.');">Eliminar Seleccionados</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('selectAll');
    const certificateCheckboxes = document.querySelectorAll('.certificate-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            certificateCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }

    certificateCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else {
                let allChecked = true;
                certificateCheckboxes.forEach(cb => {
                    if (!cb.checked) {
                        allChecked = false;
                    }
                });
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>