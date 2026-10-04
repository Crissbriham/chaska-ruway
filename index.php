<?php
require_once 'conexion.php';
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';

$productos = obtenerTodosLosProductos($conexion);
include 'includes/cabecera.php';
?>

<section class="hero" style="margin: -2rem -1.25rem 2rem; border-radius: 0;">
    <h1>Catálogo de Artesanías Peruanas</h1>
    <p>Descubre el arte tradicional directo de las manos de nuestros artesanos.</p>
</section>

<ul class="rejilla">
    <?php if (!empty($productos)): ?>
        <?php foreach ($productos as $producto): ?>
            <li>
                <a href="producto.php?id=<?= $producto['id_producto']; ?>" class="tarjeta">
                    <div class="foto">
                        🎨
                        <span class="chip"><?= htmlspecialchars($producto['departamento']); ?></span>
                    </div>
                    <div class="cuerpo">
                        <h3><?= htmlspecialchars($producto['nombre']); ?></h3>
                        <p class="autor">Taller: <?= htmlspecialchars($producto['nombre_taller']); ?></p>
                        <p class="precio"><?= formatearPrecio($producto['precio']); ?></p>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="alerta">No hay productos disponibles en este momento.</p>
    <?php endif; ?>
</ul>

<?php
include 'includes/pie.php';
?>