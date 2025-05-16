<?php
// Activar la visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir conexión a la base de datos
include "db.php";

// Verificar si hay una tabla especificada
$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? '');
if (!$tabla) {
  http_response_code(400);
  echo "Error: No se especificó una tabla válida.";
  exit;
}

// Verificar si se ha enviado un archivo
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  $error = isset($_FILES['csvFile']) ? $_FILES['csvFile']['error'] : 'No se envió el archivo';
  echo "Error: No se pudo procesar el archivo CSV. Código: " . $error;
  exit;
}

// Verificar si la tabla existe
$tableCheck = $conn->query("SHOW TABLES LIKE '$tabla'");
if ($tableCheck->num_rows === 0) {
  http_response_code(404);
  echo "Error: La tabla '$tabla' no existe.";
  exit;
}

// Verificar el archivo subido
$file = $_FILES["csvFile"]["tmp_name"];
if (!file_exists($file)) {
  http_response_code(400);
  echo "Error: El archivo temporal no existe.";
  exit;
}

// Intentar abrir el archivo
$handle = fopen($file, "r");
if (!$handle) {
  http_response_code(500);
  echo "Error: No se pudo abrir el archivo CSV.";
  exit;
}

// Procesar el CSV
$count = 0;
$errors = 0;

// Leer la primera línea (cabecera)
$header = fgetcsv($handle);
if (!$header) {
  echo "Advertencia: CSV vacío o con formato incorrecto.";
  fclose($handle);
  exit;
}

// Mostrar cabecera para depuración
echo "Cabecera detectada: " . implode(", ", $header) . "<br>";

// Procesar los datos
while (($data = fgetcsv($handle, 1000, ",")) !== false) {
  // Verificar que tengamos suficientes columnas
  if (count($data) < 6) {
    echo "Advertencia: Fila con datos insuficientes: " . implode(", ", $data) . "<br>";
    $errors++;
    continue;
  }
  
  // Imprimir fila para depuración
  echo "Procesando: " . implode(", ", $data) . "<br>";
  
  // Insertar en la base de datos
  try {
    $stmt = $conn->prepare("INSERT INTO `$tabla` (apellido_nombre, cuit_dni, razon_social, telefono, correo, rubro) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5]);
    
    if ($stmt->execute()) {
      $count++;
    } else {
      echo "Error en SQL: " . $stmt->error . "<br>";
      $errors++;
    }
  } catch (Exception $e) {
    echo "Excepción: " . $e->getMessage() . "<br>";
    $errors++;
  }
}

// Cerrar el archivo
fclose($handle);

// Mostrar resumen
echo "<hr>Importación a tabla '$tabla' completada.<br>";
echo "- Registros importados exitosamente: $count<br>";
if ($errors > 0) {
  echo "- Registros con errores: $errors<br>";
}
echo "<hr><a href='entorno.php?tabla=$tabla'>Volver al entorno</a>";