<?php
// certieducacion3/config.php (en la raíz web)

// Define la ruta raíz del proyecto (donde se encuentra este archivo)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

// Define la ruta al directorio seguro donde se encuentran los archivos de configuración
// ¡ASEGÚRATE DE QUE ESTA RUTA SEA CORRECTA PARA TU SERVIDOR!
define('SECURE_CONFIG_DIR', '/home/qdosnetw/certieducacion4_secure_configs');

// Carga el archivo de configuración principal desde el directorio seguro
require_once SECURE_CONFIG_DIR . '/main_config.php';

// El resto de la configuración (sesión, cabeceras de seguridad, etc.)
// ahora se maneja en main_config.php, que es cargado desde aquí.

?>