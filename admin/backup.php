<?php
function backupTable($tabla) {
    global $conn;
    $fecha = date('Y-m-d_H-i-s');
    $backup_file = "backups/{$tabla}_{$fecha}.sql";
    
    $result = $conn->query("SHOW CREATE TABLE `$tabla`");
    $row = $result->fetch_assoc();
    $create_table = $row['Create Table'] . ";\n\n";
    
    file_put_contents($backup_file, $create_table);
    
    $result = $conn->query("SELECT * FROM `$tabla`");
    while ($row = $result->fetch_assoc()) {
        $values = array_map(function($value) use ($conn) {
            return $conn->real_escape_string($value);
        }, $row);
        
        $insert = "INSERT INTO `$tabla` VALUES ('" . implode("','", $values) . "');\n";
        file_put_contents($backup_file, $insert, FILE_APPEND);
    }
    
    return $backup_file;
}