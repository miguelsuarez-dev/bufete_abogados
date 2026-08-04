<?php
include("conexion.php");
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$accion = $_POST['accion'] ?? $_GET['tipo'] ?? $_GET['accion'] ?? '';

// LISTAR PENDIENTES (casos sin abogado asignado)
if ($accion === 'pendientes') {
    $sql = "SELECT c.*, cl.nombre AS nombre_cliente
            FROM casos c
            LEFT JOIN clientes cl ON c.id_cliente = cl.id_clientes
            WHERE c.id_abogado IS NULL
            ORDER BY c.fecha_creacion DESC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) $lista[] = $row;
    echo json_encode($lista);
    exit;
}

// ASIGNAR ABOGADO Y CREAR/ACTUALIZAR EXPEDIENTE
if ($accion === 'asignar') {
    $id_caso    = intval($_POST['id_caso']    ?? 0);
    $id_abogado = intval($_POST['id_abogado'] ?? 0);
    $estado     = mysqli_real_escape_string($conexion, $_POST['estado'] ?? 'En proceso');
    $fecha      = date('Y-m-d');

    mysqli_begin_transaction($conexion);
    try {
        mysqli_query($conexion, "UPDATE casos SET estado='$estado', id_abogado=$id_abogado WHERE id_casos=$id_caso");

        $res = mysqli_query($conexion, "SELECT id_cliente FROM casos WHERE id_casos=$id_caso");
        $fila = mysqli_fetch_assoc($res);
        $id_cliente = intval($fila['id_cliente']);

        $resExp = mysqli_query($conexion, "SELECT id_expediente FROM expedientes WHERE id_casos=$id_caso LIMIT 1");
        if (mysqli_num_rows($resExp) > 0) {
            $id_expediente = intval(mysqli_fetch_assoc($resExp)['id_expediente']);
            $check = mysqli_query($conexion,
                "SELECT 1 FROM expediente_abogados WHERE id_expediente=$id_expediente AND id_abogado=$id_abogado");
            if (mysqli_num_rows($check) === 0) {
                mysqli_query($conexion,
                    "INSERT INTO expediente_abogados (id_expediente, id_abogado) VALUES ($id_expediente, $id_abogado)");
            }
        } else {
            $sqlExp = "INSERT INTO expedientes (id_cliente, id_casos, fecha_inicio, estado)
                       VALUES ($id_cliente, $id_caso, '$fecha', 'En trámite')";
            mysqli_query($conexion, $sqlExp);
            $id_expediente = mysqli_insert_id($conexion);
            mysqli_query($conexion,
                "INSERT INTO expediente_abogados (id_expediente, id_abogado) VALUES ($id_expediente, $id_abogado)");
        }

        mysqli_commit($conexion);
        echo json_encode(["ok" => true, "id_expediente" => $id_expediente]);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        echo json_encode(["ok" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// MIS CASOS (para el portal cliente)
if ($accion === 'mis_casos') {
    $id_cliente = intval($_POST['id_cliente'] ?? 0);
    if ($id_cliente === 0) { echo json_encode([]); exit; }

    $sql = "SELECT c.*, ab.nombre AS nombre_abogado, e.id_expediente, e.estado AS estado_expediente
            FROM casos c
            LEFT JOIN expedientes e ON c.id_casos = e.id_casos
            LEFT JOIN expediente_abogados ea ON e.id_expediente = ea.id_expediente
            LEFT JOIN abogados ab ON ea.id_abogado = ab.id_abogado
            WHERE c.id_cliente = $id_cliente
            GROUP BY c.id_casos
            ORDER BY c.fecha_creacion DESC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) $lista[] = $row;
    echo json_encode($lista);
    exit;
}

// SOLICITAR NUEVO CASO (desde el portal cliente)
if ($accion === 'solicitar') {
    $id_cliente  = intval($_POST['id_cliente'] ?? 0);
    $titulo      = mysqli_real_escape_string($conexion, $_POST['titulo']      ?? '');
    $descripcion = mysqli_real_escape_string($conexion, $_POST['descripcion'] ?? '');
    $fecha       = date('Y-m-d');

    if (!$titulo || $id_cliente === 0) {
        echo json_encode(["ok" => false, "error" => "Datos incompletos"]);
        exit;
    }

    $sql = "INSERT INTO casos (titulo, descripcion, estado, fecha_creacion, id_cliente)
            VALUES ('$titulo', '$descripcion', 'Pendiente', '$fecha', $id_cliente)";
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true, "id" => mysqli_insert_id($conexion)]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
    exit;
}

echo json_encode(["ok" => false, "error" => "Acción desconocida: $accion"]);
