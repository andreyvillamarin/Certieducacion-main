<?php
// admin/includes/pdf_generator_func.php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
}

require_once ROOT_PATH . '/libs/TCPDF/tcpdf.php';
require_once ROOT_PATH . '/libs/PHPQRCode/qrlib.php';

/**
 * Crea un certificado en formato PDF a partir de datos y una plantilla.
 *
 * @param array $student_data Datos del estudiante (ej: ['name' => 'John Doe', 'identification' => '12345']).
 * @param string $course_name Nombre del curso.
 * @param string $duration Duración del curso.
 * @param string $issue_date_str Fecha de emisión (ej: '2023-12-25').
 * @param array $template_data Array decodificado del JSON de la plantilla del certificado.
 * @param string $validation_code El código de validación único para el certificado.
 * @return string El contenido del PDF como una cadena de texto.
 * @throws Exception Si ocurre un error durante la generación.
 */
function create_certificate_from_data(array $student_data, string $course_name, string $duration, string $issue_date_str, array $template_data, string $validation_code): string {
    
    $qr_temp_path = '';

    try {
        // 1. Generar QR temporalmente
        $qr_temp_path = tempnam(sys_get_temp_dir(), 'qr') . '.png';
        QRcode::png($validation_code, $qr_temp_path, QR_ECLEVEL_L, 4);

        // 2. Formatear fecha
        $date = new DateTime($issue_date_str, new DateTimeZone('America/Bogota'));
        $months_es = ["", "enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
        $issue_date_formatted = $date->format('d') . " días del mes de " . $months_es[(int)$date->format('n')] . " de " . $date->format('Y');

        // 3. Definir los reemplazos de texto dinámico
        $replacements = [
            '{{student_name}}' => htmlspecialchars($student_data['name']),
            '{{student_identification}}' => 'C.C. No. ' . number_format($student_data['identification'], 0, ',', '.'),
            '{{course_name}}' => htmlspecialchars($course_name),
            '{{duration}}' => 'Con una intensidad de ' . htmlspecialchars($duration) . ' horas',
            '{{issue_date}}' => 'Dado en Neiva a los ' . $issue_date_formatted,
            '{{validation_code}}' => 'Código: ' . $validation_code,
            '{{director_name}}' => "Nombre Director\nJEFE DE DIVISIÓN SERVICIOS EDUCATIVOS", // Placeholder
        ];

        // 4. Configurar e inicializar el objeto TCPDF
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Comfamiliar Huila');
        $pdf->SetTitle('Certificado - ' . htmlspecialchars($student_data['name']));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // 5. Lógica de renderizado de la plantilla (extraída de generate_certificate_handler.php)
        $pt_to_mm = 25.4 / 72;
        $canvas_width_pt = 842;
        $a4_width_mm = 297;
        $scale_factor = $a4_width_mm / $canvas_width_pt;

        // Dibujar fondo
        if (isset($template_data['backgroundImage']) && isset($template_data['backgroundImage']['src'])) {
            $bg_image_path = ROOT_PATH . '/' . $template_data['backgroundImage']['src'];
            if (file_exists($bg_image_path)) {
                $pdf->Image($bg_image_path, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
            }
        }

        foreach ($template_data['objects'] as $obj) {
            if (!isset($obj['data'])) continue;

            $x_mm = ($obj['left'] ?? 0) * $scale_factor;
            $y_mm = ($obj['top'] ?? 0) * $scale_factor;
            $width_mm = ($obj['width'] ?? 0) * ($obj['scaleX'] ?? 1) * $scale_factor;
            $height_mm = ($obj['height'] ?? 0) * ($obj['scaleY'] ?? 1) * $scale_factor;

            if (isset($obj['originX']) && $obj['originX'] === 'center') $x_mm -= $width_mm / 2;
            if (isset($obj['originY']) && $obj['originY'] === 'center') $y_mm -= $height_mm / 2;

            if (isset($obj['data']['isImagePlaceholder'])) {
                $field = $obj['data']['field'];
                $image_path = '';
                if ($field === 'qr_code') {
                    $image_path = $qr_temp_path;
                } elseif ($field === 'signature') {
                    // La firma debe ser manejada por la plantilla, aquí un ejemplo
                    $signature_file = 'director.png'; // Placeholder
                    $image_path = ROOT_PATH . '/assets/img/signatures/' . $signature_file;
                }

                if ($image_path && file_exists($image_path)) {
                    $pdf->Image($image_path, $x_mm, $y_mm, $width_mm, $height_mm, 'PNG', '', 'T', false, 300, '', false, false, 0);
                }
            } elseif (isset($obj['type']) && $obj['type'] === 'textbox') {
                $font_map = ['arial' => 'arial', 'helvetica' => 'helvetica', 'times new roman' => 'times', 'courier' => 'courier', 'verdana' => 'helvetica'];
                $font_family = $font_map[strtolower($obj['fontFamily'] ?? 'helvetica')] ?? 'helvetica';
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
                $x1_pt = $obj['left'] + ($obj['x1'] ?? -$obj['width']/2) * ($obj['scaleX'] ?? 1);
                $y1_pt = $obj['top'] + ($obj['y1'] ?? 0) * ($obj['scaleY'] ?? 1);
                $x2_pt = $obj['left'] + ($obj['x2'] ?? $obj['width']/2) * ($obj['scaleX'] ?? 1);
                $y2_pt = $obj['top'] + ($obj['y2'] ?? 0) * ($obj['scaleY'] ?? 1);

                $line_style = [];
                if (isset($obj['strokeWidth'])) $line_style['width'] = $obj['strokeWidth'] * (25.4 / 72);
                if (isset($obj['stroke'])) {
                    list($r, $g, $b) = sscanf($obj['stroke'], "#%02x%02x%02x");
                    $line_style['color'] = [$r, $g, $b];
                }
                $pdf->Line($x1_pt * $scale_factor, $y1_pt * $scale_factor, $x2_pt * $scale_factor, $y2_pt * $scale_factor, $line_style);
            }
        }

        // 6. Devolver el PDF como una cadena de texto
        return $pdf->Output(null, 'S');

    } finally {
        // 7. Limpiar el archivo QR temporal
        if (!empty($qr_temp_path) && file_exists($qr_temp_path)) {
            unlink($qr_temp_path);
        }
    }
}