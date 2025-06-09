<?php
session_start();
include "../includes/Security.php";

function validarSesion() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Sesión no válida']));
    }
}

validarSesion();

// Validar CSRF
if (!isset($_POST['csrf_token']) && !isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Token CSRF no proporcionado']));
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'];
if (!Security::validateCSRFToken($token)) {
    http_response_code(403);
    die(json_encode(['error' => 'Token CSRF inválido']));
}

// Agregar validación CSRF y Rate Limiting
if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
    die('Token CSRF inválido');
}

if (!RateLimit::check($_SESSION['user_id'], 'create_environment', 10)) {
    http_response_code(429);
    die('Demasiadas solicitudes');
}

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

include "../includes/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $nombre = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($data['nombre'])));
    $campos = $data['campos'];

    if ($nombre && is_array($campos)) {
        $conn->begin_transaction();
        
        try {
            // Registrar entorno
            $stmt = $conn->prepare("INSERT INTO entornos (nombre) VALUES (?)");
            $stmt->bind_param("s", $nombre);
            $stmt->execute();

            // Crear tabla dinámica
            $sql = "CREATE TABLE `$nombre` (id INT AUTO_INCREMENT PRIMARY KEY";
            
            // Registrar campos
            $stmt = $conn->prepare("INSERT INTO entornos_campos 
                (entorno_nombre, nombre_campo, tipo_campo, es_requerido, orden) 
                VALUES (?, ?, ?, ?, ?)");

            foreach ($campos as $index => $campo) {
                // Determinar tipo de columna SQL
                $tipoDB = match($campo['tipo']) {
                    'numero' => 'INT',
                    'email' => 'VARCHAR(100)',
                    'fecha' => 'DATE',
                    default => 'VARCHAR(255)'
                };

                $sql .= sprintf(",\n%s %s", 
                    $campo['nombre'],
                    $tipoDB
                );

                $stmt->bind_param("sssii", 
                    $nombre,
                    $campo['nombre'],
                    $campo['tipo'],
                    $campo['requerido'],
                    $index
                );
                $stmt->execute();
            }

            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($sql);

            $conn->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    exit;
}
