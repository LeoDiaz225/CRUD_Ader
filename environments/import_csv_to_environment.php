<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "../includes/db.php";

$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? '');
if (!$tabla) {
    echo "Error: Entorno no válido.";
    exit;
}

if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    $error = isset($_FILES['csvFile']) ? $_FILES['csvFile']['error'] : 'No se envió el archivo';
    echo "Error: No se pudo procesar el archivo CSV. Código: $error";
    exit;
}

// Obtener estructura de campos
$stmt = $conn->prepare("SELECT nombre_campo, tipo_campo FROM entornos_campos 
                       WHERE entorno_nombre = ? ORDER BY orden");
$stmt->bind_param("s", $tabla);
$stmt->execute();
$result = $stmt->get_result();
$campos = [];
$tipos = "";

while ($campo = $result->fetch_assoc()) {
    $campos[] = $campo['nombre_campo'];
    // Determinar tipo de parámetro para bind_param
    switch ($campo['tipo_campo']) {
        case 'numero':
            $tipos .= 'i';
            break;
        default:
            $tipos .= 's';
    }
}

if (empty($campos)) {
    echo "Error: No se encontraron campos definidos para este entorno.";
    exit;
}

$file = $_FILES["csvFile"]["tmp_name"];
if (!file_exists($file)) {
    echo "Error: El archivo temporal no existe.";
    exit;
}

$handle = fopen($file, "r");
if (!$handle) {
    echo "Error: No se pudo abrir el archivo CSV.";
    exit;
}

// Preparar la consulta INSERT
$placeholders = array_fill(0, count($campos), '?');
$sql = sprintf(
    "INSERT INTO `%s` (%s) VALUES (%s)",
    $tabla,
    implode(', ', $campos),
    implode(', ', $placeholders)
);
$stmt = $conn->prepare($sql);

$count = 0;
$errors = 0;
$header = fgetcsv($handle);

// Verificar que el número de columnas coincida
if (count($header) !== count($campos)) {
    echo "Error: El número de columnas en el CSV no coincide con la estructura del entorno. ";
    echo "Se esperaban " . count($campos) . " columnas (" . implode(', ', $campos) . ")";
    fclose($handle);
    exit;
}

while (($data = fgetcsv($handle)) !== false) {
    if (count($data) === count($campos)) {
        try {
            $stmt->bind_param($tipos, ...$data);
            if ($stmt->execute()) {
                $count++;
            } else {
                $errors++;
            }
        } catch (Exception $e) {
            $errors++;
        }
    } else {
        $errors++;
    }
}
fclose($handle);

if ($count > 0 && $errors === 0) {
    echo "Se importaron $count registros correctamente";
} elseif ($count > 0 && $errors > 0) {
    echo "Importado con advertencias: $count registros correctos, $errors con error.";
} else {
    echo "No se pudo importar ningún registro. Verifique el formato del archivo.";
}