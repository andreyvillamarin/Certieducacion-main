<?php
// admin/settings_handler.php
require_once '../config.php';

// Seguridad: solo superadmin puede ejecutar esto
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    die('Acceso denegado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings.php');
    exit;
}

try {
    // Ruta al archivo de configuración
    $configFile = dirname(__DIR__) . '/config.php';
    
    // Leer el contenido actual del archivo
    $configContent = file_get_contents($configFile);
    if ($configContent === false) {
        throw new Exception("No se pudo leer el archivo de configuración.");
    }

    // Iterar sobre los datos del formulario y reemplazar los valores en el contenido
    foreach ($_POST as $key => $value) {
        $currentKey = $key; // Para usar dentro del callback
        $currentValue = $value; // Para usar dentro del callback

        // Si la clave es una de las claves API/contraseña y el valor está vacío, omitir la actualización.
        if (($currentKey === 'BREVO_SMTP_KEY' || $currentKey === 'ALTIRIA_PASSWORD') && empty(trim($currentValue))) {
            continue;
        }

        $configContent = preg_replace_callback(
            "/(define\s*\(\s*['\"]" . preg_quote($currentKey, '/') . "['\"]\s*,\s*)(['\"])(.*?)(\\2)\s*\);/",
            function ($matches) use ($currentValue) {
                // $matches[0] es la coincidencia completa
                // $matches[1] es define('KEY', 
                // $matches[2] es la comilla ( ' o " )
                // $matches[3] es el valor antiguo
                // $matches[4] es la comilla de cierre (debería ser igual a $matches[2])

                $escapedValue = addslashes($currentValue);
                return $matches[1] . $matches[2] . $escapedValue . $matches[2] . ');';
            },
            $configContent,
            1
        );
    }
    
    // Escribir el nuevo contenido de vuelta al archivo
    if (file_put_contents($configFile, $configContent) === false) {
        throw new Exception("No se pudo escribir en el archivo de configuración. Verifica los permisos.");
    }

    $_SESSION['notification'] = ['type' => 'success', 'message' => 'La configuración ha sido guardada correctamente.'];

} catch (Exception $e) {
    $_SESSION['notification'] = ['type' => 'danger', 'message' => 'Error al guardar la configuración: ' . $e->getMessage()];
}

header('Location: settings.php');
exit;
