<?php
include("conexion.php");
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ── CREAR CASO + EXPEDIENTE + VINCULAR ABOGADO ───────────────────────
if ($accion === 'crear') {
    $titulo      = mysqli_real_escape_string($conexion, $_POST['titulo']      ?? '');
    $descripcion = mysqli_real_escape_string($conexion, $_POST['descripcion'] ?? '');
    $id_cliente  = intval($_POST['id_cliente']  ?? 0);
    $id_abogado  = intval($_POST['id_abogado']  ?? 0);
    $estado      = mysqli_real_escape_string($conexion, $_POST['estado']      ?? 'Pendiente');
    $fecha       = date('Y-m-d');

    if (!$titulo || $id_cliente === 0) {
        echo json_encode(["ok" => false, "error" => "Título y cliente son obligatorios"]);
        exit;
    }

    mysqli_begin_transaction($conexion);
    try {
        // 1. Insertar caso
        $sql1 = "INSERT INTO casos (titulo, descripcion, estado, fecha_creacion, id_cliente)
                 VALUES ('$titulo', '$descripcion', '$estado', '$fecha', $id_cliente)";
        if (!mysqli_query($conexion, $sql1)) throw new Exception(mysqli_error($conexion));
        $id_caso = mysqli_insert_id($conexion);

        $id_exp = null;
        // 2. Si hay abogado, crear expediente y vincular
        if ($id_abogado > 0) {
            $sql2 = "INSERT INTO expedientes (id_cliente, id_casos, fecha_inicio, estado)
                     VALUES ($id_cliente, $id_caso, '$fecha', 'En trámite')";
            if (!mysqli_query($conexion, $sql2)) throw new Exception(mysqli_error($conexion));
            $id_exp = mysqli_insert_id($conexion);

            $sql3 = "INSERT INTO expediente_abogados (id_expediente, id_abogado)
                     VALUES ($id_exp, $id_abogado)";
            if (!mysqli_query($conexion, $sql3)) throw new Exception(mysqli_error($conexion));
        }

        // 3. Log
        $uid = intval($_SESSION['id'] ?? 0);
        if ($uid > 0) {
            $logSql = "INSERT INTO log_transacciones (id_usuario, accion, tabla_afectada, id_registro, valor_nuevo)
                       VALUES ($uid, 'Creación de caso', 'casos', $id_caso, 'Título: $titulo')";
            mysqli_query($conexion, $logSql);
        }

        mysqli_commit($conexion);
        echo json_encode(["ok" => true, "id_caso" => $id_caso, "id_expediente" => $id_exp]);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        echo json_encode(["ok" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// ── LISTAR TODOS ─────────────────────────────────────────────────────
if ($accion === 'leer_todos') {
    $sql = "SELECT c.*, cl.nombre AS nombre_cliente,
                   ab.nombre AS nombre_abogado, e.id_expediente, e.estado AS estado_expediente
            FROM casos c
            LEFT JOIN clientes cl ON c.id_cliente = cl.id_clientes
            LEFT JOIN expedientes e ON c.id_casos = e.id_casos
            LEFT JOIN expediente_abogados ea ON e.id_expediente = ea.id_expediente
            LEFT JOIN abogados ab ON ea.id_abogado = ab.id_abogado
            GROUP BY c.id_casos
            ORDER BY c.fecha_creacion DESC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) $lista[] = $row;
    echo json_encode($lista);
    exit;
}

// ── LISTAR PENDIENTES (sin abogado asignado) ─────────────────────────
if ($accion === 'pendientes') {
    $sql = "SELECT c.*, cl.nombre AS nombre_cliente
            FROM casos c
            LEFT JOIN clientes cl ON c.id_cliente = cl.id_clientes
            WHERE c.id_abogado IS NULL AND c.estado NOT IN ('Finalizado', 'Archivado')
            ORDER BY c.fecha_creacion DESC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) $lista[] = $row;
    echo json_encode($lista);
    exit;
}

// ── CASOS DE UN ABOGADO ──────────────────────────────────────────────
if ($accion === 'mis_casos_abogado') {
    $id_abogado = intval($_POST['id_abogado'] ?? 0);
    if ($id_abogado === 0) { echo json_encode([]); exit; }

    $sql = "SELECT c.*, cl.nombre AS nombre_cliente, e.id_expediente, e.estado AS estado_expediente
            FROM casos c
            INNER JOIN expedientes e ON c.id_casos = e.id_casos
            INNER JOIN expediente_abogados ea ON e.id_expediente = ea.id_expediente
            LEFT  JOIN clientes cl ON c.id_cliente = cl.id_clientes
            WHERE ea.id_abogado = $id_abogado
            ORDER BY c.fecha_creacion DESC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) $lista[] = $row;
    echo json_encode($lista);
    exit;
}

// ── CASOS DE UN CLIENTE ──────────────────────────────────────────────
if ($accion === 'mis_casos_cliente') {
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

// ── ACTUALIZAR ESTADO ────────────────────────────────────────────────
if ($accion === 'estado') {
    $id     = intval($_POST['id'] ?? 0);
    $estado = mysqli_real_escape_string($conexion, $_POST['estado'] ?? '');

    mysqli_begin_transaction($conexion);
    try {
        mysqli_query($conexion, "UPDATE casos SET estado='$estado' WHERE id_casos=$id");

        // Sincronizar expediente
        $mapeo = ['Finalizado' => 'Cerrado', 'Archivado' => 'Archivado', 'En proceso' => 'En trámite', 'Pendiente' => 'En trámite'];
        $estadoExp = $mapeo[$estado] ?? 'En trámite';
        $extraExp  = ($estadoExp === 'Cerrado' || $estadoExp === 'Archivado') ? ", fecha_final=CURDATE()" : "";
        mysqli_query($conexion, "UPDATE expedientes SET estado='$estadoExp' $extraExp WHERE id_casos=$id");

        mysqli_commit($conexion);
        echo json_encode(["ok" => true]);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        echo json_encode(["ok" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// ── ASIGNAR ABOGADO A CASO EXISTENTE ────────────────────────────────
if ($accion === 'asignar') {
    $id_caso    = intval($_POST['id_caso']    ?? 0);
    $id_abogado = intval($_POST['id_abogado'] ?? 0);
    $estado     = mysqli_real_escape_string($conexion, $_POST['estado'] ?? 'En proceso');
    $fecha      = date('Y-m-d');

    mysqli_begin_transaction($conexion);
    try {
        // Actualizar estado del caso
        mysqli_query($conexion, "UPDATE casos SET estado='$estado', id_abogado=$id_abogado WHERE id_casos=$id_caso");

        // Obtener cliente del caso
        $res = mysqli_query($conexion, "SELECT id_cliente FROM casos WHERE id_casos=$id_caso");
        $fila = mysqli_fetch_assoc($res);
        $id_cliente = intval($fila['id_cliente'] ?? 0);

        // Verificar si ya existe expediente para este caso
        $resExp = mysqli_query($conexion, "SELECT id_expediente FROM expedientes WHERE id_casos=$id_caso LIMIT 1");
        if (mysqli_num_rows($resExp) > 0) {
            $id_exp = intval(mysqli_fetch_assoc($resExp)['id_expediente']);
            // Asignar abogado si no está ya
            $check = mysqli_query($conexion,
                "SELECT 1 FROM expediente_abogados WHERE id_expediente=$id_exp AND id_abogado=$id_abogado");
            if (mysqli_num_rows($check) === 0) {
                mysqli_query($conexion,
                    "INSERT INTO expediente_abogados (id_expediente, id_abogado) VALUES ($id_exp, $id_abogado)");
            }
            // Actualizar estado expediente
            mysqli_query($conexion, "UPDATE expedientes SET estado='En trámite' WHERE id_expediente=$id_exp");
        } else {
            // Crear nuevo expediente
            mysqli_query($conexion,
                "INSERT INTO expedientes (id_cliente, id_casos, fecha_inicio, estado)
                 VALUES ($id_cliente, $id_caso, '$fecha', 'En trámite')");
            $id_exp = mysqli_insert_id($conexion);
            mysqli_query($conexion,
                "INSERT INTO expediente_abogados (id_expediente, id_abogado) VALUES ($id_exp, $id_abogado)");
        }

        mysqli_commit($conexion);
        echo json_encode(["ok" => true, "id_expediente" => $id_exp]);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        echo json_encode(["ok" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// ── ELIMINAR CASO ────────────────────────────────────────────────────
if ($accion === 'eliminar') {
    $id = intval($_POST['id'] ?? 0);
    // expedientes y expediente_abogados eliminan en cascada por FK
    $sql = "DELETE FROM casos WHERE id_casos=$id";
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
    exit;
}

echo json_encode(["ok" => false, "error" => "Acción desconocida: $accion"]);
