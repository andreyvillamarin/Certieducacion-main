<?php
// certificates/download.php - Endpoint para descargar archivos PDF de certificados

// No incluimos db_connect.php aquí porque no necesitamos la base de datos para servir un archivo.
// Sin embargo, si quisieras añadir lógica de seguridad (ej. verificar permisos), la necesitarías.

// Definir la ruta base donde se guardan los PDFs
// ¡AJUSTA ESTA RUTA A LA UBICACIÓN REAL DE TUS PDFs EN EL HOSTING B!
define('PDF_STORAGE_PATH', '/home/qdosnet/public_html/pdf_certificates/');
// Ejemplo: /home/USUARIO_CPANEL/public_html/TU_DOMINIO/pdfs_certificados/
// Asegúrate de que este directorio exista y tenga permisos de lectura para el servidor web.

$filename = isset($_GET['filename']) ? basename($_GET['filename']) : '';

if (empty($filename)) {
    http_response_code(400);
    echo "Error: Nombre de archivo no especificado.";
    exit();
}

$filepath = PDF_STORAGE_PATH . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    echo "Error: Archivo no encontrado.";
    exit();
}

// Asegurarse de que el archivo es un PDF
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

if ($mime_type !== 'application/pdf') {
    http_response_code(403); // Forbidden
    echo "Error: Tipo de archivo no permitido.";
    exit();
}

// Enviar el archivo al navegador
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit();
?>