<?php
header('Content-Type: application/json');
include "../includes/db.php";

$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

// Obtener campos
$stmt = $conn->prepare("SELECT nombre_campo FROM entornos_campos 
                       WHERE entorno_nombre = ? ORDER BY orden");
$stmt->bind_param("s", $tabla);
$stmt->execute();
$result = $stmt->get_result();
$campos = [];
while ($row = $result->fetch_assoc()) {
    $campos[] = $row['nombre_campo'];
}

// Construir consulta
$fields = empty($campos) ? '*' : 'id, ' . implode(', ', $campos);
$sql = "SELECT $fields FROM `$tabla`";

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

// Contar total
$countSql = "SELECT COUNT(*) as total FROM `$tabla`" . 
            (!empty($whereParts) ? " WHERE " . implode(" OR ", $whereParts) : "");

$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];

// Obtener registros
$sql .= " LIMIT ? OFFSET ?";
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

echo json_encode([
    'data' => $data,
    'total' => $total,
    'page' => $page,
    'pages' => ceil($total / $limit)
]);