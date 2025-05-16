document.addEventListener("DOMContentLoaded", () => {
  // Obtenemos los parámetros de la URL
  const urlParams = new URLSearchParams(window.location.search);
  const tabla = urlParams.get("tabla");
  
  // Obtenemos las referencias a elementos DOM
  const csvForm = document.getElementById("csvForm");
  const csvFile = document.getElementById("csvFile");
  const userTableBody = document.getElementById("userTableBody");

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
    userTableBody: !!userTableBody
  });

  function loadUsers() {
    if (!tabla || !userTableBody) {
      console.error("No se puede cargar usuarios: falta tabla o contenedor");
      return;
    }

    console.log("Intentando cargar usuarios para tabla:", tabla);
    
    fetch(`read.php?tabla=${tabla}`)
      .then(res => {
        if (!res.ok) {
          throw new Error(`Error HTTP: ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        console.log("Datos recibidos:", data);
        
        // Limpiamos la tabla antes de añadir nuevos datos
        userTableBody.innerHTML = "";

        if (!Array.isArray(data)) {
          console.error("Los datos recibidos no son un array:", data);
          return;
        }

        if (data.length === 0) {
          console.log("No hay registros para mostrar");
          const emptyRow = document.createElement("tr");
          emptyRow.innerHTML = '<td colspan="7" style="text-align: center">No hay registros disponibles</td>';
          userTableBody.appendChild(emptyRow);
          return;
        }

        // Para cada registro, creamos una fila
        data.forEach(user => {
          const row = document.createElement("tr");
          row.setAttribute("data-id", user.id);

          // Creamos el contenido HTML de la fila
          row.innerHTML = `
            <td>${user.apellido_nombre || ''}</td>
            <td>${user.cuit_dni || ''}</td>
            <td>${user.razon_social || ''}</td>
            <td>${user.telefono || ''}</td>
            <td>${user.correo || ''}</td>
            <td>${user.rubro || ''}</td>
            <td>
              <button class="edit-btn">Editar</button>
              <button class="delete-btn">Eliminar</button>
            </td>
          `;

          userTableBody.appendChild(row);
        });

        // Activamos los botones de edición y eliminación
        activarBotones();
      })
      .catch(error => {
        console.error("Error al cargar datos:", error);
        if (userTableBody) {
          userTableBody.innerHTML = `<tr><td colspan="7" style="color: red; text-align: center">
            Error al cargar datos: ${error.message}</td></tr>`;
        }
      });
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
      btn.addEventListener("click", handleEdit);
    });
  }

  // Manejador para el botón de eliminar
  function handleDelete() {
    const tr = this.closest("tr");
    const id = tr.dataset.id;

    if (confirm("¿Seguro que desea eliminar este registro?")) {
      fetch(`delete_from_environment.php?tabla=${tabla}&id=${id}`)
        .then(res => {
          if (!res.ok) {
            throw new Error(`Error HTTP: ${res.status}`);
          }
          return res.text();
        })
        .then(msg => {
          alert(msg);
          loadUsers(); // Recargar la tabla
        })
        .catch(error => {
          console.error("Error al eliminar:", error);
          alert("Error al eliminar: " + error.message);
        });
    }
  }

  // Manejador para el botón de editar (abre el modal y rellena los campos)
  function handleEdit() {
    const tr = this.closest("tr");
    const id = tr.dataset.id;
    const cells = tr.querySelectorAll("td");
    const user = {
      id,
      apellido_nombre: cells[0].textContent,
      cuit_dni: cells[1].textContent,
      razon_social: cells[2].textContent,
      telefono: cells[3].textContent,
      correo: cells[4].textContent,
      rubro: cells[5].textContent
    };
    openEditModal(user);
  }

  // Función para abrir el modal y rellenar los campos
  function openEditModal(user) {
    if (!editModal) return;
    document.getElementById("edit_id").value = user.id;
    document.getElementById("edit_apellido_nombre").value = user.apellido_nombre;
    document.getElementById("edit_cuit_dni").value = user.cuit_dni;
    document.getElementById("edit_razon_social").value = user.razon_social;
    document.getElementById("edit_telefono").value = user.telefono;
    document.getElementById("edit_correo").value = user.correo;
    document.getElementById("edit_rubro").value = user.rubro;
    editModal.style.display = "block";
    setTimeout(() => {
      document.getElementById("edit_apellido_nombre").focus();
    }, 100);
  }

  // Cerrar modal con la X
  if (closeEditModal) {
    closeEditModal.onclick = () => { editModal.style.display = "none"; };
  }
  // Cerrar modal con el botón cancelar
  if (editCancelBtn) {
    editCancelBtn.onclick = () => { editModal.style.display = "none"; };
  }
  // Cerrar modal haciendo click fuera del contenido
  window.onclick = function(event) {
    if (event.target === editModal) {
      editModal.style.display = "none";
    }
  };

  // Manejar el envío del formulario de edición
  if (editForm) {
    editForm.onsubmit = function(e) {
      e.preventDefault();
      const formData = new FormData(editForm);
      fetch("update_from_environment.php", {
        method: "POST",
        body: formData
      })
        .then(res => {
          if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
          return res.text();
        })
        .then(msg => {
          editModal.style.display = "none";
          alert(msg);
          loadUsers();
        })
        .catch(error => {
          alert("Error al actualizar: " + error.message);
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

  // Configurar el formulario de importación CSV si existe
  if (csvForm && csvFile) {
    console.log("Configurando formulario CSV");
    
    csvForm.addEventListener("submit", function(e) {
      e.preventDefault();
      console.log("Formulario CSV enviado");
      
      const file = csvFile.files[0];
      if (!file) {
        alert("Por favor, seleccione un archivo .CSV");
        return;
      }

      console.log("Archivo seleccionado:", file.name);
      const formData = new FormData(csvForm);

      fetch(`import_csv_to_environment.php?tabla=${tabla}`, {
        method: "POST",
        body: formData
      })
        .then(res => {
          if (!res.ok) {
            throw new Error(`Error HTTP: ${res.status}`);
          }
          return res.text();
        })
        .then(msg => {
          alert(msg);
          csvForm.reset();
          loadUsers(); // Recargar datos después de importar
        })
        .catch(err => {
          console.error("Error al importar CSV:", err);
          alert("Error al importar el CSV: " + err.message);
        });
    });
  } else {
    console.warn("No se encontró el formulario CSV");
  }
});