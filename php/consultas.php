<?php
include("conexion.php");

// CASOS POR CLIENTE
$sql = "SELECT c.nombre, e.id_expediente
        FROM clientes c
        JOIN expedientes e ON c.id_clientes = e.id_cliente";

$res = mysqli_query($conexion, $sql);

$data = [];
while($row = mysqli_fetch_assoc($res)){
    $data[] = $row;
}

echo json_encode($data);
?>