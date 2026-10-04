<?php
/* Recibe los formularios del panel de vendedor y vuelve a la página de origen. */
require_once 'includes/helpers.php';
require_once 'modelo/vendedor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ir('vendedor_panel.php');
exigirVendedor();
$idv = vendedorActual();
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
    }
} catch (mysqli_sql_exception $ex) {      // va antes: mysqli_sql_exception también es RuntimeException
    avisar('No pudimos guardar los cambios. Intenta de nuevo en un momento.', 'error');
} catch (RuntimeException $ex) {
    avisar($ex->getMessage(), 'error');
}
ir($volver);
