<?php
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';
require_once 'modelo/carrito.php';

$id = (int)($_GET['id'] ?? 0);
$p = obtenerProductoPorId($id);
$idu = compradorActual();
$idv = vendedorActual();
if ($p && !$p['visible'] && (int)$p['id_vendedor'] !== (int)$idv) $p = null;   // oculto: solo lo ve su artesano
$favs = obtenerIdsFavoritos($idu);

if ($p) {
    $titulo_pagina = $p['nombre'] . ' | Chaska Ruway';
    $resenas = obtenerResenas($id);
    $compro = $idu ? pedidoDeCompra($idu, $id) : null;
    $ya_reseno = $idu ? yaReseno($idu, $id) : false;
    $en_carrito = 0;
    if ($idu) {
        foreach (obtenerItemsCarrito($idu) as $it) if ((int)$it['id_producto'] === $id) $en_carrito = (int)$it['cantidad'];
    }
    $relacionados = obtenerRelacionados($id);
} else {
    $titulo_pagina = 'Producto no encontrado | Chaska Ruway';
}
$pagina = 'catalogo';
include 'includes/cabecera.php';
?>
  <?php if (!$p): ?>
    <div class="vacio" style="margin-top:2rem">
      <h3>Este producto no existe</h3>
      <p>Puede que lo hayan retirado del catálogo.</p>
      <a class="boton" href="index.php#catalogo">Volver al catálogo</a>
    </div>
  <?php else: ?>
    <p class="migas"><a href="index.php#catalogo">Catálogo</a> / <?= e($p['nombre']) ?></p>
    <div class="detalle">
      <?= portada($p, 'portada--grande') ?>
      <div class="detalle__info">
        <?php if (!$p['visible']): ?><p class="aviso aviso--error" style="margin:0 0 1rem">Este producto está oculto: los compradores no lo ven. Puedes volver a mostrarlo desde "Editar este producto".</p><?php endif; ?>
        <h1><?= e($p['nombre']) ?></h1>
        <p class="taller-linea">Hecho en <strong><?= e($p['nombre_taller']) ?></strong>, <?= e($p['region']) ?></p>
        <a href="#resenas" style="text-decoration:none"><?= estrellas($p['promedio'], (int)$p['num_resenas']) ?></a>
        <span class="precio"><?= soles($p['precio']) ?></span>
        <p class="detalle__desc"><?= e($p['descripcion']) ?></p>
        <p class="disponibles"><?= (int)$p['stock'] > 0
            ? '<strong>' . (int)$p['stock'] . '</strong> ' . ((int)$p['stock'] === 1 ? 'unidad disponible' : 'unidades disponibles')
            : '<span class="stock stock--agotado">Agotado por ahora</span>' ?></p>
        <?php if ($en_carrito): ?><span class="nota">Tienes <?= $en_carrito ?> en tu carrito.</span><?php endif; ?>

        <?php if ($idv): /* cuenta de vendedor: no compra */ ?>
          <?php if ((int)$p['id_vendedor'] === (int)$idv): ?>
            <div class="comprar"><a class="boton" style="grid-column:1/-1" href="vendedor_producto.php?id=<?= (int)$p['id_producto'] ?>">Editar este producto</a></div>
          <?php else: ?>
            <p class="ayuda">Estás viendo la tienda con tu cuenta de vendedor.</p>
          <?php endif; ?>
        <?php elseif ($p['stock'] > 0): ?>
          <form method="post" action="accion.php" class="comprar">
            <input type="hidden" name="accion" value="agregar">
            <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
            <div>
              <label for="cantidad">Cantidad</label>
              <input type="number" id="cantidad" name="cantidad" value="1" min="1" max="<?= (int)$p['stock'] ?>">
            </div>
            <button class="boton" type="submit">Agregar al carrito</button>
            <button class="boton boton--borde" type="submit" name="comprar_ya" value="1">Comprar ahora</button>
          </form>
        <?php else: ?>
          <div class="comprar"><button class="boton" disabled style="grid-column:1/-1">Agotado</button></div>
        <?php endif; ?>

        <?php if (!$idv): ?>
        <form method="post" action="accion.php" class="detalle__extra">
          <input type="hidden" name="accion" value="favorito">
          <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
          <input type="hidden" name="volver" value="<?= e(paginaActual()) ?>">
          <button class="enlace-boton" type="submit"><?= in_array((int)$p['id_producto'], $favs, true) ? '♥ Quitar de favoritos' : '♡ Guardar en favoritos' ?></button>
        </form>
        <?php endif; ?>

        <div class="garantias">
          <span><strong>Envío gratis</strong> a todo el Perú.</span>
          <span><strong>Pago</strong> con tarjeta, Yape, Plin, transferencia o efectivo al recibir.</span>
          <?php if ($p['taller_descripcion']): ?><span><strong><?= e($p['nombre_taller']) ?>:</strong> <?= e($p['taller_descripcion']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>

    <section id="resenas" class="resenas">
      <div>
        <h2>Reseñas</h2>
        <?php if ($p['num_resenas'] > 0): ?>
          <div class="resumen-nota"><?= number_format((float)$p['promedio'], 1) ?></div>
          <?= estrellas($p['promedio']) ?>
          <span class="nota"><?= (int)$p['num_resenas'] ?> <?= $p['num_resenas'] == 1 ? 'reseña' : 'reseñas' ?></span>
        <?php else: ?>
          <p class="ayuda">Todavía nadie ha reseñado este producto.</p>
        <?php endif; ?>

        <div style="margin-top:1.5rem">
          <?php if ($idv): ?>
            <p class="ayuda">Las reseñas las escriben los compradores.</p>
          <?php elseif (!$idu): ?>
          <?php elseif ($ya_reseno): ?>
            <p class="ayuda">Ya publicaste tu reseña de este producto.</p>
          <?php elseif (!$compro): ?>
            <p class="ayuda">Podrás dejar una reseña cuando compres este producto.</p>
          <?php else: ?>
            <form method="post" action="accion.php" class="caja">
              <input type="hidden" name="accion" value="resena">
              <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
              <fieldset class="elegir-estrellas">
                <legend>Tu calificación</legend>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <input type="radio" id="est<?= $i ?>" name="puntuacion" value="<?= $i ?>" <?= $i === 5 ? 'required' : '' ?>>
                  <label for="est<?= $i ?>" title="<?= $i ?> de 5">★<span class="sr"><?= $i ?> de 5</span></label>
                <?php endfor; ?>
              </fieldset>
              <div class="campo">
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" maxlength="150" placeholder="Resume tu experiencia">
              </div>
              <div class="campo">
                <label for="comentario">Comentario</label>
                <textarea id="comentario" name="comentario" placeholder="¿Cómo llegó? ¿Qué te pareció la pieza?"></textarea>
              </div>
              <button class="boton" type="submit">Publicar reseña</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <?php foreach ($resenas as $r): ?>
          <article class="resena">
            <?= estrellas($r['puntuacion']) ?>
            <?php if ($r['titulo']): ?><h3><?= e($r['titulo']) ?></h3><?php endif; ?>
            <?php if ($r['comentario']): ?><p><?= nl2br(e($r['comentario'])) ?></p><?php endif; ?>
            <span class="resena__autor"><?= e($r['nombres'] . ' ' . inicial($r['apellidos']) . '.') ?>, <?= fecha($r['fecha']) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($relacionados): ?>
      <section class="seccion">
        <h2>También te puede gustar</h2>
        <div class="rejilla">
          <?php foreach ($relacionados as $rp) echo tarjeta($rp, $favs, paginaActual()); ?>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
<?php include 'includes/pie.php'; ?>