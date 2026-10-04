<?php
/* Recibe los formularios pequeños (favoritos, carrito, reseñas) y redirige de vuelta. */
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';
require_once 'modelo/carrito.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ir('index.php');

$accion = $_POST['accion'] ?? '';
$idc = compradorActual();

/* Guardar o quitar de favoritos */
if ($accion === 'favorito') {
    $volver = volverSeguro($_POST['volver'] ?? 'index.php');
    exigirSesion($volver, 'Inicia sesión para guardar favoritos.');
    alternarFavorito($idc, (int)($_POST['id_producto'] ?? 0));
    ir($volver);
}

/* Agregar al carrito (o comprar ahora) */
if ($accion === 'agregar') {
    $idp = (int)($_POST['id_producto'] ?? 0);
    $cant = max(1, (int)($_POST['cantidad'] ?? 1));
    $destino = 'producto.php?id=' . $idp;
    exigirSesion($destino, 'Inicia sesión para agregar productos a tu carrito.');

    $r = agregarAlCarrito($idc, $idp, $cant);
    if (!$r['ok']) {
        avisar($r['mensaje'], 'error');
        ir(isset($_POST['comprar_ya']) && ($r['ya'] ?? 0) > 0 ? 'pagar.php' : $destino);
    }
    if (isset($_POST['comprar_ya'])) ir('pagar.php');
    avisar($r['mensaje']);
    ir($destino);
}

/* Cambiar cantidad o quitar del carrito */
if ($accion === 'cantidad' || $accion === 'quitar') {
    exigirSesion('carrito.php');
    $cant = $accion === 'quitar' ? 0 : (int)($_POST['cantidad'] ?? 0);
    avisar(cambiarCantidad($idc, (int)($_POST['id_detalle'] ?? 0), $cant));
    ir('carrito.php');
}

/* Publicar reseña (solo si compró el producto) */
if ($accion === 'resena') {
    $idp = (int)($_POST['id_producto'] ?? 0);
    $destino = 'producto.php?id=' . $idp;
    exigirSesion($destino);
    $punt = (int)($_POST['puntuacion'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $titulo = function_exists('mb_substr') ? mb_substr($titulo, 0, 150) : substr($titulo, 0, 150);
    $comentario = trim($_POST['comentario'] ?? '');
    $pedido = pedidoDeCompra($idc, $idp);

    if ($punt < 1 || $punt > 5) avisar('Elige de 1 a 5 estrellas.', 'error');
    elseif (!$pedido) avisar('Solo puedes reseñar productos que hayas comprado.', 'error');
    elseif (yaReseno($idc, $idp)) avisar('Ya publicaste una reseña de este producto.', 'error');
    else {
        publicarResena($idp, $idc, $pedido, $punt, $titulo, $comentario);
        avisar('Tu reseña está publicada.');
    }
    ir($destino . '#resenas');
}

ir('index.php');
