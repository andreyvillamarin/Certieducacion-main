<?php
// libs/autoloader.php
spl_autoload_register(function ($class) {
    // Define an array of namespace prefixes and their base directories.
    $prefixes = [
        'chillerlan\\QRCode\\'   => __DIR__ . '/QRCode/',
        'chillerlan\\Settings\\' => __DIR__ . '/Settings/', // New dependency
    ];

    // Iterate through the prefixes to find a match.
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});
