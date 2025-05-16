<?php
include "db.php";
$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? '');
if (!$tabla) {
  http_response_code(400);
  echo json_encode(["error" => "Nombre de tabla no proporcionado."]);
  exit;
}

// Verificar si la tabla existe
$tableCheck = $conn->query("SHOW TABLES LIKE '$tabla'");
if ($tableCheck->num_rows === 0) {
  http_response_code(404);
  echo json_encode(["error" => "La tabla no existe."]);
  exit;
}

// Consulta segura con prepared statements
$stmt = $conn->prepare("SELECT * FROM `$tabla` ORDER BY id DESC");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(["error" => "Error preparando la consulta: " . $conn->error]);
  exit;
}

$stmt->execute();
$result = $stmt->get_result();
$datos = [];

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $datos[] = $row;
  }
}

header("Content-Type: application/json");
echo json_encode($datos);