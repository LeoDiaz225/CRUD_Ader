<?php
include "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["nombre"])) {
    $nombre = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST["nombre"]);

    // Eliminar la tabla asociada al entorno
    $conn->query("DROP TABLE IF EXISTS `$nombre`");

    // Eliminar el registro de la tabla 'entornos'
    $stmt = $conn->prepare("DELETE FROM entornos WHERE nombre = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();

    header("Location: index.php");
    exit;
} else {
    echo "Solicitud inválida.";
}