<?php
session_start();

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

include "includes/db.php";
include "includes/Security.php";

// Función helper para sanitización
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = "Token de seguridad inválido";
    } else {
        // Cambiar esta línea
        $username = trim(htmlspecialchars($_POST["username"] ?? '', ENT_QUOTES, 'UTF-8'));
        $password = $_POST["password"] ?? "";
        
        if ($username && $password) {
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user["password"])) {
                    // Regenerar ID de sesión para prevenir session fixation
                    session_regenerate_id(true);
                    
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = h($user["username"]);
                    $_SESSION["rol"] = $user["rol"];
                    $_SESSION["puede_crear_entorno"] = (bool)$user["puede_crear_entorno"];
                    $_SESSION["puede_eliminar_entorno"] = (bool)$user["puede_eliminar_entorno"];
                    $_SESSION["puede_editar_entorno"] = (bool)$user["puede_editar_entorno"];
                    $_SESSION["puede_editar_registros"] = (bool)$user["puede_editar_registros"];
                    $_SESSION["puede_eliminar_registros"] = (bool)$user["puede_eliminar_registros"];
                    $_SESSION["entornos_asignados"] = h($user["entornos_asignados"]);
                    $_SESSION['last_activity'] = time();
                    $_SESSION['loggedin'] = true;

                    // Registrar el login en el log de auditoría
                    Security::logAction($user["id"], 'login', 'Login exitoso');
                    
                    header("Location: index.php");
                    exit;
                } else {
                    Security::logAction(0, 'login_failed', "Intento fallido para usuario: $username");
                    $error = "Contraseña incorrecta.";
                }
            } else {
                Security::logAction(0, 'login_failed', "Usuario no encontrado: $username");
                $error = "Usuario no encontrado.";
            }
        } else {
            $error = "Completa todos los campos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= Security::generateCSRFToken() ?>">
    <title>Login</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-8 col-md-6 col-lg-4">
                <div class="card shadow-sm" style="background:#2c3035; border:none;">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4 text-light">Iniciar Sesión</h2>
                        <?php if (!empty($error)) : ?>
                            <div class="alert alert-danger py-2" role="alert">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            <div class="mb-3">
                                <input type="text" name="username" class="form-control" placeholder="Usuario" required autofocus>
                            </div>
                            <div class="mb-3">
                                <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Ingresar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>