<?php
class Security {
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function h($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    public static function logAction($user_id, $action, $details) {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
    }
}