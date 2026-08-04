<?php
include("conexion.php");
header('Content-Type: application/json');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// 1. LISTAR ESPECIALIDADES
if ($accion === 'listar_especialidades') {
    $sql = "SELECT nombre FROM especialidades ORDER BY nombre ASC";
    $res = mysqli_query($conexion, $sql);
    $especialidades = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $especialidades[] = $row;
    }
    echo json_encode($especialidades);
    exit;
}

// 2. LISTAR ABOGADOS
if ($accion === 'listar' || ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($accion))) {
    $sql = "SELECT a.*, GROUP_CONCAT(e.nombre SEPARATOR ', ') AS especialidades
            FROM abogados a
            LEFT JOIN abogado_especialidad ae ON a.id_abogado = ae.id_abogado
            LEFT JOIN especialidades e ON ae.id_especialidad = e.id_especialidad
            GROUP BY a.id_abogado ORDER BY a.nombre";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    echo json_encode($lista);
    exit;
}

// 3. CREAR ABOGADO
if ($accion === 'crear') {
    // Usamos ?? '' para evitar el error "Undefined array key"
    $cedula    = mysqli_real_escape_string($conexion, $_POST['cedula'] ?? '');
    $nombre    = mysqli_real_escape_string($conexion, $_POST['nombre'] ?? '');
    $direccion = mysqli_real_escape_string($conexion, $_POST['direccion'] ?? '');
    $telefono  = mysqli_real_escape_string($conexion, $_POST['telefono'] ?? '');
    $correo    = mysqli_real_escape_string($conexion, $_POST['correo'] ?? '');
    $tarjeta   = mysqli_real_escape_string($conexion, $_POST['tarjeta'] ?? '');
    $especialidad = mysqli_real_escape_string($conexion, $_POST['especialidad'] ?? '');

    $sql = "INSERT INTO abogados (cedula, nombre, direccion, telefono, correo, tarjeta_profesional)
            VALUES ('$cedula','$nombre','$direccion','$telefono','$correo','$tarjeta')";
            
    if (!mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
        exit;
    }
    $id_abogado = mysqli_insert_id($conexion);

    if (!empty($especialidad)) {
        $resEsp = mysqli_query($conexion, "SELECT id_especialidad FROM especialidades WHERE nombre='$especialidad'");
        if (mysqli_num_rows($resEsp) > 0) {
            $rowEsp = mysqli_fetch_assoc($resEsp);
            $id_esp = $rowEsp['id_especialidad'];
        } else {
            mysqli_query($conexion, "INSERT INTO especialidades (nombre) VALUES ('$especialidad')");
            $id_esp = mysqli_insert_id($conexion);
        }
        mysqli_query($conexion, "INSERT INTO abogado_especialidad (id_abogado, id_especialidad) VALUES ($id_abogado, $id_esp)");
    }

    $sql_u = "INSERT INTO usuarios (nombre, correo, password, rol, estado)
              VALUES ('$nombre','$correo','1234','abogado','activo')";
    mysqli_query($conexion, $sql_u);

    echo json_encode(["ok" => true, "id" => $id_abogado]);
    exit;
}

// 4. ACTUALIZAR ABOGADO
if ($accion === 'actualizar') {
    $id = intval($_POST['id'] ?? 0);
    
    $cedula    = mysqli_real_escape_string($conexion, $_POST['cedula'] ?? '');
    $nombre    = mysqli_real_escape_string($conexion, $_POST['nombre'] ?? '');
    $correo    = mysqli_real_escape_string($conexion, $_POST['correo'] ?? '');
    $telefono  = mysqli_real_escape_string($conexion, $_POST['telefono'] ?? '');
    $direccion = mysqli_real_escape_string($conexion, $_POST['direccion'] ?? '');
    $tarjeta   = mysqli_real_escape_string($conexion, $_POST['tarjeta'] ?? '');
    $especialidad = mysqli_real_escape_string($conexion, $_POST['especialidad'] ?? '');

    // Si tienes la funcion registrarBitacora en tu conexion.php, descomenta la siguiente línea:
    // registrarBitacora($conexion, 'abogados', $id, $_POST);

    $sql = "UPDATE abogados SET 
            cedula='$cedula',
            nombre='$nombre', 
            correo='$correo',
            telefono='$telefono',
            direccion='$direccion',
            tarjeta_profesional='$tarjeta'
            WHERE id_abogado=$id";
            
    if (mysqli_query($conexion, $sql)) {
        // Actualizamos especialidad borrando la vieja y poniendo la nueva
        mysqli_query($conexion, "DELETE FROM abogado_especialidad WHERE id_abogado=$id");
        if (!empty($especialidad)) {
            $resEsp = mysqli_query($conexion, "SELECT id_especialidad FROM especialidades WHERE nombre='$especialidad'");
            if (mysqli_num_rows($resEsp) > 0) {
                $rowEsp = mysqli_fetch_assoc($resEsp);
                $id_esp = $rowEsp['id_especialidad'];
            } else {
                mysqli_query($conexion, "INSERT INTO especialidades (nombre) VALUES ('$especialidad')");
                $id_esp = mysqli_insert_id($conexion);
            }
            mysqli_query($conexion, "INSERT INTO abogado_especialidad (id_abogado, id_especialidad) VALUES ($id, $id_esp)");
        }
        
        // ¡ESTO ERA LO QUE FALTABA PARA QUE NO DIERA ERROR DE JSON!
        echo json_encode(["ok" => true]); 
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
    exit;
}

// 5. ELIMINAR ABOGADO
if ($accion === 'eliminar') {
    $id = intval($_POST['id']);
    mysqli_query($conexion, "DELETE FROM abogados WHERE id_abogado=$id");
    echo json_encode(["ok" => true]);
    exit;
}

echo json_encode(["ok" => false, "error" => "Acción no válida o no recibida"]);
?>