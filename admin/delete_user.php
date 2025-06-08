<?php
session_start();
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