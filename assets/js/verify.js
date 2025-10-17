document.addEventListener('DOMContentLoaded', function() {
    const formVerifyCode = document.getElementById('form-verify-code');
    const alertMessage = document.getElementById('alert-message');
    const btnVerifyCode = document.getElementById('btn-verify-code');
    
    function showAlert(message, type = 'danger') {
        alertMessage.innerHTML = message;
        alertMessage.className = `alert alert-${type} mt-3`;
        alertMessage.classList.remove('d-none');
    }

    function hideAlert() {
        alertMessage.classList.add('d-none');
    }

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

    formVerifyCode.addEventListener('submit', function(e) {
        e.preventDefault();
        hideAlert();
        toggleSpinner(btnVerifyCode, true);
        
        const formData = new FormData(formVerifyCode);
        formData.append('action', 'verify_code');

        console.log('Enviando a ajax_handler.php con action=verify_code'); // DEBUG

        fetch('ajax_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Respuesta recibida de ajax_handler.php:', response);
            // Intentar clonar la respuesta para poder leerla dos veces (una como json, otra como text si falla json)
            const clonedResponse = response.clone();
            return response.json().catch(err => {
                console.error('Error al parsear JSON:', err);
                return clonedResponse.text().then(text => {
                    console.error('Respuesta como texto:', text);
                    throw new Error('Respuesta no es JSON: ' + text.substring(0,100)); // Lanza error para que lo capture el .catch
                });
            });
        })
        .then(data => {
            console.log('Datos parseados:', data); // DEBUG
            if (data.success) {
                showAlert('Verificación exitosa. Redirigiendo a: ' + data.redirect_url, 'success');
                console.log('Redirigiendo a:', data.redirect_url); // DEBUG
                // Antes de redirigir, puedes poner un debugger o un alert para pausar
                // alert('Pausa antes de redirigir a: ' + data.redirect_url);
                window.location.href = data.redirect_url;
                console.log('Redirección iniciada.'); // DEBUG. Esto podría no verse si la redirección es muy rápida.
            } else {
                showAlert(data.message || 'El código ingresado es incorrecto.');
                console.warn('Fallo la verificación del código:', data.message);
            }
        })
        .catch(error => {
            console.error('Error en fetch o procesamiento:', error);
            showAlert(error.message || 'Ocurrió un error de comunicación. Inténtalo de nuevo.');
        })
        .finally(() => {
            toggleSpinner(btnVerifyCode, false);
        });
    });
});