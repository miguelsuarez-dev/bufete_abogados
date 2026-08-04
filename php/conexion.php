<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bufete_abogados";
$port = 3307;

$conexion = new mysqli($host, $user, $pass, $db, $port);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8");

function registrarBitacora($conexion, $tabla, $id_registro, $datosNuevos) {

    if (session_status() === PHP_SESSION_NONE) session_start();
    $id_usuario = $_SESSION['id'] ?? 0; 

    $resCol = mysqli_query($conexion, "SHOW COLUMNS FROM $tabla");
    $columnaID = mysqli_fetch_array($resCol)[0];


    $sqlViejo = "SELECT * FROM $tabla WHERE $columnaID = $id_registro";
    $resViejo = mysqli_query($conexion, $sqlViejo);
    $registroAnterior = mysqli_fetch_assoc($resViejo);

    if (!$registroAnterior) return; 


    foreach ($datosNuevos as $campo => $valorNuevo) {
        // Solo comparamos campos que existan en la tabla
        if (array_key_exists($campo, $registroAnterior)) {
            $valorViejo = $registroAnterior[$campo];


            if ($valorViejo != $valorNuevo) {
                
                $v_ant = mysqli_real_escape_string($conexion, $valorViejo);
                $v_nue = mysqli_real_escape_string($conexion, $valorNuevo);
                $accion = "Edición de campo: $campo"; 

                $sqlLog = "INSERT INTO log_transacciones 
                    (id_usuario, accion, tabla_afectada, id_registro, valor_anterior, valor_nuevo)
                    VALUES 
                    ($id_usuario, '$accion', '$tabla', $id_registro, '$v_ant', '$v_nue')";
                
                mysqli_query($conexion, $sqlLog);
            }
        }
    }
}
?>
