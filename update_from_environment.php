<?php
include "db.php";
$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['tabla'] ?? '');
$id = intval($_POST['id'] ?? 0);

if (!$tabla || !$id) {
  http_response_code(400);
  echo "Datos inválidos.";
  exit;
}

// Verificar si la tabla existe
$tableCheck = $conn->query("SHOW TABLES LIKE '$tabla'");
if ($tableCheck->num_rows === 0) {
  http_response_code(404);
  echo "La tabla no existe.";
  exit;
}

// Comprobar que el registro existe
$checkStmt = $conn->prepare("SELECT id FROM `$tabla` WHERE id = ? LIMIT 1");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
  http_response_code(404);
  echo "El registro no existe.";
  exit;
}

// Actualizar el registro
$stmt = $conn->prepare("UPDATE `$tabla` SET apellido_nombre=?, cuit_dni=?, razon_social=?, telefono=?, correo=?, rubro=? WHERE id=?");
$stmt->bind_param(
  "ssssssi",
  $_POST["apellido_nombre"],
  $_POST["cuit_dni"],
  $_POST["razon_social"],
  $_POST["telefono"],
  $_POST["correo"],
  $_POST["rubro"],
  $id
);

if ($stmt->execute()) {
  echo "Registro actualizado correctamente.";
} else {
  http_response_code(500);
  echo "Error al actualizar: " . $conn->error;
}