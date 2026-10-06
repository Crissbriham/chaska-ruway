<?php
/* Recibe los formularios del panel de vendedor y vuelve a la página de origen. */
require_once 'includes/helpers.php';
require_once 'modelo/vendedor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ir('vendedor_panel.php');
exigirVendedor();
$idv = vendedorActual();
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {   // el archivo superó el límite del servidor
    avisar('La foto es demasiado pesada para el servidor. Usa una de menos de 2 MB.', 'error');
    ir('vendedor_productos.php');
}
$accion = $_POST['accion'] ?? '';
$volver = volverSeguro($_POST['volver'] ?? 'vendedor_panel.php');

try {
    if ($accion === 'enviar' || $accion === 'entregar') {
        $id_pedido = (int)($_POST['id_pedido'] ?? 0);
        avanzarPedido($idv, $id_pedido, $accion, $_POST['empresa'] ?? '', $_POST['codigo'] ?? '');
        avisar($accion === 'enviar'
            ? 'El pedido #' . $id_pedido . ' quedó marcado como enviado.'
            : 'El pedido #' . $id_pedido . ' quedó marcado como entregado.');
    } elseif ($accion === 'producto_actualizar') {
        actualizarProducto($idv, (int)($_POST['id_producto'] ?? 0), $_POST['precio'] ?? '', $_POST['stock'] ?? '');
        avisar('Producto actualizado.');
    } elseif ($accion === 'producto_crear') {
        crearProducto($idv, $_POST['nombre'] ?? '', $_POST['descripcion'] ?? '', $_POST['precio'] ?? '', $_POST['stock'] ?? '');
        avisar('Producto agregado a tu catálogo.');
    } elseif ($accion === 'producto_guardar') {          // crear o editar con nombre, descripción, precio, stock y foto
        $id = (int)($_POST['id_producto'] ?? 0) ?: null;
        $volver = $id ? 'vendedor_producto.php?id=' . $id : 'vendedor_producto.php';
        $r = guardarProducto($idv, $id, $_POST['nombre'] ?? '', $_POST['descripcion'] ?? '',
                             $_POST['precio'] ?? '', $_POST['stock'] ?? '', $_FILES['foto'] ?? null, isset($_POST['quitar_foto']));
        if ($r['foto_error']) {
            avisar('Se guardó el producto, pero la foto no se subió: ' . $r['foto_error'], 'error');
            ir('vendedor_producto.php?id=' . $r['id']);
        }
        avisar($id ? 'Cambios guardados.' : 'Producto publicado en la tienda.');
        $volver = 'vendedor_productos.php';
    } elseif ($accion === 'producto_eliminar') {
        $nombre = eliminarProducto($idv, (int)($_POST['id_producto'] ?? 0));
        avisar('Eliminaste "' . $nombre . '" de tu catálogo.');
        $volver = 'vendedor_productos.php';
    } elseif ($accion === 'producto_agotar') {
        $nombre = dejarAgotado($idv, (int)($_POST['id_producto'] ?? 0));
        avisar('"' . $nombre . '" quedó agotado. Ya nadie puede comprarlo.');
    }
} catch (mysqli_sql_exception $ex) {      // va antes: mysqli_sql_exception también es RuntimeException
    avisar('No pudimos guardar los cambios. Intenta de nuevo en un momento.', 'error');
} catch (RuntimeException $ex) {
    if ($accion === 'producto_guardar') {   // no perder lo que escribió
        $_SESSION['form'] = ['nombre' => $_POST['nombre'] ?? '', 'descripcion' => $_POST['descripcion'] ?? '',
                             'precio' => $_POST['precio'] ?? '', 'stock' => $_POST['stock'] ?? ''];
    }
    avisar($ex->getMessage(), 'error');
}
ir($volver);