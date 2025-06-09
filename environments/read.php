<?php
session_start();
header('Content-Type: application/json');

include "../includes/db.php";
include "../includes/Security.php";

// Validar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Sesión no válida']));
}

// Validar CSRF
if (!isset($_GET['csrf_token']) || !Security::validateCSRFToken($_GET['csrf_token'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Token CSRF inválido']));
}

try {
    $tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? '');
    if (!$tabla) {
        throw new Exception('Tabla no especificada');
    }

    // Verificar si la tabla existe
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param("s", $tabla);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Tabla no encontrada');
    }

    // Obtener registros
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT * FROM `$tabla` LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $registros = $result->fetch_all(MYSQLI_ASSOC);
    
    // Contar total de registros
    $total = $conn->query("SELECT COUNT(*) as total FROM `$tabla`")->fetch_assoc()['total'];
    
    echo json_encode([
        'registros' => $registros,
        'total' => $total,
        'pagina' => $page,
        'por_pagina' => $limit
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}