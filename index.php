<?php
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';

$q = trim($_GET['q'] ?? '');
$orden = 'valorados';   // siempre se muestran primero los mejor valorados
$regiones = obtenerRegiones();
$region = in_array($_GET['region'] ?? '', $regiones, true) ? $_GET['region'] : '';

$productos = obtenerCatalogo($q, $orden, false, $region);
$favs = obtenerIdsFavoritos(compradorActual());

/* Enlace de una región conservando la búsqueda y el orden */
function enlaceRegion($reg, $q, $orden) {
    $par = array_filter(['q' => $q, 'orden' => $orden !== 'valorados' ? $orden : '', 'region' => $reg], fn($v) => $v !== '');
    return 'index.php' . ($par ? '?' . http_build_query($par) : '') . '#catalogo';
}

$titulo_pagina = 'Chaska Ruway | Artesanía peruana';
$pagina = 'catalogo';
$hero = ($q === '' && $region === '');
$nosotros = true;
include 'includes/cabecera.php';
?>
  <section id="catalogo" class="seccion">
    <h2>Catálogo</h2>

    <form class="buscador" method="get" action="index.php#catalogo" role="search">
      <div class="buscador__caja">
        <label class="sr" for="q">Buscar productos</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Busca por producto, taller o región...">
        <button class="buscador__lupa" type="submit" aria-label="Buscar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>
        </button>
      <?php if ($region !== ''): ?><input type="hidden" name="region" value="<?= e($region) ?>"><?php endif; ?>
    </form>

    <?php if ($regiones): ?>
      <nav class="regiones" aria-label="Filtrar por región">
        <a href="<?= e(enlaceRegion('', $q, $orden)) ?>" <?= $region === '' ? 'aria-current="true"' : '' ?>>Todas</a>
        <?php foreach ($regiones as $r): ?>
          <a href="<?= e(enlaceRegion($r, $q, $orden)) ?>" <?= $region === $r ? 'aria-current="true"' : '' ?>><?= e($r) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if ($q !== '' || $region !== ''): ?>
      <p class="resultado"><?= count($productos) ?> <?= count($productos) == 1 ? 'resultado' : 'resultados' ?><?= $q !== '' ? ' para “' . e($q) . '”' : '' ?><?= $region !== '' ? ' en ' . e($region) : '' ?>. <a href="index.php#catalogo">Ver todo</a></p>
    <?php endif; ?>

    <?php if ($productos): ?>
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