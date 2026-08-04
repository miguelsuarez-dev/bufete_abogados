<?php
include 'conexion.php';

// R2.1 Crear cliente
if (isset($_POST['agregar'])) {
    $cedula    = mysqli_real_escape_string($conexion, $_POST['identificacion']);
    $nombre    = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $direccion = mysqli_real_escape_string($conexion, $_POST['direccion'] ?? '');
    $telefono  = mysqli_real_escape_string($conexion, $_POST['telefono'] ?? '');
    $correo    = mysqli_real_escape_string($conexion, $_POST['correo']);

    $sql = "INSERT INTO clientes (cedula, nombre, direccion, telefono, correo)
            VALUES ('$cedula', '$nombre', '$direccion', '$telefono', '$correo')";
    mysqli_query($conexion, $sql);
}

// R2.2 Consultar clientes
$resultado = mysqli_query($conexion, "SELECT * FROM clientes");
?>

<div class="box">
    <h2>Gestión de Clientes</h2>
    <form method="POST">
        <input type="text" name="identificacion" placeholder="Identificación" required>
        <input type="text" name="nombre" placeholder="Nombre Completo" required>
        <input type="email" name="correo" placeholder="Correo Electrónico" required>
        <button type="submit" name="agregar">Agregar Cliente</button>
    </form>
    
    <ul id="listaClientes">
        <?php while($row = $resultado->fetch_assoc()): ?>
            <li>
                <?php echo $row['nombre'] . " - " . $row['correo']; ?>
                <a href="eliminar_cliente.php?id=<?php echo $row['id_clientes']; ?>">Eliminar</a>
            </li>
        <?php endwhile; ?>
    </ul>
</div>