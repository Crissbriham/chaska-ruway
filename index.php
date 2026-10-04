<?php
require_once 'conexion.php';
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';

$productos = obtenerTodosLosProductos($conexion);
include 'includes/cabecera.php';
?>

<section class="hero" style="background: #1e1b4b; color: white; padding: 3rem 2rem; text-align: center; border-radius: 12px; margin-bottom: 2.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Catálogo de Artesanías Peruanas</h1>
    <p style="font-size: 1.1rem; color: #cbd5e1; margin: 0;">Descubre el arte tradicional directo de las manos de nuestros artesanos.</p>
</section>

<ul class="rejilla" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 2rem; list-style: none; padding: 0; margin: 0;">
    <?php if (!empty($productos)): ?>
        <?php foreach ($productos as $producto): ?>
            <li>
                <a href="producto.php?id=<?= $producto['id_producto']; ?>" class="tarjeta" style="display: block; background: white; border-radius: 8px; overflow: hidden; text-decoration: none; color: inherit; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s;">
                    <div class="foto" style="background: #f1f5f9; height: 160px; display: flex; align-items: center; justify-content: center; font-size: 3rem; position: relative;">
                        🎨
                        <span class="chip" style="position: absolute; top: 10px; left: 10px; background: #e0e7ff; color: #3730a3; padding: 0.2rem 0.6rem; font-size: 0.75rem; border-radius: 4px; font-weight: bold;"><?= htmlspecialchars($producto['departamento']); ?></span>
                    </div>
                    <div class="cuerpo" style="padding: 1.25rem;">
                        <h3 style="font-size: 1.1rem; margin: 0 0 0.5rem 0; color: #0f172a;"><?= htmlspecialchars($producto['nombre']); ?></h3>
                        <p class="autor" style="font-size: 0.85rem; color: #64748b; margin: 0 0 1rem 0;">Taller: <?= htmlspecialchars($producto['nombre_taller']); ?></p>
                        <p class="precio" style="font-size: 1.2rem; font-weight: bold; color: #2563eb; margin: 0;"><?= formatearPrecio($producto['precio']); ?></p>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="alerta" style="grid-column: 1 / -1; text-align: center; padding: 2rem; background: #f8fafc; border-radius: 8px;">No hay productos disponibles en este momento.</p>
    <?php endif; ?>
</ul>

<?php
include 'includes/pie.php';
?>