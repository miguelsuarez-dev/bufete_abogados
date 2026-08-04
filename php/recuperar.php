<?php
session_start();

include("conexion.php");

$correo = $_POST['correo'] ?? '';

$_SESSION['correo_recuperacion'] = $correo;

// 🔥 REDIRECCIÓN CORRECTA
echo "<script>
window.location.href = '/bufete/html/restablecer.html';
</script>";
?>