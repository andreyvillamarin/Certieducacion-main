<?php
// admin/activity_log_viewer.php
$page_title = 'Visor de Logs de Actividad';
include 'includes/header.php'; // Asegúrate que esto incluye $pdo y session_start()

// Obtener parámetros de filtro
$filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filter_action_type = isset($_GET['action_type']) ? trim($_GET['action_type']) : '';
$filter_target_table = isset($_GET['target_table']) ? trim($_GET['target_table']) : '';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$search_details = $_GET['search_details'] ?? '';

// CORRECCIÓN: Se elimina la referencia a 'a.name'.
$sql = "SELECT al.id, al.log_timestamp, al.action_type, al.target_id, al.target_table, al.details, a.username as admin_username 
        FROM activity_log al
        LEFT JOIN admins a ON al.user_id = a.id
        WHERE 1=1";
$params = [];

if ($filter_user_id !== null && $filter_user_id > 0) {
    $sql .= " AND al.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}
if (!empty($filter_action_type)) {
    $sql .= " AND al.action_type = :action_type";
    $params[':action_type'] = $filter_action_type;
}
if (!empty($filter_target_table)) {
    $sql .= " AND al.target_table = :target_table";
    $params[':target_table'] = $filter_target_table;
}
if (!empty($filter_start_date)) {
    $sql .= " AND DATE(al.log_timestamp) >= :start_date";
    $params[':start_date'] = $filter_start_date;
}
if (!empty($filter_end_date)) {
    $sql .= " AND DATE(al.log_timestamp) <= :end_date";
    $params[':end_date'] = $filter_end_date;
}
if (!empty($search_details)) {
    $sql .= " AND al.details LIKE :search_details";
    $params[':search_details'] = "%".$search_details."%";
}

$sql .= " ORDER BY al.log_timestamp DESC";

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

$count_sql = preg_replace('/SELECT .*? FROM/', 'SELECT COUNT(*) as total FROM', $sql, 1);
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql .= " LIMIT :limit OFFSET :offset";
$params[':limit'] = $limit;
$params[':offset'] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// CORRECCIÓN: Se elimina la referencia a 'name' en la consulta de administradores.
$admins_stmt = $pdo->query("SELECT id, username FROM admins ORDER BY username ASC");
$admins_list = $admins_stmt->fetchAll();

$action_types_stmt = $pdo->query("SELECT DISTINCT action_type FROM activity_log ORDER BY action_type ASC");
$action_types_list = $action_types_stmt->fetchAll(PDO::FETCH_COLUMN);

$target_tables_stmt = $pdo->query("SELECT DISTINCT target_table FROM activity_log WHERE target_table IS NOT NULL ORDER BY target_table ASC");
$target_tables_list = $target_tables_stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<h1 class="mt-4">Visor de Logs de Actividad del Sistema</h1>
<p>Revisa las acciones realizadas en el sistema.</p>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0">Filtros de Búsqueda</h5></div>
    <div class="card-body">
        <form action="activity_log_viewer.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Usuario Admin</label>
                <select name="user_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($admins_list as $admin): ?>
                        <option value="<?php echo $admin['id']; ?>" <?php echo ($filter_user_id == $admin['id'] ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($admin['username']); // CORRECCIÓN: Mostrar solo username ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo de Acción</label>
                <select name="action_type" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($action_types_list as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($filter_action_type == $type ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tabla Afectada</label>
                <select name="target_table" class="form-select">
                    <option value="">Todas</option>
                     <?php foreach ($target_tables_list as $table): ?>
                        <option value="<?php echo htmlspecialchars($table); ?>" <?php echo ($filter_target_table == $table ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars(ucfirst($table)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Desde</label><input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
            <div class="col-md-2"><label class="form-label">Hasta</label><input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
            <div class="col-md-3"><label class="form-label">Buscar en Detalles</label><input type="text" class="form-control" name="search_details" value="<?php echo htmlspecialchars($search_details); ?>"></div>
            
            <div class="col-md-2 align-self-end">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
            <div class="col-md-1 align-self-end">
                <a href="activity_log_viewer.php" class="btn btn-secondary w-100" title="Limpiar filtros"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0">Resultados del Log (<?php echo $total_records; ?> entradas)</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>ID Log</th>
                        <th>Fecha y Hora</th>
                        <th>Usuario</th>
                        <th>Tipo Acción</th>
                        <th>Tabla Afectada</th>
                        <th>ID Afectado</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="text-center">No se encontraron registros de actividad con los filtros actuales.</td></tr>
                    <?php else: foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td>
                                <?php 
                                $log_date = new DateTime($log['log_timestamp'], new DateTimeZone('UTC'));
                                $log_date->setTimezone(new DateTimeZone('America/Bogota'));
                                echo $log_date->format('d/m/Y H:i:s'); 
                                ?>
                            </td>
                            <td>
                                <?php 
                                // CORRECCIÓN: Mostrar solo username
                                if (!empty($log['admin_username'])) {
                                    echo htmlspecialchars($log['admin_username']);
                                } elseif ($log['user_id']) {
                                    echo 'ID: ' . $log['user_id'];
                                }
                                else {
                                    echo 'Sistema';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action_type']))); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($log['target_table'])); ?></td>
                            <td><?php echo $log['target_id'] ?: 'N/A'; ?></td>
                            <td style="max-width: 400px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-all;"><?php echo nl2br(htmlspecialchars($log['details'])); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-3">
                <?php 
                $base_query = $_GET;
                // Anterior
                if ($page > 1):
                    $base_query['page'] = $page - 1;
                    echo '<li class="page-item"><a class="page-link" href="?'.http_build_query($base_query).'">Anterior</a></li>';
                else:
                    echo '<li class="page-item disabled"><a class="page-link" href="#">Anterior</a></li>';
                endif;

                // Números de página
                for ($i = 1; $i <= $total_pages; $i++):
                    $base_query['page'] = $i;
                    echo '<li class="page-item '.($i == $page ? 'active' : '').'"><a class="page-link" href="?'.http_build_query($base_query).'">'.$i.'</a></li>';
                endfor;

                // Siguiente
                if ($page < $total_pages):
                    $base_query['page'] = $page + 1;
                    echo '<li class="page-item"><a class="page-link" href="?'.http_build_query($base_query).'">Siguiente</a></li>';
                else:
                    echo '<li class="page-item disabled"><a class="page-link" href="#">Siguiente</a></li>';
                endif;
                ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
