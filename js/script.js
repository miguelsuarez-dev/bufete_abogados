// ============================================================
// BUFETE DE ABOGADOS — script.js
// ============================================================

// --- VARIABLES GLOBALES ---
let intentos = 0;
const CAPTCHA_NUM1 = Math.floor(Math.random() * 9) + 1;
const CAPTCHA_NUM2 = Math.floor(Math.random() * 9) + 1;
let clienteEditandoId = null;
let abogadoEditandoId = null;

// --- UTILIDADES ---
function phpUrl(archivo) {
    if (window.location.pathname.includes('/html/')) {
        return "../php/" + archivo;
    }
    return "php/" + archivo;
}

// Function to clear session and redirect to login
function cerrarSesion() {
    sessionStorage.clear(); // Clear all session storage items
    window.location.href = "index.html"; // Redirect to the login page
}

// Function to verify user access based on role
function verificarAcceso(rolRequerido) {
    const rol = (sessionStorage.getItem("rol") || "").trim().toLowerCase();
    const nombre = sessionStorage.getItem("nombre") || "";
    if (!rol || rol !== rolRequerido.toLowerCase()) {
        window.location.href = "index.html"; // Redirect to login if not authorized
        return; // Stop execution
    }
    return nombre; // Return the user's name if authorized
}

// ─── INICIALIZACIÓN ÚNICA (DOMContentLoaded) ────────────────
document.addEventListener("DOMContentLoaded", () => {
    // 1. Configuración de Captcha
    const lbl = document.getElementById("captchaLabel");
    if (lbl) lbl.innerText = `¿Cuánto es ${CAPTCHA_NUM1} + ${CAPTCHA_NUM2}?`;

    // 2. Listeners de Formularios
    const formLogin = document.getElementById("formLogin");
    if (formLogin) formLogin.addEventListener("submit", validarLogin);

    const formRegistro = document.getElementById("formRegistro");
    if (formRegistro) formRegistro.addEventListener("submit", registrar);

    const formAbogado = document.getElementById("formAbogado"); 
    if (formAbogado) formAbogado.addEventListener("submit", guardarAbogado);

    const formClientes = document.getElementById("formClientes") || document.querySelector("form[onsubmit*='guardarCliente']");
    if (formClientes) formClientes.addEventListener("submit", guardarCliente);

    const formRecuperar = document.getElementById("formRecuperar");
    if (formRecuperar) formRecuperar.addEventListener("submit", recuperar);

    const formRestablecer = document.getElementById("formRestablecer");
    if (formRestablecer) formRestablecer.addEventListener("submit", restablecerClave);

    // 3. Carga de datos según la página actual
    const pagina = window.location.pathname;
    
    
    if (pagina.includes("clientes")) {
        cargarClientes();
    }
    if (pagina.includes("abogados")) {
        cargarAbogados();       // Función correcta
        cargarEspecialidades(); 
    }
    if (pagina.includes("casos")) {
        cargarCasos();
        cargarSelectores();
    }
    if (pagina.includes("dashboard")) {
        mostrarBienvenida();
    }
    const formCaso = document.getElementById("formCaso"); 
    if (formCaso) formCaso.addEventListener("submit", guardarCaso);
});

// ─── 1. ACCESO Y SEGURIDAD ──────────────────────────────────
function validarLogin(event) {
    event.preventDefault();
    const correo   = document.getElementById("correo").value.trim();
    const clave    = document.getElementById("clave").value.trim();
    const mensaje  = document.getElementById("mensaje");
    const captchaDiv = document.getElementById("captcha");
    const respuesta  = document.getElementById("respuesta")?.value.trim();

    if (intentos >= 3) {
        mensaje.innerText = "Usuario bloqueado. Recupera tu contraseña.";
        mensaje.style.color = "red";
        return;
    }

    if (intentos >= 2) {
        captchaDiv.style.display = "block";
        if (parseInt(respuesta) !== CAPTCHA_NUM1 + CAPTCHA_NUM2) {
            mensaje.innerText = "Captcha incorrecto.";
            mensaje.style.color = "red";
            return;
        }
    }

    fetch(phpUrl("login.php"), {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `accion=login&correo=${encodeURIComponent(correo)}&clave=${encodeURIComponent(clave)}`
    })
    .then(res => res.text())
    .then(data => {
        if (data.includes("window.location.href")) {
            document.open(); document.write(data); document.close();
            return;
        }
        intentos++;
        mensaje.innerText = "Credenciales incorrectas.";
        mensaje.style.color = "red";
    });
}

function mostrarBienvenida() {
    const nombre = sessionStorage.getItem("nombre");
    if(nombre) {
        const element = document.getElementById("bienvenida");
        if(element) element.innerText = "Bienvenido, " + nombre;
    }
}

function registrar(event) {
    event.preventDefault();
    fetch(phpUrl("login.php"), {
        method: "POST",
        body: new URLSearchParams(new FormData(event.target))
    })
    .then(res => res.text())
    .then(data => { document.open(); document.write(data); document.close(); });
}

// ─── 2. GESTIÓN DE ABOGADOS ─────────────────────────────────
function cargarAbogados() {
    fetch(phpUrl("gestionar_abogados.php?accion=listar"))
    .then(r => r.json())
    .then(lista => {
        const ul = document.getElementById("listaAbogados");
        if (!ul) return;
        ul.innerHTML = lista.map(a => `
            <li class="item-lista">
                <div>
                    <strong>${a.nombre}</strong><br>
                    <small>ID: ${a.cedula} | Especialidad: ${a.especialidades || 'General'}</small>
                </div>
                <div class="acciones">
                    <button onclick="prepararEdicionAbogado(${a.id_abogado}, '${a.cedula}', '${a.nombre}', '${a.correo}', '${a.direccion}', '${a.telefono}', '${a.tarjeta_profesional}', '${a.especialidades}')">✏️</button>
                    <button onclick="eliminarAbogado(${a.id_abogado})" class="btn-rojo">🗑️</button>
                </div>
            </li>
        `).join('');
    });
}

function guardarAbogado(event) {
    event.preventDefault();

    const id_db = document.getElementById("idAbogadoOculto").value;
    const datos = new URLSearchParams();

    // Aquí está el secreto: mapeamos manualmente para que coincida con el PHP
    datos.append('accion', id_db ? 'actualizar' : 'crear');
    if (id_db) datos.append('id', id_db);

    // Estos nombres (cedula, nombre, etc.) deben ser IGUALES a los del PHP
    datos.append('cedula', document.getElementById("idAbogado").value);
    // Concatenamos nombre y apellido si los tienes separados
    const nombreCompleto = document.getElementById("nombreAbogado").value + " " + document.getElementById("apellidoAbogado").value;
    datos.append('nombre', nombreCompleto);
    
    datos.append('correo', document.getElementById("correoAbogado").value);
    datos.append('telefono', document.getElementById("telefonoAbogado").value);
    datos.append('direccion', document.getElementById("direccionAbogado").value);
    datos.append('tarjeta', document.getElementById("tarjetaAbogado").value);
    datos.append('especialidad', document.getElementById("especialidadAbogado").value);

   fetch(phpUrl("gestionar_abogados.php"), {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString()
    })
    .then(r => r.text()) // <--- CAMBIAMOS json() POR text()
    .then(textoCrudo => {
        console.log("🛑 RESPUESTA CRUDA DE PHP:", textoCrudo); // Aquí veremos la verdad
        
        try {
            // Intentamos convertirlo a JSON manualmente
            const data = JSON.parse(textoCrudo); 
            if (data.ok) {
                alert("✅ Guardado con éxito");
                resetearFormAbogado();
                cargarAbogados();
            } else {
                alert("❌ Error: " + data.error);
            }
        } catch(error) {
            console.error("El PHP no devolvió un JSON válido. Mira arriba la respuesta cruda.");
        }
    })
    .catch(error => {
        console.error("Error en la conexión:", error);
    });
}
function prepararEdicionAbogado(id, cedula, nombreCompleto, correo, direccion, telefono, tarjeta, especialidad) {
    document.getElementById('idAbogadoOculto').value = id;
    const partes = nombreCompleto.split(" ");
    document.getElementById('nombreAbogado').value = partes[0] || "";
    document.getElementById('apellidoAbogado').value = partes.slice(1).join(" ") || "";
    document.getElementById('idAbogado').value = cedula;
    document.getElementById('correoAbogado').value = correo;
    document.getElementById('direccionAbogado').value = direccion;
    document.getElementById('telefonoAbogado').value = telefono;
    document.getElementById('tarjetaAbogado').value = tarjeta;
    document.getElementById('especialidadAbogado').value = especialidad || "";
    document.getElementById('btnGuardar').innerText = "Guardar Cambios";
    window.scrollTo(0, 0);
}

function eliminarAbogado(id) {
    if (!confirm("¿Eliminar abogado?")) return;
    fetch(phpUrl("gestionar_abogados.php"), {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `accion=eliminar&id=${id}`
    }).then(() => cargarAbogados());
}

function cargarEspecialidades() {
    fetch(phpUrl('gestionar_abogados.php?accion=listar_especialidades'))
    .then(res => res.json())
    .then(data => {
        const datalist = document.getElementById('listaEspecialidades');
        if (datalist) {
            datalist.innerHTML = data.map(e => `<option value="${e.nombre}">`).join('');
        }
    });
}

// ─── 3. GESTIÓN DE CLIENTES ─────────────────────────────────
function cargarClientes() {
    fetch(phpUrl("gestionar_clientes.php?accion=listar"))
    .then(res => res.json())
    .then(lista => {
        const ul = document.getElementById('listaClientes');
        if (!ul) return;
        ul.innerHTML = lista.map(c => `
            <li class="item-lista" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding: 10px;">
                <div>
                    <strong>${c.nombre}</strong><br>
                    <small>CC: ${c.cedula} | Tel: ${c.telefono}</small>
                </div>
                <div class="acciones">
                    <button onclick="prepararEdicionCliente(${c.id_clientes}, '${c.cedula}', '${c.nombre}', '${c.correo}', '${c.direccion}', '${c.telefono}')">✏️</button>
                    <button onclick="eliminarCliente(${c.id_clientes})" class="btn-rojo">🗑️</button>
                </div>
            </li>
        `).join('');
    });
}

function guardarCliente(event) {
    event.preventDefault();
    const id_db = document.getElementById('idClienteOculto').value;
    const datos = new URLSearchParams();

    datos.append('accion', id_db ? 'actualizar' : 'crear');
    if (id_db) datos.append('id', id_db);

    // Mapeo manual para asegurar que llegue bien al PHP
    datos.append('cedula', document.getElementById('idDoc').value);
    datos.append('nombre', document.getElementById('nombreCliente').value);
    datos.append('correo', document.getElementById('correoCliente').value);
    datos.append('direccion', document.getElementById('dirCliente').value);
    datos.append('telefono', document.getElementById('telCliente').value);

    fetch(phpUrl("gestionar_clientes.php"), {
        method: 'POST',
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            alert(id_db ? "✅ Cliente actualizado" : "✅ Cliente agregado");
            resetearFormCliente();
            cargarClientes();
        } else {
            alert("❌ Error: " + data.error);
        }
    });
}

function prepararEdicionCliente(id, cedula, nombre, correo, direccion, telefono) {
    document.getElementById('idClienteOculto').value = id;
    document.getElementById('idDoc').value = cedula;
    document.getElementById('nombreCliente').value = nombre;
    document.getElementById('correoCliente').value = correo;
    document.getElementById('dirCliente').value = direccion;
    document.getElementById('telCliente').value = telefono;

    // Cambiar visual del botón
    document.getElementById('btnFormCliente').innerText = "Guardar Cambios";
    document.getElementById('btnCancelarCliente').style.display = "block";
    window.scrollTo(0, 0);
}

function resetearFormCliente() {
    document.getElementById('idClienteOculto').value = "";
    document.getElementById('formClientes').reset();
    document.getElementById('btnFormCliente').innerText = "Agregar Cliente";
    document.getElementById('btnCancelarCliente').style.display = "none";
}

function eliminarCliente(id) {
    if (!confirm("¿Seguro que desea eliminar este cliente?")) return;
    fetch(phpUrl("gestionar_clientes.php"), {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `accion=eliminar&id=${id}`
    }).then(() => cargarClientes());
}

// ─── 4. GESTIÓN DE CASOS ────────────────────────────────────
function cargarCasos() {
    fetch(phpUrl("gestionar_casos.php"))
    .then(r => r.json())
    .then(data => {
        const tbody = document.querySelector("#tablaCasos tbody");
        if (!tbody) return;
        tbody.innerHTML = data.map(c => `
            <tr>
                <td>${c.titulo}</td>
                <td>${c.nombre_cliente}</td>
                <td>${c.estado}</td>
                <td><button onclick="eliminarCaso(${c.id_casos})">Eliminar</button></td>
            </tr>
        `).join('');
    });
}

// --- FUNCIONES DE LIMPIEZA ---
function resetearFormAbogado() {
    document.getElementById("idAbogadoOculto").value = "";
    document.getElementById("formAbogado").reset();
    document.getElementById("btnGuardar").innerText = "Agregar Abogado";
}

function resetearFormulario() {
    document.getElementById('idCliente').value = "";
    document.querySelector('#formClientes').reset();
}
function guardarCaso(event) {
    event.preventDefault();

    const id_db = document.getElementById("idCasoOculto").value;
    const datos = new URLSearchParams();

    // Definimos la acción para el PHP
    datos.append('accion', id_db ? 'actualizar' : 'crear');
    if (id_db) datos.append('id', id_db);

    // Mapeo de campos (Asegúrate de que los IDs existan en tu HTML de casos)
    datos.append('titulo', document.getElementById("tituloCaso").value);
    datos.append('descripcion', document.getElementById("descripcionCaso").value);
    datos.append('id_cliente', document.getElementById("selectCliente").value);
    datos.append('id_abogado', document.getElementById("selectAbogado").value);
    datos.append('estado', 'Abierto'); // Estado inicial por defecto

    fetch(phpUrl("gestionar_casos.php"), {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            alert("✅ Caso guardado con éxito");
            document.getElementById("formCaso").reset();
            if (typeof cargarCasos === 'function') cargarCasos(); // Recarga la lista
        } else {
            alert("❌ Error: " + data.error);
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("Fallo en la comunicación con el servidor");
    });
}

function agregarCaso() {
    const titulo = document.getElementById("tituloCaso").value;
    const desc = document.getElementById("descripcionCaso").value;
    const cliente = document.getElementById("clienteAsignado").value;
    const abogado = document.getElementById("abogadoAsignado").value;
    const estado = document.getElementById("estadoCaso").value;

    if (!titulo || !cliente || !abogado) {
        alert("Por favor completa los campos obligatorios (Título, Cliente y Abogado)");
        return;
    }

    // Usamos FormData para enviar los datos de forma limpia
    const datos = new URLSearchParams();
    datos.append("accion", "crear_manual");
    datos.append("titulo", titulo);
    datos.append("descripcion", desc);
    datos.append("id_cliente", cliente);
    datos.append("id_abogado", abogado);
    datos.append("estado", estado);

    fetch(phpUrl("gestionar_casos.php"), {
        method: "POST",
        body: datos
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            alert("Caso y Expediente creados con éxito");
            location.reload(); // Recarga para ver los cambios
        } else {
            alert("Error: " + data.error);
        }
    });
}

// Función para crear el expediente manual (R4.1)
function guardarExpediente(event) {
    event.preventDefault();
    const datos = new URLSearchParams(new FormData(event.target));
    datos.append('accion', 'crear');

    fetch(phpUrl("gestionar_expedientes.php"), {
        method: "POST",
        body: datos
    })
    .then(r => r.json())
    .then(data => {
        if(data.ok) {
            alert("Expediente creado: " + data.numero);
            cargarExpedientes();
        }
    });
}

// Función para archivar (R4.4)
function archivarExpediente(id) {
    if(!confirm("¿Desea archivar este expediente?")) return;
    
    fetch(phpUrl("gestionar_expedientes.php"), {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body: `accion=archivar&id=${id}`
    })
    .then(() => cargarExpedientes());
}
    

// Function to clear session and redirect to login
function cerrarSesion() {
    sessionStorage.clear(); // Clear all session storage items
    window.location.href = "index.html"; // Redirect to the login page
}

// Function to verify user access based on role
function verificarAcceso(rolRequerido) {
    const rol = (sessionStorage.getItem("rol") || "").trim().toLowerCase();
    const nombre = sessionStorage.getItem("nombre") || "";
    if (!rol || rol !== rolRequerido.toLowerCase()) {
        window.location.href = "index.html"; // Redirect to login if not authorized
        return; // Stop execution
    }
    return nombre; // Return the user's name if authorized
}


