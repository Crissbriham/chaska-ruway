<?php
/* Direcciones, métodos de pago, compra y pedidos. Sin HTML. */
require_once __DIR__ . '/../includes/helpers.php';

function obtenerDirecciones($id_comprador) {
    return filas("SELECT d.*, cd.alias, cd.predeterminada FROM Comprador_Direccion cd
                  JOIN Direccion d ON d.id_direccion = cd.id_direccion
                  WHERE cd.id_comprador = ? ORDER BY cd.predeterminada DESC, d.id_direccion", "i", $id_comprador);
}

function obtenerMetodosPago($id_comprador) {
    return filas("SELECT mp.id_metodo, mp.nombre, mp.descripcion, cm.alias, cm.ultimos_digitos, COALESCE(cm.predeterminado, 0) AS predeterminado
                  FROM Metodo_Pago mp
                  LEFT JOIN Comprador_MetodoPago cm ON cm.id_metodo = mp.id_metodo AND cm.id_comprador = ?
                  WHERE mp.activo = 1 ORDER BY predeterminado DESC, mp.id_metodo", "i", $id_comprador);
}

/* Guarda una dirección nueva y la liga al comprador. Devuelve su id. */
function crearDireccion($id_comprador, $d) {
    $tel = $d['telefono'] === '' ? null : $d['telefono'];
    $ref = $d['referencia'] === '' ? null : $d['referencia'];
    $id = ejecutar("INSERT INTO Direccion (destinatario, telefono, departamento, provincia, distrito, direccion, referencia)
                    VALUES (?, ?, ?, ?, ?, ?, ?)", "sssssss",
                    $d['destinatario'], $tel, $d['departamento'], $d['provincia'], $d['distrito'], $d['direccion'], $ref);
    $tiene = (int)fila("SELECT COUNT(*) AS n FROM Comprador_Direccion WHERE id_comprador = ?", "i", $id_comprador)['n'];
    $alias = $d['alias'] !== '' ? $d['alias'] : 'Dirección ' . ($tiene + 1);
    ejecutar("INSERT INTO Comprador_Direccion (id_comprador, id_direccion, alias, predeterminada) VALUES (?, ?, ?, ?)",
             "iisi", $id_comprador, $id, $alias, $tiene ? 0 : 1);
    return $id;
}

/* Llama a ProcesarCompraCarrito(id_comprador, id_metodo, id_direccion).
   El procedimiento crea pedido, detalle, pago, envío e historial, descuenta stock y vacía el carrito.
   Devuelve el id del pedido; si algo falla lanza RuntimeException con un mensaje para mostrar. */
function procesarCompra($id_comprador, $id_metodo, $id_direccion) {
    global $conexion;
    try {
        $stmt = $conexion->prepare("CALL ProcesarCompraCarrito(?, ?, ?)");
        $stmt->bind_param("iii", $id_comprador, $id_metodo, $id_direccion);
        $stmt->execute();
        $res = $stmt->get_result();
        $id_pedido = (int)$res->fetch_assoc()['id_pedido'];
        $res->free();
        while ($stmt->more_results()) { $stmt->next_result(); }
        $stmt->close();
        return $id_pedido;
    } catch (mysqli_sql_exception $ex) {
        // 1644 = error lanzado con SIGNAL dentro del procedimiento (mensaje pensado para el cliente)
        if ($ex->getCode() === 1644) throw new RuntimeException($ex->getMessage());
        throw new RuntimeException('No pudimos registrar tu compra. Intenta de nuevo en un momento.');
    }
}

function obtenerPedidos($id_comprador) {
    return filas("SELECT pe.id_pedido, pe.fecha_pedido, pe.estado_pedido, pe.total_pedido,
                  (SELECT mp.nombre FROM Pago pa JOIN Metodo_Pago mp ON mp.id_metodo = pa.id_metodo
                   WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS metodo,
                  en.empresa_envio, en.numero_seguimiento, en.estado AS estado_envio,
                  d.distrito, d.provincia, d.direccion
                  FROM Pedidos pe
                  LEFT JOIN Envio en ON en.id_pedido = pe.id_pedido
                  LEFT JOIN Direccion d ON d.id_direccion = en.id_direccion
                  WHERE pe.id_comprador = ? ORDER BY pe.fecha_pedido DESC, pe.id_pedido DESC", "i", $id_comprador);
}

/* Productos de cada pedido, agrupados por id_pedido */
function obtenerDetallePedidos($id_comprador) {
    $detalle = [];
    foreach (filas("SELECT dp.id_pedido, dp.cantidad, dp.precio_unitario, p.id_producto, p.nombre
                    FROM Detalle_Pedidos dp JOIN Pedidos pe ON pe.id_pedido = dp.id_pedido
                    JOIN Productos p ON p.id_producto = dp.id_producto
                    WHERE pe.id_comprador = ? ORDER BY dp.id_detalle", "i", $id_comprador) as $d) {
        $detalle[$d['id_pedido']][] = $d;
    }
    return $detalle;
}

/* Fecha en que cada pedido llegó a cada estado: [id_pedido][id_estado] = fecha */
function obtenerHistorial($id_comprador) {
    $historial = [];
    foreach (filas("SELECT id_pedido, id_estado, MIN(fecha) AS fecha FROM Historial_Compra
                    WHERE id_comprador = ? GROUP BY id_pedido, id_estado", "i", $id_comprador) as $h) {
        $historial[$h['id_pedido']][(int)$h['id_estado']] = $h['fecha'];
    }
    return $historial;
}

function obtenerPasosPedido() {
    return filas("SELECT id_estado, nombre, orden FROM Estado_Pedido WHERE nombre <> 'Cancelado' ORDER BY orden");
}

function obtenerOrdenEstados() {
    return array_column(filas("SELECT nombre, orden FROM Estado_Pedido"), 'orden', 'nombre');
}
