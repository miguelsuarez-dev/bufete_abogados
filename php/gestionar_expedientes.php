<?php
include("conexion.php");
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ── CREAR EXPEDIENTE (R4.1 + R4.5) ──────────────────────────────────
if ($accion === 'crear') {
    $id_cliente = intval($_POST['id_cliente'] ?? 0);
    $id_casos   = intval($_POST['id_casos']   ?? 0);
    $id_abogado = intval($_POST['id_abogado'] ?? 0);
    $fecha      = date('Y-m-d');

    if ($id_cliente === 0 || $id_casos === 0 || $id_abogado === 0) {
        echo json_encode(["ok" => false, "error" => "Faltan datos obligatorios"]);
        exit;
    }

    mysqli_begin_transaction($conexion);
    try {
        // 1. Insertar expediente
        $sql = "INSERT INTO expedientes (id_cliente, id_casos, fecha_inicio, estado)
                VALUES ($id_cliente, $id_casos, '$fecha', 'En trámite')";
        if (!mysqli_query($conexion, $sql)) throw new Exception(mysqli_error($conexion));
        $id_exp = mysqli_insert_id($conexion);

        // 2. Asignar abogado (tabla intermedia R4.5)
        $sqlAb = "INSERT INTO expediente_abogados (id_expediente, id_abogado)
                  VALUES ($id_exp, $id_abogado)";
        if (!mysqli_query($conexion, $sqlAb)) throw new Exception(mysqli_error($conexion));

        // 3. Log
        $uid = intval($_SESSION['id'] ?? 0);
        if ($uid > 0) {
            $logSql = "INSERT INTO log_transacciones (id_usuario, accion, tabla_afectada, id_registro, valor_nuevo)
                       VALUES ($uid, 'Creación de expediente', 'expedientes', $id_exp, 'Estado: En trámite')";
            mysqli_query($conexion, $logSql);
        }

        mysqli_commit($conexion);
        echo json_encode(["ok" => true, "id" => $id_exp]);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        echo json_encode(["ok" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// ── LISTAR EXPEDIENTES (R4.2 + R5.1 + R5.2 + R5.3) ─────────────────
if ($accion === 'listar') {
    $where = "1=1";
    if (!empty($_GET['id_cliente'])) $where .= " AND e.id_cliente = " . intval($_GET['id_cliente']);
    if (!empty($_GET['id_abogado'])) $where .= " AND ea.id_abogado = " . intval($_GET['id_abogado']);
    if (!empty($_GET['estado']))     $where .= " AND e.estado = '" . mysqli_real_escape_string($conexion, $_GET['estado']) . "'";
    if (!empty($_GET['busqueda'])) {
        $b = mysqli_real_escape_string($conexion, $_GET['busqueda']);
        $where .= " AND (cl.nombre LIKE '%$b%' OR ab.nombre LIKE '%$b%' OR c.titulo LIKE '%$b%')";
    }

    $sql = "SELECT e.id_expediente, e.id_cliente, e.id_casos, e.fecha_inicio, e.fecha_final, e.estado,
                   cl.nombre AS nombre_cliente, cl.cedula AS cedula_cliente,
                   ab.nombre AS nombre_abogado, ab.id_abogado,
                   c.titulo AS titulo_caso, c.descripcion AS descripcion_caso
            FROM expedientes e
            INNER JOIN clientes cl ON e.id_cliente = cl.id_clientes
            LEFT  JOIN expediente_abogados ea ON e.id_expediente = ea.id_expediente
            LEFT  JOIN abogados ab ON ea.id_abogado = ab.id_abogado
            LEFT  JOIN casos c ON e.id_casos = c.id_casos
            WHERE $where
            ORDER BY e.id_expediente DESC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) $lista[] = $row;
    echo json_encode($lista);
    exit;
}

// ── ACTUALIZAR ESTADO (R4.3 + R4.6) ─────────────────────────────────
if ($accion === 'actualizar') {
    $id     = intval($_POST['id'] ?? 0);
    $estado = mysqli_real_escape_string($conexion, $_POST['estado'] ?? '');

    $datosNuevos = ['estado' => $estado];
    registrarBitacora($conexion, 'expedientes', $id, $datosNuevos);

    $sqlExtra = ($estado === 'Archivado' || $estado === 'Cerrado') ? ", fecha_final=CURDATE()" : "";
    $sql = "UPDATE expedientes SET estado='$estado' $sqlExtra WHERE id_expediente=$id";

    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
    exit;
}

// ── ARCHIVAR (R4.4) ──────────────────────────────────────────────────
if ($accion === 'archivar') {
    $id = intval($_POST['id'] ?? 0);
    $sql = "UPDATE expedientes SET estado='Archivado', fecha_final=CURDATE() WHERE id_expediente=$id";

    $datosNuevos = ['estado' => 'Archivado'];
    registrarBitacora($conexion, 'expedientes', $id, $datosNuevos);

    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
    exit;
}

// ── ASIGNAR ABOGADO ADICIONAL (R4.5) ─────────────────────────────────
if ($accion === 'asignar_abogado') {
    $id_exp     = intval($_POST['id_expediente'] ?? 0);
    $id_abogado = intval($_POST['id_abogado']    ?? 0);

    // Evitar duplicados
    $check = mysqli_query($conexion,
        "SELECT 1 FROM expediente_abogados WHERE id_expediente=$id_exp AND id_abogado=$id_abogado");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(["ok" => false, "error" => "El abogado ya está asignado a este expediente"]);
        exit;
    }

    $sql = "INSERT INTO expediente_abogados (id_expediente, id_abogado) VALUES ($id_exp, $id_abogado)";
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
    exit;
}

// ── ELIMINAR ─────────────────────────────────────────────────────────
if ($accion === 'eliminar') {
    $id = intval($_POST['id'] ?? 0);
    // expediente_abogados se elimina en cascada
    $sql = "DELETE FROM expedientes WHERE id_expediente=$id";
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
    exit;
}

echo json_encode(["ok" => false, "error" => "Acción desconocida: $accion"]);
