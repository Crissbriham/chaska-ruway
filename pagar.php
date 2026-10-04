<?php
require_once 'includes/helpers.php';
require_once 'modelo/carrito.php';
require_once 'modelo/pedidos.php';

exigirSesion('pagar.php', 'Inicia sesión para continuar con tu compra.');
$idu = compradorActual();

/* Confirmar compra */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_metodo = (int)($_POST['id_metodo'] ?? 0);
    $dir = $_POST['id_direccion'] ?? '';
    try {
        if ($dir === 'nueva') {
            $d = [];
            foreach (['destinatario', 'telefono', 'departamento', 'provincia', 'distrito', 'direccion', 'referencia', 'alias'] as $c) {
                $d[$c] = trim($_POST[$c] ?? '');
            }
            foreach (['destinatario', 'departamento', 'provincia', 'distrito', 'direccion'] as $c) {
                if ($d[$c] === '') throw new RuntimeException('Completa la dirección nueva: quién recibe, departamento, provincia, distrito y dirección.');
            }
            $id_direccion = crearDireccion($idu, $d);
        } else {
            $id_direccion = (int)$dir;
        }
        $id_pedido = procesarCompra($idu, $id_metodo, $id_direccion);
        avisar('Listo. Registramos tu pedido #' . $id_pedido . '.');
        ir('pedidos.php?nuevo=' . $id_pedido);
    } catch (RuntimeException $ex) {
        avisar($ex->getMessage(), 'error');
        ir('pagar.php');
    }
}

$items = obtenerItemsCarrito($idu);
$total = 0;
$hay_problema = false;
foreach ($items as $it) {
    $total += $it['precio'] * $it['cantidad'];
    if ($it['cantidad'] > $it['stock']) $hay_problema = true;
}
if (!$items) { avisar('Tu carrito está vacío. Agrega productos para continuar.', 'error'); ir('carrito.php'); }
if ($hay_problema) { avisar('Algunos productos ya no tienen stock suficiente. Ajusta las cantidades.', 'error'); ir('carrito.php'); }

$direcciones = obtenerDirecciones($idu);
$metodos = obtenerMetodosPago($idu);
$yo = fila("SELECT nombres, apellidos, telefono FROM Compradores WHERE id_comprador = ?", "i", $idu);

$titulo_pagina = 'Confirmar compra | Chaska Ruway';
$pagina = 'pagar';
include 'includes/cabecera.php';
?>
  <h1 class="pagina-titulo">Confirmar compra</h1>
  <form method="post" action="pagar.php" class="dos-columnas">
    <div>
      <fieldset class="grupo">
        <legend>Dirección de envío</legend>
        <?php foreach ($direcciones as $i => $d): ?>
          <label class="opcion">
            <input type="radio" name="id_direccion" value="<?= (int)$d['id_direccion'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <div>
              <strong><?= e($d['alias'] ?: 'Dirección') ?></strong>
              <span><?= e($d['destinatario']) ?>, <?= e($d['direccion']) ?>, <?= e($d['distrito']) ?>, <?= e($d['provincia']) ?>, <?= e($d['departamento']) ?></span>
            </div>
          </label>
        <?php endforeach; ?>
        <label class="opcion">
          <input type="radio" id="dir-nueva" name="id_direccion" value="nueva" <?= !$direcciones ? 'checked' : '' ?>>
          <div><strong>Usar una dirección nueva</strong><span>Se guardará en tu cuenta para próximas compras.</span></div>
        </label>
        <div class="campos-nueva">
          <div class="campo completo"><label for="destinatario">Quién recibe</label><input type="text" id="destinatario" name="destinatario" value="<?= e(trim(($yo['nombres'] ?? '') . ' ' . ($yo['apellidos'] ?? ''))) ?>"></div>
          <div class="campo"><label for="telefono">Teléfono</label><input type="text" id="telefono" name="telefono" value="<?= e($yo['telefono'] ?? '') ?>"></div>
          <div class="campo"><label for="alias">Nombre de la dirección</label><input type="text" id="alias" name="alias" placeholder="Casa, oficina..."></div>
          <div class="campo"><label for="departamento">Departamento</label><input type="text" id="departamento" name="departamento" placeholder="Lima"></div>
          <div class="campo"><label for="provincia">Provincia</label><input type="text" id="provincia" name="provincia" placeholder="Lima"></div>
          <div class="campo"><label for="distrito">Distrito</label><input type="text" id="distrito" name="distrito" placeholder="Miraflores"></div>
          <div class="campo"><label for="direccion">Dirección</label><input type="text" id="direccion" name="direccion" placeholder="Av. Larco 123, dpto 401"></div>
          <div class="campo completo"><label for="referencia">Referencia (opcional)</label><input type="text" id="referencia" name="referencia" placeholder="Frente al parque"></div>
        </div>
      </fieldset>

      <fieldset class="grupo">
        <legend>Método de pago</legend>
        <?php foreach ($metodos as $i => $m): ?>
          <label class="opcion">
            <input type="radio" name="id_metodo" value="<?= (int)$m['id_metodo'] ?>" <?= $i === 0 ? 'checked' : '' ?> required>
            <div>
              <strong><?= e($m['nombre']) ?><?= $m['ultimos_digitos'] ? ' terminada en ' . e($m['ultimos_digitos']) : '' ?></strong>
              <span><?= e($m['alias'] ? 'Guardado como “' . $m['alias'] . '”' : $m['descripcion']) ?></span>
            </div>
          </label>
        <?php endforeach; ?>
      </fieldset>
    </div>

    <aside class="caja resumen">
      <h2>Tu pedido</h2>
      <ul>
        <?php foreach ($items as $it): ?>
          <li><span><?= (int)$it['cantidad'] ?> × <?= e($it['nombre']) ?></span><span><?= soles($it['precio'] * $it['cantidad']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <dl>
        <dt>Envío</dt><dd>Gratis</dd>
        <dt class="total">Total</dt><dd class="total"><?= soles($total) ?></dd>
      </dl>
      <button class="boton boton--ancho" type="submit">Confirmar compra por <?= soles($total) ?></button>
      <p class="nota-demo">Proyecto universitario de demostración: no se realiza ningún cobro real.</p>
      <p class="nota-demo"><a href="carrito.php">Volver al carrito</a></p>
    </aside>
  </form>
<?php include 'includes/pie.php'; ?>
