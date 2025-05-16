// Incluir este script en entorno.php, justo antes de cerrar el body
// <script src="debug.js"></script>

// Función para verificar la conexión con read.php
function testReadConnection() {
  const tabla = new URLSearchParams(window.location.search).get("tabla");
  if (!tabla) {
    console.error("No se encontró el parámetro 'tabla' en la URL");
    return;
  }

  console.log("Probando conexión con read.php para tabla:", tabla);
  
  fetch(`read.php?tabla=${tabla}`)
    .then(response => {
      console.log("Estado de la respuesta:", response.status);
      console.log("Headers:", [...response.headers.entries()]);
      return response.text();
    })
    .then(text => {
      console.log("Respuesta de read.php:", text);
      try {
        const data = JSON.parse(text);
        console.log("Datos parseados:", data);
        
        if (Array.isArray(data)) {
          console.log(`Se recibieron ${data.length} registros`);
        } else {
          console.log("La respuesta no es un array:", data);
        }
      } catch (e) {
        console.error("Error al parsear JSON:", e);
      }
    })
    .catch(err => {
      console.error("Error en la conexión:", err);
    });
}

// Función para verificar la configuración de la base de datos
function checkDbConfig() {
  const config = {
    host: "localhost",
    user: "admin",
    pass: "123",
    db: "ader_db"
  };
  
  console.log("Configuración de la base de datos:", config);
  console.log("Verifica que estos valores sean correctos en phpMyAdmin");
}

// Ejecutar las pruebas al cargar
document.addEventListener("DOMContentLoaded", () => {
  console.log("====== INICIANDO DEPURACIÓN ======");
  console.log("URL actual:", window.location.href);
  
  testReadConnection();
  checkDbConfig();
  
  console.log("Por favor, verifica la consola para ver los resultados");
  console.log("====== FIN DE DEPURACIÓN ======");
});