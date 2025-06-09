<?php
include "../includes/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $nombre = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($data['nombre'])));
    $campos = $data['campos'];

    if ($nombre && is_array($campos)) {
        $conn->begin_transaction();
        
        try {
            // Registrar entorno
            $stmt = $conn->prepare("INSERT INTO entornos (nombre) VALUES (?)");
            $stmt->bind_param("s", $nombre);
            $stmt->execute();

            // Crear tabla dinámica
            $sql = "CREATE TABLE `$nombre` (id INT AUTO_INCREMENT PRIMARY KEY";
            
            // Registrar campos
            $stmt = $conn->prepare("INSERT INTO entornos_campos 
                (entorno_nombre, nombre_campo, tipo_campo, es_requerido, orden) 
                VALUES (?, ?, ?, ?, ?)");

            foreach ($campos as $index => $campo) {
                // Determinar tipo de columna SQL
                $tipoDB = match($campo['tipo']) {
                    'numero' => 'INT',
                    'email' => 'VARCHAR(100)',
                    'fecha' => 'DATE',
                    default => 'VARCHAR(255)'
                };

                $sql .= sprintf(",\n%s %s", 
                    $campo['nombre'],
                    $tipoDB
                );

                $stmt->bind_param("sssii", 
                    $nombre,
                    $campo['nombre'],
                    $campo['tipo'],
                    $campo['requerido'],
                    $index
                );
                $stmt->execute();
            }

            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($sql);

            $conn->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    exit;
}
