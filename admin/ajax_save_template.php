<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) { exit(json_encode(['success' => false, 'message' => 'Acceso no autorizado.'])); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit(json_encode(['success' => false, 'message' => 'Método no permitido.'])); }

define('ROOT_PATH', dirname(__DIR__));
$signatures_dir = ROOT_PATH . '/assets/img/signatures/';
$backgrounds_dir = ROOT_PATH . '/assets/img/';
$template_file_path = __DIR__ . '/certificate_template.json';

$json_string = $_POST['template_json'] ?? null;
if (empty($json_string)) { exit(json_encode(['success' => false, 'message' => 'No se recibieron datos.'])); }

$template_data = json_decode($json_string, true);
if (json_last_error() !== JSON_ERROR_NONE) { exit(json_encode(['success' => false, 'message' => 'JSON inválido.'])); }

if (isset($_FILES['signature_image'])) {
    try {
        $file = $_FILES['signature_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) { throw new RuntimeException('Error en subida de firma.'); }
        if ((new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) !== 'image/png') { throw new RuntimeException('Firma debe ser PNG.'); }
        if (!move_uploaded_file($file['tmp_name'], $signatures_dir . 'director.png')) { throw new RuntimeException('No se pudo mover la firma.'); }
    } catch (RuntimeException $e) { exit(json_encode(['success' => false, 'message' => $e->getMessage()])); }
}

if (isset($_FILES['background_image'])) {
     try {
        $file = $_FILES['background_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) { throw new RuntimeException('Error en subida de fondo.'); }
        $ext = array_search((new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']), ['jpg' => 'image/jpeg', 'png' => 'image/png'], true);
        if ($ext === false) { throw new RuntimeException('Formato de fondo no válido.'); }
        $new_filename = 'certificate_background_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $backgrounds_dir . $new_filename)) { throw new RuntimeException('No se pudo mover el fondo.'); }
        if (!isset($template_data['backgroundImage']) || !is_array($template_data['backgroundImage'])) {
            $template_data['backgroundImage'] = [];
        }
        $template_data['backgroundImage']['src'] = 'assets/img/' . $new_filename;
        $template_data['backgroundImage']['type'] = 'image';
    } catch (RuntimeException $e) { exit(json_encode(['success' => false, 'message' => $e->getMessage()])); }
}

$updated_json_string = json_encode($template_data, JSON_PRETTY_PRINT);
if (file_put_contents($template_file_path, $updated_json_string)) {
    echo json_encode(['success' => true, 'message' => 'Plantilla guardada.', 'new_template_json' => $template_data]);
} else {
    echo json_encode(['success' => false, 'message' => 'No se pudo escribir en archivo.']);
}