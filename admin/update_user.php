<?php
session_start();
include "../includes/db.php";

header('Content-Type: application/json');

if ($_SESSION['rol'] !== 'admin') {
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if (!$user_id) {
        echo json_encode(['error' => 'ID de usuario inválido']);
        exit;
    }

    $rol = $_POST['rol'] ?? 'user';
    
    // Convertir los valores a enteros
    $puede_crear_entorno = isset($_POST['puede_crear_entorno']) ? 1 : 0;
    $puede_eliminar_entorno = isset($_POST['puede_eliminar_entorno']) ? 1 : 0;
    $puede_editar_entorno = isset($_POST['puede_editar_entorno']) ? 1 : 0;
    $puede_editar_registros = isset($_POST['puede_editar_registros']) ? 1 : 0;
    $puede_eliminar_registros = isset($_POST['puede_eliminar_registros']) ? 1 : 0;
    
    // Verificar que el usuario exista
    $check_user = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $check_user->bind_param("i", $user_id);
    $check_user->execute();
    $user_result = $check_user->get_result();
    
    if ($user_result->num_rows === 0) {
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }

    // Verificar que no se esté modificando el último admin
    if ($rol !== 'admin') {
        $check_admin = $conn->prepare("SELECT COUNT(*) as admin_count FROM usuarios WHERE rol = 'admin' AND id != ?");
        $check_admin->bind_param("i", $user_id);
        $check_admin->execute();
        $admin_count = $check_admin->get_result()->fetch_assoc()['admin_count'];
        
        if ($admin_count === 0) {
            echo json_encode(['error' => 'No se puede cambiar el rol del último administrador']);
            exit;
        }
    }
    
    try {
        $stmt = $conn->prepare("UPDATE usuarios SET 
            rol = ?,
            puede_crear_entorno = ?,
            puede_eliminar_entorno = ?,
            puede_editar_entorno = ?,
            puede_editar_registros = ?,
            puede_eliminar_registros = ?
            WHERE id = ?");
            
        $stmt->bind_param("siiiiii", 
            $rol, 
            $puede_crear_entorno,
            $puede_eliminar_entorno,
            $puede_editar_entorno,
            $puede_editar_registros,
            $puede_eliminar_registros,
            $user_id
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado correctamente'
            ]);
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Error al actualizar usuario: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['error' => 'Método no permitido']);