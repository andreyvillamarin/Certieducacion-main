<?php
require_once 'config.php';
require_once 'includes/database.php';

echo "--- Iniciando pruebas ---\n";

// Prueba 1: Conexión a la base de datos
if ($pdo) {
    echo "Prueba 1: Conexión a la base de datos exitosa.\n";
} else {
    echo "Prueba 1: Error en la conexión a la base de datos.\n";
    exit;
}

// Prueba 2: Consultar un estudiante
try {
    $stmt = $pdo->prepare("SELECT * FROM students LIMIT 1");
    $stmt->execute();
    $student = $stmt->fetch();
    if ($student) {
        echo "Prueba 2: Consulta de estudiante exitosa.\n";
    } else {
        echo "Prueba 2: No se encontraron estudiantes, pero la consulta fue exitosa.\n";
    }
} catch (PDOException $e) {
    echo "Prueba 2: Error al consultar estudiante: " . $e->getMessage() . "\n";
}

// Prueba 3: Simular inicio de sesión de administrador
try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    if ($admin) {
        echo "Prueba 3: Consulta de administrador exitosa.\n";
    } else {
        echo "Prueba 3: No se encontró el administrador 'admin', pero la consulta fue exitosa.\n";
    }
} catch (PDOException $e) {
    echo "Prueba 3: Error al consultar administrador: " . $e->getMessage() . "\n";
}

echo "--- Pruebas finalizadas ---\n";
?>
