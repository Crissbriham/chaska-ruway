<?php
/* Crear un producto nuevo (sin id) o editar uno del taller (con ?id=) */
require_once 'includes/helpers.php';
require_once 'modelo/vendedor.php';

exigirVendedor();
$idv = vendedorActual();
$id = (int)($_GET['id'] ?? 0);
$p = null;
if ($id) {
    $p = obtenerProductoDelVendedor($idv, $id);
    if (!$p) { avisar('Ese producto no es de tu taller.', 'error'); ir('vendedor_productos.php'); }
}
$viejo = $_SESSION['form'] ?? [];
unset($_SESSION['form']);
$v = fn($campo, $def = '') => $viejo[$campo] ?? ($p[$campo] ?? $def);
$tiene_foto = $p && $p['imagen'];

$titulo_pagina = ($p ? 'Editar ' . $p['nombre'] : 'Agregar producto') . ' | Chaska Ruway';
$pagina = 'v_producto';
include 'includes/cabecera.php';
?>
  <p class="migas"><a href="vendedor_productos.php">Mis productos</a> / <?= $p ? e($p['nombre']) : 'Agregar producto' ?></p>
  <h1 class="pagina-titulo"><?= $p ? 'Editar producto' : 'Agregar producto' ?></h1>

  <form method="post" action="vendedor_accion.php" enctype="multipart/form-data" class="form-producto">
    <input type="hidden" name="accion" value="producto_guardar">
    <input type="hidden" name="id_producto" value="<?= $p ? $id : '' ?>">

    <div class="form-producto__foto">
      <?= portada($p ?: ['id_producto' => 0, 'nombre' => $v('nombre', 'Nuevo producto')], 'portada--grande') ?>
      <div class="campo">
        <label for="foto"><?= $tiene_foto ? 'Cambiar foto' : 'Foto del producto' ?></label>
        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
        <p class="ayuda">JPG, PNG o WEBP de hasta 2 MB. Mejor si es cuadrada y con buena luz.</p>
      </div>
      <?php if ($tiene_foto): ?>
        <label class="check"><input type="checkbox" name="quitar_foto" value="1"> Quitar la foto actual</label>
      <?php endif; ?>
    </div>

    <div class="caja">
      <div class="campo">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="150" value="<?= e($v('nombre')) ?>" required>
      </div>
      <div class="campo">
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" maxlength="2000" rows="6" placeholder="Material, técnica, medidas, lugar donde se hizo..."><?= e($v('descripcion')) ?></textarea>
      </div>
      <div class="fila-2">
        <div class="campo">
          <label for="precio">Precio (S/)</label>
          <input type="text" inputmode="decimal" id="precio" name="precio" placeholder="45.00" required
                 value="<?= e($viejo['precio'] ?? ($p ? number_format((float)$p['precio'], 2, '.', '') : '')) ?>">
        </div>
        <div class="campo">
          <label for="stock">Unidades disponibles</label>
          <input type="number" min="0" id="stock" name="stock" required value="<?= e($v('stock', '1')) ?>">
          <p class="ayuda">Con 0 se muestra como agotado.</p>
        </div>
      </div>
      <div class="botones-form">
        <button class="boton" type="submit"><?= $p ? 'Guardar cambios' : 'Publicar producto' ?></button>
        <?php if ($p): ?><a class="boton boton--borde" href="producto.php?id=<?= $id ?>">Ver en la tienda</a><?php endif; ?>
        <a class="enlace-boton" href="vendedor_productos.php">Cancelar</a>
      </div>
    </div>
  </form>

  <?php if ($p): ?>
  <section class="zona-visible">
    <h2><?= $p['visible'] ? 'Visible en la tienda' : 'Oculto de la tienda' ?></h2>
    <p><?= $p['visible']
        ? 'Los compradores pueden ver y comprar este producto. Si lo ocultas, desaparece del catálogo, la búsqueda y los favoritos, pero se conserva con su historial.'
        : 'Los compradores no lo ven ni pueden comprarlo. Tú sí lo sigues viendo aquí y en "Mis productos".' ?></p>
    <form method="post" action="vendedor_accion.php">
      <input type="hidden" name="accion" value="producto_visibilidad">
      <input type="hidden" name="id_producto" value="<?= $id ?>">
      <input type="hidden" name="visible" value="<?= $p['visible'] ? '0' : '1' ?>">
      <input type="hidden" name="volver" value="vendedor_producto.php?id=<?= $id ?>">
      <button class="boton <?= $p['visible'] ? 'boton--borde' : '' ?>" type="submit"><?= $p['visible'] ? 'Quitar de la tienda' : 'Mostrar en la tienda' ?></button>
    </form>
  </section>

  <?php if (!productoTieneVentas($id)): ?>
  <section class="zona-eliminar">
    <h2>Eliminar producto</h2>
    <p>Se borrará para siempre junto con su foto. Si solo quieres dejar de venderlo por un tiempo, mejor quítalo de la tienda.</p>
    <form method="post" action="vendedor_accion.php" onsubmit="return confirm('¿Eliminar este producto? No se puede deshacer.');">
      <input type="hidden" name="accion" value="producto_eliminar">
      <input type="hidden" name="id_producto" value="<?= $id ?>">
      <button class="boton boton--peligro" type="submit">Eliminar producto</button>
    </form>
  </section>
  <?php endif; ?>
  <?php endif; ?>
<?php include 'includes/pie.php'; ?>