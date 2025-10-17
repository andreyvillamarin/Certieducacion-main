console.log('students.js file loaded'); 

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded event fired'); 
    
    function handleAjaxForm(formId, url) {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch(url, { method: 'POST', body: formData })
                .then(res => {
                    const contentType = res.headers.get("content-type");
                    console.log('Received Content-Type from server for form ' + formId + ':', contentType); // LOG PARA DEPURAR
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return res.json();
                    } else {
                        return res.text().then(text => { 
                            console.error('Raw non-JSON response body for form ' + formId + ':', text); // LOG PARA DEPURAR
                            // Lanzar un error m芍s informativo
                            throw new Error("Expected JSON, but got Content-Type: '" + contentType + "'. Raw body: " + text.substring(0, 300) + (text.length > 300 ? "..." : ""));
                        });
                    }
                })
                .then(data => {
                    if (data.success) {
                        if (data.message) {
                            // Podr赤as usar sessionStorage para mostrar un mensaje despu谷s de recargar
                            // sessionStorage.setItem('notification', JSON.stringify({type: 'success', message: data.message}));
                            alert(data.message); // Simple alerta por ahora
                        }
                        window.location.reload();
                    } else { 
                        alert('Error: ' + (data.message || 'Ocurri車 un error desconocido.')); 
                        console.error('Form submission error (data.success false) for ' + formId + '. Message:', data.message, "Data:", data);
                    }
                })
                .catch(error => {
                    console.error('Fetch error or JSON parsing error for form ' + formId + ':', error.message);
                    alert('Ocurri車 un error de conexi車n o respuesta inesperada al procesar el formulario: ' + error.message);
                });
            });
        } else {
            console.warn('Form with ID ' + formId + ' not found during setup.');
        }
    }

    handleAjaxForm('addStudentForm', 'ajax_student_handler.php');
    handleAjaxForm('editStudentForm', 'ajax_student_handler.php');
    handleAjaxForm('uploadCsvForm', 'ajax_student_handler.php');

    const editStudentModalEl = document.getElementById('editStudentModal');
    if (editStudentModalEl) {
        console.log('editStudentModalEl found by ID "editStudentModal":', editStudentModalEl); 

        editStudentModalEl.addEventListener('show.bs.modal', function (event) {
            console.log('EVENT: show.bs.modal event fired for editStudentModalEl'); 
            
            const button = event.relatedTarget; 
            console.log('Modal triggered by:', button);

            if (button) {
                const studentId = button.dataset.id;
                console.log('Student ID from button.dataset.id:', studentId);

                if (studentId) {
                    console.log('Proceeding to fetch data for student ID:', studentId);
                    
                    // --- L車gica para obtener datos del estudiante --- 
                    const formData = new FormData();
                    formData.append('action', 'get_student');
                    formData.append('id', studentId);

                    fetch('ajax_student_handler.php', { method: 'POST', body: formData })
                        .then(res => {
                            const contentType = res.headers.get("content-type");
                            console.log('Received Content-Type for get_student:', contentType);
                            if (contentType && contentType.indexOf("application/json") !== -1) {
                                return res.json();
                            } else {
                                return res.text().then(text => { 
                                    throw new Error("Expected JSON for get_student, but got Content-Type: '" + contentType + "'. Raw body: " + text.substring(0,200) + "...");
                                });
                            }
                        })
                        .then(data => {
                            console.log('Parsed JSON data from get_student:', data); 
                            if (data.success && data.data) {
                                editStudentModalEl.querySelector('#edit_student_id').value = data.data.id;
                                editStudentModalEl.querySelector('#edit_name').value = data.data.name;
                                editStudentModalEl.querySelector('#edit_identification').value = data.data.identification;
                                editStudentModalEl.querySelector('#edit_phone').value = data.data.phone;
                                editStudentModalEl.querySelector('#edit_email').value = data.data.email;
                                console.log('Successfully populated modal for student ID:', studentId);
                            } else {
                                console.warn('Data success is false or data.data is missing from get_student. Message:', data.message, 'Data:', data); 
                                alert('Error al cargar datos del estudiante: ' + (data.message || 'Respuesta no exitosa del servidor o datos no encontrados.'));
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching or processing student data for modal:', error.message); 
                            alert('Ocurri車 un error de conexi車n al cargar datos del estudiante: ' + error.message);
                        });
                } else {
                    console.error('Student ID not found in button.dataset.id. Button:', button);
                }
            } else {
                console.error('event.relatedTarget (the button that triggered modal) is null or undefined. Event:', event);
            }
        });
    } else {
        console.error('CRITICAL: editStudentModalEl NOT found by ID "editStudentModal"'); 
    }

    const deleteConfirmModalEl = document.getElementById('deleteConfirmModal');
    if (deleteConfirmModalEl) {
        console.log('deleteConfirmModalEl found by ID "deleteConfirmModal"');
        deleteConfirmModalEl.addEventListener('show.bs.modal', function (event) {
            console.log('EVENT: show.bs.modal event fired for deleteConfirmModalEl');
            const button = event.relatedTarget;
            if (button) {
                const studentId = button.dataset.id;
                const studentName = button.dataset.name;
                console.log('Delete modal for student ID:', studentId, 'Name:', studentName);
                deleteConfirmModalEl.querySelector('#student_id_to_delete').value = studentId;
                deleteConfirmModalEl.querySelector('#student-name-to-delete').textContent = studentName;
            } else {
                 console.error('Delete modal event.relatedTarget (the button) is null or undefined. Event:', event);
            }
        });
    } else {
        console.warn('deleteConfirmModalEl NOT found by ID "deleteConfirmModal"');
    }
});

console.log('students.js file finished executing');