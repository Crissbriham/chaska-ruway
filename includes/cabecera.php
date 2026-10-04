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
        <div class="barra" style="max-width: 1200px; margin: 0 auto; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
            <div class="marca">
                <a href="index.php" style="color: white; text-decoration: none; font-size: 1.5rem; font-weight: bold;">Chaska Ruway 🎨</a>
                <br><small style="color: #cbd5e1;">Arte y Tradición Peruana</small>
            </div>
            <nav>
                <ul class="menu-horizontal" style="list-style: none; display: flex; gap: 1.5rem; margin: 0; padding: 0; align-items: center;">
                    <li><a href="index.php" style="color: white; text-decoration: none;">Catálogo</a></li>
                    <li><a href="carrito.php" style="color: white; text-decoration: none;">🛒 Carrito</a></li>
                    <li><a href="pedidos.php" style="color: white; text-decoration: none;">Mis Pedidos</a></li>
                    <?php if (isset($_SESSION['usuario'])): ?>
                        <li><a href="vendedor/panel.php" style="color: white; text-decoration: none;">Panel Vendedor</a></li>
                        <li><a href="logout.php" style="color: #f87171; text-decoration: none;">Cerrar Sesión</a></li>
                    <?php else: ?>
                        <li><a href="login.php" style="color: white; text-decoration: none;">Iniciar Sesión</a></li>
                        <li><a href="registro.php" style="color: white; text-decoration: none; background: #e0e7ff; color: #1e1b4b; padding: 0.5rem 1rem; border-radius: 4px;">Registrarse</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main style="max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem;">