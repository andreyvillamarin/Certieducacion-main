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
require_once 'includes/database.php';
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
        if (!isset($_POST['identification']) || empty($_POST['identification'])) { send_response(['success' => false, 'message' => 'El número de identificación es requerido.']); }
        
        $identification = trim($_POST['identification']);
        $ip_address = get_ip_address();

        // --- INICIO: PROTECCIÓN CONTRA FUERZA BRUTA ---
        if (check_rate_limit($pdo, $ip_address, $identification)) {
            send_response(['success' => false, 'message' => 'Has excedido el número de intentos permitidos. Por favor, espera 15 minutos.']);
        }
        // --- FIN: PROTECCIÓN CONTRA FUERZA BRUTA ---

        try {
            // CORRECCIÓN: No permitir login a estudiantes desactivados.
            $stmt = $pdo->prepare("SELECT id, name, phone, email FROM students WHERE identification = ? AND deleted_at IS NULL");
            $stmt->execute([$identification]);
            $student = $stmt->fetch();
            if ($student) {
                $response_data = ['id' => $student['id'], 'name' => $student['name']];
                if (!empty($student['email'])) {
                    $email_parts = explode('@', $student['email']); $name = $email_parts[0]; $domain = $email_parts[1];
                    $response_data['email_hint'] = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 4)) . substr($name, -2) . '@' . $domain;
                }
                if (!empty($student['phone'])) { $response_data['phone_hint'] = substr($student['phone'], -2); }
                send_response(['success' => true, 'student' => $response_data]);
            } else { 
                // --- INICIO: REGISTRO DE INTENTO FALLIDO ---
                record_failed_attempt($pdo, $ip_address, $identification);
                // --- FIN: REGISTRO DE INTENTO FALLIDO ---
                send_response(['success' => false, 'message' => 'La identificación ingresada no se encuentra registrada en nuestro sistema.']); 
            }
        } catch (PDOException $e) { error_log('Error en check_id: ' . $e->getMessage()); send_response(['success' => false, 'message' => 'Ocurrió un error en el servidor.']); }
        break;

    case 'send_code':
        if (!isset($_POST['student_id'], $_POST['verification_method'])) {
            send_response(['success' => false, 'message' => 'Faltan datos para enviar el código.']);
        }

        $student_id = $_POST['student_id'];
        $method = $_POST['verification_method'];

        try {
            // CORRECCIÓN: No enviar código a estudiantes desactivados.
            $stmt = $pdo->prepare("SELECT phone, email FROM students WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
            if (!$student) { send_response(['success' => false, 'message' => 'Estudiante no encontrado o inactivo.']); }

            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
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
            // CORRECCIÓN: Un certificado solo es válido si ni él ni el estudiante han sido eliminados.
            $sql = "SELECT c.course_name, c.issue_date, s.name as student_name, s.identification as student_id 
                    FROM certificates c 
                    JOIN students s ON c.student_id = s.id 
                    WHERE c.validation_code = ? AND c.deleted_at IS NULL AND s.deleted_at IS NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$validation_code]);
            $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($certificate) {
                // Prevenir XSS: escapar todos los datos antes de enviarlos
                $certificate_safe = array_map('htmlspecialchars', $certificate);
                $certificate_safe['issue_date'] = (new DateTime($certificate['issue_date']))->format('d/m/Y');
                send_response(['success' => true, 'certificate' => $certificate_safe]);
            } else { send_response(['success' => false, 'message' => 'El código no corresponde a un certificado válido.']); }
        } catch (PDOException $e) { error_log('Error en validate_certificate_code: ' . $e->getMessage()); send_response(['success' => false, 'message' => 'Ocurrió un error en el servidor al validar el certificado.']); }
        break;

    default:
        send_response(['success' => false, 'message' => 'Acción no válida.']);
        break;
}