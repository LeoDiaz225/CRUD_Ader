<?php
include "db.php";
$result = $conn->query("SELECT * FROM entornos ORDER BY fecha_creacion DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Entornos de Trabajo</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h1>Gestión de Entornos</h1>

  <!-- Crear nuevo entorno -->
  <form action="create_environment.php" method="POST">
    <input type="text" name="nombre" placeholder="Nombre del entorno" required>
    <button type="submit">Crear Entorno</button>
  </form>

  <!-- Lista de entornos existentes -->
  <table>
    <thead>
      <tr>
        <th>Nombre del Entorno</th>
        <th>Fecha de Creación</th>
        <th>Acción</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['nombre']) ?></td>
          <td><?= $row['fecha_creacion'] ?></td>
          <td>
          <form action="entorno.php" method="get" style="display:inline-block; margin:0; padding:0; vertical-align:middle;">
    <input type="hidden" name="tabla" value="<?= htmlspecialchars($row['nombre']) ?>">
    <button type="submit" class="action-btn ingresar" style="background:#42a5f5; color:white;">Ingresar</button>
  </form>
  <form action="delete_environment.php" method="POST" style="display:inline-block; margin:0; padding:0; vertical-align:middle;">
    <input type="hidden" name="nombre" value="<?= htmlspecialchars($row['nombre']) ?>">
    <button type="submit" class="action-btn eliminar" style="background:#f44336; color:white;">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</body>
</html>