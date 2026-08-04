<?php
session_start();
include("conexion.php");

$correo     = $_POST['correo']     ?? $_SESSION['correo_recuperacion'] ?? '';
$nuevaClave = $_POST['nuevaClave'] ?? '';
$confirmar  = $_POST['confirmarClave'] ?? '';

if (!$correo || !$nuevaClave) {
    echo json_encode(["ok" => false, "error" => "Datos incompletos"]);
    exit;
}

if ($nuevaClave !== $confirmar) {
    echo "<script>alert('Las contraseñas no coinciden'); window.history.back();</script>";
    exit;
}

$correoEsc = mysqli_real_escape_string($conexion, $correo);
$claveEsc  = mysqli_real_escape_string($conexion, $nuevaClave);

$sql = "UPDATE usuarios SET password='$claveEsc' WHERE correo='$correoEsc'";
if (mysqli_query($conexion, $sql) && mysqli_affected_rows($conexion) > 0) {
    unset($_SESSION['correo_recuperacion']);
    echo "<script>alert('Contraseña actualizada correctamente'); window.location.href='../html/index.html';</script>";
} else {
    echo "<script>alert('Correo no encontrado'); window.history.back();</script>";
}
?>
