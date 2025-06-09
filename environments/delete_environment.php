<?php
session_start();

function validarSesion() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        header('Location: ../login.php');
        exit;
    }
    if (time() - $_SESSION['last_activity'] > 1800) {
        session_destroy();
        header('Location: ../login.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

include "../includes/Security.php";
validarSesion();

// Agregar CSRF y backup antes de eliminar
if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
    die('Token CSRF inválido');
}

try {
    include "../admin/backup.php";
    $backup_file = backupTable($nombre);
    Security::logAction($_SESSION['user_id'], 'delete_environment', "Entorno eliminado: $nombre");

    // Headers de seguridad
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

    include "../includes/db.php";

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["nombre"])) {
        $nombre = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST["nombre"]);

        // Eliminar la tabla asociada al entorno
        $conn->query("DROP TABLE IF EXISTS `$nombre`");

        // Eliminar el registro de la tabla 'entornos'
        $stmt = $conn->prepare("DELETE FROM entornos WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();

        header("Location: ../index.php");
        exit;
    } else {
        echo "Solicitud inválida.";
    }
} catch (Exception $e) {
    Security::logAction($_SESSION['user_id'], 'delete_environment_error', $e->getMessage());
    die("Error: " . $e->getMessage());
}

