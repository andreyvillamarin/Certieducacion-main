<?php
// admin/generate_certificate_handler.php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
require_once ROOT_PATH . '/config.php';
require_once ROOT_PATH . '/includes/database.php';
require_once ROOT_PATH . '/includes/logger.php';
require_once ROOT_PATH . '/libs/PHPQRCode/qrlib.php';
require_once ROOT_PATH . '/libs/TCPDF/tcpdf.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['student_ids'])) {
    $_SESSION['notification'] = ['type' => 'danger', 'message' => 'No se seleccionó ningún estudiante o la solicitud es incorrecta.'];
    header('Location: certificates.php');
    exit;
}

$student_ids = $_POST['student_ids'];
$course_name = trim($_POST['course_name'] ?? 'Curso Desconocido');
$duration = trim($_POST['duration'] ?? 'N/A');
$issue_date_str = $_POST['issue_date'] ?? date('Y-m-d');
// La firma ahora se gestiona desde el diseñador, pero mantenemos una por defecto.
$signature_file = 'director.png'; 

$success_count = 0;
$error_count = 0;

$uploads_dir_abs = ROOT_PATH . '/uploads';
$certificates_generated_dir_abs = ROOT_PATH . '/certificates_generated';
$admin_dir_abs = ROOT_PATH . '/admin';

if (!is_dir($uploads_dir_abs)) { @mkdir($uploads_dir_abs, 0775, true); }
if (!is_dir($certificates_generated_dir_abs)) { @mkdir($certificates_generated_dir_abs, 0775, true); }

// --- Cargar plantilla ---
$template_path = $admin_dir_abs . '/certificate_template.json';
if (!file_exists($template_path)) {
    $_SESSION['notification'] = ['type' => 'danger', 'message' => 'No se encontró el archivo de plantilla (certificate_template.json). Diseñe y guarde la plantilla primero.'];
    header('Location: certificates.php');
    exit;
}
$template_json = file_get_contents($template_path);
$template_data = json_decode($template_json, true);


foreach ($student_ids as $student_id) {
    $student_id = (int) $student_id;
    $qr_absolute_path = '';

    try {
        $stmt = $pdo->prepare("SELECT name, identification FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            error_log("Estudiante ID {$student_id} no encontrado.");
            $error_count++;
            continue;
        }

        $validation_code = 'CERT-' . strtoupper(uniqid()) . '-' . date('Y');
        $pdf_filename_only = 'certificado-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $student['identification']) . '-' . time() . '.pdf';
        
        $qr_absolute_path = $uploads_dir_abs . '/qr_' . $validation_code . '.png';
        $pdf_absolute_save_path = $certificates_generated_dir_abs . '/' . $pdf_filename_only;
        $pdf_url_path_for_db = 'certificates_generated/' . $pdf_filename_only; 
        
        QRcode::png($validation_code, $qr_absolute_path, QR_ECLEVEL_L, 4);

        $date = new DateTime($issue_date_str, new DateTimeZone('America/Bogota'));
        $months_es = ["", "enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
        $issue_date_formatted = $date->format('d') . " días del mes de " . $months_es[(int)$date->format('n')] . " de " . $date->format('Y');

        $replacements = [
            '{{student_name}}' => htmlspecialchars($student['name']),
            '{{student_identification}}' => 'C.C. No. ' . number_format($student['identification'], 0, ',', '.'),
            '{{course_name}}' => htmlspecialchars($course_name),
            '{{duration}}' => 'Con una intensidad de ' . htmlspecialchars($duration) . ' horas',
            '{{issue_date}}' => 'Dado en Neiva a los ' . $issue_date_formatted,
            '{{validation_code}}' => 'Código: ' . $validation_code,
            '{{director_name}}' => "Nombre Director\nJEFE DE DIVISIÓN SERVICIOS EDUCATIVOS", // Placeholder
        ];

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Comfamiliar Huila');
        $pdf->SetTitle('Certificado - ' . htmlspecialchars($student['name']));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // Conversión de puntos (Fabric.js) a mm (TCPDF)
        $pt_to_mm = 25.4 / 72;
        $canvas_width_pt = 842; // The design canvas is always 842pt wide.
        $a4_width_mm = 297;
        $scale_factor = $a4_width_mm / $canvas_width_pt;

        // Dibujar fondo
        if (isset($template_data['backgroundImage']) && isset($template_data['backgroundImage']['src'])) {
            $bg_image_path = ROOT_PATH . '/' . $template_data['backgroundImage']['src'];
            if (file_exists($bg_image_path)) {
                 $pdf->Image($bg_image_path, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
            } else {
                 error_log("No se encontró la imagen de fondo: " . $bg_image_path);
            }
        }
        
        foreach ($template_data['objects'] as $obj) {
            if (!isset($obj['data'])) continue;

            $x_mm = ($obj['left'] ?? 0) * $scale_factor;
            $y_mm = ($obj['top'] ?? 0) * $scale_factor;
            $width_mm = ($obj['width'] ?? 0) * ($obj['scaleX'] ?? 1) * $scale_factor;
            $height_mm = ($obj['height'] ?? 0) * ($obj['scaleY'] ?? 1) * $scale_factor;

            // Ajuste para la alineación del texto y origen del objeto
            if (isset($obj['originX']) && $obj['originX'] === 'center') {
                $x_mm -= $width_mm / 2;
            }
            if (isset($obj['originY']) && $obj['originY'] === 'center') {
                $y_mm -= $height_mm / 2;
            }
            
            if (isset($obj['data']['isImagePlaceholder'])) {
                $field = $obj['data']['field'];
                $image_path = '';
                if ($field === 'qr_code') {
                    $image_path = $qr_absolute_path;
                } elseif ($field === 'signature') {
                    $image_path = ROOT_PATH . '/assets/img/signatures/' . $signature_file;
                }

                if ($image_path && file_exists($image_path)) {
                    $pdf->Image($image_path, $x_mm, $y_mm, $width_mm, $height_mm, 'PNG', '', 'T', false, 300, '', false, false, 0);
                }
            } elseif (isset($obj['type']) && $obj['type'] === 'textbox') {
                $font_map = [
                    'arial' => 'arial',
                    'helvetica' => 'helvetica',
                    'times new roman' => 'times',
                    'courier' => 'courier',
                    'verdana' => 'helvetica' // Fallback for Verdana
                ];

                $font_family_from_json = strtolower($obj['fontFamily'] ?? 'helvetica');
                $font_family = $font_map[$font_family_from_json] ?? 'helvetica';
                
                $font_style = '';
                if (isset($obj['fontWeight']) && ($obj['fontWeight'] === 'bold' || $obj['fontWeight'] === 700)) $font_style .= 'B';
                if (isset($obj['fontStyle']) && $obj['fontStyle'] === 'italic') $font_style .= 'I';
                $pdf->SetFont($font_family, $font_style, $obj['fontSize'] ?? 12);
                
                if (isset($obj['fill'])) {
                    list($r, $g, $b) = sscanf($obj['fill'], "#%02x%02x%02x");
                    $pdf->SetTextColor($r, $g, $b);
                } else {
                    $pdf->SetTextColor(0, 0, 0);
                }

                $text = $obj['text'] ?? '';
                if (isset($obj['data']['isDynamic']) && $obj['data']['isDynamic']) {
                    // Iterar sobre todos los reemplazos posibles para este campo de texto
                    foreach ($replacements as $placeholder => $value) {
                        $text = str_ireplace($placeholder, $value, $text);
                    }
                }

                if (isset($obj['data']['isUppercase']) && $obj['data']['isUppercase']) {
                    $text = strtoupper($text);
                }
                
                $pdf->SetXY($x_mm, $y_mm);
                $align = strtoupper(substr($obj['textAlign'] ?? 'L', 0, 1));
                $pdf->MultiCell($width_mm, $height_mm, $text, 0, $align, false, 1, '', '', true, 0, false, true, 0, 'T', false);
            } elseif (isset($obj['type']) && $obj['type'] === 'line') {
                $scaleX = $obj['scaleX'] ?? 1;
                $scaleY = $obj['scaleY'] ?? 1;
                
                // A horizontal line is defined by its center (left, top) and its width.
                // FabricJS line x1, x2 are relative to the center.
                $x1_pt = $obj['left'] + ($obj['x1'] ?? -$obj['width']/2) * $scaleX;
                $y1_pt = $obj['top'] + ($obj['y1'] ?? 0) * $scaleY;
                $x2_pt = $obj['left'] + ($obj['x2'] ?? $obj['width']/2) * $scaleX;
                $y2_pt = $obj['top'] + ($obj['y2'] ?? 0) * $scaleY;

                // Convert to mm
                $x1_mm = $x1_pt * $scale_factor;
                $y1_mm = $y1_pt * $scale_factor;
                $x2_mm = $x2_pt * $scale_factor;
                $y2_mm = $y2_pt * $scale_factor;
                
                // Set line style
                $line_style = [];
                if (isset($obj['strokeWidth'])) {
                    $line_style['width'] = $obj['strokeWidth'] * $pt_to_mm; // Use pt to mm for stroke
                }
                if (isset($obj['stroke'])) {
                    $hex = ltrim($obj['stroke'], '#');
                    if (strlen($hex) == 3) {
                        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
                        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
                        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
                    } else {
                        $r = hexdec(substr($hex, 0, 2));
                        $g = hexdec(substr($hex, 2, 2));
                        $b = hexdec(substr($hex, 4, 2));
                    }
                    $line_style['color'] = [$r, $g, $b];
                }
                
                $pdf->Line($x1_mm, $y1_mm, $x2_mm, $y2_mm, $line_style);
            }
        }

        $pdf->Output($pdf_absolute_save_path, 'F');
        
        $admin_id = $_SESSION['admin_id'] ?? null;
        $stmt_insert = $pdo->prepare("INSERT INTO certificates (student_id, course_name, issue_date, validation_code, pdf_path, generated_by_user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert->execute([$student_id, $course_name, $issue_date_str, $validation_code, $pdf_url_path_for_db, $admin_id]);
        $certificate_id = $pdo->lastInsertId();
        
        $log_details = "Certificado generado para estudiante: {$student['name']} (ID: {$student_id}), Curso: {$course_name}, Código: {$validation_code}";
        log_activity($pdo, $admin_id, 'certificado_creado', $certificate_id, 'certificates', $log_details);

        $success_count++;

    } catch (Exception $e) {
        $error_count++;
        error_log("Error al generar certificado para estudiante ID {$student_id}: " . $e->getMessage());
    } finally {
        if (!empty($qr_absolute_path) && file_exists($qr_absolute_path)) {
            unlink($qr_absolute_path);
        }
    }
}

$message = "Proceso completado. Certificados generados: {$success_count}. Errores: {$error_count}.";
if ($error_count > 0) {
    $message .= " Revise el log de errores del servidor para más detalles.";
}
$_SESSION['notification'] = ['type' => $error_count > 0 ? 'warning' : 'success', 'message' => $message];
header('Location: certificates.php');
exit;
?>