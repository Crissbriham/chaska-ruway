<?php
require_once 'includes/helpers.php';
require_once 'modelo/vendedor.php';

exigirVendedor();
$idv = vendedorActual();
$todos = obtenerPedidosVendedor($idv);
$items = obtenerItemsPedidosVendedor($idv);

$filtros = ['despachar' => 'Por despachar', 'camino' => 'En camino', 'entregados' => 'Entregados', 'todos' => 'Todos'];
$ver = isset($filtros[$_GET['ver'] ?? '']) ? $_GET['ver'] : 'despachar';
$cuenta = array_fill_keys(array_keys($filtros), 0);
$pedidos = [];
foreach ($todos as $pe) {
    $claves = ['todos'];
    if (accionPedido($pe) === 'enviar') $claves[] = 'despachar';
    if ($pe['estado_pedido'] === 'Enviado') $claves[] = 'camino';
    if ($pe['estado_pedido'] === 'Entregado') $claves[] = 'entregados';
    foreach ($claves as $k) $cuenta[$k]++;
    if (in_array($ver, $claves, true)) $pedidos[] = $pe;
}

$titulo_pagina = 'Pedidos del taller | Chaska Ruway';
$pagina = 'v_pedidos';
$es_vendedor = true;
include 'includes/cabecera.php';
?>
  <h1 class="pagina-titulo">Pedidos de <?= e($_SESSION['taller']) ?></h1>

  <nav class="pestanas" aria-label="Filtrar pedidos">
    <?php foreach ($filtros as $k => $t): ?>
      <a href="vendedor_panel.php?ver=<?= $k ?>" <?= $ver === $k ? 'aria-current="page"' : '' ?>><?= $t ?><span class="n"><?= $cuenta[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$pedidos): ?>
    <div class="vacio">
      <h3>No hay pedidos en esta lista</h3>
      <p><?= $ver === 'despachar' ? 'Cuando alguien compre uno de tus productos y el pago esté confirmado, el pedido aparecerá aquí para despacharlo.' : 'Cambia de pestaña para ver otros pedidos.' ?></p>
    </div>
  <?php else: foreach ($pedidos as $pe):
      $id_pe = (int)$pe['id_pedido'];
      $acc = accionPedido($pe);
      $lineas = $items[$id_pe] ?? [];
      $mio = 0; $hay_otros = false;
      foreach ($lineas as $l) { if ($l['mio']) $mio += $l['precio_unitario'] * $l['cantidad']; else $hay_otros = true; }
      $efectivo_pendiente = ($pe['metodo'] ?? '') === 'Efectivo' && ($pe['estado_pago'] ?? '') === 'Pendiente';
  ?>
    <article class="pedido">
      <div class="pedido__cabeza">
        <h2>Pedido #<?= $id_pe ?></h2>
        <span class="dato"><?= fecha($pe['fecha_pedido']) ?></span>
        <span class="dato">Tus productos <strong><?= soles($mio) ?></strong></span>
        <?php if ($hay_otros): ?><span class="dato">Total del pedido <?= soles($pe['total_pedido']) ?></span><?php endif; ?>
        <?php if ($pe['metodo']): ?><span class="dato"><?= e($pe['metodo']) ?></span><?php endif; ?>
        <span class="etiqueta etiqueta--<?= e($pe['estado_pedido']) ?>"><?= e($pe['estado_pedido']) ?></span>
      </div>
      <div class="pedido__cuerpo">
        <div>
          <ul class="pedido__items">
            <?php foreach ($lineas as $l): ?>
              <li<?= $l['mio'] ? '' : ' class="otro-taller"' ?>>
                <span><?= (int)$l['cantidad'] ?> × <?= e($l['nombre']) ?><?= $l['mio'] ? '' : ' (' . e($l['nombre_taller']) . ')' ?></span>
                <span><?= soles($l['precio_unitario'] * $l['cantidad']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($hay_otros): ?><p class="nota-demo">Este pedido también incluye productos de otros talleres. El envío es uno solo para todo el pedido.</p><?php endif; ?>
        </div>
        <div>
          <p class="envio-dato"><strong>Comprador:</strong> <?= e($pe['nombres'] . ' ' . $pe['apellidos']) ?></p>
          <?php if ($pe['direccion']): ?>
            <p class="envio-dato"><strong>Enviar a:</strong> <?= e($pe['destinatario']) ?>, <?= e($pe['direccion']) ?>, <?= e($pe['distrito']) ?>, <?= e($pe['provincia']) ?>, <?= e($pe['departamento']) ?><?= $pe['telefono'] ? ' · Tel. ' . e($pe['telefono']) : '' ?></p>
            <?php if ($pe['referencia']): ?><p class="envio-dato"><strong>Referencia:</strong> <?= e($pe['referencia']) ?></p><?php endif; ?>
          <?php endif; ?>
          <?php if ($pe['empresa_envio']): ?>
            <p class="envio-dato"><strong><?= e($pe['empresa_envio']) ?></strong>, código <strong><?= e($pe['numero_seguimiento']) ?></strong></p>
          <?php endif; ?>

          <?php if ($acc === 'enviar'): ?>
            <form method="post" action="vendedor_accion.php" class="acciones-pedido">
              <input type="hidden" name="accion" value="enviar">
              <input type="hidden" name="id_pedido" value="<?= $id_pe ?>">
              <input type="hidden" name="volver" value="<?= e(paginaActual()) ?>">
              <div class="fila-envio">
                <div class="campo">
                  <label for="emp<?= $id_pe ?>">Empresa de envío</label>
                  <select id="emp<?= $id_pe ?>" name="empresa">
                    <?php foreach (array_keys(EMPRESAS_ENVIO) as $emp): ?><option><?= e($emp) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="campo">
                  <label for="cod<?= $id_pe ?>">Código de seguimiento</label>
                  <input type="text" id="cod<?= $id_pe ?>" name="codigo" maxlength="100" placeholder="Opcional: se genera solo">
                </div>
              </div>
              <?php if ($efectivo_pendiente): ?><p class="envio-dato">Pago en efectivo: el cliente paga <?= soles($pe['total_pedido']) ?> al recibir.</p><?php endif; ?>
              <button class="boton" type="submit">Marcar como enviado</button>
            </form>
          <?php elseif ($acc === 'entregar'): ?>
            <form method="post" action="vendedor_accion.php" class="acciones-pedido">
              <input type="hidden" name="accion" value="entregar">
              <input type="hidden" name="id_pedido" value="<?= $id_pe ?>">
              <input type="hidden" name="volver" value="<?= e(paginaActual()) ?>">
              <button class="boton" type="submit">Marcar como entregado</button>
            </form>
          <?php elseif ($pe['estado_pedido'] === 'Pendiente'): ?>
            <p class="envio-dato acciones-pedido">Esperando la confirmación del pago.</p>
          <?php endif; ?>
        </div>
      </div>
    </article>
  <?php endforeach; endif; ?>
<?php include 'includes/pie.php'; ?>
