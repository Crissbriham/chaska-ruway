<?php
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';

$q = trim($_GET['q'] ?? '');
$regiones = obtenerRegiones();
$region = in_array($_GET['region'] ?? '', $regiones, true) ? $_GET['region'] : '';
$productos = obtenerCatalogo($q, 'valorados', false, $region);   // siempre ordenado por mejor valorados
$unidades = array_sum(array_map(fn($x) => max(0, (int)$x['stock']), $productos));
$con_stock = count(array_filter($productos, fn($x) => (int)$x['stock'] > 0));
$favs = obtenerIdsFavoritos(compradorActual());

$titulo_pagina = 'Chaska Ruway | Artesanía peruana';
$pagina = 'catalogo';
$hero = ($q === '' && $region === '');
$nosotros = true;
include 'includes/cabecera.php';
?>
  <section id="catalogo" class="seccion">
    <h2 class="titulo-tejido">Catálogo</h2>

    <form class="buscador" method="get" action="index.php#catalogo" role="search">
      <div class="buscador__caja">
        <label class="sr" for="q">Buscar productos</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Busca por producto, taller o región...">
        <button class="buscador__lupa" type="submit" aria-label="Buscar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>
        </button>
      </div>
      <?php if ($region !== ''): ?><input type="hidden" name="region" value="<?= e($region) ?>"><?php endif; ?>
    </form>

    <?php if ($regiones): ?>
    <nav class="regiones" aria-label="Filtrar por región">
      <a class="chip" href="index.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>#catalogo" <?= $region === '' ? 'aria-current="true"' : '' ?>>Todas las regiones</a>
      <?php foreach ($regiones as $r): ?>
        <a class="chip" href="index.php?<?= e(http_build_query(array_filter(['q' => $q, 'region' => $r]))) ?>#catalogo" <?= $region === $r ? 'aria-current="true"' : '' ?>><?= e($r) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($q !== ''): ?>
      <p class="resultado"><?= count($productos) ?> <?= count($productos) == 1 ? 'resultado' : 'resultados' ?> para “<?= e($q) ?>”. <a href="index.php#catalogo">Ver todo</a></p>
    <?php endif; ?>

    <?php if ($productos): ?>
      <p class="resultado"><?= $con_stock ?> de <?= count($productos) ?> productos disponibles<?= $region !== '' ? ' en ' . e($region) : '' ?>, <?= $unidades ?> unidades en total.</p>
      <div class="rejilla">
        <?php foreach ($productos as $p) echo tarjeta($p, $favs, paginaActual()); ?>
      </div>
    <?php else: ?>
      <div class="vacio">
        <h3>No hay productos que coincidan</h3>
        <p>Prueba con otra palabra, como “cerámica” o el nombre de una región.</p>
        <a class="boton boton--borde" href="index.php#catalogo">Ver todo el catálogo</a>
      </div>
    <?php endif; ?>
  </section>
<?php include 'includes/pie.php'; ?>