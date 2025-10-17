// assets/js/main.js

document.addEventListener('DOMContentLoaded', function () {
    const formCheckId = document.getElementById('form-check-id');
    const formSendCode = document.getElementById('form-send-code');
    const alertMessage = document.getElementById('alert-message');
    const step1Div = document.getElementById('step-1-identification');
    const step2Div = document.getElementById('step-2-verification');
    const verificationOptionsDiv = document.getElementById('verification-options');
    const noOptionsMessage = document.getElementById('no-options-message');
    const studentIdHidden = document.getElementById('student-id-hidden');
    const btnCheckId = document.getElementById('btn-check-id');
    const btnSendCode = document.getElementById('btn-send-code');

    /**
     * Muestra un mensaje de alerta.
     * @param {string} message - El mensaje a mostrar.
     * @param {string} type - El tipo de alerta (e.g., 'danger', 'success').
     */
    function showAlert(message, type = 'danger') {
        alertMessage.innerHTML = message;
        alertMessage.className = `alert alert-${type} mt-3`;
        alertMessage.classList.remove('d-none');
    }

    /**
     * Oculta el mensaje de alerta.
     */
    function hideAlert() {
        alertMessage.classList.add('d-none');
    }
    
    /**
     * Activa o desactiva el spinner en un botón.
     * @param {HTMLElement} button - El elemento del botón.
     * @param {boolean} show - True para mostrar el spinner, false para ocultarlo.
     */
    function toggleSpinner(button, show) {
        const spinner = button.querySelector('.spinner-border');
        if (show) {
            button.disabled = true;
            spinner.classList.remove('d-none');
        } else {
            button.disabled = false;
            spinner.classList.add('d-none');
        }
    }


    // 1. Manejar el envío del formulario de identificación
    formCheckId.addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert();
        toggleSpinner(btnCheckId, true);

        const formData = new FormData(formCheckId);
        formData.append('action', 'check_id');

        fetch('ajax_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Estudiante encontrado, mostrar paso 2
                step1Div.classList.add('d-none');
                displayVerificationOptions(data.student);
                studentIdHidden.value = data.student.id;
                step2Div.classList.remove('d-none');
            } else {
                // Estudiante no encontrado o error
                showAlert(data.message || 'No se encontró la identificación.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Ocurrió un error de comunicación. Inténtalo de nuevo.');
        })
        .finally(() => {
             toggleSpinner(btnCheckId, false);
        });
    });

    /**
     * Muestra las opciones de verificación (SMS/Email) basadas en los datos del estudiante.
     * @param {object} student - El objeto del estudiante con datos de contacto.
     */
    function displayVerificationOptions(student) {
        verificationOptionsDiv.innerHTML = ''; // Limpiar opciones previas
        
        let hasOptions = false;

        // Opción de Email
        if (student.email_hint) {
            hasOptions = true;
            const emailOptionHTML = `
                <div class="verification-option" data-method="email">
                    <input class="form-check-input" type="radio" name="verification_method" id="method_email" value="email" checked>
                    <label class="form-check-label w-100" for="method_email">
                        <i class="fa-solid fa-envelope option-icon"></i>
                        <span class="option-text">Enviar a mi Correo</span><br>
                        <span class="option-hint">${student.email_hint}</span>
                    </label>
                </div>
            `;
            verificationOptionsDiv.insertAdjacentHTML('beforeend', emailOptionHTML);
        }

        // Opción de SMS
        if (student.phone_hint) {
             hasOptions = true;
            const smsOptionHTML = `
                <div class="verification-option" data-method="sms">
                    <input class="form-check-input" type="radio" name="verification_method" id="method_sms" value="sms" ${!student.email_hint ? 'checked' : ''}>
                     <label class="form-check-label w-100" for="method_sms">
                        <i class="fa-solid fa-comment-sms option-icon"></i>
                        <span class="option-text">Enviar por SMS</span><br>
                        <span class="option-hint">Terminado en ••${student.phone_hint}</span>
                    </label>
                </div>
            `;
            verificationOptionsDiv.insertAdjacentHTML('beforeend', smsOptionHTML);
        }
        
        // Si no hay opciones, mostrar mensaje
        if(!hasOptions) {
            noOptionsMessage.classList.remove('d-none');
            btnSendCode.classList.add('d-none');
        }

        // Añadir lógica para seleccionar visualmente la opción
        document.querySelectorAll('.verification-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.verification-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Seleccionar la primera por defecto
        const firstOption = document.querySelector('.verification-option');
        if(firstOption) {
            firstOption.classList.add('selected');
        }
    }


    // 2. Manejar el envío del formulario de método de verificación
    formSendCode.addEventListener('submit', function(e) {
        e.preventDefault();
        hideAlert();
        
        const selectedMethod = document.querySelector('input[name="verification_method"]:checked');
        if (!selectedMethod) {
            showAlert('Por favor, selecciona un método de verificación.');
            return;
        }
        
        toggleSpinner(btnSendCode, true);

        const formData = new FormData(formSendCode);
        formData.append('action', 'send_code');

        fetch('ajax_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirigir a la página de verificación de código
                window.location.href = `verify.php?student_id=${studentIdHidden.value}`;
            } else {
                showAlert(data.message || 'No se pudo enviar el código.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Ocurrió un error de comunicación. Inténtalo de nuevo.');
        })
        .finally(() => {
            toggleSpinner(btnSendCode, false);
        });
    });

});
