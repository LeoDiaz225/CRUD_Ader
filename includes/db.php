<?php
$host = "localhost";
$user = "root";      
$pass = "";          
$db = "ader_db";
$port = 3307;

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    if (strpos($_SERVER['PHP_SELF'], 'environments/') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(["error" => "Conexión fallida: " . $conn->connect_error]);
        exit;
    } else {
        die("Conexión fallida: " . $conn->connect_error);
    }
}

// Agregar después de la conexión
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Agregar directorio de caché si no existe
if (!file_exists('cache')) {
    mkdir('cache', 0755, true);
}

// Mejorar la función de caché
function getCachedResult($key, $callback, $ttl = 300) {
    $cache_dir = __DIR__ . '/../cache/';
    $cache_file = $cache_dir . md5($key) . '.cache';
    
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $ttl) {
        $data = unserialize(file_get_contents($cache_file));
        if ($data !== false) {
            return $data;
        }
    }
    
    $result = $callback();
    file_put_contents($cache_file, serialize($result));
    return $result;
}
?>