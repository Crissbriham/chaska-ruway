<?php
require_once 'includes/helpers.php';
require_once 'modelo/vendedor.php';

exigirVendedor();
$productos = obtenerProductosVendedor(vendedorActual());
$cifras = resumenProductosVendedor(vendedorActual());

$titulo_pagina = 'Mis productos | Chaska Ruway';
$pagina = 'v_productos';
$es_vendedor = true;
include 'includes/cabecera.php';
?>
<div class="encabezado-panel">
    <h1 class="pagina-titulo">Productos de <?= e($_SESSION['taller']) ?></h1>
    <a class="boton" href="vendedor_producto.php">Agregar producto</a>
</div>
<dl class="cifras">
    <div><dt>Productos</dt><dd><?= $cifras['productos'] ?></dd></div>
    <div><dt>Unidades disponibles</dt><dd><?= $cifras['unidades'] ?></dd></div>
    <div><dt>Con pocas unidades</dt><dd class="<?= $cifras['pocos'] ? 'alerta' : '' ?>"><?= $cifras['pocos'] ?></dd></div>
    <div><dt>Agotados</dt><dd class="<?= $cifras['agotados'] ? 'alerta' : '' ?>"><?= $cifras['agotados'] ?></dd></div>
</dl>

<?php if (!$productos): ?>
    <div class="vacio"><h3>Aún no tienes productos</h3><p>Agrega el primero con el formulario de abajo.</p></div>
<?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla">
            <thead><tr><th>Producto</th><th>Precio (S/)</th><th>Stock</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($productos as $p): $f = 'f' . (int)$p['id_producto']; ?>
                <tr>
                    <td>
                        <div class="celda-producto">
                            <?= portada($p, 'portada--mini') ?>
                            <div>
                                <a href="vendedor_producto.php?id=<?= (int)$p['id_producto'] ?>"><?= e($p['nombre']) ?></a>
                                <?= textoStock($p['stock']) ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <label class="sr" for="precio<?= (int)$p['id_producto'] ?>">Precio de <?= e($p['nombre']) ?></label>
                        <input form="<?= $f ?>" type="text" inputmode="decimal" id="precio<?= (int)$p['id_producto'] ?>" name="precio" value="<?= number_format((float)$p['precio'], 2, '.', '') ?>">
                    </td>
                    <td>
                        <label class="sr" for="stock<?= (int)$p['id_producto'] ?>">Stock de <?= e($p['nombre']) ?></label>
                        <input form="<?= $f ?>" type="number" min="0" id="stock<?= (int)$p['id_producto'] ?>" name="stock" value="<?= (int)$p['stock'] ?>">
                    </td>
                    <td>
                        <form id="<?= $f ?>" method="post" action="vendedor_accion.php">
                            <input type="hidden" name="accion" value="producto_actualizar">
                            <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
                            <input type="hidden" name="volver" value="vendedor_productos.php">
                            <button class="boton boton--borde" type="submit" style="padding:.5rem .9rem">Guardar</button>
                        </form>
                        <a class="boton boton--borde boton--chico" href="vendedor_producto.php?id=<?= (int)$p['id_producto'] ?>">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<section class="seccion">
    <h2>Agregar un producto</h2>
    <form method="post" action="vendedor_accion.php" class="caja" style="max-width:640px">
        <input type="hidden" name="accion" value="producto_crear">
        <input type="hidden" name="volver" value="vendedor_productos.php">
        <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" maxlength="150" required></div>
        <div class="campo"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" placeholder="Material, técnica, lugar donde se hizo..."></textarea></div>
        <div class="fila-2">
            <div class="campo"><label for="precio">Precio (S/)</label><input type="text" inputmode="decimal" id="precio" name="precio" placeholder="45.00" required></div>
            <div class="campo"><label for="stock">Stock</label><input type="number" min="0" id="stock" name="stock" value="1" required></div>
        </div>
        <button class="boton" type="submit">Agregar producto</button>
        <p class="ayuda">La foto del producto todavía no se sube desde aquí; mientras tanto se muestra la portada de color.</p>
    </form>
</section>
<?php include 'includes/pie.php'; ?>
