<?php
$servidor = "68.155.144.232";
$usuario = "chaska_web";
$password = "Chaska2026!";
$base_datos = "tienda_artesanias";

$conexion = new mysqli($servidor, $usuario, $password, $base_datos);

if ($conexion->connect_error) {
    die("<h1 style='color:red;'>Error de conexión: " . $conexion->connect_error . "</h1>");
}
?>
