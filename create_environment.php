<?php
include "db.php";

if (isset($_POST['nombre']) && $_POST['nombre'] !== "") {
    $nombre = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($_POST['nombre'])));

    // Registrar en tabla 'entornos'
    $stmt = $conn->prepare("INSERT INTO entornos (nombre) VALUES (?)");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();

    // Crear tabla asociada al entorno
    $sql = "CREATE TABLE `$nombre` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        apellido_nombre VARCHAR(255),
        cuit_dni VARCHAR(50),
        razon_social VARCHAR(255),
        telefono VARCHAR(50),
        correo VARCHAR(100),
        rubro VARCHAR(100)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);

    header("Location: index.php");
    exit;
}
