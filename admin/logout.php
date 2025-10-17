<?php
// admin/logout.php (Dentro de la carpeta de administración)

// Se necesita subir un nivel para encontrar config.php
require_once '../config.php';

// Destruir todas las variables de la sesión (es seguro destruir todo).
// session_destroy() se encargará de limpiar $_SESSION['admin_id'], etc.
session_destroy();

// Redirigir a la página de login DEL ADMIN
header("Location: index.php");
exit;
?>