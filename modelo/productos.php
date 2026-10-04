<?php
// modelo/productos.php
// Este archivo contiene exclusivamente funciones de consulta SQL relacionadas con los productos.
// NO debe incluir etiquetas HTML ni diseño visual.

require_once __DIR__ . '/../conexion.php';

/**
 * Obtiene todos los productos disponibles en la tienda junto con el nombre de su taller/vendedor.
 * 
 * @param mysqli $conexion Objeto de conexión a la base de datos
 * @return array Lista asociativa de productos
 */
function obtenerTodosLosProductos($conexion) {
    $sql = "SELECT p.*, v.nombre_taller, v.departamento 
            FROM Productos p 
            JOIN Vendedores v ON p.id_vendedor = v.id_vendedor 
            ORDER BY p.id_producto DESC";
    
    $resultado = $conexion->query($sql);
    
    if (!$resultado) {
        return [];
    }
    
    return $resultado->fetch_all(MYSQLI_ASSOC);
}

/**
 * Obtiene los detalles de un producto específico por su ID.
 * 
 * @param mysqli $conexion Objeto de conexión
 * @param int $id_producto ID del producto
 * @return array|null Datos del producto o null si no existe
 */
function obtenerProductoPorId($conexion, $id_producto) {
    $sql = "SELECT p.*, v.nombre_taller, v.departamento, v.nombres AS vendedor_nombre, v.apellidos AS vendedor_apellidos 
            FROM Productos p 
            JOIN Vendedores v ON p.id_vendedor = v.id_vendedor 
            WHERE p.id_producto = ?";
            
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        return null;
    }
    
    return $resultado->fetch_assoc();
}