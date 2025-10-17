<?php
/**
 * ajax_handler.php
 *
 * Procesa todas las peticiones AJAX del frontend.
 * Integrado con Brevo para Email y Altiria para SMS.
 */

// Incluir la configuración global (importante que sea lo primero)
require_once 'config.php'; // O la ruta correcta a config.php

// Incluir la conexión a la base de datos y la configuración
// require_once 'includes/database.php'; // No longer needed for student lookup
require_once 'includes/security_functions.php';

// -- VALIDACIÓN DE TOKEN CSRF --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token inválido o ausente
        send_response(['success' => false, 'message' => 'Error de seguridad. Por favor, recarga la página e inténtalo de nuevo.']);
        exit;
    }
}


// Cargar bibliotecas necesarias
require_once 'libs/PHPMailer/Exception.php';
require_once 'libs/PHPMailer/PHPMailer.php';
require_once 'libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Establecer la cabecera de la respuesta como JSON
header('Content-Type: application/json');

// Función para enviar la respuesta y terminar la ejecución
function send_response($data) {
    echo json_encode($data);
    exit;
}

// Verificar que se haya enviado una acción
if (!isset($_POST['action'])) {
    send_response(['success' => false, 'message' => 'Acción no especificada.']);
}

$action = $_POST['action'];

switch ($action) {
    case 'check_id':
        if (!isset($_POST['identification']) || empty($_POST['identification'])) {
            send_response(['success' => false, 'message' => 'El número de identificación es requerido.']);
        }

        $identification = trim($_POST['identification']);
        $ip_address = get_ip_address();

        // --- Mantenemos la protección contra fuerza bruta ---
        if (check_rate_limit($pdo, $ip_address, $identification)) {
            send_response(['success' => false, 'message' => 'Has excedido el número de intentos permitidos. Por favor, espera 15 minutos.']);
        }

        // --- INICIO DE LA NUEVA LÓGICA CON API EXTERNA ---
        try {
            // 1. Define la URL de tu API externa.
            $api_url = API_BASE_URL . 'certificates/student/' . urlencode($identification);

            // 2. Inicializa cURL para hacer la petición a la API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Devuelve la respuesta como string
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Tiempo de espera de 10 segundos
            // Opcional: Si tu API requiere un token de autenticación, lo añades aquí
            // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer TU_API_KEY']);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 3. Procesa la respuesta de la API
            if ($http_code == 200) {
                // Éxito: El estudiante fue encontrado en la API
                $certificates_data = json_decode($response, true);

                if (empty($certificates_data)) {
                    // No certificates found for this student
                    record_failed_attempt($pdo, $ip_address, $identification);
                    send_response(['success' => false, 'message' => 'La identificación ingresada no se encuentra registrada en nuestro sistema o no tiene certificados asociados.']);
                }
                $student_data_from_api = $certificates_data[0]['student']; // Get student data from the first certificate

                // Adaptamos la respuesta para que coincida con lo que el frontend espera.
                // La API debería devolver 'id', 'name', 'email', 'phone'.
                $response_data = [
                    'id' => $student_data_from_api['id'], // Asume que la API devuelve un 'id'
                    'name' => $student_data_from_api['name']
                ];
                if (!empty($student_data_from_api['email'])) {
                    $email_parts = explode('@', $student_data_from_api['email']);
                    $name = $email_parts[0];
                    $domain = $email_parts[1];
                    $response_data['email_hint'] = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 4)) . substr($name, -2) . '@' . $domain;
                }
                if (!empty($student_data_from_api['phone'])) {
                    $response_data['phone_hint'] = substr($student_data_from_api['phone'], -2);
                }
                
                // IMPORTANTE: Para que el siguiente paso (enviar código) funcione,
                // necesitamos guardar temporalmente los datos del estudiante en la sesión
                // o tener una forma de recuperarlos sin consultar la BD local.
                // Por ahora, asumimos que la API es la única fuente de verdad.
                // El `send_code` también necesitará ser modificado.
                
                // Guardamos los datos necesarios para el siguiente paso
                $_SESSION['external_student_data'] = [
                    'id' => $student_data_from_api['id'],
                    'email' => $student_data_from_api['email'],
                    'phone' => $student_data_from_api['phone']
                ];


                send_response(['success' => true, 'student' => $response_data]);

            } else {
                // Error: El estudiante no fue encontrado (404) o hubo otro error en la API
                record_failed_attempt($pdo, $ip_address, $identification);
                send_response(['success' => false, 'message' => 'La identificación ingresada no se encuentra registrada en nuestro sistema.']);
            }

        } catch (Exception $e) {
            error_log('Error en check_id con API externa: ' . $e->getMessage());
            send_response(['success' => false, 'message' => 'Ocurrió un error en el servidor.']);
        }
        // --- FIN DE LA NUEVA LÓGICA ---
        break;

    case 'send_code':
        if (!isset($_POST['student_id'], $_POST['verification_method'])) {
            send_response(['success' => false, 'message' => 'Faltan datos para enviar el código.']);
        }

        $student_id = $_POST['student_id'];
        $method = $_POST['verification_method'];

        // --- INICIO DE LA NUEVA LÓGICA SIN CONSULTA A BD ---
        // Verificamos que los datos del estudiante externo existan en la sesión
        if (!isset($_SESSION['external_student_data']) || $_SESSION['external_student_data']['id'] != $student_id) {
            send_response(['success' => false, 'message' => 'No se encontraron datos del estudiante. Por favor, vuelve a introducir tu identificación.']);
        }
        
        // Usamos los datos de la sesión en lugar de consultar la base de datos
        $student = $_SESSION['external_student_data'];
        // --- FIN DE LA NUEVA LÓGICA ---

        try {
            // El resto del código permanece casi igual, pero usando la variable $student que hemos creado
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
            
            // IMPORTANTE: Esta inserción en 'verification_codes' se mantiene en la base de datos local.
            // Esto es aceptable, ya que los códigos son temporales.
            $stmt_insert = $pdo->prepare("INSERT INTO verification_codes (student_id, code, method, expires_at) VALUES (?, ?, ?, ?)");
            $stmt_insert->execute([$student_id, $code, $method, $expires_at]);

            if ($method === 'email') {
                if (empty($student['email'])) send_response(['success' => false, 'message' => 'No hay email registrado para este usuario.']);
                
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = BREVO_SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = BREVO_SMTP_USER;
                $mail->Password   = BREVO_SMTP_KEY;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = BREVO_SMTP_PORT;
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($student['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Tu codigo de verificacion';
                $mail->Body    = "Hola,<br><br>Tu código de verificación para el portal de certificados es: <b>$code</b><br><br>Este código expirará en 10 minutos.";
                $mail->CharSet = 'UTF-8';
                $mail->send();

            } elseif ($method === 'sms') {
                if (empty($student['phone'])) { send_response(['success' => false, 'message' => 'No hay teléfono registrado para este usuario.']); }

                $url = 'http://www.altiria.net/api/http';
                $params = [
                    'cmd' => 'sendsms', 'domainId' => 'test', 'login' => ALTIRIA_LOGIN, 'passwd' => ALTIRIA_PASSWORD,
                    'dest' => '57' . $student['phone'], 'msg' => "CertiEducacion: Tu codigo de verificacion es $code",
                ];
                if (!empty(ALTIRIA_SENDER_ID)) { $params['senderId'] = ALTIRIA_SENDER_ID; }
                $request_url = $url . '?' . http_build_query($params);
                $response = @file_get_contents($request_url);

                if ($response === FALSE || strpos($response, 'ERROR') === 0) {
                    error_log("Error de Altiria: " . $response);
                    throw new Exception('No se pudo enviar el SMS.');
                }
            }

            send_response(['success' => true, 'message' => 'Código enviado con éxito.']);

        } catch (Exception $e) {
            error_log("Error al enviar código: " . $e->getMessage());
            send_response(['success' => false, 'message' => 'No se pudo enviar el código de verificación. Por favor, contacta a soporte.']);
        }
        break;

    case 'verify_code':
        if (!isset($_POST['student_id'], $_POST['verification_code'])) { send_response(['success' => false, 'message' => 'Faltan datos para verificar el código.']); }
        
        $student_id = $_POST['student_id']; 
        $code = trim($_POST['verification_code']);
        $ip_address = get_ip_address();
        $identifier = 'student_' . $student_id; // Usar un identificador único para el estudiante

        // --- INICIO: PROTECCIÓN CONTRA FUERZA BRUTA ---
        if (check_rate_limit($pdo, $ip_address, $identifier)) {
            send_response(['success' => false, 'message' => 'Has excedido el número de intentos permitidos. Por favor, espera 15 minutos.']);
        }
        // --- FIN: PROTECCIÓN CONTRA FUERZA BRUTA ---

        if (strlen($code) !== 6 || !ctype_digit($code)) { 
            record_failed_attempt($pdo, $ip_address, $identifier);
            send_response(['success' => false, 'message' => 'El formato del código es inválido.']); 
        }

        try {
            $stmt = $pdo->prepare("SELECT id, expires_at FROM verification_codes WHERE student_id = ? AND code = ? AND is_used = 0 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$student_id, $code]); 
            $verification = $stmt->fetch();

            if (!$verification) { 
                record_failed_attempt($pdo, $ip_address, $identifier);
                send_response(['success' => false, 'message' => 'El código es incorrecto o ya ha sido utilizado.']); 
            }

            $now = new DateTime(); 
            $expires = new DateTime($verification['expires_at']);
            if ($now > $expires) { 
                record_failed_attempt($pdo, $ip_address, $identifier);
                send_response(['success' => false, 'message' => 'Este código de verificación ha expirado.']); 
            }

            // CORRECCIÓN: Verificar que el estudiante sigue activo antes de iniciar sesión
            $stmt_check_student = $pdo->prepare("SELECT id FROM students WHERE id = ? AND deleted_at IS NULL");
            $stmt_check_student->execute([$student_id]);
            if (!$stmt_check_student->fetch()) {
                send_response(['success' => false, 'message' => 'La cuenta de este estudiante ha sido desactivada.']);
            }

            // Si el código es correcto, se limpia el registro de intentos y se procede con el login.
            clear_failed_attempts($pdo, $ip_address, $identifier);

            $stmt = $pdo->prepare("UPDATE verification_codes SET is_used = 1 WHERE id = ?");
            $stmt->execute([$verification['id']]);
            
            error_log("Antes de establecer la sesión: " . print_r($_SESSION, true));
            session_regenerate_id(true);
            $_SESSION['student_id'] = $student_id;
            $_SESSION['logged_in'] = true;
            error_log("Después de establecer la sesión: " . print_r($_SESSION, true));

            send_response(['success' => true, 'redirect_url' => 'my-certificates.php']);
        } catch (PDOException $e) { 
            error_log('Error en verify_code: ' . $e->getMessage()); 
            send_response(['success' => false, 'message' => 'Ocurrió un error en el servidor.']); 
        }
        break;

    case 'validate_certificate_code':
        if (!isset($_POST['validation_code']) || empty($_POST['validation_code'])) { send_response(['success' => false, 'message' => 'El código de validación es requerido.']); }
        $validation_code = trim($_POST['validation_code']);
        try {
            $api_url = API_BASE_URL . 'certificates/validate.php?code=' . urlencode($validation_code);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);

            // Manejo de errores de cURL
            if ($response === false) {
                $curl_error = curl_error($ch);
                curl_close($ch); // Cerrar el handle aquí
                error_log('cURL error en validate_certificate_code: ' . $curl_error);
                send_response(['success' => false, 'message' => 'Error de comunicación con el servicio de validación.']);
            }

            // Obtener info y cerrar handle SOLO si no hubo error
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code == 200) {
                $certificate_data = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($certificate_data) && !isset($certificate_data['error'])) {
                    $certificate_safe = [
                        'course_name' => htmlspecialchars($certificate_data['course']['name']),
                        'issue_date' => (new DateTime($certificate_data['issue_date']))->format('d/m/Y'),
                        'student_name' => htmlspecialchars($certificate_data['student']['name']),
                        'student_id' => htmlspecialchars($certificate_data['student']['identification'])
                    ];
                    send_response(['success' => true, 'certificate' => $certificate_safe]);
                } else {
                    // API devolvió 200 pero con un error en el cuerpo JSON o vacío
                    send_response(['success' => false, 'message' => 'El código no corresponde a un certificado válido.']);
                }
            } else {
                // El código HTTP no fue 200
                send_response(['success' => false, 'message' => 'El código no corresponde a un certificado válido.']);
            }

        } catch (Exception $e) {
            error_log('Excepción en validate_certificate_code: ' . $e->getMessage());
            send_response(['success' => false, 'message' => 'Ocurrió un error en el servidor al validar el certificado.']);
        }
        break;

    default:
        send_response(['success' => false, 'message' => 'Acción no válida.']);
        break;
}