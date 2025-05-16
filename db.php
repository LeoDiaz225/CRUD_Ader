<?php
$host = "localhost";
$user = "admin";      
$pass = "123";          
$db = "ader_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
