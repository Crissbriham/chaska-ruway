<?php
/* Cabecera común. Antes de incluirla, cada página define:
   $titulo_pagina (texto), $pagina (clave del menú) y, si quiere portada, $hero = true */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../modelo/carrito.php';

$es_vendedor = $es_vendedor ?? false;   // las páginas del panel lo ponen en true
$idc = compradorActual();
$num_carrito = (!$es_vendedor && $idc) ? contarItemsCarrito($idc) : 0;
$aviso = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);
$titulo_pagina = $titulo_pagina ?? 'Chaska Ruway | Artesanía peruana';
$pagina = $pagina ?? '';
$hero = $hero ?? false;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo_pagina) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Alegreya:ital,wght@0,500;0,700;0,800;1,500&family=Alegreya+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="estilos.css">
<?php if ($es_vendedor): ?>
  <link rel="stylesheet" href="estilos_vendedor.css">
<?php endif; ?>
</head>
<body>

<header class="cabecera">
  <div class="envoltura">
    <a class="marca" href="<?= ($es_vendedor && !empty($_SESSION['id_vendedor'])) ? 'vendedor_panel.php' : 'index.php' ?>"><span class="marca__lucero" aria-hidden="true"></span>Chaska Ruway</a>
    <nav class="menu" aria-label="Principal">
      <?php if ($es_vendedor): ?>
        <?php if (!empty($_SESSION['id_vendedor'])): ?>
          <a href="vendedor_panel.php" <?= $pagina === 'v_pedidos' ? 'aria-current="page"' : '' ?>>Pedidos</a>
          <a href="vendedor_productos.php" <?= $pagina === 'v_productos' ? 'aria-current="page"' : '' ?>>Mis productos</a>
          <a href="index.php">Ver la tienda</a>
          <span class="saludo"><?= e($_SESSION['taller']) ?></span>
          <a href="vendedor_logout.php">Salir</a>
        <?php else: ?>
          <a href="index.php#catalogo">Ir a la tienda</a>
        <?php endif; ?>
      <?php else: ?>
      <a href="index.php#catalogo" <?= $pagina === 'catalogo' ? 'aria-current="page"' : '' ?>>Catálogo</a>
      <?php if ($idc): ?>
        <a href="favoritos.php" <?= $pagina === 'favoritos' ? 'aria-current="page"' : '' ?>>Favoritos</a>
        <a href="pedidos.php" <?= $pagina === 'pedidos' ? 'aria-current="page"' : '' ?>>Mis pedidos</a>
        <a class="carrito-enlace" href="carrito.php" <?= in_array($pagina, ['carrito', 'pagar'], true) ? 'aria-current="page"' : '' ?>>
          Carrito <span class="contador"><?= $num_carrito ?><span class="sr"> productos</span></span>
        </a>
        <span class="saludo">Hola, <?= e($_SESSION['nombre']) ?></span>
        <a href="logout.php">Salir</a>
      <?php else: ?>
        <a href="vendedor_login.php">Soy vendedor</a>
        <a href="login.php" <?= $pagina === 'login' ? 'aria-current="page"' : '' ?>>Iniciar sesión</a>
        <a class="boton" href="registro.php">Crear cuenta</a>
      <?php endif; ?>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($hero): ?>
<section class="hero">
  <div class="envoltura">
    <div>
      <h1>Chaska Ruway</h1>
      <p class="hero__lema">Artesanía peruana, directo de quien la hace.</p>
      <p class="hero__quechua">En quechua, chaska es el lucero y ruway es hacer.</p>
      <a class="boton boton--claro" href="#catalogo">Ver el catálogo</a>
    </div>
    <div class="lucero" aria-hidden="true"><span></span></div>
  </div>
</section>
<div class="faja" aria-hidden="true"></div>
<?php else: ?>
<div class="faja faja--fina" aria-hidden="true"></div>
<?php endif; ?>

<main class="envoltura">
  <?php if ($aviso): ?>
    <div class="aviso<?= $aviso['tipo'] === 'error' ? ' aviso--error' : '' ?>" role="<?= $aviso['tipo'] === 'error' ? 'alert' : 'status' ?>"><?= e($aviso['texto']) ?></div>
  <?php endif; ?>