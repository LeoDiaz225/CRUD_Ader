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


$stmt = $conn->prepare("SELECT * FROM entornos_campos 
                       WHERE entorno_nombre = ? ORDER BY orden");
$stmt->bind_param("s", $tabla);
$stmt->execute();
$campos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Guardar manualmente si es un formulario de carga manual (no CSV)
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_FILES['csvFile'])) {
    header('Content-Type: application/json');
    
    try {
        $campos = [];
        $valores = [];
        $tipos = '';
        $params = [];
        $errores = [];

        $stmt = $conn->prepare("SELECT nombre_campo, tipo_campo, es_requerido FROM entornos_campos 
                              WHERE entorno_nombre = ? ORDER BY orden");
        $stmt->bind_param("s", $tabla);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($campo = $result->fetch_assoc()) {
            $nombre = $campo['nombre_campo'];
            $valor = $_POST[$nombre] ?? null;

            // Validar campo requerido
            if ($campo['es_requerido'] && ($valor === null || trim($valor) === '')) {
                $errores[] = "El campo {$nombre} es requerido";
                continue;
            }

            // Validar según tipo
            switch ($campo['tipo_campo']) {
                case 'numero':
                    if ($valor !== null && $valor !== '' && !is_numeric($valor)) {
                        $errores[] = "El campo {$nombre} debe ser numérico";
                        $tipos .= 'i';
                        break;
                    }
                    $tipos .= 'i';
                    break;
                    
                case 'email':
                    if ($valor !== null && $valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                        $errores[] = "El campo {$nombre} debe ser un email válido";
                        $tipos .= 's';
                        break;
                    }
                    $tipos .= 's';
                    break;

                case 'fecha':
                    if ($valor !== null && $valor !== '') {
                        $fecha = DateTime::createFromFormat('Y-m-d', $valor);
                        if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
                            $errores[] = "El campo {$nombre} debe ser una fecha válida";
                            $tipos .= 's';
                            break;
                        }
                    }
                    $tipos .= 's';
                    break;

                case 'telefono':
                    if ($valor !== null && $valor !== '' && !preg_match('/^[0-9]{9,15}$/', $valor)) {
                        $errores[] = "El campo {$nombre} debe ser un teléfono válido (9-15 dígitos)";
                        $tipos .= 's';
                        break;
                    }
                    $tipos .= 's';
                    break;

                default:
                    if ($valor !== null && strlen($valor) > 255) {
                        $errores[] = "El campo {$nombre} no puede exceder 255 caracteres";
                        $tipos .= 's';
                        break;
                    }
                    $tipos .= 's';
                    break;
            }

            if ($valor !== null && $valor !== '') {
                $campos[] = $nombre;
                $valores[] = '?';
                $params[] = $valor;
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode('. ', $errores));
        }

        if (empty($campos)) {
            throw new Exception('No se recibieron datos válidos');
        }

        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            $tabla,
            implode(', ', $campos),
            implode(', ', $valores)
        );

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($tipos, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
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
                           min="0"
                           max="999999999"
                           step="any"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>
                           oninput="this.value = this.value.replace(/[^0-9.-]/g, '')">

                <?php elseif ($campo['tipo_campo'] === 'email'): ?>
                    <input type="email" 
                           name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                           class="form-control"
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                           maxlength="100"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>>

                <?php elseif ($campo['tipo_campo'] === 'fecha'): ?>
                    <input type="date" 
                           name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                           class="form-control"
                           min="1900-01-01"
                           max="2100-12-31"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>>

                <?php elseif ($campo['tipo_campo'] === 'telefono'): ?>
                    <input type="tel" 
                           name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                           class="form-control"
                           pattern="[0-9]{9,15}"
                           maxlength="15"
                           title="Ingrese un número de teléfono válido (9-15 dígitos)"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>>

                <?php else: ?>
                    <input type="text" 
                           name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                           class="form-control"
                           maxlength="255"
                           pattern="[A-Za-z0-9\s\-_.,]*"
                           title="Solo se permiten letras, números y algunos caracteres especiales"
                           <?= $campo['es_requerido'] ? 'required' : '' ?>>
                <?php endif; ?>
                <div class="invalid-feedback">
                    Por favor ingrese un valor válido para <?= htmlspecialchars($campo['nombre_campo']) ?>
                </div>
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
      El CSV debe tener los campos: <?php 
    $campos_lista = array_map(function($campo) {
        return htmlspecialchars($campo['nombre_campo']);
    }, $campos);
    echo implode(', ', $campos_lista);
    ?>.
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
                <?php if ($_SESSION['puede_editar_registros'] || $_SESSION['puede_eliminar_registros']): ?>
                    <th>Acciones</th>
                <?php endif; ?>
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
            <?php foreach ($campos as $campo): ?>
              <?php if ($campo['tipo_campo'] === 'numero'): ?>
                <input type="number" 
                       name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                       id="edit_<?= htmlspecialchars($campo['nombre_campo']) ?>"
                       class="form-control mb-2" 
                       placeholder="<?= htmlspecialchars($campo['nombre_campo']) ?>"
                       <?= $campo['es_requerido'] ? 'required' : '' ?>>
              <?php elseif ($campo['tipo_campo'] === 'email'): ?>
                <input type="email" 
                       name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                       id="edit_<?= htmlspecialchars($campo['nombre_campo']) ?>"
                       class="form-control mb-2" 
                       placeholder="<?= htmlspecialchars($campo['nombre_campo']) ?>"
                       <?= $campo['es_requerido'] ? 'required' : '' ?>>
              <?php elseif ($campo['tipo_campo'] === 'fecha'): ?>
                <input type="date" 
                       name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                       id="edit_<?= htmlspecialchars($campo['nombre_campo']) ?>"
                       class="form-control mb-2" 
                       <?= $campo['es_requerido'] ? 'required' : '' ?>>
              <?php else: ?>
                <input type="text" 
                       name="<?= htmlspecialchars($campo['nombre_campo']) ?>" 
                       id="edit_<?= htmlspecialchars($campo['nombre_campo']) ?>"
                       class="form-control mb-2" 
                       placeholder="<?= htmlspecialchars($campo['nombre_campo']) ?>"
                       <?= $campo['es_requerido'] ? 'required' : '' ?>>
              <?php endif; ?>
            <?php endforeach; ?>
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

<!-- Modal para confirmar eliminación de registro -->
<div class="modal fade" id="deleteRecordModal" tabindex="-1" aria-labelledby="deleteRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="deleteRecordModalLabel">Confirmar eliminación</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        ¿Está seguro que desea eliminar este registro?
        <p class="text-danger mt-2 mb-0">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          Esta acción no se puede deshacer
        </p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteRecord">
          <i class="bi bi-trash me-2"></i>Eliminar
        </button>
      </div>
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