<?php
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';

exigirSesion('favoritos.php', 'Inicia sesión para ver tus favoritos.');
$idu = compradorActual();
$favoritos = obtenerFavoritos($idu);
$favs = obtenerIdsFavoritos($idu);

$titulo_pagina = 'Favoritos | Chaska Ruway';
$pagina = 'favoritos';
include 'includes/cabecera.php';
?>
  <h1 class="pagina-titulo">Favoritos</h1>
  <?php if (!$favoritos): ?>
    <div class="vacio">
      <h3>Aún no guardas favoritos</h3>
      <p>Toca el corazón de cualquier producto para guardarlo aquí y encontrarlo después.</p>
      <a class="boton" href="index.php#catalogo">Ver el catálogo</a>
    </div>
  <?php else: ?>
    <div class="rejilla" style="margin-bottom:1rem">
      <?php foreach ($favoritos as $p) echo tarjeta($p, $favs, paginaActual()); ?>
    </div>
  <?php endif; ?>
<?php include 'includes/pie.php'; ?>
