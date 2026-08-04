<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("conexion.php");

$accion = $_POST['accion'] ?? '';

// ─── REGISTRO ────────────────────────────────────────────────
if ($accion === 'registro') {
    $tipoDoc   = mysqli_real_escape_string($conexion, $_POST['tipoDoc']);
    $documento = mysqli_real_escape_string($conexion, $_POST['documento']);
    $nombre    = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $correo    = mysqli_real_escape_string($conexion, $_POST['correo']);
    $telefono  = mysqli_real_escape_string($conexion, $_POST['telefono']);
    $rol       = mysqli_real_escape_string($conexion, $_POST['rol']);
    $clave     = mysqli_real_escape_string($conexion, $_POST['clave']);
    $confirmar = mysqli_real_escape_string($conexion, $_POST['confirmar']);

    if ($clave !== $confirmar) {
        echo "<script>alert('Las contraseñas no coinciden'); window.history.back();</script>";
        exit;
    }

    // 1. Insertar en la tabla USUARIOS (Siempre)
    $sqlUsuario = "INSERT INTO usuarios (nombre, correo, password, rol, estado) 
                   VALUES ('$nombre', '$correo', '$clave', '$rol', 'activo')";
    
    if (mysqli_query($conexion, $sqlUsuario)) {
        $id_usuario_nuevo = mysqli_insert_id($conexion); // Obtenemos el ID generado

        // 2. Si el rol es ABOGADO, insertar en la tabla ABOGADOS
        if ($rol === 'abogado') {
            $sqlAbogado = "INSERT INTO abogados (id_usuario, cedula, nombre, correo, telefono) 
                           VALUES ($id_usuario_nuevo, '$documento', '$nombre', '$correo', '$telefono')";
            mysqli_query($conexion, $sqlAbogado);
        } 
        
        // 3. Si el rol es CLIENTE, insertar en la tabla CLIENTES
        else if ($rol === 'cliente') {
            $sqlCliente = "INSERT INTO clientes (id_usuario, cedula, nombre, correo, telefono) 
                            VALUES ($id_usuario_nuevo, '$documento', '$nombre', '$correo', '$telefono')";
            mysqli_query($conexion, $sqlCliente);
        }

        echo "<script>alert('Registro exitoso'); window.location.href='../html/index.html';</script>";
    } else {
        echo "Error: " . mysqli_error($conexion);
    }
    exit;
}

// ─── LOGIN ───────────────────────────────────────────────────
if ($accion === "login") {
    $correo = mysqli_real_escape_string($conexion, $_POST['correo'] ?? '');
    $clave  = $_POST['clave'] ?? '';

    $res = mysqli_query($conexion, "SELECT * FROM usuarios WHERE correo='$correo' AND estado='activo'");
    if ($res && mysqli_num_rows($res) > 0) {
        $user = mysqli_fetch_assoc($res);
        if ($clave === $user['password']) {
            $rol = strtolower(trim($user['rol']));
            $_SESSION['id']     = $user['id_usuarios'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['rol']    = $rol;

            // Buscar id_clientes: primero por id_usuario, luego por correo
            // Si no existe, crear automáticamente
            $id_cliente = 0;
            if ($rol === 'cliente') {
                $uid = intval($user['id_usuarios']);
                $rc  = mysqli_query($conexion,
                    "SELECT id_clientes FROM clientes
                     WHERE id_usuario=$uid OR correo='$correo'
                     LIMIT 1");
                if ($rc && mysqli_num_rows($rc) > 0) {
                    $id_cliente = intval(mysqli_fetch_assoc($rc)['id_clientes']);
                } else {
                    // Crear el registro en clientes automáticamente
                    $nombreEsc  = mysqli_real_escape_string($conexion, $user['nombre']);
                    $cedula_tmp = 'USR' . $uid;
                    $ins = mysqli_query($conexion,
                        "INSERT INTO clientes (id_usuario, cedula, nombre, correo, direccion, telefono)
                         VALUES ($uid,'$cedula_tmp','$nombreEsc','$correo','','')");
                    if ($ins) $id_cliente = mysqli_insert_id($conexion);
                }
            }
            $id_abogado = 0;

if ($rol === 'abogado') {
    $uid = intval($user['id_usuarios']);

    $ra = mysqli_query($conexion,
        "SELECT id_abogado FROM abogados WHERE id_usuario = $uid LIMIT 1"
    );

    if ($ra && mysqli_num_rows($ra) > 0) {
        $id_abogado = intval(mysqli_fetch_assoc($ra)['id_abogado']);
    }
}

echo "<script>
    sessionStorage.setItem('rol',         '".addslashes($rol)."');
    sessionStorage.setItem('nombre',      '".addslashes($user['nombre'])."');
    sessionStorage.setItem('correo',      '".addslashes($user['correo'])."'); // <--- AGREGA ESTA LÍNEA
    sessionStorage.setItem('id',          '".$user['id_usuarios']."');
    sessionStorage.setItem('id_cliente',  '".$id_cliente."');
    sessionStorage.setItem('id_abogado',  '".$id_abogado."');
    window.location.href = '../html/dashboard-".$rol.".html';
</script>";
            
        } else {
            echo json_encode(["ok" => false, "msg" => "clave_incorrecta"]);
        }
    } else {
        echo json_encode(["ok" => false, "msg" => "usuario_no_encontrado"]);
    }
    exit;
}
?>
