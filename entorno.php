<?php

session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Incluir conexión a la base de datos
include "includes/db.php";

// Validar el nombre de la tabla
$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla'] ?? '');
if (!$tabla) {
  die("<div class='container my-5'><div class='alert alert-danger'>Error: Entorno no válido.</div><a href='index.php' class='btn btn-link'><i class='bi bi-arrow-left'></i> Volver a entornos</a></div>");
}



$entornos_asignados = isset($_SESSION['entornos_asignados']) ? explode(',', $_SESSION['entornos_asignados']) : [];
if ($_SESSION['rol'] !== 'admin' && !in_array($tabla, $entornos_asignados)) {
    echo "<div class='container my-5'><div class='alert alert-danger'>No tienes acceso a este entorno.</div></div>";
    exit;
}

// Verificar si la tabla existe
$tableCheck = $conn->query("SHOW TABLES LIKE '$tabla'");
if ($tableCheck->num_rows === 0) {
  die("<div class='container my-5'><div class='alert alert-danger'>Error: La tabla '$tabla' no existe.</div><a href='index.php' class='btn btn-link'><i class='bi bi-arrow-left'></i> Volver a entornos</a></div>");
}

// Al inicio del archivo, después de la conexión
$stmt = $conn->prepare("SELECT * FROM entornos_campos 
                       WHERE entorno_nombre = ? ORDER BY orden");
$stmt->bind_param("s", $tabla);
$stmt->execute();
$campos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Guardar manualmente si es un formulario de carga manual (no CSV)
if (
  $_SERVER["REQUEST_METHOD"] === "POST" && 
  isset($_POST["apellido_nombre"]) && 
  !isset($_FILES['csvFile'])
) {
  $stmt = $conn->prepare("INSERT INTO `$tabla` (apellido_nombre, cuit_dni, razon_social, telefono, correo, rubro) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param(
    "ssssss",
    $_POST["apellido_nombre"],
    $_POST["cuit_dni"],
    $_POST["razon_social"],
    $_POST["telefono"],
    $_POST["correo"],
    $_POST["rubro"]
  );

  // Agregar esto para manejar la respuesta AJAX
  if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    if ($stmt->execute()) {
      echo json_encode(['success' => true]);
    } else {
      echo json_encode(['success' => false, 'error' => "Error al guardar: " . $conn->error]);
    }
    exit;
  }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($tabla) ?> - Entorno</title>
  <link rel="stylesheet" href="css/style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-dark text-light">

<div class="container my-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Entorno: <?= htmlspecialchars($tabla) ?></h1>
  </div>
  <a href="index.php" class="btn btn-link mb-3 px-0 text-light"><i class="bi bi-arrow-left"></i> Volver a entornos</a>


<?php if (!empty($_GET['mensaje'])): ?>
  <div id="mensaje-alerta" data-mensaje="<?= htmlspecialchars($_GET['mensaje']) ?>" style="display:none"></div>
<?php endif; ?>

  <!-- Formulario manual -->
  <div class="card mb-4 bg-dark text-light border-0">
    <div class="card-body">
      <h5 class="card-title text-center mb-3">Agregar registro manualmente</h5>
      <form id="manualForm" class="row g-3">
        <?php foreach ($campos as $campo): ?>
            <div class="col-md-6">
                <label class="form-label"><?= htmlspecialchars($campo['nombre_campo']) ?></label>
                <?php if ($campo['tipo_campo'] === 'numero'): ?>
                    <input type="number" 
                           name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                           class="form-control"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>>
                <?php elseif ($campo['tipo_campo'] === 'email'): ?>
                    <input type="email" 
                           name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                           class="form-control"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>>
                <?php elseif ($campo['tipo_campo'] === 'fecha'): ?>
                    <input type="date" 
                           name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                           class="form-control"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>>
                <?php else: ?>
                    <input type="text" 
                           name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                           class="form-control"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="col-12">
            <button type="submit" class="btn btn-success">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Formulario CSV -->
  <div class="card mb-4 bg-dark text-light border-0">
  <div class="card-body">
    <h5 class="card-title text-center mb-3">Importar CSV</h5>
    <form id="csvForm" class="row g-3 align-items-center">
      <div class="col-md-8">
        <input type="file" id="csvFile" name="csvFile" accept=".csv" class="form-control" required>
        <input type="hidden" name="tabla" value="<?= htmlspecialchars($tabla) ?>">
      </div>
      <div class="col-md-4 text-end">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Importar CSV</button>
      </div>
    </form>
    <p class="mt-2 mb-0" style="font-size: 0.95em; color: #ffc107;">
      El CSV debe tener los campos: Apellido y Nombre, CUIT/DNI, Razón Social, Teléfono, Correo, Rubro.
    </p>
  </div>
</div>

  <!-- Buscador y exportar -->
  <div class="row mb-3">
    <div class="col-md-6 mb-2">
      <input type="text" id="buscadorGeneral" class="form-control" placeholder="Buscar registros...">
    </div>
    <div class="col-md-6 text-end">
      <button id="exportExcelBtn" class="btn btn-success exportar-btn">
        <i class="bi bi-file-earmark-excel"></i> Exportar
      </button>
    </div>
  </div>

  <!-- Tabla de registros -->
  <div class="table-responsive">
    <table class="table table-striped align-middle bg-dark text-light">
      <thead class="table-success">
        <tr>
          <?php foreach ($campos as $campo): ?>
              <th><?= htmlspecialchars($campo['nombre_campo']) ?></th>
          <?php endforeach; ?>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="userTableBody">
        <!-- Los datos se cargarán dinámicamente -->
      </tbody>
    </table>
  </div>
  <div class="pagination-container d-flex justify-content-end">
    <div id="pagination-controls" class="mt-3"></div>
  </div>
</div>

<!-- Modal de edición Bootstrap -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <form id="editForm" autocomplete="off">
        <div class="modal-header border-0">
          <h5 class="modal-title" id="editModalLabel">Editar registro</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          <input type="hidden" name="tabla" id="edit_tabla" value="<?= htmlspecialchars($tabla) ?>">
          <div class="mb-2">
            <input type="text" name="apellido_nombre" id="edit_apellido_nombre" class="form-control mb-2" placeholder="Apellido y Nombre" required>
            <input type="text" name="cuit_dni" id="edit_cuit_dni" class="form-control mb-2" placeholder="CUIT o DNI" required>
            <input type="text" name="razon_social" id="edit_razon_social" class="form-control mb-2" placeholder="Razón Social" required>
            <input type="text" name="telefono" id="edit_telefono" class="form-control mb-2" placeholder="Teléfono" required>
            <input type="email" name="correo" id="edit_correo" class="form-control mb-2" placeholder="Correo Electrónico" required>
            <input type="text" name="rubro" id="edit_rubro" class="form-control mb-2" placeholder="Rubro" required>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="submit" id="editSaveBtn" class="btn btn-success">Aceptar cambios</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const puedeEditarRegistros = <?= isset($_SESSION['puede_editar_registros']) && $_SESSION['puede_editar_registros'] ? 'true' : 'false' ?>;
  const puedeEliminarRegistros = <?= isset($_SESSION['puede_eliminar_registros']) && $_SESSION['puede_eliminar_registros'] ? 'true' : 'false' ?>;
  const tabla = "<?= htmlspecialchars($tabla) ?>";
</script>
<script src="js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

</body>
</html>