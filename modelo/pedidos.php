<?php
// modelo/pedidos.php
// Lógica para procesar la compra mediante procedimientos almacenados

require_once __DIR__ . '/../conexion.php';

/**
 * Ejecuta el procedimiento almacenado para procesar la compra del carrito actual.
 */
function procesarCompra($conexion, $id_carrito, $id_comprador, $metodo_pago) {
    // Llamada al procedimiento almacenado que ya tienes creado en tu BD
    $sql = "CALL ProcesarCompraCarrito(?, ?, ?)";
    
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        return ["exito" => false, "mensaje" => "Error al preparar la consulta: " . $conexion->error];
    }
    
    $stmt->bind_param("iis", $id_carrito, $id_comprador, $metodo_pago);
    
    try {
        if ($stmt->execute()) {
            return ["exito" => true, "mensaje" => "¡Compra procesada con éxito! Gracias por apoyar la artesanía peruana."];
        } else {
            return ["exito" => false, "mensaje" => "No se pudo procesar la compra: " . $stmt->error];
        }
    } catch (Exception $e) {
        return ["exito" => false, "mensaje" => "Excepción: " . $e->getMessage()];
    }
}