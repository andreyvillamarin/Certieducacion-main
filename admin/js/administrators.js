// admin/js/administrators.js (Versión Final y Funcional)
document.addEventListener('DOMContentLoaded', function() {

    function handleAjaxForm(formId, url) {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch(url, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else { alert('Error: ' + data.message); }
                })
                .catch(error => console.error('Error:', error));
            });
        }
    }

    handleAjaxForm('addAdminForm', 'ajax_admin_handler.php');
    handleAjaxForm('editAdminForm', 'ajax_admin_handler.php');

    const editAdminModalEl = document.getElementById('editAdminModal');
    if (editAdminModalEl) {
        editAdminModalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const adminId = button.dataset.id;
            
            const formData = new FormData();
            formData.append('action', 'get_admin');
            formData.append('id', adminId);

            fetch('ajax_admin_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        editAdminModalEl.querySelector('#edit_admin_id').value = data.data.id;
                        editAdminModalEl.querySelector('#edit_username').value = data.data.username;
                        editAdminModalEl.querySelector('#edit_role').value = data.data.role;
                    }
                });
        });
    }

    const deleteAdminModalEl = document.getElementById('deleteAdminModal');
    if (deleteAdminModalEl) {
        deleteAdminModalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            deleteAdminModalEl.querySelector('#admin_id_to_delete').value = button.dataset.id;
            deleteAdminModalEl.querySelector('#admin-username-to-delete').textContent = button.dataset.username;
        });
    }
});