<?php
include 'conexion.php';

// R4.1 Crear expediente
if (isset($_POST['crear_expediente'])) {
    $id_cliente  = mysqli_real_escape_string($conexion, $_POST['id_cliente']);
    $id_casos    = mysqli_real_escape_string($conexion, $_POST['id_casos']);
    $fecha_inicio = mysqli_real_escape_string($conexion, $_POST['fecha_inicio']);
    $fecha_final  = mysqli_real_escape_string($conexion, $_POST['fecha_final']);
    $estado       = 'En trámite'; // Estado inicial (R4.6)

    $sql = "INSERT INTO expedientes (id_cliente, id_casos, fecha_inicio, fecha_final, estado)
            VALUES ('$id_cliente', '$id_casos', '$fecha_inicio', '$fecha_final', '$estado')";
    mysqli_query($conexion, $sql);
}

// R5.1 Consultar expedientes por cliente
$sql_consulta = "SELECT e.id_expediente, c.nombre AS cliente, e.estado, e.fecha_inicio, e.fecha_final
                 FROM expedientes e
                 JOIN clientes c ON e.id_cliente = c.id_clientes";
$expedientes = mysqli_query($conexion, $sql_consulta);
?>