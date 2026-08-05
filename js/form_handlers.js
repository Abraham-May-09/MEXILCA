// =======================
// Procesos
// =======================
function guardarProceso(data) {
  fetch('php/guardar_proceso.php', {
    method: 'POST',
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(res => alert(res.success ? 'Proceso guardado' : 'Error al guardar proceso'))
  .catch(err => console.error('Error:', err));
}

// =======================
// Insumos (Inputs)
// =======================
function guardarInput(data) {
  fetch('php/guardar_input.php', {
    method: 'POST',
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(res => alert(res.success ? 'Input guardado' : 'Error al guardar input'))
  .catch(err => console.error('Error:', err));
}

// =======================
// Emisiones (Outputs)
// =======================
function guardarOutput(data) {
  fetch('php/guardar_output.php', {
    method: 'POST',
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(res => alert(res.success ? 'Output guardado' : 'Error al guardar output'))
  .catch(err => console.error('Error:', err));
}

// =======================
// Intercambios (Exchanges)
// =======================
function guardarExchange(data) {
  fetch('php/guardar_exchange.php', {
    method: 'POST',
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(res => alert(res.success ? 'Exchange guardado' : 'Error al guardar exchange'))
  .catch(err => console.error('Error:', err));
}

// =======================
// Documentación
// =======================
function guardarDocumentacion(data) {
  fetch('php/guardar_documentacion.php', {
    method: 'POST',
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(res => alert(res.success ? 'Documentación guardada' : 'Error al guardar documentación'))
  .catch(err => console.error('Error:', err));
}

// =======================
// Parámetros
// =======================
function guardarParametro(data) {
  fetch('php/guardar_parametros.php', {
    method: 'POST',
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(res => alert(res.success ? 'Parámetro guardado' : 'Error al guardar parámetro'))
  .catch(err => console.error('Error:', err));
}

// =======================
// Tareas
// =======================
function guardarTarea(data) {
  fetch('php/guardar_tareas.php', {
    method: 'POST',
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(res => alert(res.success ? 'Tarea guardada' : 'Error al guardar tarea'))
  .catch(err => console.error('Error:', err));
}
