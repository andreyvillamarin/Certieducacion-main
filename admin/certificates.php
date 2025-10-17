<?php
// admin/certificates.php (MODIFICADO PARA API EXTERNA)
$page_title = 'Generación de Certificados';
// No se necesita el JS específico anterior, la lógica estará en esta página.
$page_specific_js = '';
include 'includes/header.php';

// INYECTAR API_BASE_URL EN JAVASCRIPT
echo '<script>';
echo 'const API_BASE_URL = "' . API_BASE_URL . '";';
echo '</script>';

// Ya no necesitamos obtener la lista de estudiantes de la BD local.

$notification = '';
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

// La lista de certificados recientes ahora se obtiene vía API.
?>

<h1 class="mt-4">Generación de Certificados</h1>
<p>Crea certificados basados en los cursos y estudiantes de la fuente de datos externa.</p>

<?php if (!empty($notification)): ?>
<div class="alert alert-<?php echo htmlspecialchars($notification['type']); ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($notification['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-award me-2"></i>Generar Certificados desde API</h5></div>
            <div class="card-body">
                <form action="generate_certificate_handler.php" method="POST" id="generateCertForm">
                    <input type="hidden" name="course_data" id="hidden_course_data">

                    <div class="mb-3">
                        <label for="issue_date" class="form-label"><strong>Paso 1:</strong> Selecciona la Fecha de Emisión</label>
                        <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="courseSearchInput" class="form-label"><strong>Paso 2:</strong> Busca y selecciona un curso</label>
                        <input type="text" id="courseSearchInput" class="form-control" placeholder="Buscar por nombre de curso...">
                    </div>

                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6;">
                        <table class="table table-hover table-sm">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Curso</th>
                                    <th class="text-center">Estudiantes</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="courses-table-body">
                                <tr><td colspan="3" class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="selection-feedback" class="mt-2 text-muted small"></div>

                    <hr>
                    <button type="submit" class="btn btn-primary w-100 mt-2" id="btn-generate" disabled>
                        <i class="fas fa-cogs"></i> Generar Certificados
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
         <div class="card shadow-sm">
             <div class="card-header"><h5 class="mb-0"><i class="fas fa-history me-2"></i>Últimos Certificados Generados</h5></div>
             <div class="card-body">
                 <div class="table-responsive">
                     <table class="table table-hover">
                         <thead><tr><th>Estudiante</th><th>Curso</th><th class="text-end">Acciones</th></tr></thead>
                         <tbody id="recent-certs-table-body">
                             <tr><td colspan="3" class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></td></tr>
                         </tbody>
                     </table>
                 </div>
             </div>
         </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const courseSearchInput = document.getElementById('courseSearchInput');
    const coursesTableBody = document.getElementById('courses-table-body');
    const generateBtn = document.getElementById('btn-generate');
    const hiddenCourseDataInput = document.getElementById('hidden_course_data');
    const selectionFeedback = document.getElementById('selection-feedback');
    const recentCertsTableBody = document.getElementById('recent-certs-table-body');

    let allCourses = [];
    let selectedCourseId = null;
    // --- 1. Cargar cursos desde la API ---
    async function fetchCourses() {
        try {
            const response = await fetch(API_BASE_URL + 'courses/list.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            allCourses = await response.json();
            renderCoursesTable(allCourses);
        } catch (error) {
            console.error('Error al cargar los cursos:', error);
            coursesTableBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger p-4">Error al cargar los cursos. Verifique que la API esté funcionando.</td></tr>`;
        }
    }

    // --- 2. Renderizar la tabla de cursos ---
    function renderCoursesTable(courses) {
        coursesTableBody.innerHTML = '';
        if (courses.length === 0) {
            coursesTableBody.innerHTML = '<tr><td colspan="3" class="text-center p-4">No se encontraron cursos.</td></tr>';
            return;
        }

        courses.forEach(course => {
            const tr = document.createElement('tr');
            tr.dataset.courseId = course.course_id;
            tr.innerHTML = `
                <td>
                    <strong>${escapeHTML(course.course_name)}</strong><br>
                    <small class="text-muted">Duración: ${escapeHTML(course.duration)} horas</small>
                </td>
                <td class="text-center align-middle">${course.students.length}</td>
                <td class="text-end align-middle">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-select-course">Seleccionar</button>
                    <button type="button" class="btn btn-sm btn-outline-success btn-complete-course ms-2" data-course-code="${escapeHTML(course.course_code)}">Completar</button>
                </td>
            `;
            coursesTableBody.appendChild(tr);
        });
    }

    // --- 3. Cargar certificados recientes desde la API ---
    async function fetchRecentCertificates() {
        try {
            const response = await fetch(API_BASE_URL + 'certificates/recent.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const recentCerts = await response.json();
            renderRecentCertificatesTable(recentCerts);
        } catch (error) {
            console.error('Error al cargar certificados recientes:', error);
            recentCertsTableBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger p-4">Error al cargar certificados recientes.</td></tr>`;
        }
    }

    // --- 4. Renderizar la tabla de certificados recientes ---
    function renderRecentCertificatesTable(certs) {
        recentCertsTableBody.innerHTML = '';
        if (certs.length === 0) {
            recentCertsTableBody.innerHTML = '<tr><td colspan="3" class="text-center">No hay certificados generados.</td></tr>';
            return;
        }

        certs.forEach(cert => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHTML(cert.student_name)}</td>
                <td>${escapeHTML(cert.course_name)}</td>
                <td class="text-end">
                    <a href="${escapeHTML(cert.pdf_url)}" class="btn btn-sm btn-info" target="_blank" title="Ver PDF"><i class="fas fa-eye"></i></a>
                    </td>
            `;
            recentCertsTableBody.appendChild(tr);
        });
    }
    // --- 5. Manejar la búsqueda en tiempo real ---
    courseSearchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const filteredCourses = allCourses.filter(course =>
            course.course_name.toLowerCase().includes(searchTerm)
        );
        renderCoursesTable(filteredCourses);
        // Si el curso seleccionado ya no está visible, deseleccionarlo
        if (selectedCourseId && !filteredCourses.some(c => c.course_id === selectedCourseId)) {
            clearSelection();
        }
        highlightSelectedRow();
    });

    // --- 6. Manejar la selección de un curso ---
    coursesTableBody.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-select-course')) {
            const selectedRow = e.target.closest('tr');
            const courseId = selectedRow.dataset.courseId;

            if (selectedCourseId === courseId) {
                // Si se hace clic en el mismo, deseleccionar
                clearSelection();
            } else {
                const course = allCourses.find(c => c.course_id === courseId);
                if (course) {
                    selectedCourseId = course.course_id;
                    hiddenCourseDataInput.value = JSON.stringify(course);
                    generateBtn.disabled = false;
                    selectionFeedback.textContent = `Curso seleccionado: "${course.course_name}" con ${course.students.length} estudiantes.`;
                    highlightSelectedRow();
                }
            }
        } else if (e.target.classList.contains('btn-complete-course')) { // NUEVA LÓGICA PARA COMPLETAR CURSO
            const courseCodeToComplete = e.target.dataset.courseCode;
            if (confirm(`¿Estás seguro de que quieres marcar el curso "${courseCodeToComplete}" como completado? Ya no aparecerá en la lista.`)) {
                updateCourseStatus(courseCodeToComplete, 'completed');
            }
        }
    });

    // --- 7. Función para actualizar el estado del curso ---
    async function updateCourseStatus(courseCode, status) {
        try {
            const response = await fetch(API_BASE_URL + 'courses/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ course_code: courseCode, status: status })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                alert(data.message);
                fetchCourses(); // Recargar la lista de cursos para que el completado desaparezca
            } else {
                alert(`Error al actualizar el estado del curso: ${data.message || 'Error desconocido'}`);
                console.error('Error al actualizar estado:', data);
            }
        } catch (error) {
            alert('Error de comunicación al actualizar el estado del curso.');
            console.error('Error de fetch al actualizar estado:', error);
        }
    }
    function clearSelection() {
        selectedCourseId = null;
        hiddenCourseDataInput.value = '';
        generateBtn.disabled = true;
        selectionFeedback.textContent = 'Ningún curso seleccionado.';
        highlightSelectedRow();
    }

    function highlightSelectedRow() {
        document.querySelectorAll('#courses-table-body tr').forEach(row => {
            if (row.dataset.courseId === selectedCourseId) {
                row.classList.add('table-primary');
                row.querySelector('.btn-select-course').textContent = 'Seleccionado';
                row.querySelector('.btn-select-course').classList.replace('btn-outline-primary', 'btn-primary');
            } else {
                row.classList.remove('table-primary');
                row.querySelector('.btn-select-course').textContent = 'Seleccionar';
                row.querySelector('.btn-select-course').classList.replace('btn-primary', 'btn-outline-primary');
            }
            // Asegurarse de que el botón "Completar" siempre esté visible
            const completeButton = row.querySelector('.btn-complete-course');
            if (completeButton) {
                completeButton.style.display = ''; // O 'inline-block' si es necesario
            }
        });
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, function (s) {
            return {
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[s];
        });
    }

    // Iniciar la carga de cursos y certificados recientes
    fetchCourses();
    fetchRecentCertificates();
});
</script>

<?php include 'includes/footer.php'; ?>