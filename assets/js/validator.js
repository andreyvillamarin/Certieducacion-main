document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM completamente cargado y parseado. validator.js ejecutándose."); // DEBUG

    const formValidate = document.getElementById('form-validate-code');
    const btnValidate = document.getElementById('btn-validate');
    const validationCodeInput = document.getElementById('validation_code');
    const resultDiv = document.getElementById('validation-result');
    const btnScanQr = document.getElementById('btn-scan-qr');
    const qrReaderDiv = document.getElementById('qr-reader');

    console.log("btnScanQr encontrado:", btnScanQr);
    console.log("qrReaderDiv encontrado:", qrReaderDiv);
    console.log("resultDiv encontrado:", resultDiv);
    console.log("validationCodeInput encontrado:", validationCodeInput);

    let html5QrCode = null;

    // Función para detectar Chrome en iOS
    function isChromeOnIOS() {
        const ua = navigator.userAgent;
        return ua.includes('CriOS') && (ua.includes('iPhone') || ua.includes('iPad') || ua.includes('iPod'));
    }

    function toggleSpinner(button, show) {
        if (!button) {
            console.warn("toggleSpinner: el botón es nulo.");
            return; 
        }
        const spinner = button.querySelector('.spinner-border');
        if (show) {
            button.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
        } else {
            button.disabled = false;
            if (spinner) spinner.classList.add('d-none');
        }
    }
    
    function displayResult(data) {
        if (!resultDiv) {
            console.warn("displayResult: resultDiv es nulo.");
            return; 
        }
        let resultHTML = '';
        if (data.success && data.certificate) {
            const cert = data.certificate;
            resultHTML = `
                <div class="alert alert-success">
                    <h5 class="alert-heading"><i class="fas fa-check-circle"></i> Certificado Auténtico</h5>
                    <hr>
                    <p class="mb-1"><strong>Estudiante:</strong> ${cert.student_name}</p>
                    <p class="mb-1"><strong>Identificación:</strong> ${cert.student_id}</p>
                    <p class="mb-1"><strong>Curso:</strong> ${cert.course_name}</p>
                    <p class="mb-0"><strong>Fecha de Emisión:</strong> ${cert.issue_date}</p>
                </div>`;
        } else {
            resultHTML = `
                <div class="alert alert-danger">
                    <h5 class="alert-heading"><i class="fas fa-times-circle"></i> Certificado No Válido</h5>
                    <p class="mb-0">${data.message || 'El código ingresado no corresponde a ningún certificado emitido por nuestra institución.'}</p>
                </div>`;
        }
        resultDiv.innerHTML = resultHTML;
    }

    function validateCode(code) {
        toggleSpinner(btnValidate, true);
        if (resultDiv) resultDiv.innerHTML = '';

        const csrfToken = document.querySelector('#form-validate-code [name="csrf_token"]').value;

        const formData = new FormData();
        formData.append('action', 'validate_certificate_code');
        formData.append('validation_code', code);
        formData.append('csrf_token', csrfToken);

        fetch('ajax_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            displayResult(data);
            if (html5QrCode) { 
                html5QrCode.stop().then(() => {
                    console.log("Escáner QR detenido después de validación.");
                    if (qrReaderDiv) qrReaderDiv.style.display = 'none';
                }).catch(err => {
                    console.warn("Advertencia: No se pudo detener el escáner QR o ya estaba detenido.", err);
                    if (qrReaderDiv) qrReaderDiv.style.display = 'none'; 
                });
            } else {
                if (qrReaderDiv) qrReaderDiv.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error en validación AJAX:', error);
            displayResult({ success: false, message: 'Ocurrió un error de comunicación al validar.' });
        })
        .finally(() => {
            toggleSpinner(btnValidate, false);
        });
    }

    if (formValidate) {
        formValidate.addEventListener('submit', function(e) {
            e.preventDefault();
            if (validationCodeInput) {
                const code = validationCodeInput.value.trim();
                if (code) validateCode(code);
            } else {
                console.warn("Input de código de validación no encontrado al enviar formulario.");
            }
        });
    } else {
        console.warn("Formulario 'form-validate-code' no encontrado.");
    }

    if (btnScanQr) {
        console.log("Añadiendo event listener a btnScanQr"); 
        btnScanQr.addEventListener('click', () => {
            console.log("Botón 'Escanear Código QR' clickeado."); 

            if (qrReaderDiv) qrReaderDiv.style.display = 'block';
            if (resultDiv) resultDiv.innerHTML = ''; 
            
            if (!html5QrCode) {
                console.log("Creando nueva instancia de Html5Qrcode."); 
                try {
                    html5QrCode = new Html5Qrcode("qr-reader", /* verbose= */ true); 
                } catch (initError) {
                    console.error("Error al instanciar Html5Qrcode:", initError);
                    if (resultDiv) resultDiv.innerHTML = `<div class="alert alert-danger">Error al inicializar el escáner QR. ${initError.message}</div>`;
                    if (qrReaderDiv) qrReaderDiv.style.display = 'none';
                    return;
                }
            }
            
            const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                console.log(`Código QR detectado: ${decodedText}`, decodedResult);
                if (validationCodeInput) validationCodeInput.value = decodedText;
                validateCode(decodedText); 
            };

            console.log("Intentando iniciar escáner con configuración MÍNIMA (solo facingMode, callbacks, y config = undefined)");

            html5QrCode.start(
                { facingMode: "environment" }, 
                undefined, // Usar defaults de la biblioteca para la configuración
                qrCodeSuccessCallback,
                (errorMessageDuringScan) => {
                    console.warn(`Advertencia o error durante el escaneo QR (no es un error de inicio): ${errorMessageDuringScan}`);
                }
            )
            .catch(err => {
                console.error("Objeto de error recibido en .catch al iniciar escáner:", err);
                
                let userMessage = "No se pudo iniciar el escáner. Asegúrate de dar permisos a la cámara y que esta no esté siendo usada por otra aplicación.";
                let errorName = "N/A";
                let errorMessage = "N/A";
                let errorType = typeof err;
                let errorValue = ""; 

                if (err) {
                    errorName = err.name || "N/A";
                    errorMessage = err.message || "N/A";
                    if (typeof err === 'string') { 
                        errorMessage = err; 
                        errorValue = err; 
                    }
                }

                let isIOSChromeCompatibilityError = false;
                if (isChromeOnIOS() && ( (typeof errorValue === 'string' && errorValue.toLowerCase().includes("camera streaming not supported")) || (typeof errorMessage === 'string' && errorMessage.toLowerCase().includes("camera streaming not supported")) ) ) {
                    userMessage = "La función de escaneo QR no es compatible con Chrome en iOS en este momento. Por favor, intente usar Safari en su dispositivo iOS o ingrese el código manualmente.";
                    errorName = "CompatibilityError";
                    isIOSChromeCompatibilityError = true; // Marcamos que es este error específico
                } else if (err.name === "NotAllowedError" || (typeof errorMessage === 'string' && errorMessage.toLowerCase().includes("permission denied"))) {
                    userMessage = "Permiso para acceder a la cámara denegado. Revisa la configuración de permisos de tu navegador para este sitio.";
                } else if (err.name === "NotFoundError" || err.name === "DevicesNotFoundError") {
                    userMessage = "No se encontró una cámara disponible. Asegúrate de que tu dispositivo tenga una cámara.";
                } else if (err.name === "NotReadableError" || err.name === "TrackStartError") {
                    userMessage = "La cámara está ocupada o no se puede acceder a ella. Intenta cerrar otras aplicaciones que puedan estar usándola o recarga la página.";
                } else if (err.name === "OverconstrainedError" || err.name === "ConstraintNotSatisfiedError") {
                    userMessage = "No se pudo satisfacer la configuración de la cámara solicitada (ej. cámara trasera no disponible).";
                }

                if (resultDiv) {
                    if (isIOSChromeCompatibilityError) {
                        // Para el error específico de Chrome en iOS, solo mostramos el mensaje principal
                        resultDiv.innerHTML = `<div class="alert alert-warning">${userMessage}</div>`;
                    } else {
                        // Para otros errores, sí mostramos los detalles (opcional, podrías quitarlo también si prefieres)
                        let errorDetailsForUI = `Detalles del error: Tipo: ${errorType}, Nombre: ${errorName}, Mensaje: ${errorMessage}`;
                        if (errorType === 'object' && err !== null) {
                            errorDetailsForUI += `. Propiedades: ${Object.keys(err).join(', ')}`;
                        } else if (errorValue) { 
                            errorDetailsForUI += `, Valor: ${errorValue}`;
                        }
                        resultDiv.innerHTML = `<div class="alert alert-warning">${userMessage}<br><small style="word-break: break-all;">${errorDetailsForUI}</small></div>`;
                    }
                }
                if (qrReaderDiv) qrReaderDiv.style.display = 'none';
            });
        });
    } else {
        console.error("Botón 'Escanear Código QR' (btn-scan-qr) no encontrado en el DOM."); 
    }
});