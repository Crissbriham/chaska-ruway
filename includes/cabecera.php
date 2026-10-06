<?php
/* Cabecera común. Antes de incluirla, cada página define:
   $titulo_pagina (texto), $pagina (clave del menú) y, si quiere portada, $hero = true */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../modelo/carrito.php';

/* El menú depende de quién inició sesión: visitante, comprador o vendedor */
$idv = vendedorActual();
$idc = compradorActual();
$es_vendedor = (bool)$idv;
$num_carrito = $idc ? contarItemsCarrito($idc) : 0;
$aviso = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);
$titulo_pagina = $titulo_pagina ?? 'Chaska Ruway | Artesanía peruana';
$pagina = $pagina ?? '';
$hero = $hero ?? false;
if ($hero) {   // cifras reales para la portada
    $cifras_hero = fila("SELECT (SELECT COUNT(*) FROM Productos WHERE stock > 0 AND visible = 1) AS piezas,
                                (SELECT COUNT(DISTINCT id_vendedor) FROM Productos WHERE visible = 1) AS talleres,
                                (SELECT COUNT(DISTINCT v.departamento) FROM Vendedores v JOIN Productos p ON p.id_vendedor = v.id_vendedor WHERE p.visible = 1) AS regiones");
}
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
    <a class="marca" href="<?= $es_vendedor ? 'vendedor_panel.php' : 'index.php' ?>"><span class="marca__lucero" aria-hidden="true"></span>Chaska Ruway</a>
    <nav class="menu" aria-label="Principal">
      <?php if ($es_vendedor): ?>
        <a href="vendedor_panel.php" <?= $pagina === 'v_pedidos' ? 'aria-current="page"' : '' ?>>Pedidos</a>
        <a href="vendedor_productos.php" <?= in_array($pagina, ['v_productos', 'v_producto'], true) ? 'aria-current="page"' : '' ?>>Mis productos</a>
        <a href="index.php#catalogo" <?= $pagina === 'catalogo' ? 'aria-current="page"' : '' ?>>Ver la tienda</a>
        <span class="saludo"><?= e($_SESSION['taller']) ?></span>
        <a href="logout.php">Salir</a>
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
      <div class="hero__acciones">
        <a class="boton boton--claro" href="#catalogo">Ver el catálogo</a>
        <?php if (!$idc && !$idv): ?>
          <a class="boton boton--fantasma" href="registro.php?rol=vendedor">Vende tus piezas</a>
        <?php endif; ?>
      </div>
      <dl class="hero__cifras">
        <div><dt>piezas disponibles</dt><dd><?= (int)$cifras_hero['piezas'] ?></dd></div>
        <div><dt>talleres artesanos</dt><dd><?= (int)$cifras_hero['talleres'] ?></dd></div>
        <div><dt>regiones del Perú</dt><dd><?= (int)$cifras_hero['regiones'] ?></dd></div>
      </dl>
    </div>
    <div class="manta" aria-hidden="true">
      <span class="manta__flecos manta__flecos--arriba"></span>
      <div class="manta__lucero"><span></span></div>
      <span class="manta__flecos manta__flecos--abajo"></span>
    </div>
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