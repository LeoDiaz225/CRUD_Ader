document.addEventListener('DOMContentLoaded', () => {
    const camposContainer = document.getElementById('camposContainer');
    const agregarCampoBtn = document.getElementById('agregarCampo');
    const crearEntornoForm = document.getElementById('crearEntornoForm');

    if (!camposContainer || !agregarCampoBtn || !crearEntornoForm) return;

    function crearCampo() {
        const campoId = `campo_${Date.now()}`;
        const campo = document.createElement('div');
        campo.className = 'campo-row border-bottom pb-3 mb-3';
        campo.id = campoId;
        
        campo.innerHTML = `
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Nombre del campo</label>
                    <input type="text" class="form-control campo-nombre" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select campo-tipo">
                        <option value="texto">Texto</option>
                        <option value="numero">Número</option>
                        <option value="email">Email</option>
                        <option value="fecha">Fecha</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input campo-requerido" checked>
                        <label class="form-check-label">Campo requerido</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger w-100" 
                            onclick="document.getElementById('${campoId}').remove()">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        camposContainer.appendChild(campo);
    }

    agregarCampoBtn.addEventListener('click', crearCampo);
    crearCampo(); // Crear primer campo por defecto

    crearEntornoForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const campos = [];
        document.querySelectorAll('.campo-row').forEach(row => {
            campos.push({
                nombre: row.querySelector('.campo-nombre').value.trim()
                    .replace(/[^a-zA-Z0-9_]/g, '_'),
                tipo: row.querySelector('.campo-tipo').value,
                requerido: row.querySelector('.campo-requerido').checked
            });
        });

        try {
            const response = await fetch('environments/create_environment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    nombre: crearEntornoForm.nombre.value,
                    campos: campos
                })
            });
            
            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                showFloatingMessage(data.error || 'Error al crear entorno', true);
            }
        } catch (error) {
            showFloatingMessage('Error de conexión', true);
        }
    });
});