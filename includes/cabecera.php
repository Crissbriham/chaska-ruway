<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chaska Ruway - Tienda de Artesanías</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <div class="tejido"></div>
    <header>
        <div class="barra">
            <div class="marca">
                <a href="index.php" style="color: white; text-decoration: none;">Chaska Ruway 🎨</a>
                <small>Arte y Tradición Peruana</small>
            </div>
            <nav>
                <ul class="menu-horizontal" style="list-style: none; margin: 0; padding: 0;">
                    <li><a href="index.php">Catálogo</a></li>
                    <li><a href="carrito.php">🛒 Carrito</a></li>
                    <li><a href="pedidos.php">Mis Pedidos</a></li>
                    <?php if (isset($_SESSION['usuario'])): ?>
                        <li><a href="vendedor/panel.php">Panel Vendedor</a></li>
                        <li><a href="logout.php">Cerrar Sesión</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Iniciar Sesión</a></li>
                        <li><a href="registro.php">Registrarse</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main>