<?php
require_once 'includes/helpers.php';
require_once 'modelo/pedidos.php';

exigirSesion('pedidos.php', 'Inicia sesión para ver tus pedidos.');
$idu = compradorActual();
$nuevo = (int)($_GET['nuevo'] ?? 0);
$pedidos = obtenerPedidos($idu);
$detalle = obtenerDetallePedidos($idu);
$historial = obtenerHistorial($idu);
$pasos = obtenerPasosPedido();
$orden_estado = obtenerOrdenEstados();

$titulo_pagina = 'Mis pedidos | Chaska Ruway';
$pagina = 'pedidos';
include 'includes/cabecera.php';
?>
  <h1 class="pagina-titulo">Mis pedidos</h1>
  <?php if (!$pedidos): ?>
    <div class="vacio">
      <h3>Aún no tienes pedidos</h3>
      <p>Cuando compres, aquí verás el estado de cada pedido y su envío.</p>
      <a class="boton" href="index.php#catalogo">Ver el catálogo</a>
    </div>
  <?php else: foreach ($pedidos as $pe):
      $id_pe = (int)$pe['id_pedido'];
      $actual = (int)($orden_estado[$pe['estado_pedido']] ?? 1);
      $cancelado = $pe['estado_pedido'] === 'Cancelado';
  ?>
    <article class="pedido<?= $id_pe === $nuevo ? ' pedido--nuevo' : '' ?>">
      <div class="pedido__cabeza">
        <h2>Pedido #<?= $id_pe ?></h2>
        <span class="dato"><?= fecha($pe['fecha_pedido']) ?></span>
        <span class="dato">Total <strong><?= soles($pe['total_pedido']) ?></strong></span>
        <?php if ($pe['metodo']): ?><span class="dato"><?= e($pe['metodo']) ?></span><?php endif; ?>
        <span class="etiqueta etiqueta--<?= e($pe['estado_pedido']) ?>"><?= e($pe['estado_pedido']) ?></span>
      </div>
      <div class="pedido__cuerpo">
        <ul class="pedido__items">
          <?php foreach ($detalle[$id_pe] ?? [] as $d): ?>
            <li>
              <span><?= (int)$d['cantidad'] ?> × <a href="producto.php?id=<?= (int)$d['id_producto'] ?>"><?= e($d['nombre']) ?></a></span>
              <span><?= soles($d['precio_unitario'] * $d['cantidad']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <div>
          <?php if (!$cancelado): ?>
            <ol class="seguimiento" aria-label="Estado del pedido">
              <?php foreach ($pasos as $paso): $hecho = (int)$paso['orden'] <= $actual; ?>
                <li class="<?= $hecho ? 'hecho' : '' ?>">
                  <?= e($paso['nombre']) ?>
                  <?php if ($hecho && isset($historial[$id_pe][(int)$paso['id_estado']])): ?>
                    <small><?= fecha($historial[$id_pe][(int)$paso['id_estado']]) ?></small>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
          <?php if ($pe['direccion']): ?>
            <p class="envio-dato">Envío a <?= e($pe['direccion']) ?>, <?= e($pe['distrito']) ?>.</p>
          <?php endif; ?>
          <?php if ($pe['empresa_envio']): ?>
            <p class="envio-dato"><?= e($pe['empresa_envio']) ?>, código de seguimiento <strong><?= e($pe['numero_seguimiento']) ?></strong></p>
          <?php elseif (!$cancelado && $actual < 3): ?>
            <p class="envio-dato">Verás el código de seguimiento cuando despachemos tu pedido.</p>
          <?php endif; ?>
        </div>
      </div>
    </article>
  <?php endforeach; endif; ?>
<?php include 'includes/pie.php'; ?>
