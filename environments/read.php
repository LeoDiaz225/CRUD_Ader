<?php
header('Content-Type: application/json');
session_start();

// Headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

include "../includes/Security.php";
validarSesion();

// Validar token CSRF
if (!isset($_GET['csrf_token']) || !Security::validateCSRFToken($_GET['csrf_token'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Token CSRF inválido']));
}

include "../includes/db.php";

$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

// Obtener campos
$stmt = $conn->prepare("SELECT nombre_campo, tipo_campo FROM entornos_campos 
                       WHERE entorno_nombre = ? ORDER BY orden");
$stmt->bind_param("s", $tabla);
$stmt->execute();
$result = $stmt->get_result();
$campos = [];
$primer_campo_texto = '';

while ($campo = $result->fetch_assoc()) {
    $campos[] = $campo['nombre_campo'];
    // Guardar el primer campo de tipo texto para ordenamiento
    if (empty($primer_campo_texto) && $campo['tipo_campo'] === 'texto') {
        $primer_campo_texto = $campo['nombre_campo'];
    }
}

// Construir consulta base
$fields = empty($campos) ? '*' : 'id, ' . implode(', ', $campos);
$sql = "SELECT $fields FROM `$tabla`";

// Agregar WHERE si hay búsqueda
if ($search !== "") {
    $whereParts = [];
    $params = [];
    $types = "";
    
    foreach ($campos as $campo) {
        $whereParts[] = "`$campo` LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    
    if (!empty($whereParts)) {
        $sql .= " WHERE " . implode(" OR ", $whereParts);
    }
}

// Agregar ORDER BY antes del LIMIT
if (!empty($primer_campo_texto)) {
    $sql .= " ORDER BY `$primer_campo_texto` ASC";
}

// Agregar LIMIT después del ORDER BY
$sql .= " LIMIT ? OFFSET ?";

// Generar una clave única para el caché
$cacheKey = "table_{$tabla}_page_{$page}_limit_{$limit}_search_{$search}";

// Obtener resultados usando caché
$results = getCachedResult($cacheKey, function() use ($sql, $stmt, $conn, $params, $types, $limit, $offset, $whereParts) {
    // Preparar y ejecutar la consulta
    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);

    // Contar total
    $countSql = "SELECT COUNT(*) as total FROM `$tabla`" . 
                (!empty($whereParts) ? " WHERE " . implode(" OR ", $whereParts) : "");

    $stmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ];
}, 60); // Cache por 60 segundos

// Devolver resultados
echo json_encode($results);