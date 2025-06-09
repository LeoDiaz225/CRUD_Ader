<?php
header('Content-Type: application/json');
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


echo json_encode([
    'data' => $data,
    'total' => $total,
    'page' => $page,
    'pages' => ceil($total / $limit)
]);