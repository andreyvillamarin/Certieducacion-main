<?php
// includes/security_functions.php

const MAX_ATTEMPTS = 5;
const ATTEMPT_WINDOW_MINUTES = 15;

/**
 * Verifica si se ha excedido el límite de intentos para una IP o identificador.
 */
function check_rate_limit(PDO $pdo, string $ip_address, string $identifier): bool {
    $time_limit = (new DateTime())->sub(new DateInterval('PT' . ATTEMPT_WINDOW_MINUTES . 'M'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts WHERE 
        (ip_address = :ip OR identifier = :id) AND attempt_time > :time_limit"
    );
    $stmt->execute([':ip' => $ip_address, ':id' => $identifier, ':time_limit' => $time_limit]);
    
    return $stmt->fetchColumn() >= MAX_ATTEMPTS;
}

/**
 * Registra un intento fallido.
 */
function record_failed_attempt(PDO $pdo, string $ip_address, string $identifier): void {
    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (ip_address, identifier, attempt_time) VALUES (:ip, :id, NOW())"
    );
    $stmt->execute([':ip' => $ip_address, ':id' => $identifier]);
}

/**
 * Limpia los intentos fallidos para una IP e identificador.
 */
function clear_failed_attempts(PDO $pdo, string $ip_address, string $identifier): void {
    $stmt = $pdo->prepare(
        "DELETE FROM login_attempts WHERE ip_address = :ip OR identifier = :id"
    );
    $stmt->execute([':ip' => $ip_address, ':id' => $identifier]);
}

/**
 * Obtiene la dirección IP real del cliente.
 */
function get_ip_address(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
