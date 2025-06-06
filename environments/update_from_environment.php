<?php
session_start();
include "../includes/db.php";

if (!isset($_SESSION['puede_editar_registros']) || !$_SESSION['puede_editar_registros']) {
    http_response_code(403); 
    echo "No tienes permiso para editar registros.";
    exit;
}

$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['tabla'] ?? '');
$id = intval($_POST['id'] ?? 0);

if (!$tabla || !$id) {
    http_response_code(400);
    echo "Datos inválidos.";
    exit;
}

// Obtener estructura de campos
$stmt = $conn->prepare("SELECT nombre_campo FROM entornos_campos WHERE entorno_nombre = ? ORDER BY orden");
$stmt->bind_param("s", $tabla);
$stmt->execute();
$result = $stmt->get_result();

$campos = [];
$valores = [];
$tipos = "";
$params = [];

while ($campo = $result->fetch_assoc()) {
    $nombre = $campo['nombre_campo'];
    if (isset($_POST[$nombre])) {
        $campos[] = "`$nombre` = ?";
        $params[] = $_POST[$nombre];
        $tipos .= "s"; // Ajustar según el tipo de campo si es necesario
    }
}

if (!empty($campos)) {
    $sql = "UPDATE `$tabla` SET " . implode(", ", $campos) . " WHERE id = ?";
    $tipos .= "i"; // Para el ID
    $params[] = $id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipos, ...$params);

    if ($stmt->execute()) {
        echo "Registro actualizado correctamente.";
    } else {
        http_response_code(500);
        echo "Error al actualizar: " . $conn->error;
    }
} else {
    http_response_code(400);
    echo "No se recibieron datos para actualizar.";
}