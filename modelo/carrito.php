<?php
require_once 'includes/helpers.php';
require_once 'modelo/carrito.php';

exigirSesion('carrito.php', 'Inicia sesión para ver tu carrito.');
$idu = compradorActual();
$items = obtenerItemsCarrito($idu);
$total = 0;
$hay_problema = false;
foreach ($items as $it) {
    $total += $it['precio'] * $it['cantidad'];
    if ($it['cantidad'] > $it['stock']) $hay_problema = true;
}

$titulo_pagina = 'Tu carrito | Chaska Ruway';
$pagina = 'carrito';
include 'includes/cabecera.php';
?>
<h1 class="pagina-titulo">Tu carrito</h1>
<?php if (!$items): ?>
    <div class="vacio">
        <h3>Tu carrito está vacío</h3>
        <p>Explora el catálogo y agrega las piezas que te gusten.</p>
        <a class="boton" href="index.php#catalogo">Ver el catálogo</a>
    </div>
<?php else: ?>
    <div class="dos-columnas">
        <ul class="lista-carrito">
            <?php foreach ($items as $it): ?>
                <li class="item">
                    <?= portada($it, 'portada--mini') ?>
                    <div class="item__info">
                        <a href="producto.php?id=<?= (int)$it['id_producto'] ?>"><?= e($it['nombre']) ?></a>
                        <p><?= soles($it['precio']) ?> c/u, <?= e($it['nombre_taller']) ?>. Disponibles: <?= max(0, (int)$it['stock']) ?></p>
                        <?php if ($it['cantidad'] > $it['stock']): ?>
                            <p class="alerta-stock"><?= $it['stock'] > 0 ? 'Solo quedan ' . (int)$it['stock'] . '. Reduce la cantidad.' : 'Se agotó. Quítalo para continuar.' ?></p>
                        <?php endif; ?>
                        <form method="post" action="accion.php">
                            <input type="hidden" name="accion" value="quitar">
                            <input type="hidden" name="id_detalle" value="<?= (int)$it['id_detalle'] ?>">
                            <button class="enlace-boton" type="submit">Quitar</button>
                        </form>
                    </div>
                    <form method="post" action="accion.php" class="item__cantidad">
                        <input type="hidden" name="accion" value="cantidad">
                        <input type="hidden" name="id_detalle" value="<?= (int)$it['id_detalle'] ?>">
                        <label class="sr" for="cant<?= (int)$it['id_detalle'] ?>">Cantidad de <?= e($it['nombre']) ?></label>
                        <input type="number" id="cant<?= (int)$it['id_detalle'] ?>" name="cantidad" value="<?= (int)$it['cantidad'] ?>" min="0" max="<?= max(1, (int)$it['stock']) ?>">
                        <button class="boton boton--borde" type="submit" style="padding:.55rem .8rem">Actualizar</button>
                    </form>
                    <span class="item__subtotal"><?= soles($it['precio'] * $it['cantidad']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <aside class="caja resumen">
            <h2>Resumen</h2>
            <dl>
                <dt>Productos (<?= $num_carrito ?>)</dt><dd><?= soles($total) ?></dd>
                <dt>Envío</dt><dd>Gratis</dd>
                <dt class="total">Total</dt><dd class="total"><?= soles($total) ?></dd>
            </dl>
            <?php if ($hay_problema): ?>
                <button class="boton boton--ancho" disabled>Continuar con el pago</button>
                <p class="nota-demo">Ajusta las cantidades marcadas para continuar.</p>
            <?php else: ?>
                <a class="boton boton--ancho" href="pagar.php">Continuar con el pago</a>
            <?php endif; ?>
            <p class="nota-demo"><a href="index.php#catalogo">Seguir comprando</a></p>
        </aside>
    </div>
<?php endif; ?>
<?php include 'includes/pie.php'; ?>
