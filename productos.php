<?php
require_once __DIR__ . '/../conexion.php';

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