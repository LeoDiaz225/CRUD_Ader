function showFloatingMessage(msg, isError = false) {
  let alertDiv = document.createElement('div');
  alertDiv.className = "mensaje-alert";
  alertDiv.style.position = "fixed";
  alertDiv.style.top = "20px";
  alertDiv.style.right = "20px";
  alertDiv.style.zIndex = "9999";
  alertDiv.style.background = isError ? "#dc3545" : "#198754";
  alertDiv.style.color = "#fff";
  alertDiv.style.padding = "12px 24px";
  alertDiv.style.borderRadius = "6px";
  alertDiv.style.boxShadow = "0 2px 8px #0002";
  alertDiv.style.fontSize = "1rem";
  alertDiv.style.maxWidth = "350px";
  alertDiv.style.minWidth = "220px";
  alertDiv.style.textAlign = "left";
  alertDiv.textContent = msg;
  document.body.appendChild(alertDiv);
  setTimeout(() => {
    alertDiv.style.transition = "opacity 0.5s";
    alertDiv.style.opacity = 0;
    setTimeout(() => {
      if (alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv);
    }, 500);
  }, 3000);
}


document.addEventListener("DOMContentLoaded", () => {
  // Detectar si estamos en la página de entorno o en la página de usuarios
  const isEnvironmentPage = window.location.pathname.includes('entorno.php');
  console.log("Página de entorno:", isEnvironmentPage);
  
  const phpAlertDiv = document.getElementById('mensaje-alerta');
  if (phpAlertDiv) {
    const mensaje = phpAlertDiv.dataset.mensaje;
    const tipo = phpAlertDiv.dataset.tipo;
    if (mensaje) {
      showFloatingMessage(mensaje, tipo === 'danger');
      phpAlertDiv.remove();
    }
  }
  
  // Obtenemos los parámetros de la URL
  const urlParams = new URLSearchParams(window.location.search);
  const tabla = urlParams.get("tabla");
  
  // Obtenemos las referencias a elementos DOM
  const csvForm = document.getElementById("csvForm");
  const csvFile = document.getElementById("csvFile");
  const userTableBody = document.getElementById("userTableBody");
  const paginationControls = document.getElementById("pagination-controls"); // Nuevo
  let currentPage = 1;
  const itemsPerPage = 5; // Puedes ajustar esto

if (csvForm) {
  csvForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    // Agregamos la tabla a formData
    formData.append('tabla', tabla);

    fetch(`environments/import_csv_to_environment.php?tabla=${tabla}`, {
      method: 'POST',
      body: formData
    })
    .then(response => response.text())
    .then(message => { // Agregado .then para manejar el mensaje
      // Limpiamos el mensaje si contiene HTML
      const cleanMessage = message.replace(/<[^>]*>/g, '').trim();
      showFloatingMessage(cleanMessage);
      csvForm.reset();
      loadUsers(); // Recargar la tabla
    })
    .catch(error => {
      showFloatingMessage('Error al importar el archivo: ' + error.message, true);
    });
  });
}

  const buscadorGeneral = document.getElementById("buscadorGeneral");
  let searchTerm = "";

  if (buscadorGeneral) {
    buscadorGeneral.addEventListener("input", function() {
      searchTerm = this.value;
      loadUsers(1); // Buscar siempre desde la página 1
    });
  }


  // Leer permisos del body
  const bodyElement = document.body;
  const puedeEditar = bodyElement.dataset.puedeEditar === 'true';
  const puedeEliminar = bodyElement.dataset.puedeEliminar === 'true';

  // Referencias al modal y formulario de edición
  const editModal = document.getElementById("editModal");
  const closeEditModal = document.getElementById("closeEditModal");
  const editForm = document.getElementById("editForm");
  const editCancelBtn = document.getElementById("editCancelBtn");

  console.log("Parámetros de URL:", Object.fromEntries(urlParams));
  console.log("Tabla seleccionada:", tabla);
  console.log("Elementos encontrados:", {
    csvForm: !!csvForm,
    csvFile: !!csvFile,
    userTableBody: !!userTableBody,
    paginationControls: !!paginationControls // Nuevo
  });

  function loadUsers(page = 1) { // Modificado para aceptar la página
    if (!tabla || !userTableBody) {
      console.error("No se puede cargar usuarios: falta tabla o contenedor");
      return;
    }
    currentPage = page;
    console.log(`Intentando cargar usuarios para tabla: ${tabla}, página: ${page}, límite: ${itemsPerPage}`);
    
    let url = `environments/read.php?tabla=${tabla}&page=${page}&limit=${itemsPerPage}`;
    if (searchTerm && searchTerm.trim() !== "") {
      url += `&search=${encodeURIComponent(searchTerm.trim())}`;
    }
    fetch(url)

      .then(res => {
        if (!res.ok) {
          throw new Error(`Error HTTP: ${res.status}`);
        }
        return res.json();
      })
      .then(response => { // Modificado para manejar la nueva estructura de respuesta
        console.log("Datos recibidos:", response);
        const data = response.data;
        const totalItems = response.total;
        
        userTableBody.innerHTML = ""; // Limpiamos la tabla

        if (!Array.isArray(data)) {
          console.error("Los datos recibidos no son un array:", data);
          userTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center">Error: los datos recibidos no son válidos</td></tr>';
          updatePaginationControls(0, page, itemsPerPage);
          return;
        }

        if (data.length === 0 && page === 1) {
          console.log("No hay registros para mostrar");
          const emptyRow = document.createElement("tr");
          emptyRow.innerHTML = '<td colspan="7" style="text-align: center">No hay registros disponibles</td>';
          userTableBody.appendChild(emptyRow);
          updatePaginationControls(0, page, itemsPerPage);
          return;
        } else if (data.length === 0 && page > 1) {
          // Esto podría pasar si se navega a una página que ya no tiene datos (ej. después de eliminar)
          console.log("No hay registros en esta página, volviendo a la primera.");
          loadUsers(1); // Cargar la primera página
          return;
        }

data.forEach(user => {
    const row = document.createElement("tr");
    row.setAttribute("data-id", user.id);

    // Creamos las celdas para cada campo
    Object.keys(user).forEach(key => {
        if (key !== 'id') { // No mostrar el ID en la tabla
            const td = document.createElement("td");
            td.textContent = user[key] || '';
            row.appendChild(td);
        }
    });

    // Agregar columna de acciones SOLO si tiene algún permiso
    if (puedeEditarRegistros || puedeEliminarRegistros) {
        const tdAcciones = document.createElement("td");
        
 if (puedeEditarRegistros) {
    const btnEditar = document.createElement("button");
    btnEditar.className = "btn btn-warning btn-sm edit-btn me-1";
    btnEditar.innerHTML = '<i class="bi bi-pencil"></i> Editar';
    btnEditar.setAttribute("data-bs-toggle", "modal");
    btnEditar.setAttribute("data-bs-target", "#editModal");
    // Asegurarse de que el botón tenga el ID del registro
    btnEditar.setAttribute("data-id", user.id);
    tdAcciones.appendChild(btnEditar);
}
        
        if (puedeEliminarRegistros) {
            const btnEliminar = document.createElement("button");
            btnEliminar.className = "btn btn-danger btn-sm delete-btn";
            btnEliminar.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
            tdAcciones.appendChild(btnEliminar);
        }
        
        row.appendChild(tdAcciones);
    }

    userTableBody.appendChild(row);
});

        // Activamos los botones de edición y eliminación
        activarBotones();
        // Actualizamos los controles de paginación
        updatePaginationControls(totalItems, page, itemsPerPage);
      })
      .catch(error => {
        console.error("Error al cargar datos:", error);
        if (userTableBody) {
          userTableBody.innerHTML = `<tr><td colspan="7" style="color: red; text-align: center">
            Error al cargar datos: ${error.message}</td></tr>`;
        }
        updatePaginationControls(0, currentPage, itemsPerPage); // Limpiar controles en caso de error
      });
  }

  function updatePaginationControls(totalItems, currentPage, itemsPerPage) {
    if (!paginationControls) return;
    paginationControls.innerHTML = ""; // Limpiar controles existentes

    const totalPages = Math.ceil(totalItems / itemsPerPage);

    if (totalPages <= 1) return; // No mostrar controles si hay una sola página o ninguna

    // Botón Anterior
    if (currentPage > 1) {
      const prevButton = document.createElement("button");
      prevButton.textContent = "←"; // Changed to arrow
      prevButton.classList.add("arrow-button"); // Add class for styling
      prevButton.addEventListener("click", () => loadUsers(currentPage - 1));
      paginationControls.appendChild(prevButton);
    }

    // Números de página (simplificado, podrías hacerlo más complejo con elipses)
    // Mostrar hasta 5 números de página alrededor de la actual
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    if (currentPage <= 3) {
        endPage = Math.min(totalPages, 5);
    }
    if (currentPage > totalPages - 3) {
        startPage = Math.max(1, totalPages - 4);
    }

    if (startPage > 1) {
        const firstButton = document.createElement("button");
        firstButton.textContent = "1";
        firstButton.addEventListener("click", () => loadUsers(1));
        paginationControls.appendChild(firstButton);
        if (startPage > 2) {
            const ellipsis = document.createElement("span");
            ellipsis.textContent = "...";
            ellipsis.style.margin = "0 5px";
            paginationControls.appendChild(ellipsis);
        }
    }

    for (let i = startPage; i <= endPage; i++) {
      const pageButton = document.createElement("button");
      pageButton.textContent = i;
      if (i === currentPage) {
        pageButton.disabled = true;
        pageButton.style.fontWeight = "bold";
      }
      pageButton.addEventListener("click", () => loadUsers(i));
      paginationControls.appendChild(pageButton);
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const ellipsis = document.createElement("span");
            ellipsis.textContent = "...";
            ellipsis.style.margin = "0 5px";
            paginationControls.appendChild(ellipsis);
        }
        const lastButton = document.createElement("button");
        lastButton.textContent = totalPages;
        lastButton.addEventListener("click", () => loadUsers(totalPages));
        paginationControls.appendChild(lastButton);
    }

    // Botón Siguiente
    if (currentPage < totalPages) {
      const nextButton = document.createElement("button");
      nextButton.textContent = "→"; // Changed to arrow
      nextButton.classList.add("arrow-button"); // Add class for styling
      nextButton.addEventListener("click", () => loadUsers(currentPage + 1));
      paginationControls.appendChild(nextButton);
    }
  }

function activarBotones() {
  if (!userTableBody) return;

  // Activar botones de eliminación
  document.querySelectorAll(".delete-btn").forEach(btn => {
    btn.removeEventListener("click", handleDelete); // Evitar duplicados
    btn.addEventListener("click", handleDelete);
  });

  // Activar botones de edición
  document.querySelectorAll(".edit-btn").forEach(btn => {
    btn.removeEventListener("click", handleEdit); // Evitar duplicados
    btn.removeEventListener("click", handleEnvironmentEdit); // Evitar duplicados
    
    if (isEnvironmentPage) {
        btn.addEventListener("click", handleEnvironmentEdit);
    } else {
        btn.addEventListener("click", handleEdit);
    }
  });
}

// Nuevo handler para editar usando los atributos data-*
function handleEdit() {
    const userId = this.getAttribute('data-id');
    const username = this.getAttribute('data-username');
    const rol = this.getAttribute('data-rol');
    const permisos = JSON.parse(this.getAttribute('data-permisos'));
    
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_rol').value = rol;
    
    // Actualizar estado de los checkboxes basado en los permisos actuales
    const permisosFields = [
        'puede_crear_entorno',
        'puede_eliminar_entorno',
        'puede_editar_entorno',
        'puede_editar_registros',
        'puede_eliminar_registros'
    ];

    permisosFields.forEach(permiso => {
        const checkbox = document.getElementById(`edit_${permiso}`);
        if (checkbox) {
            checkbox.checked = permisos[permiso] === 1;
        }
    });
}

function handleEnvironmentEdit(event) {
    const btn = event.currentTarget;
    const row = btn.closest('tr');
    const cells = row.querySelectorAll('td');
    const formInputs = document.querySelectorAll('#editForm input[type="text"], #editForm input[type="number"], #editForm input[type="email"], #editForm input[type="date"]');
    
    // Establecer el ID en el formulario
    document.getElementById('edit_id').value = row.getAttribute('data-id');
    
    // Mapear cada celda con su correspondiente input en el formulario
    formInputs.forEach((input, index) => {
        if (cells[index]) {
            input.value = cells[index].textContent.trim();
        }
    });
    
    console.log('Datos cargados en el formulario de edición');
}

// Manejador para el botón de eliminar
  function handleDelete() {
    const tr = this.closest("tr");
    const id = tr.dataset.id;
    
    // Mostrar el modal de confirmación
    const modal = new bootstrap.Modal(document.getElementById('deleteRecordModal'));
    modal.show();
    
    // Configurar el botón de confirmación
    document.getElementById('confirmDeleteRecord').onclick = function() {
        fetch(`environments/delete_from_environment.php?tabla=${tabla}&id=${id}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`Error HTTP: ${res.status}`);
                }
                return res.text();
            })
            .then(msg => {
                showFloatingMessage(msg);
                loadUsers();
                modal.hide();
            })
            .catch(error => {
                showFloatingMessage("Error al eliminar: " + error.message, true);
                modal.hide();
            });
    };
}


  // Manejar el envío del formulario de edición
if (editForm) {
  editForm.onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(editForm);
    fetch("environments/update_from_environment.php", {
      method: "POST",
      body: formData
    })
      .then(res => {
        if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
        return res.text();
      })
      .then(msg => {
        // Cerrar el modal correctamente con Bootstrap 5
        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('editModal'));
        if (modalInstance) {
          modalInstance.hide();
        }
        showFloatingMessage(msg);
        loadUsers();
      })
      .catch(error => {
        showFloatingMessage("Error al actualizar: " + error.message, true);
      });
  };
}

// Iniciar carga de datos si estamos en la página correcta
  if (tabla && userTableBody) {
    console.log("Iniciando carga de datos para tabla:", tabla);
    loadUsers();
  } else {
    console.warn("No se encontró tabla o elemento userTableBody");
  }

  // Guardado manual por AJAX
const manualForm = document.getElementById("manualForm");
if (manualForm) {
    manualForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate form using HTML5 validation
        if (!this.checkValidity()) {
            e.stopPropagation();
            this.classList.add('was-validated');
            return;
        }

        try {
            const formData = new FormData(this);
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                showFloatingMessage('Registro guardado correctamente');
                this.reset();
                this.classList.remove('was-validated');
                loadUsers(); // Reload the table
            } else {
                throw new Error(data.error || 'Error al guardar el registro');
            }
        } catch (error) {
            showFloatingMessage(error.message, true);
        }
    });
}

// Exportar tabla a Excel
const exportBtn = document.getElementById("exportExcelBtn");
if (exportBtn) {
  exportBtn.addEventListener("click", function() {
    // Obtener la tabla actual del DOM
    const table = document.querySelector('table');
    const thead = table.querySelector('thead');
    const tbody = document.getElementById('userTableBody');
    
    // Crear una nueva tabla para exportar (sin columna de acciones)
    const exportTable = document.createElement("table");
    
    // Copiar el encabezado pero sin la columna de acciones
    const headerRow = thead.querySelector('tr');
    const exportHeader = headerRow.cloneNode(true);
    exportHeader.lastElementChild.remove(); // Eliminar columna de acciones
    
    const exportThead = document.createElement("thead");
    exportThead.appendChild(exportHeader);
    exportTable.appendChild(exportThead);
    
    // Copiar el cuerpo pero sin la columna de acciones
    const exportTbody = document.createElement("tbody");
    tbody.querySelectorAll('tr').forEach(row => {
      const newRow = row.cloneNode(true);
      newRow.lastElementChild.remove(); // Eliminar columna de acciones
      exportTbody.appendChild(newRow);
    });
    exportTable.appendChild(exportTbody);

    // Exportar usando XLSX
    const wb = XLSX.utils.table_to_book(exportTable, {sheet: "Registros"});
    XLSX.writeFile(wb, `registros_${tabla}.xlsx`);
  });
}

// --- Dropdown de entornos asignados en crear usuario ---
const dropdownItems = document.querySelectorAll('.entorno-item');
const seleccionadosList = document.getElementById('entornosSeleccionados');
const hiddenInputsDiv = document.getElementById('entornosHiddenInputs');
let seleccionados = [];

if (dropdownItems.length && seleccionadosList && hiddenInputsDiv) {
  dropdownItems.forEach(item => {
    item.addEventListener('click', function(e) {
      e.preventDefault();
      const nombre = this.getAttribute('data-nombre');
      if (!seleccionados.includes(nombre)) {
        seleccionados.push(nombre);
        actualizarSeleccionados();
      }
    });
  });

  function actualizarSeleccionados() {
    // Limpiar lista y inputs
    seleccionadosList.innerHTML = '';
    hiddenInputsDiv.innerHTML = '';
    seleccionados.forEach(nombre => {
      // Mostrar en la lista
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center py-1';
      li.textContent = nombre;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm btn-danger ms-2 flex-shrink-0';
      btn.style.width = '70px'; 
      btn.textContent = 'Quitar';
      btn.onclick = function() {
        seleccionados = seleccionados.filter(n => n !== nombre);
        actualizarSeleccionados();
      };
      li.appendChild(btn);
      seleccionadosList.appendChild(li);
      // Crear input oculto
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'entornos_asignados[]';
      input.value = nombre;
      hiddenInputsDiv.appendChild(input);
    });
  }
}

const sidebar = document.getElementById('sidebar');
const toggle = document.getElementById('sidebarToggle');
const overlay = document.getElementById('sidebarOverlay');
toggle.onclick = () => {
  sidebar.classList.toggle('active');
  overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
};
overlay.onclick = () => {
  sidebar.classList.remove('active');
  overlay.style.display = 'none';
};

// Modal de confirmación para eliminar entorno
const deleteEnvironmentModal = document.getElementById('deleteEnvironmentModal');
if (deleteEnvironmentModal) {
  deleteEnvironmentModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const environmentName = button.getAttribute('data-environment-name');
    
    // Actualizar el nombre del entorno en el modal
    const environmentSpan = document.getElementById('environmentToDelete');
    const environmentInput = document.getElementById('environmentNameInput');
    
    if (environmentSpan && environmentInput) {
      environmentSpan.textContent = environmentName;
      environmentInput.value = environmentName;
    }
  });
}
 });
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar handlers para botones de edición y eliminación
    initializeUserManagement();

   //agregar al codigo copiado aca
    

// Función para inicializar la gestión de usuarios
function initializeUserManagement() {
    // Manejador para botones de edición
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            const rol = this.getAttribute('data-rol');
            const permisos = JSON.parse(this.getAttribute('data-permisos'));
            const entornos = this.getAttribute('data-entornos');
            
            // Llenar el formulario con los datos actuales
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_rol').value = rol;
            
            // Actualizar checkboxes según los permisos
            document.getElementById('edit_puede_crear_entorno').checked = permisos.puede_crear_entorno === 1;
            document.getElementById('edit_puede_eliminar_entorno').checked = permisos.puede_eliminar_entorno === 1;
            document.getElementById('edit_puede_editar_entorno').checked = permisos.puede_editar_entorno === 1;
            document.getElementById('edit_puede_editar_registros').checked = permisos.puede_editar_registros === 1;
            document.getElementById('edit_puede_eliminar_registros').checked = permisos.puede_eliminar_registros === 1;
        });
    });

    // Manejador para el formulario de edición
    const editForm = document.getElementById('editarUsuarioForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('admin/update_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFloatingMessage('Usuario actualizado correctamente');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editarUsuarioModal'));
                    modal.hide();
                    // Recargar la página para ver los cambios
                    setTimeout(() => location.reload(), 500);
                } else {
                    throw new Error(data.error || 'Error al actualizar usuario');
                }
            })
            .catch(error => {
                showFloatingMessage(error.message, true);
            });
        });
    }

    // Manejador para botones de eliminar usuario
   document.querySelectorAll('.delete-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            
            // Actualizar el modal con la información del usuario
            const userSpan = document.getElementById('userToDelete');
            userSpan.textContent = username;
            
            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            modal.show();
            
            // Configurar el botón de confirmación
            document.getElementById('confirmDeleteUser').onclick = function() {
                const formData = new FormData();
                formData.append('user_id', userId);

                fetch('admin/delete_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showFloatingMessage('Usuario eliminado correctamente');
                        btn.closest('tr').remove();
                        modal.hide();
                    } else {
                        throw new Error(data.error || 'Error al eliminar usuario');
                    }
                })
                .catch(error => {
                    showFloatingMessage(error.message, true);
                    modal.hide();
                });
            };
        });
    });
}
});


