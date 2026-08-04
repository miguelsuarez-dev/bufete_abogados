<?php
// Endpoint de diagnóstico y vinculación:
// Si un usuario cliente existe pero no tiene registro en la tabla clientes,
// lo crea automáticamente.
include("conexion.php");
header('Content-Type: application/json');

$id_usuario = intval($_POST['id_usuario'] ?? 0);
if (!$id_usuario) { echo json_encode(["ok"=>false,"error"=>"Sin id_usuario"]); exit; }

$res = mysqli_query($conexion, "SELECT * FROM usuarios WHERE id_usuarios=$id_usuario AND rol='cliente'");
if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(["ok"=>false,"error"=>"Usuario no encontrado o no es cliente"]);
    exit;
}
$user = mysqli_fetch_assoc($res);

// Verificar si ya existe en clientes
$check = mysqli_query($conexion,
    "SELECT id_clientes FROM clientes WHERE id_usuario=$id_usuario OR correo='".mysqli_real_escape_string($conexion,$user['correo'])."'");
if ($check && mysqli_num_rows($check) > 0) {
    $row = mysqli_fetch_assoc($check);
    echo json_encode(["ok"=>true,"id_cliente"=>$row['id_clientes'],"msg"=>"Ya existía"]);
    exit;
}

// Crear el registro en clientes
$nombre = mysqli_real_escape_string($conexion, $user['nombre']);
$correo = mysqli_real_escape_string($conexion, $user['correo']);
// Generar cédula temporal única
$cedula_tmp = 'USR'.$id_usuario;

$sql = "INSERT INTO clientes (id_usuario, cedula, nombre, correo, direccion, telefono)
        VALUES ($id_usuario,'$cedula_tmp','$nombre','$correo','','')";
if (mysqli_query($conexion, $sql)) {
    echo json_encode(["ok"=>true,"id_cliente"=>mysqli_insert_id($conexion),"msg"=>"Creado y vinculado"]);
} else {
    echo json_encode(["ok"=>false,"error"=>mysqli_error($conexion)]);
}
?>
