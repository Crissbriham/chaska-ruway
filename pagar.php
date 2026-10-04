<?php
// pagar.php - Pantalla de confirmación y procesamiento de pago
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';
require_once 'includes/helpers.php';
require_once 'modelo/carrito.php';
require_once 'modelo/pedidos.php';

$id_comprador_actual = isset($_SESSION['id_comprador']) ? $_SESSION['id_comprador'] : 1;
$id_carrito = obtenerOCrearCarrito($conexion, $id_comprador_actual);
$items = obtenerItemsCarrito($conexion, $id_carrito);

// Si el carrito está vacío, redirigir al catálogo
if (empty($items)) {
    header('Location: index.php');
    exit;
}

$mensaje_error = '';
$mensaje_exito = '';

// Al enviar el formulario de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo_pago = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : 'Yape / Plin';
    
    // Ejecutamos el procedimiento almacenado
    $resultado = procesarCompra($conexion, $id_carrito, $id_comprador_actual, $metodo_pago);
    
    if ($resultado['exito']) {
        $mensaje_exito = $resultado['mensaje'];
    } else {
        $mensaje_error = $resultado['mensaje'];
    }
}

include 'includes/cabecera.php';
?>

<div class="seccion-pagar">
    <h1>💳 Finalizar Compra</h1>

    <?php if ($mensaje_exito): ?>
        <div class="alerta-exito">
            <p><?= htmlspecialchars($mensaje_exito); ?></p>
            <p><a href="index.php" class="btn-detalles">Volver al Catálogo</a></p>
        </div>
    <?php else: ?>
        <?php if ($mensaje_error): ?>
            <div class="alerta-error">
                <p><?= htmlspecialchars($mensaje_error); ?></p>
            </div>
        <?php endif; ?>

        <div class="contenedor-pago-grid">
            <div class="resumen-pago">
                <h3>Resumen de tu pedido</h3>
                <ul>
                    <?php $total = 0; foreach ($items as $item): 
                        $sub = $item['cantidad'] * $item['precio_unitario'];
                        $total += $sub;
                    ?>
                        <li><?= htmlspecialchars($item['nombre']); ?> (x<?= $item['cantidad']; ?>) - <?= formatearPrecio($sub); ?></li>
                    <?php endforeach; ?>
                </ul>
                <h4>Total a cancelar: <?= formatearPrecio($total); ?></h4>
            </div>

            <form action="pagar.php" method="POST" class="form-pago">
                <h3>Selecciona tu método de pago</h3>
                <div class="opciones-pago">
                    <label>
                        <input type="radio" name="metodo_pago" value="Yape / Plin" checked> Yape / Plin (Billetera Digital)
                    </label>
                    <label>
                        <input type="radio" name="metodo_pago" value="Tarjeta de Crédito / Débito"> Tarjeta de Crédito / Débito
                    </label>
                    <label>
                        <input type="radio" name="metodo_pago" value="Transferencia Bancaria"> Transferencia Bancaria (BCP / BBVA)
                    </label>
                </div>

                <button type="submit" class="btn-confirmar-pago">Confirmar y Pagar</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
include 'includes/pie.php';
?>