<?php
include("conexion.php");
header('Content-Type: application/json');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// LISTAR
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $accion === 'listar') {
    $sql = "SELECT * FROM clientes ORDER BY nombre";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) $lista[] = $row;
    echo json_encode($lista);
    exit;
}

// CREAR
if ($accion === 'crear') {
    $cedula    = mysqli_real_escape_string($conexion, $_POST['cedula']);
    $nombre    = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $correo    = mysqli_real_escape_string($conexion, $_POST['correo']);
    $direccion = mysqli_real_escape_string($conexion, $_POST['direccion']);
    $telefono  = mysqli_real_escape_string($conexion, $_POST['telefono']);

    $sql = "INSERT INTO clientes (cedula, nombre, direccion, telefono, correo)
            VALUES ('$cedula','$nombre','$direccion','$telefono','$correo')";
    $sql_u = "INSERT INTO usuarios (nombre, correo, password, rol, estado) 
            VALUES ('$nombre', '$correo', '1234', 'cliente', 'activo')";
            mysqli_query($conexion, $sql_u);
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true, "id" => mysqli_insert_id($conexion)]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }

    
    exit;
    
}

// ACTUALIZAR CLIENTE
if ($accion === 'actualizar') {
    $id = intval($_POST['id']);
    $cedula = $_POST['cedula'];
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];

    $sql = "UPDATE clientes SET cedula='$cedula', nombre='$nombre', correo='$correo', direccion='$direccion', telefono='$telefono' WHERE id_clientes=$id";
    
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
    exit;
}

// ELIMINAR
if ($accion === 'eliminar') {
    $id = intval($_POST['id']);
    mysqli_query($conexion, "DELETE FROM clientes WHERE id_clientes=$id");
    echo json_encode(["ok" => true]);
    exit;
}
