<?php
session_start();

include "../includes/Security.php";
validarSesion();

// Agregar Rate Limiting y CSRF
if (!RateLimit::check($_SESSION['user_id'], 'delete_user', 5)) {
    http_response_code(429);
    die(json_encode(['error' => 'Demasiadas solicitudes']));
}

if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
    die(json_encode(['error' => 'Token CSRF inválido']));
}

// Logging
Security::logAction($_SESSION['user_id'], 'delete_user', "Usuario eliminado: $user_id");

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");


include "../includes/db.php";

header('Content-Type: application/json');

if ($_SESSION['rol'] !== 'admin') {
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? 0;
    
    // Evitar que un admin se elimine a sí mismo
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['error' => 'No puedes eliminarte a ti mismo']);
        exit;
    }
    
    // Verificar que no sea el último administrador
    $check_admin = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $check_admin->bind_param("i", $user_id);
    $check_admin->execute();
    $result = $check_admin->get_result();
    $user = $result->fetch_assoc();
    
    if ($user['rol'] === 'admin') {
        $admin_count = $conn->query("SELECT COUNT(*) as count FROM usuarios WHERE rol = 'admin'")->fetch_assoc()['count'];
        if ($admin_count <= 1) {
            echo json_encode(['error' => 'No se puede eliminar el último administrador']);
            exit;
        }
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al eliminar usuario: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Método no permitido']);