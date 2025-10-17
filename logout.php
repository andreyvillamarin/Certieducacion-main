<?php
// /logout.php (Raíz del proyecto)

require_once 'config.php'; // Llama a config.php en la misma carpeta

// Destruir todas las variables de sesión.
$_SESSION = [];

// Si se desea destruir la sesión completamente, borre también la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir a la página de inicio PÚBLICA
header("Location: index.php");
exit;
?>