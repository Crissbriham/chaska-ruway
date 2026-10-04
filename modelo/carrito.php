<?php
// modelo/carrito.php
// Lógica y consultas SQL para el carrito de compras

require_once __DIR__ . '/../conexion.php';

/**
 * Obtiene o crea el ID del carrito activo para un comprador.
 */
function obtenerOCrearCarrito($conexion, $id_comprador) {
    // Buscar carrito activo
    $sql = "SELECT id_carrito FROM Carrito WHERE id_comprador = ? AND estado = 'Activo' LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_comprador);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($fila = $resultado->fetch_assoc()) {
        return $fila['id_carrito'];
    }
    
    // Si no tiene carrito activo, creamos uno nuevo
    $sqlInsert = "INSERT INTO Carrito (id_comprador, estado) VALUES (?, 'Activo')";
    $stmtInsert = $conexion->prepare($sqlInsert);
    $stmtInsert->bind_param("i", $id_comprador);
    $stmtInsert->execute();
    
    return $conexion->insert_id;
}

/**
 * Obtiene los ítems del carrito de un comprador con los detalles del producto.
 */
function obtenerItemsCarrito($conexion, $id_carrito) {
    $sql = "SELECT cd.*, p.nombre, p.stock, v.nombre_taller 
            FROM Carrito_Detalle cd
            JOIN Productos p ON cd.id_producto = p.id_producto
            JOIN Vendedores v ON p.id_vendedor = v.id_vendedor
            WHERE cd.id_carrito = ?";
            
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_carrito);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Agrega o actualiza un producto en el carrito.
 */
function agregarAlCarrito($conexion, $id_comprador, $id_producto, $cantidad) {
    $id_carrito = obtenerOCrearCarrito($conexion, $id_comprador);
    
    // Verificar si el producto ya está en el carrito
    $sqlCheck = "SELECT cantidad, precio_unitario FROM Carrito_Detalle WHERE id_carrito = ? AND id_producto = ?";
    $stmtCheck = $conexion->prepare($sqlCheck);
    $stmtCheck->bind_param("ii", $id_carrito, $id_producto);
    $stmtCheck->execute();
    $res = $stmtCheck->get_result();
    
    // Obtener el precio actual del producto
    $sqlPrecio = "SELECT precio FROM Productos WHERE id_producto = ?";
    $stmtPrecio = $conexion->prepare($sqlPrecio);
    $stmtPrecio->bind_param("i", $id_producto);
    $stmtPrecio->execute();
    $precioProd = $stmtPrecio->get_result()->fetch_assoc()['precio'];
    
    if ($row = $res->fetch_assoc()) {
        // Si ya existe, actualizamos la cantidad
        $nuevaCantidad = $row['cantidad'] + $cantidad;
        $sqlUpdate = "UPDATE Carrito_Detalle SET cantidad = ? WHERE id_carrito = ? AND id_producto = ?";
        $stmtUp = $conexion->prepare($sqlUpdate);
        $stmtUp->bind_param("iii", $nuevaCantidad, $id_carrito, $id_producto);
        $stmtUp->execute();
    } else {
        // Si no existe, lo insertamos nuevo
        $sqlIns = "INSERT INTO Carrito_Detalle (id_carrito, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
        $stmtIns = $conexion->prepare($sqlIns);
        $stmtIns->bind_param("iiid", $id_carrito, $id_producto, $cantidad, $precioProd);
        $stmtIns->execute();
    }
}