<?php
/* Panel de vendedor: sesión, pedidos del taller y productos. Sin HTML. */
require_once __DIR__ . '/../includes/helpers.php';

const EMPRESAS_ENVIO = ['Olva Courier' => 'OLV', 'Shalom' => 'SHA'];

/* El pedido tiene al menos un producto de este vendedor (un solo ? = id_vendedor) */
const SQL_PEDIDO_DEL_VENDEDOR = "EXISTS (SELECT 1 FROM Detalle_Pedidos dx JOIN Productos px ON px.id_producto = dx.id_producto
        WHERE dx.id_pedido = pe.id_pedido AND px.id_vendedor = ?)";

function vendedorActual() { return $_SESSION['id_vendedor'] ?? null; }

function exigirVendedor() {
    if (!vendedorActual()) {
        avisar('Inicia sesión como vendedor para continuar.', 'error');
        ir('vendedor_login.php');
    }
}

function buscarVendedor($usuario) {
    return fila("SELECT id_vendedor, nombres, nombre_taller, password_hash, estado FROM Vendedores WHERE username = ? OR email = ?", "ss", $usuario, $usuario);
}

/* ---------- Pedidos ---------- */
function obtenerPedidosVendedor($idv) {
    return filas("SELECT pe.id_pedido, pe.fecha_pedido, pe.estado_pedido, pe.total_pedido, c.nombres, c.apellidos,
        (SELECT mp.nombre FROM Pago pa JOIN Metodo_Pago mp ON mp.id_metodo = pa.id_metodo WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS metodo,
        (SELECT pa.estado FROM Pago pa WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS estado_pago,
        en.empresa_envio, en.numero_seguimiento,
        d.destinatario, d.telefono, d.departamento, d.provincia, d.distrito, d.direccion, d.referencia
        FROM Pedidos pe
        JOIN Compradores c ON c.id_comprador = pe.id_comprador
        LEFT JOIN Envio en ON en.id_pedido = pe.id_pedido
        LEFT JOIN Direccion d ON d.id_direccion = en.id_direccion
        WHERE " . SQL_PEDIDO_DEL_VENDEDOR . "
        ORDER BY pe.fecha_pedido DESC, pe.id_pedido DESC", "i", $idv);
}

/* Todos los productos de esos pedidos (también los de otros talleres), agrupados por pedido */
function obtenerItemsPedidosVendedor($idv) {
    $items = [];
    foreach (filas("SELECT dp.id_pedido, dp.cantidad, dp.precio_unitario, p.nombre, v.nombre_taller, (p.id_vendedor = ?) AS mio
        FROM Detalle_Pedidos dp
        JOIN Pedidos pe ON pe.id_pedido = dp.id_pedido
        JOIN Productos p ON p.id_producto = dp.id_producto
        JOIN Vendedores v ON v.id_vendedor = p.id_vendedor
        WHERE " . SQL_PEDIDO_DEL_VENDEDOR . "
        ORDER BY dp.id_detalle", "ii", $idv, $idv) as $it) {
        $items[$it['id_pedido']][] = $it;
    }
    return $items;
}

/* Qué puede hacer el vendedor con este pedido: 'enviar', 'entregar' o null */
function accionPedido($pe) {
    if ($pe['estado_pedido'] === 'Pagado') return 'enviar';
    if ($pe['estado_pedido'] === 'Pendiente' && ($pe['metodo'] ?? '') === 'Efectivo') return 'enviar'; // se cobra al entregar
    if ($pe['estado_pedido'] === 'Enviado') return 'entregar';
    return null;
}

function idEstado($nombre) {
    return (int)fila("SELECT id_estado FROM Estado_Pedido WHERE nombre = ?", "s", $nombre)['id_estado'];
}

function registrarHistorial($pe, $estado, $comentario) {
    ejecutar("INSERT INTO Historial_Compra (id_comprador, id_pedido, id_estado, total, comentario) VALUES (?, ?, ?, ?, ?)",
             "iiids", $pe['id_comprador'], $pe['id_pedido'], idEstado($estado), $pe['total_pedido'], $comentario);
}

/* Marca un pedido como enviado o entregado. Todo o nada: si algo falla no se guarda nada.
   Lanza RuntimeException con un mensaje para mostrar. */
function avanzarPedido($idv, $id_pedido, $accion, $empresa = '', $codigo = '') {
    global $conexion;
    $pe = fila("SELECT pe.id_pedido, pe.id_comprador, pe.estado_pedido, pe.total_pedido,
        (SELECT mp.nombre FROM Pago pa JOIN Metodo_Pago mp ON mp.id_metodo = pa.id_metodo WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS metodo,
        (SELECT pa.estado FROM Pago pa WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS estado_pago
        FROM Pedidos pe WHERE pe.id_pedido = ? AND " . SQL_PEDIDO_DEL_VENDEDOR, "ii", $id_pedido, $idv);

    if (!$pe) throw new RuntimeException('No encontramos ese pedido entre los de tu taller.');
    if ($accion === 'enviar' && accionPedido($pe) !== 'enviar')
        throw new RuntimeException('Este pedido todavía no se puede despachar: el pago aún no está confirmado.');
    if ($accion === 'entregar' && accionPedido($pe) !== 'entregar')
        throw new RuntimeException('Solo puedes marcar como entregado un pedido que ya fue enviado.');

    if ($accion === 'enviar') {
        if (!isset(EMPRESAS_ENVIO[$empresa])) throw new RuntimeException('Elige una empresa de envío.');
        $codigo = trim($codigo);
        if ($codigo === '') $codigo = EMPRESAS_ENVIO[$empresa] . '-' . str_pad((string)$id_pedido, 6, '0', STR_PAD_LEFT);
        $codigo = function_exists('mb_substr') ? mb_substr($codigo, 0, 100) : substr($codigo, 0, 100);
    }

    $conexion->begin_transaction();
    try {
        if ($accion === 'enviar') {
            ejecutar("UPDATE Pedidos SET estado_pedido = 'Enviado' WHERE id_pedido = ?", "i", $id_pedido);
            ejecutar("UPDATE Envio SET empresa_envio = ?, numero_seguimiento = ?, fecha_envio = NOW(), estado = 'En tránsito' WHERE id_pedido = ?",
                     "ssi", $empresa, $codigo, $id_pedido);
            registrarHistorial($pe, 'Enviado', 'Despachado con ' . $empresa);
        } else {
            ejecutar("UPDATE Pedidos SET estado_pedido = 'Entregado' WHERE id_pedido = ?", "i", $id_pedido);
            ejecutar("UPDATE Envio SET fecha_entrega = NOW(), estado = 'Entregado' WHERE id_pedido = ?", "i", $id_pedido);
            if (($pe['estado_pago'] ?? '') === 'Pendiente') {   // efectivo: se cobra al entregar
                ejecutar("UPDATE Pago SET estado = 'Aprobado' WHERE id_pedido = ? AND estado = 'Pendiente'", "i", $id_pedido);
                registrarHistorial($pe, 'Pagado', 'Pago recibido contra entrega');
            }
            registrarHistorial($pe, 'Entregado', 'Entregado al cliente');
        }
        $conexion->commit();
    } catch (Throwable $t) {
        $conexion->rollback();
        throw new RuntimeException('No pudimos actualizar el pedido. Intenta de nuevo.');
    }
}

/* ---------- Productos del taller ---------- */
function obtenerProductosVendedor($idv) {
    return filas("SELECT id_producto, nombre, descripcion, precio, stock FROM Productos WHERE id_vendedor = ? ORDER BY id_producto DESC", "i", $idv);
}

function validarPrecioStock($precio, $stock) {
    $precio = str_replace(',', '.', trim((string)$precio));
    if (!is_numeric($precio) || (float)$precio <= 0 || (float)$precio > 99999999)
        throw new RuntimeException('Escribe un precio mayor que cero.');
    if (!ctype_digit(trim((string)$stock)))
        throw new RuntimeException('El stock debe ser un número entero, 0 o más.');
    return [round((float)$precio, 2), (int)$stock];
}

function crearProducto($idv, $nombre, $descripcion, $precio, $stock) {
    $nombre = trim($nombre);
    if ($nombre === '') throw new RuntimeException('Escribe el nombre del producto.');
    $nombre = function_exists('mb_substr') ? mb_substr($nombre, 0, 150) : substr($nombre, 0, 150);
    [$p, $s] = validarPrecioStock($precio, $stock);
    return ejecutar("INSERT INTO Productos (id_vendedor, nombre, descripcion, precio, stock) VALUES (?, ?, ?, ?, ?)",
                    "issdi", $idv, $nombre, trim($descripcion), $p, $s);
}

function actualizarProducto($idv, $id_producto, $precio, $stock) {
    [$p, $s] = validarPrecioStock($precio, $stock);
    if (!fila("SELECT id_producto FROM Productos WHERE id_producto = ? AND id_vendedor = ?", "ii", $id_producto, $idv))
        throw new RuntimeException('Ese producto no es de tu taller.');
    ejecutar("UPDATE Productos SET precio = ?, stock = ? WHERE id_producto = ?", "dii", $p, $s, $id_producto);
}
