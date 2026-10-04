<?php
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';

$q = trim($_GET['q'] ?? '');
$orden = isset(ORDENES_CATALOGO[$_GET['orden'] ?? '']) ? $_GET['orden'] : 'valorados';
$solo_disponibles = isset($_GET['disponibles']);

$productos = obtenerCatalogo($q, $orden, $solo_disponibles);
$favs = obtenerIdsFavoritos(compradorActual());

$titulo_pagina = 'Chaska Ruway | Artesanía peruana';
$pagina = 'catalogo';
$hero = ($q === '');
$nosotros = true;
include 'includes/cabecera.php';
?>
  <section id="catalogo" class="seccion">
    <h2>Catálogo</h2>
    <form class="barra-catalogo" method="get" action="index.php#catalogo" role="search">
      <div class="campo">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Retablo, alpaca, Chulucanas...">
      </div>
      <div class="campo campo--orden">
        <label for="orden">Ordenar por</label>
        <select id="orden" name="orden">
          <?php foreach (['valorados' => 'Mejor valorados', 'recientes' => 'Más nuevos', 'precio_asc' => 'Precio: menor a mayor', 'precio_desc' => 'Precio: mayor a menor', 'nombre' => 'Nombre (A-Z)'] as $k => $t): ?>
            <option value="<?= $k ?>" <?= $orden === $k ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label class="check"><input type="checkbox" name="disponibles" value="1" <?= $solo_disponibles ? 'checked' : '' ?>> Solo disponibles</label>
      <button class="boton" type="submit">Buscar</button>
    </form>

    <?php if ($q !== ''): ?>
      <p class="resultado"><?= count($productos) ?> <?= count($productos) == 1 ? 'resultado' : 'resultados' ?> para “<?= e($q) ?>”. <a href="index.php#catalogo">Ver todo</a></p>
    <?php endif; ?>

    <?php if ($productos): ?>
      <div class="rejilla">
        <?php foreach ($productos as $p) echo tarjeta($p, $favs, paginaActual()); ?>
      </div>
    <?php else: ?>
      <div class="vacio">
        <h3>No hay productos que coincidan</h3>
        <p>Prueba con otra palabra, como “cerámica” o el nombre de una región, o quita el filtro de disponibles.</p>
        <a class="boton boton--borde" href="index.php#catalogo">Ver todo el catálogo</a>
      </div>
    <?php endif; ?>
  </section>
<?php include 'includes/pie.php'; ?>